<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$courseSelectionOfferingID = $_POST['courseSelectionOfferingID'] ?? '';
$gibbonPersonIDStudent = $_POST['gibbonPersonIDStudent'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/Course Selection/selectionChoices.php&sidebar=false&gibbonPersonIDStudent={$gibbonPersonIDStudent}&courseSelectionOfferingID={$courseSelectionOfferingID}";

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/selectionChoices.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $partialFail = false;
    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');

    $highestGroupedAction = getHighestGroupedAction($guid, '/modules/Course Selection/selectionChoices.php', $connection2);
    if ($highestGroupedAction != 'Course Selection_all') {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $gibbonCourseID = $_POST['gibbonCourseID'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($courseSelectionOfferingID) || empty($gibbonSchoolYearID) || empty($gibbonCourseID) || empty($gibbonPersonIDStudent) || empty($status)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $data = array();
        $data['gibbonSchoolYearID'] = $gibbonSchoolYearID;
        $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
        $data['gibbonCourseID'] = $gibbonCourseID;
        $data['courseSelectionBlockID'] = null;
        $data['gibbonPersonIDSelected'] = $session->get('gibbonPersonID');
        $data['timestampSelected'] = date('Y-m-d H:i:s');
        $data['status'] = $status;
        $data['notes'] = '';

        $choiceRequest = $selectionsGateway->selectChoiceByCourseAndPerson($gibbonCourseID, $gibbonPersonIDStudent);
        if ($choiceRequest && $choiceRequest->rowCount() > 0) {
            $partialFail &= !$selectionsGateway->updateChoice($data);
        } else {
            $partialFail &= !$selectionsGateway->insertChoice($data);
        }

        if ($partialFail == true) {
            $URL .= '&return=warning2';
            header("Location: {$URL}");
            exit;
        } else {
            $URL .= "&return=success0";
            header("Location: {$URL}");
            exit;
        }
    }
}
