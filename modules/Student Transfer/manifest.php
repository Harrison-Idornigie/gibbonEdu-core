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

/**
 * Install checks for Student Transfer module
 */
$installChecks = [
    // Check PHP version
    'phpVersion' => [
        'name' => 'PHP Version',
        'description' => 'Minimum version 7.4.0',
        'type' => 'php',
        'version' => '7.4.0'
    ],
    // Check PHP extensions
    'phpExtensions' => [
        'name' => 'PHP Extensions',
        'description' => 'Required extensions: zip, openssl',
        'type' => 'extensions',
        'extensions' => ['zip', 'openssl']
    ],
    // Check MySQL version
    'mysqlVersion' => [
        'name' => 'MySQL Version',
        'description' => 'Minimum version 5.7.0',
        'type' => 'mysql',
        'version' => '5.7.0'
    ],
    // Check write permissions
    'writeableFolder' => [
        'name' => 'Writeable Folders',
        'description' => 'The following folders must be writeable: uploads/transfers',
        'type' => 'writeable',
        'paths' => ['uploads/transfers']
    ]
];

//Module tables
$moduleTables = [
    "DROP TABLE IF EXISTS `gibbonStudentTransferLog`",
    "CREATE TABLE `gibbonStudentTransferLog` (
        `gibbonStudentTransferLogID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `gibbonSchoolYearID` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `schoolNameFrom` VARCHAR(100) NOT NULL,
        `schoolNameTo` VARCHAR(100) NOT NULL,
        `gibbonPersonIDCreated` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `status` ENUM('Pending','Exported','Imported','Complete','Cancelled') NOT NULL DEFAULT 'Pending',
        `notes` TEXT NULL,
        `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `timestampModified` TIMESTAMP NULL DEFAULT NULL,
        `exportTimestamp` TIMESTAMP NULL DEFAULT NULL,
        `importTimestamp` TIMESTAMP NULL DEFAULT NULL,
        `signature` TEXT NULL,
        `downloadToken` VARCHAR(255) NULL,
        `downloadExpiry` TIMESTAMP NULL DEFAULT NULL,
        `packagePassword` VARCHAR(255) NULL,
        `packagePasswordPlain` VARCHAR(10) NULL,
        PRIMARY KEY (`gibbonStudentTransferLogID`),
        INDEX `gibbonPersonID` (`gibbonPersonID`),
        INDEX `gibbonSchoolYearID` (`gibbonSchoolYearID`),
        INDEX `gibbonPersonIDCreated` (`gibbonPersonIDCreated`),
        INDEX `downloadToken` (`downloadToken`),
        CONSTRAINT `gibbonStudentTransferLog_ibfk_1` FOREIGN KEY (`gibbonPersonID`) 
            REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE RESTRICT,
        CONSTRAINT `gibbonStudentTransferLog_ibfk_2` FOREIGN KEY (`gibbonSchoolYearID`) 
            REFERENCES `gibbonSchoolYear` (`gibbonSchoolYearID`) ON DELETE RESTRICT,
        CONSTRAINT `gibbonStudentTransferLog_ibfk_3` FOREIGN KEY (`gibbonPersonIDCreated`) 
            REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "DROP TABLE IF EXISTS `gibbonStudentTransferData`",
    "CREATE TABLE `gibbonStudentTransferData` (
        `gibbonStudentTransferDataID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `gibbonStudentTransferLogID` INT(12) UNSIGNED ZEROFILL NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `value` TEXT NULL,
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`gibbonStudentTransferDataID`),
        INDEX `gibbonStudentTransferLogID` (`gibbonStudentTransferLogID`),
        INDEX `category` (`category`),
        CONSTRAINT `gibbonStudentTransferData_ibfk_1` FOREIGN KEY (`gibbonStudentTransferLogID`) 
            REFERENCES `gibbonStudentTransferLog` (`gibbonStudentTransferLogID`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "DROP TABLE IF EXISTS `gibbonStudentTransferAttachment`",
    "CREATE TABLE `gibbonStudentTransferAttachment` (
        `gibbonStudentTransferAttachmentID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `gibbonStudentTransferLogID` INT(12) UNSIGNED ZEROFILL NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `path` VARCHAR(255) NOT NULL,
        `type` VARCHAR(100) NOT NULL,
        `size` INT(11) NOT NULL,
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`gibbonStudentTransferAttachmentID`),
        INDEX `gibbonStudentTransferLogID` (`gibbonStudentTransferLogID`),
        CONSTRAINT `gibbonStudentTransferAttachment_ibfk_1` FOREIGN KEY (`gibbonStudentTransferLogID`) 
            REFERENCES `gibbonStudentTransferLog` (`gibbonStudentTransferLogID`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "DROP TABLE IF EXISTS `gibbonStudentTransferDownloadLog`",
    "CREATE TABLE `gibbonStudentTransferDownloadLog` (
        `gibbonStudentTransferDownloadLogID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `gibbonStudentTransferLogID` INT(12) UNSIGNED ZEROFILL NOT NULL,
        `ipAddress` VARCHAR(45) NOT NULL,
        `userAgent` VARCHAR(255) NULL,
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`gibbonStudentTransferDownloadLogID`),
        INDEX `gibbonStudentTransferLogID` (`gibbonStudentTransferLogID`),
        INDEX `ipAddress` (`ipAddress`),
        INDEX `timestamp` (`timestamp`),
        CONSTRAINT `gibbonStudentTransferDownloadLog_ibfk_1` FOREIGN KEY (`gibbonStudentTransferLogID`) 
            REFERENCES `gibbonStudentTransferLog` (`gibbonStudentTransferLogID`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

//Module settings
$gibbonSetting = [
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'requiredDocuments', 'Required Documents', 'Comma-separated list of required documents for transfer.', 'Photo,ID Card,Medical Records')",
    
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'destinationSchools', 'Destination Schools', 'Comma-separated list of available destination schools.', '')",
    
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'retentionPeriodCompleted', 'Completed Transfer Retention', 'Number of days to retain completed transfers before archiving.', '365')",
    
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'enableBatchTransfers', 'Enable Batch Transfers', 'Allow administrators to process multiple transfers at once.', 'Y')",
    
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'encryptionKey', 'Encryption Key', 'Secure key used for encrypting and signing transfer packages.', '')",
    
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'transferPrivateKey', 'Transfer Private Key', 'Private key for signing transfer packages.', '')",
    
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Student Transfer', 'transferPublicKey', 'Transfer Public Key', 'Public key for verifying transfer packages.', '')"
];

//Action rows
$actionRows = [
    [
        'name' => 'Manage Student Transfers',
        'precedence' => '0',
        'category' => 'Student Data',
        'description' => 'View and manage student transfers between schools.',
        'URLList' => 'transfer_manage.php,transfer_manage_add.php,transfer_manage_edit.php,transfer_manage_delete.php,transfer_manage_export.php,transfer_manage_import.php,transfer_manage_batch.php',
        'entryURL' => 'transfer_manage.php',
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
        'categoryPermissionOther' => 'N'
    ],
    [
        'name' => 'Student Transfer Settings',
        'precedence' => '1',
        'category' => 'Admin',
        'description' => 'Manage Student Transfer module settings.',
        'URLList' => 'transfer_settings_manage.php',
        'entryURL' => 'transfer_settings_manage.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'N',
        'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N',
        'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'N',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent' => 'N',
        'categoryPermissionOther' => 'N'
    ]
];
