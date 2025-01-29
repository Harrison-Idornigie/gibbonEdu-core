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
    $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')];
    $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonFormGroup.name as formGroup
            FROM gibbonPerson 
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
            WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID 
            AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
            AND gibbonPerson.status='Full'";
    
    $student = $pdo->selectOne($sql, $data);

    if (empty($student)) {
        $page->addError(__('The specified record cannot be found.'));
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
    $studentID = $gibbonPersonID; // Template expects $studentID
    require $templateFile;
    
    if (!isset($sections) || !is_array($sections)) {
        $page->addError(__('Template is invalid: sections not defined.'));
        return;
    }
    
    // Assessment form
    $form = Form::create('assessment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/report_cards_enterProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('reportingPeriod', $reportingPeriod);
    $form->addHiddenValue('studentID', $gibbonPersonID);
    $form->addHiddenValue('template', $template);

    // Student info
    $row = $form->addRow();
        $row->addHeading(Format::name('', $student['preferredName'], $student['surname'], 'Student').' ('.$student['formGroup'].')');

    $row = $form->addRow();
        $row->addHeading(__('Reporting Period').': '.$reportingPeriod);

    // Add assessment sections
    foreach ($sections as $sectionKey => $section) {
        $form->addRow()->addHeading($section['title']);

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

            $row = $form->addRow();
            $row->addLabel($sectionKey.'_'.$item, $item);
            $col = $row->addColumn()->addClass('flex-1');

            $col->addSelect($sectionKey.'_'.$item)
                ->fromArray(getAssessmentScores())
                ->placeholder()
                ->selected($result['score'] ?? '');

            $col->addTextArea($sectionKey.'_'.$item.'_comment')
                ->setRows(2)
                ->setValue($result['comment'] ?? '')
                ->placeholder(__('Add a comment...'));
        }
    }

    $row = $form->addRow();
        $row->addSubmit();

    echo $form->getOutput();
}
?>
