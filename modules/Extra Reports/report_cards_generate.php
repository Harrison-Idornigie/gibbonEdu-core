<?php
include '../../gibbon.php';

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\ExtraReports\ReportCardGenerator;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/classes/ReportCardGenerator.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $students = $_POST['students'] ?? [];
    $reportingPeriod = $_POST['reportingPeriod'] ?? '';
    $template = $_POST['template'] ?? '';

    if (empty($students) || empty($reportingPeriod) || empty($template)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get template data
    $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
    if (!file_exists($templateFile)) {
        $page->addError(__('The specified template cannot be found.'));
        return;
    }

    // Include template to get sections
    require $templateFile;

    if (!isset($sections) || !is_array($sections)) {
        $page->addError(__('Template is invalid: sections not defined.'));
        return;
    }

    // Create report card generator
    $generator = new ReportCardGenerator($pdo);

    // Process each student
    $successCount = 0;
    $errorCount = 0;

    foreach ($students as $studentID) {
        // Get student data
        $data = ['gibbonPersonID' => $studentID, 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')];
        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonFormGroup.name as formGroup
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID 
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'";
        
        $student = $pdo->selectOne($sql, $data);

        if (empty($student)) {
            $errorCount++;
            continue;
        }

        // Get assessment data
        $assessmentData = [];
        foreach ($sections as $sectionKey => $section) {
            $assessmentData[$sectionKey] = [
                'title' => $section['title'],
                'items' => []
            ];

            foreach ($section['items'] as $item) {
                $data = [
                    'studentID' => $studentID,
                    'reportingPeriod' => $reportingPeriod,
                    'section' => $sectionKey,
                    'item' => $item
                ];
                
                $sql = "SELECT score 
                        FROM extraReportAssessment 
                        WHERE studentID=:studentID 
                        AND reportingPeriod=:reportingPeriod 
                        AND section=:section 
                        AND item=:item";
                        
                $result = $pdo->selectOne($sql, $data);
                $assessmentData[$sectionKey]['items'][$item] = $result['score'] ?? null;
            }
        }

        try {
            // Generate PDF
            $generator->generateReportCard($student, $reportingPeriod, $template, $assessmentData);
            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
        }
    }

    // Show results
    if ($successCount > 0) {
        $page->addSuccess(sprintf(__('Successfully generated %1$s report cards.'), $successCount));
    }
    if ($errorCount > 0) {
        $page->addError(sprintf(__('Failed to generate %1$s report cards.'), $errorCount));
    }

    // Return to manage page
    header("Location: " . $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . '/report_cards_manage.php');
    exit();
}
?>