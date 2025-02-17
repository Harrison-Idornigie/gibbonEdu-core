<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;

/**
 * Workflow Manager
 *
 * Handles the transfer request workflow and state transitions
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class WorkflowManager
{
    protected $pdo;
    protected $settingGateway;
    protected $transferGateway;
    protected $notificationService;

    // Define valid workflow states and transitions
    const WORKFLOW_STATES = [
        'Draft',
        'Pending Approval',
        'Approved',
        'Pending Export',
        'Exported',
        'Pending Import',
        'Imported',
        'Completed',
        'Cancelled',
        'Rejected'
    ];

    const WORKFLOW_TRANSITIONS = [
        'Draft' => ['Pending Approval', 'Cancelled'],
        'Pending Approval' => ['Approved', 'Rejected', 'Cancelled'],
        'Approved' => ['Pending Export', 'Cancelled'],
        'Pending Export' => ['Exported', 'Cancelled'],
        'Exported' => ['Pending Import', 'Cancelled'],
        'Pending Import' => ['Imported', 'Cancelled'],
        'Imported' => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
        'Rejected' => []
    ];

    public function __construct(
        Connection $pdo,
        SettingGateway $settingGateway,
        TransferGateway $transferGateway,
        NotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->settingGateway = $settingGateway;
        $this->transferGateway = $transferGateway;
        $this->notificationService = $notificationService;
    }

    /**
     * Start a new transfer workflow.
     *
     * @param array $data Transfer data
     * @param string $gibbonPersonID Person initiating the transfer
     * @return string Transfer ID
     */
    public function startTransfer($data, $gibbonPersonID)
    {
        $data['status'] = 'Draft';
        $data['gibbonPersonIDCreated'] = $gibbonPersonID;
        $data['timestampCreated'] = date('Y-m-d H:i:s');

        $transferID = $this->transferGateway->insert($data);
        
        // Log the workflow start
        $this->logWorkflowStep($transferID, 'Draft', 'Transfer initiated', $gibbonPersonID);
        
        return $transferID;
    }

    /**
     * Transition a transfer to a new state.
     *
     * @param string $transferID
     * @param string $newState
     * @param string $comment
     * @param string $gibbonPersonID Person making the transition
     * @return bool
     */
    public function transition($transferID, $newState, $comment, $gibbonPersonID)
    {
        $transfer = $this->transferGateway->getTransferByID($transferID);
        
        if (empty($transfer)) {
            throw new \Exception('Transfer not found');
        }

        // Validate the transition
        if (!$this->isValidTransition($transfer['status'], $newState)) {
            throw new \Exception('Invalid workflow transition');
        }

        // Update the transfer status
        $oldStatus = $transfer['status'];
        $this->transferGateway->update($transferID, [
            'status' => $newState,
            'timestampModified' => date('Y-m-d H:i:s')
        ]);

        // Log the transition
        $this->logWorkflowStep($transferID, $newState, $comment, $gibbonPersonID);

        // Send notifications
        $this->notificationService->sendStatusUpdateNotification(
            $transfer,
            $oldStatus,
            $newState,
            $gibbonPersonID
        );

        return true;
    }

    /**
     * Check if a state transition is valid.
     *
     * @param string $currentState
     * @param string $newState
     * @return bool
     */
    public function isValidTransition($currentState, $newState)
    {
        if (!isset(self::WORKFLOW_TRANSITIONS[$currentState])) {
            return false;
        }

        return in_array($newState, self::WORKFLOW_TRANSITIONS[$currentState]);
    }

    /**
     * Get available actions for the current state.
     *
     * @param string $currentState
     * @return array
     */
    public function getAvailableActions($currentState)
    {
        if (!isset(self::WORKFLOW_TRANSITIONS[$currentState])) {
            return [];
        }

        return self::WORKFLOW_TRANSITIONS[$currentState];
    }

    /**
     * Log a workflow step.
     *
     * @param string $transferID
     * @param string $state
     * @param string $comment
     * @param string $gibbonPersonID
     */
    protected function logWorkflowStep($transferID, $state, $comment, $gibbonPersonID)
    {
        $data = [
            'studentTransferLogID' => $transferID,
            'status' => $state,
            'comment' => $comment,
            'gibbonPersonID' => $gibbonPersonID,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $sql = "INSERT INTO gibbonStudentTransferWorkflowLog SET 
                studentTransferLogID=:studentTransferLogID,
                status=:status,
                comment=:comment,
                gibbonPersonID=:gibbonPersonID,
                timestamp=:timestamp";

        $this->pdo->insert($sql, $data);
    }

    /**
     * Get workflow history for a transfer.
     *
     * @param string $transferID
     * @return array
     */
    public function getWorkflowHistory($transferID)
    {
        $sql = "SELECT l.*, p.title, p.preferredName, p.surname 
                FROM gibbonStudentTransferWorkflowLog l
                JOIN gibbonPerson p ON (l.gibbonPersonID=p.gibbonPersonID)
                WHERE l.studentTransferLogID=:transferID
                ORDER BY l.timestamp DESC";

        return $this->pdo->select($sql, ['transferID' => $transferID])->fetchAll();
    }
}
