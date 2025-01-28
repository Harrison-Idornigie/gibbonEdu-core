# Lesson 1: Module Hooks and Integration in GibbonEdu

## Understanding Hooks in GibbonEdu

In the world of GibbonEdu development, hooks are incredibly powerful tools. They act as special connection points in the system's execution flow, allowing your custom module to seamlessly integrate with or modify the core system's behavior. Think of hooks as predefined spots where you can "hook" your code into the main GibbonEdu system.

### What Exactly are Hooks?

Hooks in GibbonEdu provide a way for your module to interact with the core system without directly modifying its code. This is crucial for maintaining a clean, modular, and upgradeable system. Here's what hooks allow your module to do:

1. **Respond to System Events**: Your module can listen for and react to specific events in the core system, such as when a student is enrolled or when a user logs in.
2. **Modify Core Functionality**: You can alter or extend how certain core features work without changing the core code itself.
3. **Add Custom Features to Existing Pages**: Hooks let you inject your own content or functionality into existing GibbonEdu pages.
4. **Integrate with Other Modules**: Your module can interact with other modules, creating a more interconnected and powerful system.

## Types of Hooks in GibbonEdu

GibbonEdu offers several types of hooks, each serving a different purpose. Let's explore them:

### 1. Data Integration Hooks

These hooks allow your module to integrate with core data operations. They're perfect for when you need to perform actions based on data changes in the core system.

Here's an example of a data integration hook that runs when a new student is enrolled:

```php
<?php
// This would typically be in your module's hooks.php file

function studentEnrolment($args) {
    // $args is an array containing information about the student being enrolled
    
    // Extract important information from the $args array
    $gibbonPersonID = $args['gibbonPersonID'];  // The unique ID of the student
    $gibbonSchoolYearID = $args['gibbonSchoolYearID'];  // The ID of the school year
    
    // Create an instance of your module's database gateway
    $gateway = new EquipmentGateway($pdo);
    
    // Now, let's create a default equipment allocation for the new student
    try {
        // Prepare the data for insertion
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateAssigned' => date('Y-m-d'),  // Current date
            'status' => 'Pending'
        ];
        
        // Insert the data into your module's table
        $gateway->insert($data);
        
        // Log a success message
        Log::write(
            'Student Equipment', 
            'Default equipment allocation created for student: ' . $gibbonPersonID
        );
        
    } catch (Exception $e) {
        // If something goes wrong, log the error
        Log::write(
            'Student Equipment Error', 
            'Failed to create equipment allocation: ' . $e->getMessage()
        );
    }
}
```

This hook function automatically creates a default equipment allocation whenever a new student is enrolled in the school. It's a great way to ensure that every new student is immediately set up in your equipment tracking system.

### 2. Interface Hooks

Interface hooks allow you to modify the user interface of GibbonEdu. They're perfect for adding custom elements to existing pages.

Here's an example that adds a custom sidebar element to student profiles:

```php
<?php
// This function would be called when rendering a student's profile page

function studentProfileSidebar($args) {
    // Extract the student's ID from the arguments
    $gibbonPersonID = $args['gibbonPersonID'];
    
    // Use your module's gateway to fetch equipment data for this student
    $gateway = new EquipmentGateway($pdo);
    $equipment = $gateway->selectBy(['gibbonPersonID' => $gibbonPersonID])->fetch();
    
    // Start building the HTML output
    $output = '<div class="column-no-break">';
    $output .= '<h4>' . __('Equipment Summary') . '</h4>';
    
    $output .= '<ul class="list-none p-0 m-0 text-xs">';
    
    if (!empty($equipment)) {
        // If the student has equipment, list each item
        foreach ($equipment as $item) {
            $output .= '<li class="pt-2">';
            $output .= '<span class="text-gray-700">' . $item['name'] . '</span><br/>';
            $output .= '<span class="text-xxs text-gray-600">';
            $output .= __('Status') . ': ' . $item['status'];
            $output .= '</span>';
            $output .= '</li>';
        }
    } else {
        // If no equipment is assigned, display a message
        $output .= '<li class="pt-2 text-gray-600">';
        $output .= __('No equipment assigned');
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</div>';
    
    // Return the complete HTML string
    return $output;
}
```

This hook adds a summary of assigned equipment to a student's profile sidebar, making it easy for staff to quickly see what equipment a student has.

### 3. Process Hooks

Process hooks integrate with core GibbonEdu processes, allowing your module to respond to significant events or actions in the system.

Here's an example that hooks into the student departure process:

```php
<?php
// This function would be called when a student is being marked as departed from the school

function studentDeparture($args) {
    // Extract relevant information from the arguments
    $gibbonPersonID = $args['gibbonPersonID'];
    $departureDate = $args['departureDate'];
    
    // Create an instance of your equipment gateway
    $gateway = new EquipmentGateway($pdo);
    
    // Check if the departing student has any outstanding equipment
    $outstanding = $gateway->selectOutstandingEquipment($gibbonPersonID)->fetchAll();
    
    if (!empty($outstanding)) {
        // If there's outstanding equipment, create a notification
        $notification = new NotificationEvent(
            'Equipment Tracker', 
            'Outstanding Equipment'
        );
        
        // Set the text for the notification
        $notification->setNotificationText(
            sprintf(
                __('Student %1$s has %2$d items of outstanding equipment'),
                Format::name('', $args['preferredName'], $args['surname'], 'Student'),
                count($outstanding)
            )
        );
        
        // Add the student's tutor as a recipient of the notification
        $notification->addRecipient($args['tutorID']);
        
        // Send out the notification
        $notification->pushNotifications($pdo);
        
        // Log that the check was performed
        Log::write(
            'Student Departure', 
            'Equipment check completed - outstanding items found'
        );
    }
    
    // Update the status of all equipment assigned to this student
    $gateway->updateWhere(
        ['gibbonPersonID' => $gibbonPersonID],
        ['status' => 'Pending Return', 'dueDate' => $departureDate]
    );
}
```

This hook ensures that when a student is departing, any outstanding equipment is flagged, the student's tutor is notified, and all equipment statuses are updated accordingly.

### 4. Notification Hooks

Notification hooks allow your module to integrate with GibbonEdu's notification system, enabling you to send custom notifications based on specific criteria.

Here's an example that hooks into the daily notification system to check for overdue equipment:

```php
<?php
// This function would be called as part of the daily notification process

function dailyNotifications($args) {
    // Create an instance of your equipment gateway
    $gateway = new EquipmentGateway($pdo);
    
    // Fetch all overdue equipment
    $overdue = $gateway->selectOverdueEquipment()->fetchAll();
    
    // Loop through each overdue item
    foreach ($overdue as $item) {
        // Create a new notification event
        $notification = new NotificationEvent(
            'Equipment Tracker', 
            'Overdue Equipment'
        );
        
        // Set the text for the notification
        $notification->setNotificationText(
            sprintf(
                __('%1$s has not returned %2$s (due: %3$s)'),
                Format::name('', $item['preferredName'], $item['surname'], 'Student'),
                $item['equipmentName'],
                Format::date($item['dueDate'])
            )
        );
        
        // Add recipients: both the student and their tutor
        $notification->addRecipient($item['gibbonPersonID']);
        $notification->addRecipient($item['tutorID']);
        
        // Add a link to the equipment return page
        $notification->setActionLink(
            "/modules/Equipment Tracker/equipment_return.php",
            [
                'gibbonPersonID' => $item['gibbonPersonID'],
                'equipmentID' => $item['equipmentID']
            ]
        );
        
        // Send out the notification
        $notification->pushNotifications($pdo);
    }
}
```

This hook checks for overdue equipment daily and sends notifications to both the student and their tutor, including a direct link to return the equipment.

## Implementing Hooks in Your Module

To implement hooks in your GibbonEdu module, follow these steps:

### 1. Create a hooks.php File

First, create a file named `hooks.php` in your module's root directory. This file will register all the hooks your module uses:

```php
<?php
// /modules/Equipment Tracker/hooks.php

// Register your module's hooks
$hooks = array(
    'studentEnrolment' => array(
        'name' => 'Student Enrolment',
        'description' => 'Processes new student enrolment',
        'type' => 'Student',
        'function' => 'studentEnrolment'
    ),
    'studentProfileSidebar' => array(
        'name' => 'Student Profile Sidebar',
        'description' => 'Adds equipment info to profile',
        'type' => 'Student',
        'function' => 'studentProfileSidebar'
    ),
    'studentDeparture' => array(
        'name' => 'Student Departure',
        'description' => 'Handles equipment return process',
        'type' => 'Student',
        'function' => 'studentDeparture'
    ),
    'dailyNotifications' => array(
        'name' => 'Daily Notifications',
        'description' => 'Sends overdue equipment notices',
        'type' => 'System',
        'function' => 'dailyNotifications'
    )
);

// Include the files containing your hook functions
require_once 'hooks/studentHooks.php';
require_once 'hooks/systemHooks.php';
```

### 2. Organize Your Hook Functions

It's a good practice to organize your hook functions into separate files based on their type or purpose. For example:

```php
<?php
// /modules/Equipment Tracker/hooks/studentHooks.php

function studentEnrolment($args) {
    // Implementation as shown earlier
}

function studentProfileSidebar($args) {
    // Implementation as shown earlier
}

function studentDeparture($args) {
    // Implementation as shown earlier
}
```

```php
<?php
// /modules/Equipment Tracker/hooks/systemHooks.php

function dailyNotifications($args) {
    // Implementation as shown earlier
}
```

## Creating Custom Hooks for Other Modules

Your module can also provide hooks for other modules to use. Here's how you can create custom hooks:

```php
<?php
// In your module's main code file

// Define hook points that other modules can use
$hookPoints = array(
    'beforeEquipmentLoan' => array(
        'args' => array('equipmentID', 'gibbonPersonID', 'dueDate'),
        'description' => 'Called before equipment is loaned out'
    ),
    'afterEquipmentReturn' => array(
        'args' => array('equipmentID', 'gibbonPersonID', 'returnCondition'),
        'description' => 'Called after equipment is returned'
    )
);

// Example of calling hooks before loaning out equipment
$args = array(
    'equipmentID' => $equipmentID,
    'gibbonPersonID' => $gibbonPersonID,
    'dueDate' => $dueDate
);

$hooks = getHookPoints('beforeEquipmentLoan');
foreach ($hooks as $hook) {
    $result = call_user_func($hook['function'], $args);
    if ($result === false) {
        // If any hook returns false, prevent the loan
        return false;
    }
}

// Process the equipment loan here...

// Example of calling hooks after equipment is returned
$args = array(
    'equipmentID' => $equipmentID,
    'gibbonPersonID' => $gibbonPersonID,
    'returnCondition' => $condition
);

$hooks = getHookPoints('afterEquipmentReturn');
foreach ($hooks as $hook) {
    call_user_func($hook['function'], $args);
}
```

This allows other modules to hook into your module's equipment loan and return processes, extending the functionality as needed.

## Best Practices for Using Hooks

When implementing hooks in your GibbonEdu module, keep these best practices in mind:

1. **Error Handling**
   - Always use try-catch blocks to handle potential errors
   - Log errors appropriately using GibbonEdu's logging system
   - Ensure your module fails gracefully if a hook encounters an error

2. **Performance Considerations**
   - Keep your hook functions lightweight and efficient
   - Cache expensive operations where possible
   - Use database transactions for operations that involve multiple database changes

3. **Security Measures**
   - Always validate and sanitize any input your hooks receive
   - Check user permissions before performing sensitive operations
   - Sanitize any output your hooks generate, especially if it's displayed to users

4. **Code Maintainability**
   - Document the purpose and expected behavior of each hook
   - Use meaningful names for your hook functions and variables
   - Keep each hook function focused on a single responsibility

## Exercise: Implement Your First Hook

Let's practice by creating a basic hook for your module:

1. Create a Basic Hook
```php
<?php
// hooks.php
$hooks = array(
    'yourFirstHook' => array(
        'name' => 'Your First Hook',
        'description' => 'A simple hook to get started',
        'type' => 'Custom',
        'function' => 'yourFirstHookFunction'
    )
);

function yourFirstHookFunction($args) {
    // Your implementation here
    $message = "Hello, " . ($args['name'] ?? 'World') . "!";
    Log::write('Custom Hook', $message);
    return $message;
}
```

2. Test the Hook
```php
// Test script
$args = array(
    'name' => 'GibbonEdu Developer'
);

$result = yourFirstHookFunction($args);
echo $result;  // Should output: Hello, GibbonEdu Developer!
```

## Common Mistakes to Avoid

When working with hooks, be aware of these common pitfalls:

1. **Not Checking for Required Arguments**
   ```php
   // Bad practice
   function badHook($args) {
       $value = $args['someKey'];  // This might cause an error if 'someKey' doesn't exist!
   }
   
   // Good practice
   function goodHook($args) {
       $value = $args['someKey'] ?? null;  // Safely access the value
       if ($value === null) {
           return false;  // Or handle the missing value appropriately
       }
   }
   ```

2. **Not Handling Potential Errors**
   
