<?php
$name = 'API';
$description = 'Provides RESTful API endpoints for Gibbon data access and integration';
$entryURL = 'api_manage.php';
$type = 'Additional';
$category = 'Admin';
$version = '0.1.00';
$author = 'Your Name';
$url = '';

// Module tables
$moduleTables[] = "CREATE TABLE `APIKey` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `key` varchar(255) NOT NULL,
    `dateCreated` date NOT NULL,
    `lastAccessed` datetime DEFAULT NULL,
    `active` enum('Y','N') DEFAULT 'Y',
    `permissions` text NOT NULL,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Add gibbonSettings entries
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('API', 'apiKey', 'API Key Required', 'Require API key for all requests?', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('API', 'allowedIPs', 'Allowed IPs', 'Comma-separated list of allowed IP addresses', '');";

// Action rows
$actionRows[] = [
    'name' => 'Manage API', 
    'precedence' => '0',
    'category' => 'Settings',
    'description' => 'Allows privileged users to manage API keys and settings',
    'URLList' => 'api_manage.php,api_manage_add.php,api_manage_edit.php,api_manage_delete.php',
    'entryURL' => 'api_manage.php',
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

// Hooks
$hooks[] = ''; // Add hooks if needed
