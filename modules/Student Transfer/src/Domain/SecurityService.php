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
    private $secretKey;

    /**
     * Create a new SecurityService instance.
     *
     * @param Connection $pdo
     * @param SettingGateway $settingGateway
     */
    public function __construct(Connection $pdo, SettingGateway $settingGateway)
    {
        $this->pdo = $pdo;
        $this->secretKey = $settingGateway->getSettingByScope('Student Transfer', 'encryptionKey');
        
        // If no key exists, generate one and save it
        if (empty($this->secretKey)) {
            $this->secretKey = $this->generateSecureKey();
            $settingGateway->updateSettingByScope('Student Transfer', 'encryptionKey', $this->secretKey);
        }
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
     * Create digital signature for a file using HMAC-SHA256.
     * This provides cryptographic verification of file authenticity and integrity.
     *
     * @param string $filePath Path to the file to sign
     * @return string The digital signature
     * @throws \RuntimeException If file cannot be read or encryption key is not set
     */
    public function createDigitalSignature($filePath)
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('Transfer encryption key is not set. Please check module settings.');
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('Cannot read file for signing: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file contents: ' . $filePath);
        }

        return hash_hmac('sha256', $content, $this->secretKey);
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
     * Verify a digital signature for a file.
     * Used during import to verify file authenticity and integrity.
     *
     * @param string $filePath Path to the file to verify
     * @param string $signature The digital signature to verify against
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyDigitalSignature($filePath, $signature)
    {
        $content = file_get_contents($filePath);
        $expectedSignature = hash_hmac('sha256', $content, $this->secretKey);
        return hash_equals($expectedSignature, $signature);
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

        $token = hash_hmac('sha256', 
            $transferID . $expiry->format('Y-m-d H:i:s'),
            $this->secretKey
        );

        return [
            'token' => $token,
            'expiry' => $expiry->format('Y-m-d H:i:s')
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
        $now = new \DateTime();
        $expiryDate = new \DateTime($expiry);

        if ($now > $expiryDate) {
            return false;
        }

        $expectedToken = hash_hmac('sha256',
            $transferID . $expiry,
            $this->secretKey
        );

        return hash_equals($expectedToken, $token);
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

        // Sign the token with our secret key
        $token = hash_hmac('sha256', 
            json_encode($tokenData),
            $this->secretKey
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
}
