<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/plugins/ougc/DisplayName/hooks/admin.php)
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

namespace LockContent\Hooks\Admin;

use MyBB;

use function LockContent\Core\loadLanguage;
use function LockContent\Admin\dbFields;

function admin_config_plugins_deactivate(): void
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') !== 'deactivate' ||
        $mybb->get_input('plugin') !== 'lock' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return;
    }

    if ($mybb->request_method !== 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=lock'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }
}

function newpoints_admin_user_groups_edit_graph_start(array &$hookArguments): array
{
    loadLanguage();

    $hookArguments['data_fields'] = array_merge(
        $hookArguments['data_fields'],
        dbFields()['usergroups'],
    );

    return $hookArguments;
}

function newpoints_admin_user_groups_edit_commit_start(array &$hookArguments): array
{
    return newpoints_admin_user_groups_edit_graph_start($hookArguments);
}