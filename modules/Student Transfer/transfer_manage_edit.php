<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

// Module includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_edit.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Proceed!
// Get parameters
$gibbonStudentTransferLogID = isset($_GET['gibbonStudentTransferLogID']) 
    ? preg_replace('/[^0-9]/', '', $_GET['gibbonStudentTransferLogID']) // Clean the ID
    : '';

$gibbonSchoolYearID = isset($_GET['gibbonSchoolYearID'])
    ? str_pad(preg_replace('/[^0-9]/', '', $_GET['gibbonSchoolYearID']), 10, '0', STR_PAD_LEFT)
    : $session->get('gibbonSchoolYearID');

// Ensure ID is properly formatted with leading zeros
if (!empty($gibbonStudentTransferLogID)) {
    $gibbonStudentTransferLogID = str_pad($gibbonStudentTransferLogID, 12, '0', STR_PAD_LEFT);
}

// Check if tables exist first
if (!checkTablesExist($connection2)) {
    $page->addError(__('Required database tables not found. Please reinstall the module.'));
    return;
}

// Validate the database relationships exist
$transferGateway = $container->get(TransferGateway::class);
$transfer = $transferGateway->getTransferByID($gibbonStudentTransferLogID);

if (empty($gibbonStudentTransferLogID) || empty($transfer)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add(__('Manage Student Transfers'), 'transfer_manage.php')
    ->add(__('Edit Student Transfer'));

// Get student info
$studentGateway = $container->get(StudentGateway::class);
$settingGateway = $container->get(SettingGateway::class);

// First try to get active student
$student = $studentGateway->getByID($transfer['gibbonPersonID']);

// If not found, try to get any student record (including inactive)
if (empty($student)) {
    $sql = "SELECT gibbonPerson.* FROM gibbonPerson 
            WHERE gibbonPersonID=:gibbonPersonID";
    $student = $connection2->prepare($sql);
    $student->execute(['gibbonPersonID' => $transfer['gibbonPersonID']]);
    $student = $student->fetch();
}

// Create form
$form = Form::create('studentTransfer', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/transfer_manage_editProcess.php');
        
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID);

// STUDENT INFORMATION
$row = $form->addRow();
$row->addHeading(__('Student Information'))
    ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
    ->addClass('toggleDetails')
    ->addClass('font-bold');
        
$row = $form->addRow();
$row->addContent(!empty($student) 
    ? __('View details of the student being transferred.')
    : __('Warning: The associated student record could not be found.'))
    ->wrap('<div class="text-gray-600 text-sm mt-2">', '</div>');

$row = $form->addRow();
$row->addLabel('studentName', __('Student'));
if (!empty($student)) {
    $row->addTextField('studentName')
        ->setValue(Format::name('', $student['preferredName'], $student['surname'], 'Student'))
        ->readonly();
} else {
    $row->addTextField('studentName')
        ->setValue(__('Student ID: ') . $transfer['gibbonPersonID'])
        ->readonly();
}

// TRANSFER DETAILS
$row = $form->addRow();
$row->addHeading(__('Transfer Details'))
    ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
    ->addClass('toggleDetails')
    ->addClass('font-bold');
        
$row = $form->addRow();
$row->addContent(__('Edit the transfer details.'))
    ->wrap('<div class="text-gray-600 text-sm mt-2">', '</div>');

// Get destination schools from settings
$destinationSchools = array_map('trim', explode(',', $settingGateway->getSettingByScope('Student Transfer', 'destinationSchools')));
$destinationSchoolOptions = array_combine($destinationSchools, $destinationSchools);

$row = $form->addRow();
    $row->addLabel('schoolNameFrom', __('Source School'))
        ->description(__('The school the student is transferring from'));
    $row->addTextField('schoolNameFrom')
        ->setValue($transfer['schoolNameFrom'])
        ->readonly();

$row = $form->addRow();
    $row->addLabel('schoolNameTo', __('Destination School'))
        ->description(__('Select the receiving school'));
    if (!empty($destinationSchoolOptions)) {
        $row->addSelect('schoolNameTo')
            ->fromArray($destinationSchoolOptions)
            ->required()
            ->placeholder()
            ->selected($transfer['schoolNameTo']);
    } else {
        $row->addTextField('schoolNameTo')
            ->required()
            ->maxLength(100)
            ->setValue($transfer['schoolNameTo'])
            ->placeholder(__('No schools configured in settings'));
    }

$row = $form->addRow();
$row->addLabel('status', __('Status'));
$row->addSelect('status')
    ->fromArray([
        'Pending' => __('Pending'),
        'Exported' => __('Exported'),
        'Imported' => __('Imported'),
        'Complete' => __('Complete'),
        'Cancelled' => __('Cancelled')
    ])
    ->selected($transfer['status'])
    ->required();

$row = $form->addRow();
$row->addLabel('notes', __('Notes'))
    ->description(__('Any additional information about this transfer.'));
$row->addTextArea('notes')
    ->setValue($transfer['notes'])
    ->setRows(5);

$row = $form->addRow();
$row->addFooter();
$row->addSubmit();

echo $form->getOutput();
