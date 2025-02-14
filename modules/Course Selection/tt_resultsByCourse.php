<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\TimetableGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_resultsByCourse.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('View Results by Course'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $sort = $_GET['sort'] ?? 'surname';
    $allCourses = $_GET['allCourses'] ?? false;

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');
    $classResults = $timetableGateway->selectCourseResultsBySchoolYear($gibbonSchoolYearID, $sort, $allCourses);

    if (!$classResults || $classResults->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
    } else {
        echo '<h2>';
        echo __('Filter');
        echo '</h2>';

        $form = Form::create('resultsByCourse', $session->get('absoluteURL').'/index.php', 'get');

        $form->setClass('noIntBorder fullWidth');
        $form->addHiddenValue('q', '/modules/Course Selection/tt_resultsByCourse.php');

        $row = $form->addRow();
            $row->addLabel('sort', __('Sort By'));
            $row->addSelect('sort')->fromArray(array('nameShort' => __('Course Code'), 'name' => __('Course Name'), 'period' => __('Period'), 'students' => __('Students'),'issues' => __('Issues')))->selected($sort);

        $row = $form->addRow();
            $row->addLabel('allCourses', __('All Courses'))->description(__('Include courses with no classes or not timetabled.'));
            $row->addCheckbox('allCourses')->setValue('Y')->checked($allCourses);

        $row = $form->addRow();
            $row->addSubmit('Go');

        echo $form->getOutput();

        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';

        echo '<div class="paginationTop">';
        echo __('Records').': '.$classResults->rowCount();
        echo '</div>';

        echo '<table class="fullWidth colorOddEven" cellspacing="0">';

        echo '<tr class="head">';
            echo '<th>';
                echo __('Course');
            echo '</th>';
            echo '<th>';
                echo __('Class');
            echo '</th>';
            echo '<th>';
                echo __('Balance');
            echo '</th>';
            echo '<th>';
                echo __('Students');
            echo '</th>';
            echo '<th>';
                echo __('Issues');
            echo '</th>';
            echo '<th style="weight: 60px;">';
                echo __('Actions');
            echo '</th>';
        echo '</tr>';

        $classEnrolmentMaximum = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMaximum');

        while ($class = $classResults->fetch()) {
            $rowClass = ($class['students'] < 8)? 'dull' : (($class['students'] > $classEnrolmentMaximum)? 'warning' : '');
            echo '<tr class="'.$rowClass.'">';
                echo '<td>'.$class['courseName'];
                if (!empty($class['excluded'])) {
                    echo " <img class='pullRight' title='".__('Excluded from timetabling via Meta Data settings.')."' src='./themes/".$session->get('gibbonThemeName')."/img/key.png'/ style='width:20px;height:20px;margin: -4px 0 -4px 4px;opacity: 0.6;'>";
                }
                echo '</td>';
                echo '<td>'.$class['className'].'</td>';
                echo '<td style="width:25%">';
                    if ($class['students'] > 0) {
                        $progressMaximum = max($classEnrolmentMaximum, $class['students']);
                        $femaleBalance = (($class['studentsFemale'] / $progressMaximum) * 100.0);
                        $maleBalance = (($class['studentsMale'] / $progressMaximum) * 100.0);

                        echo '<div class="progressBar fill" style="width:100%">';
                            echo '<div class="complete" style="width:'.$femaleBalance.'%;" title="'.__('Female').' '.$class['studentsFemale'].'"></div>';
                            echo '<div class="highlight" style="width:'.$maleBalance.'%;" title="'.__('Male').' '.$class['studentsMale'].'"></div>';
                    }
                    echo '</div>';
                echo '</td>';
                echo '<td>'.$class['students'].'</td>';
                echo '<td>'.$class['issues'].'</td>';
                echo '<td>';
                    $enrolmentGroup = (!empty($class['enrolmentGroup']))? $class['enrolmentGroup'] : $class['gibbonCourseClassID'];
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Course Selection/tt_resultsByStudent.php&gibbonCourseClassID=".$enrolmentGroup."&gibbonSchoolYearID=".$gibbonSchoolYearID."'><img title='".__('View')."' src='./themes/".$session->get('gibbonThemeName')."/img/plus.png'/></a>";
                echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}
