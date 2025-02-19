<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_add.php';

if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_add.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Proceed!
$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$schoolNameTo = $_POST['schoolNameTo'] ?? '';
$notes = $_POST['notes'] ?? '';
$confirm = $_POST['confirm'] ?? '';

// Validate required fields
if (empty($gibbonPersonID) || empty($gibbonSchoolYearID) || empty($schoolNameTo) || empty($confirm)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    // Check if student already has an active transfer
    if (hasActiveTransfer($container->get('db'), $gibbonPersonID)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    // Get school name from settings
    $settingGateway = $container->get(SettingGateway::class);
    $schoolName = $settingGateway->getSettingByScope('System', 'organisationName');

    // Create transfer record
    $data = [
        'gibbonPersonID' => $gibbonPersonID,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'schoolNameFrom' => $schoolName,
        'schoolNameTo' => $schoolNameTo,
        'gibbonPersonIDCreated' => $session->get('gibbonPersonID'),
        'status' => 'Pending',
        'notes' => $notes,
        'timestampCreated' => date('Y-m-d H:i:s')
    ];

    // Insert record
    $transferGateway = $container->get(TransferGateway::class);
    $transferID = $transferGateway->insert($data);

    if ($transferID) {
        // Success - redirect to transfer list
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage.php&return=success0';
    } else {
        // Failed to insert
        $URL .= '&return=error2';
    }

    header("Location: {$URL}");
    exit;
} catch (Exception $e) {
    // Log error for admin
    $session->set('error', $e->getMessage());
    
    // Generic error for user
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
