<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/plugins/ougc/DisplayName/hooks/shared.php)
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

namespace LockContent\Hooks\Shared;

use PostDataHandler;

use function LockContent\Core\loadLanguage;
use function LockContent\Core\shortcodeObject;

// validate maximum points
function datahandler_post_validate_post(PostDataHandler &$dataHandler): PostDataHandler
{
    global $lang;

    $post_data = $dataHandler->data;

    $post_user_id = (int)$post_data['uid'];

    $user_permissions = user_permissions($post_user_id);

    $maximum_content_points = (float)$user_permissions['lock_maxcost'];

    // todo: moderator bypass doesn't make much sense to me but we will leave it for now
    if ($maximum_content_points && is_moderator($dataHandler->data['fid'])) {
        global $mybb;

        $maximum_content_points = max((float)$mybb->usergroup['lock_maxcost'], $maximum_content_points);
    }

    if (
        $maximum_content_points <= 0 ||
        !function_exists('newpoints_format_points')) {
        return $dataHandler;
    }

    $message = $dataHandler->data['message'];

    if (
        !shortcodeObject()->shortCodes ||
        my_strpos($message, '[' . shortcodeObject()->get_lock_tag()) === false
    ) {
        return $dataHandler;
    }

    $content_points = 0;

    shortcodeObject()->get_higher_points_from_message($message, $content_points);

    if ($content_points > $maximum_content_points && function_exists('newpoints_format_points')) {
        loadLanguage();

        $dataHandler->set_error(
            $lang->sprintf(
                $lang->lock_permission_maxcost,
                strip_tags(newpoints_format_points($maximum_content_points))
            )
        );
    }

    return $dataHandler;
}

function datahandler_post_validate_thread(PostDataHandler &$dataHandler): PostDataHandler
{
    return datahandler_post_validate_post($dataHandler);
}

// todo: delete content when users, posts are removed etc