<?php
namespace Gibbon\Module\CustomNotification;

use PDO;
use Exception;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\System\LogGateway;
use Gibbon\Services\LoggerFactory;
use Gibbon\Services\Format;

class AttendanceListener
{
    private $pdo;
    private $settingGateway;
    private $notificationSender;
    private $logGateway;
    private $logger;

    public function __construct(
        PDO $pdo,
        SettingGateway $settingGateway,
        NotificationSender $notificationSender,
        LogGateway $logGateway,
        LoggerFactory $loggerFactory
    ) {
        $this->pdo = $pdo;
        $this->settingGateway = $settingGateway;
        $this->notificationSender = $notificationSender;
        $this->logGateway = $logGateway;
        $this->logger = $loggerFactory->getLogger('CustomNotification');
    }

    public function checkNewAttendanceRecords(int $minutesBack = 5): void
    {
        $this->logger->info('Starting attendance check', ['minutesBack' => $minutesBack]);
        
        try {
            // Check if notifications are enabled
            if ($this->settingGateway->getSettingByScope('CustomNotification', 'enableAttendanceNotifications') != 'Y') {
                return;
            }

            // Initialize counters
            $absentCount = 0;
            $emailCount = 0;
            $smsCount = 0;

            // Get new absence records
            $cutoff = date('Y-m-d H:i:s', strtotime("-$minutesBack minutes"));
            $this->logger->debug('Checking for records after', ['cutoff' => $cutoff]);

            $data = ['cutoff' => $cutoff, 'type' => 'Absent'];
            $sql = "SELECT DISTINCT gibbonAttendanceLogPerson.*, gibbonPerson.preferredName, gibbonPerson.surname,
                    gibbonAttendanceCode.scope 
                    FROM gibbonAttendanceLogPerson 
                    JOIN gibbonPerson ON (gibbonAttendanceLogPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
                    JOIN gibbonAttendanceCode ON (gibbonAttendanceLogPerson.type=gibbonAttendanceCode.name) 
                    WHERE gibbonAttendanceLogPerson.timestampTaken >= :cutoff 
                    AND gibbonAttendanceLogPerson.type=:type";
            
            $result = $this->pdo->prepare($sql);
            $result->execute($data);
            
            $this->logger->info('Found new absence records', ['count' => $result->rowCount()]);
            
            while ($absence = $result->fetch()) {
                $absentCount++;
                $this->logger->debug('Processing absence', [
                    'student' => $absence['preferredName'] . ' ' . $absence['surname'],
                    'date' => $absence['date']
                ]);
                
                list($emails, $sms) = $this->notifyAbsence($absence);
                $emailCount += $emails;
                $smsCount += $sms;
            }

            // Log summary to both system log and logger
            $summary = "Absent Students: $absentCount, Emails Sent: $emailCount, SMS Sent: $smsCount";
            $this->logger->info('Attendance check summary', [
                'absentCount' => $absentCount,
                'emailCount' => $emailCount,
                'smsCount' => $smsCount
            ]);
            
            $this->logGateway->addLog(
                $this->settingGateway->getSettingByScope('System', 'gibbonSchoolYearID'),
                'CustomNotification',
                null,
                'Attendance Check Summary',
                ['summary' => $summary]
            );
        } catch (Exception $e) {
            $this->logger->error('Error checking attendance', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    private function notifyAbsence(array $absence): array
    {
        $emailCount = 0;
        $smsCount = 0;
        
        try {
            // Get notification event
            $sql = "SELECT * FROM CustomNotificationEvent WHERE name='attendance' AND active='Y'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($event)) {
                $this->logger->warning('No active attendance notification event found');
                return [$emailCount, $smsCount];
            }
            
            $this->logger->debug('Processing notification event', ['eventId' => $event['id']]);

            // Get subscribers for this student
            $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname,
                    CustomNotificationSubscription.notificationType
                    FROM CustomNotificationSubscription 
                    JOIN gibbonPerson ON (CustomNotificationSubscription.gibbonPersonID=gibbonPerson.gibbonPersonID)
                    WHERE CustomNotificationSubscription.eventType='attendance'
                    AND (CustomNotificationSubscription.targetPersonID=:studentID 
                         OR CustomNotificationSubscription.targetPersonID IS NULL)
                    AND gibbonPerson.status='Full'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['studentID' => $absence['gibbonPersonID']]);
            $subscribers = $stmt->fetchAll();

            if (empty($subscribers)) {
                $this->logger->info('No subscribers found', ['studentId' => $absence['gibbonPersonID']]);
                return [$emailCount, $smsCount];
            }

            // Replace placeholders in template
            $template = $event['template'];
            $template = str_replace('[studentName]', Format::name('', $absence['preferredName'], $absence['surname'], 'Student'), $template);
            $template = str_replace('[date]', Format::date($absence['date']), $template);
            $template = str_replace('[type]', $absence['type'], $template);
            $template = str_replace('[reason]', $absence['reason'] ?? '', $template);
            $template = str_replace('[comment]', $absence['comment'] ?? '', $template);
            
            $this->logger->debug('Sending notification for student', [
                'student' => $absence['preferredName'] . ' ' . $absence['surname'],
                'date' => $absence['date']
            ]);
            $this->logger->debug('Notification type', ['type' => $absence['type']]);

            // Send notifications to all subscribers
            foreach ($subscribers as $subscriber) {
                $this->notificationSender->addNotification(
                    $subscriber['gibbonPersonID'],
                    'Attendance',
                    'Custom Notification',
                    $template
                );
                
                // Count notifications by type
                if ($subscriber['notificationType'] === 'email') {
                    $emailCount++;
                } elseif ($subscriber['notificationType'] === 'sms') {
                    $smsCount++;
                }
                
                $this->logger->debug('Added notification', [
                    'type' => $subscriber['notificationType'],
                    'subscriberId' => $subscriber['gibbonPersonID']
                ]);
                
                // Insert into log
                $stmt = $this->pdo->prepare("INSERT INTO CustomNotificationLog 
                        (gibbonPersonID, eventType, notificationType, status, timestamp) 
                        VALUES 
                        (:gibbonPersonID, :eventType, :notificationType, :status, :timestamp)");
                
                $stmt->execute([
                    'gibbonPersonID' => $subscriber['gibbonPersonID'],
                    'eventType' => 'attendance',
                    'notificationType' => $subscriber['notificationType'],
                    'status' => 'sent',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $this->logger->debug('Added entry to CustomNotificationLog', ['subscriberId' => $subscriber['gibbonPersonID']]);
            }
            
            return [$emailCount, $smsCount];
        } catch (Exception $e) {
            $this->logger->error('Error sending notification', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
