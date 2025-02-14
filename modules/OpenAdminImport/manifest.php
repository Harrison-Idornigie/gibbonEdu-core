<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

// Basic module properties
$name = 'OpenAdmin Import';
$description = 'Import data from OpenAdmin (Sakas KOHC) into Gibbon.';
$entryURL = 'oa_import_manage.php';
$type = 'Additional';
$category = 'Admin';
$version = '1.0.00';
$author = 'Harrison Idornigie';
$url = '';

// Module tables
$moduleTables = [];
$moduleTables[] = "CREATE TABLE `OpenAdminImportLog` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `type` varchar(100) NOT NULL,
    `name` varchar(100) NOT NULL,
    `status` enum('Pending','Complete','Failed') NOT NULL DEFAULT 'Pending',
    `recordCount` int(11) NOT NULL DEFAULT 0,
    `importTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `data` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `type` (`type`),
    KEY `status` (`status`),
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Module actions
$actionRows = [];
$actionRows[] = [
    'name' => 'Manage OpenAdmin Import',
    'precedence' => '0',
    'category' => 'Import',
    'description' => 'Import data from OpenAdmin (Sakas KOHC)',
    'URLList' => 'oa_import_manage.php,oa_import_run.php',
    'entryURL' => 'oa_import_manage.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'View Import History',
    'precedence' => '0',
    'category' => 'Import',
    'description' => 'View history of OpenAdmin imports',
    'URLList' => 'oa_import_history.php,oa_import_history_view.php',
    'entryURL' => 'oa_import_history.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

// Hooks
$hooks = []; // Add hooks if needed
