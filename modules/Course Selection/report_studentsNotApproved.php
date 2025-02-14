<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/report_studentsNotApproved.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('Students Not Approved'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $sort = $_GET['sort'] ?? 'surname';

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    echo '<p>';
    echo __("This report shows students who have completed the course selection process but their courses have not yet been approved.");
    echo '<p>';

    echo '<h2>';
    echo __('Filter');
    echo '</h2>';

    $form = Form::create('action', $session->get('absoluteURL').'/index.php', 'get');

    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/Course Selection/report_studentsNotApproved.php');

    $row = $form->addRow();
        $row->addLabel('sort', __('Sort By'));
        $row->addSelect('sort')->fromArray(array('surname' => __('Surname'), 'formGroup' => __('Form Group'), 'approvalCount' => __('Approvals')))->selected($sort);

    $row = $form->addRow();
        $row->addSubmit('Go');

    echo $form->getOutput();

    echo '<h2>';
    echo __('Report Data');
    echo '</h2>';

    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');

    $students = $selectionsGateway->selectStudentsWithIncompleteSelections($gibbonSchoolYearID, $sort);

    if ($students->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
    } else {
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';

        echo '<tr class="head">';
            echo '<th>';
                echo __('Student');
            echo '</th>';
            echo '<th>';
                echo __('Form Group');
            echo '</th>';
            echo '<th>';
                echo __('Course Selections');
            echo '</th>';
            echo '<th>';
                echo __('Approved');
            echo '</th>';
            echo '<th>';
                echo __('Status');
            echo '</th>';
            echo '<th style="width: 80px;">';
                echo __('Actions');
            echo '</th>';
        echo '</tr>';

        $count = 0;
        while ($student = $students->fetch()) {

            // Skip incomplete selections on this report
            if (empty($student['selectedOfferingID'])) continue;
            if ($student['choiceCount'] < $student['minSelect']) continue;

            // Skip approved selections
            if ($student['approvalCount'] >= $student['choiceCount'] && $student['choiceCount'] > 0) continue;

            if ($student['approvalCount'] > 0) {
                $status = 'Partially Approved';
                $rowClass = 'dull';
            } else {
                $status = 'Needs Approval';
                $rowClass = '';
            }

            echo '<tr class="'.$rowClass.'">';
                echo '<td>';
                    echo '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$student['gibbonPersonID'].'&allStudents=on" target="_blank">';
                    echo Format::name('', $student['preferredName'], $student['surname'], 'Student', true);
                    echo '</a>';
                echo '</td>';

                echo '<td>'.$student['formGroupName'].'</td>';
                echo '<td>'.$student['choiceCount'].'</td>';
                echo '<td>'.$student['approvalCount'].'</td>';
                echo '<td>'.$status.'</td>';
                echo '<td>';
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/selectionChoices.php&sidebar=false&gibbonPersonIDStudent=".$student['gibbonPersonID']."&courseSelectionOfferingID=".$student['selectedOfferingID']."'><img title='".__('View Course Selections')."' src='./themes/".$session->get('gibbonThemeName')."/img/plus.png'/></a> &nbsp;";

                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/approval_byOffering.php&sidebar=false&courseSelectionOfferingID=".$student['selectedOfferingID']."&gibbonSchoolYearID=".$gibbonSchoolYearID."#".$student['gibbonPersonID']."'><img title='".__('Go to Approval')."' src='./themes/".$session->get('gibbonThemeName')."/img/page_right.png'/></a>";
                echo '</td>';
            echo '</tr>';

            $count++;
        }

        echo '</table>';

        echo '<div class="paginationBottom">';
        echo __('Records').': '.$count;
        echo '</div>';
    }
}
