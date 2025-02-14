<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use CourseSelection\Domain\Access;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\AccessGateway;
use CourseSelection\Domain\OfferingsGateway;
use CourseSelection\Domain\BlocksGateway;
use CourseSelection\Domain\SelectionsGateway;
use CourseSelection\Domain\GradesGateway;
use CourseSelection\Domain\ToolsGateway;
use CourseSelection\Form\CourseSelectionFormFactory;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/selectionChoices.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
	$page->breadcrumbs
		->add(__m('Course Selection'), 'selection.php')
		->add(__m('Course Selection Choices'));

    $highestGroupedAction = getHighestGroupedAction($guid, '/modules/Course Selection/selectionChoices.php', $connection2);

    $gibbonPersonIDStudent = isset($_REQUEST['gibbonPersonIDStudent'])? $_REQUEST['gibbonPersonIDStudent'] : 0;
    $courseSelectionOfferingID = isset($_REQUEST['courseSelectionOfferingID'])? $_REQUEST['courseSelectionOfferingID'] : 0;

    // Cancel out early if there's no valid student or course offering selected
    if (empty($courseSelectionOfferingID) || empty($gibbonPersonIDStudent)) {
        echo '<div class="error">';
            echo __('You do not have access to this action.');
        echo '</div>';
        return;
    }

    // Cancel out if a student is accessing a different student record
    if ($highestGroupedAction != 'Course Selection_all' && $gibbonPersonIDStudent != $session->get('gibbonPersonID')) {
        echo "<div class='error'>" ;
            echo __('You do not have access to this action.');
        echo "</div>" ;
        return;
    }

    $accessGateway = $container->get('CourseSelection\Domain\AccessGateway');
    $offeringsGateway = $container->get('CourseSelection\Domain\OfferingsGateway');
    $blocksGateway = $container->get('CourseSelection\Domain\BlocksGateway');
    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');
    $gradesGateway = $container->get('CourseSelection\Domain\GradesGateway');

    $accessRequest = $accessGateway->getAccessByOfferingAndPerson($courseSelectionOfferingID, $session->get('gibbonPersonID'));

    $offeringRequest = $offeringsGateway->selectOne($courseSelectionOfferingID);

    if (!$accessRequest || $accessRequest->rowCount() == 0 || !$offeringRequest || $offeringRequest->rowCount() == 0) {
        echo "<div class='error'>" ;
            echo __('Course selection for this year is closed, or you do not have access at this time.');
        echo "</div>" ;
    } else {
        $access = new Access($accessRequest->fetch());
        $offering = $offeringRequest->fetch();

        if ($access->getAccessLevel() == Access::CLOSED && $highestGroupedAction != 'Course Selection_all') {
            echo "<div class='error'>";
            echo __('Course selection for this year is closed, or you do not have access at this time.');
            echo "</div>";
            return;
        }

        $readOnly = $access->getAccessLevel() == Access::VIEW_ONLY && ! ($highestGroupedAction == 'Course Selection_all');

        echo '<h3>';
        echo __('Course Selection').' '.$access['schoolYearName'];
        echo '</h3>';

        if ($gibbonPersonIDStudent != $session->get('gibbonPersonID')) {
            $studentRequest = $selectionsGateway->selectStudentDetails($gibbonPersonIDStudent);
            if ($studentRequest && $studentRequest->rowCount() > 0) {
                $student = $studentRequest->fetch();
                echo '<p>';
                echo __('Selecting courses for ');
                echo '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$gibbonPersonIDStudent.'" target="_blank">';
                echo '<strong>'.Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true).'</strong>';
                echo '</a></p>';
            }
        }
        
		$settingGateway = $container->get(SettingGateway::class);
        $enableCourseGrades = $settingGateway->getSettingByScope('Course Selection', 'enableCourseGrades');
        $infoTextBefore = $settingGateway->getSettingByScope('Course Selection', 'infoTextSelectionBefore');
        if (!empty($infoTextBefore)) {
            echo '<p>'.$infoTextBefore.'</p>';
        }

        $offeringChoiceRequest = $selectionsGateway->selectChoiceOffering($offering['gibbonSchoolYearID'], $gibbonPersonIDStudent);
        $offeringChoice = ($offeringChoiceRequest->rowCount() > 0)? $offeringChoiceRequest->fetchColumn(0) : 0;

        if (!empty($offeringChoice) && $offeringChoice != $courseSelectionOfferingID) {
            echo '<div class="warning">';
                echo __('You have changed your previous course offering selection. Submitting your course choices here will replace any choices selected in a previous course offering.');
            echo '</div>';
        }

        $form = Form::createTable('selectionChoices', $session->get('absoluteURL').'/modules/Course Selection/selectionChoicesProcess.php');
        $form->setFactory(CourseSelectionFormFactory::create($selectionsGateway));

        $form->setClass('fullWidth smallIntBorder');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('courseSelectionOfferingID', $courseSelectionOfferingID);
        $form->addHiddenValue('gibbonSchoolYearID', $offering['gibbonSchoolYearID']);
        $form->addHiddenValue('gibbonPersonIDStudent', $gibbonPersonIDStudent);

        $form->addRow()->addHeading($offering['name'])->append($offering['description']);

        $row = $form->addRow()->setClass('break');
            $row->addContent(__('Department'));
            $row->onlyIf($enableCourseGrades == 'Y')->addContent(__('Course Grades'));
            $row->addContent(__('Choices'));

            if ($readOnly == false) {
                $row->addContent(__('Progress'));
            }

        $alreadySelected = array();

        $blocksRequest = $offeringsGateway->selectAllBlocksByOffering($courseSelectionOfferingID);
        if ($blocksRequest && $blocksRequest->rowCount() > 0) {
            while ($block = $blocksRequest->fetch()) {
                if ($block['courseCount'] == 0) continue;

                $courseSelectionBlockID = $block['courseSelectionBlockID'];

                $fieldName = 'courseSelection['.$courseSelectionBlockID.'][]';

                if ($enableCourseGrades == 'Y') {
                    $gradesRequest = $gradesGateway->selectStudentReportGradesByDepartments($block['gibbonDepartmentIDList'], $gibbonPersonIDStudent);
                }
                $coursesRequest = $blocksGateway->selectAllCoursesByBlock($courseSelectionBlockID);

                $selectedChoicesRequest = $selectionsGateway->selectChoicesByBlockAndPerson($courseSelectionBlockID, $gibbonPersonIDStudent);
                $selectedChoices = ($selectedChoicesRequest->rowCount() > 0)? $selectedChoicesRequest->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE) : array();

                // Prevent pre-filled courses from selecting multiple times (gives priority to the first instance)
                foreach ($selectedChoices as $gibbonCourseID => $choice) {
                    if (!empty($alreadySelected[$gibbonCourseID]) && ($alreadySelected[$gibbonCourseID] == 'Required' || $alreadySelected[$gibbonCourseID] == 'Approved')) {
                        $selectedChoices[$gibbonCourseID]['status'] = 'Locked';
                    }

                    $alreadySelected[$gibbonCourseID] = $choice['status'];
                }

                $row = $form->addRow();
                $row->addLabel('courseSelection', $block['blockName'])->description($block['blockDescription']);
                $row->onlyIf($enableCourseGrades == 'Y')->addCourseGrades()->fromResults($gradesRequest ?? []);
                $row->addCourseSelection($fieldName)
                    ->fromResults($coursesRequest)
                    ->selected($selectedChoices)
                    ->setReadOnly($readOnly)
                    ->setBlockID($courseSelectionBlockID)
                    ->canSelectStatus($highestGroupedAction == 'Course Selection_all');

                if ($readOnly == false) {
                    $row->addCourseProgressByBlock($block);
                }
            }
        }

        $unofferedChoicesRequest = $selectionsGateway->selectUnofferedChoicesByPerson($courseSelectionOfferingID, $gibbonPersonIDStudent);
        if ($unofferedChoicesRequest && $unofferedChoicesRequest->rowCount() > 0) {
            $unofferedChoices = $unofferedChoicesRequest->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);

            $row = $form->addRow();
            $row->addLabel('courseSelection', __('Other Courses'));
            $row->addContent();
            $row->addCourseSelection('courseSelection[][]')
                ->fromArray($unofferedChoices)
                ->selected($unofferedChoices)
                ->setReadOnly($readOnly)
                ->setBlockID(null)
                ->canSelectStatus($highestGroupedAction == 'Course Selection_all');

            if ($readOnly == false) {
                $row->addContent();
            }
        }

        if ($readOnly == false) {
            $row = $form->addRow();
                $progress = $row->addCourseProgressByOffering($offering);
                $progress->setMessage('complete', $settingGateway->getSettingByScope('Course Selection', 'selectionComplete'));
                $progress->setMessage('invalid', $settingGateway->getSettingByScope('Course Selection', 'selectionInvalid'));
                $progress->setMessage('continue', $settingGateway->getSettingByScope('Course Selection', 'selectionContinue'));

            $row = $form->addRow();
                $row->addSubmit(__('Save'));
        }

        echo $form->getOutput();

        $infoTextAfter = $settingGateway->getSettingByScope('Course Selection', 'infoTextSelectionAfter');
        if (!empty($infoTextAfter)) {
            echo '<br/><p>'.$infoTextAfter.'</p>';
        }

        if ($highestGroupedAction == 'Course Selection_all') {
            $toolsGateway = $container->get('CourseSelection\Domain\ToolsGateway');

            echo '<h3>';
            echo __('Add a Course Selection');
            echo '</h3>';

            // MANUALLY ADD COURSE
            $form = Form::create('selectByStudent', $session->get('absoluteURL').'/modules/Course Selection/selectionChoices_addProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('gibbonSchoolYearID', $access['gibbonSchoolYearID']);
            $form->addHiddenValue('gibbonPersonIDStudent', $gibbonPersonIDStudent);
            $form->addHiddenValue('courseSelectionOfferingID', $courseSelectionOfferingID);

            $courses = array();
            $courseResults = $toolsGateway->selectAllCoursesBySchoolYear($access['gibbonSchoolYearID']);
            if ($courseResults && $courseResults->rowCount() > 0) {
                while ($row = $courseResults->fetch()) {
                    $courses[$row['grouping']][$row['value']] = $row['name'];
                }
            }

            $row = $form->addRow();
                $row->addLabel('gibbonCourseID', __('Course'));
                $row->addSelect('gibbonCourseID')->fromArray($courses)->required();

            $row = $form->addRow();
                    $row->addLabel('status', __('Selection Status'));
                    $row->addSelect('status')->fromArray(array('Required', 'Recommended', 'Selected', 'Requested'))->required();

            $row = $form->addRow();
                $row->addSubmit('Add');

            echo $form->getOutput();
        }
    }
}
