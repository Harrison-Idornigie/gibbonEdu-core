# Forms and Input Handling

This comprehensive guide explains how to implement forms and handle user input effectively in your Report Template module. We'll cover form creation, input processing, and best practices for secure and user-friendly forms.

## 1. Form Creation

### 1.1 Basic Form Structure
Use GibbonEdu's Form class to ensure consistent form creation across your module:

```php
<?php
// File: templates_manage_add.php

// Import necessary classes
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

// Create a new form instance
// The first parameter is a unique identifier for the form
// The second parameter is the form's action URL
$form = Form::create('templateAdd', $session->get('absoluteURL').'/modules/ReportTemplate/templates_manage_addProcess.php');

// Set up the database form factory
// This allows the form to use database-driven elements (e.g., select fields populated from the database)
$form->setFactory(DatabaseFormFactory::create($pdo));

// Add hidden fields
// These fields are not visible to the user but are submitted with the form
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonPersonIDCreated', $session->get('gibbonPersonID'));

// Add visible form fields
// Each field is added to a new row in the form
$row = $form->addRow();
    // Add a label for the 'name' field
    $row->addLabel('name', __('Name'))
        ->description(__('Must be unique')) // Add a description for the field
        ->required(); // Mark the field as required
    // Add a text input for the 'name' field
    $row->addTextField('name')
        ->required()
        ->maxLength(90) // Set maximum length for the input
        ->uniqueField('./modules/ReportTemplate/templates_manage_uniqueAjax.php'); // Check if the name is unique via AJAX

// Add a submit button
$row = $form->addRow();
    $row->addFooter(); // Add a footer to the form
    $row->addSubmit(); // Add a submit button

// Output the form HTML
echo $form->getOutput();
```

### 1.2 Form Types
GibbonEdu provides various form field types to suit different input needs:

```php
<?php
// Text field for short, single-line inputs
$row->addTextField('title')
    ->required()
    ->maxLength(100)
    ->placeholder(__('Enter title'));

// Text area for longer, multi-line inputs
$row->addTextArea('description')
    ->setRows(5)
    ->setClass('fullWidth');

// Rich text editor for formatted content
$row->addEditor('content', $guid)
    ->showMedia(true) // Allow media uploads
    ->setRows(10);

// Select menu for choosing from predefined options
$row->addSelect('status')
    ->fromArray(['draft' => __('Draft'), 'published' => __('Published')])
    ->required()
    ->selected('draft'); // Set default selected option

// Multi-select for choosing multiple options
$row->addSelect('roles')
    ->fromQuery($pdo, "SELECT gibbonRoleID as value, name FROM gibbonRole")
    ->selectMultiple()
    ->required();

// Date picker for selecting dates
$row->addDate('date')
    ->required()
    ->setClass('shortWidth');

// File upload field
$row->addFileUpload('attachment')
    ->accepts('.pdf,.doc,.docx') // Specify accepted file types
    ->setMaxUpload(false); // Set to false for no limit, or specify a size in bytes

// Checkbox for multiple selectable options
$row->addCheckbox('options')
    ->fromArray(['option1' => 'Option 1', 'option2' => 'Option 2'])
    ->checked('option1'); // Set default checked option

// Yes/No radio buttons
$row->addYesNo('active')
    ->required()
    ->selected('Y'); // Set default selected option
```

### 1.3 Form Validation
Implement both client-side and server-side validation for data integrity:

```php
<?php
// Client-side validation
// This uses jQuery validation plugin
$row->addTextField('email')
    ->required()
    ->uniqueField() // Check if the email is unique
    ->maxLength(50)
    ->setClass('validate[required,custom[email]]'); // Apply validation rules

// Server-side validation
// This function should be called when processing form submissions
function validateFormInput($data)
{
    $errors = [];

    // Check required fields
    if (empty($data['name'])) {
        $errors[] = __('Name is required.');
    }

    // Validate field length
    if (strlen($data['name']) > 90) {
        $errors[] = __('Name cannot exceed 90 characters.');
    }

    // Validate email format
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('Invalid email format.');
    }

    // Check for unique values
    $exists = $this->templateGateway->exists($data['name']);
    if ($exists) {
        $errors[] = __('Name must be unique.');
    }

    return $errors;
}
```

## 2. Input Processing

### 2.1 Form Submission
Handle form submissions securely with proper validation and error handling:

```php
<?php
// File: templates_manage_addProcess.php

use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;

require_once '../../gibbon.php';

// Set up redirect URL
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ReportTemplate/templates_manage_add.php';

// Check user permissions
if (!isActionAccessible($guid, $connection2, '/modules/ReportTemplate/templates_manage_add.php')) {
    // Redirect if user doesn't have access
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Proceed with form processing
$templateGateway = $container->get(TemplateGateway::class);

// Sanitize and collect input data
$data = [
    'name' => $_POST['name'] ?? '',
    'description' => $_POST['description'] ?? '',
    'active' => $_POST['active'] ?? 'N',
    'header' => $_POST['header'] ?? '',
    'footer' => $_POST['footer'] ?? '',
    'orientation' => $_POST['orientation'] ?? 'P',
    'pageSize' => $_POST['pageSize'] ?? 'A4',
    'gibbonPersonIDCreated' => $_POST['gibbonPersonIDCreated'] ?? '',
    'timestampCreated' => date('Y-m-d H:i:s')
];

// Validate input
$errors = validateFormInput($data);
if (!empty($errors)) {
    // Store errors and form data in session for displaying on redirect
    $session->set('formErrors', $errors);
    $session->set('formData', $data);
    
    // Redirect back to form with error flag
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    // Insert new record
    $templateID = $templateGateway->insert($data);
    
    // Redirect with success message and new record ID
    $URL .= "&return=success0&editID=$templateID";
    
} catch (Exception $e) {
    // Redirect with general error message if insertion fails
    $URL .= '&return=error2';
}

// Redirect back to appropriate page
header("Location: {$URL}");
```

### 2.2 File Uploads
Handle file uploads securely with proper validation and error checking:

```php
<?php
// File: templates_manage_uploadProcess.php

function handleFileUpload($file, $templateID)
{
    // Define allowed file types
    $fileTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($file['type'], $fileTypes)) {
        throw new Exception(__('Invalid file type.'));
    }
    
    // Check file size (5MB limit)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception(__('File size exceeds limit.'));
    }
    
    // Generate a safe, unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $templateID.'_'.date('Ymd_His').'.'.$ext;
    
    // Set upload directory
    $uploadPath = $session->get('absolutePath').'/uploads/reportsTemplate/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }
    
    // Move uploaded file to destination
    $destination = $uploadPath.$filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception(__('Failed to upload file.'));
    }
    
    return $filename;
}

// Process the file upload
try {
    if (!empty($_FILES['file']['tmp_name'])) {
        $filename = handleFileUpload($_FILES['file'], $templateID);
        
        // Update template record with new file reference
        $data = ['attachment' => $filename];
        $templateGateway->update($templateID, $data);
    }
    
    $URL .= '&return=success0';
    
} catch (Exception $e) {
    $URL .= '&return=error2';
}
```

### 2.3 AJAX Validation
Implement real-time validation for better user experience:

```php
<?php
// File: templates_manage_uniqueAjax.php

require_once '../../gibbon.php';

// Collect input
$name = $_POST['value'] ?? '';
$templateID = $_POST['templateID'] ?? '';

$templateGateway = $container->get(TemplateGateway::class);

// Check if the name already exists
$exists = $templateGateway->unique('name', $name, $templateID);

// Prepare JSON response
$response = [
    'valid' => !$exists,
    'message' => $exists ? __('Name already taken.') : ''
];

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
```

## 3. Form Display

### 3.1 Error Handling
Display form errors clearly to guide users:

```php
<?php
// Display validation errors
if ($session->has('formErrors')) {
    echo "<div class='error'>";
    echo "<h3>" . __('Form Errors') . "</h3>";
    echo "<ul>";
    foreach ($session->get('formErrors') as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // Clear error messages from session
    $session->remove('formErrors');
}

// Repopulate form with previously submitted data
if ($session->has('formData')) {
    $form->loadAllValuesFrom($session->get('formData'));
    $session->remove('formData');
}
```

### 3.2 Success Messages
Provide clear feedback on successful actions:

```php
<?php
// Display success message
if (isset($_GET['return'])) {
    $returnMessage = null;
    
    switch ($_GET['return']) {
        case 'success0':
            $returnMessage = __('Template created successfully.');
            break;
        case 'success1':
            $returnMessage = __('Template updated successfully.');
            break;
        case 'success2':
            $returnMessage = __('Template deleted successfully.');
            break;
    }
    
    if ($returnMessage) {
        echo "<div class='success'>";
        echo "<h3>" . __('Success') . "</h3>";
        echo "<p>$returnMessage</p>";
        echo "</div>";
    }
}
```

## 4. Best Practices

### 4.1 Form Design
1. Group related fields logically
2. Use clear, concise labels
3. Provide helpful field descriptions
4. Display validation rules upfront
5. Maintain a consistent layout across forms

### 4.2 Security
1. Validate all user input on both client and server side
2. Sanitize data before processing or storing
3. Implement CSRF protection to prevent cross-site request forgery
4. Use rate limiting to prevent abuse
5. Thoroughly check and validate file uploads

### 4.3 User Experience
1. Show loading states during form submission
2. Provide immediate feedback on user actions
3. Handle errors gracefully with clear messages
4. Implement autosave for long forms
5. Enable keyboard navigation for accessibility

## Next Steps
In the next section, we'll explore implementing navigation and menus in your Report Template module to create a cohesive user interface.
