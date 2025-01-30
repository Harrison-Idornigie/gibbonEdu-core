<?php
include '../../gibbon.php';

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\ExtraReports\ReportCardGenerator;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $students = $_POST['students'] ?? [];
    $reportingPeriod = $_POST['reportingPeriod'] ?? '';
    $template = $_POST['template'] ?? '';

    if (empty($students) || empty($reportingPeriod) || empty($template)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        global $pdo;
        
        if (!isset($pdo)) {
            throw new Exception('Database connection not available.');
        }

        // Process each selected student
        foreach ($students as $gibbonPersonID) {
            // Get student data
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
                continue;
            }

            // Load and process template
            $templateFile = __DIR__ . '/templates/reportCards/' . $template . '.php';
            if (!file_exists($templateFile)) {
                throw new Exception('Template file not found: ' . $template);
            }

            // Include template file which will use ReportCardGenerator
            include $templateFile;
        }

        $URL .= '&return=success0';
    } catch (Exception $e) {
        $URL .= '&return=error2';
        error_log('Extra Reports: ' . $e->getMessage());
    }

    header("Location: {$URL}");
    exit;
}
