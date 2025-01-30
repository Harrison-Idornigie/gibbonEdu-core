<?php
// Import necessary Gibbon framework classes
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Domain\School\SchoolYearTermGateway;

// Include module-specific functions
require_once __DIR__ . '/moduleFunctions.php';

// Check if the user has permission to access this page
if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Display error if access is denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Main processing block - only executed if user has access

    // Retrieve required parameters from URL
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';     // Student ID
    $gibbonSchoolYearTermID = $_GET['gibbonSchoolYearTermID'] ?? '';   // Current term ID
    $template = $_GET['template'] ?? '';                  // Report card template name

    // Validate that all required parameters are present
    if (empty($gibbonPersonID) || empty($gibbonSchoolYearTermID) || empty($template)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Database query block to retrieve student information
    try {
        // Prepare query parameters
        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')];
        
        // SQL query to get student details including their form group
        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonFormGroup.name as formGroup
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID 
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'";
        
        $result = $connection2->prepare($sql);
        $result->execute($data);
        $student = $result->fetch();

        // Check if student exists
        if (empty($student)) {
            $page->addError(__('The specified student cannot be found.'));
            return;
        }
    } catch (Exception $e) {
        $page->addError(__('Database connection failed: ').$e->getMessage());
        return;
    }

    // Set up page navigation breadcrumbs
    $page->breadcrumbs
        ->add(__('Enter Assessments'), 'report_cards_enter.php')
        ->add(Format::name('', $student['preferredName'], $student['surname'], 'Student'));

    // Verify and load the report card template
    $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
    if (!file_exists($templateFile)) {
        $page->addError(__('The specified template cannot be found.'));
        return;
    }

    // Load the template file which should define the $sections array
    require $templateFile;
    
    // Validate template structure
    if (!isset($sections) || !is_array($sections)) {
        $page->addError(__('Template is invalid: sections not defined.'));
        return;
    }
    
    // Initialize the assessment form
    $form = Form::create('assessment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/report_cards_enter_studentProcess.php', 'post');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    // Log form creation for debugging
    error_log("Extra Reports: Creating form with gibbonPersonID: $gibbonPersonID");

    // Add hidden form fields for processing
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearTermID', $gibbonSchoolYearTermID);
    $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);
    $form->addHiddenValue('template', $template);

    // Get term name for display
    $termGateway = $container->get(SchoolYearTermGateway::class);
    $termData = $termGateway->getByID($gibbonSchoolYearTermID);
    $termName = $termData['name'] ?? $gibbonSchoolYearTermID;

    // Display student information header
    $row = $form->addRow();
    $row->addHeading(Format::name('', $student['preferredName'], $student['surname'], 'Student').' ('.$student['formGroup'].')');

    // Display term information
    $row = $form->addRow();
    $row->addHeading(__('Term').': '.$termName);

    // Add tabs container
    $tabs = $form->addRow()->addTabs();

    // Regular Assessment Sections Tab
    $regularAssessmentTab = $tabs->addTab(__('Regular Assessment'));
    
    // Process regular assessment sections
    foreach ($sections as $sectionKey => $section) {
        error_log("Extra Reports: Generating form section: $sectionKey");
        
        // Add section heading
        $regularAssessmentTab->addHeading($section['title'])->addClass('mt-4');
        
        // Create a table for the grid layout
        $table = $regularAssessmentTab->addTable()->addClass('w-full');
        $table->addMetaData('class', 'blank');
        
        // Create rows for items, 3 items per row
        $items = array_chunk($section['items'], 3);
        foreach ($items as $itemRow) {
            $tr = $table->addRow()->addClass('flex flex-wrap md:flex-nowrap gap-4');
            
            foreach ($itemRow as $item) {
                // Generate unique hash for the item
                $itemHash = md5($item);
                
                // Create a cell for the assessment item
                $td = $tr->addColumn()->addClass('flex-1 min-w-[300px] bg-white rounded shadow-sm p-4');
                
                // Add item label
                $td->addContent($item)->wrap('<div class="font-bold text-sm mb-2">', '</div>');
                
                // Create score field
                $scoreField = "assessment_{$sectionKey}_{$itemHash}_score";
                $row = $td->addRow();
                $row->addLabel($scoreField, __('Score'))->addClass('text-sm');
                $row->addSelect($scoreField)
                    ->fromArray([
                        '3' => 'Meeting the MINIMUM Standards',
                        '2' => 'Meets some of the MINIMUM Standards',
                        '1' => 'Does not meet the MINIMUM Standards'
                    ])
                    ->required()
                    ->placeholder('Please select...')
                    ->addClass('w-full mb-2');
                
                // Create comment field
                $commentField = "assessment_{$sectionKey}_{$itemHash}_comment";
                $row = $td->addRow();
                $row->addLabel($commentField, __('Comment'))->addClass('text-sm');
                $row->addTextArea($commentField)->setRows(3)->addClass('w-full');
            }
        }
    }

    // Development Chart Tab
    $developmentTab = $tabs->addTab(__('Development Chart'));
    
    if (isset($developmentSections)) {
        // Create a table for the development sections
        $devTable = $developmentTab->addTable()->addClass('w-full');
        $devTable->addMetaData('class', 'blank');
        
        // Create rows for sections, 2 sections per row
        $sections = array_chunk($developmentSections, 2, true);
        foreach ($sections as $sectionRow) {
            $tr = $devTable->addRow()->addClass('flex flex-wrap md:flex-nowrap gap-6');
            
            foreach ($sectionRow as $sectionKey => $section) {
                // Create a cell for the section
                $td = $tr->addColumn()->addClass('flex-1 min-w-[300px] bg-white rounded shadow-lg p-6');
                
                // Add section heading
                $td->addContent($section['title'])->wrap('<div class="text-lg font-bold mb-4">', '</div>');
                
                // Add subsections
                foreach ($section['subsections'] as $subsectionKey => $subsectionName) {
                    $fieldName = "assessment_development_{$sectionKey}_{$subsectionKey}";
                    
                    $row = $td->addRow()->addClass('mb-4');
                    $row->addLabel($fieldName, $subsectionName)
                        ->description('Select the appropriate level')
                        ->addClass('text-sm font-medium');
                    
                    $row->addSelect($fieldName)
                        ->fromArray([
                            '3' => 'Meeting the MINIMUM Standards',
                            '2' => 'Meets some of the MINIMUM Standards',
                            '1' => 'Does not meet the MINIMUM Standards'
                        ])
                        ->required()
                        ->placeholder('Please select...')
                        ->addClass('w-full');
                }
            }
        }
    }

    // Add floating save button
    $row = $form->addRow();
    $col = $row->addColumn()->addClass('fixed bottom-0 right-0 p-4 bg-white shadow-lg rounded-tl-lg z-50');
    $col->addSubmit('Save Assessment');

    // Output debugging information
    echo "<!-- Form field names for debugging: -->\n";
    echo "<!-- Hidden fields: address, gibbonSchoolYearTermID={$gibbonSchoolYearTermID}, gibbonPersonID={$gibbonPersonID}, template={$template} -->\n";
    foreach ($sections as $sectionKey => $section) {
        foreach ($section['items'] as $item) {
            echo "<!-- Score field: assessment_".str_replace('-', '_', $sectionKey)."_".md5($item)."_score, Comment field: assessment_".str_replace('-', '_', $sectionKey)."_".md5($item)."_comment -->\n";
        }
    }

    // Render the form
    echo $form->getOutput();
}
?>
