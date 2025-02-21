<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Security Service
 *
 * Handles secure file operations for student transfers including:
 * - Digital signatures for file authenticity
 * - Secure password generation for ZIP files
 * - File integrity verification
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SecurityService
{
    private $pdo;
    private $privateKey;
    private $publicKey;

    /**
     * Create a new SecurityService instance.
     *
     * @param Connection $pdo
     * @param SettingGateway $settingGateway
     */
    public function __construct(Connection $pdo, SettingGateway $settingGateway)
    {
        $this->pdo = $pdo;
        $this->privateKey = $settingGateway->getSettingByScope('Student Transfer', 'transferPrivateKey');
        $this->publicKey = $settingGateway->getSettingByScope('Student Transfer', 'transferPublicKey');
        
        // If no keys exist, generate a new key pair
        if (empty($this->privateKey) || empty($this->publicKey)) {
            $this->generateKeyPair();
        }
    }

    /**
     * Generate a new public/private key pair for signing and verification
     */
    private function generateKeyPair()
    {
        // Generate new key pair
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        // Create the private and public key pair
        $res = openssl_pkey_new($config);
        
        // Extract the private key
        openssl_pkey_export($res, $privateKey);
        
        // Extract the public key
        $publicKey = openssl_pkey_get_details($res)['key'];
        
        // Store both keys
        $settingGateway = new SettingGateway($this->pdo);
        $settingGateway->updateSettingByScope('Student Transfer', 'transferPrivateKey', $privateKey);
        $settingGateway->updateSettingByScope('Student Transfer', 'transferPublicKey', $publicKey);
        
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    /**
     * Get the public key used for verifying signatures.
     * This key is shared with other schools in metadata.json.
     * 
     * @return string The public key in PEM format
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Generate a secure random key for encryption and signing.
     * 
     * @param int $length Length of the key in bytes (default: 32 for 256 bits)
     * @return string The generated key in hexadecimal format
     */
    public function generateSecureKey($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Create digital signature for a file using RSA-SHA256.
     * This provides cryptographic verification of file authenticity and integrity.
     *
     * @param string $filePath Path to the file to sign
     * @return string The digital signature in base64 format
     * @throws \RuntimeException If file cannot be read or private key is not set
     */
    public function createDigitalSignature($filePath)
    {
        if (empty($this->privateKey)) {
            throw new \RuntimeException('Transfer private key is not set. Please check module settings.');
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('Cannot read file for signing: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file contents: ' . $filePath);
        }

        $signature = '';
        if (!openssl_sign($content, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to create digital signature');
        }

        return base64_encode($signature);
    }

    /**
     * Verify a digital signature for a file.
     * Used during import to verify file authenticity and integrity.
     *
     * @param string $filePath Path to the file to verify
     * @param string $signature The base64-encoded signature to verify against
     * @param string|null $publicKey Optional public key to use for verification (for external signatures)
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyDigitalSignature($filePath, $signature, $publicKey = null)
    {
        try {
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new \RuntimeException('Cannot read file for verification: ' . $filePath);
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException('Failed to read file contents: ' . $filePath);
            }

            $signature = base64_decode($signature);
            if ($signature === false) {
                throw new \RuntimeException('Invalid signature format');
            }

            $key = $publicKey ?? $this->publicKey;
            return openssl_verify($content, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Signature verification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a secure random password for ZIP encryption.
     * Uses cryptographically secure random number generator.
     *
     * @return string The generated 6-digit password
     */
    public function generateSecurePassword()
    {
        $min = 100000; // Minimum 6-digit number
        $max = 999999; // Maximum 6-digit number
        return (string) random_int($min, $max);
    }

    /**
     * Verify ZIP file encryption and attempt to extract with password.
     *
     * @param string $zipPath Path to ZIP file
     * @param string $password Password to try
     * @param string $extractPath Path to extract to
     * @return bool True if successfully decrypted and extracted
     * @throws \RuntimeException if file is not encrypted or extraction fails
     */
    public function verifyZipEncryption($zipPath, $password, $extractPath)
    {
        // First verify the file is encrypted
        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== true) {
            throw new \RuntimeException('Failed to open ZIP file for encryption check');
        }

        try {
            // Check if any files are encrypted
            $isEncrypted = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stats = $zip->statIndex($i);
                if ($stats['encryption_method'] !== 0) {
                    $isEncrypted = true;
                    break;
                }
            }

            if (!$isEncrypted) {
                throw new \RuntimeException('ZIP file is not properly encrypted');
            }

            // Set password for decryption
            if (!$zip->setPassword($password)) {
                throw new \RuntimeException('Failed to set ZIP password');
            }

            // Get list of files for validation
            $fileCount = $zip->numFiles;
            $expectedFiles = ['metadata.json', 'student_data.json', 'manifest.json'];
            $foundFiles = [];

            // Extract each file individually with decryption
            for ($i = 0; $i < $fileCount; $i++) {
                $filename = $zip->getNameIndex($i);
                $basename = basename($filename);
                
                // Handle JSON files specially
                if (in_array($basename, $expectedFiles)) {
                    // Get encrypted content
                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        throw new \RuntimeException('Failed to read encrypted file: ' . $filename);
                    }

                    // Write decrypted content
                    $targetPath = $extractPath . '/' . $basename;
                    if (file_put_contents($targetPath, $content) === false) {
                        throw new \RuntimeException('Failed to write decrypted file: ' . $targetPath);
                    }

                    $foundFiles[] = $basename;
                } else {
                    // Extract other files normally (e.g., attachments)
                    $zip->extractTo($extractPath, $filename);
                }
            }

            $zip->close();

            // Validate all required files were extracted
            $missingFiles = array_diff($expectedFiles, $foundFiles);
            if (!empty($missingFiles)) {
                throw new \RuntimeException('Missing required files in ZIP: ' . implode(', ', $missingFiles));
            }

            return true;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Exception extracting/decrypting ZIP: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a secure download token.
     * Used to create temporary secure download links.
     *
     * @param string $transferID The transfer ID
     * @param int $expiryMinutes Minutes until token expires (default: 60)
     * @return array Token and expiry timestamp
     */
    public function generateDownloadToken($transferID, $expiryMinutes = 60)
    {
        $expiry = new \DateTime();
        $expiry->modify("+{$expiryMinutes} minutes");
        $expiryFormatted = $expiry->format('Y-m-d H:i:s');

        $token = hash_hmac('sha256', 
            $transferID . $expiryFormatted,
            $this->privateKey
        );

        return [
            'token' => $token,
            'expiry' => $expiryFormatted
        ];
    }

    /**
     * Verify a download token.
     * Used to validate temporary download links.
     *
     * @param string $transferID The transfer ID
     * @param string $token The token to verify
     * @param string $expiry The token's expiry timestamp
     * @return bool True if token is valid and not expired, false otherwise
     */
    public function verifyDownloadToken($transferID, $token, $expiry)
    {
        // Ensure expiry is in correct format
        try {
            $now = new \DateTime();
            $expiryDate = new \DateTime($expiry);
            $expiryFormatted = $expiryDate->format('Y-m-d H:i:s');

            if ($now > $expiryDate) {
                return false;
            }

            $expectedToken = hash_hmac('sha256',
                $transferID . $expiryFormatted,
                $this->privateKey
            );

            return hash_equals($expectedToken, $token);
        } catch (\Exception $e) {
            error_log('Token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a secure download token for public access.
     * Used to create temporary secure download links that can be accessed without login.
     *
     * @param string $transferID The transfer ID
     * @param int $expiryHours Hours until token expires (default: 48)
     * @return array Token data including download URL
     */
    public function generatePublicDownloadToken($transferID, $expiryHours = 48)
    {
        $expiry = new \DateTime();
        $expiry->modify("+{$expiryHours} hours");

        // Create a more complex token that includes transfer metadata
        $tokenData = [
            'id' => $transferID,
            'exp' => $expiry->getTimestamp(),
            'iat' => time(),
            'nonce' => bin2hex(random_bytes(16))
        ];

        // Sign the token with our private key
        $token = hash_hmac('sha256', 
            json_encode($tokenData),
            $this->privateKey
        );

        return [
            'token' => $token,
            'expiry' => $expiry->format('Y-m-d H:i:s'),
            'tokenData' => $tokenData
        ];
    }

    /**
     * Verify a public download token.
     * Used to validate temporary download links that don't require login.
     *
     * @param string $transferID The transfer ID
     * @param string $token The token to verify
     * @return bool True if token is valid and not expired
     */
    public function verifyPublicDownloadToken($transferID, $token)
    {
        try {
            // Get token data from database
            $sql = "SELECT downloadToken, downloadExpiry FROM gibbonStudentTransferLog 
                   WHERE gibbonStudentTransferLogID = :transferID";
            $result = $this->pdo->selectOne($sql, ['transferID' => $transferID]);

            if (empty($result) || empty($result['downloadToken']) || empty($result['downloadExpiry'])) {
                return false;
            }

            // Check expiry
            $expiry = new \DateTime($result['downloadExpiry']);
            $now = new \DateTime();
            if ($now > $expiry) {
                return false;
            }

            // Verify token
            return hash_equals($result['downloadToken'], $token);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Rate limit download attempts.
     * Prevents brute force attempts on the download URL.
     *
     * @param string $transferID The transfer ID
     * @param string $ipAddress The client IP address
     * @return bool True if allowed, false if rate limited
     */
    public function checkRateLimit($transferID, $ipAddress)
    {
        $timeWindow = 3600; // 1 hour
        $maxAttempts = 10;  // Maximum attempts per hour

        $sql = "SELECT COUNT(*) as attempts 
                FROM gibbonStudentTransferDownloadLog 
                WHERE gibbonStudentTransferLogID = :transferID 
                AND ipAddress = :ipAddress 
                AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $result = $this->pdo->selectOne($sql, [
            'transferID' => $transferID,
            'ipAddress' => $ipAddress
        ]);

        return ($result['attempts'] ?? 0) < $maxAttempts;
    }

    /**
     * Verify that a ZIP file is properly encrypted.
     *
     * @param string $zipPath Path to ZIP file
     * @return bool True if encrypted, false otherwise
     */
    public function isZipEncrypted($zipPath)
    {
        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== true) {
            throw new \RuntimeException('Failed to open ZIP file for encryption check');
        }

        try {
            // Check if any files are encrypted
            $isEncrypted = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stats = $zip->statIndex($i);
                if ($stats['encryption_method'] !== 0) {
                    $isEncrypted = true;
                    break;
                }
            }

            return $isEncrypted;
        } finally {
            $zip->close();
        }
    }

    /**
     * Extract and decrypt a ZIP file containing encrypted JSON files
     *
     * @param string $zipPath Path to the ZIP file
     * @param string $extractPath Directory to extract to
     * @param string $password ZIP password
     * @return bool True if successful, false otherwise
     */
    public function extractAndDecryptZip(string $zipPath, string $extractPath, string $password): bool
    {
        try {
            $zip = new \ZipArchive();
            $result = $zip->open($zipPath);
            
            if ($result !== true) {
                error_log("[Student Transfer] Failed to open ZIP file: " . $zipPath);
                return false;
            }

            // Set password for decryption
            if (!$zip->setPassword($password)) {
                error_log("[Student Transfer] Failed to set ZIP password");
                $zip->close();
                return false;
            }

            // Get list of files for validation
            $fileCount = $zip->numFiles;
            $expectedFiles = ['metadata.json', 'student_data.json', 'manifest.json'];
            $foundFiles = [];

            // Extract each file individually with decryption
            for ($i = 0; $i < $fileCount; $i++) {
                $filename = $zip->getNameIndex($i);
                $basename = basename($filename);
                
                // Handle JSON files specially
                if (in_array($basename, $expectedFiles)) {
                    // Get encrypted content
                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        error_log("[Student Transfer] Failed to read encrypted file: " . $filename);
                        continue;
                    }

                    // Write decrypted content
                    $targetPath = $extractPath . '/' . $basename;
                    if (file_put_contents($targetPath, $content) === false) {
                        error_log("[Student Transfer] Failed to write decrypted file: " . $targetPath);
                        continue;
                    }

                    $foundFiles[] = $basename;
                } else {
                    // Extract other files normally (e.g., attachments)
                    $zip->extractTo($extractPath, $filename);
                }
            }

            $zip->close();

            // Validate all required files were extracted
            $missingFiles = array_diff($expectedFiles, $foundFiles);
            if (!empty($missingFiles)) {
                error_log("[Student Transfer] Missing required files in ZIP: " . implode(', ', $missingFiles));
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Exception extracting/decrypting ZIP: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read a JSON file that was extracted from the encrypted ZIP
     * 
     * @param string $filePath Path to the JSON file
     * @return array|false Decoded JSON data as array, or false on failure
     */
    public function readJsonFile(string $filePath)
    {
        try {
            // Read the file content
            $content = file_get_contents($filePath);
            if ($content === false) {
                error_log("[Student Transfer] Failed to read file: " . $filePath);
                return false;
            }

            // Parse JSON
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[Student Transfer] Failed to parse JSON from file: " . json_last_error_msg());
                return false;
            }

            return $data;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Exception reading JSON file: " . $e->getMessage());
            return false;
        }
    }
    


    /**
     * Verify that a newly created ZIP file is properly encrypted.
     *
     * @param string $zipPath Path to ZIP file
     * @param string $password Password used for encryption
     * @return bool True if encryption verification passes
     * @throws \RuntimeException if verification fails
     */
    public function verifyNewZipEncryption($zipPath, $password)
    {
        // First verify the file is encrypted
        if (!$this->isZipEncrypted($zipPath)) {
            throw new \RuntimeException('Newly created ZIP file is not properly encrypted');
        }

        // Create a temporary extraction directory
        $tempDir = sys_get_temp_dir() . '/verify_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            throw new \RuntimeException('Failed to create temporary directory for verification');
        }

        try {
            // Try to extract and verify
            $this->extractAndDecryptZip($zipPath, $tempDir, $password);
            return true;
        } finally {
            // Clean up temp directory
            $this->cleanupTempDir($tempDir);
        }
    }

    /**
     * Decrypt a JSON file that was encrypted within a ZIP archive
     * 
     * @param string $filePath Path to the encrypted JSON file
     * @param string $password Password for decryption
     * @return array|false Decrypted JSON data as array, or false on failure
     */
    public function decryptJsonFile(string $filePath, string $password)
    {
        try {
            // Read the encrypted file content
            $content = file_get_contents($filePath);
            if ($content === false) {
                error_log("[Student Transfer] Failed to read file: " . $filePath);
                return false;
            }

            // Get the directory containing the file
            $dir = dirname($filePath);
            $zipPath = $dir . '/upload.zip';

            // Open ZIP to get the encryption parameters
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                error_log("[Student Transfer] Failed to open ZIP for encryption parameters");
                return false;
            }

            // Set password and get the file
            if (!$zip->setPassword($password)) {
                error_log("[Student Transfer] Failed to set ZIP password");
                $zip->close();
                return false;
            }

            // Get the relative path within the ZIP
            $relativePath = basename($filePath);
            $decrypted = $zip->getFromName($relativePath);
            $zip->close();

            if ($decrypted === false) {
                error_log("[Student Transfer] Failed to extract file from ZIP: " . $relativePath);
                return false;
            }

            // Parse JSON
            $data = json_decode($decrypted, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[Student Transfer] Failed to parse JSON from decrypted file: " . json_last_error_msg());
                return false;
            }

            return $data;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Exception decrypting file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract a password-protected ZIP file to a directory
     *
     * @param string $zipPath Path to the ZIP file
     * @param string $extractPath Directory to extract to
     * @param string $password ZIP password
     * @return bool True if successful, false otherwise
     */
    public function extractZipFile(string $zipPath, string $extractPath, string $password): bool
    {
        return $this->extractAndDecryptZip($zipPath, $extractPath, $password);
    }

    /**
     * Create a password-protected ZIP file from a directory.
     * Uses ZipArchive with encryption for secure file packaging.
     *
     * @param string $sourceDir Directory to zip
     * @param string $zipFile Output ZIP file path
     * @param string|null $password Optional password (generates random if null)
     * @return array ZIP file info including password
     */
    public function createSecureZip($sourceDir, $zipFile, $password = null)
    {
        // Generate random password if none provided
        if ($password === null) {
            $password = bin2hex(random_bytes(16)); // 32 character hex string
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create ZIP file");
        }

        // Set ZIP encryption password
        if (!$zip->setPassword($password)) {
            throw new \RuntimeException("Failed to set ZIP password");
        }

        // Add files to ZIP with encryption
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                
                // Add file with encryption
                if (!$zip->addFile($filePath, $relativePath)) {
                    throw new \RuntimeException("Failed to add file to ZIP: {$relativePath}");
                }
                $zip->setEncryptionName($relativePath, \ZipArchive::EM_AES_256);
            }
        }

        $zip->close();

        return [
            'path' => $zipFile,
            'password' => $password
        ];
    }

    /**
     * Clean up a temporary directory and its contents.
     *
     * @param string $dir Directory to clean
     */
    private function cleanupTempDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    private function getIV()
    {
        // Implement logic to retrieve initialization vector
    }

    /**
     * Store a transfer password securely.
     * Password is stored both encrypted and in plain text for sharing.
     *
     * @param string $studentID Student ID associated with the transfer
     * @param string $password Password to store
     * @return bool Success/failure
     */
    public function storeTransferPassword($studentID, $password)
    {
        try {
            // Get the latest transfer log for this student
            $sql = "SELECT gibbonStudentTransferLogID FROM gibbonStudentTransferLog 
                    WHERE gibbonPersonID = ? 
                    ORDER BY timestampCreated DESC 
                    LIMIT 1";
            
            $result = $this->pdo->selectOne($sql, [$studentID]);
            if (empty($result)) {
                error_log("[Student Transfer] No transfer log found for student: " . $studentID);
                return false;
            }

            // Update the transfer log with the password
            $data = [
                'packagePassword' => password_hash($password, PASSWORD_DEFAULT),
                'packagePasswordPlain' => $password,
                'timestampModified' => date('Y-m-d H:i:s'),
                'gibbonStudentTransferLogID' => $result['gibbonStudentTransferLogID'] // Include ID in data for WHERE clause
            ];
            
            $this->pdo->update('gibbonStudentTransferLog', $data);
            return true;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Failed to store transfer password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a stored transfer password.
     * Returns the plain text password for sharing with the receiving school.
     *
     * @param string $studentID Student ID associated with the transfer
     * @return string|false The password or false if not found
     */
    public function getTransferPassword($studentID)
    {
        try {
            $sql = "SELECT packagePasswordPlain 
                    FROM gibbonStudentTransferLog 
                    WHERE gibbonPersonID = ? 
                    ORDER BY timestampCreated DESC 
                    LIMIT 1";
            
            $result = $this->pdo->selectOne($sql, [$studentID]);
            return $result ? $result['packagePasswordPlain'] : false;
        } catch (\Exception $e) {
            error_log("[Student Transfer] Failed to retrieve transfer password: " . $e->getMessage());
            return false;
        }
    }
}
