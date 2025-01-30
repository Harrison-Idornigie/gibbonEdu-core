<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

// Basic variables
$name = 'Extra Reports';
$description = 'A module for generating custom report cards.';
$entryURL = "report_cards_manage.php";
$type = "Additional";
$category = "Assess";
$version = '0.1.00';
$author = 'Your Name';
$url = '';

// Autoloader
$gibbonModuleClassLists[] = [
    'prefix' => 'Gibbon\\Module\\ExtraReports\\',
    'sourcePath' => 'src'
];

// Module tables
$moduleTables = [
    "CREATE TABLE `extraReportAssessment` (
        `assessmentID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
        `reportingPeriod` VARCHAR(50) NOT NULL,
        `section` VARCHAR(50) NOT NULL,
        `item` VARCHAR(255) NOT NULL,
        `score` INT(1) NOT NULL DEFAULT 0,
        `comment` TEXT NULL DEFAULT NULL,
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`assessmentID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
    
    "CREATE TABLE `extraReportTemplate` (
        `templateID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT NULL,
        `sections` TEXT NOT NULL,
        `chartSections` TEXT NOT NULL,
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`templateID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
];

// Gibbonisation
$gibbonSetting = [
    "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) 
    VALUES 
    ('Extra Reports', 'templatePath', 'Template Path', 'Path to report card templates', '/modules/Extra Reports/templates/reportCards/');"
];

// Action rows
$actionRows = [
    [
        'name' => 'Manage Report Cards',
        'precedence' => '0',
        'category' => 'Report Cards',
        'description' => 'Manage and generate report cards',
        'URLList' => 'report_cards_manage.php',
        'entryURL' => 'report_cards_manage.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N',
        'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent' => 'N',
        'categoryPermissionOther' => 'N'
    ],
    [
        'name' => 'Enter Assessments',
        'precedence' => '0',
        'category' => 'Report Cards',
        'description' => 'Enter assessments for report cards',
        'URLList' => 'report_cards_enter.php,report_cards_enter_student.php',
        'entryURL' => 'report_cards_enter.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N',
        'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent' => 'N',
        'categoryPermissionOther' => 'N'
    ],
    [
        'name' => 'View Assessments',
        'precedence' => '0',
        'category' => 'Report Cards',
        'description' => 'View and manage all assessments',
        'URLList' => 'report_cards_view.php',
        'entryURL' => 'report_cards_view.php',
        'entrySidebar' => 'Y',
        'menuShow' => 'Y',
        'defaultPermissionAdmin' => 'Y',
        'defaultPermissionTeacher' => 'Y',
        'defaultPermissionStudent' => 'N',
        'defaultPermissionParent' => 'N',
        'defaultPermissionSupport' => 'N',
        'categoryPermissionStaff' => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent' => 'N',
        'categoryPermissionOther' => 'N'
    ]
];

// Module config
$moduleConfig = [
    'reportCardFormats' => json_encode([
        'preKindergarten' => ['name' => 'Pre-Kindergarten', 'file' => 'preKindergartenReport.php'],
        'kindergarten' => ['name' => 'Kindergarten', 'file' => 'kindergartenReport.php'],
        'gradeOne' => ['name' => 'Grade One', 'file' => 'gradeOneReport.php']
    ])
];
?>
