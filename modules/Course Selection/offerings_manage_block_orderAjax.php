<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\OfferingsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/offerings_manage_addEdit.php') == false) {
    exit;
} else {
    //Proceed!
    $data = array();
    $data['courseSelectionOfferingID'] = $_POST['courseSelectionOfferingID'] ?? '';

    $courseSelectionBlockIDList = json_decode($_POST['blocklist']);

    if (empty($data['courseSelectionOfferingID']) || empty($courseSelectionBlockIDList)) {
        exit;
    } else {
        $gateway = $container->get('CourseSelection\Domain\OfferingsGateway');

        $count = 1;
        foreach ($courseSelectionBlockIDList as $courseSelectionBlockID) {

            $data['courseSelectionBlockID'] = $courseSelectionBlockID;
            $data['sequenceNumber'] = $count;

            $inserted = $gateway->updateBlockOrder($data);
            $count++;
        }
    }
}
