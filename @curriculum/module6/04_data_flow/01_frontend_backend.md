# Frontend to Backend Communication

Guide to implementing frontend-backend communication in GibbonEdu modules.

## 1. Template-Based Architecture

GibbonEdu uses Twig templates for server-side rendering combined with HTMX for dynamic updates. This architecture provides:
- Server-side rendering for initial page loads
- Progressive enhancement with HTMX
- Clean separation of concerns
- Maintainable and testable code

### 1.1 Twig Templates

Templates are stored in the `templates` directory with the `.twig.html` extension. Twig allows for powerful templating features such as inheritance, includes, and macros.

```twig
{# templates/ui/writingStudentOverview.twig.html #}
{% if reportCriteria %}
    <h3>{{ __('Report Overview') }}</h3>

    {% for course, criteriaList in reportCriteria %}
        <div class="mb-3">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="p-2">
                            <h5 class="m-0 mt-1">{{ criteriaCheck.scopeName }}</h5>
                        </th>
                    </tr>
                </thead>
                {# Table content would be populated here #}
            </table>
        </div>
    {% endfor %}
{% endif %}
```

### 1.2 HTMX Integration

HTMX is used for dynamic updates without full page reloads. It's integrated using HTML attributes.

```php
// src/Forms/ReportingSidebarForm.php
$form = parent::createBlank('reportingSelector')
    ->enableQuickSubmit()
    ->setAttribute('hx-trigger', 'change from:.auto-submit');
// This form will trigger an HTMX request when any element with class 'auto-submit' changes
```

```twig
{# Template with HTMX attributes #}
<div hx-trigger="load"
     hx-get="/modules/Reports/reporting_progress.php"
     hx-target="#progressArea">
    <div id="progressArea">
        Loading...
    </div>
</div>
{# This div will automatically load content from reporting_progress.php when the page loads #}
```

## 2. Data Flow Patterns

### 2.1 Form Submission

Forms use a combination of traditional POST submissions and HTMX for enhanced interactivity.

```twig
{# Form template #}
<form method="post" 
      action="{{ absoluteURL }}/modules/Reports/reporting_writeProcess.php"
      hx-post="{{ absoluteURL }}/modules/Reports/reporting_writeProcess.php"
      hx-trigger="submit"
      hx-target="#feedbackArea">
    
    <input type="hidden" name="address" value="{{ session.get('address') }}">
    <input type="hidden" name="reportID" value="{{ reportID }}">
    
    {{ form|raw }}
</form>
{# This form can be submitted traditionally or via HTMX, depending on browser capabilities #}
```

### 2.2 Server-Side Processing

Process files handle both traditional and HTMX requests, providing appropriate responses based on the request type.

```php
// reporting_writeProcess.php
require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Reports/reporting_write.php';

// Process the form data
try {
    // Handle the submission
    $reportGateway = $container->get(ReportGateway::class);
    $success = $reportGateway->update($data);

    // Return appropriate response based on request type
    if (isHTMXRequest()) {
        // Return partial HTML for HTMX update
        echo createFeedbackElement('success', __('Report saved successfully.'));
    } else {
        // Redirect for traditional form submit
        header("Location: {$URL}&return=success0");
    }
} catch (Exception $e) {
    // Handle errors similarly
    if (isHTMXRequest()) {
        http_response_code(400);
        echo createFeedbackElement('error', $e->getMessage());
    } else {
        header("Location: {$URL}&return=error0");
    }
}
```

## 3. Security Considerations

### 3.1 CSRF Protection

Cross-Site Request Forgery (CSRF) protection is handled automatically by the Form class.

```php
// Form automatically adds CSRF token
$form = Form::create('reportEdit');
// This ensures that the form submission comes from a legitimate source
```

### 3.2 Access Control

Always validate access before processing any requests to ensure unauthorized users can't access sensitive functionality.

```php
if (!isActionAccessible($guid, $connection2, '/modules/Reports/reporting_write.php')) {
    // Handle unauthorized access
    if (isHTMXRequest()) {
        http_response_code(403);
        exit();
    } else {
        // Redirect to error page
        header("Location: {$URL}");
        exit();
    }
}
```

## 4. Best Practices

1. **Template Organization**
   - Keep templates in the `templates` directory
   - Use subdirectories for organization (ui, reports, etc.)
   - Follow Twig best practices for inheritance and includes

2. **Progressive Enhancement**
   - Ensure forms work without JavaScript
   - Use HTMX for enhanced interactivity
   - Maintain graceful degradation for users without JavaScript

3. **Error Handling**
   - Return appropriate HTTP status codes (e.g., 400 for bad requests, 403 for forbidden)
   - Provide user-friendly error messages
   - Log errors for debugging purposes

4. **Performance**
   - Use Twig template caching to reduce rendering time
   - Minimize database queries by optimizing data fetching
   - Implement appropriate indexes on frequently queried database columns

5. **Security**
   - Validate all input on both client and server sides
   - Escape output using Twig's automatic escaping to prevent XSS attacks
   - Implement proper access control for all routes and actions
   - Use CSRF protection for all forms and state-changing requests

## 5. Alpine.js Integration

GibbonEdu uses Alpine.js for reactive UI components. It provides a lightweight solution for adding interactivity to templates.

### 5.1 State Management

Alpine.js allows for easy state management within components.

```twig
{# templates/ui/reportEditor.twig.html #}
<div x-data="{ 
    isEditing: false,
    content: '',
    status: 'idle'
}">
    <div x-show="!isEditing" @click="isEditing = true">
        {{ content|default('Click to edit') }}
    </div>
    
    <textarea
        x-show="isEditing"
        x-model="content"
        @blur="isEditing = false"
        hx-post="/modules/Reports/reporting_writeProcess.php"
        hx-trigger="blur"
        hx-target="#feedback">
    </textarea>
    
    <div id="feedback" x-text="status"></div>
</div>
{# This component manages its own state for editing, content, and status #}
```

### 5.2 Component Communication

Use events for component communication, allowing decoupled components to interact.

```twig
{# Parent template #}
<div x-data @report-saved.window="showNotification($event.detail)">
    {# Child components #}
</div>

{# Child component #}
<button @click="$dispatch('report-saved', { message: 'Report saved successfully' })">
    Save Report
</button>
{# The child component can dispatch events that the parent listens for #}
```

### 5.3 Combining Alpine.js with HTMX

Alpine.js and HTMX can work together to create dynamic, reactive interfaces.

```twig
<div x-data="{ loading: false }"
     @htmx:before-request="loading = true"
     @htmx:after-request="loading = false">
     
    <button hx-post="/save-report"
            hx-target="#result"
            :disabled="loading">
        <span x-show="!loading">Save Report</span>
        <span x-show="loading">Saving...</span>
    </button>
    
    <div id="result"></div>
</div>
{# This component uses Alpine.js for UI state and HTMX for server communication #}
```

## 6. Form Validation and Processing

### 6.1 LiveValidation Integration

LiveValidation provides immediate client-side validation feedback.

```php
// Form with LiveValidation
$row = $form->addRow();
    $row->addLabel('name', __('Template Name'))
        ->description(__('Must be unique'))
        ->required();
    $row->addTextField('name')
        ->required()
        ->maxLength(90)
        ->uniqueField('./modules/Reports/templates_uniqueAjax.php')
        ->addValidation('Validate.Format', 'pattern: /^[a-zA-Z0-9_\-\s]+$/, failureMessage: "Please use only letters, numbers, hyphens and underscores"');
// This field will be validated in real-time as the user types
```

### 6.2 Server-Side Validation

Always implement server-side validation as a security measure and to ensure data integrity.

```php
// reporting_writeProcess.php
function validateReport($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['name'])) {
        $errors['name'] = __('Name is required');
    }
    
    // Length validation
    if (strlen($data['name']) > 90) {
        $errors['name'] = __('Name cannot exceed 90 characters');
    }
    
    // Format validation
    if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $data['name'])) {
        $errors['name'] = __('Name contains invalid characters');
    }
    
    return $errors;
}

// Process the form
$errors = validateReport($_POST);
if (!empty($errors)) {
    if (isHTMXRequest()) {
        http_response_code(422);
        echo createFeedbackElement('error', implode('<br>', $errors));
        exit;
    } else {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }
}
// This ensures that even if client-side validation is bypassed, the data is still validated
```

## 7. Template Inheritance

### 7.1 Base Template Structure

Create a base template that defines the overall structure of your pages.

```twig
{# templates/base.twig.html #}
<!DOCTYPE html>
<html>
<head>
    {% block head %}
        <title>{% block title %}{% endblock %} - Reports</title>
    {% endblock %}
</head>
<body>
    {% block navigation %}
        {% include 'navigation.twig.html' %}
    {% endblock %}
    
    <div class="content">
        {% block content %}{% endblock %}
    </div>
    
    {% block footer %}
        {% include 'footer.twig.html' %}
    {% endblock %}
</body>
</html>
{# This base template defines the overall structure and placeholders for child templates #}
```

### 7.2 Extending Base Template

Child templates can extend the base template and fill in the blocks.

```twig
{# templates/reports/editor.twig.html #}
{% extends "base.twig.html" %}

{% block title %}Report Editor{% endblock %}

{% block content %}
    <div class="editor-container">
        <h2>{{ report.name }}</h2>
        
        <div x-data="{ content: '{{ report.content|escape('js') }}' }">
            <textarea x-model="content"
                     hx-post="/modules/Reports/reporting_writeProcess.php"
                     hx-trigger="blur"
                     class="editor">
            </textarea>
        </div>
    </div>
{% endblock %}
{# This template extends the base template and provides specific content for the report editor #}
```

### 7.3 Reusable Components

Create reusable components to maintain consistency and reduce duplication.

```twig
{# templates/components/statusBadge.twig.html #}
{% macro statusBadge(status) %}
    <span class="badge badge-{{ status|lower }}">
        {{ status|title }}
    </span>
{% endmacro %}

{# Usage in other templates #}
{% from 'components/statusBadge.twig.html' import statusBadge %}
{{ statusBadge(report.status) }}
{# This creates a reusable status badge component that can be used across multiple templates #}
```

## 8. Best Practices

1. **Component Organization**
   - Keep Alpine.js components small and focused on a single responsibility
   - Use x-data for local state management within components
   - Leverage events for communication between components
   - Combine Alpine.js with HTMX where appropriate for optimal performance

2. **Form Implementation**
   - Use LiveValidation for immediate client-side feedback
   - Implement both client and server-side validation for security
   - Handle all form states (loading, success, error) to provide a smooth user experience
   - Provide clear, user-friendly feedback messages for all interactions

3. **Template Structure**
   - Follow a consistent template hierarchy to improve maintainability
   - Use blocks for modularity and easy overriding in child templates
   - Create reusable components for common UI elements
   - Keep templates DRY (Don't Repeat Yourself) by leveraging includes and macros

4. **Performance**
   - Minimize JavaScript bundle size by only including necessary features
   - Use lazy loading for components and content that isn't immediately visible
   - Implement template caching to reduce render times
   - Optimize Alpine.js reactivity by using x-effect for computed properties

5. **Accessibility**
   - Ensure proper ARIA attributes are used for dynamic content
   - Maintain keyboard navigation for all interactive elements
   - Provide visual feedback for all user interactions
   - Regularly test with screen readers to ensure compatibility

By following these best practices and leveraging the power of Twig, HTMX, and Alpine.js, you can create robust, interactive, and maintainable frontend interfaces for your GibbonEdu modules.
