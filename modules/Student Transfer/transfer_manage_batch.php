<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\BatchProcessor;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_batch.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Student Transfers'), 'transfer_manage.php')
        ->add(__('Batch Transfer'));

    // Check if batch transfers are enabled
    $settingGateway = $container->get(SettingGateway::class);
    if ($settingGateway->getSettingByScope('Student Transfer', 'enableBatchTransfers') != 'Y') {
        $page->addError(__('Batch transfers are not enabled.'));
        return;
    }

    // Get action parameters
    $step = $_GET['step'] ?? '';
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    // Setup gateways
    $transferGateway = $container->get(TransferGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $batchProcessor = $container->get(BatchProcessor::class);

    if (isset($_POST['address'])) {
        // Validate form inputs
        if (empty($_POST['gibbonPersonIDs']) || empty($_POST['schoolNameTo'])) {
            $page->addError(__('Your request failed because your inputs were invalid.'));
        } else {
            $gibbonPersonIDs = $_POST['gibbonPersonIDs'];
            $schoolNameTo = $_POST['schoolNameTo'];
            $notes = $_POST['notes'] ?? '';

            // Process the batch transfer
            $success = $batchProcessor->processBatchTransfer(
                $gibbonPersonIDs,
                $schoolNameTo,
                $session->get('gibbonPersonID'),
                $notes
            );

            if ($success) {
                $url = $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage.php';
                header("Location: {$url}&return=success0");
                exit;
            } else {
                $page->addError(__('Your request failed due to a database error.'));
            }
        }
    }

    // Get list of students
    $students = $transferGateway->selectActiveStudents($gibbonSchoolYearID)->fetchAll();

    // Get list of schools
    $schools = $transferGateway->selectSchools()->fetchAll();

    // Setup batch transfer form
    $form = Form::create('batchTransfer', $session->get('absoluteURL').'/modules/Student Transfer/transfer_manage_batchProcess.php');
    $form->setTitle(__('Batch Transfer Students'));
    $form->setDescription(__('Select students and destination school to initiate batch transfer.'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('q', '/modules/Student Transfer/transfer_manage_batch.php');
    $form->addHiddenValue('step', 'process');

    $row = $form->addRow();
        $row->addLabel('students', __('Students'));
        $col = $row->addColumn()->addClass('flex-col');
        
    // Add student checkboxes
    foreach ($students as $student) {
        $col->addCheckbox('gibbonPersonIDs[]')
            ->setValue($student['gibbonPersonID'])
            ->setID('student'.$student['gibbonPersonID'])
            ->description($student['surname'].', '.$student['preferredName'].' ('.$student['yearGroup'].')');
    }

    $row = $form->addRow();
        $row->addLabel('schoolNameTo', __('Destination School'));
        $row->addSelect('schoolNameTo')
            ->fromArray(array_column($schools, 'name'))
            ->required();

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')->setRows(3);

    $row = $form->addRow();
        $row->addSubmit(__('Process Batch Transfer'));

    echo $form->getOutput();
}
