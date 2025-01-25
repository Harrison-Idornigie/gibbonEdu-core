-- Create OAuth2 tables
CREATE TABLE IF NOT EXISTS `gibbonOAuthClient` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `name` varchar(100) NOT NULL,
    `secret` varchar(100) NOT NULL,
    `redirectUri` text DEFAULT NULL,
    `grantTypes` varchar(100) NOT NULL DEFAULT 'client_credentials',
    `scopes` text DEFAULT NULL,
    `active` enum('Y','N') DEFAULT 'Y',
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `dateCreated` datetime NOT NULL,
    `lastAccessed` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert a test client
INSERT INTO `gibbonOAuthClient` (`identifier`, `name`, `secret`, `grantTypes`, `scopes`, `active`, `gibbonPersonID`, `dateCreated`) 
VALUES ('test_client', 'Test Client', 'test_secret', 'client_credentials', 'students:read', 'Y', 1, NOW());

CREATE TABLE IF NOT EXISTS `gibbonOAuthAccessToken` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `clientId` varchar(100) NOT NULL,
    `userIdentifier` varchar(100) DEFAULT NULL,
    `expiryDateTime` datetime NOT NULL,
    `revoked` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`),
    KEY `clientId` (`clientId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gibbonOAuthRefreshToken` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `accessTokenId` varchar(100) NOT NULL,
    `expiryDateTime` datetime NOT NULL,
    `revoked` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`),
    KEY `accessTokenId` (`accessTokenId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gibbonOAuthAuthCode` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `identifier` varchar(100) NOT NULL,
    `clientId` varchar(100) NOT NULL,
    `userIdentifier` varchar(100) NOT NULL,
    `expiryDateTime` datetime NOT NULL,
    `revoked` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `identifier` (`identifier`),
    KEY `clientId` (`clientId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
