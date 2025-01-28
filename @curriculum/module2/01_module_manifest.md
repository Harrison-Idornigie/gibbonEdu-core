# Lesson 1: Module Manifest and Configuration

## Understanding manifest.php

The `manifest.php` file is the cornerstone of your GibbonEdu module. It serves as a comprehensive blueprint that defines your module's identity, functionality, and how it integrates with the core GibbonEdu system. Think of it as both an ID card and an instruction manual for your module, all rolled into one crucial file.

### Basic Module Information

Let's start by examining the fundamental information that every module must declare:

```php
<?php
$name = "Student Equipment Tracker";
$description = "Track and manage equipment borrowed by students in science labs";
$entryURL = "equipment_overview.php";
$type = "Additional";
$category = "Learn"; // Options: Admin, Assess, Learn, People, Other
$version = "1.0.00";
$author = "Jane Smith";
$url = "https://github.com/janesmith/equipment-tracker";
$minimumVersion = "23.0.00"; // Minimum version of Gibbon
$dependencies = array(); // Other modules this module depends on
```

Let's break down each component in detail:

1. **name**: This is your module's official title. Choose a clear, descriptive name that immediately conveys the module's purpose.
   - Good example: "Student Equipment Tracker"
   - Poor example: "SETracker" (too cryptic and doesn't explain its function)

2. **description**: Provide a concise yet informative summary of what your module does. This helps administrators understand your module's functionality at a glance.
   - Good example: "Track and manage equipment borrowed by students in science labs"
   - Poor example: "A tracking system" (too vague and doesn't specify what it tracks)

3. **entryURL**: This is the main page users will see when they click on your module in the GibbonEdu menu.
   - It should end with .php
   - Choose the most frequently used or most important page in your module
   - In this case, "equipment_overview.php" likely shows a dashboard of all equipment

4. **type**: For most custom modules, this will be "Additional". Other types are typically reserved for core GibbonEdu modules.

5. **category**: This determines where your module appears in the main menu. Choose the most appropriate category:
   - Admin: For system administration features
   - Assess: For assessment and reporting tools
   - Learn: For teaching and learning resources
   - People: For user management features
   - Other: For modules that don't fit the above categories

6. **version**: Always use semantic versioning (MAJOR.MINOR.PATCH)
   - Start with "1.0.00" for your first release
   - Increment appropriately for updates (e.g., "1.0.01" for small fixes, "1.1.00" for new features)

7. **author**: Your name or organization

8. **url**: A link to where users can find more information or updates about your module

9. **minimumVersion**: The oldest version of GibbonEdu that your module is compatible with
   - Check the latest GibbonEdu release and consider compatibility

10. **dependencies**: If your module requires other modules to function, list them here

### Module Tables

Next, let's look at how to define database tables for your module. This is crucial for storing and managing your module's data efficiently.

```php
$gibbonSetting[] = array(
    'scope' => 'Equipment Tracker',
    'name' => 'defaultLoanDuration',
    'nameDisplay' => 'Default Loan Duration',
    'description' => 'Default number of days for equipment loans',
    'value' => '7',
    'type' => 'text'
);

$moduleTables[] = array(
    'name' => 'equipmentItem',
    'columns' => array(
        'id' => array(
            'type' => 'int',
            'length' => '10',
            'not_null' => true,
            'primary_key' => true,
            'auto_increment' => true
        ),
        'name' => array(
            'type' => 'varchar',
            'length' => '50',
            'not_null' => true
        ),
        'description' => array(
            'type' => 'text'
        ),
        'condition' => array(
            'type' => 'enum',
            'options' => array('New', 'Good', 'Fair', 'Poor'),
            'default' => 'Good'
        ),
        'dateAdded' => array(
            'type' => 'date',
            'not_null' => true
        )
    ),
    'primary_key' => array('id'),
    'indexes' => array(
        'name' => array('columns' => array('name'))
    )
);
```

Let's break this down:

1. **gibbonSetting**: This array defines settings for your module that can be configured by administrators.
   - 'scope': Your module's name
   - 'name': Internal name for the setting
   - 'nameDisplay': User-friendly name shown in the admin interface
   - 'description': Explains what the setting does
   - 'value': Default value
   - 'type': Type of input (text, select, etc.)

2. **moduleTables**: This array defines the database tables your module needs.
   - 'name': Table name (prefix with your module name to avoid conflicts)
   - 'columns': Define each column in the table
     - 'type': SQL data type (int, varchar, text, enum, etc.)
     - 'length': For types that need a length (like varchar)
     - 'not_null': Set to true if the column can't be empty
     - 'primary_key': Set to true for the main identifier column
     - 'auto_increment': For automatically incrementing ID columns
   - 'primary_key': Specify which column(s) form the primary key
   - 'indexes': Define any additional indexes for improved query performance

### Actions and Permissions

Actions define what users can do in your module. Each action represents a distinct feature or capability, and you can set permissions for different user roles.

Here's a detailed breakdown of the key components in an action:

1. **Basic Information**
   - 'name': Displayed in menus and permissions lists
   - 'description': Explains what the action does
   - 'category': Groups related actions together in the interface
   - 'precedence': Controls the order in menus (lower numbers appear first)

2. **URL Configuration**
   - 'URLList': All pages where this action applies (comma-separated)
   - 'entryURL': The main page for this action
   - 'entrySidebar': Whether to show in the sidebar ('Y' or 'N')
   - 'menuShow': Whether to display in the main menu ('Y' or 'N')

3. **Default Permissions**
   - These control initial access for each role
   - Can be modified later by administrators
   - Consider security implications carefully

Here's an example of how to structure actions:

```php
$actionRows[] = array(
    'name' => 'View Equipment',          // Action name
    'precedence' => '0',                 // Order in menu (0 appears first)
    'category' => 'Equipment',           // Sub-menu category
    'description' => 'View all equipment in the system',
    'URLList' => 'equipment_view.php',   // Pages this action applies to
    'entryURL' => 'equipment_view.php',  // Main page for this action
    'entrySidebar' => 'Y',               // Show in sidebar? Yes
    'menuShow' => 'Y',                   // Show in menu? Yes
    'defaultPermissionAdmin' => 'Y',     // Give to admins by default? Yes
    'defaultPermissionTeacher' => 'Y',   // Give to teachers by default? Yes
    'defaultPermissionStudent' => 'N',   // Give to students by default? No
    'defaultPermissionParent' => 'N',    // Give to parents by default? No
    'defaultPermissionSupport' => 'Y',   // Give to support staff by default? Yes
    'categoryPermissionStaff' => 'Y',    // Can staff manage these permissions? Yes
    'categoryPermissionStudent' => 'N',  // Can students manage these permissions? No
    'categoryPermissionParent' => 'N',   // Can parents manage these permissions? No
    'categoryPermissionOther' => 'N'     // Can others manage these permissions? No
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

In this example, we've defined two actions:
1. "View Equipment": Allows users to view the equipment list. It's given to admins, teachers, and support staff by default.
2. "Manage Equipment": Allows users to add, edit, and delete equipment. It's only given to admins by default, as it's a more sensitive operation.

### Module Settings and Configuration

Module settings are customizable options that allow users to change how your module works without modifying the code. Think of them as preferences or options in a smartphone app.

Here's a detailed example of how to define settings:

```php
// This setting lets users choose if they want email notifications
$gibbonSetting[] = array(
    'scope' => 'Equipment Tracker',       // Your module name
    'name' => 'sendEmails',               // Internal name (used in code)
    'nameDisplay' => 'Send Emails',       // Display name (shown to users)
    'description' => 'Should the system send email notifications for overdue equipment?',
    'value' => 'Y',                       // Default value (Yes)
    'type' => 'select',                   // Setting type (dropdown menu)
    'options' => array('Y' => 'Yes', 'N' => 'No')
);

// This setting lets users enter the number of days before an item is considered overdue
$gibbonSetting[] = array(
    'scope' => 'Equipment Tracker',
    'name' => 'overdueDays',
    'nameDisplay' => 'Days Until Overdue',
    'description' => 'Number of days after the due date before an item is considered overdue',
    'value' => '3',                       // Default value (3 days)
    'type' => 'text'                      // Setting type (text input)
);
```

You can use these types for settings:
- `text`: For short text inputs (like names or numbers)
- `select`: For choices from a predefined list (like Yes/No)
- `textarea`: For longer text inputs (like messages or descriptions)
- `date`: For date selections

Tips for creating good settings:
1. Use clear, descriptive names
2. Provide helpful descriptions
3. Set sensible default values
4. Group related settings together

## Exercise: Create Your First manifest.php

Now it's your turn to create a manifest.php file for your own module. Follow these steps:

1. Plan Your Module
   - Decide on its main purpose
   - Identify the target users
   - List the key features and permissions needed

2. Write Basic Information
   ```php
   <?php
   $name = "Your Module Name";
   $description = "A brief, clear description of what your module does";
   $entryURL = "your_main_page.php";
   $type = "Additional";
   $category = "Choose appropriate category";
   $version = "1.0.00";
   $author = "Your Name";
   $url = "https://github.com/yourusername/your-module";
   ```

3. Define Tables
   ```php
   $moduleTables[] = array(
       'name' => 'yourModuleMainTable',
       'columns' => array(
           'id' => array(
               'type' => 'int',
               'length' => '10',
               'not_null' => true,
               'primary_key' => true,
               'auto_increment' => true
           ),
           // Add more columns as needed
       )
   );
   ```

4. Add Actions
   ```php
   $actionRows[] = array(
       'name' => 'Your First Action',
       'precedence' => '0',
       'category' => 'Your Category',
       'description' => 'Description of what this action does',
       'URLList' => 'your_action_page.php',
       'entryURL' => 'your_action_page.php',
       'entrySidebar' => 'Y',
       'menuShow' => 'Y',
       'defaultPermissionAdmin' => 'Y',
       // Set other permissions as appropriate
   );
   ```

5. Create Settings
   ```php
   $gibbonSetting[] = array(
       'scope' => 'Your Module Name',
       'name' => 'yourSetting',
       'nameDisplay' => 'Your Setting Name',
       'description' => 'What does this setting do?',
       'value' => 'default value',
       'type' => 'text'
   );
   ```

## Common Mistakes to Avoid

1. **Version Numbers**
   - Always use semantic versioning (MAJOR.MINOR.PATCH)
   - Correct: "1.0.00" 
   - Incorrect: "1.0" or "1"

2. **Permissions**
   - Don't give everyone access by default
   - Consider the principle of least privilege
   - Think about the security implications of each permission

3. **URLs**
   - Include all related pages in the URLList
   - Use consistent, lowercase naming with underscores
   - Example: 'equipment_view.php', not 'EquipmentView.php'

4. **Settings**
   - Use clear, descriptive names
   - Provide sensible default values
   - Include helpful descriptions for each setting

## Best Practices

1. **Naming Conventions**
   - Use descriptive, consistent names throughout
   - Prefix database tables with your module name to avoid conflicts
   - Use camelCase for action names (e.g., 'viewEquipment', 'manageUsers')

2. **Documentation**
   - Comment your manifest.php file extensively
   - Explain any non-obvious settings or permissions
   - Document any dependencies or special requirements

3. **Organization**
   - Group related actions together under the same category
   - Keep settings organized by function or feature
   - Use meaningful precedence numbers to create a logical menu order

4. **Security**
   - Review permission settings carefully
   - Consider different access levels for viewing vs. editing data
   - Document any security considerations for administrators

## Next Steps

After completing your manifest.php:
1. Double-check all permissions to ensure they're appropriate
2. Verify that your table definitions are complete and correct
3. Test all your settings to make sure they work as expected
4. Review your URL routing to ensure all pages are accessible
5. Document your choices and any special considerations for your module

In the next lesson, we'll dive into database integration and learn how to manage your module's data structure effectively. This will include creating and modifying tables, handling data migrations, and best practices for database operations in the GibbonEdu ecosystem.

Remember, creating a good manifest.php file is crucial for the success of your module. It sets the foundation for everything else you'll build. Take your time, plan carefully, and don't hesitate to revise as your module evolves. Happy coding!
