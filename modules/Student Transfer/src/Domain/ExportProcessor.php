<?php
namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;

/**
 * Export Processor
 *
 * Handles the export of student data for transfer
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ExportProcessor
{
    protected $pdo;
    protected $settingGateway;
    protected $studentExporter;
    protected $securityService;

    public function __construct(
        Connection $pdo,
        SettingGateway $settingGateway,
        StudentExporter $studentExporter,
        SecurityService $securityService
    ) {
        $this->pdo = $pdo;
        $this->settingGateway = $settingGateway;
        $this->studentExporter = $studentExporter;
        $this->securityService = $securityService;
    }

    /**
     * Process a student export.
     *
     * @param string $studentID Student ID to export
     * @param array $options Export options
     * @return bool Success/failure
     */
    public function processExport($studentID, array $options)
    {
        try {
            // Generate temporary directory
            $tempDir = sys_get_temp_dir() . '/transfer_' . uniqid();
            if (!mkdir($tempDir) && !is_dir($tempDir)) {
                throw new \RuntimeException('Failed to create temporary directory');
            }

            // Export student data to JSON
            $data = $this->studentExporter->exportStudentData($studentID);
            $jsonFile = $tempDir . '/student_data.json';
            file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

            // Export attachments if any
            if (!empty($data['attachments'])) {
                mkdir($tempDir . '/attachments');
                foreach ($data['attachments'] as $attachment) {
                    copy($attachment['path'], $tempDir . '/attachments/' . basename($attachment['path']));
                }
            }

            // Create metadata
            $metadata = [
                'timestamp' => date('Y-m-d H:i:s'),
                'source' => $this->settingGateway->getSettingByScope('System', 'organisationName'),
                'version' => '1.0',
                'publicKey' => $this->securityService->getPublicKey() // Use proper public key for verification
            ];
            file_put_contents($tempDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

            // Create manifest with checksums and signatures
            $manifest = ['files' => []];
            foreach (glob($tempDir . '/*') as $file) {
                if (is_file($file)) {
                    $relativePath = basename($file);
                    $manifest['files'][$relativePath] = [
                        'checksum' => hash_file('sha256', $file),
                        'signature' => $this->securityService->createDigitalSignature($file)
                    ];
                }
            }
            file_put_contents($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // Create secure ZIP
            $zipFile = sys_get_temp_dir() . "/transfer_{$studentID}.zip";
            $zipInfo = $this->securityService->createSecureZip($tempDir, $zipFile);

            // Store password securely for later retrieval
            if (!$this->securityService->storeTransferPassword($studentID, $zipInfo['password'])) {
                throw new \RuntimeException('Failed to store transfer password');
            }

            // Clean up
            $this->cleanupTempFiles($tempDir);

            return true;

        } catch (\Exception $e) {
            // Log error and return false
            error_log($e->getMessage());
            $this->cleanupTempFiles($tempDir ?? null);
            return false;
        }
    }

    /**
     * Clean up temporary files.
     *
     * @param string|null $tempDir Directory to clean up
     */
    protected function cleanupTempFiles($tempDir)
    {
        if ($tempDir && is_dir($tempDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            rmdir($tempDir);
        }
    }
}
