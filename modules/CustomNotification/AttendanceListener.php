<?php
namespace Gibbon\Module\CustomNotification\Domain;

use PDO;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\DataSet;
use Gibbon\Comms\SMS;

/**
 * Attendance Listener Class
 * Handles attendance notifications by monitoring the gibbonAttendanceLogPerson table
 */
class AttendanceListener
{
    protected $pdo;
    protected $notificationGateway;
    protected $notificationSender;
    protected $settingGateway;
    protected $sms;

    public function __construct(
        PDO $pdo,
        NotificationGateway $notificationGateway,
        NotificationSender $notificationSender,
        SettingGateway $settingGateway,
        SMS $sms
    ) {
        $this->pdo = $pdo;
        $this->notificationGateway = $notificationGateway;
        $this->notificationSender = $notificationSender;
        $this->settingGateway = $settingGateway;
        $this->sms = $sms;
    }

    /**
     * Check for new attendance records and send notifications
     * @param int $minutesBack How far back to check for new records
     * @return void
     */
    public function checkNewAttendanceRecords(int $minutesBack = 5): void
    {
        // Check if notifications are enabled
        if ($this->settingGateway->getSettingByScope('CustomNotification', 'enableAttendanceNotifications') != 'Y') {
            error_log("[CustomNotification] Notifications are disabled");
            return;
        }

        // Get new absence records
        $sql = "SELECT gibbonAttendanceLogPerson.*, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.gibbonPersonID
                FROM gibbonAttendanceLogPerson 
                JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=gibbonAttendanceLogPerson.gibbonPersonID 
                WHERE timestampTaken > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                AND type='Absent'
                AND gibbonAttendanceLogPerson.context IN ('Class', 'Form Group')";

        error_log("[CustomNotification] Checking for absences in last $minutesBack minutes");
        
        $result = $this->pdo->prepare($sql);
        $result->execute(['minutes' => $minutesBack]);

        while ($absence = $result->fetch()) {
            error_log("[CustomNotification] Found absence for student {$absence['preferredName']} {$absence['surname']}");
            $this->notifyAbsence($absence);
        }
    }

    /**
     * Send notifications for a single absence
     * @param array $absence The absence record
     */
    protected function notifyAbsence(array $absence): void
    {
        error_log("[CustomNotification] Processing notification for absence {$absence['gibbonAttendanceLogPersonID']}");

        $subscribers = [];
        $allowParentUnsubscribe = $this->settingGateway->getSettingByScope('CustomNotification', 'allowParentUnsubscribe');

        if ($allowParentUnsubscribe == 'Y') {
            // Get notification subscribers
            $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, 
                           CustomNotificationSubscription.notifyBy
                    FROM CustomNotificationSubscription 
                    JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=CustomNotificationSubscription.gibbonPersonID
                    WHERE CustomNotificationSubscription.eventType='attendance'
                    AND CustomNotificationSubscription.active='Y'
                    AND (CustomNotificationSubscription.studentID IS NULL 
                         OR CustomNotificationSubscription.studentID=:studentID)";

            $result = $this->pdo->prepare($sql);
            $result->execute(['studentID' => $absence['gibbonPersonID']]);
            $subscribers = $result->fetchAll();
        }

        // Always get parents if parent unsubscribe is not allowed
        if ($allowParentUnsubscribe == 'N') {
            $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, 
                           gibbonPerson.email, 'Email' as notifyBy
                    FROM gibbonFamilyChild
                    JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
                    JOIN gibbonFamilyAdult ON (gibbonFamilyAdult.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
                    JOIN gibbonPerson ON (gibbonFamilyAdult.gibbonPersonID=gibbonPerson.gibbonPersonID)
                    WHERE gibbonFamilyChild.gibbonPersonID=:gibbonPersonID
                    AND gibbonPerson.status='Full'
                    AND gibbonPerson.email <> ''";
            
            $result = $this->pdo->prepare($sql);
            $result->execute(['gibbonPersonID' => $absence['gibbonPersonID']]);
            
            while ($parent = $result->fetch()) {
                $subscribers[] = $parent;
            }
        }

        if (empty($subscribers)) {
            error_log("[CustomNotification] No subscribers found for absence notification");
            return;
        }

        // Create the notification
        $text = sprintf(
            'Student %s %s has been marked absent from class.',
            $absence['preferredName'],
            $absence['surname']
        );

        // Add notification for each subscriber
        foreach ($subscribers as $subscriber) {
            $this->notificationSender->addNotification(
                $subscriber['gibbonPersonID'],
                sprintf('Your child %s %s has been marked absent.', 
                    $absence['preferredName'], 
                    $absence['surname']
                ),
                'CustomNotification',
                '/modules/Attendance/attendance_take_byPerson.php'
            );
        }

        // Send notifications
        $this->notificationSender->sendNotifications();

        // Send to each subscriber based on their preference
        foreach ($subscribers as $subscriber) {
            error_log("[CustomNotification] Sending notification to subscriber {$subscriber['gibbonPersonID']}");

            // If SMS notification is requested, send via SMS class
            if ($subscriber['notifyBy'] == 'SMS' || $subscriber['notifyBy'] == 'Both') {
                error_log("[CustomNotification] Attempting to send SMS to subscriber {$subscriber['gibbonPersonID']}");
                try {
                    $this->sms->to($subscriber['gibbonPersonID'])->content($text)->send();
                    
                    // Log successful SMS
                    $sql = "INSERT INTO CustomNotificationLog 
                            (eventType, recipientType, recipientID, notificationType, status, message) 
                            VALUES 
                            ('attendance', 'Parent', :recipientID, 'SMS', 'Sent', :message)";
                    $this->pdo->prepare($sql)->execute([
                        'recipientID' => $subscriber['gibbonPersonID'],
                        'message' => $text
                    ]);
                } catch (\Exception $e) {
                    // Log failed SMS
                    $sql = "INSERT INTO CustomNotificationLog 
                            (eventType, recipientType, recipientID, notificationType, status, message, error) 
                            VALUES 
                            ('attendance', 'Parent', :recipientID, 'SMS', 'Failed', :message, :error)";
                    $this->pdo->prepare($sql)->execute([
                        'recipientID' => $subscriber['gibbonPersonID'],
                        'message' => $text,
                        'error' => $e->getMessage()
                    ]);
                    error_log("[CustomNotification] SMS Error: " . $e->getMessage());
                }
            }
        }
    }
}
