<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\BlocksGateway;

// Module Bootstrap
require 'module.php';

$courseSelectionBlockID = $_POST['courseSelectionBlockID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/blocks_manage_addEdit.php&courseSelectionBlockID='.$courseSelectionBlockID;

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/blocks_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $data = array();
    $data['courseSelectionBlockID'] = $courseSelectionBlockID;
    $data['gibbonSchoolYearID'] = $_POST['gibbonSchoolYearID'] ?? '';
    $data['gibbonDepartmentIDList'] = $_POST['gibbonDepartmentIDList'] ?? '';
    $data['name'] = $_POST['name'] ?? '';
    $data['description'] = $_POST['description'] ?? '';
    $data['countable'] = $_POST['countable'] ?? '';

    if (!empty($data['gibbonDepartmentIDList'])) {
        $data['gibbonDepartmentIDList'] = implode(',', $data['gibbonDepartmentIDList']);
    }

    if (empty($data['courseSelectionBlockID']) || empty($data['gibbonSchoolYearID']) || empty($data['name'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        $gateway = $container->get('CourseSelection\Domain\BlocksGateway');

        $updated = $gateway->update($data);

        if ($updated == false) {
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
