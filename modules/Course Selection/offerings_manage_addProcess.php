<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\OfferingsGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/offerings_manage_addEdit.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/offerings_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $data = array();
    $data['gibbonSchoolYearID'] = $_POST['gibbonSchoolYearID'] ?? '';
    $data['gibbonYearGroupIDList'] = $_POST['gibbonYearGroupIDList'] ?? array();
    $data['name'] = $_POST['name'] ?? '';
    $data['description'] = $_POST['description'] ?? '';
    $data['minSelect'] = $_POST['minSelect'] ?? 0;
    $data['maxSelect'] = $_POST['maxSelect'] ?? 0;
    $data['sequenceNumber'] = $_POST['sequenceNumber'] ?? 1;

    $data['gibbonYearGroupIDList'] = implode(',', $data['gibbonYearGroupIDList']);
    $data['minSelect'] = intval($data['minSelect']);
    $data['maxSelect'] = intval($data['maxSelect']);

    if (empty($data['gibbonSchoolYearID']) || empty($data['name']) || !isset($data['sequenceNumber'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        $gateway = $container->get('CourseSelection\Domain\OfferingsGateway');

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
