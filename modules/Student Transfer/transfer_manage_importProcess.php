<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferImportGateway;
use Gibbon\Module\StudentTransfer\Domain\ImportProcessor;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\NotificationService;
use Gibbon\Data\Validator;

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
$transferImportGateway = $container->get(TransferImportGateway::class);
$importProcessor = $container->get(ImportProcessor::class);
$securityService = $container->get(SecurityService::class);
$notificationService = $container->get(NotificationService::class);

// Get step and form data
$step = $_POST['step'] ?? '';
$mode = $_POST['mode'] ?? '';
$studentTransferImportID = $_POST['studentTransferImportID'] ?? '';
$password = $_POST['password'] ?? '';
$ignoreErrors = $_POST['ignoreErrors'] ?? 'N';
$notifyUsers = $_POST['notifyUsers'] ?? 'Y';
$confirmDuplicates = $_POST['confirmDuplicates'] ?? 'N';

// Validate the step
if (empty($step) || !in_array($step, ['1', '2', '3', '4'])) {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    // STEP 1: FILE UPLOAD AND VALIDATION
    if ($step == '1') {
        // Validate file upload
        if (empty($_FILES['file']['tmp_name'])) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Validate file type
        $fileType = mime_content_type($_FILES['file']['tmp_name']);
        if ($fileType !== 'application/zip') {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Verify ZIP encryption
        if (!$securityService->isZipEncrypted($_FILES['file']['tmp_name'])) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error5';
            header("Location: {$URL}");
            exit;
        }

        // Create temp directory
        $tempDir = sys_get_temp_dir() . '/transfer_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Function to clean up temp directory
        $cleanup = function() use ($tempDir) {
            if (!is_dir($tempDir)) {
                return;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            rmdir($tempDir);
        };

        try {
            // Move uploaded file to temp directory
            $uploadedFile = $_FILES['file']['tmp_name'];
            $targetPath = $tempDir . '/upload.zip';
            if (!move_uploaded_file($uploadedFile, $targetPath)) {
                throw new \Exception('Failed to move uploaded file');
            }

            // Extract and validate the ZIP file
            $zip = new \ZipArchive();
            if ($zip->open($targetPath) !== true) {
                throw new \Exception('Failed to open ZIP file');
            }

            // Set password for decryption
            if (!$zip->setPassword($password)) {
                throw new \Exception('Invalid password');
            }

            // Extract to temp directory
            if (!$zip->extractTo($tempDir)) {
                throw new \Exception('Failed to extract ZIP file');
            }
            $zip->close();

            // Process the import
            $importData = $importProcessor->processImport($tempDir, $password);
            if ($importData === false) {
                throw new \Exception('Failed to process import data');
            }

            // Check for duplicates
            $duplicates = $importProcessor->checkDuplicates($importData['studentData']);

            // Create import record
            $data = [
                'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
                'gibbonPersonIDCreated' => $session->get('gibbonPersonID'),
                'status' => 'Pending',
                'mode' => $mode,
                'ignoreErrors' => $ignoreErrors,
                'notifyUsers' => $notifyUsers,
                'schoolNameFrom' => $importData['metadata']['schoolNameFrom'] ?? '',
                'metadata' => json_encode($importData['metadata']),
                'studentData' => json_encode($importData['studentData']),
                'duplicates' => !empty($duplicates) ? json_encode($duplicates) : null,
                'importProgress' => json_encode([
                    'stage' => 'Upload',
                    'status' => 'Complete',
                    'errors' => [],
                    'warnings' => []
                ]),
                'timestampCreated' => date('Y-m-d H:i:s')
            ];

            // Insert import record
            $studentTransferImportID = $transferImportGateway->insert($data);
            if (empty($studentTransferImportID)) {
                throw new \Exception('Failed to create import record');
            }

            // Redirect to step 2
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=2&studentTransferImportID='.$studentTransferImportID;
            header("Location: {$URL}");
            exit;

        } catch (\Exception $e) {
            $cleanup();
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error0';
            header("Location: {$URL}");
            exit;
        }
    }

    // STEP 2: CONFIRM DATA
    elseif ($step == '2') {
        // Validate import record exists
        if (empty($studentTransferImportID)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Get import data
        $importData = $transferImportGateway->getByID($studentTransferImportID);
        if (empty($importData)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Check for duplicates requiring confirmation
        $duplicates = !empty($importData['duplicates']) ? json_decode($importData['duplicates'], true) : [];
        if (!empty($duplicates) && $confirmDuplicates != 'Y') {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=2&studentTransferImportID='.$studentTransferImportID.'&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Update progress to show we're moving to dry run
        $progress = [
            'stage' => 'DryRun',
            'status' => 'Pending',
            'errors' => [],
            'warnings' => []
        ];
        $transferImportGateway->update($studentTransferImportID, ['importProgress' => json_encode($progress)]);

        // Redirect to dry run step
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=3&studentTransferImportID='.$studentTransferImportID;
        header("Location: {$URL}");
        exit;
    }

    // STEP 3: DRY RUN
    elseif ($step == '3') {
        // Validate import record exists
        if (empty($studentTransferImportID)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Get import data
        $importData = $transferImportGateway->getByID($studentTransferImportID);
        if (empty($importData)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Perform dry run
        $studentData = json_decode($importData['studentData'], true);
        $dryRunResult = $importProcessor->dryRun($studentData, [
            'mode' => $importData['mode'],
            'ignoreErrors' => $importData['ignoreErrors']
        ]);

        // Update progress with dry run results
        $progress = [
            'stage' => 'DryRun',
            'status' => 'Complete',
            'errors' => $dryRunResult['errors'] ?? [],
            'warnings' => $dryRunResult['warnings'] ?? []
        ];
        $transferImportGateway->update($studentTransferImportID, ['importProgress' => json_encode($progress)]);

        // Redirect back to show results
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=3&studentTransferImportID='.$studentTransferImportID;
        header("Location: {$URL}");
        exit;
    }

    // STEP 4: LIVE RUN
    elseif ($step == '4') {
        // Validate import record exists
        if (empty($studentTransferImportID)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Get import data
        $importData = $transferImportGateway->getByID($studentTransferImportID);
        if (empty($importData)) {
            $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=1&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Perform live run
        $studentData = json_decode($importData['studentData'], true);
        $liveRunResult = $importProcessor->liveRun($studentData, [
            'mode' => $importData['mode'],
            'ignoreErrors' => $importData['ignoreErrors']
        ]);

        // Update progress with live run results
        $progress = [
            'stage' => 'LiveRun',
            'status' => 'Complete',
            'errors' => $liveRunResult['errors'] ?? [],
            'warnings' => $liveRunResult['warnings'] ?? [],
            'imported' => $liveRunResult['imported'] ?? 0
        ];
        $transferImportGateway->update($studentTransferImportID, [
            'importProgress' => json_encode($progress),
            'status' => 'Complete'
        ]);

        // Send notifications if enabled
        if ($importData['notifyUsers'] == 'Y') {
            $notificationService->sendTransferNotification(
                $studentTransferImportID,
                'import',
                ['count' => $progress['imported']]
            );
        }

        // Redirect to manage page with success
        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage.php&return=success0';
        header("Location: {$URL}");
        exit;
    }

} catch (\Exception $e) {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&return=error0';
    header("Location: {$URL}");
    exit;
}
