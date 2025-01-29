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
$description = 'Extends the core Reports module with additional features like A3 paper size support.';
$entryURL = 'extraReports_manage.php';
$type = 'Additional';
$category = 'Assess';
$version = '1.0.00';
$author = 'Gibbon User Community';
$url = 'https://github.com/GibbonEdu/module-ExtraReports';

// Module relationships
$dependencies = array(
    'Reports' => '23.0.00' // Requires Reports module version 23.0.00 or higher
);

// Compatibility
$gibbonCompatibility = '23.0.00';
$phpCompatibility = '7.3';

// Module tables
$moduleTables[] = "CREATE TABLE `extraReportsPaperSize` (
    `id` INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    `gibbonReportTemplateID` INT(10) UNSIGNED ZEROFILL,
    `paperSize` ENUM('A4', 'A3', 'LETTER') DEFAULT 'A4',
    PRIMARY KEY (`id`),
    UNIQUE KEY `template` (`gibbonReportTemplateID`),
    FOREIGN KEY (`gibbonReportTemplateID`) REFERENCES `gibbonReportTemplate` (`gibbonReportTemplateID`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Add hooks
$hooks[] = "INSERT INTO `gibbonHook` (`name`, `type`, `options`, `gibbonModuleID`) VALUES 
('Paper Size Settings', 'Report Template', 'a:3:{s:16:\"sourceModuleName\";s:13:\"Extra Reports\";s:18:\"sourceModuleAction\";s:18:\"extraReports_manage\";s:10:\"sourceClass\";s:54:\"Gibbon\\Module\\ExtraReports\\Hook\\ReportTemplateHook\";}', 
(SELECT gibbonModuleID FROM gibbonModule WHERE name='Extra Reports'));";

// Action rows
$actionRows[] = [
    'name'                      => 'Manage Paper Sizes', 
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'Manage paper size settings for report templates.',
    'URLList'                   => 'extraReports_manage.php',
    'entryURL'                  => 'extraReports_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N'
];
