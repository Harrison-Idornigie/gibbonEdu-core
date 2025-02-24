<?php
/*
Gibbon: the flexible, open school platform
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

use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;
use Gibbon\Module\OpenAdminImport\Domain\ImportProcessor;
use Gibbon\Data\Validator;

// Include Gibbon
include '../../gibbon.php';
include './moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/OpenAdminImport/oa_import_manage.php';

if (!isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_manage.php')) {
    // Access denied
    header("Location: {$URL}&return=error0");
    exit;
}

// Get the current step
$step = $_POST['step'] ?? '1';
$nextStep = $step + 1;

// STEP 1: Process file upload and settings
if ($step == '1') {
    // Basic validation
    if (empty($_FILES['file']['tmp_name'])) {
        header("Location: {$URL}&return=error1");
        exit;
    }

    // Validate file type
    $mimeType = mime_content_type($_FILES['file']['tmp_name']);
    if ($mimeType != 'text/csv' && $mimeType != 'text/plain') {
        header("Location: {$URL}&return=error2");
        exit;
    }

    // Store uploaded file info in session
    $session->set('importFileTemp', $_FILES['file']['tmp_name']);
    $session->set('importType', $_POST['importType']);
    $session->set('importDelimiter', $_POST['delimiter']);
    $session->set('importEncoding', $_POST['encoding']);

    // Proceed to step 2
    header("Location: {$URL}&step=2");
    exit;
}
// STEP 2: Process field mapping
else if ($step == '2') {
    // Get the field mappings
    $fieldMappings = $_POST['fieldMapping'] ?? [];
    
    if (empty($fieldMappings)) {
        header("Location: {$URL}&step=2&return=error3");
        exit;
    }

    // Store mappings in session
    $session->set('importFieldMappings', $fieldMappings);

    // Proceed to step 3
    header("Location: {$URL}&step=3");
    exit;
}
// STEP 3: Process dry run
else if ($step == '3') {
    try {
        // Get import settings from session
        $importType = $session->get('importType');
        $filePath = $session->get('importFileTemp');
        $delimiter = $session->get('importDelimiter');
        $encoding = $session->get('importEncoding');
        $fieldMappings = $session->get('importFieldMappings');

        // Initialize processor
        $processor = new ImportProcessor($pdo);
        $processor->setOptions([
            'dryRun' => true,
            'delimiter' => $delimiter,
            'encoding' => $encoding
        ]);

        // Run validation
        $results = $processor->validate($filePath, $fieldMappings);
        
        // Store results in session
        $session->set('importValidation', $results);

        if ($results['errors'] > 0) {
            $session->set('importValidationErrors', $results['messages']);
            header("Location: {$URL}&step=3&return=error4");
            exit;
        }

        // Proceed to step 4
        header("Location: {$URL}&step=4");
        exit;

    } catch (Exception $e) {
        header("Location: {$URL}&step=3&return=error3");
        exit;
    }
}
// STEP 4: Process live import
else if ($step == '4') {
    try {
        // Get import settings from session
        $importType = $session->get('importType');
        $filePath = $session->get('importFileTemp');
        $delimiter = $session->get('importDelimiter');
        $encoding = $session->get('importEncoding');
        $fieldMappings = $session->get('importFieldMappings');

        // Start transaction
        $connection2->beginTransaction();

        // Initialize processor
        $processor = new ImportProcessor($pdo);
        $processor->setOptions([
            'dryRun' => false,
            'delimiter' => $delimiter,
            'encoding' => $encoding
        ]);

        // Run import
        $results = $processor->import($filePath, $fieldMappings);

        // Log the import
        $importGateway = $container->get(ImportGateway::class);
        $importGateway->logImport($importType, 'Complete', [
            'recordCount' => $results['total'],
            'success' => $results['success'],
            'errors' => $results['errors'],
            'messages' => $results['messages']
        ]);

        // Commit transaction
        $connection2->commit();

        // Store results and clean up
        $session->set('importResults', $results);
        $session->remove('importFileTemp');
        $session->remove('importType');
        $session->remove('importDelimiter');
        $session->remove('importEncoding');
        $session->remove('importFieldMappings');
        $session->remove('importValidation');

        header("Location: {$URL}&return=success0");
        exit;

    } catch (Exception $e) {
        $connection2->rollBack();
        header("Location: {$URL}&step=4&return=error3");
        exit;
    }
}

// Invalid step
header("Location: {$URL}&return=error0");
exit;
