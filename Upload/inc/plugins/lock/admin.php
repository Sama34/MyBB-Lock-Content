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
use function LockContent\Core\purchaseLogGet;
use function LockContent\Core\purchaseLogInsert;

use const PLUGINLIBRARY;
use const LockContent\ROOT;
use const Newpoints\DECIMAL_DATA_TYPE_SIZE;
use const Newpoints\DECIMAL_DATA_TYPE_STEP;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;

const TABLES_DATA = [
    'ougc_lock_content_logs' => [
        'log_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'post_id' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'purchase_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['user_post_id' => 'user_id,post_id']
    ],
];

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
        'version' => '2.0.0',
        'versioncode' => 2000,
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

    dbVerifyTables();

    /*~*~* RUN UPDATES START *~*~*/
    global $db;

    if ($pluginInfo['versioncode'] <= 1837) {
        if ($db->field_exists('lock_maxcost', 'usergroups')) {
            $query = $db->simple_select('usergroups', 'gid, lock_maxcost');

            while ($groupData = $db->fetch_array($query)) {
                $db->update_query(
                    'usergroups',
                    ['lock_maxcost' => (float)$groupData['lock_maxcost']],
                    "gid='{$groupData['gid']}'"
                );
            }
        }

        if ($db->field_exists('unlocked', 'posts')) {
            $query = $db->simple_select('posts', 'pid, unlocked', "unlocked IS NOT NULL AND unlocked!=''");

            while ($postData = $db->fetch_array($query)) {
                $postID = (int)$postData['pid'];

                $allowedUsers = array_filter(array_map('intval', explode(',', $postData['unlocked'] ?? '')));

                foreach ($allowedUsers as $key => $userID) {
                    $logData = purchaseLogGet(["user_id={$userID}", "post_id={$postID}"], queryOptions: ['limit' => 1]);

                    if (!$logData) {
                        if (purchaseLogInsert(['user_id' => $userID, 'post_id' => $postID])) {
                            unset($allowedUsers[$key]);

                            $db->update_query(
                                'posts',
                                ['unlocked' => implode(',', $allowedUsers)],
                                "pid='{$postID}'"
                            );
                        }
                    }
                }
            }
        }
    }

    /*~*~* RUN UPDATES END *~*~*/

    dbVerifyColumns();

    $cache->update_usergroups();

    $plugins['LockContent'] = $pluginInfo['versioncode'];

    $cache->update('ougc_plugins', $plugins);
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalledEach = true;

        foreach (TABLES_DATA as $tableName => $tableColumns) {
            $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;
        }

        $isInstalled = $isInstalledEach;
    }

    return $isInstalled;
}

function pluginUninstall(): void
{
    global $db, $PL, $cache;

    loadPluginLibrary();

    foreach (TABLES_DATA as $tableName => $tableData) {
        if ($db->table_exists($tableName)) {
            $db->drop_table($tableName);
        }
    }

    foreach (dbFields() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($db->field_exists($fieldName, $tableName)) {
                    $db->drop_column($tableName, $fieldName);
                }
            }
        }
    }

    $cache->update_usergroups();

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

function dbTables(): array
{
    $tables_data = [];

    foreach (TABLES_DATA as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $tables_data[$tableName][$fieldName] = dbBuildFieldDefinition($fieldData);
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (isset($fieldData['primary_key'])) {
                $tables_data[$tableName]['primary_key'] = $fieldName;
            }

            if ($fieldName === 'unique_key') {
                $tables_data[$tableName]['unique_key'] = $fieldData;
            }
        }
    }

    return $tables_data;
}

function dbVerifyTables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (dbTables() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key' || $fieldName == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$tableName}` (";

            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName != 'unique_key') {
                    $query_string .= "`{$fieldName}` {$fieldData},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    dbVerifyIndexes();

    return true;
}

function dbVerifyIndexes(): bool
{
    global $db;

    foreach (dbTables() as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableColumns['unique_key'])) {
            foreach ($tableColumns['unique_key'] as $key_name => $key_value) {
                if ($db->index_exists($tableName, $key_name)) {
                    continue;
                }

                $db->write_query(
                    "ALTER TABLE {$db->table_prefix}{$tableName} ADD UNIQUE KEY {$key_name} ({$key_value})"
                );
            }
        }
    }

    return true;
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