<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\ToolsGateway;

require_once '../../gibbon.php';

// Module Bootstrap
require 'module.php';

$settingGateway = $container->get(SettingGateway::class);
$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
$gibbonCourseClassID = $_REQUEST['gibbonCourseClassID'] ?? '';
$gibbonTTID = $_REQUEST['gibbonTTID'] ?? '';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/Course Selection/tools_timetableByClass.php&gibbonSchoolYearID={$gibbonSchoolYearID}&gibbonTTID={$gibbonTTID}&gibbonCourseClassID={$gibbonCourseClassID}";

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tools_timetableByClass.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $gibbonTTDayRowClassID = $_GET['gibbonTTDayRowClassID'] ?? '';

    if (empty($gibbonTTDayRowClassID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $gateway = $container->get('CourseSelection\Domain\ToolsGateway');

        $deleted = $gateway->deleteTTDayRowClass($gibbonTTDayRowClassID);

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
