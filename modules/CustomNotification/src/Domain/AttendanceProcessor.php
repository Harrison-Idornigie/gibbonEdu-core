<?php
namespace Gibbon\Module\CustomNotification\Domain;

use Gibbon\Module\CustomNotification\AttendanceListener;
use Gibbon\Domain\System\LogGateway;
use Gibbon\Domain\System\SettingGateway;
use PDO;

class AttendanceProcessor
{
    private $listener;
    private $logGateway;
    private $pdo;
    private $settingGateway;

    public function __construct(
        AttendanceListener $listener,
        LogGateway $logGateway,
        SettingGateway $settingGateway,
        PDO $pdo
    ) {
        $this->listener = $listener;
        $this->logGateway = $logGateway;
        $this->pdo = $pdo;
        $this->settingGateway = $settingGateway;
    }

    public function process(array $data = []): bool
    {
        try {
            // Get current school year
            $sql = "SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current'";
            $schoolYear = $this->pdo->query($sql)->fetch();
            $gibbonSchoolYearID = $schoolYear['gibbonSchoolYearID'] ?? null;

            // Log start of process
            $this->logGateway->addLog(
                $gibbonSchoolYearID,
                'Custom Notification',
                null,
                'Attendance Check Started'
            );

            // Get check frequency from settings
            $frequency = (int) $this->settingGateway->getSettingByScope('CustomNotification', 'attendanceCheckFrequency') ?? 5;
            
            // Check for new records
            $this->listener->checkNewAttendanceRecords($frequency);

            // Log successful completion
            $this->logGateway->addLog(
                $gibbonSchoolYearID,
                'Custom Notification',
                null,
                'Attendance Check Completed'
            );

            return true;
        } catch (\Exception $e) {
            // Log any errors
            $this->logGateway->addLog(
                $gibbonSchoolYearID ?? null,
                'Custom Notification',
                null,
                'Attendance Check Error',
                ['error' => $e->getMessage()]
            );
            return false;
        }
    }
}
