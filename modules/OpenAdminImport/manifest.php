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

// Module manifest file for OpenAdminImport module

// Basic module properties
$name = 'OpenAdminImport';
$description = 'Import student and staff data from Open Admin for Schools (OAFS) CSV exports';
$entryURL = 'oa_import_manage.php';
$type = 'Additional';
$category = 'Admin';
$version = '1.0.00';
$author = 'Your Name';
$url = '';

// Module tables
$moduleTables = [];
$moduleTables[] = "CREATE TABLE `oafsImportLog` (
    `oafsImportLogID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `importType` varchar(50) NOT NULL,
    `status` varchar(20) NOT NULL,
    `recordCount` int(10) NOT NULL DEFAULT 0,
    `successCount` int(10) NOT NULL DEFAULT 0,
    `errorCount` int(10) NOT NULL DEFAULT 0,
    `messages` text DEFAULT NULL,
    `gibbonPersonIDCreated` int(10) unsigned NULL,
    `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`oafsImportLogID`),
    KEY `importType` (`importType`),
    KEY `status` (`status`),
    KEY `gibbonPersonIDCreated` (`gibbonPersonIDCreated`),
    CONSTRAINT `oafsImportLog_ibfk_1` FOREIGN KEY (`gibbonPersonIDCreated`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `oafsFieldMapping` (
    `oafsFieldMappingID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `importType` varchar(50) NOT NULL,
    `sourceField` varchar(100) NOT NULL,
    `targetField` varchar(100) NOT NULL,
    `isRequired` enum('Y','N') NOT NULL DEFAULT 'N',
    `defaultValue` varchar(255) DEFAULT NULL,
    `transformationRule` text DEFAULT NULL,
    `gibbonPersonIDCreated` int(10) unsigned NULL,
    `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`oafsFieldMappingID`),
    UNIQUE KEY `unique_mapping` (`importType`, `sourceField`),
    KEY `importType` (`importType`),
    KEY `gibbonPersonIDCreated` (`gibbonPersonIDCreated`),
    CONSTRAINT `oafsFieldMapping_ibfk_1` FOREIGN KEY (`gibbonPersonIDCreated`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Module settings
$gibbonSetting = [];
$gibbonSetting[] = "INSERT INTO `gibbonSetting` 
    (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('OpenAdminImport', 'csvDelimiter', 'CSV Delimiter', 'Default delimiter for CSV files', ','),
    ('OpenAdminImport', 'csvEncoding', 'CSV Encoding', 'Default encoding for CSV files', 'UTF-8'),
    ('OpenAdminImport', 'batchSize', 'Batch Size', 'Number of records to process in each batch', '100');";

// Module actions
$actionRows = [];
$actionRows[] = [
    'name' => 'Manage OAFS Import',
    'precedence' => '0',
    'category' => 'Import',
    'description' => 'Import data from Open Admin for Schools CSV exports',
    'URLList' => 'oa_import_manage.php',
    'entryURL' => 'oa_import_manage.php',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N'
];

// Hooks
$hooks = []; // Add hooks if needed
