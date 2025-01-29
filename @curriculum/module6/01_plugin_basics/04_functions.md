# Working with functions.php

This comprehensive guide explains how to create, manage, and optimize module functions in GibbonEdu, with practical examples from our Report Template module.

## 1. Module Functions

### 1.1 Function Organization
The `moduleFunctions.php` file is a central repository for shared functions used across your module. This organization promotes code reusability and maintainability.

```php
<?php
/**
 * Report Template Module Functions
 *
 * This file contains shared functions for the Report Template module.
 * These functions are accessible throughout the module and provide
 * core functionality for template and report management.
 *
 * @package Module Report Template
 */

use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;
use Gibbon\Module\ReportTemplate\Domain\ReportGateway;
use Gibbon\Domain\System\SettingGateway;

/**
 * Retrieves a list of available report templates
 *
 * This function queries the database for report templates, optionally
 * filtering for active templates only. It uses the TemplateGateway
 * to interact with the database, ensuring separation of concerns.
 *
 * @param \PDO $pdo Database connection object
 * @param bool $activeOnly If true, only return active templates
 * @return array An array of template objects
 */
function getReportTemplates($pdo, $activeOnly = true)
{
    // Initialize the TemplateGateway with the database connection
    $templateGateway = new TemplateGateway($pdo);
    
    // Create a new query criteria object
    $criteria = $templateGateway->newQueryCriteria()
        ->sortBy(['name']); // Sort templates alphabetically by name
    
    // If $activeOnly is true, add a filter for active templates
    if ($activeOnly) {
        $criteria->filterBy('active', 'Y');
    }
    
    // Execute the query and return the results
    return $templateGateway->queryTemplates($criteria);
}

/**
 * Generates a report from a specified template
 *
 * This function retrieves a template by ID, then processes it with
 * the provided data to generate a report. If the template is not found,
 * an empty string is returned.
 *
 * @param \PDO $pdo Database connection object
 * @param int $templateID The ID of the template to use
 * @param array $data An associative array of data to populate the report
 * @return string The generated report content
 */
function generateReport($pdo, $templateID, $data)
{
    // Initialize the TemplateGateway
    $templateGateway = new TemplateGateway($pdo);
    
    // Retrieve the template by ID
    $template = $templateGateway->getByID($templateID);
    
    // If the template doesn't exist, return an empty string
    if (empty($template)) {
        return '';
    }
    
    // Process the template with the provided data
    return processTemplate($template, $data);
}
```

### 1.2 Function Documentation
Thorough documentation is crucial for maintaining and understanding your code. Always include detailed DocBlocks for your functions:

```php
<?php
/**
 * Processes a template with provided data
 *
 * This function takes a template array and a data array, then replaces
 * placeholders in the template with corresponding values from the data.
 * It's the core function for generating reports from templates.
 *
 * @param array $template Associative array with keys: id, name, content
 * @param array $data Associative array with keys matching template variables
 * @return string The processed template content
 * @throws Exception If template processing fails
 */
function processTemplate($template, $data)
{
    // Implementation details would go here
    // This might involve parsing the template, replacing variables, etc.
}

/**
 * Validates template data before saving or updating
 *
 * This function checks if the required fields for a template are present
 * and not empty. It's used to ensure data integrity before database operations.
 *
 * @param array $data Associative array of template data to validate
 * @return array An array of validation errors, empty if all data is valid
 */
function validateTemplateData($data)
{
    $errors = [];
    
    // Check if name is present and not empty
    if (empty($data['name'])) {
        $errors[] = 'Name is required';
    }
    
    // Check if content is present and not empty
    if (empty($data['content'])) {
        $errors[] = 'Content is required';
    }
    
    // Additional validation rules could be added here
    
    return $errors;
}
```

## 2. Common Functions

### 2.1 Database Functions
These functions encapsulate common database operations, promoting code reuse and consistency:

```php
<?php
/**
 * Retrieves a template by ID with error checking
 *
 * This function safely retrieves a template from the database. It includes
 * error checking to handle cases where the template might not exist.
 *
 * @param \PDO $pdo Database connection object
 * @param int $templateID The ID of the template to retrieve
 * @return array|null The template data as an array, or null if not found
 */
function getTemplateByID($pdo, $templateID)
{
    $templateGateway = new TemplateGateway($pdo);
    $template = $templateGateway->getByID($templateID);
    
    // Return the template if found, otherwise return null
    return !empty($template) ? $template : null;
}

/**
 * Retrieves all reports associated with a specific template
 *
 * This function queries the database for all reports generated from
 * a particular template, sorted by creation date in descending order.
 *
 * @param \PDO $pdo Database connection object
 * @param int $templateID The ID of the template to fetch reports for
 * @return array An array of report objects
 */
function getReportsByTemplate($pdo, $templateID)
{
    $reportGateway = new ReportGateway($pdo);
    
    // Create query criteria
    $criteria = $reportGateway->newQueryCriteria()
        ->filterBy('templateID', $templateID) // Filter by the specified template ID
        ->sortBy(['timestampCreated DESC']); // Sort by creation time, newest first
    
    // Execute the query and return the results
    return $reportGateway->queryReports($criteria);
}
```

### 2.2 Form Functions
These functions handle form creation and processing, ensuring consistency across your module:

```php
<?php
/**
 * Creates a standardized template form
 *
 * This function generates a form for creating or editing templates. It uses
 * Gibbon's Form API to ensure consistency with the rest of the platform.
 *
 * @param string $actionURL The URL where the form will be submitted
 * @param array $values Pre-filled values for the form fields (optional)
 * @return \Gibbon\Forms\Form A Form object ready for rendering
 */
function createTemplateForm($actionURL, $values = [])
{
    $form = Form::create('templateForm', $actionURL);
    
    $form->addHiddenValue('address', $_GET['q']);
    
    // Add name field
    $row = $form->addRow();
        $row->addLabel('name', __('Name'))
            ->description(__('Template name'))
            ->required();
        $row->addTextField('name')
            ->required()
            ->maxLength(90)
            ->setValue($values['name'] ?? '');
    
    // Add content field
    $row = $form->addRow();
        $row->addLabel('content', __('Content'))
            ->description(__('Template content'))
            ->required();
        $row->addTextArea('content')
            ->required()
            ->setRows(10)
            ->setValue($values['content'] ?? '');
    
    // Add form actions
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
    
    return $form;
}
```

## 3. Helper Functions

### 3.1 String Functions
These utility functions assist with string manipulation tasks common in template processing:

```php
<?php
/**
 * Replaces template variables with actual data
 *
 * This function takes a template string containing placeholders (e.g., {variable})
 * and replaces them with corresponding values from the data array.
 *
 * @param string $template The template content with placeholders
 * @param array $data An associative array of variable names and their values
 * @return string The processed content with placeholders replaced
 */
function replaceTemplateVariables($template, $data)
{
    // Extract all variables from the template
    $variables = extractTemplateVariables($template);
    
    // Replace each variable with its corresponding value
    foreach ($variables as $variable) {
        $value = $data[$variable] ?? ''; // Use empty string if variable not found
        $template = str_replace('{'.$variable.'}', $value, $template);
    }
    
    return $template;
}

/**
 * Extracts variable names from template content
 *
 * This function uses a regular expression to find all placeholders
 * in the format {variable_name} within the template string.
 *
 * @param string $template The template content to analyze
 * @return array An array of variable names (without brackets)
 */
function extractTemplateVariables($template)
{
    preg_match_all('/\{([^}]+)\}/', $template, $matches);
    
    return $matches[1] ?? [];
}
```

### 3.2 Date Functions
These functions handle date formatting and retrieval tasks specific to the academic context:

```php
<?php
/**
 * Formats a date string for display
 *
 * This function uses Gibbon's Format class to ensure consistent
 * date formatting across the platform.
 *
 * @param string $date The date string to format
 * @param string $format The desired output format (default: 'Y-m-d')
 * @return string The formatted date string
 */
function formatReportDate($date, $format = 'Y-m-d')
{
    return Format::date($date, $format);
}

/**
 * Retrieves the start and end dates of the current academic year
 *
 * This function queries the system settings to get the official
 * start and end dates of the academic year.
 *
 * @param \PDO $pdo Database connection object
 * @return array Associative array with 'start' and 'end' date strings
 */
function getAcademicYearDates($pdo)
{
    $settingGateway = new SettingGateway($pdo);
    
    return [
        'start' => $settingGateway->getSettingByScope('System', 'firstDayOfTheAcademicYear'),
        'end' => $settingGateway->getSettingByScope('System', 'lastDayOfTheAcademicYear')
    ];
}
```

## 4. Best Practices

### 4.1 Function Scope
When designing functions, adhere to these principles:
- Keep functions focused on a single purpose
- Use descriptive names that clearly indicate the function's purpose
- Maintain consistent return data types
- Document all parameters and return values thoroughly
- Implement appropriate error handling mechanisms

### 4.2 Error Handling
Proper error handling is crucial for robust code. Here's an example of how to implement error handling in a function:

```php
<?php
/**
 * Safely generates and saves a report
 *
 * This function demonstrates proper error handling techniques. It validates
 * inputs, checks for the existence of required data, and wraps operations
 * in a try-catch block to handle exceptions.
 *
 * @param \PDO $pdo Database connection object
 * @param int $templateID The ID of the template to use
 * @param array $data The data to populate the report
 * @return array An associative array indicating success status and relevant messages
 */
function generateAndSaveReport($pdo, $templateID, $data)
{
    try {
        // Validate inputs
        if (!is_numeric($templateID)) {
            throw new Exception('Invalid template ID');
        }
        
        // Retrieve the template
        $template = getTemplateByID($pdo, $templateID);
        if (empty($template)) {
            throw new Exception('Template not found');
        }
        
        // Generate the report content
        $report = generateReport($pdo, $templateID, $data);
        
        // Save the generated report
        $reportGateway = new ReportGateway($pdo);
        $reportID = $reportGateway->insert([
            'templateID' => $templateID,
            'content' => $report,
            'generatedAt' => date('Y-m-d H:i:s')
        ]);
        
        // Check if the report was successfully saved
        if (!$reportID) {
            throw new Exception('Failed to save report');
        }
        
        // Return success response
        return [
            'success' => true,
            'message' => 'Report generated and saved successfully',
            'reportID' => $reportID
        ];
    } catch (Exception $e) {
        // Return error response
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
```

## Practical Example
To put these concepts into practice, we'll implement essential functions for the Report Template module, including template management and report generation functions. These examples will demonstrate how to apply the best practices and patterns discussed in this guide.

## Next Steps
Now that you have a solid understanding of how to work with module functions in GibbonEdu, you're ready to move on to the Database Integration section. There, you'll learn about database schema design and implementation, which will complement your knowledge of function development and help you build more robust and efficient modules.
