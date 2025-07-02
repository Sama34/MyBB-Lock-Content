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

// You can uncomment the lines below to avoid storing some settings in the DB
define('LockContent\SETTINGS', [
    //'key' => '',
]);

define('LockContent\DEBUG', true);

define('LockContent\ROOT', constant('MYBB_ROOT') . 'inc/plugins/lock');

require_once ROOT . '/core.php';

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', constant('MYBB_ROOT') . 'inc/plugins/pluginlibrary.php');

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    addHooks('LockContent\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    addHooks('LockContent\Hooks\Forum');
}

function lock_info(): array
{
    return pluginInfo();
}

global $plugins;

if (!defined('IN_ADMINCP')) {
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

require_once __DIR__ . '/lock/core/shortcode.php';