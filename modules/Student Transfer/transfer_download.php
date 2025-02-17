<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

// Module includes
include '../../gibbon.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage.php') == false) {
    die(__('Your request failed because you do not have access to this action.'));
}

// Get the download token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die(__('Invalid download token.'));
}

// Validate the token
$securityService = $container->get(SecurityService::class);
$tokenData = $securityService->validateDownloadToken($token);

if (!$tokenData) {
    die(__('The download link has expired or is invalid.'));
}

// Get the transfer details
$transferGateway = $container->get(TransferGateway::class);
$transfer = $transferGateway->getTransferByID($tokenData['studentTransferLogID']);

if (empty($transfer)) {
    die(__('The requested transfer cannot be found.'));
}

// Get the file path
$filePath = "/path/to/transfers/{$transfer['studentTransferLogID']}.zip";

if (!file_exists($filePath)) {
    die(__('The transfer file cannot be found.'));
}

// Verify the file signature
$signature = $transfer['signature'] ?? '';
if (!empty($signature) && !$securityService->verifySignature($filePath, $signature)) {
    die(__('The file signature is invalid. The file may have been tampered with.'));
}

// Set headers for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="student_transfer_'.$transfer['studentTransferLogID'].'.zip"');
header('Content-Length: ' . filesize($filePath));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

// Output file
readfile($filePath);
exit();
