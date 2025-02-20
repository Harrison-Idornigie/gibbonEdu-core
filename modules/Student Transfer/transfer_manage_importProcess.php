<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\ImportProcessor;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\NotificationService;

require_once '../../gibbon.php';

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_import.php')) {
    // Access denied
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage.php';
    header("Location: {$URL}");
    exit;
}

// Initialize services
$settingGateway = $container->get(SettingGateway::class);
$transferGateway = $container->get(TransferGateway::class);
$importProcessor = $container->get(ImportProcessor::class);
$securityService = $container->get(SecurityService::class);
$notificationService = $container->get(NotificationService::class);

// Get form data
$mode = $_POST['mode'] ?? '';
$studentTransferLogID = $_POST['studentTransferLogID'] ?? '';

// Handle upload mode
if ($mode == 'upload') {
    // Check file upload
    if (empty($_FILES['file']['tmp_name'])) {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate file type
    $fileType = mime_content_type($_FILES['file']['tmp_name']);
    if ($fileType !== 'application/zip') {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Create temp directory
    $tempDir = sys_get_temp_dir().'/gibbon_transfer_'.uniqid();
    if (!mkdir($tempDir, 0755, true)) {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&return=error3';
        header("Location: {$URL}");
        exit;
    }

    try {
        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($_FILES['file']['tmp_name']) !== true) {
            throw new Exception('Failed to open ZIP file');
        }

        // Extract to temp directory
        if (!$zip->extractTo($tempDir)) {
            throw new Exception('Failed to extract ZIP file');
        }
        $zip->close();

        // Validate package structure
        if (!file_exists($tempDir.'/student_data.json') || !file_exists($tempDir.'/metadata.json')) {
            throw new Exception('Invalid package structure');
        }

        // Read metadata
        $metadata = json_decode(file_get_contents($tempDir.'/metadata.json'), true);
        if (empty($metadata)) {
            throw new Exception('Invalid metadata');
        }

        // Verify package password
        $password = $_POST['password'] ?? '';
        if (!$securityService->verifyPackagePassword($password, $metadata['packagePassword'])) {
            throw new Exception('Invalid package password');
        }

        // Create transfer record
        $transferData = [
            'status' => 'Pending Import',
            'schoolNameFrom' => $metadata['schoolNameFrom'],
            'schoolNameTo' => $session->get('organisationName'),
            'gibbonPersonIDCreated' => $session->get('gibbonPersonID'),
            'timestampCreated' => date('Y-m-d H:i:s'),
            'packagePassword' => $metadata['packagePassword'],
            'packagePasswordPlain' => $password,
            'studentData' => json_decode(file_get_contents($tempDir.'/student_data.json'), true)
        ];

        $studentTransferLogID = $transferGateway->insert($transferData);
        if (empty($studentTransferLogID)) {
            throw new Exception('Failed to create transfer record');
        }

        // Send upload notification
        $notificationService->sendTransferNotification(
            $studentTransferLogID,
            'upload',
            ['schoolName' => $metadata['schoolNameFrom']]
        );

        // Move attachments to permanent storage
        $attachmentDir = $tempDir.'/attachments';
        if (is_dir($attachmentDir)) {
            $targetDir = $session->get('absolutePath').'/uploads/studentTransfers/'.$studentTransferLogID;
            if (!rename($attachmentDir, $targetDir)) {
                throw new Exception('Failed to move attachments');
            }
        }

        // Clean up temp directory
        array_map('unlink', glob("$tempDir/*.*"));
        rmdir($tempDir);

        // Redirect to import preview
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&studentTransferLogID='.$studentTransferLogID;
        header("Location: {$URL}");
        exit;

    } catch (Exception $e) {
        // Clean up temp directory
        if (is_dir($tempDir)) {
            array_map('unlink', glob("$tempDir/*.*"));
            rmdir($tempDir);
        }

        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&return=error4';
        header("Location: {$URL}");
        exit;
    }
}

// Handle import mode
if ($mode == 'import') {
    try {
        // Get transfer record
        $transfer = $transferGateway->getByID($studentTransferLogID);
        if (empty($transfer)) {
            throw new Exception('Invalid transfer record');
        }

        // Create application form
        $applicationID = $importProcessor->createApplicationForm($transfer['studentData'], $studentTransferLogID);
        if (empty($applicationID)) {
            throw new Exception('Failed to create application form');
        }

        // Update transfer status
        $transferGateway->update($studentTransferLogID, [
            'status' => 'Imported',
            'timestampModified' => date('Y-m-d H:i:s')
        ]);

        // Send import notification
        $notificationService->sendTransferNotification(
            $studentTransferLogID,
            'import',
            ['applicationID' => $applicationID]
        );

        // Notify previous school
        $notificationService->notifyPreviousSchool(
            $studentTransferLogID,
            'Imported',
            'The student transfer has been successfully imported and is pending review.'
        );

        // Redirect to application form
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Students/applicationForm_manage_edit.php&gibbonApplicationFormID='.$applicationID.'&return=success0';
        header("Location: {$URL}");
        exit;

    } catch (Exception $e) {
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&studentTransferLogID='.$studentTransferLogID.'&return=error0';
        header("Location: {$URL}");
        exit;
    }
}

// Invalid mode
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage.php';
header("Location: {$URL}");
exit;
