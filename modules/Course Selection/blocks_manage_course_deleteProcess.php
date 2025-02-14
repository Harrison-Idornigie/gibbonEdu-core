<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\BlocksGateway;

// Module Bootstrap
require 'module.php';

$courseSelectionBlockID = $_GET['courseSelectionBlockID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/blocks_manage_addEdit.php&courseSelectionBlockID='.$courseSelectionBlockID;

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/blocks_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $gibbonCourseID = $_GET['gibbonCourseID'] ?? '';

    if (empty($courseSelectionBlockID) || empty($gibbonCourseID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $gateway = $container->get('CourseSelection\Domain\BlocksGateway');

        $deleted = $gateway->deleteCourse($courseSelectionBlockID, $gibbonCourseID);

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
