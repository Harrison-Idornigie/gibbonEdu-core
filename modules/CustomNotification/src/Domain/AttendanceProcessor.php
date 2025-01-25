<?php
namespace Gibbon\Module\CustomNotification\Domain;

use PDO;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Comms\NotificationSender;
use Gibbon\Comms\SMS;
use Gibbon\Services\BackgroundProcess;

/**
 * Background processor for attendance notifications
 */
class AttendanceProcessor extends BackgroundProcess
{
    protected $pdo;
    protected $settingGateway;
    protected $studentGateway;
    protected $familyGateway;
    protected $notificationGateway;
    protected $notificationSender;
    protected $sms;
    protected $listener;

    public function __construct(
        PDO $pdo,
        SettingGateway $settingGateway,
        StudentGateway $studentGateway,
        FamilyGateway $familyGateway,
        NotificationSender $notificationSender,
        NotificationGateway $notificationGateway,
        ?SMS $sms = null
    ) {
        $this->pdo = $pdo;
        $this->settingGateway = $settingGateway;
        $this->studentGateway = $studentGateway;
        $this->familyGateway = $familyGateway;
        $this->notificationSender = $notificationSender;
        $this->notificationGateway = $notificationGateway;
        $this->sms = $sms;

        // Create attendance listener
        $this->listener = new AttendanceListener(
            $pdo,
            $notificationGateway,
            $notificationSender,
            $settingGateway,
            $sms
        );
    }

    /**
     * Process attendance notifications
     * This is called by the background processor
     * 
     * @param array $data Process data from the background processor
     * @return bool
     */
    public function process(array $data): bool
    {
        // Get check frequency from settings
        $frequency = (int) $this->settingGateway->getSettingByScope('CustomNotification', 'attendanceCheckFrequency') ?? 5;
        
        // Check for new records
        $this->listener->checkNewAttendanceRecords($frequency);

        return true;
    }
}
