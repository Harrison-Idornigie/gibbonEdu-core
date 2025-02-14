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
    $name = $_POST['name'] ?? '';
    $nameShort = $_POST['nameShort'] ?? '';

    if (empty($gibbonCourseClassID) || empty($name) || empty($nameShort)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $gateway = $container->get('CourseSelection\Domain\ToolsGateway');

        $data = array(
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'name'                => $name,
            'nameShort'           => $nameShort,
        );

        $renamed = $gateway->renameCourseClass($data);

        if ($renamed == false) {
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
