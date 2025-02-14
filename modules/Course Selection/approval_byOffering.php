<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\ToolsGateway;
use CourseSelection\Domain\OfferingsGateway;
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
	 	->add(__m('Course Approval by Offering'));

    $toolsGateway = $container->get('CourseSelection\Domain\ToolsGateway');
    $offeringsGateway = $container->get('CourseSelection\Domain\OfferingsGateway');
    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');

    $courseSelectionOfferingID = $_REQUEST['courseSelectionOfferingID'] ?? '';
    $showRemoved = $_GET['showRemoved'] ?? 'N';
    
    $settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $enableCourseGrades = $settingGateway->getSettingByScope('Course Selection', 'enableCourseGrades');

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    // SELECT OFFERING
    $form = Form::create('courseApprovalByOffering', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/Course Selection/approval_byOffering.php');
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('sidebar', 'false');

    $offeringsResults = $offeringsGateway->selectAllBySchoolYear($gibbonSchoolYearID);

    if ($offeringsResults->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
        return;
    }

    $offerings = $offeringsResults->fetchAll();
    $offeringsArray = array_combine(array_column($offerings, 'courseSelectionOfferingID'), array_column($offerings, 'name'));

    $row = $form->addRow();
        $row->addLabel('courseSelectionOfferingID', __('Offering'));
        $row->addSelect('courseSelectionOfferingID')->fromArray($offeringsArray)->required()->placeholder()->selected($courseSelectionOfferingID);

    $row = $form->addRow();
        $row->addSubmit();

    echo $form->getOutput();

    $offeringResult = $offeringsGateway->selectOne($courseSelectionOfferingID);
    $offering = ($offeringResult && $offeringResult->rowCount() > 0)? $offeringResult->fetch() : array();

    if (empty($offering)) {
        return;
    }

    // LIST STUDENTS
    if (!empty($courseSelectionOfferingID)) {
        $studentChoicesResults = $selectionsGateway->selectStudentsByOffering($courseSelectionOfferingID);

        if ($studentChoicesResults->rowCount() == 0) {
            echo '<div class="error">';
            echo __("There are no records to display.") ;
            echo '</div>';
        } else {

            echo '<br/><p>';
            echo sprintf(__('Showing %1$s student course selections:'), $studentChoicesResults->rowCount());
            echo '</p>';

            echo '<table class="fullWidth colorOddEven" cellspacing="0">';

            while ($student = $studentChoicesResults->fetch()) {

                $choicesResults = $selectionsGateway->selectChoicesByOfferingAndPerson($courseSelectionOfferingID, $student['gibbonPersonID']);
                $choices = ($choicesResults && $choicesResults->rowCount() > 0)? $choicesResults->fetchAll() : array();

                $status = __('In Progress');
                $rowClass = '';

                if (count($choices) >= $offering['minSelect']) {
                    $status = __('Complete');
                    //$rowClass = 'current';
                }

                echo '<tr class="'.$rowClass.'" id="'.$student['gibbonPersonID'].'">';
                    echo '<td width="15%">';
                        echo '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$student['gibbonPersonID'].'" target="_blank">';
                        echo Format::userPhoto($student['image_240'], 75).'<br/>';
                        echo Format::name('', $student['preferredName'], $student['surname'], 'Student', true);
                        echo '</a><br/>';
                        echo $student['formGroupName'];
                    echo '</td>';

                    echo '<td width="35%">';
                        if (count($choices) > 0) {
                            foreach ($choices as $choice) {
                                $checked = ($choice['approval'] == 'Approved')? 'checked' : '';

                                echo '<div class="courseChoiceContainer" data-status="'.$choice['approval'].'">';
                                echo '<input type="checkbox" name="'.$student['gibbonPersonID'].'" class="courseSelectionApproval" value="'.$choice['courseSelectionChoiceID'].'" data-student="'.$student['gibbonPersonID'].'" '.$checked.'/> &nbsp;';

                                echo $choice['courseName'];

                                if ($choice['status'] == 'Required') {
                                    echo '<span class="pullRight courseTag small emphasis">'.$choice['status'].'</span>';
                                }
                                echo '</div>';
                            }
                        }
                    echo '</td>';
                    echo '<td width="35%">';
                        echo '<strong>'.$status.'</strong>: ';

                        echo sprintf(__('%1$s of %2$s courses selected'), count($choices), $offering['minSelect']).'<br/>';

                    echo '</td>';
                    echo '<td width="15%">';

                        echo "<a onclick='courseSelectionApproveAll(\"".$student['gibbonPersonID']."\")' style='cursor:pointer;'><img title='".__('Approve All')."' src='./themes/".$session->get('gibbonThemeName')."/img/iconTick_double.png'/></a> &nbsp;&nbsp;&nbsp;";

                        echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/selectionChoices.php&gibbonPersonIDStudent=".$student['gibbonPersonID']."&sidebar=false&courseSelectionOfferingID=".$student['courseSelectionOfferingID']."' target='_blank'><img title='".__('View Course Selections')."' src='./themes/".$session->get('gibbonThemeName')."/img/plus.png'/></a> &nbsp;&nbsp;";

                        if ($enableCourseGrades == 'Y') {
                            echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/report_studentGrades.php&gibbonPersonIDStudent=".$student['gibbonPersonID']."&sidebar=false' target='_blank'><img title='".__('Student Grades')."' src='./themes/".$session->get('gibbonThemeName')."/img/internalAssessment.png'/></a>";
                        }

                    echo '</td>';
                echo '</tr>';
            }

            echo '</table>';

            ?>
            <script>
                $('.courseSelectionApproval').change(function() {
                    courseSelectionApprovalSave($(this), <?php echo $courseSelectionOfferingID; ?>, '<?php echo $session->get('absoluteURL').'/modules/Course Selection/'; ?>');
                });
            </script>
            <?php
        }
    }
}
