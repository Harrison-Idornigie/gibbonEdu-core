<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Connection;
use Firebase\JWT\JWT;

/**
 * Security Service
 *
 * Handles encryption, decryption, and secure file operations for student transfers
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SecurityService
{
    protected $pdo;
    protected $settingGateway;
    private $privateKey;
    private $publicKey;

    public function __construct(Connection $pdo, SettingGateway $settingGateway)
    {
        $this->pdo = $pdo;
        $this->settingGateway = $settingGateway;
        
        // Initialize keys from settings
        $this->privateKey = $this->settingGateway->getSettingByScope('System Admin', 'transferPrivateKey');
        $this->publicKey = $this->settingGateway->getSettingByScope('System Admin', 'transferPublicKey');
    }

    /**
     * Create a digital signature for a file.
     *
     * @param string $filePath
     * @return string
     */
    public function signFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File not found: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        $hash = hash('sha256', $content);
        
        // Sign the hash with the private key
        openssl_sign($hash, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        
        return base64_encode($signature);
    }

    /**
     * Verify a file's digital signature.
     *
     * @param string $filePath
     * @param string $signature
     * @return bool
     */
    public function verifySignature($filePath, $signature)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File not found: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        $hash = hash('sha256', $content);
        $decodedSignature = base64_decode($signature);
        
        return openssl_verify($hash, $decodedSignature, $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Create a secure download link for a transfer package.
     *
     * @param string $transferID
     * @return array Download information including URL and expiry
     */
    public function createSecureDownloadLink($transferID)
    {
        // Generate a secure token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Store the token
        $sql = "INSERT INTO gibbonStudentTransferToken SET 
                transferID=:transferID, 
                token=:token, 
                expiry=:expiry";
                
        $this->pdo->insert($sql, [
            'transferID' => $transferID,
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'expiry' => $expiry
        ]);
        
        return [
            'url' => '/modules/Student Transfer/transfer_download.php?token=' . $token,
            'token' => $token,
            'expiry' => $expiry
        ];
    }

    /**
     * Decrypt transfer data from secure storage.
     *
     * @param string $transferData Encrypted transfer data string
     * @return array Decrypted data
     * @throws \InvalidArgumentException If no data provided
     * @throws \RuntimeException If decryption fails
     */
    public function decryptTransferData($transferData)
    {
        if (empty($transferData)) {
            throw new \InvalidArgumentException('No transfer data provided');
        }

        try {
            // Ensure we have a string
            $transferDataString = is_array($transferData) ? json_encode($transferData) : $transferData;

            // Decrypt using private key
            $decoded = JWT::decode($transferDataString, $this->privateKey, ['RS256']);
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to decrypt transfer data: ' . $e->getMessage());
        }
    }

    /**
     * Create a secure ZIP file with password protection.
     *
     * @param string $sourceDir Directory to zip
     * @param string $destinationZip Path for output ZIP
     * @return bool Success/failure
     */
    public function createSecureZip($sourceDir, $destinationZip)
    {
        if (!is_dir($sourceDir)) {
            throw new \InvalidArgumentException('Source directory not found: ' . $sourceDir);
        }

        $zip = new \ZipArchive();
        if ($zip->open($destinationZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP file');
        }

        try {
            // Generate a strong random password
            $password = bin2hex(random_bytes(16));

            // Set encryption method to AES-256
            $zip->setEncryptionName('*', \ZipArchive::EM_AES_256, $password);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($sourceDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();

            // Store password securely
            $this->storeTransferPassword($destinationZip, $password);

            return true;
        } catch (\Exception $e) {
            if ($zip->numFiles > 0) {
                $zip->close();
            }
            if (file_exists($destinationZip)) {
                unlink($destinationZip);
            }
            throw $e;
        }
    }

    /**
     * Store transfer password securely.
     *
     * @param string $zipPath Path to ZIP file
     * @param string $password Password to store
     */
    protected function storeTransferPassword($zipPath, $password)
    {
        $sql = "INSERT INTO gibbonStudentTransferPassword SET zipPath=:zipPath, password=:password";
        $this->pdo->insert($sql, [
            'zipPath' => $zipPath,
            'password' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }
}
