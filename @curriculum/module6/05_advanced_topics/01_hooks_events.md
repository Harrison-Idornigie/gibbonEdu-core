# Hooks and Event System in GibbonEdu

This comprehensive guide explains how to effectively use hooks in GibbonEdu modules, based on the official documentation and current implementation practices.

## 1. Available Hook Types

GibbonEdu's core system provides five main hook types that allow modules to seamlessly insert data into core interfaces:

1. Parent Dashboard - For displaying module-specific information to parents
2. Student Dashboard - For showing relevant data to students
3. Staff Dashboard - For presenting staff-related information
4. Public Homepage - For adding content visible to all visitors
5. Student Profile - For integrating module data into individual student profiles

These hooks enable modules to extend core functionality without modifying the main codebase.

## 2. Hook Registration

Hooks must be registered in your module's manifest file. This registration process writes to the `gibbonHook` table with the following required information:

```php
// manifest.php
$hooks = array(
    'hook' => array(
        'name' => 'My Module Hook', // Display name in the interface
        'type' => 'Student Profile', // One of the five hook types listed above
        'options' => serialize(array(
            // Required options vary by hook type (explained in section 3)
            'sourceModuleName' => 'My Module', // Name of the module providing the data
            'sourceModuleAction' => 'view', // Action required for access (corresponds to an action in your module)
            'sourceModuleInclude' => 'hook_studentProfile.php' // File to include when the hook is called
        ))
    )
);
```

This structure allows GibbonEdu to dynamically load your module's data at the appropriate points in the core system.

## 3. Hook Type Requirements

Each hook type requires specific options to function correctly:

### 3.1 Dashboard Hooks (Parent/Student/Staff)
These hooks share a common structure:

```php
$options = array(
    'sourceModuleName' => 'My Module', // Your module's name
    'sourceModuleAction' => 'view', // The action in your module that users need access to
    'sourceModuleInclude' => 'hook_dashboard.php' // The file in your module that outputs the dashboard data
);
```

### 3.2 Public Homepage Hook
This hook type has unique options for toggling visibility:

```php
$options = array(
    'toggleSettingName' => 'myModuleElement', // The name of the setting that toggles this element
    'toggleSettingScope' => 'My Module', // The scope of the setting (usually your module name)
    'toggleSettingValue' => 'Y', // The value that enables the element (typically 'Y' for yes)
    'title' => 'My Section', // The title of your section on the homepage
    'text' => 'Content to display' // The actual content to show on the homepage
);
```

### 3.3 Student Profile Hook
Similar to dashboard hooks, but specifically for the student profile:

```php
$options = array(
    'sourceModuleName' => 'My Module',
    'sourceModuleAction' => 'view', // The action in your module that controls access to this data
    'sourceModuleInclude' => 'hook_studentProfile.php' // The file that generates the profile content
);
```

## 4. Implementing Hook Files

Hook files should be placed in your module's root directory. Here's a detailed example of a Student Profile hook:

```php
// hook_studentProfile.php
<?php
// Retrieve the student ID from the URL parameters
$studentID = $_GET['gibbonPersonID'] ?? '';

// Check the current user's role category for access control
$roleCategory = getRoleCategory($session->get('gibbonRoleIDCurrent'), $connection2);

// Only allow staff or admin to view this data
if ($roleCategory == 'Staff' || $roleCategory == 'Admin') {
    // Use dependency injection to get the data gateway
    $dataGateway = $container->get(MyModuleGateway::class);
    
    // Fetch the relevant data for this student
    $data = $dataGateway->getStudentData($studentID);
    
    // Begin output
    echo '<h4>My Module Data</h4>';
    echo '<table class="smallIntBorder" cellspacing="0" style="width:100%">';
    
    // Loop through and display each piece of data
    foreach ($data as $row) {
        echo '<tr>';
        echo '<td style="width:33%; vertical-align: top">' . $row['name'] . '</td>';
        echo '<td style="width:67%; vertical-align: top">' . $row['value'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
} else {
    // If the user doesn't have permission, display nothing or a message
    echo '<p>You do not have permission to view this data.</p>';
}
```

## 5. Best Practices for Hook Implementation

1. **Robust Permission Checking**
   - Always verify user permissions before displaying sensitive data
   - Utilize the role category system for broad access control
   - Implement specific action checks for granular control over data access

2. **Consistent Data Display**
   - Adhere to GibbonEdu's established UI conventions for a seamless user experience
   - Use standard table classes (e.g., `smallIntBorder`) for consistent styling
   - Keep data displays concise, relevant, and easy to understand

3. **Performance Optimization**
   - Design efficient database queries to minimize load times
   - Implement caching mechanisms for frequently accessed, rarely changing data
   - Reduce external dependencies to improve reliability and speed

4. **Comprehensive Error Handling**
   - Validate all input parameters to prevent unexpected behavior
   - Gracefully handle missing or invalid data to avoid disrupting the user experience
   - Implement appropriate error logging for easier debugging and maintenance

## 6. Real-World Example: Free Learning Module

The Free Learning module provides an excellent example of hook implementation:

```php
// manifest.php
$hooks = array(
    'hook' => array(
        'name' => 'Free Learning Unit History',
        'type' => 'Student Profile',
        'options' => serialize(array(
            'sourceModuleName' => 'Free Learning',
            'sourceModuleAction' => 'Units_browse',
            'sourceModuleInclude' => 'hook_studentProfile_unitHistory.php'
        ))
    )
);
```

The corresponding hook file displays a student's unit completion history in their profile:

```php
// hook_studentProfile_unitHistory.php
<?php
$output = ''; // Initialize output variable
$student = $_GET['gibbonPersonID'] ?? ''; // Get student ID safely

if (!empty($student)) {
    // Use dependency injection to get the data gateway
    $gateway = $container->get(UnitStudentGateway::class);
    
    // Fetch all units for this student
    $units = $gateway->selectBy(['gibbonPersonID' => $student])->fetchAll();
    
    if (!empty($units)) {
        $output .= '<h4>Free Learning Units</h4>';
        $output .= '<table class="smallIntBorder" cellspacing="0" style="width:100%">';
        
        foreach ($units as $unit) {
            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars($unit['name']) . '</td>'; // Escape output for security
            $output .= '<td>' . htmlspecialchars($unit['status']) . '</td>';
            $output .= '</tr>';
        }
        
        $output .= '</table>';
    } else {
        $output .= '<p>No units completed yet.</p>';
    }
} else {
    $output .= '<p>Invalid student ID.</p>';
}

echo $output; // Display the final output
```

This example demonstrates:
- Proper permission checking through the module's action system
- Clean data retrieval using gateway classes for database abstraction
- Use of standard GibbonEdu UI elements for consistency
- Error handling for missing data or invalid student IDs
- Output escaping to prevent XSS vulnerabilities

By following these practices and examples, you can create robust, secure, and well-integrated hooks for your GibbonEdu modules.
