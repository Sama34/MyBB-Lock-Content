<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/plugins/ougc/DisplayName/hooks/forum.php)
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

namespace LockContent\Hooks\Forum;

use Exception;

use function LockContent\Core\getSetting;
use function LockContent\Core\purchaseLogGet;
use function LockContent\Core\purchaseLogInsert;
use function LockContent\Core\safeDecrypt;
use function LockContent\Core\shortcodeObject;
use function Newpoints\Core\language_load;
use function NewPoints\Core\log_add;
use function NewPoints\Core\points_add_simple;
use function NewPoints\Core\points_subtract;

use function Newpoints\Core\post_parser;

use const NewPoints\Core\LOGGING_TYPE_CHARGE;
use const NewPoints\Core\LOGGING_TYPE_INCOME;

function global_start(): bool
{
    global $templatelist;

    if (isset($templatelist)) {
        $templatelist .= ',';
    } else {
        $templatelist = '';
    }

    if (THIS_SCRIPT == 'showthread.php') {
        $templatelist .= ', lock_' . implode(', lock_', ['form', 'wrapper']);
    }

    return true;
}

/**
 * @throws Exception
 */
function showthread_start(): void
{
    global $mybb, $db;
    global $thread;

    if ($mybb->get_input('action') !== 'purchase' ||
        $mybb->request_method !== 'post' ||
        empty($mybb->settings['lock_purchases_enabled']) ||
        !function_exists('newpoints_format_points')) {
        return;
    }

    verify_post_check($mybb->get_input('my_post_key'));

    $json = safeDecrypt(base64_decode($mybb->get_input('info')), $mybb->post_code);

    $contentDetails = json_decode($json);

    global $lang;

    if (empty($contentDetails) || !is_object($contentDetails)) {
        error($lang->error_invalidpost);
    }

    // if the data is indeed json data
    // if the data has been successfully turned back into an object.
    $content_points = (float)$contentDetails->content_points;

    $post_id = (int)$contentDetails->post_id;

    if (!$content_points ||
        !$post_id ||
        (empty($mybb->settings['lock_allow_user_prices']) && getSetting('default_price') <= 0)) {
        error($lang->error_invalidpost);
    }

    $query = $db->simple_select('posts', 'tid, uid, message', "pid = '{$post_id}'");

    if (!$db->num_rows($query)) {
        error($lang->error_invalidpost);
    }

    $post_data = $db->fetch_array($query);

    if ((int)$post_data['tid'] !== (int)$thread['tid']) {
        error($lang->error_invalidpost);
    }

    $post_user_id = (int)$post_data['uid'];

    $higher_content_points = 0;

    shortcodeObject()->get_higher_points_from_message($post_data['message'], $higher_content_points);

    if (!empty($mybb->settings['lock_allow_user_prices'])) {
        $content_points = max($higher_content_points, $content_points); // too much?
    } else {
        $content_points = (float)getSetting('default_price');
    }

    $current_user_id = (int)$mybb->user['uid'];

    // check to see whether the user has purchased this post's content
    if (!purchaseLogGet(["user_id={$current_user_id}", "post_id={$post_id}"], queryOptions: ['limit' => 1])) {
        if ($mybb->user['newpoints'] < $content_points) {
            \LockContent\Core\loadLanguage();

            // user does not have enough funds to pay for the item
            error($lang->lock_purchase_error_no_funds);
        }

        // take the points from the user

        points_subtract($current_user_id, $content_points);

        log_add(
            'lock_content_purchase',
            '',
            $mybb->user['username'] ?? '',
            $current_user_id,
            $content_points,
            $post_id,
            $post_user_id,
            0,
            LOGGING_TYPE_CHARGE
        );

        if (is_numeric($mybb->settings['lock_tax']) && $mybb->settings['lock_tax'] > 0) {
            $tax = $mybb->settings['lock_tax'];
        }

        $tax = (float)($tax ?? 0);

        if ($tax > 100) {
            $tax = 100;
        }

        if ($tax) {
            $content_points = $content_points - ($content_points / 100 * $tax);
        }

        // give them to the creator of the post

        points_add_simple($post_user_id, $content_points);

        log_add(
            'lock_content_sell',
            '',
            get_user($post_user_id)['username'] ?? '',
            $post_user_id,
            $content_points,
            $post_id,
            $current_user_id,
            0,
            LOGGING_TYPE_INCOME
        );

        // add the purchase log
        purchaseLogInsert(['user_id' => $current_user_id, 'post_id' => $post_id, 'purchase_stamp' => TIME_NOW]);
    }

    $url = $mybb->settings['bburl'] . '/' . get_post_link($post_id) . '#pid' . $post_id;

    header('Location: ' . $url);

    exit();
}

function parse_message_start11(string &$message): string
{
    global $mybb;

    if (!empty($mybb->input['highlight'])) {
        shortcodeObject()->refresh_highlight_replacement();

        $message = str_replace(
            shortcodeObject()->get_lock_tag(),
            shortcodeObject()->get_highlight_replacement(),
            $message
        );
    }

    return $message;
}

function parse_message09(string &$message): string
{
    global $mybb;

    if (!empty($mybb->input['highlight'])) {
        $message = str_replace(
            shortcodeObject()->get_highlight_replacement(),
            shortcodeObject()->get_lock_tag(),
            $message
        );
    }

    return $message;
}

function parse_message_end(string &$message): string
{
    return shortcodeObject()->parse($message);
}

function parse_quoted_message(array &$quoted_post): array
{
    $tag = shortcodeObject()->get_lock_tag();

    $quoted_post['message'] = preg_replace(
        '#\[' . $tag . '(.*)\[/' . $tag . '\]#is',
        '',
        $quoted_post['message']
    );

    return $quoted_post;
}

function newpoints_logs_log_row(): bool
{
    global $log_data;

    if (!in_array($log_data['action'], [
        'lock_content_purchase',
        'lock_content_sell',
    ])) {
        return false;
    }

    global $lang;
    global $log_action, $log_primary, $log_secondary, $log_tertiary;

    \LockContent\Core\loadLanguage();

    if ($log_data['action'] === 'lock_content_purchase') {
        $log_action = $lang->lock_content_newpoints_page_logs_purchase;
    }

    if ($log_data['action'] === 'lock_content_sell') {
        $log_action = $lang->lock_content_newpoints_page_logs_sell;
    }

    $post_id = (int)$log_data['log_primary_id'];

    $post_data = get_post($post_id);

    if (!empty($post_data['tid'])) {
        $thread_id = (int)$post_data['tid'];

        $thread_data = get_thread($thread_id);
    }

    if (!(empty($post_data) || empty($post_data['visible']) || empty($thread_data) || empty($thread_data['visible']))) {
        global $mybb;

        $current_user_id = (int)$mybb->user['uid'];

        $forum_permissions = forum_permissions($thread_data['fid']);

        if (!(empty($forum_permissions['canview']) ||
            empty($forum_permissions['canviewthreads']) ||
            (!empty($forum_permissions['canonlyviewownthreads']) && (int)$thread_data['uid'] !== $current_user_id))) {
            $log_primary = $lang->sprintf(
                $lang->lock_content_newpoints_page_logs_post_link,
                $mybb->settings['bburl'],
                get_post_link($post_id) . '#pid' . $post_id,
                post_parser()->parse_badwords($post_data['subject'] ?? $thread_data['subject'])
            );
        }
    }

    $purchaser_seller_user_id = (int)$log_data['log_secondary_id'];

    $user_data = get_user($purchaser_seller_user_id);

    if (!empty($user_data['uid'])) {
        $log_secondary = build_profile_link(
            format_name(
                htmlspecialchars_uni($user_data['username']),
                $user_data['usergroup'],
                $user_data['displaygroup'],
            ),
            $user_data['uid']
        );
    }

    return true;
}

function newpoints_logs_end(): bool
{
    global $lang;
    global $action_types;

    \LockContent\Core\loadLanguage();

    foreach ($action_types as $key => &$action_type) {
        if ($key === 'lock_content_purchase') {
            $action_type = $lang->lock_content_newpoints_page_logs_purchase;
        }

        if ($key === 'lock_content_sell') {
            $action_type = $lang->lock_content_newpoints_page_logs_sell;
        }
    }

    return true;
}