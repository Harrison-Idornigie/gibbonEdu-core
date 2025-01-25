<?php
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\CustomNotification\Domain\NotificationSubscriptionGateway;

require_once __DIR__ . '/../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/notifications_subscriptions.php';

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_subscriptions.php') === false) {
    // Access denied
    $URL = $session->get('absoluteURL').'/index.php';
    header("Location: {$URL}");
    exit;
}

// Validate inputs
if (empty($_POST['gibbonPersonID']) || empty($_POST['eventType']) || empty($_POST['notifyBy'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$gibbonPersonID = $_POST['gibbonPersonID'];
$eventType = $_POST['eventType'];
$notifyBy = $_POST['notifyBy'];
$studentID = !empty($_POST['studentID']) ? $_POST['studentID'] : null;

// Check if this is a parent and if they're allowed to unsubscribe
$settingGateway = $container->get(SettingGateway::class);
$allowParentUnsubscribe = $settingGateway->getSettingByScope('CustomNotification', 'allowParentUnsubscribe');
$mandatoryTypes = explode(',', $settingGateway->getSettingByScope('CustomNotification', 'mandatoryNotificationTypes'));

if (isParent($session->get('gibbonRoleIDPrimary'))) {
    if ($allowParentUnsubscribe == 'N' && in_array($eventType, $mandatoryTypes)) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    // Check if this parent has access to this student
    if (!empty($studentID)) {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'studentID' => $studentID
        ];
        $sql = "SELECT COUNT(*) FROM gibbonFamilyChild 
                JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
                JOIN gibbonFamilyAdult ON (gibbonFamilyAdult.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
                WHERE gibbonFamilyAdult.gibbonPersonID=:gibbonPersonID 
                AND gibbonFamilyChild.gibbonPersonID=:studentID";
        $result = $pdo->selectOne($sql, $data);
        
        if ($result['COUNT(*)'] < 1) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }
    }
}

// Check if event type exists and is active
$data = ['eventType' => $eventType];
$sql = "SELECT COUNT(*) FROM CustomNotificationEvent WHERE name=:eventType AND active='Y'";
$result = $pdo->selectOne($sql, $data);

if ($result['COUNT(*)'] < 1) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Check if subscription already exists
$subscriptionGateway = $container->get(NotificationSubscriptionGateway::class);

try {
    // Prepare subscription data
    $data = [
        'gibbonPersonID' => $gibbonPersonID,
        'eventType' => $eventType,
        'notifyBy' => $notifyBy,
        'studentID' => $studentID,
        'active' => 'Y'
    ];

    // Insert new subscription
    $subscriptionGateway->insertSubscription($data);

    // Success
    $URL .= '&return=success0';
} catch (Exception $e) {
    $URL .= '&return=error2';
}

header("Location: {$URL}");
