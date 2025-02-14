<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

//This file describes the module, including database tables

//Basic variables
$name="Course Selection" ;
$description="Student Course Request and Timetabling Engine" ;
$entryURL="selection.php" ;
$type="Additional" ;
$category="Learn" ;
$version="1.2.01" ;
$author="Sandra Kuipers" ;
$url="https://github.com/SKuipers" ;

// Module tables
$moduleTables[] = "CREATE TABLE `courseSelectionAccess` (
  `courseSelectionAccessID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `gibbonSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `gibbonRoleIDList` varchar(255) DEFAULT NULL,
  `dateStart` date DEFAULT NULL,
  `dateEnd` date DEFAULT NULL,
  `accessType` enum('View','Request','Select') NOT NULL DEFAULT 'Request',
  PRIMARY KEY (`courseSelectionAccessID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionBlock` (
  `courseSelectionBlockID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `gibbonSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `gibbonDepartmentIDList` varchar(255) DEFAULT NULL,
  `name` varchar(90) NOT NULL,
  `description` varchar(255) NOT NULL,
  `countable` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`courseSelectionBlockID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionBlockCourse` (
  `courseSelectionBlockID` int(10) unsigned zerofill NOT NULL,
  `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
  PRIMARY KEY (`courseSelectionBlockID`,`gibbonCourseID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionOffering` (
  `courseSelectionOfferingID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `gibbonSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `gibbonYearGroupIDList` varchar(255) DEFAULT NULL,
  `name` varchar(90) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `minSelect` smallint(3) DEFAULT NULL,
  `maxSelect` smallint(3) DEFAULT NULL,
  `sequenceNumber` smallint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`courseSelectionOfferingID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionOfferingRestriction` (
  `courseSelectionOfferingRestrictionID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `courseSelectionOfferingID` int(8) unsigned zerofill NOT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill DEFAULT NULL,
  `gibbonYearGroupID` int(3) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`courseSelectionOfferingRestrictionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionOfferingBlock` (
  `courseSelectionOfferingID` int(8) unsigned zerofill NOT NULL,
  `courseSelectionBlockID` int(10) unsigned zerofill NOT NULL,
  `minSelect` smallint(3) DEFAULT NULL,
  `maxSelect` smallint(3) DEFAULT NULL,
  `sequenceNumber` INT(3) NULL,
  PRIMARY KEY (`courseSelectionOfferingID`,`courseSelectionBlockID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionChoice` (
  `courseSelectionChoiceID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NOT NULL ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `courseSelectionBlockID` int(10) unsigned zerofill NULL,
  `status` ENUM('Required','Approved','Requested','Selected','Recommended','Declined','Removed') NOT NULL DEFAULT 'Selected',
  `gibbonPersonIDSelected` INT(10) UNSIGNED ZEROFILL NULL ,
  `timestampSelected` DATETIME NULL ,
  `gibbonPersonIDStatusChange` INT(10) UNSIGNED ZEROFILL NULL ,
  `timestampStatusChange` DATETIME NULL,
  `notes` VARCHAR(255) NULL ,
  PRIMARY KEY (`courseSelectionChoiceID`),
  UNIQUE KEY (`gibbonPersonIDStudent`, `gibbonCourseID`)
) ENGINE=InnoDB CHARSET=utf8 COLLATE utf8_general_ci;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionChoiceOffering` (
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NOT NULL,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NOT NULL,
  `courseSelectionOfferingID` int(8) unsigned zerofill NOT NULL,
  PRIMARY KEY (`gibbonSchoolYearID`,`gibbonPersonIDStudent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionLog` (
  `courseSelectionLogID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `courseSelectionOfferingID` int(8) unsigned zerofill NOT NULL,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NULL,
  `gibbonPersonIDChanged` INT(10) UNSIGNED ZEROFILL NULL,
  `timestampChanged` DATETIME NULL,
  `action` ENUM('Create','Update','Delete') NOT NULL DEFAULT 'Update',
  PRIMARY KEY (`courseSelectionLogID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionApproval` (
  `courseSelectionChoiceID` INT(12) UNSIGNED ZEROFILL NOT NULL,
  `gibbonPersonIDApproved` INT(10) UNSIGNED ZEROFILL NULL ,
  `timestampApproved` DATETIME NULL ,
  PRIMARY KEY (`courseSelectionChoiceID`)
) ENGINE=InnoDB CHARSET=utf8 COLLATE utf8_general_ci;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionRecommendation` (
  `courseSelectionRecommendationID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NOT NULL ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `gibbonPersonIDRecommended` INT(10) UNSIGNED ZEROFILL NULL ,
  `timestampRecommended` DATETIME NULL ,
  PRIMARY KEY (`courseSelectionRecommendationID`)
) ENGINE=InnoDB CHARSET=utf8 COLLATE utf8_general_ci;" ;

$moduleTables[] = "CREATE TABLE `courseSelectionTTResult` (
  `courseSelectionTTResultID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NULL ,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NULL ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `gibbonCourseClassID` INT(8) UNSIGNED ZEROFILL NULL ,
  `weight` DECIMAL(6,2) NULL ,
  `status` ENUM('Complete','Flagged','Failed') NULL DEFAULT 'Complete',
  `flag` VARCHAR(30) NULL,
  `reason` VARCHAR(255) NULL,
  PRIMARY KEY (`courseSelectionTTResultID`),
  INDEX `gibbonSchoolYear` (`gibbonSchoolYearID`, `gibbonPersonIDStudent`)
) ENGINE=InnoDB CHARSET=utf8 COLLATE utf8_general_ci;";

$moduleTables[] = "CREATE TABLE `courseSelectionMetaData` (
  `courseSelectionMetaDataID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `enrolmentGroup` VARCHAR(30) NULL ,
  `timetablePriority` DECIMAL(6,2) NULL ,
  `tags` VARCHAR(255) NULL ,
  `excludeClasses` VARCHAR(255) NULL ,
  PRIMARY KEY (`courseSelectionMetaDataID`),
  UNIQUE KEY (`gibbonCourseID`)
) ENGINE=InnoDB CHARSET=utf8 COLLATE utf8_general_ci;";


//gibbonSettings entries
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'activeSchoolYear', 'Course Selection School Year', 'Sets the default school year to be pre-selected on various pages.', (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Upcoming' ORDER BY sequenceNumber ASC LIMIT 1));";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'requireApproval', 'Require Course Approval', 'Require a staff member to approve course selections.', 'Y');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'infoTextOfferings', 'Course Offerings Introduction', 'Information to display with the course offerings.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'infoTextSelectionBefore', 'Course Selection Introduction', 'Information to display before the course selections form.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'infoTextSelectionAfter', 'Course Selection Postscript', 'Information to display after the course selections form.', '');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'selectionComplete', 'Message on Completion', 'The text to display when the course selection process is complete.', 'Great! The course selection form is complete, you\'re ready to submit.');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'selectionInvalid', 'Message on Invalid', 'The text to display when an invalid selection has been made.', 'The form is incomplete or contains an invalid choice. Please check your course selections above.');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'selectionContinue', 'Message to Continue', 'The text to display when the course selection is in progress.', 'Continue selecting courses. You can submit a partial selection now and complete your choices at a later date.');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'timetablingResults', 'Timetabling Results', 'Performance and result counts from last timetable operation.', '');";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'classEnrolmentMinimum', 'Minimum Students per Class', 'Timetabling will aim to fill every class beyond the minimum.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'classEnrolmentTarget', 'Target Students per Class', 'An ideal amount for timetabling to aim for.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'classEnrolmentMaximum', 'Maximum Students per Class', 'Timetabling will not exceed this amount.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'timetableConflictTollerance', 'Timetabling Conflict Tollerance', 'Maximum number of conflicts allowed per student.', '0');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'genderBalancePriority', 'Gender Balanace Priority', '', '0.5');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'targetEnrolmentPriority', 'Target Enrolment Priority', '', '1.0');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'coreCoursePriority', 'Core Course Priority', '', '1.0');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'avoidConflictPriority', 'Avoid Conflict Priority', '', '2.0');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'autoResolveConflicts', 'Auto-Resolve Conflicts?', 'If enabled conflicts will be resolved by keeping the course with the highest priority.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'enableCourseGrades', 'Enable Course Grades?', 'If enabled, past course grades from the Reporting module will be displayed in the course selection screen.', 'N');";



//Action rows
//One array per action
$actionRows[] = [
    'name'                      => 'Course Selection Access',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'access_manage.php,access_manage_addEdit.php,access_manage_delete.php',
    'entryURL'                  => 'access_manage.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Course Offerings',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'offerings_manage.php,offerings_manage_addEdit.php,offerings_manage_delete.php',
    'entryURL'                  => 'offerings_manage.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Course Blocks',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'blocks_manage.php,blocks_manage_addEdit.php,blocks_manage_delete.php',
    'entryURL'                  => 'blocks_manage.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Meta Data',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'meta_manage.php,meta_manage_addEdit.php,meta_manage_delete.php',
    'entryURL'                  => 'meta_manage.php',
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
    'categoryPermissionOther'   => 'N',
];

// $actionRows[] = [
//     'name'                      => 'Manage Prerequisites',
//     'precedence'                => '0',
//     'category'                  => 'Administration',
//     'description'               => '',
//     'URLList'                   => 'rules_manage.php,rules_manage_addEdit.php,rules_manage_delete.php',
//     'entryURL'                  => 'rules_manage.php',
//     'entrySidebar'              => 'Y',
//     'menuShow'                  => 'Y',
//     'defaultPermissionAdmin'    => 'Y',
//     'defaultPermissionTeacher'  => 'N',
//     'defaultPermissionStudent'  => 'N',
//     'defaultPermissionParent'   => 'N',
//     'defaultPermissionSupport'  => 'N',
//     'categoryPermissionStaff'   => 'Y',
//     'categoryPermissionStudent' => 'N',
//     'categoryPermissionParent'  => 'N',
//     'categoryPermissionOther'   => 'N',
// ];


$actionRows[] = [
    'name'                      => 'Course Selection_all',
    'precedence'                => '1',
    'category'                  => 'Courses',
    'description'               => '',
    'URLList'                   => 'selection.php,selectionChoices.php',
    'entryURL'                  => 'selection.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Course Selection_my',
    'precedence'                => '0',
    'category'                  => 'Courses',
    'description'               => '',
    'URLList'                   => 'selection.php,selectionChoices.php',
    'entryURL'                  => 'selection.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'N',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Upcoming Timetable_all',
    'precedence'                => '0',
    'category'                  => 'Courses',
    'description'               => '',
    'URLList'                   => 'upcomingTimetable.php',
    'entryURL'                  => 'upcomingTimetable.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Upcoming Timetable_my',
    'precedence'                => '0',
    'category'                  => 'Courses',
    'description'               => '',
    'URLList'                   => 'upcomingTimetable.php',
    'entryURL'                  => 'upcomingTimetable.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'N',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Approve Requests by Course',
    'precedence'                => '0',
    'category'                  => 'Approval',
    'description'               => '',
    'URLList'                   => 'approval_byCourse.php',
    'entryURL'                  => 'approval_byCourse.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Approve Requests by Offering',
    'precedence'                => '0',
    'category'                  => 'Approval',
    'description'               => '',
    'URLList'                   => 'approval_byOffering.php',
    'entryURL'                  => 'approval_byOffering.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Activity Log',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => '',
    'URLList'                   => 'report_courseSelectionLog.php',
    'entryURL'                  => 'report_courseSelectionLog.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Total Requests by Course',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => '',
    'URLList'                   => 'report_requestsByCourse.php',
    'entryURL'                  => 'report_requestsByCourse.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Students Not Selected',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => '',
    'URLList'                   => 'report_studentsNotSelected.php',
    'entryURL'                  => 'report_studentsNotSelected.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Students Not Approved',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => '',
    'URLList'                   => 'report_studentsNotApproved.php',
    'entryURL'                  => 'report_studentsNotApproved.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Course Selection Settings',
    'precedence'                => '0',
    'category'                  => 'Settings',
    'description'               => '',
    'URLList'                   => 'settings.php',
    'entryURL'                  => 'settings.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Copy Selections By Course',
    'precedence'                => '0',
    'category'                  => 'Tools',
    'description'               => '',
    'URLList'                   => 'tools_copyByCourse.php',
    'entryURL'                  => 'tools_copyByCourse.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Edit Timetable by Class',
    'precedence'                => '0',
    'category'                  => 'Tools',
    'description'               => '',
    'URLList'                   => 'tools_timetableByClass.php',
    'entryURL'                  => 'tools_timetableByClass.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Timetable Deletion',
    'precedence'                => '0',
    'category'                  => 'Tools',
    'description'               => '',
    'URLList'                   => 'tools_timetableDelete.php',
    'entryURL'                  => 'tools_timetableDelete.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Timetabling Engine',
    'precedence'                => '0',
    'category'                  => 'Timetable',
    'description'               => '',
    'URLList'                   => 'tt_engine.php',
    'entryURL'                  => 'tt_engine.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Results by Course',
    'precedence'                => '0',
    'category'                  => 'Timetable',
    'description'               => '',
    'URLList'                   => 'tt_resultsByCourse.php',
    'entryURL'                  => 'tt_resultsByCourse.php',
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
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Results by Student',
    'precedence'                => '0',
    'category'                  => 'Timetable',
    'description'               => '',
    'URLList'                   => 'tt_resultsByStudent.php',
    'entryURL'                  => 'tt_resultsByStudent.php',
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
    'categoryPermissionOther'   => 'N',
];


//Hooks
// $hooks[0]="" ; //Serialised array to create hook and set options. See Hooks documentation online.
