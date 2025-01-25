<?php
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\CustomNotification\Domain\NotificationSubscriptionGateway;

require_once __DIR__ . '/../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/notifications_subscriptions.php';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_subscriptions.php') === false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$subscriptionGateway = $container->get(NotificationSubscriptionGateway::class);
$criteria = $subscriptionGateway->newQueryCriteria()
    ->fromPOST();

$subscription = $subscriptionGateway->querySubscriptions($criteria)
    ->getRow($id);

if (empty($subscription)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

if ($subscription['gibbonPersonID'] != $session->get('gibbonPersonID')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$allowParentUnsubscribe = $settingGateway->getSettingByScope('CustomNotification', 'allowParentUnsubscribe');
$mandatoryTypes = explode(',', $settingGateway->getSettingByScope('CustomNotification', 'mandatoryNotificationTypes'));

if ($subscription['roleType'] == 'Parent' && $allowParentUnsubscribe != 'Y') {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

if (in_array($subscription['eventType'], $mandatoryTypes)) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

try {
    $subscriptionGateway->deleteSubscription($id);

    $URL .= '&return=success0';
} catch (Exception $e) {
    $URL .= '&return=error2';
}

header("Location: {$URL}");
exit;
