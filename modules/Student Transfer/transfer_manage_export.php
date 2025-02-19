<?php
/*
Gibbon: the flexible, open school platform

Copyright (c) 2010-2022
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Services\Format;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Domain\Students\FirstAidGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\StudentExporter;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\System\CustomFieldGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;

// Module includes - MUST be after use statements
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_export.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonStudentTransferLogID = $_GET['gibbonStudentTransferLogID'] ?? '';

    // Validate the transfer ID
    $transferGateway = $container->get(TransferGateway::class);
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

            // Get global container and required services
            global $container;

            // Get database connection
            $connection = $container->get('db');

            // Initialize gateways
            $transferGateway = $container->get(TransferGateway::class);
            $userGateway = $container->get(UserGateway::class);
            $studentGateway = $container->get(StudentGateway::class);
            $medicalGateway = $container->get(MedicalGateway::class);
            $firstAidGateway = $container->get(FirstAidGateway::class);
            $settingGateway = $container->get(SettingGateway::class);
            $facilityGateway = $container->get(FacilityGateway::class);
            $customFieldGateway = $container->get(CustomFieldGateway::class);

            // Initialize services
            $session = $container->get('session');
            $securityService = $container->get(SecurityService::class);

            // Initialize StudentExporter with required dependencies
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

            try {
                // Generate the ZIP file
                $result = $studentExporter->exportToZip($transfer['gibbonPersonID'], $transfer['gibbonStudentTransferLogID']);
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
                
                $page->addSuccess(__('Secure download link (valid for 1 hour): {link}', ['link' => Format::link($downloadURL, $downloadURL)]));

                // Output file
                readfile($zipFile);

                // Clean up
                unlink($zipFile);
                exit();
            } catch (\Exception $e) {
                $page->addError(__('There was an error exporting the student data: {error}', ['error' => $e->getMessage()]));
            }
        }
    }
}
