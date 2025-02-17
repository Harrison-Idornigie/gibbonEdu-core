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

//This file describes the module, including database tables

//Basic variables
$name = 'Student Transfer';
$description = 'Manage student transfers between schools.';
$entryURL = 'transfer_manage.php';
$type = 'Additional';
$category = 'Students';
$version = '1.0.00';
$author = 'Harrison Idornigie';
$url = '';

//Module tables
$moduleTables[] = "CREATE TABLE `gibbonStudentTransferLog` (
    `gibbonStudentTransferLogID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDCreated` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `status` ENUM('Pending','Exported','Imported','Cancelled') NOT NULL DEFAULT 'Pending',
    `comments` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NULL,
    `exportTimestamp` TIMESTAMP NULL,
    `importTimestamp` TIMESTAMP NULL,
    `signature` TEXT NULL,
    `downloadToken` VARCHAR(255) NULL,
    `downloadExpiry` TIMESTAMP NULL,
    `packagePassword` VARCHAR(255) NULL,
    PRIMARY KEY (`gibbonStudentTransferLogID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonStudentTransferToken` (
    `gibbonStudentTransferTokenID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `transferID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expiry` TIMESTAMP NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`gibbonStudentTransferTokenID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonStudentTransferPassword` (
    `gibbonStudentTransferPasswordID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `zipPath` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`gibbonStudentTransferPasswordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

//Settings
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES ('Student Transfer', 'retentionPeriodActive', 'Active Transfer Retention', 'Number of days to retain active transfers.', '90');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES ('Student Transfer', 'retentionPeriodCompleted', 'Completed Transfer Retention', 'Number of days to retain completed transfers before archiving.', '365');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES ('Student Transfer', 'retentionPeriodCancelled', 'Cancelled Transfer Retention', 'Number of days to retain cancelled transfers.', '30');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES ('Student Transfer', 'retentionPeriodArchive', 'Archive Retention', 'Number of days to retain archived transfers.', '730');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES ('Student Transfer', 'enableAutoArchive', 'Enable Auto-Archive', 'Automatically archive old transfers according to retention policy.', 'Y');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES ('Student Transfer', 'enableAutoDelete', 'Enable Auto-Delete', 'Automatically delete expired transfers according to retention policy.', 'Y');";

$gibbonSetting[] = [
    'scope'         => 'Student Transfer',
    'name'          => 'packageExpiryDays',
    'nameDisplay'   => 'Package Expiry Days',
    'description'   => 'Number of days before a transfer package expires.',
    'value'         => '7'
];

$gibbonSetting[] = [
    'scope'         => 'Student Transfer',
    'name'          => 'allowBatchTransfers',
    'nameDisplay'   => 'Allow Batch Transfers',
    'description'   => 'Allow multiple students to be transferred at once.',
    'value'         => 'Y'
];

$gibbonSetting[] = [
    'scope'         => 'Student Transfer',
    'name'          => 'requiredDocuments',
    'nameDisplay'   => 'Required Documents',
    'description'   => 'List of documents required for transfer (comma-separated).',
    'value'         => 'Photo,Medical Form,Previous Reports'
];

//Action rows
$actionRows[0]['name'] = 'Manage Student Transfers';
$actionRows[0]['precedence'] = '0';
$actionRows[0]['category'] = 'Students';
$actionRows[0]['description'] = 'View and manage student transfers between schools.';
$actionRows[0]['URLList'] = 'transfer_manage.php,transfer_manage_add.php,transfer_manage_edit.php,transfer_manage_delete.php,transfer_manage_export.php,transfer_manage_import.php';
$actionRows[0]['entryURL'] = 'transfer_manage.php';
$actionRows[0]['entrySidebar'] = 'Y';
$actionRows[0]['menuShow'] = 'Y';
$actionRows[0]['defaultPermissionAdmin'] = 'Y';
$actionRows[0]['defaultPermissionTeacher'] = 'N';
$actionRows[0]['defaultPermissionStudent'] = 'N';
$actionRows[0]['defaultPermissionParent'] = 'N';
$actionRows[0]['defaultPermissionSupport'] = 'N';
$actionRows[0]['categoryPermissionStaff'] = 'Y';
$actionRows[0]['categoryPermissionStudent'] = 'N';
$actionRows[0]['categoryPermissionParent'] = 'N';
$actionRows[0]['categoryPermissionOther'] = 'N';
