# Lesson 4: Security and Permissions

## Security Considerations

Security is crucial in educational software. This lesson covers essential security practices for GibbonEdu module development.

### Input Validation

1. **Form Input Validation**
```php
<?php
// equipment_process.php

// Get and validate input
$equipmentID = $_POST['equipmentID'] ?? '';
$name = $_POST['name'] ?? '';
$serialNumber = $_POST['serialNumber'] ?? '';
$condition = $_POST['condition'] ?? '';

// Validation class
class EquipmentValidator
{
    protected $errors = [];
    
    public function validate($data)
    {
        // Required fields
        if (empty($data['name'])) {
            $this->errors[] = __('Name is required');
        }
        
        // Length validation
        if (strlen($data['name']) > 50) {
            $this->errors[] = __('Name cannot exceed 50 characters');
        }
        
        // Pattern validation
        if (!empty($data['serialNumber']) && 
            !preg_match('/^[A-Z0-9-]{5,20}$/', $data['serialNumber'])) {
            $this->errors[] = __('Invalid serial number format');
        }
        
        // Enum validation
        $validConditions = ['New', 'Good', 'Fair', 'Poor'];
        if (!in_array($data['condition'], $validConditions)) {
            $this->errors[] = __('Invalid condition selected');
        }
        
        return empty($this->errors);
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
}

// Usage
$validator = new EquipmentValidator();
if (!$validator->validate($_POST)) {
    $URL .= '&error=' . urlencode(implode('<br/>', $validator->getErrors()));
    header("Location: {$URL}");
    exit();
}
```

2. **Database Input Sanitization**
```php
class EquipmentGateway extends QueryableGateway
{
    /**
     * Safely insert equipment with sanitized input
     */
    public function insertEquipment(array $data)
    {
        // Sanitize input
        $safe = array(
            'name' => substr(strip_tags($data['name']), 0, 50),
            'serialNumber' => preg_replace('/[^A-Z0-9-]/', '', 
                                         strtoupper($data['serialNumber'])),
            'condition' => in_array($data['condition'], 
                                  ['New', 'Good', 'Fair', 'Poor']) 
                          ? $data['condition'] : 'Good',
            'notes' => strip_tags($data['notes']),
            'dateAdded' => date('Y-m-d')
        );
        
        // Use QueryableGateway's insert method which uses prepared statements
        return $this->insert($safe);
    }
}
```

### SQL Injection Prevention

1. **Using QueryableGateway**
```php
// Safe query building
public function getEquipmentByCategory($category)
{
    // Safe - Uses prepared statements
    return $this
        ->newQuery()
        ->from('equipmentTrackerEquipment')
        ->where('category = :category')
        ->bindValue('category', $category)
        ->execute()
        ->fetchAll();
}

// Complex conditions
public function searchEquipment($criteria)
{
    $query = $this
        ->newQuery()
        ->from('equipmentTrackerEquipment')
        ->where('1=1');
        
    // Safe way to add multiple conditions
    if (!empty($criteria['name'])) {
        $query->where('name LIKE :name')
              ->bindValue('name', '%'.$criteria['name'].'%');
    }
    
    if (!empty($criteria['condition'])) {
        $query->where('condition = :condition')
              ->bindValue('condition', $criteria['condition']);
    }
    
    return $query->execute()->fetchAll();
}
```

### XSS Protection

1. **Output Escaping**
```php
// In your templates/views
<div class="equipment-details">
    <h3><?php echo Format::escape($equipment['name']); ?></h3>
    <p><?php echo Format::escape($equipment['description']); ?></p>
    
    <?php if (!empty($equipment['notes'])): ?>
        <div class="notes">
            <?php echo Format::htmlize($equipment['notes']); ?>
        </div>
    <?php endif; ?>
</div>

// For URLs
<a href="<?php echo Format::url($URL, array(
    'equipmentID' => $equipment['equipmentID']
)); ?>">
    <?php echo Format::escape($equipment['name']); ?>
</a>
```

2. **Form Token Protection**
```php
// In your form
$form = Form::create('equipmentAdd', '');
$form->addHiddenValue('address', $session->get('address'));

// Verify in process file
if ($session->get('address') != $_POST['address']) {
    $URL .= '&error=' . __('Invalid form submission');
    header("Location: {$URL}");
    exit();
}
```

### CSRF Protection

1. **Adding CSRF Token**
```php
// In your form page
$form = Form::create('equipmentAdd', '');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csrfToken', $session->get('csrfToken'));

// In your process page
if ($session->get('csrfToken') != $_POST['csrfToken']) {
    $URL .= '&error=' . __('Invalid security token');
    header("Location: {$URL}");
    exit();
}
```

## Permission System

### Role-Based Access Control

1. **Action Definitions**
```php
// In manifest.php
$actionRows[] = array(
    'name' => 'View Equipment',          // Action name
    'precedence' => '0',                 // Menu order
    'category' => 'Equipment',           // Sub-menu category
    'description' => 'View equipment list and details',
    'URLList' => 'equipment_view.php,equipment_detail.php',
    'entryURL' => 'equipment_view.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'Y',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N'
);

$actionRows[] = array(
    'name' => 'Manage Equipment',
    'precedence' => '1',
    'category' => 'Equipment',
    'description' => 'Add, edit and delete equipment',
    'URLList' => 'equipment_manage.php,equipment_manage_add.php,equipment_manage_edit.php,equipment_manage_delete.php',
    'entryURL' => 'equipment_manage.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N'
);
```

2. **Permission Checking**
```php
<?php
// Basic permission check
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_view.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Advanced permission checking
class EquipmentPermissions
{
    protected $guid;
    protected $connection;
    protected $gibbonPersonID;
    
    public function __construct($guid, $connection)
    {
        $this->guid = $guid;
        $this->connection = $connection;
        $this->gibbonPersonID = $session->get('gibbonPersonID');
    }
    
    public function canViewEquipment()
    {
        return isActionAccessible($this->guid, $this->connection, 
            '/modules/Equipment Tracker/equipment_view.php');
    }
    
    public function canManageEquipment()
    {
        return isActionAccessible($this->guid, $this->connection, 
            '/modules/Equipment Tracker/equipment_manage.php');
    }
    
    public function canEditEquipment($equipmentID)
    {
        // First check basic permission
        if (!$this->canManageEquipment()) {
            return false;
        }
        
        // Check specific equipment permissions
        $equipment = $this->getEquipment($equipmentID);
        if (empty($equipment)) {
            return false;
        }
        
        // Additional checks (e.g., department-specific permissions)
        return $this->hasEquipmentDepartmentAccess($equipment['departmentID']);
    }
    
    protected function hasEquipmentDepartmentAccess($departmentID)
    {
        // Check if user has access to this department
        $sql = "SELECT COUNT(*) FROM departmentStaff 
                WHERE gibbonPersonID=? AND departmentID=?";
        $result = $this->connection->prepare($sql);
        $result->execute([$this->gibbonPersonID, $departmentID]);
        
        return ($result->fetchColumn() > 0);
    }
}

// Usage
$permissions = new EquipmentPermissions($guid, $connection2);
if (!$permissions->canEditEquipment($_GET['equipmentID'])) {
    $page->addError(__('You do not have permission to edit this equipment.'));
    return;
}
```

### Custom Permission Levels

```php
// Define custom permission levels
$modulePermissions = array(
    'equipment_view' => array(
        'name' => 'View Equipment',
        'description' => 'View equipment list and details',
        'levels' => array(
            'none' => 'No Access',
            'own' => 'View Own Department',
            'all' => 'View All'
        )
    ),
    'equipment_manage' => array(
        'name' => 'Manage Equipment',
        'description' => 'Add, edit and delete equipment',
        'levels' => array(
            'none' => 'No Access',
            'add' => 'Add Only',
            'edit' => 'Add & Edit',
            'delete' => 'Full Access'
        )
    )
);

// Check custom permissions
function checkEquipmentPermission($permission, $minimumLevel)
{
    global $session;
    
    // Get user's permission level
    $sql = "SELECT permissionLevel FROM equipmentTrackerPermissions 
            WHERE gibbonPersonID=? AND permission=?";
    $result = $pdo->prepare($sql);
    $result->execute([$session->get('gibbonPersonID'), $permission]);
    $level = $result->fetchColumn();
    
    // Convert levels to numeric values for comparison
    $levels = array(
        'none' => 0,
        'own' => 1,
        'add' => 1,
        'edit' => 2,
        'all' => 3,
        'delete' => 3
    );
    
    return $levels[$level] >= $levels[$minimumLevel];
}
```

## Security Testing

### 1. Input Validation Testing
```php
class EquipmentSecurityTest
{
    public function testInputValidation()
    {
        $tests = array(
            // Test SQL injection attempts
            array(
                'input' => array(
                    'name' => "'; DROP TABLE users; --",
                    'serialNumber' => "12345' OR '1'='1",
                    'condition' => "New' OR '1'='1"
                ),
                'expectValid' => false
            ),
            
            // Test XSS attempts
            array(
                'input' => array(
                    'name' => "<script>alert('xss')</script>",
                    'serialNumber' => "12345<img src='x' onerror='alert(1)'>",
                    'condition' => "New"
                ),
                'expectValid' => false
            ),
            
            // Test valid input
            array(
                'input' => array(
                    'name' => "Test Equipment",
                    'serialNumber' => "12345-ABC",
                    'condition' => "New"
                ),
                'expectValid' => true
            )
        );
        
        $validator = new EquipmentValidator();
        
        foreach ($tests as $test) {
            $result = $validator->validate($test['input']);
            assert($result === $test['expectValid'], 
                   "Validation failed for test case");
        }
    }
}
```

### 2. Permission Testing
```php
class PermissionTest
{
    public function testPermissionLevels()
    {
        // Test cases
        $tests = array(
            array(
                'role' => 'admin',
                'action' => 'equipment_manage',
                'expectAccess' => true
            ),
            array(
                'role' => 'teacher',
                'action' => 'equipment_view',
                'expectAccess' => true
            ),
            array(
                'role' => 'student',
                'action' => 'equipment_manage',
                'expectAccess' => false
            )
        );
        
        foreach ($tests as $test) {
            // Set up test user
            $this->loginAs($test['role']);
            
            // Check permission
            $hasAccess = isActionAccessible($guid, $connection2, 
                '/modules/Equipment Tracker/' . $test['action'] . '.php');
            
            assert($hasAccess === $test['expectAccess'], 
                   "Permission check failed for {$test['role']}");
        }
    }
}
```

## Best Practices

1. **Input Validation**
   - Validate all user input
   - Use whitelisting over blacklisting
   - Validate on both client and server

2. **Database Security**
   - Use prepared statements
   - Escape special characters
   - Use proper indexing

3. **Output Escaping**
   - Always escape HTML output
   - Use proper encoding for URLs
   - Handle special characters

4. **Permission Management**
   - Use principle of least privilege
   - Check permissions consistently
   - Document access requirements

## Exercise: Implement Security Measures

1. Create Input Validator
```php
class YourValidator
{
    public function validate($input)
    {
        // Add validation rules
    }
}
```

2. Add Permission Checks
```php
// In your pages
if (!isActionAccessible($guid, $connection2, '/modules/YourModule/page.php')) {
    // Handle access denied
}
```

3. Implement CSRF Protection
```php
// Add to forms
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csrfToken', $session->get('csrfToken'));
```

## Common Mistakes to Avoid

1. **Insufficient Validation**
```php
// Bad
$id = $_GET['id'];

// Good
$id = isset($_GET['id']) ? abs(intval($_GET['id'])) : 0;
```

2. **Direct SQL Queries**
```php
// Bad
$sql = "SELECT * FROM table WHERE id = " . $_GET['id'];

// Good
$sql = "SELECT * FROM table WHERE id = :id";
$result = $pdo->prepare($sql);
$result->execute(['id' => $_GET['id']]);
```

3. **Missing Permission Checks**
```php
// Bad
function editRecord($id) {
    // Direct edit without checks
}

// Good
function editRecord($id) {
    if (!$this->permissions->canEdit($id)) {
        throw new PermissionException();
    }
    // Proceed with edit
}
```

## Next Steps

After completing this lesson:
1. Review your security measures
2. Implement comprehensive validation
3. Test permission system
4. Document security procedures

Continue to Module 4 to learn about best practices in code organization, testing, and documentation!
