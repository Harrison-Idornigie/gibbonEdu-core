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
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

// Module includes - MUST be first!
require_once __DIR__ . '/../../gibbon.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Student Transfers'));

    // Add multiple schools warning
    echo Format::alert(__('This module allows you to manage student transfers between different schools in your district. Please ensure you have the necessary permissions and data sharing agreements in place.'), 'message');

    // Get gateway from container
    $transferGateway = $container->get(TransferGateway::class);

    // QUERY
    $criteria = $transferGateway->newQueryCriteria()
        ->sortBy('timestampCreated')
        ->fromPOST();

    $transfers = $transferGateway->queryTransfers($criteria);

    // Add transfer button
    echo Format::link('./modules/Student Transfer/transfer_manage_add.php', __('Add Student Transfer'), ['class' => 'button']);

    // DATA TABLE
    $table = DataTable::create('transfers');
    $table->setTitle(__('Recent Transfers'));

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Student Transfer/transfer_manage_add.php')
        ->displayLabel();

    $table->addColumn('gibbonStudentTransferLogID', __('ID'))
        ->format(Format::using('number', 'gibbonStudentTransferLogID'));

    $table->addColumn('student', __('Student'))
        ->sortable(['surname', 'preferredName'])
        ->format(function($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('status', __('Status'))
        ->format(function($row) {
            return Format::tag($row['status'], [
                'Pending' => 'message',
                'Exported' => 'success',
                'Imported' => 'success',
                'Cancelled' => 'error'
            ]);
        });

    $table->addColumn('timestampCreated', __('Date'))
        ->format(Format::using('dateTime', 'timestampCreated'));

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonStudentTransferLogID')
        ->format(function ($transfer, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Student Transfer/transfer_manage_edit.php');
            
            if ($transfer['status'] == 'Pending') {
                $actions->addAction('export', __('Export'))
                    ->setURL('/modules/Student Transfer/transfer_manage_export.php')
                    ->setIcon('download');
            }
            
            if ($transfer['status'] == 'Exported') {
                $actions->addAction('import', __('Import'))
                    ->setURL('/modules/Student Transfer/transfer_manage_import.php')
                    ->setIcon('upload');
            }
            
            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Student Transfer/transfer_manage_delete.php')
                ->modalWindow(650, 400);
        });

    echo $table->render($transfers);
}
