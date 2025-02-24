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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_manage.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get step (1-4)
$step = isset($_GET['step']) ? min(max(1, intval($_GET['step'])), 4) : 1;

// Proceed!
$page->breadcrumbs->add(__('Manage Import'));
$page->breadcrumbs->add(__('Step {number}', ['number' => $step]));

// Check for messages
if (isset($_GET['return'])) {
    returnProcess($guid, $_GET['return'], null, null);
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

// STEP 1: File Upload & Initial Settings
if ($step == 1) {
    $form = Form::create('importForm', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/oa_import_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('step', '2');

    $row = $form->addRow();
        $row->addHeading(__('Step 1 - Upload & Settings'));

    $row = $form->addRow();
        $row->addLabel('importType', __('Import Type'))
            ->description(__('Choose the type of data to import'));
        $row->addSelect('importType')
            ->fromArray([
                'students' => __('Students'),
                'staff' => __('Staff'),
                'families' => __('Families')
            ])
            ->required()
            ->placeholder();

    $row = $form->addRow();
        $row->addLabel('file', __('CSV File'));
        $row->addFileUpload('file')
            ->required()
            ->accepts('.csv');

    $row = $form->addRow();
        $row->addLabel('delimiter', __('CSV Delimiter'))
            ->description(__('Field delimiter in the CSV file'));
        $row->addSelect('delimiter')
            ->fromArray([
                ',' => 'Comma (,)',
                ';' => 'Semicolon (;)',
                '\t' => 'Tab (\t)'
            ])
            ->selected(',')
            ->required();

    $row = $form->addRow();
        $row->addLabel('encoding', __('File Encoding'))
            ->description(__('Character encoding of the CSV file'));
        $row->addSelect('encoding')
            ->fromArray([
                'UTF-8' => 'UTF-8',
                'ISO-8859-1' => 'ISO-8859-1',
                'Windows-1252' => 'Windows-1252'
            ])
            ->selected('UTF-8')
            ->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Proceed to Step 2'));

    echo $form->getOutput();
}
// STEP 2: Field Mapping
else if ($step == 2) {
    // Field mapping form will be added here
    $page->addAlert('info', __('Step 2 - Field Mapping coming soon'));
}
// STEP 3: Dry Run
else if ($step == 3) {
    // Dry run validation will be added here
    $page->addAlert('info', __('Step 3 - Dry Run coming soon'));
}
// STEP 4: Live Import
else if ($step == 4) {
    // Live import process will be added here
    $page->addAlert('info', __('Step 4 - Live Import coming soon'));
}

// Recent imports table
$importGateway = $container->get(ImportGateway::class);
$criteria = $importGateway->newQueryCriteria()
    ->sortBy(['timestampCreated'], 'DESC')
    ->fromPOST();

$imports = $importGateway->queryImportLogs($criteria);

$table = DataTable::createPaginated('recentImports', $criteria);
$table->setTitle(__('Recent Imports'));

$table->addColumn('timestampCreated', __('Date'))
    ->format(function($values) {
        return Format::dateTime($values['timestampCreated']);
    });
$table->addColumn('importType', __('Type'));
$table->addColumn('oafsImportLog.status', __('Status'));
$table->addColumn('recordCount', __('Records'));
$table->addColumn('successCount', __('Successful'));
$table->addColumn('errorCount', __('Errors'));

echo $table->render($imports);
