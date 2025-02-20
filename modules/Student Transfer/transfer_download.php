<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
*/

// Module includes - but don't require login
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;

// Get request parameters
$transferID = $_GET['transferID'] ?? '';
$token = $_GET['token'] ?? '';
$password = $_GET['password'] ?? '';
$clientIP = $_SERVER['REMOTE_ADDR'];

if (empty($transferID) || empty($token)) {
    die(__('Invalid download link. Please contact the sending school for assistance.'));
}

try {
    // Initialize services manually to avoid circular dependencies
    $settingGateway = $container->get(SettingGateway::class);
    $transferGateway = new TransferGateway($pdo);
    $securityService = new SecurityService($pdo, $settingGateway);

    // Check rate limiting
    if (!$securityService->checkRateLimit($transferID, $clientIP)) {
        die(__('Too many download attempts. Please wait and try again later.'));
    }

    // Get the transfer details
    $transfer = $transferGateway->getByID($transferID);
    if (empty($transfer)) {
        die(__('The requested transfer cannot be found.'));
    }

    // Validate the token
    if (!$securityService->verifyPublicDownloadToken($transferID, $token)) {
        // Log failed attempt
        $transferGateway->logDownloadAttempt($transferID, [
            'ipAddress' => $clientIP,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'Invalid Token'
        ]);
        die(__('This download link has expired or is invalid. Please contact the sending school for a new link.'));
    }

    // If no password provided, show password form
    if (empty($password)) {
        // Output password form
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo __('Enter Transfer Password'); ?></title>
            <link rel="stylesheet" href="<?php echo $session->get('absoluteURL'); ?>/themes/Default/css/main.css">
        </head>
        <body>
            <div class="container">
                <h2><?php echo __('Student Transfer Download'); ?></h2>
                <p><?php echo __('Please enter the password provided by the sending school.'); ?></p>
                
                <form method="get" action="">
                    <input type="hidden" name="transferID" value="<?php echo $transferID; ?>">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    
                    <div class="row">
                        <div class="col">
                            <input type="text" name="password" 
                                   placeholder="<?php echo __('Enter 6-digit password'); ?>"
                                   pattern="[0-9]{6}" 
                                   maxlength="6" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <input type="submit" value="<?php echo __('Download File'); ?>" class="button">
                        </div>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    // Get plain password from database
    $sql = "SELECT packagePasswordPlain FROM gibbonStudentTransferLog WHERE gibbonStudentTransferLogID = :transferID";
    $result = $pdo->selectOne($sql, ['transferID' => $transferID]);
    $correctPassword = $result['packagePasswordPlain'] ?? '';

    // Verify password
    if (empty($correctPassword) || $password !== $correctPassword) {
        // Log failed attempt
        $transferGateway->logDownloadAttempt($transferID, [
            'ipAddress' => $clientIP,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'Invalid Password'
        ]);
        die(__('Invalid password. Please try again.'));
    }

    // Get the file path and check permissions
    $zipFile = sys_get_temp_dir() . '/student_transfer_' . $transferID . '.zip';
    
    if (!file_exists($zipFile)) {
        error_log("Transfer ZIP file not found: $zipFile");
        die(__('The transfer file is no longer available. Please contact the sending school to generate a new export.'));
    }

    if (!is_readable($zipFile)) {
        error_log("Transfer ZIP file not readable: $zipFile (Permissions: " . decoct(fileperms($zipFile)) . ")");
        die(__('Unable to access the transfer file. Please contact your system administrator.'));
    }

    // Check file size
    $fileSize = filesize($zipFile);
    if ($fileSize === false || $fileSize === 0) {
        error_log("Transfer ZIP file is empty or unreadable: $zipFile");
        die(__('The transfer file appears to be corrupted. Please contact the sending school to generate a new export.'));
    }

    // Log successful download
    $transferGateway->logDownloadAttempt($transferID, [
        'ipAddress' => $clientIP,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'],
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'Success',
        'fileSize' => $fileSize
    ]);

    // Set headers for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="student_transfer_'.$transferID.'.zip"');
    header('Content-Length: ' . $fileSize);
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    // Clear any previous output and disable output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Disable max execution time and memory limit for large files
    set_time_limit(0);
    ini_set('memory_limit', '256M');

    // Output file in chunks with error checking
    if ($fileHandle = fopen($zipFile, 'rb')) {
        $chunkSize = 8192; // 8KB chunks
        $totalBytesRead = 0;
        
        while (!feof($fileHandle)) {
            $buffer = fread($fileHandle, $chunkSize);
            if ($buffer === false) {
                error_log("Error reading ZIP file at position $totalBytesRead: $zipFile");
                break;
            }
            
            $bytesWritten = print($buffer);
            if ($bytesWritten === false) {
                error_log("Error writing ZIP file to output at position $totalBytesRead: $zipFile");
                break;
            }
            
            $totalBytesRead += strlen($buffer);
            
            if (connection_status() != 0) {
                error_log("Connection lost during download at position $totalBytesRead: $zipFile");
                break;
            }
            
            flush();
        }
        
        fclose($fileHandle);

        // Log if download was incomplete
        if ($totalBytesRead < $fileSize) {
            error_log("Incomplete download: $totalBytesRead of $fileSize bytes transferred for file: $zipFile");
            $transferGateway->logDownloadAttempt($transferID, [
                'ipAddress' => $clientIP,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'Incomplete',
                'bytesTransferred' => $totalBytesRead,
                'fileSize' => $fileSize
            ]);
        }
        
        exit();
    }

    error_log("Failed to open ZIP file for reading: $zipFile (Permissions: " . decoct(fileperms($zipFile)) . ")");
    die(__('Failed to download the file. Please try again or contact the sending school.'));
} catch (\Exception $e) {
    // Log the error
    error_log('Student Transfer Download Error: ' . $e->getMessage());
    die(__('An error occurred while processing your download. Please try again or contact the sending school.'));
}
