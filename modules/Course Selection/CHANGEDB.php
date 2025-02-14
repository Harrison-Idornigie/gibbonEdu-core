<?php
//USE ;end TO SEPERATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql=array() ;
$count=0 ;

//v0.0.00
$sql[$count][0]="0.0.00" ;


//v0.0.01
$count++;
$sql[$count][0]="0.0.01" ;

//v0.0.02
$count++;
$sql[$count][0]="0.0.02" ;
$sql[$count][1]="
ALTER TABLE `courseSelectionChoice` ADD `courseSelectionBlockID` INT(10) UNSIGNED ZEROFILL NULL AFTER `gibbonCourseID`;end
ALTER TABLE `courseSelectionChoice` CHANGE `status` `status` ENUM('Required','Approved','Requested','Selected','Recommended','Declined','Removed') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Selected';end
ALTER TABLE `courseSelectionOfferingBlock` ADD `sequenceNumber` INT(3) NULL AFTER `maxSelect`;end" ;

//v0.0.03
$count++;
$sql[$count][0]="0.0.03" ;
$sql[$count][1]="
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Copy Course Selections', 0, 'Tools', 'Create new course selections from existing course enrolments.', 'tools_copy.php', 'tools_copy.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Copy Course Selections'));end" ;

//v0.0.04
$count++;
$sql[$count][0]="0.0.04" ;
$sql[$count][1]="
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'selectionComplete', 'Message on Completion', 'The text to display when the course selection process is complete.', 'Great! The course selection form is complete, you\'re ready to submit.');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'selectionInvalid', 'Message on Invalid', 'The text to display when an invalid selection has been made.', 'The form is incomplete or contains an invalid choice. Please check your course selections above.');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'selectionContinue', 'Message to Continue', 'The text to display when the course selection is in progress.', 'Continue selecting courses. You can submit a partial selection now and complete your choices at a later date.');end";

//v0.0.05
$count++;
$sql[$count][0]="0.0.05" ;
$sql[$count][1]="
UPDATE `gibbonAction` SET `name`='Copy Selections By Course', `URLList`='tools_copyByCourse.php', `entryURL`='tools_copyByCourse.php' WHERE name='Copy Course Selections' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonModule.name='Course Selection');end
";

//v0.0.06
$count++;
$sql[$count][0]="0.0.06" ;
$sql[$count][1]="
CREATE TABLE `courseSelectionApproval` (
  `courseSelectionApprovalID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `courseSelectionChoiceID` INT(12) UNSIGNED ZEROFILL DEFAULT NULL,
  `gibbonPersonIDApproved` INT(10) UNSIGNED ZEROFILL NULL ,
  `timestampApproved` DATETIME NULL ,
  PRIMARY KEY (`courseSelectionApprovalID`)
) ENGINE=MyISAM CHARSET=utf8 COLLATE utf8_general_ci;end
CREATE TABLE `courseSelectionRecommendation` (
  `courseSelectionRecommendationID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL DEFAULT NULL,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NOT NULL ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `gibbonPersonIDRecommended` INT(10) UNSIGNED ZEROFILL NULL ,
  `timestampRecommended` DATETIME NULL ,
  PRIMARY KEY (`courseSelectionRecommendationID`)
) ENGINE=MyISAM CHARSET=utf8 COLLATE utf8_general_ci;end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'activeSchoolYear', 'Course Selection School Year', 'Sets the default school year to be pre-selected on various pages.', (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Upcoming' ORDER BY sequenceNumber ASC LIMIT 1));end
";

//v0.0.07
$count++;
$sql[$count][0]="0.0.07" ;
$sql[$count][1]="
UPDATE `gibbonAction` SET `name`='Course Approval by Offering', `URLList`='approval_byOffering.php', `entryURL`='approval_byOffering.php' WHERE name='Course Approval by Student' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonModule.name='Course Selection');end
ALTER TABLE `courseSelectionApproval` DROP `courseSelectionApprovalID`;end
ALTER TABLE `courseSelectionApproval` ADD PRIMARY KEY(`courseSelectionChoiceID`);end
";

//v0.0.08
$count++;
$sql[$count][0]="0.0.08" ;
$sql[$count][1]="
UPDATE `gibbonAction` SET `name`='Total Requests by Course', `URLList`='report_requestsByCourse.php', `entryURL`='report_requestsByCourse.php' WHERE name='Course Selection Numbers' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonModule.name='Course Selection');end
";

//v0.0.09
$count++;
$sql[$count][0]="0.0.09" ;
$sql[$count][1]="
UPDATE `gibbonAction` SET `name`='Approve Requests by Course', `URLList`='approval_byCourse.php', `entryURL`='approval_byCourse.php', category='Approval' WHERE name='Course Approval by Class' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonModule.name='Course Selection');end
UPDATE `gibbonAction` SET `name`='Approve Requests by Offering', category='Approval' WHERE name='Course Approval by Offering' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonModule.name='Course Selection');end
UPDATE `gibbonAction` SET `name`='Copy Requests By Course' WHERE name='Copy Selections By Course' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonModule.name='Course Selection');end
";

//v0.1.00
$count++;
$sql[$count][0]="0.1.00" ;
$sql[$count][1]="
CREATE TABLE `courseSelectionTTResult` (
  `courseSelectionTTResultID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NULL ,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NULL ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `gibbonCourseClassID` INT(8) UNSIGNED ZEROFILL NULL ,
  `weight` DECIMAL(6,2) NULL ,
  PRIMARY KEY (`courseSelectionTTResultID`),
  INDEX `gibbonSchoolYear` (`gibbonSchoolYearID`, `gibbonPersonIDStudent`)
) ENGINE=MyISAM CHARSET=utf8 COLLATE utf8_general_ci;end
CREATE TABLE `courseSelectionTTFlag` (
  `courseSelectionTTFlagID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NULL ,
  `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NULL ,
  `gibbonCourseClassID` INT(8) UNSIGNED ZEROFILL NULL ,
  `type` VARCHAR(30) NULL ,
  `reason` VARCHAR(255) NULL ,
  PRIMARY KEY (`courseSelectionTTFlagID`),
   INDEX `gibbonSchoolYear` (`gibbonSchoolYearID`, `gibbonPersonIDStudent`, `gibbonCourseClassID`)
) ENGINE=MyISAM CHARSET=utf8 COLLATE utf8_general_ci;end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'timetablingResults', 'Timetabling Results', 'Performance and result counts from last timetable operation.', '');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Timetabling Engine', 0, 'Timetable', '', 'tt_engine.php', 'tt_engine.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Timetabling Engine'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'View Results by Course', 0, 'Timetable', '', 'tt_resultsByCourse.php', 'tt_resultsByCourse.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='View Results by Course'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'View Results by Student', 0, 'Timetable', '', 'tt_resultsByStudent.php', 'tt_resultsByStudent.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='View Results by Student'));end
";

//v0.1.01
$count++;
$sql[$count][0]="0.1.01" ;
$sql[$count][1]="INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'classEnrolmentMinimum', 'Minimum Students per Class', 'Timetabling will aim to fill every class beyond the minimum.', '');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'classEnrolmentTarget', 'Target Students per Class', 'An ideal amount for timetabling to aim for.', '');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'classEnrolmentMaximum', 'Maximum Students per Class', 'Timetabling will not exceed this amount.', '');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'timetableConflictTollerance', 'Timetabling Conflict Tollerance', 'Maximum number of conflicts allowed per student.', '0');end
ALTER TABLE `courseSelectionTTFlag` ADD `scope` ENUM('Course','Student') NULL AFTER `gibbonCourseClassID`;end
ALTER TABLE `courseSelectionTTFlag` DROP INDEX `gibbonSchoolYear`, ADD INDEX `gibbonSchoolYear` (`gibbonSchoolYearID`, `gibbonPersonIDStudent`, `gibbonCourseClassID`, `scope`);end
";

//v0.1.02
$count++;
$sql[$count][0]="0.1.02" ;
$sql[$count][1]="CREATE TABLE `courseSelectionMetaData` (
  `courseSelectionMetaDataID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT ,
  `gibbonCourseID` INT(8) UNSIGNED ZEROFILL NULL ,
  `enrolmentGroup` VARCHAR(30) NULL ,
  `timetablePriority` DECIMAL(6,2) NULL ,
  `tags` VARCHAR(255) NULL ,
  PRIMARY KEY (`courseSelectionMetaDataID`),
  UNIQUE KEY (`gibbonCourseID`)
) ENGINE=MyISAM CHARSET=utf8 COLLATE utf8_general_ci;end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'genderBalancePriority', 'Gender Balanace Priority', '', '0.5');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'targetEnrolmentPriority', 'Target Enrolment Priority', '', '1.0');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'coreCoursePriority', 'Core Course Priority', '', '1.0');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'avoidConflictPriority', 'Avoid Conflict Priority', '', '2.0');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'autoResolveConflicts', 'Auto-Resolve Conflicts?', 'If enabled conflicts will be resolved by keeping the course with the highest priority.', 'Y');end
ALTER TABLE `courseSelectionTTResult` ADD `status` ENUM('Complete','Flagged','Failed') NULL DEFAULT 'Complete' AFTER `weight`, ADD `flag` VARCHAR(30) NULL AFTER `status`, ADD `reason` VARCHAR(255) NULL AFTER `flag`;end
DROP TABLE IF EXISTS `courseSelectionTTFlag`;end
";


//v0.1.03
$count++;
$sql[$count][0]="0.1.03" ;
$sql[$count][1]="INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Manage Meta Data', 0, 'Administration', '', 'meta_manage.php,meta_manage_addEdit.php,meta_manage_delete.php', 'meta_manage.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Manage Meta Data'));end
";


//v0.1.04
$count++;
$sql[$count][0]="0.1.04" ;
$sql[$count][1]="ALTER TABLE `courseSelectionMetaData` ADD `excludeClasses` VARCHAR(255) NULL  AFTER `tags`;end
";

//v0.1.05
$count++;
$sql[$count][0]="0.1.05" ;
$sql[$count][1]="INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Edit Timetable by Class', 0, 'Tools', '', 'tools_timetableByClass.php', 'tools_timetableByClass.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Edit Timetable by Class'));end
";

//v0.1.06
$count++;
$sql[$count][0]="0.1.06" ;
$sql[$count][1]="
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Upcoming Timetable_all', 0, 'Courses', '', 'upcomingTimetable.php', 'upcomingTimetable.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Upcoming Timetable_all'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Upcoming Timetable_my', 0, 'Courses', '', 'upcomingTimetable.php', 'upcomingTimetable.php', 'N', 'N', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '3', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Upcoming Timetable_my'));end
";

//v0.1.07
$count++;
$sql[$count][0]="0.1.07" ;
$sql[$count][1]="
ALTER TABLE `courseSelectionBlock` DROP `minSelect`, DROP `maxSelect`;end
ALTER TABLE `courseSelectionBlock` ADD `countable` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `description`;end
";

//v0.1.08
$count++;
$sql[$count][0]="0.1.08" ;
$sql[$count][1]="
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'studentOrder', 'Student Order', '', 'yearGroupDesc');end
";

//v0.1.09
$count++;
$sql[$count][0]="0.1.09" ;
$sql[$count][1]="
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Course Selection'), 'Timetable Deletion', 0, 'Tools', '', 'tools_timetableDelete.php', 'tools_timetableDelete.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N') ;end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Course Selection' AND gibbonAction.name='Timetable Deletion'));end
";

//v0.2.00
$count++;
$sql[$count][0]="0.2.00" ;
$sql[$count][1]="
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Course Selection', 'enableCourseGrades', 'Enable Course Grades?', 'If enabled, past course grades from the Reporting module will be displayed in the course selection screen.', 'N');end
";

//v1.0.00
$count++;
$sql[$count][0]="1.0.00" ;
$sql[$count][1]="
";

//v1.1.00
$count++;
$sql[$count][0]="1.1.00" ;
$sql[$count][1]="
";

//v1.1.01
$count++;
$sql[$count][0]="1.1.01" ;
$sql[$count][1]="
";

//v1.1.02
$count++;
$sql[$count][0]="1.1.02" ;
$sql[$count][1]="
";

//v1.1.03
$count++;
$sql[$count][0]="1.1.03" ;
$sql[$count][1]="
";

//v1.1.04
$count++;
$sql[$count][0]="1.1.04" ;
$sql[$count][1]="
";

//v1.2.00
$count++;
$sql[$count][0]="1.2.00" ;
$sql[$count][1]="
";

//v1.2.01
$count++;
$sql[$count][0]="1.2.01" ;
$sql[$count][1]="
";
