<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/settings.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $settingGateway = $container->get(SettingGateway::class);
    $partialFail = false;

    $settings = [
        'enableAttendanceNotifications' => $_POST['enableAttendanceNotifications'] ?? 'N',
        'allowParentUnsubscribe' => $_POST['allowParentUnsubscribe'] ?? 'N',
        'attendanceCheckFrequency' => $_POST['attendanceCheckFrequency'] ?? '5'
    ];

    // Validate the frequency
    if ($settings['attendanceCheckFrequency'] < 1 || $settings['attendanceCheckFrequency'] > 60) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Update each setting
    foreach ($settings as $name => $value) {
        $updated = $settingGateway->updateSettingByScope('CustomNotification', $name, $value);
        $partialFail &= !$updated;
    }

    // Update the background process frequency if it changed
    if ($settings['attendanceCheckFrequency'] != $settingGateway->getSettingByScope('CustomNotification', 'attendanceCheckFrequency')) {
        // The background process system will pick up the new frequency on its next run
        $sql = "UPDATE gibbonBackgroundProcess SET frequency = :frequency 
                WHERE class = 'Gibbon\\\\Module\\\\CustomNotification\\\\Domain\\\\AttendanceProcessor'";
        $pdo->executeQuery(['frequency' => '*/'.$settings['attendanceCheckFrequency'].' * * * *'], $sql);
    }

    $URL .= $partialFail
        ? '&return=error2'
        : '&return=success0';
    header("Location: {$URL}");
}
