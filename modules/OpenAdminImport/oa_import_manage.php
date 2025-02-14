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

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\System\LogGateway;
use Gibbon\Module\OpenAdminImport\Domain\OpenAdminImporter;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('OpenAdmin Import'));

    // Get import types
    $importTypes = [
        'students' => [
            'name' => __('Students'),
            'category' => __('People'),
            'description' => __('Import student data from OpenAdmin'),
            'type' => 'openadmin_students',
            'table' => 'gibbonPerson',
            'mode' => ['insert' => 'Insert', 'update' => 'Update', 'sync' => 'Sync'],
        ],
        'staff' => [
            'name' => __('Staff'),
            'category' => __('People'),
            'description' => __('Import staff data from OpenAdmin'),
            'type' => 'openadmin_staff',
            'table' => 'gibbonStaff',
            'mode' => ['insert' => 'Insert', 'update' => 'Update', 'sync' => 'Sync'],
        ],
    ];

    // Get import history
    $logGateway = $container->get(LogGateway::class);
    $criteria = $logGateway->newQueryCriteria()
        ->sortBy('timestamp', 'DESC')
        ->fromPOST();

    $logs = $logGateway->queryLogs($criteria, 'OpenAdmin Import', null, 'Import - %');

    // IMPORT TYPES TABLE
    $table = DataTable::create('importTypes');
    $table->setTitle(__('Available Imports'));

    $table->addHeaderAction('history', __('Import History'))
        ->setURL('/modules/OpenAdminImport/oa_import_history.php')
        ->setIcon('clock')
        ->displayLabel();

    $table->addColumn('category', __('Category'))
        ->width('15%');

    $table->addColumn('name', __('Name'))
        ->width('20%')
        ->format(function ($values) {
            return Format::link('./index.php?q=/modules/OpenAdminImport/oa_import_run.php&type=' . urlencode($values['type']), $values['name']);
        });

    $table->addColumn('description', __('Description'));

    $table->addColumn('lastImport', __('Last Import'))
        ->format(function ($values) use ($logs) {
            if ($log = $logs->getRow($values['type'])) {
                return Format::relativeTime($log['timestamp']);
            }
            return Format::small(__('Never'));
        });

    $table->addActionColumn()
        ->addParam('type')
        ->format(function ($values, $actions) {
            $actions->addAction('import', __('Import'))
                ->setURL('/modules/OpenAdminImport/oa_import_run.php')
                ->setIcon('page_white_get');
        });

    // Convert import types to dataset
    $importTypeRows = array_map(function($type, $data) {
        return ['type' => $type] + $data;
    }, array_keys($importTypes), array_values($importTypes));
    
    echo $table->render(new DataSet($importTypeRows));

    // SETTINGS FORM
    $form = Form::create('importSettings', $session->get('absoluteURL').'/modules/OpenAdminImport/oa_import_manageProcess.php');
    $form->setTitle(__('Import Settings'));

    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('fieldDelimiter', __('Field Delimiter'));
        $row->addTextField('fieldDelimiter')
            ->setValue(',')
            ->setTitle(__('Character used to separate fields in the CSV file'))
            ->required();

    $row = $form->addRow();
        $row->addLabel('stringEnclosure', __('String Enclosure'));
        $row->addTextField('stringEnclosure')
            ->setValue('"')
            ->setTitle(__('Character used to enclose strings in the CSV file'))
            ->required();

    $row = $form->addRow();
        $row->addLabel('dryRun', __('Dry Run'));
        $row->addYesNo('dryRun')
            ->selected('N')
            ->setTitle(__('Test the import without making any changes'));

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
