<?php
// Database changes for the API module

$sql = array();
$count = 0;

// Version 0.1.00
$sql[$count][0] = '0.1.00';
$sql[$count][1] = '-- First version, nothing to update';

// Version 0.2.00
$count++;
$sql[$count][0] = '0.2.00';
$sql[$count][1] = '
CREATE TABLE `gibbonOAuthClient` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `name` varchar(100) NOT NULL,
    `secret` varchar(100) NOT NULL,
    `redirectUri` text DEFAULT NULL,
    `grantTypes` varchar(100) NOT NULL DEFAULT "client_credentials",
    `scopes` text DEFAULT NULL,
    `active` enum("Y","N") DEFAULT "Y",
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `dateCreated` datetime NOT NULL,
    `lastAccessed` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `gibbonOAuthAccessToken` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `clientId` int(10) unsigned zerofill NOT NULL,
    `userIdentifier` varchar(100) DEFAULT NULL,
    `expiryDateTime` datetime NOT NULL,
    `revoked` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`),
    KEY `clientId` (`clientId`),
    CONSTRAINT `FK_oauth_access_token_client` FOREIGN KEY (`clientId`) REFERENCES `gibbonOAuthClient` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `gibbonOAuthRefreshToken` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `accessTokenId` int(10) unsigned zerofill NOT NULL,
    `expiryDateTime` datetime NOT NULL,
    `revoked` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`),
    KEY `accessTokenId` (`accessTokenId`),
    CONSTRAINT `FK_oauth_refresh_token_access_token` FOREIGN KEY (`accessTokenId`) REFERENCES `gibbonOAuthAccessToken` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `gibbonOAuthAuthCode` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `clientId` int(10) unsigned zerofill NOT NULL,
    `userIdentifier` varchar(100) NOT NULL,
    `expiryDateTime` datetime NOT NULL,
    `revoked` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`),
    KEY `clientId` (`clientId`),
    CONSTRAINT `FK_oauth_auth_code_client` FOREIGN KEY (`clientId`) REFERENCES `gibbonOAuthClient` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `APIKey`;
';
