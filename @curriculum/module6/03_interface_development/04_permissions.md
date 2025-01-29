# Permissions and Access Control

This comprehensive guide explains how to implement a robust permissions and access control system in your Report Template module. We'll cover role-based access control, template-specific permissions, and report access control.

## 1. Role-Based Access Control (RBAC)

RBAC is a method of regulating access to computer or network resources based on the roles of individual users within an organization.

### 1.1 Module Permissions
Define default permissions in `manifest.php`. These permissions will be used as a baseline for all users with a specific role.

```php
<?php
// manifest.php

// Define default permissions for each role
$modulePermissions = [
    'admin' => [
        'manageTemplates' => true,  // Can manage all templates
        'createReports' => true,    // Can create new reports
        'viewAllReports' => true,   // Can view all reports in the system
        'deleteReports' => true,    // Can delete any report
    ],
    'teacher' => [
        'manageTemplates' => false, // Cannot manage templates
        'createReports' => true,    // Can create reports for their students
        'viewAllReports' => false,  // Can only view reports for their students
        'deleteReports' => false,   // Cannot delete reports
    ],
    'student' => [
        'manageTemplates' => false, // Cannot manage templates
        'createReports' => false,   // Cannot create reports
        'viewAllReports' => false,  // Can only view their own reports
        'deleteReports' => false,   // Cannot delete reports
    ],
    'parent' => [
        'manageTemplates' => false, // Cannot manage templates
        'createReports' => false,   // Cannot create reports
        'viewAllReports' => false,  // Can only view their children's reports
        'deleteReports' => false,   // Cannot delete reports
    ],
    'support' => [
        'manageTemplates' => false, // Cannot manage templates
        'createReports' => false,   // Cannot create reports
        'viewAllReports' => false,  // Cannot view reports
        'deleteReports' => false,   // Cannot delete reports
    ]
];

// Add module record
$moduleName = 'Report Template';
$moduleDescription = 'A module for creating and managing report templates';

// Add module tables
// This table stores the actual permissions for each role
$sql = "CREATE TABLE IF NOT EXISTS reportTemplatePermissions (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    gibbonRoleID INT(3) UNSIGNED ZEROFILL,
    permission VARCHAR(50),
    value ENUM('Y','N') DEFAULT 'N',
    PRIMARY KEY (id),
    UNIQUE KEY unique_permission (gibbonRoleID, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
```

### 1.2 Permission Checks
Implement permission checks in your code to ensure users only access features they're allowed to use.

```php
<?php
// templates_manage.php

use Gibbon\Module\ReportTemplate\Domain\PermissionGateway;

// Get permission gateway from the service container
$permissionGateway = $container->get(PermissionGateway::class);

// Check if the user has access to this action
if (!isActionAccessible($guid, $connection2, '/modules/ReportTemplate/templates_manage.php')) {
    // If not, redirect to the homepage
    $URL = $session->get('absoluteURL').'/index.php';
    header("Location: {$URL}");
    exit;
}

// Check if the user has the specific permission to manage templates
$canManageTemplates = $permissionGateway->hasPermission($session->get('gibbonRoleIDCurrent'), 'manageTemplates');
if (!$canManageTemplates) {
    // If not, redirect to the module's main page with an error
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/ReportTemplate/templates_manage.php';
    header("Location: {$URL}&return=error0");
    exit;
}

// If we've reached this point, the user has the necessary permissions
// Continue with the rest of the page logic...
```

### 1.3 Permission Gateway
Create a permission gateway class to handle database operations related to permissions.

```php
<?php
// src/Domain/PermissionGateway.php

namespace Gibbon\Module\ReportTemplate\Domain;

use Gibbon\Domain\Gateway;

class PermissionGateway extends Gateway
{
    private static $tableName = 'reportTemplatePermissions';
    
    /**
     * Check if a role has a specific permission
     *
     * @param int $roleID The ID of the role
     * @param string $permission The permission to check
     * @return bool True if the role has the permission, false otherwise
     */
    public function hasPermission($roleID, $permission)
    {
        $sql = "SELECT value 
                FROM reportTemplatePermissions 
                WHERE gibbonRoleID=:roleID 
                AND permission=:permission";
                
        $result = $this->db->selectOne($sql, [
            'roleID' => $roleID,
            'permission' => $permission
        ]);
        
        // If the permission is not set, default to 'N'
        return ($result['value'] ?? 'N') == 'Y';
    }
    
    /**
     * Set a permission for a role
     *
     * @param int $roleID The ID of the role
     * @param string $permission The permission to set
     * @param string $value 'Y' or 'N'
     * @return bool True if the operation was successful, false otherwise
     */
    public function setPermission($roleID, $permission, $value)
    {
        $data = [
            'gibbonRoleID' => $roleID,
            'permission' => $permission,
            'value' => $value
        ];
        
        // Check if the permission already exists
        $exists = $this->hasPermission($roleID, $permission);
        
        if ($exists) {
            // Update existing permission
            return $this->update($data);
        } else {
            // Insert new permission
            return $this->insert($data);
        }
    }
    
    /**
     * Get all permissions for a role
     *
     * @param int $roleID The ID of the role
     * @return array An array of permissions and their values
     */
    public function getPermissionsByRole($roleID)
    {
        $sql = "SELECT permission, value 
                FROM reportTemplatePermissions 
                WHERE gibbonRoleID=:roleID";
                
        return $this->db->select($sql, [
            'roleID' => $roleID
        ]);
    }
}
```

## 2. Template Access Control

In addition to role-based permissions, implement template-specific access control to allow fine-grained control over who can view, edit, and delete individual templates.

### 2.1 Template Permissions
Add template-specific permissions to your Template class:

```php
<?php
// src/Domain/Template.php

class Template
{
    private $id;
    private $name;
    private $createdBy;
    private $shared;
    private $accessRoles;
    
    /**
     * Check if a user can view this template
     *
     * @param int $roleID The role ID of the user
     * @return bool True if the user can view the template, false otherwise
     */
    public function canView($roleID)
    {
        // The creator can always view the template
        if ($this->createdBy == $session->get('gibbonPersonID')) {
            return true;
        }
        
        // If the template is not shared, only the creator can view it
        if (!$this->shared) {
            return false;
        }
        
        // Check if the user's role has access to this template
        return in_array($roleID, $this->accessRoles);
    }
    
    /**
     * Check if a user can edit this template
     *
     * @param int $roleID The role ID of the user
     * @return bool True if the user can edit the template, false otherwise
     */
    public function canEdit($roleID)
    {
        // Only the creator can edit the template
        return $this->createdBy == $session->get('gibbonPersonID');
    }
    
    /**
     * Check if a user can delete this template
     *
     * @param int $roleID The role ID of the user
     * @return bool True if the user can delete the template, false otherwise
     */
    public function canDelete($roleID)
    {
        // The creator can always delete the template
        if ($this->createdBy == $session->get('gibbonPersonID')) {
            return true;
        }
        
        // Admins can also delete templates
        return $permissionGateway->hasPermission($roleID, 'deleteTemplates');
    }
}
```

### 2.2 Access Control Table
Create a database table to store template access permissions:

```sql
CREATE TABLE reportTemplateAccess (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL,
    gibbonRoleID INT(3) UNSIGNED ZEROFILL,
    canView ENUM('Y','N') DEFAULT 'N',
    canEdit ENUM('Y','N') DEFAULT 'N',
    canDelete ENUM('Y','N') DEFAULT 'N',
    PRIMARY KEY (id),
    UNIQUE KEY unique_access (templateID, gibbonRoleID),
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE,
    FOREIGN KEY (gibbonRoleID) REFERENCES gibbonRole(gibbonRoleID)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 2.3 Access Control Forms
Add forms for managing template access:

```php
<?php
// templates_manage_access.php

// Fetch all roles from the database
$sql = "SELECT gibbonRoleID, name FROM gibbonRole ORDER BY name";
$roles = $pdo->select($sql)->fetchAll();

// Fetch current access settings for this template
$sql = "SELECT gibbonRoleID, canView, canEdit, canDelete 
        FROM reportTemplateAccess 
        WHERE templateID=:templateID";
$access = $pdo->select($sql, ['templateID' => $templateID])->fetchAll();

// Create an array to store current access settings
$currentAccess = [];
foreach ($access as $item) {
    $currentAccess[$item['gibbonRoleID']] = [
        'view' => $item['canView'],
        'edit' => $item['canEdit'],
        'delete' => $item['canDelete']
    ];
}

// Create the form
$form = Form::create('templateAccess', '');

$row = $form->addRow()->addHeading(__('Role Access'));

// Add checkboxes for each role and permission
foreach ($roles as $role) {
    $row = $form->addRow();
    $row->addLabel($role['name']);
    
    // View permission checkbox
    $row->addCheckbox("access[{$role['gibbonRoleID']}][view]")
        ->checked($currentAccess[$role['gibbonRoleID']]['view'] ?? 'N')
        ->setClass('text-center');
        
    // Edit permission checkbox
    $row->addCheckbox("access[{$role['gibbonRoleID']}][edit]")
        ->checked($currentAccess[$role['gibbonRoleID']]['edit'] ?? 'N')
        ->setClass('text-center');
        
    // Delete permission checkbox
    $row->addCheckbox("access[{$role['gibbonRoleID']}][delete]")
        ->checked($currentAccess[$role['gibbonRoleID']]['delete'] ?? 'N')
        ->setClass('text-center');
}

$row = $form->addRow();
    $row->addSubmit();

echo $form->getOutput();
```

## 3. Report Access Control

Implement access control for individual reports to ensure that only authorized users can view, edit, or delete reports.

### 3.1 Report Permissions
Add report-specific permissions to your Report class:
