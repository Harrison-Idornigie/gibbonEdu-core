<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\OfferingsGateway;

// Module Bootstrap
require 'module.php';

$courseSelectionOfferingID = $_POST['courseSelectionOfferingID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/offerings_manage_addEdit.php&courseSelectionOfferingID='.$courseSelectionOfferingID;

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/offerings_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $data = array();
    $data['courseSelectionOfferingID'] = $_POST['courseSelectionOfferingID'] ?? '';
    $courseSelectionBlockIDList = $_POST['courseSelectionBlockID'] ?? '';
    $data['minSelect'] = $_POST['minSelect'] ?? 0;
    $data['maxSelect'] = $_POST['maxSelect'] ?? 1;

    if (empty($data['courseSelectionOfferingID']) || empty($courseSelectionBlockIDList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $gateway = $container->get('CourseSelection\Domain\OfferingsGateway');

        $partialFail = false;
        foreach ($courseSelectionBlockIDList as $courseSelectionBlockID) {
            $data['courseSelectionBlockID'] = $courseSelectionBlockID;
            $inserted = $gateway->insertBlock($data);

            $partialFail = $partialFail && !$inserted;
        }

        if ($partialFail == true) {
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
