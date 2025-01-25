<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Services\BackgroundProcessor;
use Gibbon\Module\CustomNotification\Domain\AttendanceProcessor;

require __DIR__ . '/../../../gibbon.php';

// Start the attendance check process
$processor = $container->get(BackgroundProcessor::class);
$processor->startProcess(AttendanceProcessor::class, 'process', []);
