<?php

/***************************************************************************
 *
 *    Lock plugin (/inc/plugins/lock/core/shortcode.php)
 *    Author: Neko
 *    Maintainer: Omar Gonzalez
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

use Random\RandomException;

use function LockContent\Core\getSetting;
use function LockContent\Core\getTemplate;
use function LockContent\Core\safeEncrypt;
use function LockContent\Core\shortcodeObject;

/**
 * @throws RandomException
 * @throws SodiumException
 */
function lock_hide(array $params, string $message): string
{
    global $mybb, $post, $lang, $db;

    isset($lang->lock) || $lang->load('lock');

    // if the tag has no content, do nothing.
    if (!$message) {
        return '';
    }

    // return nothing if the print thread page is viewed
    if (empty($post['pid'])) {
        return $lang->lock_title;
    }

    if (isset($params[0]) && my_strpos($params[0], '=') === 0) {
        $params['cost'] = (int)str_replace('=', '', $params[0]);
    }

    $paid = false;

    // does the user have to pay for the content?
    if (function_exists('newpoints_format_points') &&
        (!empty($mybb->settings['lock_purchases_enabled']) || getSetting('default_price') > 0)) {
        // is the pay to view feature allowed in this forum?
        $disabled = explode(',', $mybb->settings['lock_disabled_forums']);
        if (!in_array($post['fid'], $disabled) || $mybb->settings['lock_disabled_forums'] === -1) {
            // does the content have a price? can the user set the price?
            if (!isset($params['cost'])) {
                // if not, do we have a default price?
                if (getSetting('default_price') > 0) {
                    $params['cost'] = (float)getSetting('default_price');
                } else {
                    $params['cost'] = null;
                }
            } elseif (empty($mybb->settings['lock_allow_user_prices']) && getSetting('default_price') > 0) {
                $params['cost'] = (float)getSetting('default_price');
            }

            // is the cost an actual number?
            if (is_numeric($params['cost'])) {
                // cost must be valid, because numbers aren't evil.
                $cost = (float)$params['cost'];

                // check to see whether the user hasn't already unlocked the content.
                $allowed = explode(',', $post['unlocked'] ?? '');

                if (in_array($mybb->user['uid'], $allowed)) {
                    $paid = true;
                }
            }
        }
    }

    static $posted = null;

    if (!isset($cost) && $posted === null) {
        // if there's no cost, this must be a "post to view" hide tag

        // check to see whether the user has posted in this thread.
        $query = $db->simple_select(
            'posts',
            '*',
            "tid = '{$post['tid']}' AND uid = '{$mybb->user['uid']}'"
        );//  AND visible='1' ?

        $posted = (bool)$db->num_rows($query);
    }

    // if no title has been set, set a default title.
    $title = $params['title'] ?? $lang->lock_title;

    // if the user is not the OP, and has not been exempt from having hidden content
    if (
        $mybb->user['uid'] !== $post['uid'] &&
        !is_member($mybb->settings['lock_exempt'])
    ) {
        // if the user isn't logged in, tell them to login or register.
        if (!$mybb->user['uid']) {
            $return = $lang->sprintf($lang->lock_nopermission_guest, $mybb->settings['bburl']);
            // if they are logged in, but the item has a price that they haven't paid yet, tell them how they can pay for it.
        } elseif (isset($cost) && !$paid && function_exists('newpoints_format_points')) {
            // place the info we need, into an array
            $info = [
                'pid' => (int)$post['pid'],
                'cost' => $cost
            ];

            // encode the information as json, for safe transit
            $info = json_encode($info);

            // encrypt the json, and encode it as base64; so it can be submitted in a form.

            $info = base64_encode(safeEncrypt($info, $mybb->post_code));

            static $posts_prices = [];

            if (!isset($posts_prices[$post['pid']])) {
                $posts_prices[$post['pid']] = $cost;
            }

            $posts_prices[$post['pid']] = max($posts_prices[$post['pid']], $cost);

            $points = strip_tags(newpoints_format_points((float)$posts_prices[$post['pid']]));

            $user_points = $lang->sprintf(
                $lang->lock_purchase_yougot,
                strip_tags(newpoints_format_points((float)$mybb->user['newpoints']))
            );

            $lang_confirm = $lang->sprintf($lang->lock_purchase_confirm, $points);
            $lock_purchase = $lang->sprintf($lang->lock_purchase, $points);

            // build the return button.
            $return = eval(getTemplate('form', false));
            // if the user doesn't need to pay, but hasn't posted

        } elseif (!$paid && !$posted) {
            // tell them to reply to the thread.

            $return = $lang->lock_nopermission_reply;
            // all is good.
        } else {
            // give them the content.
            $return = $message;
        }
        // bypass the hide tags.
    } else {
        // give them the content
        $return = $message;
    }

    $cost_desc = '';

    if (isset($cost) && function_exists('newpoints_format_points') && !isset($points)) {
        $points = newpoints_format_points($cost);

        $cost_desc = $lang->sprintf($lang->lock_purchase_cost, $points);
    }

    return eval(getTemplate('wrapper', false));
}