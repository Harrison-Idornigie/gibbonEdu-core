# Security Best Practices

A comprehensive guide to implementing security in the Report Template module.

## 1. Input Validation and Sanitization

Proper input validation and sanitization are crucial for preventing various security vulnerabilities, including SQL injection and cross-site scripting (XSS) attacks.

### 1.1 Data Validation Service

This service ensures that all input data meets the required criteria before processing.

```php
// Domain/Security/DataValidator.php
namespace Gibbon\Domain\Reports\Security;

class DataValidator
{
    /**
     * Validates template data against a set of rules
     *
     * @param array $data The template data to validate
     * @return array An array of error messages, empty if validation passes
     */
    public function validateTemplate(array $data): array
    {
        $errors = [];
        
        // Check for required fields
        if (empty($data['name'])) {
            $errors[] = 'Name is required';
        }
        
        // Enforce string length limits to prevent database overflow
        if (strlen($data['name']) > 100) {
            $errors[] = 'Name cannot exceed 100 characters';
        }
        
        // Ensure only allowed values are accepted
        if (!in_array($data['active'], ['Y', 'N'])) {
            $errors[] = 'Active must be Y or N';
        }
        
        // Validate file types to prevent malicious file uploads
        if (!empty($data['attachment'])) {
            $allowedTypes = ['application/pdf', 'application/msword'];
            if (!in_array($data['attachment']['type'], $allowedTypes)) {
                $errors[] = 'Invalid file type. Allowed types: PDF, DOC';
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitizes input data to remove potential XSS threats
     *
     * @param array $data The input data to sanitize
     * @return array The sanitized data
     */
    public function sanitizeInput(array $data): array
    {
        $clean = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove HTML tags to prevent XSS
                $clean[$key] = strip_tags($value);
            } elseif (is_array($value)) {
                // Recursively sanitize nested arrays
                $clean[$key] = $this->sanitizeInput($value);
            } else {
                // Keep other data types as is
                $clean[$key] = $value;
            }
        }
        
        return $clean;
    }
}
```

### 1.2 SQL Injection Prevention

Using prepared statements and parameterized queries is essential for preventing SQL injection attacks.

```php
// Domain/Gateway/TemplateGateway.php
namespace Gibbon\Domain\Reports\Gateway;

use PDO;

class TemplateGateway
{
    private $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Retrieves a template by its ID using a prepared statement
     *
     * @param int $id The template ID
     * @return array|false The template data or false if not found
     */
    public function getByID(int $id)
    {
        $sql = "SELECT * FROM gibbonReportTemplate WHERE gibbonReportTemplateID = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Searches for templates based on given criteria using parameterized queries
     *
     * @param array $criteria The search criteria
     * @return array The matching templates
     */
    public function search(array $criteria)
    {
        $sql = "SELECT * FROM gibbonReportTemplate WHERE 1=1";
        $params = [];
        
        // Dynamically build the query based on provided criteria
        if (!empty($criteria['name'])) {
            $sql .= " AND name LIKE :name";
            $params['name'] = '%' . $criteria['name'] . '%';
        }
        
        if (isset($criteria['active'])) {
            $sql .= " AND active = :active";
            $params['active'] = $criteria['active'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
```

## 2. Authentication and Authorization

Proper authentication and authorization ensure that users can only access resources they're permitted to.

### 2.1 Permission Manager

This class manages user permissions for viewing and editing templates.

```php
// Domain/Security/PermissionManager.php
namespace Gibbon\Domain\Reports\Security;

class PermissionManager
{
    private $db;
    private $session;
    
    public function __construct($db, $session)
    {
        $this->db = $db;
        $this->session = $session;
    }
    
    /**
     * Checks if the current user can view a specific template
     *
     * @param int $templateID The ID of the template
     * @return bool True if the user can view the template, false otherwise
     */
    public function canViewTemplate(int $templateID): bool
    {
        $user = $this->session->get('gibbonPersonID');
        $role = $this->session->get('gibbonRoleIDPrimary');
        
        // Admins (role ID 1) always have access
        if ($role === 1) {
            return true;
        }
        
        // Check user-specific and role-based permissions
        $sql = "SELECT COUNT(*) FROM gibbonReportTemplatePermission 
                WHERE gibbonReportTemplateID = ? 
                AND (gibbonPersonID = ? OR gibbonRoleID = ?)";
                
        $result = $this->db->executeQuery([$templateID, $user, $role]);
        return $result[0][0] > 0;
    }
    
    /**
     * Checks if the current user can edit a specific template
     *
     * @param int $templateID The ID of the template
     * @return bool True if the user can edit the template, false otherwise
     */
    public function canEditTemplate(int $templateID): bool
    {
        $user = $this->session->get('gibbonPersonID');
        $role = $this->session->get('gibbonRoleIDPrimary');
        
        // Only admins and template owners can edit
        $sql = "SELECT createdBy FROM gibbonReportTemplate 
                WHERE gibbonReportTemplateID = ?";
                
        $result = $this->db->executeQuery([$templateID]);
        
        return $role === 1 || ($result && $result[0]['createdBy'] === $user);
    }
}
```

### 2.2 Session Management

Secure session management is crucial for maintaining user authentication state safely.

```php
// Domain/Security/SessionManager.php
namespace Gibbon\Domain\Reports\Security;

class SessionManager
{
    private const SESSION_LIFETIME = 3600; // 1 hour
    
    /**
     * Starts a secure session with appropriate security settings
     */
    public function startSecureSession()
    {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
        ini_set('session.cookie_secure', 1);   // Only transmit cookie over HTTPS
        ini_set('session.use_only_cookies', 1); // Prevent session ID in URL
        ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF attacks
        
        // Set session lifetime
        ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
        session_set_cookie_params(self::SESSION_LIFETIME);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically to prevent session fixation
        if (!isset($_SESSION['last_regeneration'])) {
            $this->regenerateSession();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
            $this->regenerateSession();
        }
    }
    
    /**
     * Regenerates the session ID to prevent session fixation attacks
     */
    private function regenerateSession()
    {
        // Regenerate session ID and delete old session
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
```

## 3. Data Protection

Protecting sensitive data is crucial for maintaining user privacy and complying with data protection regulations.

### 3.1 Encryption Service

This service provides methods for encrypting and decrypting sensitive data.

```php
// Domain/Security/EncryptionService.php
namespace Gibbon\Domain\Reports\Security;

class EncryptionService
{
    private $key;
    
    public function __construct(string $key)
    {
        $this->key = $key;
    }
    
    /**
     * Encrypts data using AES-256-CBC encryption
     *
     * @param string $data The data to encrypt
     * @return string The encrypted data, base64 encoded
     */
    public function encrypt(string $data): string
    {
        $iv = random_bytes(16); // Generate a random initialization vector
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Combine IV and encrypted data for storage
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypts data that was encrypted with the encrypt method
     *
     * @param string $data The encrypted data to decrypt
     * @return string The decrypted data
     */
    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
}
```

### 3.2 Secure File Storage

This class manages secure file storage, including encryption and access control.

```php
// Domain/Security/FileStorage.php
namespace Gibbon\Domain\Reports\Security;

class FileStorage
{
    private $encryptionService;
    private $basePath;
    
    public function __construct(EncryptionService $encryptionService, string $basePath)
    {
        $this->encryptionService = $encryptionService;
        $this->basePath = $basePath;
    }
    
    /**
     * Stores a file securely with encryption
     *
     * @param string $content The file content
     * @param string $filename The original filename
     * @return string The secure filename used for storage
     */
    public function storeFile(string $content, string $filename): string
    {
        // Generate a secure, random filename to prevent guessing
        $secureFilename = bin2hex(random_bytes(16)) . '_' . $filename;
        $path = $this->basePath . '/' . $secureFilename;
        
        // Encrypt the file content before storage
        $encrypted = $this->encryptionService->encrypt($content);
        
        // Store the encrypted file
        file_put_contents($path, $encrypted);
        chmod($path, 0600); // Set restrictive file permissions
        
        return $secureFilename;
    }
    
    /**
     * Retrieves and decrypts a securely stored file
     *
     * @param string $filename The secure filename
     * @return string The decrypted file content
     */
    public function retrieveFile(string $filename): string
    {
        $path = $this->basePath . '/' . $filename;
        
        if (!file_exists($path)) {
            throw new \RuntimeException('File not found');
        }
        
        // Read and decrypt the file content
        $encrypted = file_get_contents($path);
        return $this->encryptionService->decrypt($encrypted);
    }
    
    /**
     * Securely deletes a file
     *
     * @param string $filename The secure filename
     */
    public function deleteFile(string $filename): void
    {
        $path = $this->basePath . '/' . $filename;
        
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
```

## 4. Security Monitoring

Monitoring and logging security events is crucial for detecting and responding to potential security threats.

### 4.1 Audit Logger

This class logs important actions and provides methods to retrieve the audit log.

```php
// Domain/Security/AuditLogger.php
namespace Gibbon\Domain\Reports\Security;

class AuditLogger
{
    private $db;
    private $session;
    
    public function __construct($db, $session)
    {
        $this->db = $db;
        $this->session = $session;
    }
    
    /**
     * Logs a security-relevant action
     *
     * @param string $action The action being performed
     * @param array $details Additional details about the action
     */
    public function logAction(string $action, array $details = []): void
    {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'userID' => $this->session->get('gibbonPersonID'),
            'ipAddress' => $_SERVER['REMOTE_ADDR'],
            'action' => $action,
            'details' => json_encode($details)
        ];
        
        $sql = "INSERT INTO gibbonReportAuditLog 
                (timestamp, gibbonPersonID, ipAddress, action, details) 
                VALUES (?, ?, ?, ?, ?)";
                
        $this->db->executeQuery([
            $data['timestamp'],
            $data['userID'],
            $data['ipAddress'],
            $data['action'],
            $data['details']
        ]);
    }
    
    /**
     * Retrieves the audit log, optionally filtered
     *
     * @param array $filters Optional filters for the log
     * @return array The filtered audit log entries
     */
    public function getAuditLog(array $filters = []): array
    {
        $sql = "SELECT * FROM gibbonReportAuditLog WHERE 1=1";
        $params = [];
        
        // Apply filters if provided
        if (!empty($filters['userID'])) {
            $sql .= " AND gibbonPersonID = ?";
            $params[] = $filters['userID'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['dateFrom'])) {
            $sql .= " AND timestamp >= ?";
            $params[] = $filters['dateFrom'];
        }
        
        $sql .= " ORDER BY timestamp DESC";
        
        return $this->db->executeQuery($sql, $params);
    }
}
```

### 4.2 Error Handler

This class provides custom error and exception handling, ensuring that errors are logged and handled appropriately.
