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

    // Add page scripts - this goes at the top of the file after session check
    $page->scripts->add('index', '/modules/Extra Reports/js/module.js');
    $page->stylesheets->add('module', '/modules/Extra Reports/css/module.css');
    
    // Add core assets
    $page->scripts->add('core', '/resources/assets/js/core.js');
    $page->stylesheets->add('core', '/resources/assets/css/core.min.css');

    // Add Alpine.js initialization for toggleDetails
    $page->scripts->add('toggleInit', '/modules/Extra Reports/js/toggleDetails.js', ['type' => 'module']);

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
    $form = Form::create('reportCardEntry', $session->get('absoluteURL').'/modules/'.$session->get('module').'/report_cards_enter_studentProcess.php', 'post');
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

    // Regular Assessment Sections
    $row = $form->addRow();
    $row->addHeading(__('Regular Assessment'))->addClass('mt-4');
    
    // Process regular assessment sections
    foreach ($sections as $sectionKey => $section) {
        error_log("Extra Reports: Generating form section: $sectionKey");
        
        // Add section heading with toggle
        $row = $form->addRow();
        $row->addHeading($section['title'])
            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
            ->addClass('toggleDetails')
            ->addClass('font-bold');
        
        // Process items
        if (isset($section['items'])) {
            foreach ($section['items'] as $item) {
                // Generate unique hash for the item
                $itemHash = md5($item);
                
                // Create score field
                $scoreField = "assessment_{$sectionKey}_{$itemHash}_score";
                $row = $form->addRow()->addClass('toggleDetailsContent');
                $row->addLabel($scoreField, $item);
                $row->addSelect($scoreField)
                    ->fromArray([
                        '3' => 'Meeting the MINIMUM Standards',
                        '2' => 'Meets some of the MINIMUM Standards',
                        '1' => 'Does not meet the MINIMUM Standards'
                    ])
                    ->required()
                    ->placeholder('Please select...');
                
                // Create comment field
                $commentField = "assessment_{$sectionKey}_{$itemHash}_comment";
                $row = $form->addRow()->addClass('toggleDetailsContent');
                $row->addLabel($commentField, __('Comment'));
                $row->addTextArea($commentField)->setRows(3);
            }
        }
    }

    // Development Chart Section
    if (isset($developmentSections)) {
        $row = $form->addRow();
        $row->addHeading(__('Development Chart'))->addClass('mt-4');
        
        foreach ($developmentSections as $sectionKey => $section) {
            // Add section heading with toggle
            $row = $form->addRow();
            $row->addHeading($section['title'])
                ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                ->addClass('toggleDetails')
                ->addClass('font-bold');
            
            // Process subsections
            if (isset($section['subsections'])) {
                foreach ($section['subsections'] as $subsectionKey => $subsectionName) {
                    // Create unique hash for subsection
                    $hash = md5($subsectionName);
                    
                    // Create score field with development_ prefix
                    $scoreField = "assessment_development_{$sectionKey}_{$hash}_score";
                    $row = $form->addRow()->addClass('toggleDetailsContent');
                    $row->addLabel($scoreField, $subsectionName)
                        ->description(__('Select the appropriate level'));
                    $row->addSelect($scoreField)
                        ->fromArray([
                            '3' => 'Meeting the MINIMUM Standards',
                            '2' => 'Meets some of the MINIMUM Standards',
                            '1' => 'Does not meet the MINIMUM Standards'
                        ])
                        ->required()
                        ->placeholder('Please select...');
                        
                    // Create comment field with development_ prefix
                    $commentField = "assessment_development_{$sectionKey}_{$hash}_comment";
                    $row = $form->addRow()->addClass('toggleDetailsContent');
                    $row->addLabel($commentField, __('Comment'));
                    $row->addTextArea($commentField)->setRows(3);
                }
            }
        }
    }

    // Add save button
    $row = $form->addRow();
    $row->addSubmit('Save Assessment');

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
