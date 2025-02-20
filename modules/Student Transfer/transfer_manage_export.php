<?php
/*
Gibbon: the flexible, open school platform

Copyright (c) 2010-2022
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Domain\Students\FirstAidGateway;
use Gibbon\Domain\System\CustomFieldGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\StudentExporter;

// Module includes
require_once __DIR__ . '/../../gibbon.php';

if (!isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_manage.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get parameters
$gibbonStudentTransferLogID = $_GET['gibbonStudentTransferLogID'] ?? '';
$gibbonSchoolYearID = isset($_GET['gibbonSchoolYearID'])
    ? str_pad(preg_replace('/[^0-9]/', '', $_GET['gibbonSchoolYearID']), 3, '0', STR_PAD_LEFT)
    : $session->get('gibbonSchoolYearID');

try {
    // Initialize core services
    $settingGateway = $container->get(SettingGateway::class);
    $securityService = new SecurityService($pdo, $settingGateway);
    $transferGateway = $container->get(TransferGateway::class);

    // Initialize data gateways
    $studentGateway = $container->get(StudentGateway::class);
    $facilityGateway = $container->get(FacilityGateway::class);
    $userGateway = $container->get(UserGateway::class);
    $customFieldGateway = $container->get(CustomFieldGateway::class);
    $medicalGateway = $container->get(MedicalGateway::class);
    $firstAidGateway = $container->get(FirstAidGateway::class);

    // Create StudentExporter instance directly
    $studentExporter = new StudentExporter(
        $pdo,
        $settingGateway,
        $studentGateway,
        $facilityGateway,
        $userGateway,
        $customFieldGateway,
        $medicalGateway,
        $firstAidGateway,
        $session,
        $securityService
    );
} catch (\Exception $e) {
    $page->addError(__('Failed to initialize required services.'));
    error_log('Student Transfer Service Init Error: ' . $e->getMessage());
    return;
}

// Set up breadcrumbs
$page->breadcrumbs
    ->add(__('Manage Student Transfers'), 'transfer_manage.php', ['gibbonSchoolYearID' => $gibbonSchoolYearID])
    ->add(__('Export Student Transfer'));

// Get transfer record
$transfer = $transferGateway->getByID($gibbonStudentTransferLogID);

if (empty($transfer)) {
    $page->addError(__('The specified record cannot be found.'));
} else {
    // Check if transfer is in correct state for export
    if (!in_array($transfer['status'], ['Pending', 'Exported'])) {
        $page->addError(__('This transfer cannot be exported in its current state.'));
    } else {
        try {
            // Check for required PHP extensions
            if (!extension_loaded('zip')) {
                throw new \RuntimeException('The PHP ZIP extension is required for student transfers.');
            }

            // Check for ZIP encryption support
            if (!defined('ZipArchive::EM_AES_256')) {
                throw new \RuntimeException('Your PHP ZIP extension does not support encryption. Please upgrade to PHP 7.2 or later.');
            }

            // Get student ID from transfer record
            $studentID = $transfer['gibbonPersonID'];

            // Get existing password and token if this is a re-export
            $existingPassword = null;
            $existingToken = null;
            $password = null;
            $token = null;
            $expiry = null;
            $zipFile = sys_get_temp_dir() . '/student_transfer_' . $gibbonStudentTransferLogID . '.zip';
            
            // Only check for existing values if not regenerating
            $regenerate = $_GET['regenerate'] ?? '';
            if ($transfer['status'] === 'Exported' && $regenerate === '') {
                // Try to get existing password and token from database
                $sql = "SELECT packagePassword, packagePasswordPlain, downloadToken, downloadExpiry 
                       FROM gibbonStudentTransferLog 
                       WHERE gibbonStudentTransferLogID=:transferID";
                $result = $pdo->selectOne($sql, ['transferID' => $gibbonStudentTransferLogID]);
                
                if ($result) {
                    // Only reuse password if both hash and plain exist
                    if (!empty($result['packagePassword']) && !empty($result['packagePasswordPlain'])) {
                        $existingPassword = [
                            'hash' => $result['packagePassword'],
                            'plain' => $result['packagePasswordPlain']
                        ];
                        $password = $result['packagePasswordPlain']; // Store for later use
                    }
                    
                    // Only reuse token if not expired
                    if (!empty($result['downloadToken']) && !empty($result['downloadExpiry'])) {
                        $expiry = new \DateTime($result['downloadExpiry']);
                        $now = new \DateTime();
                        if ($now < $expiry) {
                            $existingToken = [
                                'token' => $result['downloadToken'],
                                'expiry' => $result['downloadExpiry']
                            ];
                        }
                    }
                }
            }

            // Handle regeneration requests
            if ($regenerate == 'password' || $regenerate == 'token') {
                // Clear existing values to force regeneration
                if ($regenerate == 'password') {
                    $existingPassword = null;
                    $password = null; // Clear stored password
                    $page->addMessage(__('A new password has been generated.'));
                    
                    // Update the transfer record to clear old password
                    $transferGateway->update($transfer['gibbonStudentTransferLogID'], [
                        'packagePassword' => null,
                        'packagePasswordPlain' => null
                    ]);
                } else {
                    $existingToken = null;
                    $page->addMessage(__('A new download link has been generated.'));
                    
                    // Update the transfer record to clear old token
                    $transferGateway->update($transfer['gibbonStudentTransferLogID'], [
                        'downloadToken' => null,
                        'downloadExpiry' => null
                    ]);
                }
            }

            // Only generate new ZIP if we don't have a valid existing password and token
            if (!isset($password) || $regenerate) {
                // Generate the ZIP file, reusing password and token if available
                $result = $studentExporter->exportToZip(
                    $studentID, 
                    $gibbonStudentTransferLogID,
                    $existingPassword,
                    $existingToken ? ['token' => $existingToken['token'], 'expiry' => $existingToken['expiry']] : null
                );
                $zipFile = $result['path'];
                $password = $result['password'];
                $token = $result['token'];
                $expiry = $result['expiry'];

                // Update transfer status and store security info
                $transferGateway->update($transfer['gibbonStudentTransferLogID'], [
                    'status' => 'Exported',
                    'exportTimestamp' => date('Y-m-d H:i:s'),
                    'packagePassword' => password_hash($password, PASSWORD_DEFAULT),
                    'packagePasswordPlain' => $password,
                    'downloadToken' => $token,
                    'downloadExpiry' => $expiry
                ]);
            } else {
                // Use existing values for display
                $token = $existingToken['token'];
                $expiry = $existingToken['expiry'];
            }

            // Create secure download link
            $downloadURL = $session->get('absoluteURL') . '/modules/Student Transfer/transfer_download.php';
            $downloadURL .= '?transferID=' . $transfer['gibbonStudentTransferLogID'];
            $downloadURL .= '&token=' . $token;

            // Show success information with copy buttons
            $page->addSuccess(__('Transfer package created successfully.'));

            // Add copy-to-clipboard JavaScript
            echo "<script>
                function copyToClipboard(elementId) {
                    var element = document.getElementById(elementId);
                    var text = element.textContent;
                    
                    navigator.clipboard.writeText(text).then(function() {
                        // Show feedback
                        var button = element.nextElementSibling;
                        var originalSrc = button.querySelector('img').src;
                        button.querySelector('img').src = './themes/" . $session->get('gibbonThemeName') . "/img/iconTick.png';
                        setTimeout(function() {
                            button.querySelector('img').src = originalSrc;
                        }, 1000);
                    });
                }
            </script>";
            
            // Add password display with copy button
            echo "<div class='success'>";
            echo "<div class='flex'>";
            echo "<div class='flex-1'>" . __('ZIP file password: ') . "<code id='transfer-password'>{$password}</code></div>";
            echo "<button onclick='copyToClipboard(\"transfer-password\")' class='button-link ml-2'>";
            echo "<img title='" . __('Copy to Clipboard') . "' src='./themes/" . $session->get('gibbonThemeName') . "/img/copy.png'/>";
            echo "</button>";
            echo "</div>";
            echo "</div>";

            // Add download URL with copy button
            echo "<div class='success'>";
            echo "<div class='flex'>";
            echo "<div class='flex-1'>" . __('Download URL: ') . "<code id='download-url'>{$downloadURL}</code></div>";
            echo "<button onclick='copyToClipboard(\"download-url\")' class='button-link ml-2'>";
            echo "<img title='" . __('Copy to Clipboard') . "' src='./themes/" . $session->get('gibbonThemeName') . "/img/copy.png'/>";
            echo "</button>";
            echo "</div>";
            echo "</div>";

            // Add regenerate buttons for security
            echo "<div class='mt-2'>";
            echo "<a href='" . $session->get('absoluteURL') . "/index.php?q=/modules/Student Transfer/transfer_manage_export.php&gibbonStudentTransferLogID=" . $gibbonStudentTransferLogID . "&gibbonSchoolYearID=" . $gibbonSchoolYearID . "&regenerate=password' class='button mr-1'>" . __('Regenerate Password') . "</a>";
            echo "<a href='" . $session->get('absoluteURL') . "/index.php?q=/modules/Student Transfer/transfer_manage_export.php&gibbonStudentTransferLogID=" . $gibbonStudentTransferLogID . "&gibbonSchoolYearID=" . $gibbonSchoolYearID . "&regenerate=token' class='button'>" . __('Regenerate Download Link') . "</a>";
            echo "</div>";

            // Add download and return buttons
            echo "<div class='mt-4'>";
            echo "<a href='" . $downloadURL . "' target='_blank' class='button mr-1'>" . __('Download Now') . "</a>";
            echo "<a href='" . $session->get('absoluteURL') . "/index.php?q=/modules/Student Transfer/transfer_manage.php&gibbonSchoolYearID=" . $gibbonSchoolYearID . "' class='button'>" . __('Return to Transfers') . "</a>";
            echo "</div>";

            // Clean up the temporary ZIP file after a delay
            register_shutdown_function(function() use ($zipFile) {
                if (file_exists($zipFile)) {
                    unlink($zipFile);
                }
            });
        } catch (\Exception $e) {
            // Log the full error for administrators
            error_log('Student Transfer Export Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            // Show a user-friendly error message
            $page->addError(__('An error occurred while creating the transfer package: {error}', [
                'error' => $e->getMessage()
            ]));
            
            // Clean up any temporary files
            if (isset($zipFile) && file_exists($zipFile)) {
                unlink($zipFile);
            }
            if (isset($tempDir) && file_exists($tempDir)) {
                cleanupTempDir($tempDir);
            }
        }
    }
}

/**
 * Recursively remove a directory and its contents
 * @param string $dir Directory path to remove
 */
function cleanupTempDir($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? cleanupTempDir($path) : unlink($path);
    }
    return rmdir($dir);
}
