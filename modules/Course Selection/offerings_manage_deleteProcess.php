<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\OfferingsGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/offerings_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/offerings_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $courseSelectionOfferingID = $_POST['courseSelectionOfferingID'] ?? '';

    if (empty($courseSelectionOfferingID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        $gateway = $container->get('CourseSelection\Domain\OfferingsGateway');

        $deleted = $gateway->delete($courseSelectionOfferingID);

        if ($deleted == false) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        } else {

            $deleted = $gateway->deleteAllBlocksByOffering($courseSelectionOfferingID);

            if ($deleted == false) {
                $URL .= "&return=warning2";
                header("Location: {$URL}");
                exit;
            } else {
                $URL .= "&return=success0";
                header("Location: {$URL}");
                exit;
            }
        }
    }
}
