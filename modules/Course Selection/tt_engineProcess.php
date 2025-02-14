<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

include '../../gibbon.php';

use CourseSelection\BackgroundProcess;
use CourseSelection\Domain\SettingsGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/tt_engine.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_engine.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? null;

    if (empty($gibbonSchoolYearID)) {
        $URL .= "&return=error1";
        header("Location: {$URL}");
        exit;
    } else {
        $settingsGateway = $container->get('CourseSelection\Domain\SettingsGateway');

        // Save any changes made to timetabling settings
        $studentOrder = $_POST['studentOrder'] ?? 'yearGroupDesc';
        $settingsGateway->update('Course Selection', 'studentOrder', $studentOrder);

        $genderBalancePriority = $_POST['genderBalancePriority'] ?? '0.5';
        $settingsGateway->update('Course Selection', 'genderBalancePriority', $genderBalancePriority);

        $targetEnrolmentPriority = $_POST['targetEnrolmentPriority'] ?? '1.0';
        $settingsGateway->update('Course Selection', 'targetEnrolmentPriority', $targetEnrolmentPriority);

        $coreCoursePriority = $_POST['coreCoursePriority'] ?? '1.0';
        $settingsGateway->update('Course Selection', 'coreCoursePriority', $coreCoursePriority);

        $avoidConflictPriority = $_POST['avoidConflictPriority'] ?? '2.0';
        $settingsGateway->update('Course Selection', 'avoidConflictPriority', $avoidConflictPriority);

        $timetableConflictTollerance = $_POST['timetableConflictTollerance'] ?? '0';
        $settingsGateway->update('Course Selection', 'timetableConflictTollerance', $timetableConflictTollerance);

        $autoResolveConflicts = $_POST['autoResolveConflicts'] ?? 'Y';
        $settingsGateway->update('Course Selection', 'autoResolveConflicts', $autoResolveConflicts);

        $process = new BackgroundProcess($session->get('absolutePath').'/uploads/engine');
        $process->startProcess('engine', __DIR__.'/tt_engineRun.php', array($gibbonSchoolYearID));

        header("Location: {$URL}");
        exit;
    }
}
