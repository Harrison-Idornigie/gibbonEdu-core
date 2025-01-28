<?php
namespace Gibbon\Module\CustomNotification\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\CustomNotification\AttendanceListener;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\System\LogGateway;
use Gibbon\Comms\NotificationSender;
use Gibbon\Services\LoggerFactory;
use PDO;
use PDOStatement;
use Exception;

class AttendanceListenerTest extends TestCase
{
    private $pdo;
    private $settingGateway;
    private $notificationSender;
    private $logGateway;
    private $loggerFactory;
    private $logger;
    private $attendanceListener;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->notificationSender = $this->createMock(NotificationSender::class);
        $this->logGateway = $this->createMock(LogGateway::class);
        $this->loggerFactory = $this->createMock(LoggerFactory::class);
        $this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->loggerFactory->method('getLogger')
            ->willReturn($this->logger);

        $this->attendanceListener = new AttendanceListener(
            $this->pdo,
            $this->settingGateway,
            $this->notificationSender,
            $this->logGateway,
            $this->loggerFactory
        );
    }

    public function testCheckNewAttendanceRecordsWhenDisabled(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->with('CustomNotification', 'enableAttendanceNotifications')
            ->willReturn('N');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Starting attendance check', ['minutesBack' => 5]);

        $this->attendanceListener->checkNewAttendanceRecords();
    }

    public function testCheckNewAttendanceRecordsWithNoAbsences(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['CustomNotification', 'enableAttendanceNotifications', 'Y'],
                ['System', 'gibbonSchoolYearID', '1']
            ]);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')
            ->willReturn($stmt);

        $this->logGateway->expects($this->once())
            ->method('addLog')
            ->with(
                '1',
                'CustomNotification',
                null,
                'Attendance Check Summary',
                ['summary' => 'Absent Students: 0, Emails Sent: 0, SMS Sent: 0']
            );

        $this->attendanceListener->checkNewAttendanceRecords();
    }

    public function testCheckNewAttendanceRecordsWithAbsences(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->willReturnMap([
                ['CustomNotification', 'enableAttendanceNotifications', 'Y'],
                ['System', 'gibbonSchoolYearID', '1']
            ]);

        // Mock attendance records query
        $attendanceStmt = $this->createMock(PDOStatement::class);
        $attendanceStmt->method('execute')->willReturn(true);
        $attendanceStmt->method('rowCount')->willReturn(1);
        $attendanceStmt->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'gibbonPersonID' => '1',
                'preferredName' => 'John',
                'surname' => 'Doe',
                'date' => '2025-01-26',
                'type' => 'Absent',
                'reason' => 'Sick',
                'comment' => 'Called in sick'
            ],
            false
        );

        // Mock event query
        $eventStmt = $this->createMock(PDOStatement::class);
        $eventStmt->method('execute')->willReturn(true);
        $eventStmt->method('fetch')->willReturn([
            'id' => '1',
            'name' => 'attendance',
            'active' => 'Y',
            'template' => '[studentName] was marked [type] on [date]. Reason: [reason]. Comment: [comment]'
        ]);

        // Mock subscribers query
        $subscribersStmt = $this->createMock(PDOStatement::class);
        $subscribersStmt->method('execute')->willReturn(true);
        $subscribersStmt->method('fetchAll')->willReturn([
            [
                'gibbonPersonID' => '2',
                'preferredName' => 'Parent',
                'surname' => 'One',
                'notificationType' => 'email'
            ]
        ]);

        // Mock log insert
        $logStmt = $this->createMock(PDOStatement::class);
        $logStmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')
            ->willReturnOnConsecutiveCalls($attendanceStmt, $eventStmt, $subscribersStmt, $logStmt);

        $this->notificationSender->expects($this->once())
            ->method('addNotification');

        $this->logGateway->expects($this->once())
            ->method('addLog')
            ->with(
                '1',
                'CustomNotification',
                null,
                'Attendance Check Summary',
                ['summary' => 'Absent Students: 1, Emails Sent: 1, SMS Sent: 0']
            );

        $this->attendanceListener->checkNewAttendanceRecords();
    }

    public function testCheckNewAttendanceRecordsWithError(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->willReturn('Y');

        $this->pdo->method('prepare')
            ->willThrowException(new Exception('Database error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->attendanceListener->checkNewAttendanceRecords();
    }
}
