<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

require_once '../../gibbon.php';

use Gibbon\Module\StudentTransfer\Domain\RetentionManager;
use Gibbon\Domain\System\SettingGateway;

// Get container
$container = $container ?? $gibbon->getContainer();

// Get retention manager
$retentionManager = $container->get(RetentionManager::class);

// Check if auto features are enabled
$settingGateway = $container->get(SettingGateway::class);
$autoArchive = $settingGateway->getSettingByScope('Student Transfer', 'enableAutoArchive');
$autoDelete = $settingGateway->getSettingByScope('Student Transfer', 'enableAutoDelete');

if ($autoArchive != 'Y' && $autoDelete != 'Y') {
    die('Auto retention features are disabled.' . PHP_EOL);
}

try {
    // Process retention
    $results = $retentionManager->processRetention();
    
    // Output results
    echo "Retention processing completed:\n";
    echo "Archived: {$results['archived']}\n";
    echo "Deleted: {$results['deleted']}\n";
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "- {$error}\n";
        }
    }
    
} catch (Exception $e) {
    die('Error processing retention: ' . $e->getMessage() . PHP_EOL);
}
