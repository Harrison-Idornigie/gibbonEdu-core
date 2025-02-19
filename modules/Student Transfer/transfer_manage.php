<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

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

// Check if tables exist
if (!checkTablesExist($connection2)) {
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
    if (!checkColumnsExist($connection2, $table, $columns)) {
        $url = $session->get('absoluteURL').'/index.php?q=/modules/System Admin/module_manage.php';
        $page->addError(sprintf(__('Required columns not found in table %1$s. Please reinstall the module.'), $table));
        $page->addWarning(sprintf(__('Click %1$shere%2$s to return to the module management page.'), "<a href='$url'>", '</a>'));
        return;
    }
}

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Proceed!
$page->breadcrumbs->add(__('Manage Student Transfers'));

// Get action parameters
$search = $_GET['search'] ?? '';

// Setup gateways
$transferGateway = $container->get(TransferGateway::class);
$studentGateway = $container->get(StudentGateway::class);
$settingGateway = $container->get(SettingGateway::class);

// Setup search form
$form = Form::create('search', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(__('Search & Filter'));
$form->setClass('noIntBorder fullWidth');

$form->addHiddenValue('q', '/modules/Student Transfer/transfer_manage.php');
$form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

$row = $form->addRow();
    $row->addLabel('search', __('Search For'))->description(__('Student Name, ID'));
    $row->addTextField('search')->setValue($search);

$row = $form->addRow();
    $row->addSearchSubmit($session, __('Clear Search'));

echo $form->getOutput();

// Setup data table
$table = DataTable::create('transfers');
$table->setTitle(__('Student Transfers'));
$table->setDescription(__('View and manage student transfers between schools.'));

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
    ->sortable(['surname', 'preferredName'])
    ->format(function ($row) {
        return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
    });

$table->addColumn('yearGroup', __('Year Group'))
    ->sortable(['yearGroup'])
    ->format(function ($row) {
        return $row['yearGroup'];
    });

$table->addColumn('schoolFrom', __('From School'))
    ->sortable(['schoolNameFrom'])
    ->format(function ($row) {
        return $row['schoolNameFrom'];
    });

$table->addColumn('schoolTo', __('To School'))
    ->sortable(['schoolNameTo'])
    ->format(function ($row) {
        return $row['schoolNameTo'];
    });

$table->addColumn('status', __('Status'))
    ->sortable(['status'])
    ->format(function ($row) {
        $statusClasses = [
            'Pending' => 'message',
            'Exported' => 'warning',
            'Imported' => 'success',
            'Complete' => 'success',
            'Cancelled' => 'error'
        ];
        return Format::tag($row['status'], $statusClasses[$row['status']] ?? 'default');
    });

$table->addColumn('timestamp', __('Date'))
    ->sortable(['timestampCreated'])
    ->format(function ($row) {
        return Format::date($row['timestampCreated']);
    });

// Add actions
$table->addActionColumn()
    ->addParam('gibbonStudentTransferLogID')
    ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
    ->format(function ($row, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/Student Transfer/transfer_manage_edit.php');

        if ($row['status'] == 'Pending') {
            $actions->addAction('export', __('Export'))
                ->setURL('/modules/Student Transfer/transfer_manage_export.php')
                ->setIcon('delivery2');
        }

        if ($row['status'] == 'Exported') {
            $actions->addAction('download', __('Download'))
                ->setURL('/modules/Student Transfer/transfer_download.php')
                ->setIcon('download');
        }

        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/Student Transfer/transfer_manage_delete.php')
            ->modalWindow(650, 400);
    });

// Add filters
$criteria = $transferGateway->newQueryCriteria()
    ->searchBy($transferGateway->getSearchableColumns(), $search)
    ->sortBy('timestampCreated', 'DESC')
    ->fromPOST();

// Get and display transfers
$transfers = $transferGateway->queryTransfers($criteria, $gibbonSchoolYearID);
echo $table->render($transfers);

// Add create transfer button
echo "<div class='mt-4'>";
echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Student Transfer/transfer_manage_add.php' class='button'>";
echo __('Create New Transfer');
echo "</a>";
echo "</div>";
