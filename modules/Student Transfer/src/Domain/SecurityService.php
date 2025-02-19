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
     */
    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
        $settingGateway = new SettingGateway($pdo);
        $this->secretKey = $settingGateway->getSettingByScope('System', 'installKey');
    }

    /**
     * Create digital signature for a file using HMAC-SHA256.
     * This provides cryptographic verification of file authenticity and integrity.
     *
     * @param string $filePath Path to the file to sign
     * @return string The digital signature
     * @throws \RuntimeException If file cannot be read or secret key is not set
     */
    public function createDigitalSignature($filePath)
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('System install key not found. Please check system settings.');
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
     * @param int $length Length of the password (default: 16)
     * @return string The generated password
     */
    public function generateSecurePassword($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
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
}
