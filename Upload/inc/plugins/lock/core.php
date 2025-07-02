<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/plugins/lock/core.php)
 *    Author: Neko
 *    Maintainer: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to hide content in their posts in exchange for replies or NewPoints currency.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace LockContent\Core;

use Exception;
use RangeException;
use Shortcodes;
use SodiumException;
use Random\RandomException;

use const LockContent\DEBUG;
use const LockContent\ROOT;
use const LockContent\SETTINGS;

function addHooks(string $namespace): void
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) === $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }
}

function loadLanguage(): bool
{
    global $lang;

    if (!isset($lang->lock)) {
        $lang->load('lock');
    }

    return true;
}

function getTemplateName(string $templateName = ''): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    return "lock{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true): string
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

function getSetting(string $settingKey = '')
{
    global $mybb;

    return SETTINGS[$settingKey] ?? (
        $mybb->settings['lock_' . $settingKey] ?? false
    );
}

function purchaseLogInsert(array $logData): int
{
    global $db;

    $insertData = [];

    if (isset($logData['user_id'])) {
        $insertData['user_id'] = (int)$logData['user_id'];
    }

    if (isset($logData['post_id'])) {
        $insertData['post_id'] = (int)$logData['post_id'];
    }

    if (isset($logData['purchase_stamp'])) {
        $insertData['purchase_stamp'] = (int)$logData['purchase_stamp'];
    }

    return (int)$db->insert_query('ougc_lock_content_logs', $insertData);
}

function purchaseLogGet(array $whereClauses, array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $queryFields[] = 'log_id';

    $query = $db->simple_select(
        'ougc_lock_content_logs',
        implode(',', $queryFields),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $logObjects = [];

    while ($logData = $db->fetch_array($query)) {
        $logObjects[(int)$logData['log_id']] = $db->fetch_array($query);
    }

    return $logObjects;
}

function shortcodeObject(): Shortcodes
{
    static $lockedContent = null;

    if (!is_object($lockedContent) || !($lockedContent instanceof Shortcodes)) {
        require_once ROOT . '/shortcodes.class.php';

        $lockedContent = new Shortcodes(lock_tag: getSetting('type'));
    }

    return $lockedContent;
}

/**
 * @throws RandomException
 * @throws SodiumException
 */
function hideMessageContents(array $attributes, string $message): string
{
    global $mybb, $post, $lang, $db;

    loadLanguage();

    // if the tag has no content, do nothing.
    if (!$message) {
        return '';
    }

    // return nothing if the print thread page is viewed
    if (empty($post['pid'])) {
        return $lang->lock_title;
    }

    $post_id = (int)$post['pid'];

    if (empty($post['fid'])) {
        $post_data = get_post($post_id);

        $forum_id = (int)$post_data['fid'];
    } else {
        $forum_id = (int)$post['fid'];
    }

    if (empty($post['tid'])) {
        $post_data = $post_data ?? get_post($post_id);

        $thread_id = (int)$post_data['tid'];
    } else {
        $thread_id = (int)$post['tid'];
    }

    if (isset($attributes[0]) && my_strpos($attributes[0], '=') === 0) {
        $attributes['content_points'] = (float)str_replace('=', '', $attributes[0]);
    }

    $paid = false;

    $current_user_id = (int)$mybb->user['uid'];

    // does the user have to pay for the content?
    if (function_exists('newpoints_format_points') &&
        (!empty($mybb->settings['lock_purchases_enabled']) || getSetting('default_price') > 0)) {
        // is the pay to view feature allowed in this forum?
        $disabled = explode(',', $mybb->settings['lock_disabled_forums']);

        if (!in_array($forum_id, $disabled) || $mybb->settings['lock_disabled_forums'] === -1) {
            // does the content have a price? can the user set the price?
            if (!isset($attributes['content_points'])) {
                // if not, do we have a default price?
                if (getSetting('default_price') > 0) {
                    $attributes['content_points'] = (float)getSetting('default_price');
                } else {
                    $attributes['content_points'] = null;
                }
            } elseif (empty($mybb->settings['lock_allow_user_prices']) && getSetting('default_price') > 0) {
                $attributes['content_points'] = (float)getSetting('default_price');
            }

            // is the cost an actual number?
            if (is_numeric($attributes['content_points'])) {
                // cost must be valid, because numbers aren't evil.
                $content_points = (float)$attributes['content_points'];

                // check to see whether the user has purchased this post's content
                $paid = (bool)purchaseLogGet(
                    ["user_id={$current_user_id}", "post_id={$post_id}"],
                    queryOptions: ['limit' => 1]
                );
            }
        }
    }

    static $posted = null;

    if (!isset($content_points) && $posted === null) {
        // if there's no cost, this must be a "post to view" hide tag

        // check to see whether the user has posted in this thread.
        $query = $db->simple_select(
            'posts',
            '*',
            "tid = '{$thread_id}' AND uid = '{$current_user_id}'"
        );//  AND visible='1' ?

        $posted = (bool)$db->num_rows($query);
    }

    // if no title has been set, set a default title.
    $title = $attributes['title'] ?? $lang->lock_title;

    // if the user is not the OP, and has not been exempt from having hidden content
    if (
        $current_user_id !== (int)$post['uid'] &&
        !is_member($mybb->settings['lock_exempt'])
    ) {
        // if the user isn't logged in, tell them to login or register.
        if (!$current_user_id) {
            $contents = $lang->sprintf($lang->lock_nopermission_guest, $mybb->settings['bburl']);
            // if they are logged in, but the item has a price that they haven't paid yet, tell them how they can pay for it.
        } elseif (isset($content_points) && !$paid && function_exists('newpoints_format_points')) {
            // place the info we need, into an array
            $contentDetails = [
                'post_id' => $post_id,
                'content_points' => $content_points
            ];

            // encode the information as json, for safe transit
            $contentDetails = json_encode($contentDetails);

            // encrypt the json, and encode it as base64; so it can be submitted in a form.

            $contentDetails = base64_encode(safeEncrypt($contentDetails, $mybb->post_code));

            static $posts_content_points = [];

            if (!isset($posts_content_points[$post['pid']])) {
                $posts_content_points[$post['pid']] = $content_points;
            }

            $posts_content_points[$post['pid']] = max($posts_content_points[$post['pid']], $content_points);

            $points = strip_tags(newpoints_format_points((float)$posts_content_points[$post['pid']]));

            $user_points = $lang->sprintf(
                $lang->lock_purchase_yougot,
                strip_tags(newpoints_format_points((float)$mybb->user['newpoints']))
            );

            $confirmMessage = $lang->sprintf($lang->lock_purchase_confirm, $points);
            $buttonText = $lang->sprintf($lang->lock_purchase, $points);

            $threadUrl = get_thread_link($thread_id);

            $formMessage = $lang->lock_purchase_desc;

            // build the return button.
            $contents = eval(getTemplate('form', false));
            // if the user doesn't need to pay, but hasn't posted

        } elseif (!$paid && !$posted) {
            // tell them to reply to the thread.

            $contents = $lang->lock_nopermission_reply;
            // all is good.
        } else {
            // give them the content.
            $contents = $message;
        }
        // bypass the hide tags.
    } else {
        // give them the content
        $contents = $message;
    }

    $cost_desc = '';

    if (isset($content_points) && function_exists('newpoints_format_points') && !isset($points)) {
        $points = newpoints_format_points($content_points);

        $cost_desc = $lang->sprintf($lang->lock_purchase_cost, $points);
    }

    return eval(getTemplate('wrapper', false));
}

// the following functions replace the old encrypt logic with stock php encryption logic
// https://stackoverflow.com/a/30159120
// todo: I think there is no need for encryption on this plugin but maybe I'm wrong so it will remain for now
/**
 * Encrypt a message
 *
 * @param string $message - message to encrypt
 * @param string $key - encryption key
 * @return string
 * @throws RandomException
 * @throws SodiumException
 */
function safeEncrypt(string $message, string $key): string
{
    if (mb_strlen($key, '8bit') !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RangeException('Key is not the correct size (must be 32 bytes).');
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $cipher = base64_encode(
        $nonce .
        sodium_crypto_secretbox(
            $message,
            $nonce,
            $key
        )
    );

    sodium_memzero($message);

    sodium_memzero($key);

    return $cipher;
}

/**
 * Decrypt a message
 *
 * @param string $encrypted - message encrypted with safeEncrypt()
 * @param string $key - encryption key
 * @return string
 * @throws Exception
 */
function safeDecrypt(string $encrypted, string $key): string
{
    $decoded = base64_decode($encrypted);
    $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

    $plain = sodium_crypto_secretbox_open(
        $ciphertext,
        $nonce,
        $key
    );

    if (!is_string($plain)) {
        throw new Exception('Invalid MAC');
    }

    sodium_memzero($ciphertext);

    sodium_memzero($key);

    return $plain;
}
