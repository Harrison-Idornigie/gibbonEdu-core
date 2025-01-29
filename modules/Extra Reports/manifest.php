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

// Basic module information
$name = 'Extra Reports';
$description = 'An extension of the Reports module that adds support for A3 paper size and additional template management.';
$entryURL = 'extraReports_manage.php';
$type = 'Additional';
$category = 'Assess';
$version = '0.0.01';
$author = 'Harrison Idornigie';
$url = '';

// Module relationships
$dependencies = array(
    'Reports' => '23.0.00' // Requires Reports module version 23.0.00 or higher
);

// Compatibility
$gibbonCompatibility = '23.0.00';
$phpCompatibility = '7.3';

// Module tables
$moduleTables[] = "CREATE TABLE IF NOT EXISTS extraReportsPaperSize (
    gibbonReportTemplateID INT(10) UNSIGNED NOT NULL,
    paperSize ENUM('A3','A4','LETTER') NOT NULL DEFAULT 'A4',
    PRIMARY KEY (gibbonReportTemplateID),
    FOREIGN KEY (gibbonReportTemplateID) REFERENCES gibbonReportTemplate (gibbonReportTemplateID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Add moduleID column to gibbonReportTemplate if it doesn't exist
$moduleTables[] = "ALTER TABLE gibbonReportTemplate 
    ADD COLUMN IF NOT EXISTS `moduleID` varchar(50) DEFAULT 'Reports' AFTER `gibbonReportTemplateID`,
    ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `orientation`;";

// Update existing templates to have Reports as moduleID
$moduleTables[] = "UPDATE gibbonReportTemplate SET moduleID='Reports' WHERE moduleID IS NULL;";

// Add hooks
$hooks[] = "INSERT INTO `gibbonHook` (`name`, `type`, `options`, `gibbonModuleID`) VALUES 
('Paper Size Settings', 'Report Template', 'a:3:{s:16:\"sourceModuleName\";s:13:\"Extra Reports\";s:18:\"sourceModuleAction\";s:18:\"extraReports_manage\";s:10:\"sourceClass\";s:54:\"Gibbon\\Module\\ExtraReports\\Hook\\ReportTemplateHook\";}', 
(SELECT gibbonModuleID FROM gibbonModule WHERE name='Extra Reports'));";

// Action rows
$actionRows[] = [
    'name' => 'Manage Report Templates',
    'precedence' => '0',
    'category' => 'Assess',
    'description' => 'Allows users to manage report templates specific to Extra Reports.',
    'URLList' => 'extraReports_templates_manage.php,extraReports_templates_manage_add.php,extraReports_templates_manage_addProcess.php,extraReports_templates_manage_edit.php,extraReports_templates_manage_editProcess.php,extraReports_templates_manage_delete.php,extraReports_templates_manage_deleteProcess.php',
    'entryURL' => 'extraReports_templates_manage.php',
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
];

$actionRows[] = [
    'name' => 'Manage Reports',
    'precedence' => '0',
    'category' => 'Assess',
    'description' => 'Allows users to manage report paper sizes.',
    'URLList' => 'extraReports_manage.php,extraReports_manage_edit.php,extraReports_manage_editProcess.php',
    'entryURL' => 'extraReports_manage.php',
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
];
