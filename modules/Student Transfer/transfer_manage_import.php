<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\ImportProcessor;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\TransferImportGateway;
use Gibbon\Module\StudentTransfer\Domain\NotificationService;

// Module includes
require_once dirname(__FILE__) . '/../../gibbon.php';
require_once dirname(__FILE__) . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_import.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Initialize services
$transferGateway = $container->get(TransferGateway::class);
$transferImportGateway = $container->get(TransferImportGateway::class);
$importProcessor = $container->get(ImportProcessor::class);
$securityService = $container->get(SecurityService::class);
$notificationService = $container->get(NotificationService::class);

$page->breadcrumbs->add(__('Import Student Transfer'));

// Get step from URL or default to 1
$step = $_GET['step'] ?? 1;
$studentTransferImportID = $_GET['studentTransferImportID'] ?? '';

// Define the steps
$steps = [
    1 => __('Select File'),
    2 => __('Confirm Data'),
    3 => __('Dry Run'),
    4 => __('Live Run')
];

// Display the step progress bar
echo '<ul class="flex justify-between w-full p-4 mb-8 bg-white rounded shadow steps">';
foreach ($steps as $stepNum => $stepName) {
    $stepClass = 'step';
    if ($stepNum < $step) {
        $stepClass .= ' completed'; // Previous steps
    } elseif ($stepNum == $step) {
        $stepClass .= ' active'; // Current step
    }
    printf('<li class="%s">%s</li>', $stepClass, $stepName);
}
echo '</ul>';

echo '<h2>' . __('Step {number}', ['number' => $step]) . ' - ' . $steps[$step] . '</h2>';

if ($step == 1) {
    // STEP 1: FILE SELECTION
    $form = Form::create('importStep1', $session->get('absoluteURL').'/modules/Student Transfer/transfer_manage_importProcess.php');
    $form->setTitle(__('Import Details'));
    
    $form->addHiddenValue('step', '1');
    $form->addHiddenValue('address', $session->get('address'));

    // Add a warning about backing up
    $row = $form->addRow()->addClass('warning');
        $row->addContent(__('Always backup your database before performing an import.'))
            ->wrap('<div class="message warning p-4 rounded">', '</div>');

    // Import mode
    $row = $form->addRow();
        $row->addLabel('mode', __('Mode'));
        $row->addSelect('mode')
            ->fromArray(['Insert' => __('Insert Only'), 'Update' => __('Update Only'), 'Update & Insert' => __('Update & Insert')])
            ->required()
            ->selected('Insert');

    // File upload
    $row = $form->addRow();
        $row->addLabel('file', __('Transfer Package'));
        $row->addFileUpload('file')
            ->required()
            ->accepts('.zip')
            ->setMaxUpload(false);

    // Package password
    $row = $form->addRow();
        $row->addLabel('password', __('Package Password'))
            ->description(__('Enter the password provided by the source school'));
        $row->addPassword('password')
            ->required()
            ->maxLength(255);

    // Options
    $form->addRow()->addHeading(__('Options'));

    $row = $form->addRow();
        $row->addLabel('ignoreErrors', __('Ignore Errors?'))
            ->description(__('Should the import continue if non-critical errors are found?'));
        $row->addYesNo('ignoreErrors')->selected('N');

    $row = $form->addRow();
        $row->addLabel('notifyUsers', __('Notify Users?'))
            ->description(__('Send notifications to relevant staff members?'));
        $row->addYesNo('notifyUsers')->selected('Y');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

} elseif ($step == 2) {
    // STEP 2: DATA PREVIEW
    if (empty($studentTransferImportID)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Get import data
    $importData = $transferImportGateway->getByID($studentTransferImportID);

    if (empty($importData)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Get student data
    $studentData = json_decode($importData['studentData'], true);
    $progress = !empty($importData['importProgress']) ? json_decode($importData['importProgress'], true) : [];
    $duplicates = !empty($progress['duplicates']) ? $progress['duplicates'] : [];

    // Create the form
    $form = Form::create('importPreview', $session->get('absoluteURL').'/modules/Student Transfer/transfer_manage_importProcess.php');
    
    // Add hidden values
    $form->addHiddenValue('step', '2');
    $form->addHiddenValue('studentTransferImportID', $studentTransferImportID);
    $form->addHiddenValue('address', $session->get('address'));

    // STUDENT DETAILS
    $form->addRow()->addHeading(__('Student Details'));

    // Basic Information
    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
    
    $row = $table->addRow();
        $row->addLabel('fullName', __('Full Name'));
        $row->addTextField('fullName')
            ->setValue($studentData['personal']['officialName'])
            ->readonly();

    $row = $table->addRow();
        $row->addLabel('preferredName', __('Preferred Name'));
        $row->addTextField('preferredName')
            ->setValue($studentData['personal']['preferredName'])
            ->readonly();

    $row = $table->addRow();
        $row->addLabel('gender', __('Gender'));
        $row->addTextField('gender')
            ->setValue($studentData['personal']['gender'])
            ->readonly();

    $row = $table->addRow();
        $row->addLabel('dob', __('Date of Birth'));
        $row->addDate('dob')
            ->setValue($studentData['personal']['dob'])
            ->readonly();

    // Contact Information
    $form->addRow()->addSubheading(__('Contact Information'));
    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');

    $row = $table->addRow();
        $row->addLabel('email', __('Email'));
        $row->addTextField('email')
            ->setValue($studentData['personal']['email'])
            ->readonly();

    if (!empty($studentData['personal']['emailAlternate'])) {
        $row = $table->addRow();
            $row->addLabel('emailAlternate', __('Alternate Email'));
            $row->addTextField('emailAlternate')
                ->setValue($studentData['personal']['emailAlternate'])
                ->readonly();
    }

    if (!empty($studentData['personal']['phone1'])) {
        $row = $table->addRow();
            $row->addLabel('phone1', __('Phone 1'));
            $row->addTextField('phone1')
                ->setValue($studentData['personal']['phone1'])
                ->readonly();
    }

    if (!empty($studentData['personal']['phone2'])) {
        $row = $table->addRow();
            $row->addLabel('phone2', __('Phone 2'));
            $row->addTextField('phone2')
                ->setValue($studentData['personal']['phone2'])
                ->readonly();
    }

    // Background Information
    $form->addRow()->addSubheading(__('Background Information'));
    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');

    $backgroundFields = [
        'countryOfBirth' => __('Country of Birth'),
        'ethnicity' => __('Ethnicity'),
        'religion' => __('Religion'),
        'citizenship1' => __('Citizenship 1'),
        'citizenship2' => __('Citizenship 2')
    ];

    foreach ($backgroundFields as $field => $label) {
        $row = $table->addRow();
            $row->addLabel($field, $label);
            $row->addTextField($field)
                ->setValue($studentData['personal'][$field])
                ->readonly();
    }

    // ACADEMIC DETAILS
    $form->addRow()->addHeading(__('Academic Details'));
    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');

    $row = $table->addRow();
        $row->addLabel('previousSchool', __('Previous School'));
        $row->addTextField('previousSchool')
            ->setValue($importData['schoolNameFrom'])
            ->readonly();

    $row = $table->addRow();
        $row->addLabel('yearGroup', __('Year Group'));
        $row->addTextField('yearGroup')
            ->setValue($studentData['academic']['yearGroup']['name'])
            ->readonly();

    $row = $table->addRow();
        $row->addLabel('formGroup', __('Form Group'));
        $row->addTextField('formGroup')
            ->setValue($studentData['academic']['formGroup']['name'])
            ->readonly();

    // GRADES
    if (!empty($studentData['grades'])) {
        $form->addRow()->addHeading(__('Academic Grades'));
        $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
        
        $header = $table->addHeaderRow();
            $header->addContent(__('Course'));
            $header->addContent(__('Grade'));
            $header->addContent(__('Effort'));
            $header->addContent(__('Comment'));
            
        foreach ($studentData['grades'] as $grade) {
            $row = $table->addRow();
                $row->addContent($grade['course'] ?? '');
                $row->addContent($grade['grade'] ?? '');
                $row->addContent($grade['effort'] ?? '');
                $row->addContent($grade['comment'] ?? '');
        }
    }

    // BEHAVIOR RECORDS
    if (!empty($studentData['behavior'])) {
        $form->addRow()->addHeading(__('Behavior Records'));
        $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
        
        $header = $table->addHeaderRow();
            $header->addContent(__('Date'));
            $header->addContent(__('Type'));
            $header->addContent(__('Descriptor'));
            $header->addContent(__('Level'));
            $header->addContent(__('Comment'));
            
        foreach ($studentData['behavior'] as $record) {
            $row = $table->addRow();
                $row->addContent(!empty($record['date']) ? Format::date($record['date']) : '');
                $row->addContent($record['type'] ?? '');
                $row->addContent($record['descriptor'] ?? '');
                $row->addContent($record['level'] ?? '');
                $row->addContent($record['comment'] ?? '');
        }
    }

    // ATTENDANCE RECORDS
    if (!empty($studentData['attendance'])) {
        $form->addRow()->addHeading(__('Attendance Records'));
        $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
        
        $header = $table->addHeaderRow();
            $header->addContent(__('Date'));
            $header->addContent(__('Type'));
            $header->addContent(__('Status'));
            $header->addContent(__('Reason'));
            $header->addContent(__('Context'));
            $header->addContent(__('Comment'));
            
        foreach ($studentData['attendance'] as $record) {
            $row = $table->addRow();
                $row->addContent(!empty($record['date']) ? Format::date($record['date']) : '');
                $row->addContent($record['type'] ?? '');
                $row->addContent($record['code']['name'] ?? '');
                $row->addContent($record['reason'] ?? '');
                $row->addContent($record['context'] ?? '');
                $row->addContent($record['comment'] ?? '');
        }
    } else {
        $form->addRow()->addAlert(__('No attendance records found.'), 'message');
    }

    // MEDICAL INFORMATION
    if (!empty($studentData['medical']['conditions']) || !empty($studentData['medical']['firstAid'])) {
        if (!empty($studentData['medical']['conditions'])) {
            $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
            $table->addHeaderRow()->addContent(__('Medical Conditions'));
            foreach ($studentData['medical']['conditions'] as $condition) {
                $table->addRow()->addContent($condition);
            }
        }

        if (!empty($studentData['medical']['firstAid'])) {
            $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
            $table->addHeaderRow()->addContent(__('First Aid'));
            foreach ($studentData['medical']['firstAid'] as $firstAid) {
                $table->addRow()->addContent($firstAid);
            }
        }
    } else {
        $form->addRow()->addAlert(__('No medical information recorded.'), 'message');
    }

    // FAMILY INFORMATION
    if (!empty($studentData['family'])) {
        $form->addRow()->addHeading(__('Family Information'));
        
        foreach ($studentData['family'] as $relation) {
            $col = $form->addRow()->addColumn();
            $relationship = !empty($relation['relationship']) ? $relation['relationship'] : __('Family Member');
            $col->addContent('<h4>'.$relationship.'</h4>');
            
            $table = $col->addTable()->setClass('smallIntBorder w-full');
            
            // Access adult information from the nested structure
            if (!empty($relation['adult'])) {
                $adult = $relation['adult'];
                $name = implode(' ', array_filter([
                    $adult['title'] ?? '',
                    $adult['surname'] ?? '',
                    $adult['preferredName'] ?? ''
                ]));
                $table->addRow()->addContent($name);
                
                if (!empty($adult['email'])) {
                    $table->addRow()->addContent($adult['email']);
                }
                if (!empty($adult['phone1'])) {
                    $table->addRow()->addContent($adult['phone1']);
                }
                if (!empty($adult['phone2'])) {
                    $table->addRow()->addContent($adult['phone2']);
                }
            }

            // Add family-level information
            if (!empty($relation['homeAddress'])) {
                $table->addRow()->addContent($relation['homeAddress']);
            }
            if (!empty($relation['languageHomePrimary'])) {
                $table->addRow()->addContent(__('Primary Language').': '.$relation['languageHomePrimary']);
            }
            if (!empty($relation['languageHomeSecondary'])) {
                $table->addRow()->addContent(__('Secondary Language').': '.$relation['languageHomeSecondary']);
            }
        }
    }

    // DUPLICATE WARNINGS
    if (!empty($duplicates)) {
        $form->addRow()->addHeading(__('Duplicate Warnings'))->addClass('text-red-600');
        
        foreach ($duplicates as $duplicate) {
            $col = $form->addRow()->addColumn();
            $col->addAlert(
                __('Possible duplicate: ').$duplicate['name'].' ('.__('DOB').': '.Format::date($duplicate['dob']).')', 
                'error'
            );
        }

        $row = $form->addRow();
            $row->addLabel('confirmDuplicates', __('Confirm Import'))
                ->description(__('I confirm that I want to proceed with the import despite possible duplicates.'));
            $row->addCheckbox('confirmDuplicates')
                ->setValue('Y')
                ->required();
    }

    // BUTTONS
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Proceed to Dry Run'));

    echo $form->getOutput();

} elseif ($step == 3) {
    // STEP 3: DRY RUN
    if (empty($studentTransferImportID)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Get import data
    $importData = $transferImportGateway->getByID($studentTransferImportID);
    if (empty($importData)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Create the form
    $form = Form::create('importDryRun', $session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage_import.php&step=4&studentTransferImportID='.$studentTransferImportID);
    $form->setTitle(__('Dry Run'));
    
    // Add hidden values
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('step', '3');
    $form->addHiddenValue('studentTransferImportID', $studentTransferImportID);

    // Add description
    $row = $form->addRow();
        $row->addContent(__('The system will now perform a dry run to validate the data. No changes will be made to the database.'))
            ->wrap('<div class="message emphasis">', '</div>');

    // Progress information
    $progress = !empty($importData['importProgress']) ? json_decode($importData['importProgress'], true) : [];
    
    if (!empty($progress['errors'])) {
        $row = $form->addRow();
            $row->addHeading(__('Errors'))->addClass('text-red-600');

        foreach ($progress['errors'] as $error) {
            $row = $form->addRow();
                $row->addAlert($error, 'error');
        }
    }

    if (!empty($progress['warnings'])) {
        $row = $form->addRow();
            $row->addHeading(__('Warnings'))->addClass('text-yellow-600');

        foreach ($progress['warnings'] as $warning) {
            $row = $form->addRow();
                $row->addAlert($warning, 'warning');
        }
    }

    // Add buttons
    $row = $form->addRow();
        $row->addFooter();
        if (empty($progress['errors']) || $importData['ignoreErrors'] == 'Y') {
            $row->addSubmit(__('Proceed to Live Run'));
        }

    echo $form->getOutput();

    // Perform dry run if not already done
    if ($progress['stage'] != 'DryRun') {
        $studentData = json_decode($importData['studentData'], true);
        $dryRunResult = $importProcessor->dryRun($studentData, [
            'mode' => $importData['mode'],
            'ignoreErrors' => $importData['ignoreErrors']
        ]);

        // Update progress
        $progress = [
            'stage' => 'DryRun',
            'status' => 'Complete',
            'errors' => $dryRunResult['errors'] ?? [],
            'warnings' => $dryRunResult['warnings'] ?? []
        ];
        $transferImportGateway->update($studentTransferImportID, ['importProgress' => json_encode($progress)]);

        // Refresh the page to show results
        echo "<script>window.location.reload();</script>";
    }

} elseif ($step == 4) {
    // STEP 4: LIVE RUN
    if (empty($studentTransferImportID)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Get import data
    $importData = $transferImportGateway->getByID($studentTransferImportID);
    if (empty($importData)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Create the form
    $form = Form::create('importLiveRun', '');
    $form->setTitle(__('Live Run'));

    // Add description
    $row = $form->addRow();
        $row->addContent(__('The system is now importing the student data. This process may take a few minutes.'))
            ->wrap('<div class="message emphasis">', '</div>');

    // Progress information
    $progress = !empty($importData['importProgress']) ? json_decode($importData['importProgress'], true) : [];

    // Progress bar
    $progressBar = '<div class="progress-bar stripes" style="width: 100%;">';
    $progressBar .= '<div class="progress-bar-fill" style="width: '.($progress['status'] == 'Complete' ? '100%' : '0%').'">';
    $progressBar .= '<span class="progress-bar-text">'.__($progress['status']).'</span>';
    $progressBar .= '</div></div>';

    $row = $form->addRow();
        $row->addContent($progressBar)
            ->wrap('<div class="w-full">', '</div>');

    // Display errors and warnings
    if (!empty($progress['errors'])) {
        $row = $form->addRow();
            $row->addHeading(__('Errors'))->addClass('text-red-600');

        foreach ($progress['errors'] as $error) {
            $row = $form->addRow();
                $row->addAlert($error, 'error');
        }
    }

    if (!empty($progress['warnings'])) {
        $row = $form->addRow();
            $row->addHeading(__('Warnings'))->addClass('text-yellow-600');

        foreach ($progress['warnings'] as $warning) {
            $row = $form->addRow();
                $row->addAlert($warning, 'warning');
        }
    }

    // Display buttons based on status
    if ($progress['status'] == 'Complete') {
        $row = $form->addRow();
            $row->addContent('<div class="flex justify-end gap-2">');
        
        if (!empty($progress['imported'])) {
            $row->addContent('<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Students/applicationForm_manage.php" class="button">View Applications</a>');
        }
        
        $row->addContent('<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Student Transfer/transfer_manage.php" class="button">Done</a>');
        $row->addContent('</div>');
    }

    echo $form->getOutput();

    // Perform live run if not already done
    if ($progress['stage'] != 'LiveRun') {
        $studentData = json_decode($importData['studentData'], true);
        $liveRunResult = $importProcessor->liveRun($studentData, [
            'mode' => $importData['mode'],
            'ignoreErrors' => $importData['ignoreErrors']
        ]);

        // Update progress
        $progress = [
            'stage' => 'LiveRun',
            'status' => 'Complete',
            'errors' => $liveRunResult['errors'] ?? [],
            'warnings' => $liveRunResult['warnings'] ?? [],
            'imported' => $liveRunResult['imported'] ?? 0
        ];
        
        // Update import record
        $transferImportGateway->update($studentTransferImportID, [
            'importProgress' => json_encode($progress),
            'status' => 'Complete'
        ]);

        // Send notifications if enabled
        if ($importData['notifyUsers'] == 'Y') {
            $notificationService->sendTransferNotification(
                $studentTransferImportID,
                'import',
                ['count' => $progress['imported']]
            );
        }

        // Refresh the page to show results
        echo "<script>window.location.reload();</script>";
    } else {
        // Auto-refresh if not complete
        if ($progress['status'] != 'Complete') {
            echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
        }
    }
}
