<?php
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/notifications_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_manage.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Proceed!
$settingGateway = $container->get(SettingGateway::class);

$settings = [
    'enableAttendanceNotifications' => $_POST['enableAttendanceNotifications'] ?? 'N',
    'enableStudentAttendanceNotifications' => $_POST['enableStudentAttendanceNotifications'] ?? 'N',
    'allowParentUnsubscribe' => $_POST['allowParentUnsubscribe'] ?? 'N',
    'attendanceCheckFrequency' => $_POST['attendanceCheckFrequency'] ?? '5',
    'mandatoryNotificationTypes' => $_POST['mandatoryTypes'] ?? ''
];

// Validate attendance check frequency
if ($settings['attendanceCheckFrequency'] < 1 || $settings['attendanceCheckFrequency'] > 60) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Update each setting
$failed = false;
foreach ($settings as $name => $value) {
    $updated = $settingGateway->updateSettingByScope('CustomNotification', $name, $value);
    if (!$updated) {
        $failed = true;
    }
}

// Return based on success/failure
$URL .= $failed ? '&return=error2' : '&return=success0';
header("Location: {$URL}");
