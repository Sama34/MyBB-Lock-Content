<?php

/***************************************************************************
 *
 *    Lock plugin (/inc/plugins/lock.php)
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

use function LockContent\Core\addHooks;
use function LockContent\Admin\pluginActivate;
use function LockContent\Admin\pluginInfo;
use function LockContent\Admin\pluginIsInstalled;
use function LockContent\Admin\pluginUninstall;

use const LockContent\ROOT;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

define('LockContent\ROOT', constant('MYBB_ROOT') . 'inc/plugins/lock');

require_once ROOT . '/core.php';

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', constant('MYBB_ROOT') . 'inc/plugins/pluginlibrary.php');

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    addHooks('LockContent\Hooks\Admin');
} else {
    //require_once ROOT . '/hooks/forum.php';

    addHooks('LockContent\Hooks\Forum');
}

if (THIS_SCRIPT === 'showthread.php') {
    global $templatelist;

    if (!isset($templatelist)) {
        $templatelist = '';
    }

    $templatelist .= ',lock_wrapper,lock_form,';
}

defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

function lock_info(): array
{
    return pluginInfo();
}

global $plugins;

if (!defined('IN_ADMINCP')) {
    // keep people from using the highlight feature to bypass the tags
    $plugins->add_hook('parse_message_start', 'lock_highlight_start');
    $plugins->add_hook('parse_message', 'lock_highlight_end');

    // adds a new action method to MyBB.
    $plugins->add_hook('global_end', 'lock_purchase');

    // remove hide tags from quotes
    $plugins->add_hook('parse_quoted_message', 'lock_quoted');

    // validate maximum cost
    $plugins->add_hook('datahandler_post_validate_post', ['Shortcodes', 'validate_post']);
    $plugins->add_hook('datahandler_post_validate_thread', ['Shortcodes', 'validate_post']);
}

if (!empty($mybb->input['highlight'])) {
    $highlight_replacement = null;
}

if (!class_exists('Shortcodes')) {
    require __DIR__ . '/lock/shortcodes.class.php';
}

function lock_activate(): void
{
    pluginActivate();
}

function lock_uninstall(): void
{
    pluginUninstall();
}

function lock_is_installed(): bool
{
    return pluginIsInstalled();
}

function lock_highlight_start(string &$message): string
{
    global $mybb, $replacement;

    if (!empty($mybb->input['highlight'])) {
        $replacement = substr(
            str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWYXZ', 20)),
            0,
            20
        );

        switch ((string)$mybb->settings['lock_type']) {
            case 'lock':
                $message = str_replace('lock', $replacement, $message);
                break;
            case 'cap':
                $message = str_replace('cap', $replacement, $message);
                break;
            default:
                $message = str_replace('hide', $replacement, $message);
                break;
        }
    }

    return $message;
}

function lock_highlight_end(string &$message): string
{
    global $mybb, $replacement;

    if (!empty($mybb->input['highlight'])) {
        switch ((string)$mybb->settings['lock_type']) {
            case 'lock':
                $message = str_replace($replacement, 'lock', $message);
                break;
            case 'cap':
                $message = str_replace($replacement, 'cap', $message);
                break;
            default:
                $message = str_replace($replacement, 'hide', $message);
                break;
        }
    }

    return Shortcodes::parse($message);
}

function lock_purchase(): bool
{
    global $_POST, $mybb, $db;

    require_once __DIR__ . '/lock/core/purchase.php';

    return true;
}

require_once __DIR__ . '/lock/core/shortcode.php';

function lock_quoted(array &$quoted_post): array
{
    Shortcodes::set_tag();
    $quoted_post['message'] = preg_replace(
        '#\[' . Shortcodes::$tag . '(.*)\[/' . Shortcodes::$tag . '\]#is',
        '',
        $quoted_post['message']
    );

    return $quoted_post;
}