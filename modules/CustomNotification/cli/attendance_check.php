<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Gibbon Foundation
*/

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/attendance_check_errors.log');

use Gibbon\Services\BackgroundProcessor;
use Gibbon\Module\CustomNotification\Domain\AttendanceProcessor;

require __DIR__ . '/../../../gibbon.php';

try {
    // Start the attendance check process
    $processor = $container->get(BackgroundProcessor::class);
    $result = $processor->startProcess(AttendanceProcessor::class, 'process', []);
    
    // Log the result
    error_log("Attendance check completed. Result: " . ($result ? 'success' : 'failed'));
} catch (Exception $e) {
    error_log("Error in attendance check: " . $e->getMessage());
    throw $e;
}
