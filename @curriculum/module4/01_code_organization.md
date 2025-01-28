# Lesson 1: Code Organization

## Directory Structure

A well-organized module follows GibbonEdu conventions and separates concerns effectively. Here's the recommended structure:

```plaintext
YourModule/
├── CHANGELOG.txt           # Version history
├── LICENCE                 # License information
├── README.md              # Module documentation
├── manifest.php           # Module configuration
├── moduleFunctions.php    # Shared functions
├── index.php             # Module entry point
├── src/                  # Source code
│   ├── Domain/           # Domain logic
│   │   ├── EquipmentGateway.php
│   │   ├── LoanGateway.php
│   │   └── Services/
│   │       ├── EquipmentService.php
│   │       └── LoanService.php
│   └── Forms/            # Form classes
│       ├── EquipmentForm.php
│       └── LoanForm.php
├── templates/            # Twig templates
│   ├── equipment.twig.html
│   └── loan.twig.html
├── assets/              # Static resources
│   ├── css/
│   │   └── module.css
│   ├── js/
│   │   └── module.js
│   └── images/
│       └── icon.png
├── i18n/                # Translations
│   ├── en_GB/
│   │   └── messages.php
│   └── es_ES/
│       └── messages.php
├── db/                  # Database
│   └── install.sql
├── tests/              # Unit tests
│   ├── EquipmentTest.php
│   └── LoanTest.php
└── docs/               # Documentation
    ├── INSTALL.md
    └── USAGE.md
```

### File Naming Conventions

1. **PHP Classes**
```php
// src/Domain/EquipmentGateway.php
namespace Gibbon\Module\EquipmentTracker\Domain;

class EquipmentGateway extends QueryableGateway
{
    // Class implementation
}

// src/Forms/EquipmentForm.php
namespace Gibbon\Module\EquipmentTracker\Forms;

class EquipmentForm
{
    // Class implementation
}
```

2. **Action Pages**
```php
// equipment_view.php
<?php
// Page for viewing equipment

// equipment_manage.php
<?php
// Page for managing equipment

// equipment_manage_add.php
<?php
// Page for adding new equipment
```

3. **Process Files**
```php
// equipment_manage_addProcess.php
<?php
// Process file for adding equipment

// equipment_manage_editProcess.php
<?php
// Process file for editing equipment
```

## Coding Standards

### PSR Standards

1. **PSR-1: Basic Coding Standard**
```php
<?php
namespace Gibbon\Module\EquipmentTracker;

class EquipmentManager
{
    public function processEquipment($data)
    {
        // Method implementation
    }
}
```

2. **PSR-2: Coding Style Guide**
```php
<?php
namespace Gibbon\Module\EquipmentTracker\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\QueryCriteria;

class EquipmentGateway extends QueryableGateway
{
    private static $tableName = 'equipmentTrackerEquipment';
    private static $primaryKey = 'equipmentID';
    
    public function selectEquipment(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName());
            
        return $this->runQuery($query, $criteria);
    }
    
    protected function formatQueryResults($results)
    {
        $output = [];
        foreach ($results as $result) {
            $output[] = $this->formatResult($result);
        }
        return $output;
    }
    
    private function formatResult($result)
    {
        return [
            'id' => $result['equipmentID'],
            'name' => $result['name'],
            'status' => $this->formatStatus($result['status'])
        ];
    }
}
```

3. **PSR-4: Autoloading**
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "Gibbon\\Module\\EquipmentTracker\\": "src/"
        }
    }
}
```

### GibbonEdu Specific Conventions

1. **Action Pages**
```php
<?php
// equipment_view.php

require_once '../../gibbon.php';

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_view.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Set up page properties
$page->breadcrumbs
    ->add(__('Equipment Tracker'), 'index.php')
    ->add(__('View Equipment'));

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Page content
$page->write('<h2>');
$page->write(__('View Equipment'));
$page->write('</h2>');

// Add content...
```

2. **Process Files**
```php
<?php
// equipment_manage_addProcess.php

require_once '../../gibbon.php';

// Module includes
include './moduleFunctions.php';

// Set up return URL
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Equipment Tracker/';

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_manage_add.php')) {
    $URL .= 'error.php&error='.__('Your request failed because you do not have access to this action.');
    header("Location: {$URL}");
    exit();
}

// Proceed!
$data = [
    'name' => $_POST['name'] ?? '',
    'description' => $_POST['description'] ?? '',
    'category' => $_POST['category'] ?? ''
];

try {
    // Process data
    $gateway = new EquipmentGateway($pdo);
    $inserted = $gateway->insert($data);
    
    if ($inserted) {
        $URL .= 'equipment_view.php&success=1';
    } else {
        $URL .= 'equipment_manage_add.php&error=Could not insert record';
    }
} catch (Exception $e) {
    $URL .= 'equipment_manage_add.php&error='.urlencode($e->getMessage());
}

header("Location: {$URL}");
```

### Documentation Standards

1. **Class Documentation**
```php
<?php
namespace Gibbon\Module\EquipmentTracker\Domain;

/**
 * Equipment Gateway
 *
 * Handles all database operations for equipment management
 *
 * @package   EquipmentTracker
 * @copyright 2024 Your Name
 * @license   GNU GPL v3
 * @since     v1.0.0
 */
class EquipmentGateway extends QueryableGateway
{
    /**
     * @var string Table name in the database
     */
    private static $tableName = 'equipmentTrackerEquipment';
    
    /**
     * Retrieves equipment by ID with associated loan information
     *
     * @param string $equipmentID
     * @return array|false Equipment data or false if not found
     * @throws PDOException On database error
     */
    public function getEquipmentByID($equipmentID)
    {
        // Method implementation
    }
}
```

2. **Function Documentation**
```php
/**
 * Process an equipment loan
 *
 * Creates a new loan record and updates equipment status
 *
 * @param array $data Loan data including:
 *                    - equipmentID (string): Equipment identifier
 *                    - gibbonPersonID (string): Person identifier
 *                    - dateExpected (string): Expected return date
 * @return array Result with keys:
 *               - success (bool): Whether operation succeeded
 *               - message (string): Success/error message
 * @throws Exception If required data is missing
 */
function processEquipmentLoan($data)
{
    // Function implementation
}
```

### Code Formatting

1. **Consistent Indentation**
```php
class EquipmentManager
{
    protected $gateway;
    
    public function __construct(EquipmentGateway $gateway)
    {
        $this->gateway = $gateway;
    }
    
    public function processEquipment($data)
    {
        if (empty($data)) {
            return false;
        }
        
        try {
            $result = $this->gateway->insert($data);
            if ($result) {
                return [
                    'success' => true,
                    'message' => __('Equipment added successfully')
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
```

2. **Alignment and Spacing**
```php
// Consistent array formatting
$equipment = [
    'name'        => $data['name'],
    'description' => $data['description'],
    'category'    => $data['category'],
    'condition'   => $data['condition'],
    'location'    => $data['location']
];

// Consistent operator spacing
$total = $quantity * $price + ($tax * $price);
$name  = ($firstName !== null) ? $firstName : $username;

// Consistent method chaining
$query = $this->newQuery()
    ->from($this->getTableName())
    ->where('active = :active')
    ->bindValue('active', 'Y');
```

## Best Practices

1. **Separation of Concerns**
   - Keep database logic in gateways
   - Keep business logic in services
   - Keep presentation logic in templates

2. **Single Responsibility**
   - Each class should have one purpose
   - Each method should do one thing
   - Each file should have one responsibility

3. **DRY (Don't Repeat Yourself)**
   - Use shared functions
   - Create reusable components
   - Maintain consistent patterns

4. **SOLID Principles**
   - Single Responsibility Principle
   - Open/Closed Principle
   - Liskov Substitution Principle
   - Interface Segregation Principle
   - Dependency Inversion Principle

## Exercise: Organize Your Module

1. Create Directory Structure
```plaintext
YourModule/
├── src/
│   └── Domain/
├── templates/
├── assets/
└── i18n/
```

2. Create Base Classes
```php
// src/Domain/YourGateway.php
namespace Gibbon\Module\YourModule\Domain;

class YourGateway extends QueryableGateway
{
    // Implement gateway
}

// src/Forms/YourForm.php
namespace Gibbon\Module\YourModule\Forms;

class YourForm
{
    // Implement form
}
```

3. Document Your Code
```php
/**
 * Your class description
 *
 * @package YourModule
 */
class YourClass
{
    /**
     * Your method description
     *
     * @param array $data Input data
     * @return bool Success/failure
     */
    public function yourMethod($data)
    {
        // Implementation
    }
}
```

## Common Mistakes to Avoid

1. **Inconsistent Naming**
```php
// Bad
class equipment_manager {}
function ProcessData() {}

// Good
class EquipmentManager {}
function processData() {}
```

2. **Mixed Responsibilities**
```php
// Bad - mixing concerns
class Equipment
{
    public function save()
    {
        // Database logic here
    }
    
    public function display()
    {
        // HTML output here
    }
}

// Good - separated concerns
class EquipmentGateway
{
    public function save($data)
    {
        // Database logic
    }
}

class EquipmentView
{
    public function render($equipment)
    {
        // Display logic
    }
}
```

3. **Poor File Organization**
```php
// Bad - everything in root
/YourModule
    equipment.php
    process.php
    style.css
    script.js

// Good - organized structure
/YourModule
    /src
        /Domain
            EquipmentGateway.php
    /assets
        /css
            module.css
        /js
            module.js
```

## Next Steps

After completing this lesson:
1. Review your module structure
2. Apply coding standards
3. Update documentation
4. Refactor if needed

In the next lesson, we'll learn about version control and managing your module's development!
