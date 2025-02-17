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
 * Retention Manager
 *
 * Handles data retention policies for student transfers
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RetentionManager
{
    protected $pdo;
    protected $settingGateway;
    protected $transferGateway;
    protected $notificationService;

    // Default retention periods in days
    const DEFAULT_RETENTION_PERIODS = [
        'active' => 90,    // Active transfers
        'completed' => 365, // Completed transfers
        'cancelled' => 30,  // Cancelled/rejected transfers
        'archive' => 730    // Archived transfers
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
     * Process data retention policies.
     * This should be run as a scheduled task.
     *
     * @return array Results of the retention process
     */
    public function processRetention()
    {
        $results = [
            'archived' => 0,
            'deleted' => 0,
            'errors' => []
        ];

        try {
            // Start transaction
            $this->pdo->getConnection()->beginTransaction();

            // Archive old completed transfers
            $archivedTransfers = $this->archiveOldTransfers();
            $results['archived'] = count($archivedTransfers);

            // Delete expired transfers
            $deletedTransfers = $this->deleteExpiredTransfers();
            $results['deleted'] = count($deletedTransfers);

            // Clean up orphaned files
            $this->cleanupOrphanedFiles();

            // Commit transaction
            $this->pdo->getConnection()->commit();

            return $results;

        } catch (\Exception $e) {
            $this->pdo->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Archive transfers that have exceeded their retention period.
     *
     * @return array Archived transfer IDs
     */
    protected function archiveOldTransfers()
    {
        $archivedTransfers = [];
        $retentionPeriod = $this->getRetentionPeriod('completed');

        $sql = "SELECT studentTransferLogID, gibbonPersonID 
                FROM gibbonStudentTransferLog 
                WHERE status = 'Completed' 
                AND timestampModified < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND archived = 'N'";

        $transfers = $this->pdo->select($sql, ['days' => $retentionPeriod])->fetchAll();

        foreach ($transfers as $transfer) {
            // Archive the transfer data
            $this->archiveTransfer($transfer['studentTransferLogID']);
            
            // Update transfer status
            $this->transferGateway->update($transfer['studentTransferLogID'], [
                'archived' => 'Y',
                'archiveTimestamp' => date('Y-m-d H:i:s')
            ]);

            $archivedTransfers[] = $transfer['studentTransferLogID'];
        }

        return $archivedTransfers;
    }

    /**
     * Delete transfers that have exceeded their expiry period.
     *
     * @return array Deleted transfer IDs
     */
    protected function deleteExpiredTransfers()
    {
        $deletedTransfers = [];

        // Get retention periods for different statuses
        $periods = [
            'cancelled' => $this->getRetentionPeriod('cancelled'),
            'archived' => $this->getRetentionPeriod('archive')
        ];

        // Delete cancelled/rejected transfers
        $sql = "SELECT studentTransferLogID, gibbonPersonID 
                FROM gibbonStudentTransferLog 
                WHERE (status IN ('Cancelled', 'Rejected') 
                AND timestampModified < DATE_SUB(NOW(), INTERVAL :cancelDays DAY))
                OR (archived = 'Y' 
                AND archiveTimestamp < DATE_SUB(NOW(), INTERVAL :archiveDays DAY))";

        $transfers = $this->pdo->select($sql, [
            'cancelDays' => $periods['cancelled'],
            'archiveDays' => $periods['archived']
        ])->fetchAll();

        foreach ($transfers as $transfer) {
            // Delete transfer files
            $this->deleteTransferFiles($transfer['studentTransferLogID']);
            
            // Delete transfer record
            $this->transferGateway->delete($transfer['studentTransferLogID']);

            $deletedTransfers[] = $transfer['studentTransferLogID'];
        }

        return $deletedTransfers;
    }

    /**
     * Archive a transfer's data to long-term storage.
     *
     * @param string $transferID
     * @return bool
     */
    protected function archiveTransfer($transferID)
    {
        $transfer = $this->transferGateway->getTransferByID($transferID);
        if (empty($transfer)) {
            return false;
        }

        // Create archive directory if it doesn't exist
        $archiveDir = '/path/to/archives/' . date('Y/m');
        if (!file_exists($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        // Archive the transfer data
        $archiveData = [
            'transfer' => $transfer,
            'workflow' => $this->getWorkflowHistory($transferID),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Write to archive file
        $archivePath = $archiveDir . '/' . $transferID . '.json';
        file_put_contents($archivePath, json_encode($archiveData));

        return true;
    }

    /**
     * Delete transfer files from storage.
     *
     * @param string $transferID
     * @return bool
     */
    protected function deleteTransferFiles($transferID)
    {
        $paths = [
            '/path/to/transfers/' . $transferID . '.zip',
            '/path/to/archives/' . date('Y/m') . '/' . $transferID . '.json'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        return true;
    }

    /**
     * Clean up orphaned files.
     *
     * @return bool
     */
    protected function cleanupOrphanedFiles()
    {
        // Get list of valid transfer IDs
        $sql = "SELECT studentTransferLogID FROM gibbonStudentTransferLog";
        $validIDs = $this->pdo->select($sql)->fetchAll(\PDO::FETCH_COLUMN);

        // Check transfer directory
        $transferDir = '/path/to/transfers';
        if (is_dir($transferDir)) {
            $files = glob($transferDir . '/*.zip');
            foreach ($files as $file) {
                $id = basename($file, '.zip');
                if (!in_array($id, $validIDs)) {
                    unlink($file);
                }
            }
        }

        return true;
    }

    /**
     * Get retention period for a specific status.
     *
     * @param string $status
     * @return int Number of days
     */
    protected function getRetentionPeriod($status)
    {
        $setting = $this->settingGateway->getSettingByScope(
            'Student Transfer',
            'retentionPeriod' . ucfirst($status)
        );

        return !empty($setting)? intval($setting) : self::DEFAULT_RETENTION_PERIODS[$status];
    }

    /**
     * Get workflow history for archiving.
     *
     * @param string $transferID
     * @return array
     */
    protected function getWorkflowHistory($transferID)
    {
        $sql = "SELECT * FROM gibbonStudentTransferWorkflowLog 
                WHERE studentTransferLogID=:transferID 
                ORDER BY timestamp";

        return $this->pdo->select($sql, ['transferID' => $transferID])->fetchAll();
    }
}
