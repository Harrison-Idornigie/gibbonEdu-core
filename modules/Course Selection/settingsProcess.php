<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\SettingsGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/settings.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $partialFail = false;
    $settingsGateway = $container->get('CourseSelection\Domain\SettingsGateway');

    $activeSchoolYear = $_POST['activeSchoolYear'] ?? $session->get('gibbonSchoolYearID');
    $partialFail &= !$settingsGateway->update('Course Selection', 'activeSchoolYear', $activeSchoolYear);

    $requireApproval = $_POST['requireApproval'] ?? 'Y';
    $partialFail &= !$settingsGateway->update('Course Selection', 'requireApproval', $requireApproval);

    $infoTextOfferings = $_POST['infoTextOfferings'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'infoTextOfferings', $infoTextOfferings);

    $infoTextSelectionBefore = $_POST['infoTextSelectionBefore'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'infoTextSelectionBefore', $infoTextSelectionBefore);

    $infoTextSelectionAfter = $_POST['infoTextSelectionAfter'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'infoTextSelectionAfter', $infoTextSelectionAfter);

    $selectionComplete = $_POST['selectionComplete'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'selectionComplete', $selectionComplete);

    $selectionInvalid = $_POST['selectionInvalid'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'selectionInvalid', $selectionInvalid);

    $selectionContinue = $_POST['selectionContinue'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'selectionContinue', $selectionContinue);

    $classEnrolmentMinimum = $_POST['classEnrolmentMinimum'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'classEnrolmentMinimum', $classEnrolmentMinimum);

    $classEnrolmentTarget = $_POST['classEnrolmentTarget'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'classEnrolmentTarget', $classEnrolmentTarget);

    $classEnrolmentMaximum = $_POST['classEnrolmentMaximum'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'classEnrolmentMaximum', $classEnrolmentMaximum);

    $enableCourseGrades = $_POST['enableCourseGrades'] ?? '';
    $partialFail &= !$settingsGateway->update('Course Selection', 'enableCourseGrades', $enableCourseGrades);

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
