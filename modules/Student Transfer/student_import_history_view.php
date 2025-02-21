<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, "/modules/Student Transfer/student_import_history_view.php") == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $studentTransferLogID = $_GET['studentTransferLogID'] ?? '';

    if (empty($studentTransferLogID)) {
        echo $page->getBlankSlate();
        return;
    }

    $transferGateway = $container->get(TransferGateway::class);
    $transfer = $transferGateway->getTransferByID($studentTransferLogID);

    if (empty($transfer)) {
        echo $page->getBlankSlate();
        return;
    }

    $progress = !empty($transfer['importProgress']) ? json_decode($transfer['importProgress'], true) : [];

    // Start output
    ?>
    <div class="w-full p-4">
        <div class="flex flex-col gap-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-bold"><?php echo __('Import Details'); ?></h2>
                <div class="text-sm text-gray-600"><?php echo Format::dateTime($transfer['timestampCreated']); ?></div>
            </div>

            <!-- Status and School Info -->
            <div class="grid grid-cols-2 gap-4 bg-white rounded shadow p-4">
                <div>
                    <h3 class="font-semibold mb-2"><?php echo __('Transfer Details'); ?></h3>
                    <table class="w-full">
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('Status'); ?>:</td>
                            <td>
                                <?php if ($transfer['status'] == 'Imported'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded"><?php echo __('Success'); ?></span>
                                <?php elseif ($transfer['status'] == 'Failed'): ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded"><?php echo __('Failed'); ?></span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded"><?php echo __($transfer['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('From School'); ?>:</td>
                            <td><?php echo $transfer['schoolNameFrom']; ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('To School'); ?>:</td>
                            <td><?php echo $transfer['schoolNameTo']; ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('File Name'); ?>:</td>
                            <td><?php echo $progress['filename'] ?? ''; ?></td>
                        </tr>
                    </table>
                </div>

                <?php if (!empty($progress['metadata'])): ?>
                <div>
                    <h3 class="font-semibold mb-2"><?php echo __('Student Details'); ?></h3>
                    <table class="w-full">
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('Name'); ?>:</td>
                            <td><?php echo $progress['metadata']['studentName'] ?? ''; ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('Date of Birth'); ?>:</td>
                            <td><?php echo $progress['metadata']['dateOfBirth'] ?? ''; ?></td>
                        </tr>
                        <?php if (!empty($progress['metadata']['yearGroup'])): ?>
                        <tr>
                            <td class="py-1 pr-4 text-gray-600"><?php echo __('Year Group'); ?>:</td>
                            <td><?php echo $progress['metadata']['yearGroup']; ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Progress Steps -->
            <div class="bg-white rounded shadow p-4">
                <h3 class="font-semibold mb-4"><?php echo __('Import Progress'); ?></h3>
                <div class="flex flex-col gap-2">
                    <?php if (!empty($progress['steps'])): 
                        foreach ($progress['steps'] as $stepName => $stepInfo): ?>
                        <div class="flex items-center gap-2 p-2 border-b">
                            <div class="w-6 h-6 flex items-center justify-center">
                                <?php if ($stepInfo['status'] == 'success'): ?>
                                    <i class="fas fa-check text-green-600"></i>
                                <?php elseif ($stepInfo['status'] == 'error'): ?>
                                    <i class="fas fa-times text-red-600"></i>
                                <?php elseif ($stepInfo['status'] == 'active'): ?>
                                    <i class="fas fa-circle-notch fa-spin text-blue-600"></i>
                                <?php else: ?>
                                    <i class="fas fa-circle text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <div class="font-semibold"><?php echo ucfirst($stepName); ?></div>
                                <?php if (!empty($stepInfo['message'])): ?>
                                    <div class="text-sm text-gray-600"><?php echo $stepInfo['message']; ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($stepInfo['timestamp'])): ?>
                                <div class="text-sm text-gray-500"><?php echo Format::dateTime($stepInfo['timestamp']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; 
                    endif; ?>
                </div>
            </div>

            <!-- Application Link -->
            <?php if (!empty($progress['applicationID'])): ?>
            <div class="bg-white rounded shadow p-4">
                <h3 class="font-semibold mb-2"><?php echo __('Application Form'); ?></h3>
                <p>
                    <a href="<?php echo $session->get('absoluteURL').'/index.php?q=/modules/Students/applicationForm_manage_edit.php&gibbonApplicationFormID='.$progress['applicationID']; ?>" 
                       class="text-blue-600 hover:underline">
                        <?php echo __('View Application Form'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
