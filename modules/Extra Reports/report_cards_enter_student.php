<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $reportingPeriod = $_GET['reportingPeriod'] ?? '';
    $template = $_GET['template'] ?? '';

    if (empty($gibbonPersonID) || empty($reportingPeriod) || empty($template)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get student info
    try {
        global $pdo;
        
        if (!isset($pdo)) {
            throw new Exception('Database connection not available.');
        }

        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')];
        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonFormGroup.name as formGroup
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID 
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'";
        
        $result = $pdo->select($sql, $data);
        $student = $result->fetch();

        if (empty($student)) {
            $page->addError(__('The specified student cannot be found.'));
            return;
        }
    } catch (Exception $e) {
        $page->addError(__('Database connection failed: ').$e->getMessage());
        return;
    }

    $page->breadcrumbs
        ->add(__('Enter Assessments'), 'report_cards_enter.php')
        ->add(Format::name('', $student['preferredName'], $student['surname'], 'Student'));

    // Get template data
    $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
    if (!file_exists($templateFile)) {
        $page->addError(__('The specified template cannot be found.'));
        return;
    }

    // Include template in a scope that preserves variables
    require $templateFile;
    
    if (!isset($sections) || !is_array($sections)) {
        $page->addError(__('Template is invalid: sections not defined.'));
        return;
    }
    
    // Assessment form
    $form = Form::create('assessment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/report_cards_enter_studentProcess.php', 'post');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    error_log("Extra Reports: Creating form with gibbonPersonID: $gibbonPersonID");

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('reportingPeriod', $reportingPeriod);
    $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);
    $form->addHiddenValue('template', $template);

    // Student info
    $row = $form->addRow();
        $row->addHeading(Format::name('', $student['preferredName'], $student['surname'], 'Student').' ('.$student['formGroup'].')');

    $row = $form->addRow();
        $row->addHeading(__('Reporting Period').': '.$reportingPeriod);

    // Add assessment sections
    foreach ($sections as $sectionKey => $section) {
        $form->addRow()->addHeading($section['title'])->addClass('mt-4');

        // Create a container for three columns
        $row = $form->addRow();
        $columnContainer = $row->addColumn()->addClass('grid grid-cols-3 gap-6 w-full');

        $itemCount = 0;
        foreach ($section['items'] as $item) {
            // Get existing assessment
            $data = [
                'studentID' => $gibbonPersonID,
                'reportingPeriod' => $reportingPeriod,
                'section' => $sectionKey,
                'item' => $item
            ];
            
            $sql = "SELECT score, comment 
                    FROM extraReportAssessment 
                    WHERE studentID=:studentID 
                    AND reportingPeriod=:reportingPeriod 
                    AND section=:section 
                    AND item=:item";
                    
            $result = $pdo->selectOne($sql, $data);

            // Add each item to the column container instead of creating new rows
            $itemDiv = $columnContainer->addColumn()->addClass('flex flex-col mb-6 bg-white rounded shadow-sm p-3');
            $itemDiv->addLabel($sectionKey.'_'.$item, $item)->addClass('font-bold text-sm mb-2');
            
            $controlsDiv = $itemDiv->addColumn()->addClass('flex-1 space-y-2');

            // Create the score select field with a specific name format
            $scoreField = $controlsDiv->addSelect($sectionKey.'_'.$item)
                ->fromArray(getAssessmentScores())
                ->placeholder('Choose...')
                ->required()
                ->setClass('w-full mb-2');

            if (isset($result['score'])) {
                $scoreField->selected($result['score']);
            }

            // Create the comment field with a specific name format
            $commentField = $controlsDiv->addTextArea($sectionKey.'_'.$item.'_comment')
                ->setRows(3)
                ->setValue($result['comment'] ?? '')
                ->setClass('w-full')
                ->placeholder(__('Add a comment...'));
        }
    }

    $row = $form->addRow();
        $row->addSubmit('Save Assessment');

    // Debug output
    echo "<!-- Form field names for debugging: -->\n";
    echo "<!-- Hidden fields: address, reportingPeriod={$reportingPeriod}, gibbonPersonID={$gibbonPersonID}, template={$template} -->\n";
    foreach ($sections as $sectionKey => $section) {
        foreach ($section['items'] as $item) {
            echo "<!-- Score field: {$sectionKey}_{$item}, Comment field: {$sectionKey}_{$item}_comment -->\n";
        }
    }

    echo $form->getOutput();
}
?>
