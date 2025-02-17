<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

// Include Gibbon functions
require_once '../../gibbon.php';
require_once './moduleFunctions.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/Student Transfer/transfer_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_add.php') == false) {
    // Access denied
    $URL = $URL . '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
    $comments = $_POST['comments'] ?? '';

    // Validate required fields
    if (empty($gibbonPersonID)) {
        $URL = $URL . '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Check if student is already in transfer
    if (isStudentInTransfer($pdo, $gibbonPersonID)) {
        $URL = $URL . '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Get transfer gateway
    $transferGateway = $container->get(TransferGateway::class);

    // Prepare data
    $data = [
        'gibbonPersonID' => $gibbonPersonID,
        'gibbonPersonIDCreated' => $session->get('gibbonPersonID'),
        'status' => 'Pending',
        'comments' => $comments,
        'timestampCreated' => date('Y-m-d H:i:s')
    ];

    // Insert transfer record
    $inserted = $transferGateway->insert($data);

    if (!$inserted) {
        $URL = $URL . '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Success
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/Student Transfer/transfer_manage.php&return=success0';
    header("Location: {$URL}");
    exit;
}
