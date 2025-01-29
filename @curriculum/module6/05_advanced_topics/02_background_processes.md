# Background Processes in GibbonEdu

This guide outlines the implementation of background processes in GibbonEdu modules, using the Reports module as a reference.

## 1. Batch Processing Overview

The Reports module employs a straightforward batch processing system for generating multiple reports. This system is implemented through three primary files:

1. `reports_generate_batch.php` - Provides the user interface for initiating batch generation
2. `reports_generate_batchConfirm.php` - Handles the confirmation step before processing
3. `reports_generate_batchProcess.php` - Executes the actual process of report generation

## 2. Detailed Implementation Example

### 2.1 Batch Generation Interface

```php
// File: reports_generate_batch.php
<?php
// First, we check if the user has the necessary permissions to access this page
if (!isActionAccessible($guid, $connection2, '/modules/Reports/reports_generate_batch.php')) {
    // If not, we redirect with an error message
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Retrieve the report template ID from the GET parameters
$templateID = $_GET['gibbonReportTemplateID'] ?? '';
if (empty($templateID)) {
    // If no template ID is provided, we redirect with an error
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Fetch the template details using the ReportTemplateGateway
$template = $container->get(ReportTemplateGateway::class)->getByID($templateID);

// Create a form for batch report generation
$form = Form::create('reportGenerateBatch', $session->get('absoluteURL').'/modules/Reports/reports_generate_batchConfirm.php');
$form->addHiddenValue('gibbonReportTemplateID', $templateID);

// Add a dropdown for selecting the Year Group
$row = $form->addRow();
$row->addLabel('gibbonYearGroupID', __('Year Group'));
$row->addSelectYearGroup('gibbonYearGroupID')->required();

// Add a dropdown for selecting the Form Group
$row = $form->addRow();
$row->addLabel('gibbonFormGroupID', __('Form Group'));
$row->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->required();

// Output the form
echo $form->getOutput();
```

### 2.2 Batch Confirmation

```php
// File: reports_generate_batchConfirm.php
<?php
// Retrieve the selected Year Group and Form Group IDs from the POST data
$yearGroupID = $_POST['gibbonYearGroupID'] ?? '';
$formGroupID = $_POST['gibbonFormGroupID'] ?? '';

// Fetch the list of students based on the selected Year Group and Form Group
$students = $container->get(StudentGateway::class)
    ->selectBy([
        'gibbonYearGroupID' => $yearGroupID,
        'gibbonFormGroupID' => $formGroupID
    ])
    ->fetchAll();

// Create a confirmation form
$form = Form::create('reportGenerateConfirm', $session->get('absoluteURL').'/modules/Reports/reports_generate_batchProcess.php');
$form->addHiddenValue('gibbonReportTemplateID', $templateID);
$form->addHiddenValue('gibbonYearGroupID', $yearGroupID);
$form->addHiddenValue('gibbonFormGroupID', $formGroupID);

// Create a table to display the list of students
$table = $form->addRow()->addTable()->setClass('smallIntBorder fullWidth');
$table->addHeaderRow()
    ->addColumn('student', __('Student'))
    ->addColumn('formGroup', __('Form Group'));

// Populate the table with student information
foreach ($students as $student) {
    $row = $table->addRow();
    $row->addColumn('student', Format::name('', $student['preferredName'], $student['surname'], 'Student'));
    $row->addColumn('formGroup', $student['formGroup']);
}

// Output the form
echo $form->getOutput();
```

### 2.3 Batch Processing

```php
// File: reports_generate_batchProcess.php
<?php
// Retrieve necessary parameters from the POST data
$templateID = $_POST['gibbonReportTemplateID'] ?? '';
$yearGroupID = $_POST['gibbonYearGroupID'] ?? '';
$formGroupID = $_POST['gibbonFormGroupID'] ?? '';

// Fetch the list of students based on the selected Year Group and Form Group
$students = $container->get(StudentGateway::class)
    ->selectBy([
        'gibbonYearGroupID' => $yearGroupID,
        'gibbonFormGroupID' => $formGroupID
    ])
    ->fetchAll();

// Get instances of necessary services
$generator = $container->get(ReportGenerator::class);
$reportLog = $container->get(ReportLogGateway::class);

$success = true;

// Process each student
foreach ($students as $student) {
    try {
        // Generate the report for the current student
        $report = $generator->generateReport(
            $templateID,
            $student['gibbonPersonID']
        );
        
        // Log the successful report generation
        $reportLog->insert([
            'gibbonReportTemplateID' => $templateID,
            'gibbonPersonID' => $student['gibbonPersonID'],
            'status' => 'Success',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        // Log any errors that occur during report generation
        $reportLog->insert([
            'gibbonReportTemplateID' => $templateID,
            'gibbonPersonID' => $student['gibbonPersonID'],
            'status' => 'Error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $success = false;
    }
}

// Redirect to the appropriate page based on the overall success of the batch process
$URL .= $success ? '&return=success0' : '&return=error0';
header("Location: {$URL}");
```

## 3. Progress Monitoring

The Reports module includes several views for monitoring progress:

1. **By Department** (`progress_byDepartment.php`)
   - Displays completion status grouped by department
   - Allows filtering by reporting cycle

2. **By Person** (`progress_byPerson.php`)
   - Shows individual staff progress
   - Lists outstanding reports for each staff member

3. **By Proof Reading** (`progress_byProofReading.php`)
   - Presents reports pending review
   - Shows completed reviews

4. **By Reporting Cycle** (`progress_byReportingCycle.php`)
   - Provides an overview of the entire reporting cycle progress
   - Includes deadline tracking functionality

## 4. Best Practices for Background Processes

1. **Effective Process Management**
   - Divide large batches into smaller, manageable chunks to prevent timeouts
   - Implement comprehensive logging for all operations to facilitate debugging
   - Implement robust error handling to ensure the process continues despite individual failures
   - Provide clear and frequent progress updates to the user

2. **Performance Optimization**
   - Utilize database transactions where appropriate to ensure data integrity
   - Optimize database queries to reduce processing time
   - Be mindful of memory usage, especially when dealing with large batches
   - Implement a system to clean up temporary files after processing

3. **Comprehensive Error Handling**
   - Log all errors with detailed context information
   - Design the system to continue processing even if individual items fail
   - Provide clear, user-friendly error messages
   - Implement retry mechanisms for failed items

4. **User-Friendly Interface**
   - Implement clear progress indicators to keep users informed
   - Provide options to cancel or pause long-running processes
   - Display meaningful error messages that guide users towards resolution
   - Include process summaries to give users an overview of the completed work

5. **Robust Security Measures**
   - Validate all input parameters to prevent injection attacks
   - Check user permissions at each step of the process
   - Implement secure file operations to prevent unauthorized access
   - Protect sensitive data throughout the entire process

## 5. Example Usage in Your Module

Here's how you can implement batch report generation in your own module:

```php
// In your module file

// Get necessary services
$reportGenerator = $container->get(ReportGenerator::class);
$reportLog = $container->get(ReportLogGateway::class);

// Fetch students for the batch process
$students = $container->get(StudentGateway::class)
    ->selectBy(['gibbonYearGroupID' => $yearGroupID])
    ->fetchAll();

// Process each student in the batch
foreach ($students as $student) {
    try {
        // Generate the report for the current student
        $report = $reportGenerator->generateReport(
            $templateID,
            $student['gibbonPersonID']
        );
        
        // Log successful report generation
        $reportLog->insert([
            'gibbonReportTemplateID' => $templateID,
            'gibbonPersonID' => $student['gibbonPersonID'],
            'status' => 'Success',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        // Log any errors and continue processing
        $reportLog->insert([
            'gibbonReportTemplateID' => $templateID,
            'gibbonPersonID' => $student['gibbonPersonID'],
            'status' => 'Error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
```

This implementation adheres to GibbonEdu's approach to batch processing, prioritizing reliability and proper error handling over complex queuing systems. It provides a solid foundation for implementing background processes in your own modules.
