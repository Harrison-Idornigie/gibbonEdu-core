<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
*/

// Module includes - but don't require login
require_once __DIR__ . '/../../gibbon.php';

// Register module classes for autoloading
spl_autoload_register(function ($class) {
    // Only handle our module's classes
    if (strpos($class, 'Gibbon\\Module\\StudentTransfer\\') === 0) {
        // Convert namespace to path
        $path = __DIR__ . '/src/' . str_replace(['Gibbon\\Module\\StudentTransfer\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;

/**
 * Handle secure download of student transfer packages.
 * Implements rate limiting, token verification, and password protection.
 * Uses chunked file reading to handle large files efficiently.
 */

// Get request parameters
$transferID = $_REQUEST['transferID'] ?? '';  // Check both GET and POST
$token = $_REQUEST['token'] ?? '';  // Check both GET and POST
$password = $_POST['password'] ?? '';  // Check POST for password
$clientIP = $_SERVER['REMOTE_ADDR'];

// Validate required parameters
if (empty($transferID) || empty($token)) {
    error_log("Student Transfer: Invalid download attempt - missing parameters. IP: $clientIP");
    die(__('Invalid download link. Please contact the sending school for assistance.'));
}

try {
    // Initialize services manually to avoid circular dependencies
    $settingGateway = $container->get(SettingGateway::class);
    $transferGateway = new TransferGateway($pdo);
    $securityService = new SecurityService($pdo, $settingGateway);

    // Check rate limiting first
    if (!$securityService->checkRateLimit($transferID, $clientIP)) {
        error_log("Student Transfer: Rate limit exceeded for transfer $transferID from IP: $clientIP");
        die(__('Too many download attempts. Please wait and try again later.'));
    }

    // Get transfer record
    $transfer = $transferGateway->getByID($transferID);
    error_log("Student Transfer Debug: Transfer record: " . json_encode($transfer));

    if (empty($transfer)) {
        error_log("Student Transfer: Transfer record not found: $transferID");
        die(__('Invalid transfer ID. Please contact the sending school for assistance.'));
    }

    // Construct the correct file path
    $zipFile = $session->get('absolutePath').'/uploads/transfers/'.$transferID.'.zip';
    
    // Check if file exists
    if (!file_exists($zipFile)) {
        error_log("Student Transfer: ZIP file not found: $zipFile");
        die(__('The transfer file is no longer available. Please contact the sending school to generate a new export.'));
    }

    // Verify token and expiry
    if ($transfer['downloadToken'] !== $token) {
        error_log("Student Transfer: Invalid token for transfer $transferID from IP: $clientIP");
        die(__('Invalid download token. Please contact the sending school for assistance.'));
    }

    if (strtotime($transfer['downloadExpiry']) < time()) {
        error_log("Student Transfer: Expired download link for transfer $transferID");
        die(__('This download link has expired. Please contact the sending school for assistance.'));
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
            <style>
                .container { max-width: 600px; margin: 50px auto; padding: 20px; }
                .form-row { margin: 20px 0; }
                input[type="text"] { width: 200px; padding: 8px; font-size: 16px; }
                .button { padding: 8px 20px; background: #3B7694; color: white; border: none; cursor: pointer; }
                .button:hover { background: #2B5B73; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2><?php echo __('Student Transfer Download'); ?></h2>
                <p><?php echo __('Please enter the password provided by the sending school.'); ?></p>
                
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <input type="hidden" name="transferID" value="<?php echo $transferID; ?>">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    
                    <div class="form-row">
                        <input type="text" name="password" 
                               placeholder="<?php echo __('Enter 6-digit password'); ?>"
                               pattern="[0-9]{6}" 
                               maxlength="6" 
                               required>
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="button"><?php echo __('Download File'); ?></button>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    // Get plain password from database and verify
    $correctPassword = $transfer['packagePasswordPlain'] ?? '';

    // Debug logging
    error_log("Student Transfer Debug: Comparing passwords for transfer $transferID");
    error_log("Student Transfer Debug: Entered password: " . $password);
    error_log("Student Transfer Debug: Stored password: " . $correctPassword);

    if (empty($correctPassword) || $password !== $correctPassword) {
        // Log failed attempt
        error_log("Student Transfer: Invalid password attempt for transfer $transferID from IP: $clientIP");
        $transferGateway->logDownloadAttempt($transferID, [
            'ipAddress' => $clientIP,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => 0
        ]);
        
        // Show error message and password form again
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo __('Enter Transfer Password'); ?></title>
            <link rel="stylesheet" href="<?php echo $session->get('absoluteURL'); ?>/themes/Default/css/main.css">
            <style>
                .container { max-width: 600px; margin: 50px auto; padding: 20px; }
                .form-row { margin: 20px 0; }
                input[type="text"] { width: 200px; padding: 8px; font-size: 16px; }
                .button { padding: 8px 20px; background: #3B7694; color: white; border: none; cursor: pointer; }
                .button:hover { background: #2B5B73; }
                .error { color: #cc0000; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2><?php echo __('Student Transfer Download'); ?></h2>
                <p class="error"><?php echo __('Invalid password. Please try again.'); ?></p>
                <p><?php echo __('Please enter the password provided by the sending school.'); ?></p>
                
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <input type="hidden" name="transferID" value="<?php echo $transferID; ?>">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    
                    <div class="form-row">
                        <input type="text" name="password" 
                               placeholder="<?php echo __('Enter 6-digit password'); ?>"
                               pattern="[0-9]{6}" 
                               maxlength="6" 
                               required>
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="button"><?php echo __('Download File'); ?></button>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    // Validate file
    if (!is_readable($zipFile)) {
        error_log("Student Transfer: ZIP file not readable: $zipFile (Permissions: " . decoct(fileperms($zipFile)) . ")");
        die(__('Unable to access the transfer file. Please contact your system administrator.'));
    }

    $fileSize = filesize($zipFile);
    if ($fileSize === false || $fileSize === 0) {
        error_log("Student Transfer: ZIP file is empty or unreadable: $zipFile");
        die(__('The transfer file appears to be corrupted. Please contact the sending school to generate a new export.'));
    }

    // Log successful download start
    $transferGateway->logDownloadAttempt($transferID, [
        'ipAddress' => $clientIP,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'],
        'timestamp' => date('Y-m-d H:i:s'),
        'success' => 1
    ]);

    // Set headers for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="student_transfer_'.$transferID.'.zip"');
    header('Content-Length: ' . $fileSize);
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Increase memory and time limits for large files
    ini_set('memory_limit', '256M');
    set_time_limit(300); // 5 minutes

    // Output file in chunks
    if ($fileHandle = fopen($zipFile, 'rb')) {
        $chunkSize = 8192; // 8KB chunks
        $totalBytesRead = 0;
        
        while (!feof($fileHandle)) {
            $buffer = fread($fileHandle, $chunkSize);
            if ($buffer === false) {
                error_log("Student Transfer: Error reading ZIP file at position $totalBytesRead: $zipFile");
                break;
            }
            
            echo $buffer;  
            $totalBytesRead += strlen($buffer);
            
            if (connection_status() != 0) {
                error_log("Student Transfer: Connection lost during download at position $totalBytesRead: $zipFile");
                break;
            }
            
            flush();
        }
        
        fclose($fileHandle);

        // Log download completion
        if ($totalBytesRead === $fileSize) {
            $transferGateway->logDownloadAttempt($transferID, [
                'ipAddress' => $clientIP,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => date('Y-m-d H:i:s'),
                'success' => 1,
                'bytesTransferred' => $totalBytesRead
            ]);
        } else {
            error_log("Student Transfer: Incomplete download - $totalBytesRead of $fileSize bytes for transfer $transferID");
            $transferGateway->logDownloadAttempt($transferID, [
                'ipAddress' => $clientIP,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => date('Y-m-d H:i:s'),
                'success' => 0,
                'bytesTransferred' => $totalBytesRead,
                'fileSize' => $fileSize
            ]);
        }
        
        exit();
    }

    error_log("Student Transfer: Failed to open ZIP file: $zipFile (Permissions: " . decoct(fileperms($zipFile)) . ")");
    die(__('Failed to download the file. Please try again or contact the sending school.'));

} catch (\Exception $e) {
    error_log('Student Transfer Download Error: ' . $e->getMessage());
    die(__('An error occurred while processing your download. Please try again or contact the sending school.'));
}
