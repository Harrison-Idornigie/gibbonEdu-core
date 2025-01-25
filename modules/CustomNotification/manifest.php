<?php
/*
Gibbon: the flexible, open school platform
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

// Basic variables
$name = 'CustomNotification';
$description = 'A module for managing custom notifications';
$entryURL = 'notifications_manage.php';
$type = 'Additional';
$category = 'Other';
$version = '0.1.00';
$author = 'Your Name';
$url = '';

// Module tables
$moduleTables[] = "CREATE TABLE `CustomNotificationEvent` (
    `id` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(90) NOT NULL,
    `type` ENUM('Email','SMS','Both') NOT NULL DEFAULT 'Email',
    `recipients` TEXT NOT NULL,
    `template` TEXT NOT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `gibbonModuleID` INT(4) UNSIGNED ZEROFILL NULL,
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `CustomNotificationSubscription` (
    `id` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `eventType` VARCHAR(90) NOT NULL,
    `notifyBy` ENUM('Email','SMS','Both') NOT NULL DEFAULT 'Email',
    `studentID` INT(10) UNSIGNED ZEROFILL NULL COMMENT 'Optional: specific student to monitor',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subscription` (`gibbonPersonID`,`eventType`,`studentID`),
    CONSTRAINT `CustomNotificationSubscription_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `CustomNotificationSubscription_ibfk_2` FOREIGN KEY (`studentID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `CustomNotificationLog` (
    `id` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `eventType` VARCHAR(90) NOT NULL,
    `recipientType` ENUM('Parent', 'Staff', 'Student', 'Other') NOT NULL,
    `recipientID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `notificationType` ENUM('Email', 'SMS') NOT NULL,
    `status` ENUM('Sent', 'Failed') NOT NULL,
    `message` TEXT NOT NULL,
    `error` TEXT NULL,
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `eventType` (`eventType`),
    INDEX `recipientID` (`recipientID`),
    INDEX `timestamp` (`timestamp`),
    CONSTRAINT `CustomNotificationLog_ibfk_1` FOREIGN KEY (`recipientID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Module settings
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES
    ('CustomNotification', 'enableAttendanceNotifications', 'Enable Attendance Notifications', 'Enable notifications for attendance events', 'Y'),
    ('CustomNotification', 'allowParentUnsubscribe', 'Allow Parent Unsubscribe', 'Allow parents to unsubscribe from notifications', 'Y'),
    ('CustomNotification', 'mandatoryNotificationTypes', 'Mandatory Notification Types', 'Comma-separated list of notification types that cannot be unsubscribed from', ''),
    ('CustomNotification', 'attendanceCheckFrequency', 'Attendance Check Frequency', 'How often to check for new attendance records (in minutes)', '5');";

// Action rows
$actionRows[] = [
    'name'                      => 'Manage Notifications',
    'precedence'                => '0',
    'category'                  => 'Admin',
    'description'               => 'Configure notification settings and manage notification events.',
    'URLList'                   => 'notifications_manage.php,notifications_manage_settingsProcess.php',
    'entryURL'                  => 'notifications_manage.php',
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

$actionRows[] = [
    'name'                      => 'Manage My Subscriptions',
    'precedence'                => '0',
    'category'                  => 'Notifications',
    'description'               => 'Subscribe or unsubscribe from notifications.',
    'URLList'                   => 'notifications_subscriptions.php,notifications_subscriptionsProcess.php,notifications_subscriptions_deleteProcess.php',
    'entryURL'                  => 'notifications_subscriptions.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N'
];

$actionRows[] = [
    'name'                      => 'View Notification Log',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View a log of all sent notifications.',
    'URLList'                   => 'notifications_log.php',
    'entryURL'                  => 'notifications_log.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N'
];
