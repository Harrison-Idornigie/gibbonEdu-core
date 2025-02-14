<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Services\Format;
use Gibbon\Module\OpenAdminImport\Domain\ImportProcessor;
use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;
use Gibbon\Module\OpenAdminImport\Domain\ImportLogGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Check if the request is for progress updates
$isProgressUpdate = !empty($_GET['progress']);

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_run.php') == false) {
    if ($isProgressUpdate) {
        http_response_code(403);
        exit;
    }
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get import configuration
$type = $_POST['type'] ?? '';
$config = getImportConfig($type);
if (empty($config)) {
    if ($isProgressUpdate) {
        http_response_code(400);
        exit;
    }
    $page->addError(__('Invalid import type.'));
    return;
}

// Initialize processor
$processor = new ImportProcessor(
    $pdo,
    $container->get(ImportGateway::class),
    $container->get(ImportLogGateway::class)
);

// For progress updates, process the next batch and return results
if ($isProgressUpdate) {
    $results = $processor->processBatch(50);
    
    // Return progress HTML
    ?>
    <div class="w-full" id="importProgress">
        <div class="relative pt-1">
            <div class="flex mb-2 items-center justify-between">
                <div>
                    <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200">
                        <?php echo __('Progress'); ?>
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold inline-block text-blue-600">
                        <?php echo $results['processed']; ?> / <?php echo $results['total']; ?>
                    </span>
                </div>
            </div>
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200">
                <div style="width:<?php echo ($results['processed'] / $results['total'] * 100); ?>%"
                     class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500">
                </div>
            </div>
        </div>

        <?php if (!empty($results['errors'])): ?>
            <div class="error">
                <?php foreach ($results['errors'] as $error): ?>
                    <div class="text-red-500 text-sm"><?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($results['complete']): ?>
            <div class="success">
                <?php
                echo Format::alert(sprintf(
                    __('Import complete. Processed %1$s rows: %2$s successful, %3$s errors.'),
                    $results['processed'],
                    $results['success'],
                    $results['error']
                ), 'success');
                ?>
            </div>
        <?php else: ?>
            <div hx-get="<?php echo $session->get('absoluteURL'); ?>/modules/OpenAdminImport/oa_import_process.php?progress=1"
                 hx-trigger="load delay:1s"
                 hx-target="#importProgress"
                 hx-swap="outerHTML">
            </div>
        <?php endif; ?>
    </div>
    <?php
    exit;
}

// Start new import process
$file = $_POST['file'] ?? '';
$fieldMap = $_POST['fields'] ?? [];
$options = [
    'delimiter' => $_POST['delimiter'] ?? ',',
    'enclosure' => $_POST['enclosure'] ?? '"',
    'gibbonPersonID' => $session->get('gibbonPersonID'),
    'dryRun' => $_POST['dryRun'] ?? 'N',
];

if (empty($file) || empty($fieldMap)) {
    $page->addError(__('Invalid import configuration.'));
    return;
}

// Initialize the import
if (!$processor->init($file, $config, $fieldMap, $options)) {
    $page->addError(__('Failed to initialize import.'));
    return;
}

// Display the progress interface
?>
<div class="w-full">
    <h2><?php echo __('Import Progress'); ?></h2>
    
    <div id="importProgress" 
         hx-get="<?php echo $session->get('absoluteURL'); ?>/modules/OpenAdminImport/oa_import_process.php?progress=1"
         hx-trigger="load"
         hx-target="this"
         hx-swap="outerHTML">
        <div class="text-center">
            <?php echo __('Starting import...'); ?>
        </div>
    </div>
</div>
<?php
