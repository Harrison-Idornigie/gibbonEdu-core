# Lesson 4: Documentation

## Code Documentation

### PHPDoc Standards

1. **File Documentation**
```php
<?php
/**
 * Equipment Tracker Module
 *
 * @package     Module
 * @subpackage  EquipmentTracker
 * @category    Equipment
 * @version     v1.0.0
 * @since       v23.0.0
 * @copyright   2024 Your Name
 * @license     GNU GPL v3
 */

namespace Gibbon\Module\EquipmentTracker\Domain;
```

2. **Class Documentation**
```php
/**
 * Equipment Gateway
 *
 * Handles all database operations for equipment management
 *
 * @package     Module
 * @subpackage  EquipmentTracker
 * @author      Your Name <your.email@example.com>
 */
class EquipmentGateway extends QueryableGateway
{
    /**
     * @var string Database table name
     */
    private static $tableName = 'equipmentTrackerEquipment';
    
    /**
     * @var array Searchable columns for the equipment table
     */
    private static $searchableColumns = ['name', 'category', 'serialNumber'];
}
```

3. **Method Documentation**
```php
/**
 * Process an equipment loan request
 *
 * Creates a new loan record and updates equipment status
 *
 * @param array $data Loan request data containing:
 *                    - equipmentID (string): Equipment identifier
 *                    - gibbonPersonID (string): Person identifier
 *                    - dateOut (string): Loan start date (Y-m-d)
 *                    - dateReturn (string): Expected return date (Y-m-d)
 * 
 * @return array Result containing:
 *               - success (bool): Operation success status
 *               - message (string): Success/error message
 *               - loanID (string): New loan identifier (if successful)
 * 
 * @throws PDOException On database error
 * @throws ValidationException If data is invalid
 */
public function processLoanRequest(array $data): array
{
    // Method implementation
}
```

4. **Property Documentation**
```php
class Equipment
{
    /**
     * @var string Unique identifier
     */
    private $id;
    
    /**
     * @var string Equipment name
     */
    private $name;
    
    /**
     * @var string Current status (Available, On Loan, Under Repair)
     */
    private $status;
    
    /**
     * @var DateTime|null Date when equipment was added
     */
    private $dateAdded;
}
```

### Inline Comments

1. **Code Section Comments**
```php
class EquipmentService
{
    // Database gateways
    private $equipmentGateway;
    private $loanGateway;
    
    // Validation rules
    private $rules = [
        'name' => 'required|max:100',
        'category' => 'required',
        'serialNumber' => 'required|unique:equipment'
    ];
    
    public function processEquipment($data)
    {
        // Validate input data
        if (!$this->validate($data)) {
            return false;
        }
        
        // Process equipment record
        try {
            // Begin transaction
            $this->pdo->beginTransaction();
            
            // Insert equipment record
            $equipmentID = $this->equipmentGateway->insert($data);
            
            // Create initial status record
            $this->createStatusRecord($equipmentID, 'Available');
            
            // Commit transaction
            $this->pdo->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback on error
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
```

2. **Complex Logic Comments**
```php
class LoanService
{
    public function calculateLoanDuration($dateOut, $dateReturn)
    {
        // Convert dates to timestamps
        $start = strtotime($dateOut);
        $end = strtotime($dateReturn);
        
        // Calculate difference in days
        $diffDays = floor(($end - $start) / (60 * 60 * 24));
        
        // Adjust for school days only
        $schoolDays = 0;
        for ($i = 0; $i <= $diffDays; $i++) {
            $currentDate = strtotime("+$i days", $start);
            
            // Skip weekends
            if (date('N', $currentDate) >= 6) {
                continue;
            }
            
            // Skip holidays
            if ($this->isHoliday(date('Y-m-d', $currentDate))) {
                continue;
            }
            
            $schoolDays++;
        }
        
        return $schoolDays;
    }
}
```

### README Files

1. **Main README**
```markdown
# Equipment Tracker Module

## Description
The Equipment Tracker module helps schools manage their equipment inventory
and loan system. Track equipment status, manage loans, and generate reports.

## Features
- Equipment inventory management
- Loan tracking system
- Status updates
- Reports generation
- Barcode integration

## Requirements
- Gibbon v23.0.0 or later
- PHP 7.4 or later
- MySQL 5.7 or later

## Installation
1. Copy files to `/modules/Equipment Tracker`
2. Log in as administrator
3. Go to Admin > System Admin > Manage Modules
4. Click "Install"

## Configuration
1. Set up equipment categories
2. Configure loan periods
3. Set user permissions
4. Customize notification settings

## Usage
See [User Guide](docs/USAGE.md) for detailed instructions.

## Contributing
1. Fork the repository
2. Create feature branch
3. Submit pull request

## License
GNU General Public License v3.0

## Support
- [Issue Tracker](https://github.com/your/repo/issues)
- [Documentation](docs/)
```

2. **Technical README**
```markdown
# Technical Documentation

## Architecture

### Directory Structure
```plaintext
EquipmentTracker/
├── src/
│   ├── Domain/
│   │   ├── Equipment.php
│   │   ├── EquipmentGateway.php
│   │   └── Services/
│   │       └── EquipmentService.php
│   └── Forms/
│       └── EquipmentForm.php
└── templates/
    └── equipment.twig.html
```

### Database Schema
```sql
CREATE TABLE `equipmentTrackerEquipment` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `serialNumber` VARCHAR(50) UNIQUE,
    `status` ENUM('Available','On Loan','Under Repair') NOT NULL,
    `dateAdded` DATE NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### Class Dependencies
- EquipmentService depends on:
  - EquipmentGateway
  - LoanGateway
  - NotificationService

## Development

### Setup Development Environment
1. Install dependencies
2. Configure database
3. Set up test data

### Coding Standards
- Follow PSR-1 and PSR-2
- Use meaningful variable names
- Document all classes and methods

### Testing
- Run unit tests: `./vendor/bin/phpunit`
- Run integration tests: `./vendor/bin/phpunit --testsuite integration`
```

## User Documentation

### Installation Guide
```markdown
# Installation Guide

## Prerequisites
1. Gibbon v23.0.0 or later
2. Administrative access
3. Database access

## Installation Steps

### 1. Download Module
- Download latest release
- Extract files to `/modules/Equipment Tracker`

### 2. Database Setup
- Log in as administrator
- Go to System Admin > Manage Modules
- Click "Install"
- Verify database tables created

### 3. Configuration
- Set up equipment categories
- Configure loan settings
- Set permissions

### 4. Verification
- Add test equipment
- Create test loan
- Verify notifications

## Troubleshooting

### Common Issues
1. Database Error
   - Check permissions
   - Verify table creation

2. Access Denied
   - Check role permissions
   - Verify module activation

### Support
Contact support@example.com for assistance
```

### User Manual
```markdown
# User Manual

## Equipment Management

### Adding Equipment
1. Navigate to Equipment > Add Equipment
2. Fill required fields:
   - Name
   - Category
   - Serial Number
3. Click "Save"

### Managing Loans
1. Find equipment
2. Click "Create Loan"
3. Select borrower
4. Set dates
5. Confirm

### Generating Reports
1. Go to Reports
2. Select report type:
   - Inventory
   - Loan History
   - Overdue Items
3. Set parameters
4. Click "Generate"

## Administrator Guide

### User Management
1. Setting Permissions
   - Access Admin > Manage Permissions
   - Configure role access
   - Save changes

2. Managing Categories
   - Go to Settings
   - Add/Edit categories
   - Set loan limits

### System Settings
1. Notification Setup
   - Configure email templates
   - Set reminder schedule
   - Test notifications

2. Backup/Restore
   - Regular backups
   - Restore procedure
   - Data verification
```

### Configuration Guide
```markdown
# Configuration Guide

## System Settings

### Database Configuration
```php
// config.php
$databaseSettings = [
    'host' => 'localhost',
    'name' => 'gibbon_db',
    'user' => 'db_user',
    'pass' => 'db_pass'
];
```

### Module Settings
```php
// settings.php
$moduleSettings = [
    'loanDuration' => 14, // days
    'reminderDays' => 2,  // days before due
    'categories' => [
        'Electronics',
        'Books',
        'Sports'
    ]
];
```

## Customization

### Email Templates
```html
<!-- templates/loan_reminder.html -->
<h2>Loan Reminder</h2>
<p>Dear {name},</p>
<p>Your loan of {equipment} is due on {date}.</p>
```

### Report Templates
```php
// reports/inventory.php
$reportConfig = [
    'title' => 'Inventory Report',
    'columns' => [
        'name' => 'Equipment Name',
        'category' => 'Category',
        'status' => 'Status'
    ]
];
```
```

### Troubleshooting Guide
```markdown
# Troubleshooting Guide

## Common Issues

### Database Errors
1. Connection Failed
   - Check credentials
   - Verify database exists
   - Test connection: 
     ```php
     try {
         $pdo = new PDO($dsn, $user, $pass);
     } catch (PDOException $e) {
         echo $e->getMessage();
     }
     ```

2. Table Creation Failed
   - Check permissions
   - Verify SQL syntax
   - Run manually:
     ```sql
     CREATE TABLE IF NOT EXISTS...
     ```

### Access Issues
1. Permission Denied
   - Check role settings
   - Verify action rights
   - Debug access:
     ```php
     if (!isActionAccessible($guid, $connection2, '/modules/...')) {
         print_r($session->get('modulePermissions'));
     }
     ```

2. Module Not Found
   - Check installation
   - Verify file paths
   - Debug module:
     ```php
     echo __DIR__;
     print_r($gibbon->getModule('Equipment Tracker'));
     ```

## Debugging

### Enable Debug Mode
```php
// config.php
$debugMode = true;

if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
```

### Check Logs
```bash
tail -f /path/to/error.log
```

### Test Database
```sql
-- Check tables
SHOW TABLES LIKE 'equipmentTracker%';

-- Verify data
SELECT * FROM equipmentTrackerEquipment LIMIT 5;
```
```

## Exercise: Create Documentation

1. Create Basic Structure
```plaintext
docs/
├── README.md
├── INSTALL.md
├── USAGE.md
├── technical/
│   ├── architecture.md
│   └── api.md
└── user/
    ├── manual.md
    └── troubleshooting.md
```

2. Document Code
```php
/**
 * Your class description
 */
class YourClass
{
    /**
     * Your method description
     */
    public function yourMethod()
    {
        // Implementation
    }
}
```

3. Write User Guide
```markdown
# User Guide

## Getting Started
1. Installation
2. Configuration
3. Basic Usage
```

## Common Mistakes to Avoid

1. **Outdated Documentation**
```php
// Bad - outdated docs
/**
 * @deprecated Use newMethod() instead
 */
public function oldMethod()
{
    // Still in use but docs say deprecated
}

// Good - accurate docs
/**
 * Current method description
 */
public function currentMethod()
{
    // Implementation matches docs
}
```

2. **Missing Context**
```php
// Bad - lacks context
function process($data) {
    // Process something
}

// Good - clear context
/**
 * Process equipment loan request
 *
 * @param array $data Loan request data
 */
function processLoanRequest($data) {
    // Process loan
}
```

3. **Inconsistent Style**
```php
// Bad - mixed styles
class Equipment {
    // Some docs with periods, some without
    /** Gets the name. */
    function getName() {}
    
    /** @return string status */
    function getStatus() {}
}

// Good - consistent style
class Equipment {
    /**
     * Get equipment name
     *
     * @return string
     */
    function getName() {}
    
    /**
     * Get equipment status
     *
     * @return string
     */
    function getStatus() {}
}
```

## Next Steps

After completing this lesson:
1. Review existing documentation
2. Update code comments
3. Create user guides
4. Set up documentation workflow

Continue to Module 5 to learn about deploying and maintaining your module!
