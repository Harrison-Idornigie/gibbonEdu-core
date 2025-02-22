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

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/OpenAdminImport/oa_import_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_manage.php') == false) {
    // Access denied
    header("Location: {$URL}&return=error0");
    exit;
} else {
    // Proceed!
    $importMode = $_POST['importMode'] ?? '';
    $importType = $_POST['importType'] ?? '';
    $dryRun = isset($_POST['dryRun']) && $_POST['dryRun'] == 'Y';
    $continueOnError = isset($_POST['continueOnError']) && $_POST['continueOnError'] == 'Y';

    // Initialize results array
    $totalResults = [
        'success' => 0,
        'errors' => 0,
        'messages' => []
    ];

    try {
        if ($importMode == 'single') {
            // Single file import
            if (empty($importType)) {
                header("Location: {$URL}&return=error1");
                exit;
            }

            if (empty($_FILES['file']['tmp_name'])) {
                header("Location: {$URL}&return=error2");
                exit;
            }

            // Validate file type
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) != 'csv') {
                header("Location: {$URL}&return=error3");
                exit;
            }

            // Process single file
            $results = processSingleImport($connection2, $importType, $_FILES['file'], $dryRun);
            $totalResults['success'] += $results['success'];
            $totalResults['errors'] += $results['errors'];
            $totalResults['messages'] = array_merge($totalResults['messages'], $results['messages']);

        } elseif ($importMode == 'batch') {
            // Batch import
            if (empty($_FILES['files']['tmp_name']) || !is_array($_FILES['files']['tmp_name'])) {
                header("Location: {$URL}&return=error2");
                exit;
            }

            // Process each file
            foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
                if (empty($tmpName)) continue;

                // Determine import type from filename
                $filename = $_FILES['files']['name'][$index];
                if (preg_match('/^(staff|student)_/i', $filename, $matches)) {
                    $fileImportType = strtolower($matches[1]);
                } else {
                    $totalResults['errors']++;
                    $totalResults['messages'][] = sprintf(__('Invalid filename format: %s. Files must be prefixed with staff_ or student_'), $filename);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                // Validate file type
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if (strtolower($ext) != 'csv') {
                    $totalResults['errors']++;
                    $totalResults['messages'][] = sprintf(__('Invalid file type: %s. Only CSV files are allowed.'), $filename);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                // Process file
                $fileData = [
                    'name' => $_FILES['files']['name'][$index],
                    'type' => $_FILES['files']['type'][$index],
                    'tmp_name' => $tmpName,
                    'error' => $_FILES['files']['error'][$index],
                    'size' => $_FILES['files']['size'][$index]
                ];

                $results = processSingleImport($connection2, $fileImportType, $fileData, $dryRun);
                $totalResults['success'] += $results['success'];
                $totalResults['errors'] += $results['errors'];
                $totalResults['messages'] = array_merge($totalResults['messages'], $results['messages']);

                if (!$continueOnError && $results['errors'] > 0) {
                    break;
                }
            }
        } else {
            header("Location: {$URL}&return=error1");
            exit;
        }

        // Store results in session
        $session->set('importResults', $totalResults);

        // Log the import attempt
        $importGateway = $container->get(ImportGateway::class);
        $status = $totalResults['errors'] > 0 ? 'Warning' : 'Success';
        $importGateway->logImport($importMode == 'batch' ? 'batch' : $importType, $status, [
            'recordCount' => $totalResults['success'] + $totalResults['errors'],
            'success' => $totalResults['success'],
            'errors' => $totalResults['errors'],
            'messages' => $totalResults['messages'],
            'gibbonPersonIDCreated' => $session->get('gibbonPersonID')
        ]);

        if ($totalResults['errors'] > 0) {
            header("Location: {$URL}&return=warning1");
            exit;
        } else {
            header("Location: {$URL}&return=success0");
            exit;
        }

    } catch (Exception $e) {
        $session->set('importError', $e->getMessage());
        header("Location: {$URL}&return=error5");
        exit;
    }
}
