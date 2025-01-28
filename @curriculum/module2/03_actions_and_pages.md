# Lesson 3: Creating Actions and Pages

## Module Actions

Actions are the fundamental building blocks of your module's functionality. They define what users can do and how they can interact with your module. In Gibbon, actions are used to control access to different parts of your module and to create menu items.

### Defining Actions in manifest.php

The `manifest.php` file is where you define the actions for your module. Each action is represented by an associative array with various properties. Here's a detailed example:

```php
$actionRows[] = array(
    'name' => 'Manage Equipment',              // The name of the action
    'precedence' => '0',                       // The order in which it appears in menus (lower numbers appear first)
    'category' => 'Equipment',                 // The category under which this action is grouped
    'description' => 'Add and edit equipment', // A brief description of what this action does
    'URLList' => 'equipment_manage.php,equipment_manage_add.php,equipment_manage_edit.php,equipment_manage_delete.php',
                                               // A comma-separated list of PHP files associated with this action
    'entryURL' => 'equipment_manage.php',      // The main PHP file for this action
    'entrySidebar' => 'Y',                     // Whether to show this action in the sidebar (Y/N)
    'menuShow' => 'Y',                         // Whether to show this action in the main menu (Y/N)
    'defaultPermissionAdmin' => 'Y',           // Whether admins have access by default (Y/N)
    'defaultPermissionTeacher' => 'N',         // Whether teachers have access by default (Y/N)
    'defaultPermissionStudent' => 'N',         // Whether students have access by default (Y/N)
    'defaultPermissionParent' => 'N',          // Whether parents have access by default (Y/N)
    'defaultPermissionSupport' => 'N',         // Whether support staff have access by default (Y/N)
    'categoryPermissionStaff' => 'Y',          // Whether staff can assign this permission (Y/N)
    'categoryPermissionStudent' => 'N',        // Whether students can assign this permission (Y/N)
    'categoryPermissionParent' => 'N',         // Whether parents can assign this permission (Y/N)
    'categoryPermissionOther' => 'N'           // Whether other roles can assign this permission (Y/N)
);
```

This structure allows you to precisely control who can access each part of your module and how it appears in the Gibbon interface.

### Permission Levels

Gibbon supports different levels of permissions for actions. This allows you to create a nuanced access control system. Here are common permission levels:

1. View Only: Users can only view data but not modify it.
2. Edit Own: Users can view and edit their own data.
3. Edit All: Users can view and edit all data.
4. Manage: Users have full control, including adding and deleting data.

Here's an example of how you might implement these permission levels:

```php
// In manifest.php

// View Only permission
$actionRows[] = array(
    'name' => 'View Equipment',
    'precedence' => '0',
    'category' => 'Equipment',
    'description' => 'View available equipment',
    'URLList' => 'equipment_view.php',
    'entryURL' => 'equipment_view.php',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'Y',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'Y'
);

// Edit Own permission
$actionRows[] = array(
    'name' => 'Edit Own Loans',
    'precedence' => '1',
    'category' => 'Equipment',
    'description' => 'Edit your own equipment loans',
    'URLList' => 'loans_edit.php',
    'entryURL' => 'loans_edit.php',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'Y'
);
```

In this example, we've defined two actions with different permission levels. The "View Equipment" action is available to most user roles, while "Edit Own Loans" is more restricted.

## Page Structure

Each page in your module should follow a standard structure to ensure consistency and proper integration with Gibbon.

### Standard Page Layout

Here's a basic template for a page in your module:

```php
<?php
// Include Gibbon core
require_once '../../gibbon.php';

// Include any module-specific functions
include './moduleFunctions.php';

// Set up common variables
$URL = $session->get('absoluteURL');

// Set up breadcrumbs
$page->breadcrumbs
    ->add(__('Equipment Tracker'), 'index.php')
    ->add(__('View Equipment'));

// Check access to this page
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_view.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get action parameters (e.g., from URL)
$equipmentID = $_GET['equipmentID'] ?? '';

// Add page title
$page->write('<h2>');
$page->write(__('View Equipment'));
$page->write('</h2>');

// Add your page content here...
```

This structure ensures that:
1. The Gibbon core is included
2. Module-specific functions are available
3. Breadcrumbs are set up for navigation
4. Access permissions are checked
5. The page title is displayed

### Including Core Functions

To use Gibbon's built-in functionality and your module's custom functions, include these files:

```php
// At the top of your page
require_once '../../gibbon.php';

// Module-specific functions
include './moduleFunctions.php';

// Database gateway classes
include_once '../../src/Domain/DataSet.php';
include_once '../../src/Domain/QueryCriteria.php';
include_once 'src/Domain/EquipmentGateway.php';
```

These includes give you access to Gibbon's core functions, your module's custom functions, and database interaction classes.

### Navigation Breadcrumbs

Breadcrumbs help users understand where they are in your module. Here's how to set them up:

```php
// Simple breadcrumbs
$page->breadcrumbs
    ->add(__('Equipment Tracker'), 'index.php')
    ->add(__('View Equipment'));

// Breadcrumbs with dynamic content
$page->breadcrumbs
    ->add(__('Equipment Tracker'), 'index.php')
    ->add(__('Edit Equipment'), 'equipment_edit.php')
    ->add($equipment['name']);
```

The `__()` function is used for internationalization, allowing your module to be translated into different languages.

### Form Handling

Gibbon provides a powerful Form API to create and process forms. Here's an example of creating a form:

```php
// Create form
$form = Form::create('addEquipment', $session->get('absoluteURL').'/modules/Equipment Tracker/equipment_addProcess.php');

// Add form elements
$row = $form->addRow();
    $row->addLabel('name', __('Name'))
        ->description(__('Equipment name'))
        ->required();
    $row->addTextField('name')
        ->required()
        ->maxLength(50);

$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')
        ->setRows(4);

$row = $form->addRow();
    $row->addLabel('condition', __('Condition'))
        ->required();
    $row->addSelect('condition')
        ->fromArray(['New' => __('New'), 
                    'Good' => __('Good'),
                    'Fair' => __('Fair'),
                    'Poor' => __('Poor')])
        ->required()
        ->placeholder();

// Add submit button
$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

// Output form
echo $form->getOutput();
```

This creates a form with various input types, labels, and a submit button. The Form API handles much of the HTML generation and validation for you.

To process the form submission, create a separate PHP file:

```php
<?php
// equipment_addProcess.php

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Equipment Tracker/';

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_add.php')) {
    $URL .= 'error.php&error=Your request failed because you do not have access to this action.';
    header("Location: {$URL}");
    exit();
}

// Proceed!
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$condition = $_POST['condition'] ?? '';

// Validate required fields
if (empty($name) || empty($condition)) {
    $URL .= 'equipment_add.php&error=Please fill all required fields';
    header("Location: {$URL}");
    exit();
}

try {
    // Create gateway
    $gateway = new EquipmentGateway($pdo);
    
    // Insert data
    $data = [
        'name' => $name,
        'description' => $description,
        'condition' => $condition,
        'dateAdded' => date('Y-m-d')
    ];
    
    $inserted = $gateway->insert($data);
    
    if ($inserted) {
        $URL .= 'equipment_view.php&success=Equipment added successfully';
    } else {
        $URL .= 'equipment_add.php&error=Could not insert equipment';
    }
} catch (Exception $e) {
    $URL .= 'equipment_add.php&error='.urlencode($e->getMessage());
}

header("Location: {$URL}");
```

This file handles the form submission, validates the input, inserts the data into the database, and redirects the user with an appropriate message.

### Data Display

Gibbon uses DataTables to display data in a user-friendly format. Here's how to set up a DataTable:

```php
<?php
// Create gateway
$gateway = new EquipmentGateway($pdo);

// Create table
$table = DataTable::create('equipment');
$table->setTitle(__('Equipment'));

// Add columns
$table->addColumn('name', __('Name'))
    ->sortable();

$table->addColumn('description', __('Description'))
    ->format(function($row) {
        return Format::truncate($row['description'], 50);
    });

$table->addColumn('condition', __('Condition'))
    ->sortable();

$table->addColumn('dateAdded', __('Date Added'))
    ->format(Format::using('date', 'dateAdded'));

// Add actions
$table->addActionColumn()
    ->addParam('equipmentID')
    ->format(function($row, $actions) use ($guid) {
        $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Equipment Tracker/equipment_edit.php');
                
        $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Equipment Tracker/equipment_delete.php')
                ->modalWindow(650, 400);
    });

// Add filters
$table->addFilterField('condition')
    ->fromArray(['New' => __('New'), 
                'Good' => __('Good'),
                'Fair' => __('Fair'),
                'Poor' => __('Poor')]);

// Output table
echo $table->render($gateway->queryEquipment($criteria));
```

This code creates a table with sortable columns, action buttons, and filters. The `render()` method outputs the HTML for the table.

## Exercise: Create a Basic CRUD Interface

Now, let's put it all together to create a basic CRUD (Create, Read, Update, Delete) interface for our Equipment Tracker module.

1. Create the View Page (Read)

```php
<?php
// equipment_view.php
require_once '../../gibbon.php';

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_view.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Set up page
$page->breadcrumbs
    ->add(__('View Equipment'));

// Create and output table
$gateway = new EquipmentGateway($pdo);
$table = DataTable::create('equipment');
// ... add columns as in the DataTable example above
echo $table->render($gateway->queryEquipment($criteria));
```

2. Create the Add Page (Create)

```php
<?php
// equipment_add.php
require_once '../../gibbon.php';

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_add.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Set up page
$page->breadcrumbs
    ->add(__('Add Equipment'));

// Create and output form
$form = Form::create('addEquipment', $session->get('absoluteURL').'/modules/Equipment Tracker/equipment_addProcess.php');
// ... add form fields as in the Form example above
echo $form->getOutput();
```

3. Create the Edit Page (Update)

```php
<?php
// equipment_edit.php
require_once '../../gibbon.php';

// Check access and get equipment
$equipmentID = $_GET['equipmentID'] ?? '';
if (empty($equipmentID)) {
    $page->addError(__('No equipment selected.'));
    return;
}

// Load equipment data
$gateway = new EquipmentGateway($pdo);
$equipment = $gateway->getByID($equipmentID);

if (empty($equipment)) {
    $page->addError(__('The specified record does not exist.'));
    return;
}

// Set up page
$page->breadcrumbs
    ->add(__('Edit Equipment'));

// Create and populate form
$form = Form::create('editEquipment', $session->get('absoluteURL').'/modules/Equipment Tracker/equipment_editProcess.php');
$form->addHiddenValue('equipmentID', $equipmentID);
// ... add form fields as in the Form example above, but use setValues() to populate with existing data
$form->setValues($equipment);
echo $form->getOutput();
```

## Best Practices

When developing Gibbon modules, keep these best practices in mind:

1. **Access Control**
   - Always check permissions first using `isActionAccessible()`
   - Validate user input to prevent unauthorized access
   - Use Gibbon's built-in functions for user management

2. **Form Security**
   - Use CSRF tokens to prevent cross-site request forgery
   - Validate all input on both client and server side
   - Escape output to prevent XSS attacks

3. **Database Access**
   - Use gateway classes for database interactions
   - Always use prepared statements to prevent SQL injection
   - Handle database errors gracefully and provide user-friendly messages

4. **User Interface**
   - Maintain consistent navigation across your module
   - Provide clear error messages and success confirmations
   - Design your interface to be responsive and accessible

## Next Steps

After completing this lesson:
1. Create your basic CRUD pages for the Equipment Tracker module
2. Test all form submissions thoroughly
3. Verify that access control is working correctly
4. Implement comprehensive error handling

In the next lesson, we'll dive deeper into styling your module with CSS and enhancing its functionality with JavaScript. This will allow you to create a more polished and interactive user experience for your Gibbon module.
