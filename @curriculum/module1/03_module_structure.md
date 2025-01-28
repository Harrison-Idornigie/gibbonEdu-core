# Understanding Module Structure in GibbonEdu

## Introduction to GibbonEdu Modules

GibbonEdu is a flexible, open-source school platform. One of its key features is the ability to extend functionality through modules. As a beginner, understanding how to create these modules is crucial for customizing GibbonEdu to meet your school's specific needs.

## Module Directory Layout

When you're developing a module for GibbonEdu, it's essential to follow a standard directory structure. This structure isn't just a suggestion - it's a crucial part of how GibbonEdu recognizes and integrates your module. Let's break down this structure in detail:

```plaintext
yourModule/
├── CHANGELOG.txt           # A running log of all changes made to your module
├── CHANGEDB.php            # Manages database changes as your module evolves
├── LICENSE                 # The legal terms under which your module is distributed
├── manifest.php            # The heart of your module - defines its core information
├── index.php               # The main entry point when someone clicks on your module
├── version.php             # Stores the current version number of your module
├── moduleFunctions.php     # A collection of shared functions used across your module
├── css/                    # A directory for all your module's CSS files
│   └── module.css          # The main CSS file for styling your module's pages
├── js/                     # A directory for all your module's JavaScript files
│   └── module.js           # The main JavaScript file for your module's interactivity
├── src/                    # This directory contains the main PHP source code
│   └── Domain/             # Holds domain-specific classes (more on this later)
│       ├── ExampleGateway.php    # A class for database interactions
│       └── ExampleManager.php    # A class for business logic
├── templates/              # Stores Twig templates for generating views
│   └── template.twig.html  # An example Twig template
└── img/                    # A directory for images used in your module
    └── logo.png            # Your module's logo or icon
```

Now, let's examine each component in more detail:

## 1. Core Files

### manifest.php
This file is absolutely crucial - it's like your module's ID card or passport. GibbonEdu reads this file to understand what your module is, what it does, and how it should be integrated into the system. Here's a detailed breakdown of what goes into a manifest.php file:

```php
<?php
// manifest.php

// The name that appears in the main menu
// Choose something descriptive but concise
$name = 'Student Equipment Tracker';

// A brief description of what your module does
// This helps administrators understand your module's purpose
$description = 'Track laboratory equipment borrowed by students';

// The main page of your module that users will see when they click on it
// This should be the most important or frequently used page in your module
$entryURL = 'equipment_tracker.php';

// The type of module: 'Additional' for general modules, 'Teaching & Learning' for academic modules
// This helps categorize your module in the GibbonEdu ecosystem
$type = 'Additional';

// The category in the main menu where your module will appear
// Choose from existing categories or create a new one if necessary
$category = 'Other';

// The current version of your module (use semantic versioning: MAJOR.MINOR.PATCH)
// This helps track updates and ensures compatibility
$version = '1.0.00';

// Your name or your team's name
// This credits the creators and helps users know who to contact
$author = 'Jane Smith';

// A URL where users can get support or more information about your module
// This could be a GitHub repository, a website, or a support email
$url = 'https://github.com/janesmith/equipment-tracker';

// Define the database tables your module needs
// This example creates a table to store equipment information
$tables = [
    "CREATE TABLE `moduleEquipmentTracker` (
        `id` INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
        `name` VARCHAR(50) NOT NULL,
        `category` VARCHAR(30) NOT NULL,
        `available` BOOLEAN DEFAULT TRUE,
        `location` VARCHAR(100),
        `dateAdded` DATE,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Define any settings your module needs
// This example creates a setting to store an email address for notifications
$gibbonSetting[] = [
    'scope' => 'Equipment Tracker',  // Your module's name
    'name' => 'notificationEmail',   // The setting's name (used internally)
    'nameDisplay' => 'Notification Email',  // How it appears in the settings page
    'description' => 'Email for equipment notifications',  // Explanation of the setting
    'value' => ''  // Default value (empty in this case)
];

// Define the actions (pages) and permissions for your module
// This is crucial for security and accessibility
$actionRows[] = [
    'name' => 'View Equipment',  // Name of the action
    'precedence' => '0',  // Order in the menu (lower numbers appear first)
    'category' => 'Equipment',  // Sub-menu category
    'description' => 'View all equipment',  // Tooltip text
    'URLList' => 'equipment_view.php',  // Pages associated with this action
    'entryURL' => 'equipment_view.php',  // Main page for this action
    'entrySidebar' => 'Y',  // Show in sidebar? Y/N
    'menuShow' => 'Y',  // Show in menu? Y/N
    'defaultPermissionAdmin' => 'Y',  // Give admin access by default? Y/N
    'defaultPermissionTeacher' => 'Y',  // Give teachers access by default? Y/N
    'defaultPermissionStudent' => 'N',  // Give students access by default? Y/N
    'defaultPermissionParent' => 'N',  // Give parents access by default? Y/N
    'defaultPermissionSupport' => 'Y',  // Give support staff access by default? Y/N
    'categoryPermissionStaff' => 'Y',  // Allow all staff? Y/N
    'categoryPermissionStudent' => 'N',  // Allow all students? Y/N
    'categoryPermissionParent' => 'N',  // Allow all parents? Y/N
    'categoryPermissionOther' => 'N',  // Allow others? Y/N
];
```

### Using Gibbon's Form API
GibbonEdu provides a powerful Form API that helps create consistent and user-friendly forms across the platform. This API is crucial for maintaining a uniform look and feel throughout your module and the entire GibbonEdu system. Here's how to use it:

```php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

// Create a new form
// The first parameter is the form's ID, the second is the form's action (where it submits to)
$form = Form::create('equipment', $_SESSION[$guid]['absoluteURL'].'/modules/'.$_SESSION[$guid]['module'].'/equipment_addProcess.php');

// Set up the form to use database-aware form elements
// This allows you to easily create dropdowns populated from the database
$form->setFactory(DatabaseFormFactory::create($pdo));

// Add form elements
// This creates a new row in the form
$row = $form->addRow();
    // Add a label for the 'name' field
    $row->addLabel('name', __('Name'))
        ->description(__('Equipment name'))
        ->required();
    // Add a text field for the 'name' input
    $row->addTextField('name')
        ->required()
        ->maxLength(50);

// Add another row for the 'category' field
$row = $form->addRow();
    // Add a label for the 'category' field
    $row->addLabel('category', __('Category'));
    // Add a select (dropdown) field for the 'category' input
    // This populates the dropdown with categories from the database
    $row->addSelect('category')
        ->fromQuery($pdo, "SELECT DISTINCT category as value, category as name FROM moduleEquipmentTracker ORDER BY category")
        ->required()
        ->placeholder();

// Output the form HTML
echo $form->getOutput();
```

This code creates a form with two fields: a text input for the equipment name and a dropdown for the category. The dropdown is populated with existing categories from the database.

### Using DataTable for Listings
GibbonEdu's DataTable class helps create consistent and interactive list views. This is essential for displaying data in a user-friendly format. Here's how to use it:

```php
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Equipment\EquipmentGateway;

// Get an instance of the EquipmentGateway
// This assumes you've set up dependency injection correctly
$equipmentGateway = $container->get(EquipmentGateway::class);

// Create a new DataTable
$table = DataTable::create('equipment');
$table->setTitle(__('Equipment'));

// Add columns to the table
// Each column corresponds to a field in your database
$table->addColumn('name', __('Name'))
    ->sortable();
$table->addColumn('category', __('Category'))
    ->sortable();
$table->addColumn('location', __('Location'));
$table->addColumn('available', __('Available'))
    ->format(Format::using('yesNo', ['available']));

// Add an action column with edit and delete buttons
// This allows users to interact with each row
$table->addActionColumn()
    ->addParam('id')
    ->format(function ($item, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/Equipment Tracker/equipment_edit.php');
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/Equipment Tracker/equipment_delete.php');
    });

// Render the table with data from the EquipmentGateway
echo $table->render($equipmentGateway->queryEquipment());
```

This code creates a table listing all equipment items, with columns for name, category, location, and availability. It also adds edit and delete buttons for each item.

### Error Handling and Notifications
GibbonEdu provides a notification system for giving feedback to users. This is crucial for informing users about the results of their actions. Here's how to use it:

```php
// For a successful operation, redirect with a success message
$URL .= '&return=success0';
header("Location: {$URL}");
exit();

// For an error, redirect with an error message
$URL .= '&return=error2';
header("Location: {$URL}");
exit();

// To add a custom message
$session->addMessage(__('Operation successful'), 'success');
```

These snippets show how to redirect users after an operation, with appropriate success or error messages.

## 2. Domain Logic

### QueryableGateway Implementation
In modern GibbonEdu modules (version 23+), we use QueryableGateways for database operations. This pattern provides a clean and maintainable way to interact with the database. Here's a basic implementation with detailed explanations:

```php
namespace Gibbon\Module\EquipmentTracker\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class EquipmentGateway extends QueryableGateway
{
    use TableAware;

    // Define the database table this gateway manages
    private static $tableName = 'moduleEquipmentTracker';
    
    // Define the column that serves as the unique identifier
    private static $primaryKey = 'id';
    
    // List columns that can be searched
    private static $searchableColumns = ['name', 'category', 'location'];

    // Main method to query equipment data
    public function queryEquipment(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'moduleEquipmentTracker.id',
                'moduleEquipmentTracker.name',
                'moduleEquipmentTracker.category',
                'moduleEquipmentTracker.available',
                'moduleEquipmentTracker.location',
                'moduleEquipmentTracker.dateAdded'
            ]);

        return $this->runQuery($query, $criteria);
    }

    // Method to get a single equipment item by ID
    public function getEquipmentByID($id)
    {
        $data = ['id' => $id];
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id=:id";
        
        return $this->db()->selectOne($sql, $data);
    }
}
```

Key points for beginners:
- The `TableAware` trait provides methods for table operations, simplifying database interactions
- Always use fully qualified table names in column selections to avoid ambiguity in complex queries
- `QueryCriteria` handles sorting, filtering, and pagination automatically, making your code more efficient
- Always use parameter binding (`:id`) for SQL queries to prevent SQL injection attacks
- Method names should clearly describe their purpose (e.g., `getEquipmentByID`) for better code readability

To make your gateway available to the rest of your module, you need to register it in `src/Domain/services.php`:

```php
$container->add(EquipmentGateway::class)
         ->autowire();
```

This tells GibbonEdu's dependency injection container about your gateway, allowing it to be automatically injected where needed.

### Module Configuration
You can access and update module settings through the System Admin interface. Here's how to interact with these settings in your code:

```php
// Get a module setting
$setting = getSettingByScope($connection2, 'Equipment Tracker', 'notificationEmail');

// Update a module setting
$sql = "UPDATE gibbonSetting SET value=:value WHERE scope='Equipment Tracker' AND name='notificationEmail'";
$result = $connection2->prepare($sql);
$result->execute(['value' => $newEmail]);
```

These snippets show how to retrieve and update module settings programmatically.

## Modern Dependency Management with Composer

Starting with GibbonEdu v22.0.00, Composer is used to manage PHP dependencies. This section covers how to properly manage dependencies in your GibbonEdu module.

### Setting Up Composer

1. **Initialize Your Module's Composer**

Create a `composer.json` in your module root:

```json
{
    "name": "your-vendor/module-name",
    "description": "Your GibbonEdu module description",
    "type": "gibbonedu-module",
    "require": {
        "php": "^7.4",
        "ext-pdo": "*"
    },
    "autoload": {
        "psr-4": {
            "Gibbon\\Module\\YourModule\\": "src/"
        }
    }
}
```

2. **Required Dependencies**

Your module must be compatible with GibbonEdu's core requirements:

```json
{
    "require": {
        "php": "^7.4",
        "ext-curl": "*",
        "ext-intl": "*",
        "ext-mbstring": "*",
        "ext-gettext": "*",
        "ext-PDO": "*"
    }
}
```

### Dependency Management Best Practices

1. **Version Constraints**
   - Use caret (`^`) for minor version flexibility
   - Use exact versions for critical dependencies
   - Document version requirements in README.md

```json
{
    "require": {
        "league/csv": "^9.8",
        "specific/package": "1.2.3"
    }
}
```

2. **Development Dependencies**
   - Keep testing and development tools in require-dev
   - Include coding standards and static analysis tools

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.3",
        "squizlabs/php_codesniffer": "^3.5",
        "phpstan/phpstan": "^1.8"
    }
}
```

3. **Autoloading Configuration**
   - Use PSR-4 autoloading
   - Follow GibbonEdu's namespace conventions

```json
{
    "autoload": {
        "psr-4": {
            "Gibbon\\Module\\YourModule\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Gibbon\\Module\\YourModule\\Tests\\": "tests/"
        }
    }
}
```

### Security Best Practices

1. **Dependency Scanning**
   ```bash
   # Check for known vulnerabilities
   composer audit

   # Update dependencies securely
   composer update --dry-run
   composer update
   ```

2. **Lock File Management**
   - Always commit composer.lock
   - Update dependencies regularly
   - Review changes before updating

3. **Private Dependencies**
   ```json
   {
       "repositories": [
           {
               "type": "vcs",
               "url": "https://github.com/your-org/private-package"
           }
       ]
   }
   ```

### Common Workflows

1. **Installing Dependencies**
```bash
# Development installation
composer install

# Production installation
composer install --no-dev --optimize-autoloader
```

2. **Adding New Dependencies**
```bash
# Add a new package
composer require vendor/package

# Add a development package
composer require --dev vendor/package
```

3. **Updating Dependencies**
```bash
# Update all dependencies
composer update

# Update specific package
composer update vendor/package
```

### Integration with GibbonEdu Core

1. **Accessing Core Services**
   ```php
   <?php
   namespace Gibbon\Module\YourModule;

   use Gibbon\Domain\DataSet;
   use Gibbon\Domain\QueryCriteria;
   ```

2. **Using Core Libraries**
   ```php
   <?php
   // Access Twig templating
   use Twig\Environment;
   
   // Access database queries
   use Aura\SqlQuery\QueryFactory;
   ```

### Troubleshooting

1. **Autoloading Issues**
   - Run `composer dump-autoload`
   - Check namespace declarations
   - Verify file locations match PSR-4 configuration

2. **Version Conflicts**
   ```bash
   # Show package dependencies
   composer why vendor/package

   # Show conflicts
   composer why-not vendor/package
   ```

3. **Performance Issues**
   ```bash
   # Optimize autoloader
   composer dump-autoload --optimize

   # Analyze impact
   composer show --tree
   ```

### Module Distribution

When distributing your module:

1. **Required Files**
   - Include composer.json
   - Include composer.lock
   - Document installation process

2. **Installation Instructions**
   ```markdown
   ## Installation
   1. Copy module to GibbonEdu modules directory
   2. Run: composer install --no-dev
   3. Activate module in GibbonEdu admin
   ```

3. **Version Control**
   - Tag releases using semantic versioning
   - Update composer.json version
   - Document changes in CHANGELOG.md

## Exercise: Create a Modern Module

To practice what you've learned, try creating a "Homework Tracker" module with these features:

1. Use these GibbonEdu features:
   - Form API for creating submission forms
   - DataTable for listing assignments
   - QueryableGateway for database operations
   - Error handling and notifications for user feedback

2. Implement the following functionality:
   - A form for submitting new assignments
   - A list view of all assignments with filtering options
   - An interface for teachers to grade assignments
   - A view for students to see their assignments and grades

3. Use proper namespacing for your classes:
```php
namespace Gibbon\Module\HomeworkTracker\Domain;
```

This exercise will help you apply the concepts we've covered and gain practical experience in GibbonEdu module development.

## Next Steps
In the upcoming lesson, we'll walk through the process of creating your first functional module using the GibbonEdu starter module template. This hands-on experience will help solidify the concepts we've covered here and give you a practical understanding of the module development process.

Remember, module development in GibbonEdu is a skill that improves with practice. Don't be afraid to experiment and refer back to this guide as you build your module. Good luck with your GibbonEdu module development journey!
