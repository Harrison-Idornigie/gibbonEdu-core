<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\TimetableGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/tt_engine.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_engine.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';

    if (empty($gibbonSchoolYearID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');

        $deleted = $timetableGateway->deleteAllResultsBySchoolYear($gibbonSchoolYearID);

        if ($deleted == false) {
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
