<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Services\Format;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\StudentExporter;

// Module includes
include '../../gibbon.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_export.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $studentTransferLogID = $_GET['studentTransferLogID'] ?? '';

    // Validate the transfer ID
    $transferGateway = $container->get(TransferGateway::class);
    $transfer = $transferGateway->getTransferByID($studentTransferLogID);

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

            // Get the student exporter
            $studentExporter = $container->get(StudentExporter::class);

            try {
                // Generate the ZIP file
                $zipFile = $studentExporter->exportToZip($transfer['gibbonPersonID'], $studentTransferLogID);

                // Set headers for download
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="student_transfer_'.$studentTransferLogID.'.zip"');
                header('Content-Length: ' . filesize($zipFile));
                header('Pragma: public');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

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
