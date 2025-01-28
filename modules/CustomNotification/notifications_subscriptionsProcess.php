<?php
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\CustomNotification\Domain\NotificationSubscriptionGateway;

include __DIR__ . '/../../gibbon.php';

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

try {
    // Get role info
    $data = ['gibbonRoleID' => $session->get('gibbonRoleIDPrimary')];
    $sql = "SELECT gibbonRole.category 
            FROM gibbonRole 
            WHERE gibbonRole.gibbonRoleID=:gibbonRoleID";
    $result = $connection2->prepare($sql);
    $result->execute($data);
    
    if ($result && $result->rowCount() > 0) {
        $role = $result->fetch();
        
        if ($role['category'] == 'Parent') {
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
                $sql = "SELECT COUNT(*) as count FROM gibbonFamilyChild 
                        JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
                        JOIN gibbonFamilyAdult ON (gibbonFamilyAdult.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
                        WHERE gibbonFamilyAdult.gibbonPersonID=:gibbonPersonID 
                        AND gibbonFamilyChild.gibbonPersonID=:studentID";
                $result = $connection2->prepare($sql);
                $result->execute($data);
                $count = $result->fetch()['count'];
                
                if ($count < 1) {
                    $URL .= '&return=error0';
                    header("Location: {$URL}");
                    exit;
                }
            }
        }
    }

    // Handle student-specific subscriptions for attendance
    if ($eventType == 'attendance') {
        $studentIDs = is_array($_POST['studentID']) ? $_POST['studentID'] : [];
        
        try {
            if (!empty($studentIDs)) {
                foreach ($studentIDs as $targetID) {
                    $data = [
                        'gibbonPersonID' => $gibbonPersonID,
                        'targetPersonID' => $targetID,
                        'eventType' => $eventType,
                        'notificationType' => $notifyBy,
                        'active' => 'Y'
                    ];
                    
                    $sql = "INSERT INTO CustomNotificationSubscription 
                            (gibbonPersonID, targetPersonID, eventType, notificationType, active) 
                            VALUES 
                            (:gibbonPersonID, :targetPersonID, :eventType, :notificationType, :active)";
                    
                    $connection2->prepare($sql)->execute($data);
                }
            } else {
                // Subscribe to all students
                $data = [
                    'gibbonPersonID' => $gibbonPersonID,
                    'eventType' => $eventType,
                    'notificationType' => $notifyBy,
                    'active' => 'Y'
                ];
                
                $sql = "INSERT INTO CustomNotificationSubscription 
                        (gibbonPersonID, eventType, notificationType, active) 
                        VALUES 
                        (:gibbonPersonID, :eventType, :notificationType, :active)";
                
                $connection2->prepare($sql)->execute($data);
            }
        } catch (Exception $e) {
            error_log('CustomNotification Error: ' . $e->getMessage());
            throw $e;
        }
    } else {
        // Update or insert subscription
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'eventType' => $eventType,
            'notificationType' => $notifyBy,
            'targetPersonID' => $studentID,
            'active' => 'Y'
        ];

        $sql = "INSERT INTO CustomNotificationSubscription 
                (gibbonPersonID, eventType, notificationType, targetPersonID, active) 
                VALUES 
                (:gibbonPersonID, :eventType, :notificationType, :targetPersonID, :active)
                ON DUPLICATE KEY UPDATE 
                notificationType=:notificationType, active=:active";
        
        $connection2->prepare($sql)->execute($data);
    }

    $URL .= "&return=success0";
} catch (Exception $e) {
    error_log('CustomNotification Error: ' . $e->getMessage());
    $URL .= "&return=error2";
}

header("Location: {$URL}");
exit;
