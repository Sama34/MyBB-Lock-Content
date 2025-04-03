<?php

/***************************************************************************
 *
 *    Lock plugin (/inc/plugins/lock/core/purchase.php)
 *    Author: Neko
 *    Maintainer: Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Lock is a MyBB plugin for hiding content and selling it for your Newpoints currency.
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

use function Newpoints\Core\log_add;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_subtract;

use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;

global $mybb, $db;

// if the action is not purchase, we don't need to continue.
if ($mybb->get_input('action') !== 'purchase') {
    return;
}

// if the purchases functionality has not been enabled, we do not need to continue.
if (empty($mybb->settings['lock_purchases_enabled']) || !function_exists('newpoints_format_points')) {
    return;
}

$key = $mybb->settings['lock_key'];

// include the pcrypt class, so we can decrypt the sent info.
require_once __DIR__ . '/../pcrypt.php';
$pcrypt = new pcrypt();

$pcrypt = $pcrypt->pcrypt(MODE_ECB, 'BLOWFISH', $key);

// convert the sent info back into json data
$json = $pcrypt->decrypt(base64_decode($_POST['info']));

// if the data is indeed json data
if ($info = json_decode($json)) {
    // if the data has been successfully turned back into an object.
    if (is_object($info)) {
        $lock_price = (float)$info->cost;

        $post_id = (int)$info->pid;

        // if the cost and post id are not numbers, return an error.
        if (!$lock_price || !$post_id) {
            error('Something went wrong: NaN');
        }

        $query = $db->simple_select('posts', 'uid, message, unlocked', "pid = '{$post_id}'");

        if ($db->num_rows($query)) {
            $post_data = $db->fetch_array($query);
        } else {
            return;
        }

        $post_user_id = (int)$post_data['uid'];

        $higher_price = 0;

        Shortcodes::get_higher_price_from_message($post_data['message'], $higher_price);

        if (!empty($mybb->settings['lock_allow_user_prices'])) {
            $lock_price = max($higher_price, $lock_price); // too much?
        } else {
            $params['cost'] = (int)$mybb->settings['lock_default_price'];
        }

        // check whether the current user has already unlocked the content
        $allowed = explode(',', $post_data['unlocked'] ?? '');

        if (!is_array($allowed)) {
            $allowed = [];
        }

        $current_user_id = (int)$mybb->user['uid'];

        if (!in_array($current_user_id, $allowed)) {
            // user doesn't have it unlocked
            if ($mybb->user['newpoints'] < $lock_price) {
                // user does not have enough funds to pay for the item
                error('You do not have enough points to purchase this item.');
            } else {
                // take the points from the user

                if (function_exists('\Newpoints\Core\log_add')) {
                    points_subtract($current_user_id, $lock_price);

                    log_add(
                        'lock_purchase',
                        '',
                        $mybb->user['username'] ?? '',
                        $current_user_id,
                        $lock_price,
                        $post_id,
                        0,
                        0,
                        LOGGING_TYPE_CHARGE
                    );
                } else {
                    newpoints_addpoints($current_user_id, -$lock_price);

                    newpoints_log(
                        'lock_purchase',
                        '',
                        $mybb->user['username'] ?? '',
                        $current_user_id
                    );
                }

                if (is_numeric($mybb->settings['lock_tax']) && $mybb->settings['lock_tax'] > 0) {
                    $tax = $mybb->settings['lock_tax'];
                }

                $tax = (int)($tax ?? 0);

                if ($tax > 100) {
                    $tax = 100;
                }

                if ($tax) {
                    $lock_price = $lock_price - ($lock_price / 100 * $tax);
                }

                // give them to the creator of the post

                if (function_exists('\Newpoints\Core\log_add')) {
                    points_add_simple($post_user_id, $lock_price);

                    log_add(
                        'lock_purchase',
                        '',
                        get_user($post_user_id)['username'] ?? '',
                        $post_user_id,
                        $lock_price,
                        $post_id,
                        0,
                        0,
                        LOGGING_TYPE_INCOME
                    );
                } else {
                    newpoints_addpoints($post_user_id, $lock_price);

                    newpoints_log(
                        'lock_purchase',
                        '',
                        get_user($post_user_id)['username'] ?? '',
                        $post_user_id
                    );
                }

                // add the user to the list of people with access to the content
                $allowed[] = $current_user_id;
                $allowed = implode(',', $allowed);

                $unlocked = [
                    'unlocked' => $allowed,
                ];

                $db->update_query('posts', $unlocked, "pid='{$post_id}'");
            }
        }

        $url = $mybb->settings['bburl'] . '/' . get_post_link($post_id) . '#pid' . $post_id;

        header('Location: ' . $url);

        exit();
    }
}