<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Services\Format;

/**
 * Notification Service
 *
 * Handles automated notifications for the Student Transfer module
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class NotificationService
{
    protected $pdo;
    protected $notificationGateway;
    protected $userGateway;
    protected $settingGateway;

    public function __construct(
        Connection $pdo,
        NotificationGateway $notificationGateway,
        UserGateway $userGateway,
        SettingGateway $settingGateway
    ) {
        $this->pdo = $pdo;
        $this->notificationGateway = $notificationGateway;
        $this->userGateway = $userGateway;
        $this->settingGateway = $settingGateway;
    }

    /**
     * Send a notification to specified users
     *
     * @param array $users Array of gibbonPersonIDs
     * @param string $text Notification text
     * @param string $moduleName Module name
     * @param string $actionLink Action link
     */
    protected function sendNotification($users, $text, $moduleName, $actionLink)
    {
        foreach ($users as $user) {
            $data = [
                'gibbonPersonID' => $user['gibbonPersonID'],
                'text' => $text,
                'moduleName' => $moduleName,
                'actionLink' => $actionLink
            ];

            $this->notificationGateway->insert($data);
        }
    }

    /**
     * Send a transfer request notification.
     *
     * @param array $transfer Transfer details
     * @param string $actionBy gibbonPersonID of the user initiating the action
     */
    public function sendTransferRequestNotification($transfer, $actionBy)
    {
        // Get users with permission to handle transfers
        $users = $this->getUsersByPermission('Student Transfer_manage');

        $actionByUser = $this->userGateway->getByID($actionBy);
        $student = $this->userGateway->getByID($transfer['gibbonPersonID']);
        
        $text = sprintf(
            __('New student transfer request for %s initiated by %s.'),
            Format::name('', $student['preferredName'], $student['surname'], 'Student'),
            Format::name('', $actionByUser['preferredName'], $actionByUser['surname'], 'Staff')
        );

        $this->sendNotification($users, $text, 'Student Transfer', 'transfer_manage.php');
    }

    /**
     * Send a transfer status update notification.
     *
     * @param array $transfer Transfer details
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param string $actionBy gibbonPersonID of the user making the change
     */
    public function sendStatusUpdateNotification($transfer, $oldStatus, $newStatus, $actionBy)
    {
        $actionByUser = $this->userGateway->getByID($actionBy);
        $student = $this->userGateway->getByID($transfer['gibbonPersonID']);

        // Notify relevant staff members
        $users = $this->getUsersByPermission('Student Transfer_manage');
        
        $text = sprintf(
            __('Student transfer status for %s changed from %s to %s by %s.'),
            Format::name('', $student['preferredName'], $student['surname'], 'Student'),
            __($oldStatus),
            __($newStatus),
            Format::name('', $actionByUser['preferredName'], $actionByUser['surname'], 'Staff')
        );

        $this->sendNotification($users, $text, 'Student Transfer', 'transfer_manage.php');
    }

    /**
     * Send a transfer package expiry notification.
     *
     * @param array $transfer Transfer details
     */
    public function sendExpiryNotification($transfer)
    {
        $student = $this->userGateway->getByID($transfer['gibbonPersonID']);
        $users = $this->getUsersByPermission('Student Transfer_manage');

        $text = sprintf(
            __('Transfer package for %s will expire in 24 hours.'),
            Format::name('', $student['preferredName'], $student['surname'], 'Student')
        );

        $this->sendNotification($users, $text, 'Student Transfer', 'transfer_manage.php');
    }

    /**
     * Send a batch transfer completion notification.
     *
     * @param array $results Batch transfer results
     * @param string $actionBy gibbonPersonID of the user who initiated the batch
     */
    public function sendBatchCompletionNotification($results, $actionBy)
    {
        $actionByUser = $this->userGateway->getByID($actionBy);
        $users = $this->getUsersByPermission('Student Transfer_manage');

        $text = sprintf(
            __('Batch transfer completed by %s. Success: %d, Failed: %d'),
            Format::name('', $actionByUser['preferredName'], $actionByUser['surname'], 'Staff'),
            $results['success'],
            $results['failed']
        );

        $this->sendNotification($users, $text, 'Student Transfer', 'transfer_manage.php');
    }

    /**
     * Get users with a specific permission.
     *
     * @param string $action The action to check permission for
     * @return array Array of user IDs
     */
    protected function getUsersByPermission($action)
    {
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID 
                FROM gibbonPerson 
                JOIN gibbonRole ON (FIND_IN_SET(gibbonRole.gibbonRoleID, gibbonPerson.gibbonRoleIDAll))
                JOIN gibbonPermission ON (gibbonRole.gibbonRoleID=gibbonPermission.gibbonRoleID)
                JOIN gibbonAction ON (gibbonPermission.gibbonActionID=gibbonAction.gibbonActionID)
                WHERE gibbonAction.name=:action 
                AND gibbonPerson.status='Full'";

        return $this->pdo->select($sql, ['action' => $action])->fetchAll();
    }
}
