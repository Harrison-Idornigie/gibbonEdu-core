<?php
/*
Gibbon: the flexible, open school platform

Copyright (c) 2010-2022
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Domain\Students\FirstAidGateway;
use Gibbon\Domain\System\CustomFieldGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\StudentExporter;
use Gibbon\Services\Format;

// Module includes - MUST be after use statements
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

// Get global container and required services
global $container;

// Get database connection
$connection = $container->get('db');
$pdo = $connection->getConnection();

if (isActionAccessible($guid, $pdo, '/modules/Student Transfer/transfer_manage_export.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonStudentTransferLogID = $_GET['gibbonStudentTransferLogID'] ?? '';

    // Get transfer gateway from container
    $transferGateway = $container->get(TransferGateway::class);

    // Validate the transfer ID
    $transfer = $transferGateway->getTransferByID($gibbonStudentTransferLogID);

    if (empty($transfer)) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        // Check if transfer is in correct state for export
        if (!in_array($transfer['status'], ['Pending', 'Exported'])) {
            $page->addError(__('This transfer cannot be exported in its current state.'));
        } else {
            $page->breadcrumbs
                ->add(__('Manage Student Transfers'), 'transfer_manage.php')
                ->add(__('Export Student Transfer'));

            try {
                // Check for required PHP extensions
                if (!extension_loaded('zip')) {
                    throw new \RuntimeException('The PHP ZIP extension is required for student transfers.');
                }

                // Check for ZIP encryption support
                if (!defined('ZipArchive::EM_AES_256')) {
                    throw new \RuntimeException('Your PHP ZIP extension does not support encryption. Please upgrade to PHP 7.2 or later.');
                }

                // Get student ID from transfer record
                $studentID = $transfer['gibbonPersonID'];
                
                // Initialize required services
                $securityService = new SecurityService($connection);
                $settingGateway = new SettingGateway($connection);
                
                // Verify system install key exists
                if (empty($settingGateway->getSettingByScope('System', 'installKey'))) {
                    throw new \RuntimeException('System install key is not set. Please check system settings.');
                }

                // Initialize remaining services
                $studentGateway = new StudentGateway($connection);
                $facilityGateway = new FacilityGateway($connection);
                $userGateway = new UserGateway($connection);
                $customFieldGateway = new CustomFieldGateway($connection);
                $medicalGateway = new MedicalGateway($connection);
                $firstAidGateway = new FirstAidGateway($connection);

                // Initialize StudentExporter with dependencies
                $studentExporter = new StudentExporter(
                    $connection,
                    $settingGateway,
                    $studentGateway,
                    $facilityGateway,
                    $userGateway,
                    $customFieldGateway,
                    $medicalGateway,
                    $firstAidGateway,
                    $session,
                    $securityService
                );
                
                // Generate the ZIP file
                $result = $studentExporter->exportToZip($studentID, $gibbonStudentTransferLogID);
                $zipFile = $result['path'];
                $password = $result['password'];

                // Update transfer status and store security info
                $transferGateway->update($transfer['gibbonStudentTransferLogID'], [
                    'status' => 'Exported',
                    'exportTimestamp' => date('Y-m-d H:i:s'),
                    'packagePassword' => password_hash($password, PASSWORD_DEFAULT),
                    'downloadToken' => $result['token'],
                    'downloadExpiry' => $result['expiry']
                ]);

                // Verify the ZIP file exists and is readable
                if (!file_exists($zipFile) || !is_readable($zipFile)) {
                    throw new \RuntimeException('Generated ZIP file is not accessible.');
                }

                // Set headers for download
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="student_transfer_'.$transfer['gibbonStudentTransferLogID'].'.zip"');
                header('Content-Length: ' . filesize($zipFile));
                header('Pragma: public');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

                // Display security information to user
                $page->addSuccess(__('Transfer package created successfully.'));
                $page->addSuccess(__('ZIP file password: {password}', ['password' => $password]));
                $page->addSuccess(__('This password has been securely stored and can be retrieved by authorized staff if needed.'));

                // Create secure download link
                $downloadURL = $session->get('absoluteURL') . '/modules/Student Transfer/transfer_download.php';
                $downloadURL .= '?transferID=' . $transfer['gibbonStudentTransferLogID'];
                $downloadURL .= '&token=' . $result['token'];
                
                // Output the file in chunks to handle large files
                if ($fileHandle = fopen($zipFile, 'rb')) {
                    while (!feof($fileHandle) && connection_status() == 0) {
                        echo fread($fileHandle, 8192);
                        flush();
                    }
                    fclose($fileHandle);
                    
                    // Clean up the temporary ZIP file
                    if (file_exists($zipFile)) {
                        unlink($zipFile);
                    }
                    exit();
                } else {
                    throw new \RuntimeException('Failed to read ZIP file for download.');
                }
            } catch (\Exception $e) {
                // Log the full error for administrators
                error_log('Student Transfer Export Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                
                // Show a user-friendly error message
                $page->addError(__('An error occurred while creating the transfer package: {error}', [
                    'error' => $e->getMessage()
                ]));
                
                // Clean up any temporary files
                if (isset($zipFile) && file_exists($zipFile)) {
                    unlink($zipFile);
                }
                if (isset($tempDir) && file_exists($tempDir)) {
                    $this->cleanupTempDir($tempDir);
                }
            }
        }
    }
}
