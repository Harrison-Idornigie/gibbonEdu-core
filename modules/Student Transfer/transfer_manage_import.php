<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\ImportProcessor;

// Module includes
include '../../gibbon.php';

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage_import.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Student Transfers'), 'transfer_manage.php')
        ->add(__('Import Student Transfer'));

    // Check for existing transfer ID
    $studentTransferLogID = $_GET['studentTransferLogID'] ?? '';
    $mode = empty($studentTransferLogID) ? 'upload' : 'import';

    if ($mode == 'upload') {
        // Show file upload form
        $form = Form::create('importTransfer', $session->get('absoluteURL').'/modules/Student Transfer/transfer_manage_importProcess.php');
        
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('mode', 'upload');

        $row = $form->addRow()->addHeading(__('Upload Transfer Package'))
            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
            ->addClass('toggleDetails')
            ->addClass('font-bold');

        $row = $form->addRow();
            $row->addLabel('file', __('Transfer Package'))
                ->description(__('Upload a student transfer package (.zip)'));
            $row->addFileUpload('file')
                ->required()
                ->accepts('.zip');

        $row = $form->addRow();
            $row->addLabel('password', __('Package Password'))
                ->description(__('Enter the password provided by the source school.'));
            $row->addPassword('password')
                ->required();

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();

    } else {
        // Validate the transfer ID
        $transferGateway = $container->get(TransferGateway::class);
        $transfer = $transferGateway->getTransferByID($studentTransferLogID);
        $importProcessor = $container->get(ImportProcessor::class);

        if (empty($transfer)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            // Check if transfer is in correct state for import
            if ($transfer['status'] != 'Exported') {
                $page->addError(__('This transfer cannot be imported in its current state.'));
            } else {
                // Check for duplicates
                $duplicates = $importProcessor->checkDuplicates($transfer['studentData']);
                
                // Show preview and confirmation form
                $form = Form::create('confirmImport', $session->get('absoluteURL').'/modules/Student Transfer/transfer_manage_importProcess.php');
                
                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('mode', 'import');
                $form->addHiddenValue('studentTransferLogID', $studentTransferLogID);

                // DUPLICATE WARNINGS
                if (!empty($duplicates)) {
                    $row = $form->addRow()->addHeading(__('Potential Duplicates'))
                        ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                        ->addClass('toggleDetails warning')
                        ->addClass('font-bold text-warning');

                    foreach ($duplicates as $type => $matches) {
                        $row = $form->addRow()->addClass('duplicateWarning bg-warning-100');
                            $row->addContent(sprintf(__('Found %d potential matches by %s:'), count($matches), $type));
                            
                        foreach ($matches as $match) {
                            $row = $form->addRow()->addClass('duplicateWarning bg-warning-100');
                                $row->addContent(Format::name('', $match['preferredName'], $match['surname'], 'Student'))
                                    ->append(' - '.$match['dob']);
                        }
                    }
                }

                // STUDENT DETAILS
                $row = $form->addRow()->addHeading(__('Student Details'))
                    ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                    ->addClass('toggleDetails')
                    ->addClass('font-bold');

                // Display student data preview
                $studentData = json_decode($transfer['exportData'], true);
                
                $row = $form->addRow();
                    $row->addLabel('name', __('Student Name'));
                    $row->addTextField('name')
                        ->setValue(Format::name('', $studentData['personal']['preferredName'], $studentData['personal']['surname'], 'Student'))
                        ->readonly();

                $row = $form->addRow();
                    $row->addLabel('dob', __('Date of Birth'));
                    $row->addDate('dob')
                        ->setValue($studentData['personal']['dob'])
                        ->readonly();

                // Add collapsible sections for different data types
                $types = [
                    'Academic Records' => 'academic',
                    'Medical Information' => 'medical',
                    'Family Details' => 'family',
                    'Custom Fields' => 'custom'
                ];

                foreach ($types as $title => $key) {
                    $row = $form->addRow();
                    $row->addHeading(__($title))
                        ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                        ->addClass('toggleDetails')
                        ->addClass('font-bold');

                    $row = $form->addRow()->addClass(strtolower(str_replace(' ', '', $title)));
                        $column = $row->addColumn()->addClass('flex-col');
                        
                        // Add preview content based on data type
                        switch ($key) {
                            case 'academic':
                                foreach ($studentData[$key]['enrolments'] as $enrolment) {
                                    $column->addContent($enrolment['schoolYear'].' - '.$enrolment['yearGroup'])
                                        ->addClass('py-2');
                                }
                                break;
                                
                            case 'medical':
                                foreach ($studentData[$key]['conditions'] as $condition) {
                                    $column->addContent($condition['name'].': '.$condition['details'])
                                        ->addClass('py-2');
                                }
                                break;
                                
                            case 'family':
                                foreach ($studentData[$key]['adults'] as $adult) {
                                    $column->addContent(Format::name($adult['title'], $adult['preferredName'], $adult['surname'], 'Parent').' ('.$adult['relationship'].')')
                                        ->addClass('py-2');
                                }
                                break;
                                
                            case 'custom':
                                foreach ($studentData[$key] as $field => $data) {
                                    $column->addContent($field.': '.($data['type'] == 'multiSelect' ? implode(', ', $data['value']) : $data['value']))
                                        ->addClass('py-2');
                                }
                                break;
                        }
                }

                // IMPORT OPTIONS
                $row = $form->addRow()->addHeading(__('Import Options'))
                    ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                    ->addClass('toggleDetails')
                    ->addClass('font-bold');

                $row = $form->addRow();
                    $row->addLabel('createApplicationForm', __('Create Application Form'))
                        ->description(__('Create an application form for review before final import.'));
                    $row->addYesNo('createApplicationForm')
                        ->selected('Y')
                        ->required();

                $row = $form->addRow();
                    $row->addLabel('importAttachments', __('Import Attachments'))
                        ->description(__('Import all attached files and documents.'));
                    $row->addYesNo('importAttachments')
                        ->selected('Y')
                        ->required();

                if (!empty($duplicates)) {
                    $row = $form->addRow();
                        $row->addLabel('ignoreDuplicates', __('Ignore Duplicates'))
                            ->description(__('Proceed with import despite potential duplicates.'));
                        $row->addYesNo('ignoreDuplicates')
                            ->selected('N')
                            ->required();
                }

                // CONFIRMATION
                $row = $form->addRow()->addHeading(__('Confirmation'))
                    ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                    ->addClass('toggleDetails')
                    ->addClass('font-bold');

                $row = $form->addRow();
                    $row->addLabel('confirm', __('Confirm Import'))
                        ->description(__('Are you sure you want to import this student data?'));
                    $row->addCheckbox('confirm')
                        ->description(__('Yes'))
                        ->required();

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit();

                echo $form->getOutput();
            }
        }
    }
}
