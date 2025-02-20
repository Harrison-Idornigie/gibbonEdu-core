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
            if ($transfer['status'] != 'Pending Import') {
                $page->addError(__('This transfer cannot be imported in its current state.'));
            } else {
                // Load student data
                $studentData = $transfer['studentData'];
                
                // Check for duplicates
                $duplicates = $importProcessor->checkDuplicates($studentData);
                
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

                // DATA PREVIEW
                $row = $form->addRow()->addHeading(__('Student Data Preview'));

                // Personal Information
                $row = $form->addRow();
                    $row->addHeading(__('Personal Information'))
                        ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                        ->addClass('toggleDetails');

                $table = $form->addRow()->addTable()->addClass('smallIntBorder fullWidth');
                
                $row = $table->addRow();
                    $row->addLabel('name', __('Name'));
                    $row->addContent(Format::name('', $studentData['preferredName'], $studentData['surname'], 'Student'));

                $row = $table->addRow();
                    $row->addLabel('dob', __('Date of Birth'));
                    $row->addContent(Format::date($studentData['dob']));

                $row = $table->addRow();
                    $row->addLabel('gender', __('Gender'));
                    $row->addContent($studentData['gender']);

                $row = $table->addRow();
                    $row->addLabel('email', __('Email'));
                    $row->addContent($studentData['email']);

                // Academic Information
                $row = $form->addRow();
                    $row->addHeading(__('Academic Information'))
                        ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                        ->addClass('toggleDetails');

                $table = $form->addRow()->addTable()->addClass('smallIntBorder fullWidth');
                
                foreach ($studentData['academic']['enrolments'] as $enrolment) {
                    $row = $table->addRow();
                        $row->addLabel('school', __('School'));
                        $row->addContent($enrolment['schoolName']);
                    
                    $row = $table->addRow();
                        $row->addLabel('yearGroup', __('Year Group'));
                        $row->addContent($enrolment['yearGroup']);
                    
                    $row = $table->addRow();
                        $row->addLabel('dates', __('Dates'));
                        $row->addContent(Format::date($enrolment['dateStart']).' - '.Format::date($enrolment['dateEnd']));
                }

                // Medical Information
                if (!empty($studentData['medical'])) {
                    $row = $form->addRow();
                        $row->addHeading(__('Medical Information'))
                            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                            ->addClass('toggleDetails');

                    $table = $form->addRow()->addTable()->addClass('smallIntBorder fullWidth');
                    
                    foreach ($studentData['medical']['conditions'] as $condition) {
                        $row = $table->addRow();
                            $row->addLabel('condition', __('Condition'));
                            $row->addContent($condition['name']);
                        
                        $row = $table->addRow();
                            $row->addLabel('details', __('Details'));
                            $row->addContent($condition['details']);
                    }
                }

                // Family Information
                if (!empty($studentData['family'])) {
                    $row = $form->addRow();
                        $row->addHeading(__('Family Information'))
                            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                            ->addClass('toggleDetails');

                    $table = $form->addRow()->addTable()->addClass('smallIntBorder fullWidth');
                    
                    foreach ($studentData['family'] as $family) {
                        $row = $table->addRow();
                            $row->addLabel('relation', __('Relation'));
                            $row->addContent($family['relation']);
                        
                        $row = $table->addRow();
                            $row->addLabel('name', __('Name'));
                            $row->addContent(Format::name('', $family['preferredName'], $family['surname'], 'Parent'));
                        
                        $row = $table->addRow();
                            $row->addLabel('contact', __('Contact'));
                            $row->addContent($family['email'].'<br/>'.$family['phone']);
                    }
                }

                // Attachments
                $attachmentDir = $session->get('absolutePath').'/uploads/studentTransfers/'.$studentTransferLogID;
                if (is_dir($attachmentDir)) {
                    $row = $form->addRow();
                        $row->addHeading(__('Attachments'))
                            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                            ->addClass('toggleDetails');

                    $table = $form->addRow()->addTable()->addClass('smallIntBorder fullWidth');
                    
                    foreach (glob($attachmentDir.'/*.*') as $file) {
                        $row = $table->addRow();
                            $row->addLabel('file', __('File'));
                            $row->addContent(basename($file));
                    }
                }

                // Confirmation
                $row = $form->addRow();
                    $row->addContent(__('Are you sure you want to import this student? This will create a new application form.'))
                        ->wrap('<p class="mt-3">', '</p>')
                        ->addClass('text-warning font-bold');

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit(__('Import Student'));

                echo $form->getOutput();
            }
        }
    }
}
