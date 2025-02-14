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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Module\OpenAdminImport\Domain\OpenAdminImporter;
use Gibbon\Module\OpenAdminImport\Forms\ImportFieldMappingForm;
use Gibbon\Module\OpenAdminImport\Tables\ImportPreviewTable;
use ParseCsv\Csv;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_run.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $type = $_GET['type'] ?? '';
    $step = $_GET['step'] ?? 1;

    $page->breadcrumbs
        ->add(__('OpenAdmin Import'), 'oa_import_manage.php')
        ->add(__('Run Import'));

    // Get import configuration
    $config = getImportConfig($type);
    if (empty($config)) {
        $page->addError(__('Invalid import type.'));
        return;
    }

    // STEP 1: File Upload
    if ($step == 1) {
        $form = Form::create('importStep1', $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/oa_import_run.php&type='.$type.'&step=2');
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->setTitle(__('Step 1 - Upload File'));

        $row = $form->addRow();
            $row->addLabel('file', __('File'))
                ->description(__('See notes below for specification.'));
            $row->addFileUpload('file')
                ->required()
                ->accepts('.csv');

        $row = $form->addRow();
            $row->addLabel('mode', __('Mode'));
            $row->addSelect('mode')
                ->fromArray(getImportModes())
                ->required()
                ->placeholder();

        $row = $form->addRow();
            $row->addLabel('delimiter', __('CSV Delimiter'))
                ->description(__('Field delimiter in the CSV file.'));
            $row->addTextField('delimiter')
                ->setValue(',')
                ->maxLength(1)
                ->required();

        $row = $form->addRow();
            $row->addLabel('enclosure', __('Text Enclosure'))
                ->description(__('Character that encloses text fields.'));
            $row->addTextField('enclosure')
                ->setValue('"')
                ->maxLength(1)
                ->required();

        $row = $form->addRow();
            $row->addLabel('dryRun', __('Dry Run'))
                ->description(__('Test the import without making any changes.'));
            $row->addYesNo('dryRun')
                ->required()
                ->selected('Y');

        $row = $form->addRow();
            $row->addSubmit(__('Proceed'));

        echo $form->getOutput();

        // Add specifications as a message box
        $page->addMessage(__('Import specifications and instructions go here...'));

    } elseif ($step == 2) {
        // STEP 2: Field Mapping
        $page->scripts->add('sortable', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js');
        $page->scripts->add('alpine', 'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js', ['defer' => true]);
        
        $importFields = $config['fields'] ?? [];
        
        // Handle file upload
        $file = $_FILES['file'] ?? '';
        if (empty($file) || $file['error'] > 0) {
            $page->addError(__('No file was uploaded'));
            return;
        }

        // Parse CSV headers
        $csv = new Csv();
        $csv->delimiter = $_POST['delimiter'] ?? ',';
        $csv->enclosure = $_POST['enclosure'] ?? '"';
        $csv->heading = true;
        $csv->parseFile($file['tmp_name']);

        if (empty($csv->data)) {
            $page->addError(__('No data was found in the uploaded file.'));
            return;
        }

        // Create field mapping form
        $form = ImportFieldMappingForm::createWithOptions(
            'importStep2',
            $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/oa_import_run.php&type='.$type.'&step=3',
            $csv->titles,
            $importFields
        );

        // Add hidden values to the internal form
        $form->form->addHiddenValue('file', $file['tmp_name']);
        $form->form->addHiddenValue('mode', $_POST['mode']);
        $form->form->addHiddenValue('delimiter', $_POST['delimiter']);
        $form->form->addHiddenValue('enclosure', $_POST['enclosure']);
        $form->form->addHiddenValue('dryRun', $_POST['dryRun']);

        // Add submit button to the internal form
        $row = $form->form->addRow();
            $row->addSubmit(__('Preview Import'));

        echo $form->getOutput();

        // Add preview table
        $table = new ImportPreviewTable($csv->titles, $importFields);
        $table->addData($csv->data);
        
        echo $table->render($csv->data);
    } elseif ($step == 3) {
        // STEP 3: Import Processing
        $page->scripts->add('modules/OpenAdminImport/js/module.js');

        // Add HTMX for progress updates
        echo '<script src="https://unpkg.com/htmx.org@1.9.6"></script>';

        // Create the import form
        $form = Form::create('importStep3', '');
        $form->setTitle(__('Step 3 - Import Data'));

        // Add hidden values
        $form->addHiddenValue('type', $type);
        $form->addHiddenValue('file', $_POST['file']);
        $form->addHiddenValue('delimiter', $_POST['delimiter']);
        $form->addHiddenValue('enclosure', $_POST['enclosure']);
        $form->addHiddenValue('dryRun', $_POST['dryRun']);

        // Add field mappings as hidden values
        foreach ($_POST['fields'] ?? [] as $index => $field) {
            $form->addHiddenValue("fields[{$index}]", $field);
        }

        // Add import options
        $row = $form->addRow();
            $row->addLabel('batchSize', __('Batch Size'))
                ->description(__('Number of rows to process at once.'));
            $row->addNumber('batchSize')
                ->setValue(50)
                ->minimum(1)
                ->maximum(500)
                ->required();

        $row = $form->addRow();
            $row->addLabel('continueOnError', __('Continue on Error'))
                ->description(__('Continue processing if errors occur.'));
            $row->addYesNo('continueOnError')
                ->required()
                ->selected('N');

        // Add the submit button
        $row = $form->addRow();
            $row->addSubmit(__('Start Import'));

        // Add the form output
        echo $form->getOutput();

        // Add the progress container (initially hidden)
        ?>
        <div id="importContainer" class="hidden">
            <div hx-post="<?php echo $session->get('absoluteURL'); ?>/modules/OpenAdminImport/oa_import_process.php"
                 hx-trigger="load"
                 hx-target="#importProgress"
                 hx-vals='<?php echo json_encode($_POST); ?>'
                 id="importProgress">
            </div>
        </div>

        <script>
            window.onload = function() {
                document.getElementById('importStep3').addEventListener('submit', function(e) {
                    e.preventDefault();
                    document.getElementById('importContainer').classList.remove('hidden');
                    document.getElementById('importStep3').classList.add('hidden');
                });
            };
        </script>
        <?php
    }
}
