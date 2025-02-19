<?php
/*
Gibbon: the flexible, open school platform
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_settings_manage.php';

// Get database connections
$connection2 = $container->get('db')->getConnection();
$db = $container->get('db');

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_settings_manage.php') == false) {
    // Access denied
    header("Location: {$URL}&return=error0");
    exit;
} else {
    // Proceed!
    $settingGateway = $container->get(SettingGateway::class);
    
    // Regenerate encryption key if requested
    if (!empty($_POST['regenerateKey'])) {
        $securityService = new SecurityService($db, $settingGateway);
        $newKey = bin2hex(random_bytes(32)); // Generate 256-bit key
        $settingGateway->updateSettingByScope('Student Transfer', 'encryptionKey', $newKey);
    }

    // Update other settings
    $settingGateway->updateSettingByScope('Student Transfer', 'requiredDocuments', $_POST['requiredDocuments'] ?? '');
    $settingGateway->updateSettingByScope('Student Transfer', 'retentionPeriodCompleted', $_POST['retentionPeriodCompleted'] ?? '365');
    $settingGateway->updateSettingByScope('Student Transfer', 'enableBatchTransfers', $_POST['enableBatchTransfers'] ?? 'N');

    // Success
    header("Location: {$URL}&return=success0");
}
