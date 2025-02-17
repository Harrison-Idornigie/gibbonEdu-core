<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;

/**
 * Batch Processor
 *
 * Handles batch processing of student transfers
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class BatchProcessor
{
    protected $pdo;
    protected $studentGateway;
    protected $settingGateway;
    protected $transferGateway;
    protected $exportProcessor;
    protected $importProcessor;
    protected $notificationService;

    public function __construct(
        Connection $pdo,
        StudentGateway $studentGateway,
        SettingGateway $settingGateway,
        TransferGateway $transferGateway,
        ExportProcessor $exportProcessor,
        ImportProcessor $importProcessor,
        NotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->studentGateway = $studentGateway;
        $this->settingGateway = $settingGateway;
        $this->transferGateway = $transferGateway;
        $this->exportProcessor = $exportProcessor;
        $this->importProcessor = $importProcessor;
        $this->notificationService = $notificationService;
    }

    /**
     * Process a batch of student transfers.
     *
     * @param array $studentIDs Array of student IDs to process
     * @param array $options Transfer options
     * @param string $gibbonPersonID Person initiating the batch
     * @return array Results of batch processing
     */
    public function processBatch($studentIDs, $options, $gibbonPersonID)
    {
        if (empty($studentIDs) || !is_array($studentIDs)) {
            throw new \InvalidArgumentException('No students selected for batch processing');
        }

        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($studentIDs as $studentID) {
            try {
                // Create transfer record
                $transferID = $this->transferGateway->insert([
                    'gibbonPersonID' => $studentID,
                    'gibbonPersonIDCreated' => $gibbonPersonID,
                    'status' => 'Pending',
                    'timestampCreated' => date('Y-m-d H:i:s')
                ]);

                if (!$transferID) {
                    throw new \RuntimeException('Failed to create transfer record');
                }

                // Process based on transfer type
                if ($options['type'] === 'export') {
                    $result = $this->processExport($studentID, $transferID, $options);
                    if (!$result) {
                        throw new \RuntimeException('Export processing failed');
                    }
                } elseif ($options['type'] === 'import') {
                    $result = $this->processImport($studentID, $transferID, $options);
                    if (!$result) {
                        throw new \RuntimeException('Import processing failed');
                    }
                }

                // Update transfer status
                $this->transferGateway->update($transferID, [
                    'status' => 'Complete',
                    'timestampModified' => date('Y-m-d H:i:s')
                ]);

                // Send notification
                $this->notificationService->sendTransferNotification($transferID, $gibbonPersonID);

                $results['success'][] = $studentID;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'studentID' => $studentID,
                    'error' => $e->getMessage()
                ];

                // Log error
                error_log('Batch Transfer Error: ' . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Process export for a single student.
     *
     * @param string $studentID
     * @param string $transferID
     * @param array $options
     * @return bool Success/failure
     */
    protected function processExport($studentID, $transferID, array $options)
    {
        try {
            // Export student data
            $result = $this->exportProcessor->processExport($studentID, $transferID, $options);
            return $result && isset($result['success']) ? $result['success'] : false;
        } catch (\Exception $e) {
            error_log('Export Processing Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process import for a single student.
     *
     * @param string $studentID
     * @param string $transferID
     * @param array $options
     * @return bool Success/failure
     */
    protected function processImport($studentID, $transferID, array $options)
    {
        try {
            // Import student data
            $result = $this->importProcessor->processImport($studentID, $options);
            return $result && isset($result['success']) ? $result['success'] : false;
        } catch (\Exception $e) {
            error_log('Import Processing Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process a batch import of transfers.
     *
     * @param array $files Array of transfer package files
     * @param array $options Import options
     * @param string $gibbonPersonID Person initiating the import
     * @return array Results of the batch import
     */
    public function processBatchImport($files, $options, $gibbonPersonID)
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'imports' => []
        ];

        // Start transaction
        $this->pdo->getConnection()->beginTransaction();

        try {
            foreach ($files as $file) {
                try {
                    // Validate and process import
                    $importResult = $this->importProcessor->processImport($file, $options);

                    $results['success']++;
                    $results['imports'][] = [
                        'transferID' => $importResult['transferID'],
                        'status' => 'success'
                    ];

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'file' => $file['name'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Commit transaction
            $this->pdo->getConnection()->commit();

            // Send batch completion notification
            $this->notificationService->sendBatchCompletionNotification($results, $gibbonPersonID);

            return $results;

        } catch (\Exception $e) {
            $this->pdo->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Get status of a batch operation.
     *
     * @param string $batchID
     * @return array Batch status and progress
     */
    public function getBatchStatus($batchID)
    {
        $sql = "SELECT COUNT(*) as total,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed
                FROM gibbonStudentTransferLog
                WHERE batchID=:batchID";

        return $this->pdo->select($sql, ['batchID' => $batchID])->fetch();
    }
}
