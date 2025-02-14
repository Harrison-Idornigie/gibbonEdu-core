<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\System\LogGateway;

// Module includes
include './modules/OpenAdminImport/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_history.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('OpenAdmin Import'), 'oa_import_manage.php')
        ->add(__('Import History'));

    // Get import history from log
    $logGateway = $container->get(LogGateway::class);
    $criteria = $logGateway->newQueryCriteria()
        ->sortBy('timestamp', 'DESC')
        ->fromPOST();

    $logs = $logGateway->queryLogs($criteria, 'OpenAdmin Import', null, 'Import - %');

    // Create the table
    $table = DataTable::create('importHistory');
    $table->setTitle(__('Import History'));

    $table->modifyRows(function ($log, $row) {
        if ($log['status'] == 'Failed') $row->addClass('error');
        return $row;
    });

    $table->addColumn('timestamp', __('Date'))
        ->format(Format::using('dateTime'));

    $table->addColumn('title', __('Type'))
        ->format(function ($log) {
            return substr($log['title'], 8); // Remove 'Import - ' prefix
        });

    $table->addColumn('gibbonPersonID', __('User'))
        ->format(Format::using('name', ['', 'preferredName', 'surname', 'Staff', false, true]));

    $table->addColumn('status', __('Status'))
        ->format(function ($log) {
            $data = !empty($log['serialisedArray'])? unserialize($log['serialisedArray']) : [];
            
            if (empty($data)) return Format::tag(__('Failed'), 'error');
            
            $results = $data['results'] ?? [];
            if ($results['success'] > 0 && $results['failed'] == 0) {
                return Format::tag(__('Success'), 'success');
            } elseif ($results['success'] > 0 && $results['failed'] > 0) {
                return Format::tag(__('Partial'), 'warning');
            } else {
                return Format::tag(__('Failed'), 'error');
            }
        });

    $table->addColumn('recordCount', __('Records'))
        ->format(function ($log) {
            $data = !empty($log['serialisedArray'])? unserialize($log['serialisedArray']) : [];
            if (empty($data)) return '0';
            
            $results = $data['results'] ?? [];
            return ($results['success'] ?? 0) . ' / ' . (($results['success'] ?? 0) + ($results['failed'] ?? 0));
        });

    $table->addActionColumn()
        ->addParam('gibbonLogID')
        ->format(function ($log, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/OpenAdminImport/oa_import_history_view.php');
        });

    echo $table->render($logs);
}
