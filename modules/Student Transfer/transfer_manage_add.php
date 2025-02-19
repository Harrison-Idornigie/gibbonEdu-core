<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_add.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs
    ->add(__('Manage Student Transfers'), 'transfer_manage.php')
    ->add(__('Add Student Transfer'));

// Handle error returns
if (isset($_GET['return'])) {
    $return = $_GET['return'];

    if (substr($return, 0, 5) == 'error') {
        $messages = [
            'error1' => __('Please complete all required fields.'),
            'error2' => __('Your request failed due to a database error.'),
            'error3' => __('Student already has an active transfer.')
        ];
        $page->addError($messages[$return] ?? __('An error has occurred.'));
    } else {
        $page->addMessage(__('Your request was completed successfully.'));
    }
}

// Get current school year
$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

// Get school name from settings
$settingGateway = $container->get(SettingGateway::class);
$schoolName = $settingGateway->getSettingByScope('System', 'organisationName');

// Initialize gateways
$transferGateway = $container->get(TransferGateway::class);

// Get list of eligible students
try {
    $students = getEligibleStudentsForTransfer($container->get('db'), $gibbonSchoolYearID);
} catch (Exception $e) {
    $page->addError(__('Unable to fetch eligible students.'));
    return;
}

// Format student options
$studentOptions = [];
foreach ($students as $student) {
    $studentName = Format::name('', $student['preferredName'], $student['surname'], 'Student');
    $studentOptions[$student['gibbonPersonID']] = $studentName . ' (' . $student['yearGroup'] . ' - ' . $student['rollGroup'] . ')';
}

// Create form
$form = Form::create('studentTransfer', $session->get('absoluteURL').'/modules/Student Transfer/transfer_manage_addProcess.php');
$form->setFactory(DatabaseFormFactory::create($pdo));

$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

// Student Selection
$form->addRow()->addHeading(__('Student Selection'));
$row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Student'))
        ->description(__('Select the student to transfer'));
    $row->addSelect('gibbonPersonID')
        ->fromArray($studentOptions)
        ->required()
        ->placeholder();

// Transfer Details
$form->addRow()->addHeading(__('Transfer Details'));
$row = $form->addRow();
    $row->addLabel('schoolNameFrom', __('Source School'))
        ->description(__('The school the student is transferring from'));
    $row->addTextField('schoolNameFrom')
        ->setValue($schoolName)
        ->readonly();

$row = $form->addRow();
    $row->addLabel('schoolNameTo', __('Destination School'))
        ->description(__('Enter the name of the receiving school'));
    $row->addTextField('schoolNameTo')
        ->required()
        ->maxLength(100);

$row = $form->addRow();
    $row->addLabel('notes', __('Notes'))
        ->description(__('Add any relevant notes about the transfer'));
    $row->addTextArea('notes')
        ->setRows(5);

// Confirmation
$form->addRow()->addHeading(__('Confirmation'));
$row = $form->addRow();
    $row->addLabel('confirm', __('Confirm Transfer'))
        ->description(__('Are you sure you want to initiate this student transfer?'));
    $row->addCheckbox('confirm')
        ->description(__('Yes, I want to initiate this transfer'))
        ->setValue('Y')
        ->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
