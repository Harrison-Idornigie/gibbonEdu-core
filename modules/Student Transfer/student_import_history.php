<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\DataSet;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;

if (isActionAccessible($guid, $connection2, "/modules/Student Transfer/student_import_history.php") == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Import Student Transfer'), 'transfer_manage_import.php')
        ->add(__('Import History'));

    $transferGateway = $container->get(TransferGateway::class);

    $criteria = $transferGateway->newQueryCriteria()
        ->sortBy(['timestampCreated'], 'DESC')
        ->fromPOST();

    $transfers = $transferGateway->queryTransfers($criteria);

    $table = DataTable::create('importHistory');
    $table->setTitle(__('Import History'));

    $table->addColumn('timestamp', __('Date'))
        ->format(function($values) {
            return Format::dateTime($values['timestampCreated']);
        });

    $table->addColumn('user', __('User'))
        ->format(function($values) {
            return Format::name('', $values['preferredName'], $values['surname'], 'Staff', false, true);
        });

    $table->addColumn('schoolNameFrom', __('From School'));

    $table->addColumn('status', __('Status'))
        ->format(function($values) {
            if ($values['status'] == 'Imported') {
                return Format::tag(__('Success'), 'success');
            } else if ($values['status'] == 'Failed') {
                return Format::tag(__('Failed'), 'error');
            } else {
                return Format::tag(__($values['status']), 'message');
            }
        });

    $table->addColumn('progress', __('Progress'))
        ->format(function($values) {
            if (empty($values['importProgress'])) return '';
            
            $progress = json_decode($values['importProgress'], true);
            if (empty($progress['steps'])) return '';

            $html = '<div class="flex flex-col gap-1">';
            foreach ($progress['steps'] as $step => $info) {
                $icon = $info['status'] == 'success' ? '✓' : ($info['status'] == 'error' ? '✗' : '○');
                $color = $info['status'] == 'success' ? 'text-green-600' : ($info['status'] == 'error' ? 'text-red-600' : 'text-gray-600');
                $html .= '<div class="text-xs '.$color.'">'.$icon.' '.ucfirst($step).'</div>';
            }
            $html .= '</div>';
            return $html;
        });

    $table->addActionColumn()
        ->addParam('studentTransferLogID')
        ->format(function($values, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/Student Transfer/student_import_history_view.php')
                ->modalWindow(800, 550);
        });

    echo $table->render($transfers);
}
