<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//This file describes the module, including database tables

//Basica variables
$name = 'ATL';
$description = 'The ATL module allows schools to run a program of Approaches To Learning assessments, based on a rubric.';
$entryURL = 'atl_write.php';
$type = 'Additional';
$category = 'Assess';
$version = '1.6.03';
$author = "Gibbon Foundation";
$url = "https://gibbonedu.org";

//Module tables
$moduleTables[0] = "CREATE TABLE `atlColumn` (
  `atlColumnID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `gibbonCourseClassID` int(8) unsigned zerofill NOT NULL,
  `groupingID` int(8) unsigned zerofill DEFAULT NULL COMMENT 'A value used to group multiple ATL columns.',
  `name` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `gibbonRubricID` int(8) unsigned zerofill DEFAULT NULL,
  `complete` enum('N','Y') NOT NULL,
  `completeDate` date DEFAULT NULL,
  `gibbonPersonIDCreator` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonIDLastEdit` int(10) unsigned zerofill NOT NULL,
  PRIMARY KEY (`atlColumnID`),
  KEY `gibbonCourseClassID` (`gibbonCourseClassID`),
  KEY `gibbonRubricID` (`gibbonRubricID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[1] = "CREATE TABLE `atlEntry` (
  `atlEntryID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `atlColumnID` int(10) unsigned zerofill NOT NULL,
  `gibbonPersonIDStudent` int(10) unsigned zerofill NOT NULL,
  `complete` enum('Y','N') NOT NULL DEFAULT 'N',
  `gibbonPersonIDLastEdit` int(10) unsigned zerofill NOT NULL,
   PRIMARY KEY (`atlEntryID`),
   KEY (`atlColumnID`),
   KEY (`gibbonPersonIDStudent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

//Action rows
$actionRows[0]['name'] = 'Manage ATLs_all';
$actionRows[0]['precedence'] = '0';
$actionRows[0]['category'] = 'Manage & Assess';
$actionRows[0]['description'] = 'Allows privileged users to create and manage ATL columns.';
$actionRows[0]['URLList'] = 'atl_manage.php, atl_manage_add.php, atl_manage_edit.php, atl_manage_delete.php';
$actionRows[0]['entryURL'] = 'atl_manage.php';
$actionRows[0]['defaultPermissionAdmin'] = 'Y';
$actionRows[0]['defaultPermissionTeacher'] = 'N';
$actionRows[0]['defaultPermissionStudent'] = 'N';
$actionRows[0]['defaultPermissionParent'] = 'N';
$actionRows[0]['defaultPermissionSupport'] = 'N';
$actionRows[0]['categoryPermissionStaff'] = 'Y';
$actionRows[0]['categoryPermissionStudent'] = 'N';
$actionRows[0]['categoryPermissionParent'] = 'N';
$actionRows[0]['categoryPermissionOther'] = 'N';

$actionRows[1]['name'] = 'Write ATLs_myClasses';
$actionRows[1]['precedence'] = '0';
$actionRows[1]['category'] = 'Manage & Assess';
$actionRows[1]['description'] = 'Allows teachers to enter ATL assessment data to columns in their classes.';
$actionRows[1]['URLList'] = 'atl_write.php, atl_write_data.php';
$actionRows[1]['entryURL'] = 'atl_write.php';
$actionRows[1]['defaultPermissionAdmin'] = 'N';
$actionRows[1]['defaultPermissionTeacher'] = 'Y';
$actionRows[1]['defaultPermissionStudent'] = 'N';
$actionRows[1]['defaultPermissionParent'] = 'N';
$actionRows[1]['defaultPermissionSupport'] = 'N';
$actionRows[1]['categoryPermissionStaff'] = 'Y';
$actionRows[1]['categoryPermissionStudent'] = 'N';
$actionRows[1]['categoryPermissionParent'] = 'N';
$actionRows[1]['categoryPermissionOther'] = 'N';

$actionRows[2]['name'] = 'Write ATLs_all';
$actionRows[2]['precedence'] = '1';
$actionRows[2]['category'] = 'Manage & Assess';
$actionRows[2]['description'] = 'Allows privileged users to enter ATL assessment data to columns in all classes.';
$actionRows[2]['URLList'] = 'atl_write.php, atl_write_data.php';
$actionRows[2]['entryURL'] = 'atl_write.php';
$actionRows[2]['defaultPermissionAdmin'] = 'Y';
$actionRows[2]['defaultPermissionTeacher'] = 'N';
$actionRows[2]['defaultPermissionStudent'] = 'N';
$actionRows[2]['defaultPermissionParent'] = 'N';
$actionRows[2]['defaultPermissionSupport'] = 'N';
$actionRows[2]['categoryPermissionStaff'] = 'Y';
$actionRows[2]['categoryPermissionStudent'] = 'N';
$actionRows[2]['categoryPermissionParent'] = 'N';
$actionRows[2]['categoryPermissionOther'] = 'N';

$actionRows[3]['name'] = 'View ATLs_mine';
$actionRows[3]['precedence'] = '0';
$actionRows[3]['category'] = 'View';
$actionRows[3]['description'] = 'Allows students to view their own ATL results.';
$actionRows[3]['URLList'] = 'atl_view.php';
$actionRows[3]['entryURL'] = 'atl_view.php';
$actionRows[3]['defaultPermissionAdmin'] = 'N';
$actionRows[3]['defaultPermissionTeacher'] = 'N';
$actionRows[3]['defaultPermissionStudent'] = 'Y';
$actionRows[3]['defaultPermissionParent'] = 'N';
$actionRows[3]['defaultPermissionSupport'] = 'N';
$actionRows[3]['categoryPermissionStaff'] = 'N';
$actionRows[3]['categoryPermissionStudent'] = 'Y';
$actionRows[3]['categoryPermissionParent'] = 'N';
$actionRows[3]['categoryPermissionOther'] = 'N';

$actionRows[4]['name'] = 'View ATLs_myChildrens';
$actionRows[4]['precedence'] = '1';
$actionRows[4]['category'] = 'View';
$actionRows[4]['description'] = "Allows parents to view their childrens' ATL results.";
$actionRows[4]['URLList'] = 'atl_view.php';
$actionRows[4]['entryURL'] = 'atl_view.php';
$actionRows[4]['defaultPermissionAdmin'] = 'N';
$actionRows[4]['defaultPermissionTeacher'] = 'N';
$actionRows[4]['defaultPermissionStudent'] = 'N';
$actionRows[4]['defaultPermissionParent'] = 'Y';
$actionRows[4]['defaultPermissionSupport'] = 'N';
$actionRows[4]['categoryPermissionStaff'] = 'N';
$actionRows[4]['categoryPermissionStudent'] = 'N';
$actionRows[4]['categoryPermissionParent'] = 'Y';
$actionRows[4]['categoryPermissionOther'] = 'N';

$actionRows[5]['name'] = 'View ATLs_all';
$actionRows[5]['precedence'] = '2';
$actionRows[5]['category'] = 'View';
$actionRows[5]['description'] = 'Allows staff to see ATL results for all children.';
$actionRows[5]['URLList'] = 'atl_view.php';
$actionRows[5]['entryURL'] = 'atl_view.php';
$actionRows[5]['defaultPermissionAdmin'] = 'Y';
$actionRows[5]['defaultPermissionTeacher'] = 'Y';
$actionRows[5]['defaultPermissionStudent'] = 'N';
$actionRows[5]['defaultPermissionParent'] = 'N';
$actionRows[5]['defaultPermissionSupport'] = 'N';
$actionRows[5]['categoryPermissionStaff'] = 'Y';
$actionRows[5]['categoryPermissionStudent'] = 'N';
$actionRows[5]['categoryPermissionParent'] = 'N';
$actionRows[5]['categoryPermissionOther'] = 'N';

$array = array();
$array['sourceModuleName'] = 'ATL';
$array['sourceModuleAction'] = 'View ATLs_all';
$array['sourceModuleInclude'] = 'hook_studentProfile_atlView.php';
$hooks[0] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'ATL', 'Student Profile', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";

$array = array();
$array['sourceModuleName'] = 'ATL';
$array['sourceModuleAction'] = 'View ATLs_myChildrens';
$array['sourceModuleInclude'] = 'hook_parentalDashboard_atlView.php';
$hooks[1] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'ATL', 'Parental Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";

$array = array();
$array['sourceModuleName'] = 'ATL';
$array['sourceModuleAction'] = 'View ATLs_mine';
$array['sourceModuleInclude'] = 'hook_studentDashboard_atlView.php';
$hooks[2] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'ATL', 'Student Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";
