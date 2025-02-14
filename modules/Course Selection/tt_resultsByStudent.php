<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\TimetableGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_resultsByStudent.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('View Results by Student'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $gibbonCourseClassID = $_REQUEST['gibbonCourseClassID'] ?? null;
    $enableCourseGrades = $settingGateway->getSettingByScope('Course Selection', 'enableCourseGrades');

    $sort = $_GET['sort'] ?? 'surname';
    $allCourses = $_GET['allCourses'] ?? false;

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');
    $studentResults = $timetableGateway->selectStudentResultsBySchoolYear($gibbonSchoolYearID, $sort, $gibbonCourseClassID);

    if (!$studentResults || $studentResults->rowCount() == 0) {
        echo '<div class="error">';
        echo __('There are no records to display.') ;
        echo '</div>';
    } else {
        echo '<h2>';
        echo __('Filter');
        echo '</h2>';

        $form = Form::create('resultsByStudent', $session->get('absoluteURL').'/index.php', 'get');

        $form->setClass('noIntBorder fullWidth');
        $form->addHiddenValue('q', '/modules/Course Selection/tt_resultsByStudent.php');
        $form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);

        $row = $form->addRow();
            $row->addLabel('sort', __('Sort By'));
            $row->addSelect('sort')->fromArray(array('surname' => __('Surname'), 'formGroup' => __('Form Group'), 'count' => __('Classes'), 'count' => __('Issues'), 'weight' => __('Weight')))->selected($sort);

        $row = $form->addRow();
            $row->addLabel('allCourses', __('All Courses'))->description(__('Include courses with no classes or not timetabled.'));
            $row->addCheckbox('allCourses')->setValue('Y')->checked($allCourses);

        $row = $form->addRow();
            $row->addSubmit('Go');

        echo $form->getOutput();

        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';

        $students = $studentResults->fetchAll(\PDO::FETCH_GROUP);

        echo '<div class="paginationTop">';
        echo __('Records').': '.count($students);
        echo '</div>';

        echo '<table class="fullWidth colorOddEven" cellspacing="0">';

        echo '<tr class="head">';
            echo '<th width="22%">';
                echo __('Name');
            echo '</th>';
            echo '<th>';
                echo __('Grade');
            echo '</th>';
            echo '<th style="width:50%">';
                echo __('Classes');
            echo '</th>';
            echo '<th>';
                echo __('Weight');
            echo '</th>';
            echo '<th>';
                echo __('Issues');
            echo '</th>';
        echo '</tr>';

        foreach ($students as $studentClasses) {
            $rowClass = '';

            $student = current($studentClasses);

            echo '<tr class="'.$rowClass.'">';
                echo '<td>';
                    echo '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Course Selection/approval_byOffering.php&sidebar=false&courseSelectionOfferingID='.$student['courseSelectionOfferingID'].'&gibbonSchoolYearID='.$gibbonSchoolYearID.'#'.$student['gibbonPersonID'].'" target="_blank">';
                    echo Format::name('', $student['preferredName'], $student['surname'], 'Student', true);
                    echo '</a><br/><br/>';

                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Course Selection/tt_previewByStudent.php&gibbonPersonID=".$student['gibbonPersonID']."' target='_blank'><img title='".__('Preview Timetable')."' src='./themes/".$session->get('gibbonThemeName')."/img/markbook.png'/></a>";
                    echo '&nbsp;&nbsp;';
                    
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Timetable Admin/courseEnrolment_manage_byPerson_edit.php&gibbonSchoolYearID={$gibbonSchoolYearID}&gibbonPersonID=".$student['gibbonPersonID']."&allUsers=on&ttDate=06/09/2017&type=Student&search=' target='_blank'><img title='".__('Course Enrolment')."' src='./themes/".$session->get('gibbonThemeName')."/img/attendance.png'/></a>";
                    echo '&nbsp;&nbsp;';

                    if ($enableCourseGrades == 'Y') {
                        echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/report_studentGrades.php&gibbonPersonIDStudent=".$student['gibbonPersonID']."&sidebar=false' target='_blank'><img title='".__('Student Grades')."' src='./themes/".$session->get('gibbonThemeName')."/img/internalAssessment.png'/></a>";
                        echo '&nbsp;&nbsp;';
                    }
                    
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=".$student['gibbonPersonID']."&allStudents=on' target='_blank'><img title='".__('View Student')."' src='./themes/".$session->get('gibbonThemeName')."/img/plus.png'/></a>&nbsp";
                    
                echo '</td>';

                echo '<td>'.$student['formGroupName'].'</td>';
                echo '<td>';

                $conflictCount = 0;
                if (!empty($studentClasses) && !empty($student['classNameShort'])) {
                    usort($studentClasses, function($a, $b) { return strnatcmp($a['classNameShort'], $b['classNameShort']); } );

                    foreach ($studentClasses as $class) {
                        $status = ($class['status'] != 'Complete')? ($class['flag'] == 'Full'? 'Failed' : 'Conflict') : '';

                        echo '<div class="courseChoiceContainer" data-status="'.$status.'">';
                        echo '<span style="width:35px;">'.$class['classNameShort'].'</span>';
                        echo '<span title="'.$class['courseNameShort'].'.'.$class['classNameShort'].'">';
                            echo $class['courseName'];
                        echo '</span>';

                        if ($status != '') {
                            echo '<span class="pullRight courseTag small emphasis" title="'.$class['reason'].'">'.__($class['flag']).'</span>';
                        } else if ($class['currentEnrolment'] == 1) {
                            echo '<span class="pullRight courseTag small emphasis" title="'.__('Already enroled in class.').'">'.__('Enroled').'</span>';
                        }
                        echo '</div>';

                        $conflictCount += (!empty($class['flag']))? 1 : 0;
                    }
                }

                if ($allCourses && empty($gibbonCourseClassID)) {
                    $incompleteResults = $timetableGateway->selectIncompleteResultsBySchoolYearAndStudent($gibbonSchoolYearID, $student['gibbonPersonID']);
                    if ($incompleteResults && $incompleteResults->rowCount() > 0) {
                        while ($course = $incompleteResults->fetch()) {
                            echo '<div class="courseChoiceContainer" data-status="Unknown" title="'.$course['courseNameShort'].'">';
                            echo '<span style="width:35px; display:inline-block;"></span>';
                            echo $course['courseName'];

                            $reasons = array();
                            if (empty($course['classCount'])) {
                                $reasons[] = __('No classes');
                            }
                            if (empty($course['ttCount'])) {
                                $reasons[] = __('Not timetabled');
                            }
                            if (!empty($course['excludeClasses'])) {
                                $reasons[] = __('Classes excluded');
                            }


                            echo '<span class="pullRight courseTag small emphasis" title="'.implode(', ', $reasons).'">'.__('Omitted').'</span>';
                            echo '</div>';
                        }
                    }
            }

                echo '</td>';
                echo '<td>'.$student['weight'].'</td>';
                echo '<td>'.$conflictCount.'</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

}
