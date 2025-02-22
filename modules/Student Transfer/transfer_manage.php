<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Forms\Form;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\System\SettingGateway;

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Student Transfers'));

if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get parameters
$gibbonSchoolYearID = isset($_GET['gibbonSchoolYearID'])
    ? str_pad(preg_replace('/[^0-9]/', '', $_GET['gibbonSchoolYearID']), 10, '0', STR_PAD_LEFT)
    : $session->get('gibbonSchoolYearID');

/** @var TransferGateway $transferGateway */
$transferGateway = $container->get(TransferGateway::class);

/** @var StudentGateway $studentGateway */
$studentGateway = $container->get(StudentGateway::class);

/** @var SettingGateway $settingGateway */
$settingGateway = $container->get(SettingGateway::class);

// Check if tables exist
if (!$transferGateway->tableExists('gibbonStudentTransferLog')) {
    $url = $session->get('absoluteURL').'/index.php?q=/modules/System Admin/module_manage.php';
    $page->addError(__('Required database tables not found. Please reinstall the module.'));
    $page->addWarning(sprintf(__('Click %1$shere%2$s to return to the module management page.'), "<a href='$url'>", '</a>'));
    return;
}

// Check required columns
$requiredColumns = [
    'gibbonStudentTransferLog' => ['schoolNameFrom', 'schoolNameTo', 'status'],
    'gibbonStudentTransferData' => ['category', 'name', 'value'],
    'gibbonStudentTransferAttachment' => ['name', 'path', 'type']
];

foreach ($requiredColumns as $table => $columns) {
    if (!$transferGateway->tableHasColumns($table, $columns)) {
        $url = $session->get('absoluteURL').'/index.php?q=/modules/System Admin/module_manage.php';
        $page->addError(sprintf(__('Required columns not found in table %1$s. Please reinstall the module.'), $table));
        $page->addWarning(sprintf(__('Click %1$shere%2$s to return to the module management page.'), "<a href='$url'>", '</a>'));
        return;
    }
}

// Get search params
$search = $_GET['search'] ?? '';

// Setup search form
$form = Form::create('search', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(__('Search & Filter'));
$form->addHiddenValue('q', '/modules/Student Transfer/transfer_manage.php');
$form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

$row = $form->addRow();
$row->addLabel('search', __('Search'));
$row->addTextField('search')->setValue($search);

$row = $form->addRow();
$row->addSearchSubmit($session, __('Clear Search'));

echo $form->getOutput();



// Setup data table
$table = DataTable::create('studentTransfers');
$table->setTitle(__('Student Transfers'));
$table->setDescription(__('View and manage student transfers between schools.'));
$table->addMetaData('gibbonSchoolYearID', $gibbonSchoolYearID);

 // Add create transfer button
echo "<div class='mt-4 ml-auto'>";
echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Student Transfer/transfer_manage_add.php' class='button'>";
echo __('Create New Transfer');
echo "</a>";
echo "</div>";

// Add bulk actions if batch transfers enabled
if ($session->get('isAdmin') && $settingGateway->getSettingByScope('Student Transfer', 'enableBatchTransfers') == 'Y') {
    $col = $table->addColumn('checkbox', '')
        ->format(function ($row) {
            return '<input type="checkbox" name="transfers[]" value="'.$row['gibbonStudentTransferLogID'].'">';
        });
    $table->addHeaderAction('batch', __('Batch Actions'))
        ->setURL('/modules/Student Transfer/transfer_manage_batch.php')
        ->setIcon('attendance')
        ->displayLabel();
}



// Add columns
$table->addColumn('student', __('Student'))
    ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
    ->format(function ($row) {
        return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
    });

$table->addColumn('yearGroup', __('Year Group'))
    ->sortable(['gibbonYearGroup.nameShort'])
    ->format(function ($row) {
        return $row['yearGroup'];
    });

$table->addColumn('schoolFrom', __('From School'))
    ->sortable(['gibbonStudentTransferLog.schoolNameFrom'])
    ->format(function ($row) {
        return $row['schoolNameFrom'];
    });

$table->addColumn('schoolTo', __('To School'))
    ->sortable(['gibbonStudentTransferLog.schoolNameTo'])
    ->format(function ($row) {
        return $row['schoolNameTo'];
    });

// Get available columns for sorting
$hasTimestampCreated = $transferGateway->tableHasColumns('gibbonStudentTransferLog', ['timestampCreated']);
$hasExportTimestamp = $transferGateway->tableHasColumns('gibbonStudentTransferLog', ['exportTimestamp']);

$table->addColumn('timestampCreated', __('Created'))
    ->sortable($hasTimestampCreated ? ['gibbonStudentTransferLog.timestampCreated'] : [])
    ->format(function ($row) {
        return !empty($row['timestampCreated']) 
            ? Format::dateTime($row['timestampCreated']) 
            : '';
    });

$table->addColumn('exportTimestamp', __('Exported'))
    ->sortable($hasExportTimestamp ? ['gibbonStudentTransferLog.exportTimestamp'] : [])
    ->format(function ($row) {
        return !empty($row['exportTimestamp']) 
            ? Format::dateTime($row['exportTimestamp']) 
            : '';
    });

$table->addColumn('status', __('Status'))
    ->sortable(['gibbonStudentTransferLog.status'])
    ->format(function ($row) {
        $statusClasses = [
            'Pending' => 'message',
            'Exported' => 'warning',
            'Imported' => 'success',
            'Complete' => 'success',
            'Cancelled' => 'error'
        ];
        $output = Format::tag($row['status'], $statusClasses[$row['status']] ?? 'default');
        
        if ($row['status'] == 'Exported' && !empty($row['exportTimestamp'])) {
            $output .= "<br/><small>" . Format::dateTime($row['exportTimestamp']) . "</small>";
        } elseif ($row['status'] == 'Imported' && !empty($row['importTimestamp'])) {
            $output .= "<br/><small>" . Format::dateTime($row['importTimestamp']) . "</small>";
        }
        
        return $output;
    });

// Add actions
$table->addActionColumn()
    ->addParam('gibbonStudentTransferLogID')
    ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
    ->format(function ($row, $actions) use ($gibbonSchoolYearID) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/Student Transfer/transfer_manage_edit.php')
            ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID);

        if ($row['status'] == 'Pending') {
            $actions->addAction('export', __('Export'))
                ->setURL('/modules/Student Transfer/transfer_manage_export.php')
                ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
                ->setIcon('delivery2');
        }

        if ($row['status'] == 'Exported') {
            $actions->addAction('download', __('Download'))
                ->setURL('/modules/Student Transfer/transfer_manage_export.php')
                ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
                ->setIcon('download');
        }

        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/Student Transfer/transfer_manage_delete.php')
            ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->modalWindow(650, 400);
    });

// Query transfers
$criteria = $transferGateway->newQueryCriteria()
    ->searchBy($transferGateway->getSearchableColumns(), $search)
    ->sortBy('gibbonStudentTransferLog.timestampCreated', 'DESC')
    ->fromPOST();

$transfers = $transferGateway->queryTransfers($criteria, $gibbonSchoolYearID);
echo $table->render($transfers);

 