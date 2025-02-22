<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Ross Parker

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
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage OAFS Import'));

    // Handle return messages
    if (isset($_GET['return'])) {
        $page->return->addReturns([
            'error0' => __('Access denied.'),
            'error1' => __('Import type not specified.'),
            'error2' => __('No file was uploaded.'),
            'error3' => __('Invalid file type. Please upload a CSV file.'),
            'error4' => __('CSV validation failed. Please check the file format.'),
            'error5' => __('An error occurred during import.'),
            'success0' => __('Import completed successfully.'),
            'success1' => __('Dry run completed successfully.'),
            'warning1' => __('Import completed with some errors.')
        ]);
    }

    // Show any validation errors
    if ($session->has('importValidationErrors')) {
        $errors = $session->get('importValidationErrors');
        $session->remove('importValidationErrors');
        
        $page->addAlert('error', __('Validation Errors:').'<br/>'.implode('<br/>', $errors));
    }

    // Show import results
    if ($session->has('importResults')) {
        $results = $session->get('importResults');
        $session->remove('importResults');

        $alertType = $results['errors'] > 0 ? 'warning' : 'success';
        $message = __('Import Results:').'<br/>';
        $message .= sprintf(__('Successful: %d'), $results['success']).'<br/>';
        $message .= sprintf(__('Errors: %d'), $results['errors']);

        if (!empty($results['messages'])) {
            $message .= '<br/><br/>'.__('Messages:').'<br/>'.implode('<br/>', $results['messages']);
        }

        $page->addAlert($alertType, $message);
    }

    // Import form
    $form = Form::create('importForm', $session->get('absoluteURL').'/modules/OpenAdminImport/oa_import_manageProcess.php');
    $form->setTitle(__('Import Data'));
    
    $form->addHiddenValue('address', $session->get('address'));

    // Import mode selection
    $row = $form->addRow();
        $row->addLabel('importMode', __('Import Mode'));
        $row->addSelect('importMode')
            ->fromArray([
                'single' => __('Single File'),
                'batch' => __('Multiple Files')
            ])
            ->required()
            ->placeholder()
            ->addClass('importModeSelect');

    // Single file import options
    $form->toggleVisibilityByClass('singleImport')->onSelect('importMode')->when('single');
    
    $row = $form->addRow()->addClass('singleImport');
        $row->addLabel('importType', __('Import Type'));
        $row->addSelect('importType')
            ->fromArray([
                'staff' => __('Staff'),
                'student' => __('Student')
            ])
            ->required()
            ->placeholder();

    $row = $form->addRow()->addClass('singleImport');
        $row->addLabel('file', __('CSV File'));
        $row->addFileUpload('file')
            ->required()
            ->accepts('.csv');

    // Batch import options
    $form->toggleVisibilityByClass('batchImport')->onSelect('importMode')->when('batch');

    $row = $form->addRow()->addClass('batchImport');
        $col = $row->addColumn();
        $col->addLabel('files[]', __('CSV Files'))
            ->description(__('You can upload multiple CSV files at once. The import type will be determined by the filename prefix (e.g. staff_*.csv or student_*.csv)'));
        $col->addFileUpload('files[]')
            ->required()
            ->accepts('.csv')
            ->setMultiple(true);

    // Common options
    $row = $form->addRow();
        $row->addLabel('dryRun', __('Dry Run'))->description(__('Test the import without making any changes'));
        $row->addCheckbox('dryRun')->setValue('Y');

    $row = $form->addRow();
        $row->addLabel('continueOnError', __('Continue on Error'))->description(__('Continue processing remaining records if an error occurs'));
        $row->addCheckbox('continueOnError')->setValue('Y');

    $row = $form->addRow();
        $row->addSubmit(__('Import'));

    echo $form->getOutput();

    // Recent imports table
    $importGateway = $container->get(ImportGateway::class);
    $criteria = $importGateway->newQueryCriteria()
        ->sortBy(['timestampCreated'], 'DESC')
        ->fromPOST();

    $imports = $importGateway->queryImportLogs($criteria);

    $table = DataTable::createPaginated('recentImports', $criteria);
    $table->setTitle(__('Recent Imports'));

    $table->addColumn('timestampCreated', __('Date'))
        ->format(Format::using('dateTime'));
    $table->addColumn('importType', __('Type'));
    $table->addColumn('oafsImportLog.status', __('Status'));
    $table->addColumn('recordCount', __('Records'));
    $table->addColumn('successCount', __('Successful'));
    $table->addColumn('errorCount', __('Errors'));

    echo $table->render($imports);

  
}
