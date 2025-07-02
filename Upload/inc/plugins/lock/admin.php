<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/plugins/lock/admin.php)
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

namespace LockContent\Admin;

use DirectoryIterator;
use stdClass;

use function LockContent\Core\loadLanguage;

use const PLUGINLIBRARY;
use const LockContent\ROOT;
use const Newpoints\DECIMAL_DATA_TYPE_SIZE;
use const Newpoints\DECIMAL_DATA_TYPE_STEP;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;

function pluginInfo(): array
{
    global $lang;

    loadLanguage();

    return [
        'name' => 'Lock Content',
        'description' => $lang->lock_desc,
        'website' => 'https://ougc.network',
        'author' => '<a href="https://community.mybb.com/user-99749.html">Neko</a> & Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.37',
        'versioncode' => 1837,
        'compatibility' => '18*',
        'codename' => 'ougc_lock',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

function pluginActivate(): void
{
    global $PL, $cache, $lang;

    loadLanguage();

    loadPluginLibrary();

    $settingsContents = file_get_contents(ROOT . '/settings.json');

    $settingsData = json_decode($settingsContents, true);

    foreach ($settingsData as $settingKey => &$settingData) {
        if (empty($lang->{"setting_lock_{$settingKey}"})) {
            continue;
        }

        if ($settingData['optionscode'] == 'select' || $settingData['optionscode'] == 'checkbox' || $settingData['optionscode'] == 'radio') {
            foreach ($settingData['options'] as $optionKey) {
                $settingData['optionscode'] .= "\n{$optionKey}={$lang->{"setting_lock_{$settingKey}_{$optionKey}"}}";
            }
        }

        $settingData['title'] = $lang->{"setting_lock_{$settingKey}"};

        $settingData['description'] = $lang->{"setting_lock_{$settingKey}_desc"};
    }

    $PL->settings(
        'lock',
        $lang->setting_group_lock,
        $lang->setting_group_lock_desc,
        $settingsData
    );

    $templates = [];

    if (file_exists($templateDirectory = ROOT . '/templates')) {
        $templatesDirIterator = new DirectoryIterator($templateDirectory);

        foreach ($templatesDirIterator as $template) {
            if (!$template->isFile()) {
                continue;
            }

            $pathName = $template->getPathname();

            $pathInfo = pathinfo($pathName);

            if ($pathInfo['extension'] === 'html') {
                $templates[$pathInfo['filename']] = file_get_contents($pathName);
            }
        }
    }

    if ($templates) {
        $PL->templates('lock', 'Lock Content', $templates);
    }

    $pluginInfo = pluginInfo();

    // Insert/update version into cache
    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    if (!isset($plugins['LockContent'])) {
        $plugins['LockContent'] = $pluginInfo['versioncode'];
    }

    dbVerifyColumns();

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $plugins['LockContent'] = $pluginInfo['versioncode'];

    $cache->update('ougc_plugins', $plugins);
}

function pluginInstall(): void
{
    dbVerifyColumns();
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalledEach = true;

        foreach (dbFields() as $tableName => $tableColumns) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                $isInstalledEach = $db->field_exists($fieldName, $tableName) && $isInstalledEach;
            }
        }

        $isInstalled = $isInstalledEach;
    }

    return $isInstalled;
}

function pluginUninstall(): void
{
    global $db, $PL, $cache;

    loadPluginLibrary();

    foreach (dbFields() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($db->field_exists($fieldName, $tableName)) {
                    $db->drop_column($tableName, $fieldName);
                }
            }
        }
    }

    $PL->settings_delete('lock');

    $PL->templates_delete('lock');

    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['LockContent'])) {
        unset($plugins['LockContent']);
    }

    if (!empty($plugins)) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $cache->delete('ougc_plugins');
    }
}

function dbVerifyColumns(): void
{
    global $db;

    foreach (dbFields() as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            if ($db->field_exists($fieldName, $tableName)) {
                $db->modify_column($tableName, "`{$fieldName}`", dbBuildFieldDefinition($fieldData));
            } else {
                $db->add_column($tableName, $fieldName, dbBuildFieldDefinition($fieldData));
            }
        }
    }
}

function dbBuildFieldDefinition(array $fieldData): string
{
    $field_definition = '';

    $field_definition .= $fieldData['type'];

    if (isset($fieldData['size'])) {
        $field_definition .= "({$fieldData['size']})";
    }

    if (isset($fieldData['unsigned'])) {
        if ($fieldData['unsigned'] === true) {
            $field_definition .= ' UNSIGNED';
        } else {
            $field_definition .= ' SIGNED';
        }
    }

    if (!isset($fieldData['null'])) {
        $field_definition .= ' NOT';
    }

    $field_definition .= ' NULL';

    if (isset($fieldData['auto_increment'])) {
        $field_definition .= ' AUTO_INCREMENT';
    }

    if (isset($fieldData['default'])) {
        $field_definition .= " DEFAULT '{$fieldData['default']}'";
    }

    return $field_definition;
}

function pluginLibraryRequirements(): stdClass
{
    return (object)pluginInfo()['pl'];
}

function loadPluginLibrary(): void
{
    global $PL, $lang;

    loadLanguage();

    $fileExists = file_exists(PLUGINLIBRARY);

    if ($fileExists && !($PL instanceof PluginLibrary)) {
        require_once PLUGINLIBRARY;
    }

    if (!$fileExists || $PL->version < pluginLibraryRequirements()->version) {
        flash_message(
            $lang->sprintf(
                $lang->lockPluginLibrary,
                pluginLibraryRequirements()->url,
                pluginLibraryRequirements()->version
            ),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }
}

function dbFields(): array
{
    if (defined('\Newpoints\DECIMAL_DATA_TYPE_SIZE')) {
        $dbFields = [
            'usergroups' => [
                'lock_maxcost' => [
                    'type' => 'DECIMAL',
                    'unsigned' => true,
                    'size' => DECIMAL_DATA_TYPE_SIZE,
                    'default' => 0,
                    'form_type' => FORM_TYPE_NUMERIC_FIELD,
                    'form_options' => [
                        //'min' => 0,
                        'step' => DECIMAL_DATA_TYPE_STEP,
                    ],
                ],
            ],
        ];
    } else {
        $dbFields = [
            'usergroups' => [
                'lock_maxcost' => [
                    'type' => 'DECIMAL',
                    'unsigned' => true,
                    'size' => '16,4',
                    'default' => 0,
                ],
            ],
        ];
    }

    $dbFields['posts'] = [
        'unlocked' => [
            'type' => 'TEXT',
            'null' => true,
        ],
    ];

    return $dbFields;
}