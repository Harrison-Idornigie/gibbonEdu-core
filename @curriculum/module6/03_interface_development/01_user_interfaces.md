# Creating User Interfaces

A comprehensive guide to building user interfaces in GibbonEdu modules.

## TODO Topics

1. Interface Components
   - Page layout structure: Explains the standard layout for GibbonEdu pages
   - Navigation elements: Covers breadcrumbs, menus, and other navigation tools
   - Content areas: Describes how to structure main content sections
   - Action buttons: Guidelines for placement and styling of action buttons
   - Tables and lists: Best practices for displaying tabular data and lists

2. Design Guidelines
   - GibbonEdu UI standards: Detailed explanation of the GibbonEdu design system
   - Responsive design: Techniques for creating mobile-friendly interfaces
   - Accessibility: WCAG 2.1 compliance and accessibility best practices
   - User experience: Principles for creating intuitive and efficient interfaces
   - Visual hierarchy: How to organize information for better readability and focus

3. Template System
   - Using Twig templates: Introduction to Twig and its integration in GibbonEdu
   - Template inheritance: Explaining parent templates and how to extend them
   - Template variables: How to pass and use data in templates
   - Custom blocks: Creating reusable template components
   - Template caching: Optimizing template rendering performance

4. JavaScript Integration
   - AJAX functionality: Implementing asynchronous data loading and updates
   - Dynamic content: Techniques for updating page content without full reloads
   - Form validation: Client-side validation using JavaScript and Alpine.js
   - Interactive elements: Creating dropdowns, modals, and other interactive components
   - LiveValidation: Utilizing GibbonEdu's built-in form validation library

## Practical Example
We'll create the complete interface for the Report Template module, including:
- Template listing page: Displays all available report templates
- Template editor: Interface for creating and editing report templates
- Report preview: A live preview of the report template
- Settings interface: Module-specific configuration options

## 1. Basic Structure

### 1.1 Page Layout
Every page in your module should follow GibbonEdu's standard layout. Here's a detailed breakdown:

```php
<?php
// templates_manage.php

// Import necessary classes
use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;

// Setup page properties
$page->breadcrumbs
    ->add(__('Manage Templates'));

// Check user permissions
if (!isActionAccessible($guid, $connection2, '/modules/ReportTemplate/templates_manage.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Initialize services
$templateGateway = $container->get(TemplateGateway::class);

// Add page title
$page->write('<h2>');
$page->write(__('Manage Templates'));
$page->write('</h2>');

// Add help text to guide users
$page->write('<p>');
$page->write(__('This page allows you to create and manage report templates.'));
$page->write('</p>');

// The rest of your page content goes here...
```

### 1.2 Template Structure
Create a consistent template structure using Twig. This example shows a basic report template:

```php
<?php
// templates/template.twig.html
?>
{% extends "layout.twig.html" %}

{% block content %}
<div class="template-container">
    {# Header Section #}
    <header class="template-header">
        <div class="logo">
            {# Use the school's logo #}
            <img src="{{ absoluteURL }}/themes/{{ gibbonThemeName }}/img/logo.png" 
                 alt="{{ organisationName }}">
        </div>
        <h1>{{ templateName }}</h1>
    </header>

    {# Content Section #}
    <main class="template-content">
        {% for section in sections %}
            <section class="template-section">
                <h2>{{ section.name }}</h2>
                <div class="section-content">
                    {# Use the raw filter to render HTML content #}
                    {{ section.content|raw }}
                </div>
            </section>
        {% endfor %}
    </main>

    {# Footer Section #}
    <footer class="template-footer">
        <div class="signature-box">
            {% if signature %}
                <img src="{{ signature }}" alt="Signature">
            {% endif %}
            <p>{{ signatoryName }}</p>
            <p>{{ signatoryTitle }}</p>
        </div>
        <div class="footer-text">
            {{ footerText }}
        </div>
    </footer>
</div>
{% endblock %}
```

### 1.3 Styling
Add module-specific CSS in `css/module.css`. Here's an example with detailed comments:

```css
/* Template Styles */
.template-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: var(--font-family-sans); /* Use GibbonEdu's default sans-serif font */
}

/* Header styling */
.template-header {
    text-align: center;
    margin-bottom: 30px;
}

/* Logo sizing */
.template-header .logo img {
    max-width: 200px;
    height: auto;
}

/* Section styling */
.template-section {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Section headings */
.template-section h2 {
    color: var(--primary); /* Use GibbonEdu's primary color */
    font-size: 1.2em;
    margin-bottom: 10px;
}

/* Signature box styling */
.signature-box {
    text-align: right;
    margin-top: 30px;
}

/* Signature image sizing */
.signature-box img {
    max-width: 150px;
    height: auto;
}
```

## 2. Component Library

### 2.1 Form Components
Use GibbonEdu's form builder for consistent and accessible forms:

```php
<?php
// templates_manage_add.php

// Create a new form
$form = Form::create('templateAdd', $session->get('absoluteURL').'/modules/ReportTemplate/templates_manage_addProcess.php');

// Add a hidden field for the address
$form->addHiddenValue('address', $session->get('address'));

// Template name field
$row = $form->addRow();
    $row->addLabel('name', __('Name'))
        ->description(__('Must be unique'))
        ->required();
    $row->addTextField('name')
        ->required()
        ->maxLength(90);

// Template description field
$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')
        ->setRows(5);

// Active status field
$row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addYesNo('active')
        ->required()
        ->selected('Y');

// Header content field with rich text editor
$row = $form->addRow();
    $row->addLabel('header', __('Header'))
        ->description(__('Template header content'));
    $row->addEditor('header', $guid)
        ->setRows(10)
        ->showMedia(true);

// Footer content field with rich text editor
$row = $form->addRow();
    $row->addLabel('footer', __('Footer'))
        ->description(__('Template footer content'));
    $row->addEditor('footer', $guid)
        ->setRows(10)
        ->showMedia(true);

// Page orientation selection
$row = $form->addRow();
    $row->addLabel('orientation', __('Orientation'));
    $row->addSelect('orientation')
        ->fromArray(['P' => __('Portrait'), 'L' => __('Landscape')])
        ->required()
        ->selected('P');

// Page size selection
$row = $form->addRow();
    $row->addLabel('pageSize', __('Page Size'));
    $row->addSelect('pageSize')
        ->fromArray(['A4' => 'A4', 'Letter' => 'Letter'])
        ->required()
        ->selected('A4');

// Add submit button
$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

// Output the form
echo $form->getOutput();
```

### 2.2 Data Tables
Use GibbonEdu's data table component for consistent and feature-rich tables:

```php
<?php
// templates_manage.php

// Create a new data table
$table = DataTable::create('templates');

// Add a header action for creating new templates
$table->addHeaderAction('add', __('Add'))
    ->setURL('/modules/ReportTemplate/templates_manage_add.php')
    ->displayLabel();

// Add columns to the table
$table->addColumn('name', __('Name'));
$table->addColumn('description', __('Description'));
$table->addColumn('active', __('Active'))
    ->format(Format::using('yesNo', 'active'));
$table->addColumn('orientation', __('Orientation'))
    ->format(function($values) {
        return $values['orientation'] == 'P' ? __('Portrait') : __('Landscape');
    });

// Add action column with edit, delete, and preview options
$table->addActionColumn()
    ->addParam('id')
    ->format(function ($template, $actions) use ($guid) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/ReportTemplate/templates_manage_edit.php');
        
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/ReportTemplate/templates_manage_delete.php')
            ->modalWindow(650, 400);

        $actions->addAction('preview', __('Preview'))
            ->setURL('/modules/ReportTemplate/templates_manage_preview.php')
            ->setIcon('preview');
    });

// Render the table with the templates data
echo $table->render($templates);
```

## 3. JavaScript Integration

### 3.1 Module Scripts
Add module-specific JavaScript in `js/module.js`:

```javascript
// Template Preview Handler
$(document).ready(function() {
    // Template preview button click handler
    $('.template-preview').click(function(e) {
        e.preventDefault();
        
        var templateID = $(this).data('template-id');
        var previewURL = $(this).data('preview-url');
        
        // Show loading indicator
        $.fancybox.showLoading();
        
        // Load preview in modal
        $.fancybox.open({
            type: 'iframe',
            src: previewURL + '&templateID=' + templateID,
            opts: {
                afterShow: function() {
                    $.fancybox.hideLoading();
                },
                width: 800,
                height: 600
            }
        });
    });
    
    // Template section drag-and-drop functionality
    $('.template-sections').sortable({
        handle: '.drag-handle',
        update: function(event, ui) {
            // Get new order of sections
            var order = $(this).sortable('toArray', {
                attribute: 'data-section-id'
            });
            
            // Update section order via AJAX
            $.ajax({
                url: './modules/ReportTemplate/templates_manage_reorderAjax.php',
                type: 'POST',
                data: {
                    order: order
                },
                success: function(response) {
                    // Show success notification
                    $.notify(response.message, 'success');
                },
                error: function(xhr, status, error) {
                    // Show error notification
                    $.notify('Failed to update section order', 'error');
                }
            });
        }
    });
});
```

### 3.2 AJAX Handlers
Create AJAX handlers for dynamic updates:

```php
<?php
// templates_manage_reorderAjax.php

use Gibbon\Module\ReportTemplate\Domain\TemplateSectionGateway;

// Get the section gateway
$sectionGateway = $container->get(TemplateSectionGateway::class);

// Check for POST data
if (empty($_POST['order'])) {
    $response = [
        'success' => false,
        'message' => __('Invalid request')
    ];
    echo json_encode($response);
    exit;
}

try {
    // Update section order
    $order = $_POST['order'];
    foreach ($order as $sequence => $sectionID) {
        $sectionGateway->update($sectionID, [
            'sequenceNumber' => $sequence + 1
        ]);
    }
    
    $response = [
        'success' => true,
        'message' => __('Section order updated successfully')
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => __('Failed to update section order')
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
```

## 4. Alpine.js Integration

GibbonEdu uses Alpine.js as its primary JavaScript framework for building interactive user interfaces. This section covers how to effectively use Alpine.js in your module.

### 4.1 State Management

Use `x-data` to define reactive state:

```html
<!-- Example of a component with reactive state -->
<div x-data="{ 
    isOpen: false,
    formData: {
        name: '',
        description: '',
        status: 'draft'
    },
    validation: {
        invalid: false,
        submitting: false
    }
}">
    <!-- Component content goes here -->
</div>
```

### 4.2 Form Handling

Forms in GibbonEdu use Alpine.js for validation and state management:

```html
<!-- Example of a form with Alpine.js validation -->
<form x-validate 
      x-data="{ invalid: false, submitting: false }" 
      x-ref="form" 
      @submit="$validate.submit; invalid = !$validate.isComplete($el)"
      @change.debounce.750ms="if (invalid) invalid = !$validate.isComplete($el)">
    
    <!-- Form fields go here -->
    
    <!-- Error message -->
    <div x-show="invalid" class="alert error">
        {{ __('Please check the form and try again') }}
    </div>
    
    <!-- Submit button -->
    <button type="submit" 
            x-bind:disabled="submitting"
            x-text="submitting ? 'Saving...' : 'Save'">
        Save
    </button>
</form>
```

### 4.3 Interactive Components

Create reusable interactive components:

```html
<!-- Modal Dialog Component -->
<div x-data="{ modalOpen: false }"
     @keydown.escape.window="modalOpen = false">
     
    <button @click="modalOpen = true">Open Modal</button>
    
    <div x-show="modalOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:leave="transition ease-in duration-200"
         class="modal">
        <!-- Modal content goes here -->
        <button @click="modalOpen = false">Close</button>
    </div>
</div>

<!-- Dropdown Menu Component -->
<div x-data="{ menuOpen: false }" 
     @click.outside="menuOpen = false">
     
    <button @click="menuOpen = !menuOpen">Toggle Menu</button>
    
    <ul x-show="menuOpen"
        x-transition:enter.duration.250ms
        x-transition:leave.duration.100ms
        class="dropdown-menu">
        <!-- Menu items go here -->
    </ul>
</div>
```

### 4.4 Data Loading and HTMX Integration

Alpine.js works seamlessly with HTMX for dynamic content loading:
