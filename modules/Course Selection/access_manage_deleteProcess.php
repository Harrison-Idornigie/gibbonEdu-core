<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

require_once '../../gibbon.php';

use CourseSelection\Domain\AccessGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/access_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/access_manage_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $courseSelectionAccessID = $_POST['courseSelectionAccessID'] ?? '';

    if (empty($courseSelectionAccessID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        $gateway = $container->get('CourseSelection\Domain\AccessGateway');

        $deleted = $gateway->delete($courseSelectionAccessID);

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
