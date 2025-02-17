<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

// Module includes - MUST be after gibbon.php
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Student Transfers'), 'transfer_manage.php')
        ->add(__('Add Student Transfer'));

    $studentGateway = $container->get(StudentGateway::class);
    $transferGateway = $container->get(TransferGateway::class);
    
    // Create form factory
    $form = Form::create('studentTransfer', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/transfer_manage_addProcess.php');
    
    // Use DatabaseFormFactory
    $form->setFactory(DatabaseFormFactory::create($pdo));
    
    $form->addHiddenValue('address', $session->get('address'));

    // STUDENT SELECTION
    $form->addSection(__('Student Selection'), __('Select the student to transfer.'));

    // Get eligible students
    $students = getEligibleStudentsForTransfer($pdo, $session->get('gibbonSchoolYearID'));
    $studentOptions = array_reduce($students, function($carry, $student) {
        $carry[$student['gibbonPersonID']] = Format::name('', $student['preferredName'], $student['surname'], 'Student') . 
            ' (' . $student['yearGroup'] . $student['rollGroup'] . ')';
        return $carry;
    }, []);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Student'))
            ->description(__('Select the student to transfer.'))
            ->description(__('Only students who are not already in a transfer process are shown.'));
        $row->addSelect('gibbonPersonID')
            ->fromArray($studentOptions)
            ->required()
            ->placeholder();

    // TRANSFER DETAILS
    $form->addSection(__('Transfer Details'), __('Enter the transfer details.'));

    $row = $form->addRow();
        $row->addLabel('sourceSchool', __('Source School'))
            ->description(__('The school the student is transferring from.'));
        $row->addTextField('sourceSchool')
            ->required()
            ->setValue($session->get('organisationName'))
            ->readonly();

    $row = $form->addRow();
        $row->addLabel('destinationSchool', __('Destination School'))
            ->description(__('The school the student is transferring to.'));
        $row->addTextField('destinationSchool')
            ->required()
            ->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('comments', __('Comments'))
            ->description(__('Any additional information about this transfer.'));
        $row->addTextArea('comments')
            ->setRows(5);

    // CONFIRMATION
    $form->addSection(__('Confirmation'), __('Confirm the transfer details.'));

    $row = $form->addRow();
        $row->addLabel('confirm', __('Confirm Transfer'))
            ->description(__('Are you sure you want to initiate this student transfer?'));
        $row->addCheckbox('confirm')
            ->description(__('Yes'))
            ->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
