<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Tables;

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

/**
 * TransferLog
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class TransferLog
{
    protected $transferGateway;

    public function __construct(TransferGateway $transferGateway)
    {
        $this->transferGateway = $transferGateway;
    }

    public function createTable($criteria)
    {
        $transfers = $this->transferGateway->queryTransfers($criteria);

        $table = DataTable::create('transfers');
        $table->setTitle(__('Transfer Log'));

        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/Student Transfer/transfer_manage_add.php')
            ->displayLabel();

        $table->addColumn('studentName', __('Student'))
            ->sortable(['surname', 'preferredName'])
            ->format(function ($row) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
            });

        $table->addColumn('sourceSchool', __('From School'))
            ->sortable();

        $table->addColumn('destinationSchool', __('To School'))
            ->sortable();

        $table->addColumn('status', __('Status'))
            ->sortable()
            ->format(function ($row) {
                return Format::tag($row['status'], [
                    'Pending' => 'message',
                    'Exported' => 'warning',
                    'Imported' => 'success',
                    'Completed' => 'success',
                    'Cancelled' => 'error'
                ]);
            });

        $table->addColumn('timestampCreated', __('Date'))
            ->sortable()
            ->format(Format::using('dateTime'));

        // Add actions column
        $table->addActionColumn()
            ->addParam('studentTransferLogID')
            ->format(function ($row, $actions) {
                if ($row['status'] == 'Pending') {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/Student Transfer/transfer_manage_edit.php');
                }
                if (in_array($row['status'], ['Pending', 'Exported'])) {
                    $actions->addAction('export', __('Export'))
                        ->setURL('/modules/Student Transfer/transfer_manage_export.php')
                        ->setIcon('download');
                }
                if ($row['status'] == 'Exported') {
                    $actions->addAction('import', __('Import'))
                        ->setURL('/modules/Student Transfer/transfer_manage_import.php')
                        ->setIcon('upload');
                }
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/Student Transfer/transfer_manage_view.php');
            });

        return $table;
    }
}
