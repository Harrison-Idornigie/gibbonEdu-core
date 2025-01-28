<?php
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Data\Validator;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../lib/phpmailer/PHPMailerAutoload.php';

$session = $container->get('session');

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_subscribeStudents.php') == false) {
    // Access denied
    $URL = $session->get('absoluteURL').'/index.php';
    header("Location: {$URL}");
    exit;
}

// Sanitize input
$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
$targetPersonID = $_POST['targetPersonID'] ?? '';
$action = $_POST['action'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/notifications_subscribeStudents.php';

// Validate inputs
if (empty($gibbonPersonID) || empty($targetPersonID) || empty($action)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    if ($action == 'subscribe') {
        // Check if already subscribed
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'targetPersonID' => $targetPersonID,
            'eventType' => 'attendance'
        ];
        
        $sql = "SELECT COUNT(*) FROM CustomNotificationSubscription 
                WHERE gibbonPersonID=:gibbonPersonID 
                AND targetPersonID=:targetPersonID 
                AND eventType=:eventType";
        
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        if ($result->fetchColumn() > 0) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Add subscription
        $data['notificationType'] = 'Email';
        $sql = "INSERT INTO CustomNotificationSubscription 
                (gibbonPersonID, targetPersonID, eventType, notificationType) 
                VALUES 
                (:gibbonPersonID, :targetPersonID, :eventType, :notificationType)";
        
        $result = $connection2->prepare($sql);
        $result->execute($data);

    } else if ($action == 'unsubscribe') {
        // Remove subscription
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'targetPersonID' => $targetPersonID,
            'eventType' => 'attendance'
        ];
        
        $sql = "DELETE FROM CustomNotificationSubscription 
                WHERE gibbonPersonID=:gibbonPersonID 
                AND targetPersonID=:targetPersonID 
                AND eventType=:eventType";
        
        $result = $connection2->prepare($sql);
        $result->execute($data);
    }

    $URL .= '&return=success0';
} catch (PDOException $e) {
    $URL .= '&return=error2';
}

header("Location: {$URL}");
