<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Services\Format;
use CourseSelection\Domain\GradesGateway;
use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/approval_byOffering.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
   $page->breadcrumbs
    	->add(__m('Student Grades'));

    $gibbonPersonIDStudent = isset($_REQUEST['gibbonPersonIDStudent'])? $_REQUEST['gibbonPersonIDStudent'] : 0;

    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');
    $gradesGateway = $container->get('CourseSelection\Domain\GradesGateway');

    $studentResults = $selectionsGateway->selectStudentDetails($gibbonPersonIDStudent);
    $studentEnrolmentResults = $gradesGateway->selectStudentEnrolmentByStudent($gibbonPersonIDStudent);

    if ($studentResults->rowCount() == 0 || $studentEnrolmentResults->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
    } else {
        $student = $studentResults->fetch();

        echo '<br/>';
        echo '<div>';
        echo __('Viewing report grades for ');
        echo '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$gibbonPersonIDStudent.'" target="_blank">';
        echo '<strong>'.Format::name('', $student['preferredName'], $student['surname'], 'Student', true).'</strong>';
        echo '</a>';
        echo '</div>';

        while ($enrolment = $studentEnrolmentResults->fetch())
        {
            $gradesResults = $gradesGateway->selectStudentReportGradesBySchoolYear($enrolment['schoolYearID'], $gibbonPersonIDStudent);

            if (!$gradesResults || $gradesResults->rowCount() == 0) continue;

            echo '<h3>';
            echo $enrolment['schoolYearName'];
            echo '</h3>';

            echo '<table class="reportGradesTable colorOddEven" cellspacing="0">';
            echo '<tr class="break">';
                echo '<td>'.__('Course').'</td>';
                echo '<td>'.__('Name').'</td>';

                if (intval($enrolment['schoolYearID']) >= 12) {
                    echo '<td class="w-24">'.__('Term 1 Mid').'</td>';
                    echo '<td class="w-24">'.__('Term 1 End').'</td>';
                    echo '<td class="w-24">'.__('Term 2 Interim').'</td>';
                    echo '<td class="w-24">'.__('Term 2 End').'</td>';
                } else {
                    echo '<td class="w-24">'.__('Sem1-Mid').'</td>';
                    echo '<td class="w-24">'.__('Sem1-End').'</td>';
                    echo '<td class="w-24">'.__('Sem2-Mid').'</td>';
                    echo '<td class="w-24">'.__('Sem2-End').'</td>';
                }
            
                echo '<td style="border-left: 2px solid #bbb;">'.__('Exam').'</td>';
                echo '<td>'.__('Final').'</td>';
            echo '</tr>';

            $schoolYearGrades = $gradesGateway->processStudentGrades($enrolment, $gradesResults->fetchAll());



            foreach ($schoolYearGrades as $courseGrades) {

                $sem1Mid = (!empty($courseGrades['Sem1-Mid']))? $courseGrades['Sem1-Mid'] : '';
                $sem1MidClass = (!empty($sem1Mid) && intval($sem1Mid) < 50)? 'gradesRow' : 'gradesRow';

                $sem1End = (!empty($courseGrades['Sem1-End']))? $courseGrades['Sem1-End'] : '';
                $sem1EndClass = (!empty($sem1End) && intval($sem1End) < 50)? 'gradesRow' : 'gradesRow';

                $sem2Mid = (!empty($courseGrades['Sem2-Mid']))? $courseGrades['Sem2-Mid'] : '';
                $sem2MidClass = (!empty($sem2Mid) && intval($sem2Mid) < 50)? 'gradesRow' : 'gradesRow';

                $sem2End = (!empty($courseGrades['Sem2-End']))? $courseGrades['Sem2-End'] : '';
                $sem2EndClass = (!empty($sem2End) && intval($sem2End) < 50)? 'gradesRow' : 'gradesRow';

                $credits = (!empty($courseGrades['credits']))? $courseGrades['credits'] : '';

                $exam = (!empty($courseGrades['Exam']))? $courseGrades['Exam'] : '';
                $examClass = (!empty($exam) && intval($exam) < 50)? 'gradesRow' : 'gradesRow';

                $final = (!empty($courseGrades['Final']))? $courseGrades['Final'] : '';
                $finalClass = (!empty($final) && intval($final) < 50)? 'gradesRow' : 'gradesRow';

                if (!empty($courseGrades['gibbonCourseClassID']) && $enrolment['schoolYearID'] == $session->get('gibbonSchoolYearID')) {
                    $courseGrades['courseNameShort'] = sprintf('<a href="%2$s">%1$s</a>', $courseGrades['courseNameShort'], $session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$gibbonPersonIDStudent.'&search=&search=&allStudents=&subpage=Markbook#'.str_pad($courseGrades['gibbonCourseClassID'], 8, '0', STR_PAD_LEFT));
                }

                echo '<tr>';
                    echo '<td class="gradesRow" style="width:15%;text-align:left;">'.$courseGrades['courseNameShort'].'</td>';
                    echo '<td class="gradesRow" style="width:37%;text-align:left;">'.$courseGrades['courseName'].'</td>';
                    echo '<td class="'.$sem1MidClass.'" style="width:8%;">'.$sem1Mid.'</td>';
                    echo '<td class="'.$sem1EndClass.'" style="width:8%;">'.$sem1End.'</td>';
                    echo '<td class="'.$sem2MidClass.'" style="width:8%">'.$sem2Mid.'</td>';
                    echo '<td class="'.$sem2EndClass.'" style="width:8%">'.$sem2End.'</td>';
                    echo '<td class="'.$examClass.'" style="width:8%;border-left: 2px solid #bbb;">'.$exam.'</td>';
                    echo '<td class="'.$finalClass.'" style="width:8%;text-align:left;">'.$final.'</td>';
                echo '</tr>';
            }
            echo '</table>';


        }
    }

}
