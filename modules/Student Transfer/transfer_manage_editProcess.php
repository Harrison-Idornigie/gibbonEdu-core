<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/Student Transfer/transfer_manage_edit.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_edit.php') == false) {
    // Access denied
    $URL = $URL . '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $gibbonStudentTransferLogID = $_POST['gibbonStudentTransferLogID'] ?? '';
    $URL .= "&gibbonStudentTransferLogID=$gibbonStudentTransferLogID";

    if (empty($gibbonStudentTransferLogID)) {
        $URL = $URL . '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $transferGateway = $container->get(TransferGateway::class);
    $transfer = $transferGateway->getTransferByID($gibbonStudentTransferLogID);

    if (empty($transfer)) {
        $URL = $URL . '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Get the form data
    $data = [
        'status' => $_POST['status'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'timestampModified' => date('Y-m-d H:i:s')
    ];

    // Validate required fields
    if (empty($data['status'])) {
        $URL = $URL . '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    if (!$transferGateway->update($gibbonStudentTransferLogID, $data)) {
        $URL = $URL . '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Success
    $URL = $URL . '&return=success0';
    header("Location: {$URL}");
    exit;
}
