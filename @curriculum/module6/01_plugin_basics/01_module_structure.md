# Module Structure and Required Files

This comprehensive guide will walk you through creating the basic structure for the Report Template module. We'll build a robust module that empowers schools to create, manage, and utilize custom report templates efficiently.

## 1. Initial Setup

### 1.1 Create Module Directory
First, we need to set up the module directory within the Gibbon framework:

```bash
# Navigate to your Gibbon installation's modules directory
cd modules

# Create the ReportTemplate module directory
mkdir ReportTemplate
cd ReportTemplate
```

### 1.2 Essential Files
Create these essential files in your module directory. Each file serves a specific purpose in the module's functionality:

```plaintext
ReportTemplate/
├── CHANGELOG.txt           # Detailed version history and update notes
├── LICENCE                 # Legal information about module usage and distribution
├── README.md               # Comprehensive module documentation and setup instructions
├── manifest.php            # Core module configuration and integration details
├── moduleFunctions.php     # Shared functions used across the module
├── version.php             # Code version number for update management
├── index.php               # Module landing page and entry point
├── templates_manage.php    # Interface for managing report templates
├── templates_edit.php      # Editor for creating and modifying templates
├── reports_generate.php    # Page for generating reports from templates
├── reports_view.php        # Interface for viewing generated reports
├── css/
│   └── module.css          # Module-specific styles for consistent UI
├── js/
│   └── module.js           # Module-specific scripts for enhanced functionality
├── src/
│   └── Domain/             # Core business logic and database interaction
│       ├── TemplateGateway.php   # Database operations for templates
│       ├── ReportGateway.php     # Database operations for reports
│       └── Services/
│           ├── TemplateService.php  # Business logic for template management
│           └── ReportService.php    # Business logic for report generation
├── templates/              # Storage for report template files
└── reports/                # Storage for generated report files
```

## 2. Key Files Explained

### 2.1 manifest.php
This crucial file configures your module within the Gibbon framework. Here's a detailed example with comments:

```php
<?php
// Basic module information
$name = 'Report Template';
$description = 'Create and manage custom report templates for student reports, analytics, and more.';
$entryURL = 'templates_manage.php';
$type = 'Additional';  // Indicates this is an optional, add-on module
$category = 'Reports';  // Categorizes the module for easier navigation
$version = '1.0.00';  // Follow semantic versioning (MAJOR.MINOR.PATCH)
$author = 'Your Name';
$url = 'https://github.com/yourusername/ReportTemplate';

// Module tables - Define database structure
$moduleTables[] = "CREATE TABLE `reportTemplateTemplate` (
    `id` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(90) NOT NULL,
    `description` TEXT,
    `template` MEDIUMTEXT NOT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `gibbonPersonIDCreator` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NULL,
    `timestampModified` TIMESTAMP NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Module actions (pages) - Define accessible pages and permissions
$actionRows[] = [
    'name' => 'Manage Templates',  // Action name in the module
    'precedence' => '0',  // Order in the module's menu
    'category' => 'Templates',  // Subgroup in the module's menu
    'description' => 'Create and manage report templates',
    'URLList' => 'templates_manage.php,templates_edit.php',  // Comma-separated list of related pages
    'entryURL' => 'templates_manage.php',  // Main page for this action
    'entrySidebar' => 'Y',  // Show in sidebar menu
    'menuShow' => 'Y',  // Display in main menu
    'defaultPermissionAdmin' => 'Y',  // Access for administrators
    'defaultPermissionTeacher' => 'N',  // Access for teachers
    'defaultPermissionStudent' => 'N',  // Access for students
    'defaultPermissionParent' => 'N',  // Access for parents
    'defaultPermissionSupport' => 'N',  // Access for support staff
    'categoryPermissionStaff' => 'Y',  // Access for general staff
    'categoryPermissionStudent' => 'N',  // Student category access
    'categoryPermissionParent' => 'N',  // Parent category access
    'categoryPermissionOther' => 'N'  // Other user type access
];
```

### 2.2 moduleFunctions.php
This file contains shared functions used across your module. Here's an example with detailed comments:

```php
<?php
/**
 * Report Template Module Functions
 *
 * @package Module Report Template
 */

use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;
use Gibbon\Module\ReportTemplate\Domain\ReportGateway;

/**
 * Retrieves available report templates
 *
 * @param \PDO $pdo Database connection object
 * @param bool $activeOnly If true, only return active templates
 * @return array An array of template objects
 */
function getReportTemplates($pdo, $activeOnly = true) {
    $templateGateway = new TemplateGateway($pdo);
    $criteria = $templateGateway->newQueryCriteria()
        ->sortBy(['name']);  // Sort templates by name
    
    if ($activeOnly) {
        $criteria->filterBy('active', 'Y');  // Only include active templates
    }
    
    return $templateGateway->queryTemplates($criteria);
}

/**
 * Generates a report from a template
 *
 * @param \PDO $pdo Database connection object
 * @param int $templateID The ID of the template to use
 * @param array $data An associative array of data to populate the report
 * @return string The generated report content
 */
function generateReport($pdo, $templateID, $data) {
    // Report generation logic here
    // This could involve retrieving the template, parsing it,
    // and replacing placeholders with actual data
}
```

## 3. Module Organization Best Practices

### 3.1 Code Organization
- Use namespaces for all PHP classes to avoid conflicts
- Follow PSR-4 autoloading standards for efficient class loading
- Keep business logic in Domain classes for better separation of concerns
- Use Gateways for database operations to encapsulate data access
- Place templates in the templates directory for easy management
- Store generated reports in reports directory for organized output

### 3.2 File Naming Conventions
- Use lowercase for file names to ensure cross-platform compatibility
- Separate words with underscores for improved readability
- Use descriptive names that clearly indicate the file's purpose
- Add type suffixes (e.g., Gateway, Service) for clear identification
- Ensure all PHP files have the .php extension

### 3.3 Documentation Standards
- Add comprehensive PHPDoc comments to all classes and methods
- Include detailed parameter and return type documentation
- Provide usage examples in comments for complex functions
- Keep README.md up to date with installation and usage instructions
- Document all database schema changes in CHANGELOG.txt for easy tracking

## 4. Getting Started

Follow these steps to implement your module:

1. Copy this structure to your Gibbon modules directory
2. Update manifest.php with your specific module details
3. Create necessary database tables as defined in manifest.php
4. Implement basic CRUD (Create, Read, Update, Delete) operations
5. Add user interface elements for interacting with the module
6. Conduct thorough testing, including edge cases and error handling
7. Document usage instructions and any configuration requirements

## Next Steps
Continue to the next section to learn about the GibbonEdu module system and how your module integrates with the core platform. This will cover topics such as hooking into core events, utilizing shared resources, and adhering to Gibbon's coding standards.
