<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\SelectionsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/approval_byOffering.php') == false) {
    die(false);
} else {
    //Proceed!
    $courseSelectionOfferingID = $_POST['courseSelectionOfferingID'] ?? '';
    $gibbonPersonIDStudent = $_POST['gibbonPersonIDStudent'] ?? '';
    $courseSelectionChoiceID = $_POST['courseSelectionChoiceID'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($courseSelectionOfferingID) || empty($gibbonPersonIDStudent) || empty($courseSelectionChoiceID)) {
        die(false);
    } else {
        $gateway = $container->get('CourseSelection\Domain\SelectionsGateway');
        if ($status === 'true') {
            $data = array();
            $data['courseSelectionChoiceID'] = $courseSelectionChoiceID;
            $data['gibbonPersonIDApproved'] = $session->get('gibbonPersonID');
            $data['timestampApproved'] = date('Y-m-d H:i:s');

            $gateway->insertApproval($data);
        } else {
            $gateway->deleteApproval($courseSelectionChoiceID);
        }
        die(true);
    }
}
