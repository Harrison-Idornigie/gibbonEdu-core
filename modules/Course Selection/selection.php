<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use CourseSelection\Domain\Access;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\AccessGateway;
use CourseSelection\Domain\OfferingsGateway;
use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/selection.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('Course Selection'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    $highestGroupedAction = getHighestGroupedAction($guid, '/modules/Course Selection/selection.php', $connection2);

    if ($highestGroupedAction == 'Course Selection_all') {
        $gibbonPersonIDStudent = isset($_REQUEST['gibbonPersonIDStudent'])? $_REQUEST['gibbonPersonIDStudent'] : 0;

        $form = Form::create('selectStudent', $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/selection.php');
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDStudent', __('Student'));
            $row->addSelectStudent('gibbonPersonIDStudent', $session->get('gibbonSchoolYearID'), array('allStudents' => false, 'byName' => true, 'showForm' => true))->selected($gibbonPersonIDStudent);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    } else if ($highestGroupedAction == 'Course Selection_my') {
        $gibbonPersonIDStudent = $session->get('gibbonPersonID');
    }

    // Cancel out early if there's no valid student selected
    if (empty($gibbonPersonIDStudent)) return;

    $accessGateway = $container->get('CourseSelection\Domain\AccessGateway');
    $offeringsGateway = $container->get('CourseSelection\Domain\OfferingsGateway');
    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');

    $accessRequest = $accessGateway->getAccessByPerson($gibbonSchoolYearID, $session->get('gibbonPersonID'));

    if (!$accessRequest || $accessRequest->rowCount() == 0) {
        echo "<div class='error'>" ;
            echo __('Course selection for this year is closed, or you do not have access at this time.');
        echo "</div>" ;
    } else {
        $today = date('Y-m-d');
        $access = new Access($accessRequest->fetch());

        if ($access->getAccessLevel() == Access::CLOSED) {
            $accessMessageClass = 'warning';
            $accessMessageText = sprintf(__('Course selection is currently %1$s.'), __('Closed'));
        }
        else if ($access->getAccessLevel() == Access::OPEN) {
            $accessMessageClass = 'success';
            $accessMessageText = sprintf(__('Course selection is currently %1$s.'), __('Open'));
        } else if ($access->getAccessLevel() == Access::VIEW_ONLY) {
            $accessMessageClass = 'message';
            $accessMessageText = sprintf(__('Course selection is currently %1$s.'), __('View Only'));
        } else {
            $accessMessageClass = 'warning';
            $accessMessageText = sprintf(__('Course selection is currently %1$s.'), __('Closed'));
        }

        echo '<div class="'.$accessMessageClass.'">';
            echo $accessMessageText.' '.sprintf(__('Access is available from %1$s to %2$s'),
                date('M j', strtotime($access['dateStart'])),
                date('M j, Y', strtotime($access['dateEnd']))
            );
        echo '</div>';

        if ($access->getAccessLevel() == Access::CLOSED && $highestGroupedAction != 'Course Selection_all') {
            return;
        }

        $infoText = $settingGateway->getSettingByScope('Course Selection', 'infoTextOfferings');
        if (!empty($infoText)) {
            echo '<p>'.$infoText.'</p>';
        }

        $readOnly = $access->getAccessLevel() == Access::VIEW_ONLY && !($highestGroupedAction == 'Course Selection_all');

        $offeringsRequest = $offeringsGateway->selectOfferingsByStudentEnrolment($gibbonSchoolYearID, $gibbonPersonIDStudent);

        if ($offeringsRequest && $offeringsRequest->rowCount() > 0) {
            $offeringChoiceRequest = $selectionsGateway->selectChoiceOffering($gibbonSchoolYearID, $gibbonPersonIDStudent);
            $offeringChoice = ($offeringChoiceRequest->rowCount() > 0)? $offeringChoiceRequest->fetchColumn(0) : 0;

            $form = Form::create('selection', $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/selectionChoices.php&sidebar=false');

            $form->setClass('fullWidth');
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
            $form->addHiddenValue('gibbonPersonIDStudent', $gibbonPersonIDStudent);

            $form->addRow()->addSubHeading(__('Course Offerings'));

            $offerings = $offeringsRequest->fetchAll();
            if (empty($offeringChoice)) {
                $firstOffering = current($offerings);
                $offeringChoice = $firstOffering['courseSelectionOfferingID'] ?? 0;
            }

            foreach ($offerings as $offering) {
                $row = $form->addRow();
                    $row->addLabel('courseSelectionOfferingID', $offering['name'])->description($offering['description']);
                    $row->addRadio('courseSelectionOfferingID')
                        ->required()
                        ->setClass('')
                        ->fromArray(array($offering['courseSelectionOfferingID'] => ''))
                        ->checked($offeringChoice)
                        ->setDisabled($readOnly);

                    if ($readOnly) {
                        $form->addHiddenValue('courseSelectionOfferingID', $offeringChoice);
                    }
            }

            $row = $form->addRow();
                $row->addSubmit( ($readOnly)? __('View') : __('Select') );

            echo $form->getOutput();
        } else {
            echo "<div class='error'>" ;
                echo __('There are no course offerings available at this time.');
            echo "</div>" ;
        }

    
    }
}
