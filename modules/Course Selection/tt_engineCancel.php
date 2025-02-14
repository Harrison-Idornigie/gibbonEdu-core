<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

include '../../gibbon.php';

use CourseSelection\BackgroundProcess;
use CourseSelection\Domain\SettingsGateway;

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/tt_engine.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_engine.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $process = new BackgroundProcess($session->get('absolutePath').'/uploads/engine');
    $process->stopProcess('engine');

    header("Location: {$URL}");
    exit;

}
