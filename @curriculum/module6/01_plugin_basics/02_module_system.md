# Understanding the Module System

This guide explains how GibbonEdu's module system works and how to integrate your Report Template module with the core platform.

## 1. Module Architecture

### 1.1 Module Lifecycle
GibbonEdu modules follow a specific lifecycle:

1. **Installation**
   - Module files copied to modules directory
   - manifest.php processed (defines module metadata, actions, and permissions)
   - Database tables created (as defined in install.sql)
   - Initial settings configured (from settings.php)
   - Actions and permissions registered in the system

2. **Activation**
   - Module enabled in System Admin
   - Menu items become visible in the navigation
   - Hooks registered (allowing the module to integrate with core functions)
   - Background processes started (if any are defined)

3. **Updates**
   - New version detected (based on version number in manifest.php)
   - Database migrations run (from update.php)
   - New settings applied (any additions to settings.php)
   - New permissions added (changes in manifest.php)

4. **Deactivation**
   - Module disabled in System Admin
   - Menu items hidden from navigation
   - Hooks unregistered (module no longer affects core functions)
   - Background processes stopped

### 1.2 Integration Points
Your module integrates with GibbonEdu through several mechanisms:

```php
<?php
// 1. Database Connection
// Use the Connection interface for database operations
use Gibbon\Contracts\Database\Connection;

class TemplateGateway extends QueryableGateway
{
    private $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }
    
    // Methods for database operations...
}

// 2. Session Management
// Access user session data securely
use Gibbon\Contracts\Services\Session;

class TemplateService
{
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }
    
    // Methods using session data...
}

// 3. Container Services
// Utilize core services for formatting and settings
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

class ReportService
{
    private $formatter;
    private $settingGateway;

    public function __construct(Format $formatter, SettingGateway $settingGateway)
    {
        $this->formatter = $formatter;
        $this->settingGateway = $settingGateway;
    }
    
    // Methods for report generation and formatting...
}
```

## 2. Module Configuration

### 2.1 Settings Management
Create a settings file (settings.php) for your module to define configurable options:

```php
<?php
// settings.php
return [
    'enableReportTemplates' => [
        'name' => 'Enable Report Templates',
        'description' => 'Allow creation and use of report templates',
        'type' => 'select',
        'options' => ['Y' => 'Yes', 'N' => 'No'],
        'default' => 'Y',
    ],
    'defaultTemplate' => [
        'name' => 'Default Template',
        'description' => 'Template to use by default',
        'type' => 'text',
        'default' => '',
    ],
    // Add more settings as needed...
];
```

### 2.2 Accessing Settings
Use the SettingGateway to manage and retrieve settings:

```php
<?php
use Gibbon\Domain\System\SettingGateway;

class ReportService
{
    private $settingGateway;

    public function __construct(SettingGateway $settingGateway)
    {
        $this->settingGateway = $settingGateway;
    }

    public function isEnabled(): bool
    {
        // Retrieve the 'enableReportTemplates' setting for this module
        return $this->settingGateway->getSettingByScope(
            'Report Template',
            'enableReportTemplates'
        ) === 'Y';
    }
    
    // Other methods using settings...
}
```

## 3. Module Actions

### 3.1 Defining Actions
Actions are defined in manifest.php to create menu items and set permissions:

```php
$actionRows[] = [
    'name' => 'Generate Report',          // Action name (appears in menu)
    'precedence' => '0',                  // Menu order (lower numbers appear first)
    'category' => 'Reports',              // Menu category for grouping
    'description' => 'Generate reports from templates',
    'URLList' => 'reports_generate.php,reports_view.php',
    'entryURL' => 'reports_generate.php', // Main page for this action
    'entrySidebar' => 'Y',                // Show in sidebar menu
    'menuShow' => 'Y',                    // Show in main menu
    'defaultPermissionAdmin' => 'Y',      // Default access for Admin
    'defaultPermissionTeacher' => 'Y',    // Default access for Teachers
    'defaultPermissionStudent' => 'N',    // Default access for Students
    'defaultPermissionParent' => 'N',     // Default access for Parents
    'defaultPermissionSupport' => 'N',    // Default access for Support staff
    'categoryPermissionStaff' => 'Y',     // Access for Staff category
    'categoryPermissionStudent' => 'N',   // Access for Student category
    'categoryPermissionParent' => 'N',    // Access for Parent category
    'categoryPermissionOther' => 'N'      // Access for Other category
];
```

### 3.2 Action Pages
Create action pages that handle specific functionality:

```php
<?php
// reports_generate.php
include '../../gibbon.php';

// Check module access
if (!isActionAccessible($guid, $connection2, '/modules/ReportTemplate/reports_generate.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Add page navigation
$page->breadcrumbs
    ->add(__('Generate Report'));

// Get available templates
$templateGateway = $container->get(TemplateGateway::class);
$templates = $templateGateway->selectActiveTemplates()->fetchAll();

// Display form
$form = Form::create('generateReport', $session->get('absoluteURL').'/modules/ReportTemplate/reports_generateProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow();
    $row->addLabel('template', __('Template'));
    $row->addSelect('template')
        ->fromArray($templates)
        ->required()
        ->placeholder('Please select...');

$row = $form->addRow();
    $row->addSubmit('Generate Report');

echo $form->getOutput();
```

## 4. Module Tables

### 4.1 Table Structure
Follow these conventions for table creation:

```sql
-- Primary tables use module name prefix
CREATE TABLE `reportTemplateTemplate` (
    `id` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `active` ENUM('Y','N') DEFAULT 'Y',
    -- Always include creator and timestamp
    `gibbonPersonIDCreator` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Include modifier for audit trail
    `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NULL,
    `timestampModified` TIMESTAMP NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Link tables use both table names
CREATE TABLE `reportTemplateTemplateStudent` (
    `reportTemplateTemplateID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonStudentID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (`reportTemplateTemplateID`, `gibbonStudentID`),
    FOREIGN KEY (`reportTemplateTemplateID`) REFERENCES `reportTemplateTemplate` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`gibbonStudentID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 4.2 Index Optimization
Add appropriate indexes for performance:

```sql
-- Index foreign keys
ALTER TABLE `reportTemplateTemplate`
ADD INDEX `creator` (`gibbonPersonIDCreator`),
ADD INDEX `modifier` (`gibbonPersonIDModified`);

-- Index commonly searched fields
ALTER TABLE `reportTemplateTemplate`
ADD INDEX `active_name` (`active`, `name`);

-- Index for efficient joins
ALTER TABLE `reportTemplateTemplateStudent`
ADD INDEX `gibbonStudentID` (`gibbonStudentID`);
```

## Practical Example
We'll implement the core configuration for the Report Template module, demonstrating each concept in practice.

1. Create the module directory structure:
   ```
   ReportTemplate/
   ├── manifest.php
   ├── settings.php
   ├── install.sql
   ├── update.php
   ├── CHANGELOG.txt
   ├── templates/
   ├── src/
   │   ├── Domain/
   │   └── Services/
   └── actions/
       ├── reports_generate.php
       └── reports_view.php
   ```

2. Define the module in manifest.php
3. Create database tables in install.sql
4. Implement core classes in src/
5. Create action pages in actions/
6. Add settings in settings.php

This structure provides a solid foundation for building a comprehensive Report Template module in GibbonEdu.
