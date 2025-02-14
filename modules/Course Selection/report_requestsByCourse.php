<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/report_requestsByCourse.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('Total Requests by Course'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $sort = $_GET['sort'] ?? 'surname';
    $min = $_GET['min'] ?? $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMinimum');
    $max = $_GET['max'] ?? $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMaximum');

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    echo '<h2>';
    echo __('Filter');
    echo '</h2>';

    $form = Form::create('action', $session->get('absoluteURL').'/index.php', 'get');

    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/Course Selection/report_requestsByCourse.php');

    $row = $form->addRow();
        $row->addLabel('sort', __('Sort By'));
        $row->addSelect('sort')->fromArray(array('nameShort' => __('Course Code'), 'name' => __('Course Name'), 'order' => __('Report Order'), 'count' => __('Requests')))->selected($sort);

    $row = $form->addRow();
        $row->addLabel('highlight', __('Target Range'))->description(__('Highlight courses outside this range.'));
        $column = $row->addColumn()->addClass('inline right');
            $column->addTextField('min')->setClass('shortWidth')->placeholder(__('Minimum'))->setValue($min);
            $column->addTextField('max')->setClass('shortWidth')->placeholder(__('Maximum'))->setValue($max);

    $row = $form->addRow();
        $row->addSubmit('Go');

    echo $form->getOutput();

    echo '<h2>';
    echo __('Course Requests');
    echo '</h2>';

    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');
    $courses = $selectionsGateway->selectChoiceCountsBySchoolYear($gibbonSchoolYearID, $sort);

    if ($courses->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
    } else {

        echo '<div class="paginationTop">';
        echo __('Records').': '.$courses->rowCount();
        echo '</div>';

        echo '<table class="fullWidth colorOddEven" cellspacing="0">';
        echo '<tr class="head">';
            echo '<th>';
                echo __('Course');
            echo '</th>';
            echo '<th>';
                echo __('Name');
            echo '</th>';
            echo '<th>';
                echo __('Requests');
            echo '</th>';
            echo '<th style="width: 80px;">';
                echo __('Actions');
            echo '</th>';
        echo '</tr>';

        $count = 0;
        while ($course = $courses->fetch()) {
            $trClass = '';

            if (!empty($min) && $course['count'] < $min) $trClass = 'warning';
            if (!empty($max) && $course['count'] > $max) $trClass = 'warning';

            echo '<tr class="'.$trClass.'">';
                echo '<td>'.$course['courseNameShort'].'</td>';
                echo '<td>'.$course['courseName'].'</td>';
                echo '<td>'.$course['count'].'</td>';

                echo '<td>';
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/approval_byCourse.php&sidebar=false&gibbonSchoolYearID=".$gibbonSchoolYearID."&gibbonCourseID=".$course['gibbonCourseID']."'><img title='".__('View Course Selections')."' src='./themes/".$session->get('gibbonThemeName')."/img/plus.png'/></a>";
                echo '</td>';
            echo '</tr>';

            $count++;
        }

        echo '</table>';

        echo '<h2>';
        echo __('Alternate Course Requests');
        echo '</h2>';

        // Count alternate course choices separately
        $alternates = $selectionsGateway->selectChoiceCountsBySchoolYear($gibbonSchoolYearID, $sort, 'N');
        
        if ($alternates->rowCount() == 0) {
            echo '<div class="error">';
            echo __("There are no records to display.") ;
            echo '</div>';
        } else {
    
            echo '<div class="paginationTop">';
            echo __('Records').': '.$alternates->rowCount();
            echo '</div>';
    
            echo '<table class="fullWidth colorOddEven" cellspacing="0">';
            echo '<tr class="head">';
                echo '<th>';
                    echo __('Course');
                echo '</th>';
                echo '<th>';
                    echo __('Name');
                echo '</th>';
                echo '<th>';
                    echo __('Requests');
                echo '</th>';
                echo '<th style="width: 80px;">';
                    echo __('Actions');
                echo '</th>';
            echo '</tr>';
    
            $count = 0;
            while ($course = $alternates->fetch()) {
                $trClass = '';
    
                if (!empty($min) && $course['count'] < $min) $trClass = 'warning';
                if (!empty($max) && $course['count'] > $max) $trClass = 'warning';
    
                echo '<tr class="'.$trClass.'">';
                    echo '<td>'.$course['courseNameShort'].'</td>';
                    echo '<td>'.$course['courseName'].'</td>';
                    echo '<td>'.$course['count'].'</td>';
    
                    echo '<td>';
                        echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/approval_byCourse.php&sidebar=false&gibbonSchoolYearID=".$gibbonSchoolYearID."&gibbonCourseID=".$course['gibbonCourseID']."'><img title='".__('View Course Selections')."' src='./themes/".$session->get('gibbonThemeName')."/img/plus.png'/></a>";
                    echo '</td>';
                echo '</tr>';
    
                $count++;
            }
    
            echo '</table>';
        }

    }
}
