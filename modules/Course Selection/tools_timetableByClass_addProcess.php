<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\ToolsGateway;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

// Module Bootstrap
require 'module.php';

$settingGateway = $container->get(SettingGateway::class);

$settingGateway = $container->get(SettingGateway::class);
$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
$gibbonCourseClassID = $_REQUEST['gibbonCourseClassID'] ?? '';
$gibbonTTID = $_REQUEST['gibbonTTID'] ?? '';

$URL = $session->get('absoluteURL') . "/index.php?q=/modules/Course Selection/tools_timetableByClass.php&gibbonSchoolYearID={$gibbonSchoolYearID}&gibbonTTID={$gibbonTTID}&gibbonCourseClassID={$gibbonCourseClassID}";

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tools_timetableByClass.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    if (empty($gibbonCourseClassID) || empty($gibbonTTID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $toolGateway = $container->get(ToolsGateway::class);

    $success = $toolGateway->deleteTTDayRowClasses($gibbonCourseClassID, $gibbonTTID);

    $entryOrders = $_POST['order'] ?? [];

    foreach ($entryOrders as $order) {
        $entry = $_POST['ttBlocks'][$order];

        if (empty($entry['gibbonTTColumnRowID']) || empty($entry['gibbonTTDayID'])) {
            continue;
        }

        $data = [
            'gibbonTTColumnRowID' => strstr($entry['gibbonTTColumnRowID'], '-', true),
            'gibbonTTDayID' => $entry['gibbonTTDayID'],
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'gibbonSpaceID' => $entry['gibbonTTSpaceID'] ?? ''
        ]; 

        $success &= $toolGateway->insertTTDayRowClass($data) > 0;
    }

    if ($success) {
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    } else {
        $URL .= '&return=warning1';
        header("Location: {$URL}");
        exit;
    }
}
