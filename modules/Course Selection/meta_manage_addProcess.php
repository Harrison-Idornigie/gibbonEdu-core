<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\MetaDataGateway;

require_once '../../gibbon.php';

// Module Bootstrap
require 'module.php';

$settingGateway = $container->get(SettingGateway::class);
$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/meta_manage_addEdit.php&gibbonSchoolYearID'.$gibbonSchoolYearID;

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/meta_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $data = array();
    $data['gibbonCourseID'] = $_POST['gibbonCourseID'] ?? '';
    $data['enrolmentGroup'] = $_POST['enrolmentGroup'] ?? null;
    $data['timetablePriority'] = $_POST['timetablePriority'] ?? null;
    $data['tags'] = $_POST['tags'] ?? null;
    $data['excludeClasses'] = $_POST['excludeClasses'] ?? '';

    if (is_array($data['excludeClasses'])) $data['excludeClasses'] = implode(',', $data['excludeClasses']);

    if (empty($gibbonSchoolYearID) || empty($data['gibbonCourseID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $gateway = $container->get('CourseSelection\Domain\MetaDataGateway');

        $insertID = $gateway->insert($data);

        if (empty($insertID)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        } else {
            $URL .= "&return=success0&editID=$insertID";
            header("Location: {$URL}");
            exit;
        }
    }
}
