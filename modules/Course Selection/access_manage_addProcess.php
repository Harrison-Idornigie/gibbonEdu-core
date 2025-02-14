<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Services\Format;
use CourseSelection\Domain\AccessGateway;

require_once '../../gibbon.php';

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/access_manage_addEdit.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/access_manage_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $data = array();
    $data['gibbonSchoolYearID'] = $_POST['gibbonSchoolYearID'] ?? '';
    $data['dateStart'] = $_POST['dateStart'] ?? '';
    $data['dateEnd'] = $_POST['dateEnd'] ?? '';
    $data['accessType'] = $_POST['accessType'] ?? '';
    $data['gibbonRoleIDList'] = $_POST['gibbonRoleIDList'] ?? array();

    $data['dateStart'] = Format::dateConvert($data['dateStart']);
    $data['dateEnd'] = Format::dateConvert($data['dateEnd']);
    $data['gibbonRoleIDList'] = implode(',', $data['gibbonRoleIDList']);

    if (empty($data['gibbonSchoolYearID']) || empty($data['dateStart']) || empty($data['dateEnd']) || empty($data['accessType']) || empty($data['gibbonRoleIDList'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {

        $gateway = $container->get('CourseSelection\Domain\AccessGateway');

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
