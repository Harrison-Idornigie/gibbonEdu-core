<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\Access;
use CourseSelection\Domain\AccessGateway;
use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

$courseSelectionOfferingID = $_POST['courseSelectionOfferingID'] ?? '';
$gibbonPersonIDStudent = $_POST['gibbonPersonIDStudent'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/Course Selection/selectionChoices.php&sidebar=false&gibbonPersonIDStudent={$gibbonPersonIDStudent}&courseSelectionOfferingID={$courseSelectionOfferingID}";

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/selectionChoices.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $highestGroupedAction = getHighestGroupedAction($guid, '/modules/Course Selection/selectionChoices.php', $connection2);

    if ($highestGroupedAction != 'Course Selection_all' && $gibbonPersonIDStudent != $session->get('gibbonPersonID')) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $accessGateway = $container->get('CourseSelection\Domain\AccessGateway');

    $accessRequest = $accessGateway->getAccessByOfferingAndPerson($courseSelectionOfferingID, $session->get('gibbonPersonID'));

    if (!$accessRequest || $accessRequest->rowCount() == 0) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } else {
        $access = new Access($accessRequest->fetch());

        if (($access->getAccessLevel() == Access::CLOSED || $access->getAccessLevel() == Access::VIEW_ONLY) && $highestGroupedAction != 'Course Selection_all') {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $data = array();
        $data['gibbonSchoolYearID'] = $_POST['gibbonSchoolYearID'] ?? '';
        $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
        $data['gibbonPersonIDSelected'] = $session->get('gibbonPersonID');
        $data['timestampSelected'] = date('Y-m-d H:i:s');
        $data['notes'] = '';

        if (empty($courseSelectionOfferingID) || empty($data['gibbonSchoolYearID']) || empty($data['gibbonPersonIDStudent']) || empty($data['gibbonPersonIDSelected'])) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        } else {
            $partialFail = false;
            $gateway = $container->get('CourseSelection\Domain\SelectionsGateway');

            $courseSelectionsList = array();
            $courseSelections = $_POST['courseSelection'] ?? array();
            $courseStatus = $_POST['courseStatus'] ?? array();

            if (!empty($courseStatus) && $highestGroupedAction == 'Course Selection_all') {
                $courseSelections = array_replace_recursive($courseSelections, $courseStatus);
            }

            if (!empty($courseSelections) && is_array($courseSelections)) {
                foreach ($courseSelections as $blockID => $courseBlockSelections) {
                    $data['courseSelectionBlockID'] = (!empty($blockID))? $blockID : null;

                    foreach ($courseBlockSelections as $courseSelection => $status) {
                        if (empty($courseSelection)) continue;

                        $data['gibbonCourseID'] = $courseSelection;
                        $data['status'] = '';

                        if ($highestGroupedAction == 'Course Selection_all') {
                            $data['status'] = $status;
                        } else if ($access['accessType'] == 'Select') {
                            $data['status'] = 'Selected';
                        } else {
                            $data['status'] = 'Requested';
                        }

                        $choiceRequest = $gateway->selectChoiceByCourseAndPerson($courseSelection, $gibbonPersonIDStudent);
                        if ($choiceRequest && $choiceRequest->rowCount() > 0) {
                            $choice = $choiceRequest->fetch();

                            if (empty($status) && !empty($courseSelectionsList[$courseSelection])) continue;

                            if ($highestGroupedAction == 'Course Selection_all' || $access['accessType'] == 'Select') {
                                if (empty($data['status'])) $data['status'] = 'Removed';

                                $partialFail &= !$gateway->updateChoice($data);
                            } else if ($choice['status'] == 'Removed' || $choice['status'] == 'Recommended' || $choice['status'] == 'Requested' || $choice['status'] == 'Selected' || $choice['status'] == '') {

                                $partialFail &= !$gateway->updateChoice($data);
                            }
                        } else {
                            if (empty($status) || empty($data['status'])) continue;
                            $partialFail &= !$gateway->insertChoice($data);
                        }

                        $courseSelectionsList[$courseSelection] = $courseSelection;
                    }
                }
            }

            $courseSelectionsList = (is_array($courseSelectionsList))? implode(',', $courseSelectionsList) : $courseSelectionsList;
            $gateway->updateUnselectedChoicesBySchoolYearAndPerson($data['gibbonSchoolYearID'], $gibbonPersonIDStudent, $courseSelectionsList);

            $data = array();
            $data['gibbonSchoolYearID'] = $_POST['gibbonSchoolYearID'] ?? '';
            $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
            $data['courseSelectionOfferingID'] = $courseSelectionOfferingID ?? '';

            $insertID = $gateway->insertChoiceOffering($data);
            $partialFail &= empty($insertID);


            $data = array();
            $data['courseSelectionOfferingID'] = $courseSelectionOfferingID ?? '';
            $data['gibbonSchoolYearID'] = $_POST['gibbonSchoolYearID'] ?? '';
            $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
            $data['gibbonPersonIDChanged'] = $session->get('gibbonPersonID');
            $data['timestampChanged'] = date('Y-m-d H:i:s');
            $data['action'] = 'Update';

            $insertID = $gateway->insertLog($data);
            $partialFail &= empty($insertID);

            if ($partialFail == true) {
                $URL .= '&return=error2';
                header("Location: {$URL}");
                exit;
            } else {
                $URL .= "&return=success0";
                header("Location: {$URL}");
                exit;
            }
        }
    }
}
