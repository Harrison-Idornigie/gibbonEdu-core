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
    public function sendNotification($users, $text, $moduleName, $actionLink)
    {
        if (empty($users)) return;

        // Get module ID
        $sql = "SELECT gibbonModuleID FROM gibbonModule WHERE name=:name";
        $moduleID = $this->pdo->selectOne($sql, ['name' => $moduleName]);

        // Create notifications
        foreach ($users as $gibbonPersonID) {
            $data = [
                'gibbonPersonID' => $gibbonPersonID,
                'text' => $text,
                'actionLink' => $actionLink,
                'gibbonModuleID' => $moduleID['gibbonModuleID'] ?? null,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'New'
            ];

            $fields = implode(',', array_keys($data));
            $values = ':'.implode(',:', array_keys($data));
            $sql = "INSERT INTO gibbonNotification ($fields) VALUES ($values)";
            $this->pdo->insert($sql, $data);
        }
    }

    /**
     * Send a transfer request notification.
     *
     * @param string $transferID
     * @param string $type upload|import|approve|reject
     * @param array $data Additional data for notification
     */
    public function sendTransferNotification($transferID, $type, array $data = [])
    {
        // Get staff with Student Transfer access
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonRole.category 
                FROM gibbonPerson 
                JOIN gibbonRole ON (gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary)
                JOIN gibbonPermission ON (gibbonPermission.gibbonRoleID=gibbonRole.gibbonRoleID)
                JOIN gibbonAction ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID)
                WHERE gibbonAction.name='Manage Student Transfers'
                AND gibbonAction.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Student Transfer')
                AND gibbonPerson.status='Full'";
        
        $staff = $this->pdo->select($sql)->fetchAll();
        if (empty($staff)) return;

        $actionLink = '/index.php?q=/modules/Student Transfer/transfer_manage.php';
        $moduleName = 'Student Transfer';

        switch ($type) {
            case 'upload':
                $text = __('A new student transfer package has been uploaded.');
                if (!empty($data['schoolName'])) {
                    $text .= ' '.__('From').': '.$data['schoolName'];
                }
                break;

            case 'import':
                $text = __('A student transfer has been imported.');
                if (!empty($data['applicationID'])) {
                    $actionLink = '/index.php?q=/modules/Students/applicationForm_manage_edit.php&gibbonApplicationFormID='.$data['applicationID'];
                }
                break;

            case 'approve':
                $text = __('A student transfer has been approved.');
                break;

            case 'reject':
                $text = __('A student transfer has been rejected.');
                if (!empty($data['reason'])) {
                    $text .= ' '.__('Reason').': '.$data['reason'];
                }
                break;

            default:
                return;
        }

        // Send to all staff with access
        $users = array_column($staff, 'gibbonPersonID');
        $this->sendNotification($users, $text, $moduleName, $actionLink);

        // Additional notifications for specific roles
        foreach ($staff as $member) {
            if ($member['category'] == 'Staff' && $type == 'import') {
                // Send additional details to admin staff
                $detailedText = __('Please review the imported student application.');
                $this->sendNotification(
                    [$member['gibbonPersonID']], 
                    $detailedText,
                    $moduleName,
                    $actionLink
                );
            }
        }
    }

    /**
     * Notify previous school about transfer status.
     *
     * @param string $transferID
     * @param string $status
     * @param string $message
     */
    public function notifyPreviousSchool($transferID, $status, $message = '')
    {
        // Get transfer record
        $sql = "SELECT * FROM gibbonStudentTransferLog WHERE gibbonStudentTransferLogID=:transferID";
        $transfer = $this->pdo->selectOne($sql, ['transferID' => $transferID]);
        if (empty($transfer)) return;

        // Get notification settings
        $notifyPreviousSchool = $this->settingGateway->getSettingByScope(
            'Student Transfer',
            'notifyPreviousSchool'
        );

        if ($notifyPreviousSchool != 'Y') return;

        // Get email template
        $template = $this->getEmailTemplate($status);
        if (empty($template)) return;

        // Replace placeholders
        $template = str_replace('{school_name}', $transfer['schoolNameTo'], $template);
        $template = str_replace('{status}', $status, $template);
        $template = str_replace('{message}', $message, $template);

        // Send email
        // Note: This would need to be implemented based on your email system
        // For now, we'll just log it
        error_log("Student Transfer: Would send email to {$transfer['schoolNameFrom']}");
        error_log("Subject: Student Transfer Status Update");
        error_log("Body: $template");
    }

    /**
     * Get email template for status.
     *
     * @param string $status
     * @return string
     */
    protected function getEmailTemplate($status)
    {
        $templates = [
            'Imported' => "Dear {school_name},\n\nThe student transfer package has been successfully imported.\n\n{message}",
            'Approved' => "Dear {school_name},\n\nThe student transfer has been approved.\n\n{message}",
            'Rejected' => "Dear {school_name},\n\nThe student transfer has been rejected.\n\nReason: {message}"
        ];

        return $templates[$status] ?? '';
    }
}
