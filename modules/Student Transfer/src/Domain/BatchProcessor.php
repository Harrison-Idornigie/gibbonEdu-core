<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\School\SettingGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Services\Format;

/**
 * Batch Processor Class
 *
 * Handles batch processing of student transfers including:
 * - Export of multiple student records
 * - Import of multiple transfer packages
 * - Notification handling
 * - Status tracking
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class BatchProcessor
{
    protected $connection;
    protected $notificationGateway;
    protected $transferGateway;
    protected $studentGateway;
    protected $userGateway;
    protected $settingGateway;
    protected $studentExporter;

    /**
     * Create a new BatchProcessor
     *
     * @param Connection $connection
     * @param NotificationGateway $notificationGateway
     * @param TransferGateway $transferGateway
     * @param StudentGateway $studentGateway
     * @param UserGateway $userGateway
     * @param SettingGateway $settingGateway
     * @param StudentExporter $studentExporter
     */
    public function __construct(
        Connection $connection,
        NotificationGateway $notificationGateway,
        TransferGateway $transferGateway,
        StudentGateway $studentGateway,
        UserGateway $userGateway,
        SettingGateway $settingGateway,
        StudentExporter $studentExporter
    ) {
        $this->connection = $connection;
        $this->notificationGateway = $notificationGateway;
        $this->transferGateway = $transferGateway;
        $this->studentGateway = $studentGateway;
        $this->userGateway = $userGateway;
        $this->settingGateway = $settingGateway;
        $this->studentExporter = $studentExporter;
    }

    /**
     * Process a batch of student transfers for export
     *
     * @param array $studentIDs
     * @param array $params
     * @return array
     */
    public function processBatchExport(array $studentIDs, array $params): array
    {
        $results = [];
        
        foreach ($studentIDs as $studentID) {
            $data = [
                'gibbonPersonID' => $studentID,
                'gibbonSchoolYearID' => $params['gibbonSchoolYearID'],
                'schoolNameFrom' => $params['schoolNameFrom'],
                'schoolNameTo' => $params['schoolNameTo'],
                'status' => 'Pending'
            ];

            $inserted = $this->transferGateway->insert($data);
            if ($inserted) {
                $transferID = $this->connection->insertID();
                $results[$studentID] = [
                    'success' => true,
                    'transferID' => $transferID
                ];

                // Send notification
                $this->notificationGateway->insert([
                    'title' => 'Student Transfer Created',
                    'text' => "Transfer #{$transferID} has been created",
                    'moduleName' => 'Student Transfer'
                ]);
            } else {
                $results[$studentID] = [
                    'success' => false,
                    'error' => 'Failed to create transfer record'
                ];
            }
        }

        return $results;
    }

    /**
     * Process a batch of student transfers for import
     *
     * @param array $transfers
     * @return array
     */
    public function processBatchImport(array $transfers): array
    {
        $results = [];

        foreach ($transfers as $transfer) {
            $updated = $this->transferGateway->update($transfer['transferID'], [
                'status' => 'Imported',
                'importTimestamp' => date('Y-m-d H:i:s')
            ]);

            if ($updated) {
                $this->notificationGateway->insert([
                    'title' => 'Student Transfer Imported',
                    'text' => "Transfer #{$transfer['transferID']} has been imported",
                    'moduleName' => 'Student Transfer'
                ]);

                $results[$transfer['transferID']] = [
                    'success' => true
                ];
            } else {
                $results[$transfer['transferID']] = [
                    'success' => false,
                    'error' => 'Failed to update transfer status'
                ];
            }
        }

        return $results;
    }

    /**
     * Process a batch transfer of students
     *
     * @param array $gibbonPersonIDs
     * @param string $schoolNameTo
     * @param string $gibbonPersonIDCreated
     * @param string $notes
     * @return array
     */
    public function processBatchTransfer(array $gibbonPersonIDs, $schoolNameTo, $gibbonPersonIDCreated, $notes = ''): array
    {
        $results = [];

        try {
            $this->connection->beginTransaction();

            // Get current school name
            $currentSchoolName = $this->settingGateway->getSettingByScope('System', 'organisationName');

            foreach ($gibbonPersonIDs as $gibbonPersonID) {
                $data = [
                    'gibbonPersonID' => $gibbonPersonID,
                    'gibbonSchoolYearID' => $this->settingGateway->getSettingByScope('System', 'gibbonSchoolYearID'),
                    'schoolNameFrom' => $currentSchoolName,
                    'schoolNameTo' => $schoolNameTo,
                    'gibbonPersonIDCreated' => $gibbonPersonIDCreated,
                    'status' => 'Pending',
                    'notes' => $notes
                ];

                $inserted = $this->transferGateway->insert($data);
                if ($inserted) {
                    $transferID = $this->connection->insertID();
                    
                    // Add notification for the student
                    $this->notificationGateway->insert([
                        'title' => 'Transfer Initiated',
                        'text' => "A transfer has been initiated for you to {$schoolNameTo}",
                        'moduleName' => 'Student Transfer'
                    ]);

                    $results[$gibbonPersonID] = [
                        'success' => true,
                        'transferID' => $transferID
                    ];
                } else {
                    $results[$gibbonPersonID] = [
                        'success' => false,
                        'error' => 'Failed to create transfer record'
                    ];
                }
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Validates a transfer package
     * @param string $file Path to package file
     * @return bool
     */
    protected function validatePackage($file)
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return false;
        }

        // Check required files exist
        $requiredFiles = ['student_data.json', 'metadata.json', 'manifest.json'];
        foreach ($requiredFiles as $required) {
            if ($zip->locateName($required) === false) {
                $zip->close();
                return false;
            }
        }

        // Validate manifest checksums
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        if (!$manifest) {
            $zip->close();
            return false;
        }

        foreach ($manifest as $file => $hash) {
            $content = $zip->getFromName($file);
            if (!$content || hash('sha256', $content) !== $hash) {
                $zip->close();
                return false;
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Extracts and processes package data
     * @param string $file Path to package file
     * @return array Processed data
     */
    protected function extractPackageData($file)
    {
        $zip = new \ZipArchive();
        $zip->open($file);

        $data = json_decode($zip->getFromName('student_data.json'), true);
        $metadata = json_decode($zip->getFromName('metadata.json'), true);

        $zip->close();

        return array_merge($data, $metadata);
    }

    /**
     * Creates an application form from transfer data
     * @param array $data Transfer data
     * @return int Application ID
     */
    protected function createApplicationForm($data)
    {
        // Format data for application form
        $formData = [
            'gibbonSchoolYearID' => $this->settingGateway->getSettingByScope('System', 'gibbonSchoolYearIDNext'),
            'surname' => $data['personal']['surname'],
            'firstName' => $data['personal']['firstName'],
            'preferredName' => $data['personal']['preferredName'],
            'officialName' => $data['personal']['officialName'],
            'dob' => $data['personal']['dob'],
            'email' => $data['personal']['email'],
            'phone' => $data['personal']['phone1'],
            'address' => $data['personal']['address1'],
            'emergency1Name' => $data['family'][0]['preferredName'] . ' ' . $data['family'][0]['surname'],
            'emergency1Number1' => $data['family'][0]['phone1'],
            'emergency1Relationship' => $data['family'][0]['relationship'],
            'medicalInformation' => json_encode($data['medical'])
        ];

        // Insert application form
        $sql = "INSERT INTO gibbonApplicationForm SET " . 
               implode(', ', array_map(function($key) { return "`$key`=:$key"; }, array_keys($formData)));
        
        $this->connection->insert($sql, $formData);
        return $this->connection->lastInsertID();
    }

    /**
     * Imports attachments from transfer package
     * @param string $file Path to package file
     * @param int $gibbonPersonID Student ID
     */
    protected function importAttachments($file, $gibbonPersonID)
    {
        $zip = new \ZipArchive();
        $zip->open($file);

        $targetDir = 'uploads/students/' . $gibbonPersonID;
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Extract attachments
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'attachments/') === 0) {
                $zip->extractTo($targetDir, $name);
            }
        }

        $zip->close();
    }

    /**
     * Notifies receiving school of new transfer
     * @param int $transferID Transfer ID
     * @param string $schoolNameTo Destination school name
     */
    protected function notifyReceivingSchool($transferID, $schoolNameTo)
    {
        // Implementation depends on how schools communicate
        // Could be email, API call, or other notification method
    }

    /**
     * Notifies original school of completed transfer
     * @param int $transferID Transfer ID
     */
    protected function notifyOriginalSchool($transferID)
    {
        $transfer = $this->transferGateway->getByID($transferID);
        $student = $this->userGateway->getByID($transfer['gibbonPersonID']);
        
        $this->notificationGateway->addNotification([
            'title' => 'Transfer Complete',
            'text' => sprintf('Transfer completed for %s %s', $student['preferredName'], $student['surname']),
            'moduleName' => 'Student Transfer',
            'actionLink' => '/modules/Student Transfer/transfer_manage.php'
        ]);
    }

    /**
     * Send a notification for a transfer event
     *
     * @param string $event The event type (transfer_exported, transfer_imported)
     * @param array $data Additional data for the notification
     */
    protected function sendNotification(string $event, array $data): void
    {
        $text = '';
        $actionLink = '';

        switch ($event) {
            case 'transfer_exported':
                $text = sprintf('Student transfer exported for %s (ID: %s)', $data['studentName'], $data['transferID']);
                $actionLink = '/modules/Student Transfer/transfer_manage_edit.php?gibbonStudentTransferLogID=' . $data['transferID'];
                break;
            
            case 'transfer_imported':
                $text = sprintf('Student transfer imported for %s (ID: %s)', $data['studentName'], $data['transferID']);
                $actionLink = '/modules/Student Transfer/transfer_manage_edit.php?gibbonStudentTransferLogID=' . $data['transferID'];
                break;
        }

        $this->notificationGateway->insert([
            'title'    => 'Student Transfer Notification',
            'text'     => $text,
            'moduleName' => 'Student Transfer',
            'actionLink' => $actionLink
        ]);
    }

    /**
     * Validate and extract a ZIP file
     *
     * @param string $file Path to the ZIP file
     * @return array Extracted and validated data
     * @throws \Exception If validation fails
     */
    protected function validateAndExtractZip(string $file): array
    {
        if (!file_exists($file)) {
            throw new \Exception('Import file not found');
        }

        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \Exception('Failed to open import file');
        }

        // Extract to temporary directory
        $tempDir = sys_get_temp_dir() . '/student_transfer_import_' . uniqid();
        $zip->extractTo($tempDir);
        $zip->close();

        // Validate required files
        $requiredFiles = ['student_data.json', 'metadata.json', 'manifest.json'];
        foreach ($requiredFiles as $requiredFile) {
            if (!file_exists($tempDir . '/' . $requiredFile)) {
                throw new \Exception('Missing required file: ' . $requiredFile);
            }
        }

        // Load and validate data
        $studentData = json_decode(file_get_contents($tempDir . '/student_data.json'), true);
        if (empty($studentData)) {
            throw new \Exception('Invalid student data format');
        }

        // Clean up
        $this->removeDirectory($tempDir);

        return $studentData;
    }

    /**
     * Recursively remove a directory
     *
     * @param string $dir Directory path
     */
    protected function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        $this->removeDirectory($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
