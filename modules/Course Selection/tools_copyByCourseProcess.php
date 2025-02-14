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

$gibbonCourseID = $_POST['gibbonCourseID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/Course Selection/tools_copyByCourse.php&gibbonSchoolYearID={$gibbonSchoolYearID}&gibbonCourseID={$gibbonCourseID}";

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tools_copyByCourse.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $partialFail = false;
    $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');
    $selectionsGateway = $container->get('CourseSelection\Domain\SelectionsGateway');

    $gibbonSchoolYearIDCopyTo = $_POST['gibbonSchoolYearIDCopyTo'] ?? '';
    $gibbonCourseIDCopyTo = $_POST['gibbonCourseIDCopyTo'] ?? '';
    $studentList = $_POST['studentList'] ?? '';
    $status = $_POST['status'] ?? '';
    $overwrite = $_POST['overwrite'] ?? 'Y';
    $actionCopyTo = $_POST['actionCopyTo'] ?? 'Y';

    if (empty($gibbonSchoolYearID) || empty($gibbonSchoolYearIDCopyTo) || empty($gibbonCourseID) || empty($gibbonCourseIDCopyTo) || empty($studentList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        if ($actionCopyTo == 'Requests') {
            $data = array();
            $data['gibbonSchoolYearID'] = $gibbonSchoolYearIDCopyTo;
            $data['gibbonCourseID'] = $gibbonCourseIDCopyTo;
            $data['courseSelectionBlockID'] = null;
            $data['gibbonPersonIDSelected'] = $session->get('gibbonPersonID');
            $data['timestampSelected'] = date('Y-m-d H:i:s');
            $data['status'] = ($status != 'Approved')? $status : 'Requested';
            $data['notes'] = '';

            $dataApproval = array();
            $dataApproval['gibbonPersonIDApproved'] = $session->get('gibbonPersonID');
            $dataApproval['timestampApproved'] = date('Y-m-d H:i:s');

            foreach ($studentList as $gibbonPersonIDStudent) {
                $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
                $courseSelectionChoiceID = null;

                $choiceRequest = $selectionsGateway->selectChoiceByCourseAndPerson($gibbonCourseIDCopyTo, $gibbonPersonIDStudent);
                if ($choiceRequest && $choiceRequest->rowCount() > 0) {
                    $choice = $choiceRequest->fetch();
                    $courseSelectionChoiceID = $choice['courseSelectionChoiceID'];

                    if ($overwrite == 'Y') {
                        $partialFail &= !$selectionsGateway->updateChoice($data);
                    }
                } else {
                    $courseSelectionChoiceID = $selectionsGateway->insertChoice($data);
                    $partialFail &= !$courseSelectionChoiceID;
                }

                if ($status == 'Approved' && !empty($courseSelectionChoiceID)) {
                    $dataApproval['courseSelectionChoiceID'] = $courseSelectionChoiceID;
                    $partialFail &= !$selectionsGateway->insertApproval($dataApproval);
                }
            }
        } else if ($actionCopyTo == 'Enrolments') {
            $gibbonCourseClassIDCopyTo = $_POST['gibbonCourseClassIDCopyTo'] ?? '';

            if (empty($gibbonCourseClassIDCopyTo)) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            } else {
                $data = array();
                $data['gibbonCourseClassID'] = $gibbonCourseClassIDCopyTo;
                $data['role'] = 'Student';

                foreach ($studentList as $gibbonPersonIDStudent) {
                    $data['gibbonPersonID'] = $gibbonPersonIDStudent;

                    $partialFail &= !$timetableGateway->insertClassEnrolment($data);
                }
            }
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
