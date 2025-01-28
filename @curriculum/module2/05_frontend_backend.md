# Lesson 5: Frontend and Backend Separation in GibbonEdu Module Development

## Understanding GibbonEdu's Architecture

GibbonEdu, while based on a traditional PHP architecture with server-side rendering, has evolved to incorporate modern practices for separating concerns between frontend and backend components. This separation is crucial for maintaining clean, organized, and easily maintainable code. Let's dive into how this works in the context of GibbonEdu module development.

### Basic Structure: A Closer Look

In GibbonEdu modules, we typically divide our code into two main categories: Backend and Frontend components. This division helps in organizing our code and maintaining a clear separation of concerns.

1. **Backend Components**
   These are the parts of your module that handle data processing, business logic, and database interactions. They're not directly visible to the user but form the core functionality of your module.

```plaintext
YourModule/
├── src/
│   ├── Domain/           # This is where your business logic lives
│   │   ├── Gateway.php   # Handles database access
│   │   └── Service.php   # Implements business rules
│   └── Forms/            # Manages form handling
├── moduleFunctions.php   # Contains shared functions used across your module
└── {action}_process.php  # Processes form submissions and other backend operations
```

2. **Frontend Components**
   These are the parts of your module that the user interacts with directly. They handle the presentation of data and user interface elements.

```plaintext
YourModule/
├── templates/           # Contains Twig templates for rendering HTML
│   └── pages/
├── assets/
│   ├── css/            # Stores your module's stylesheets
│   └── js/             # Houses your module's JavaScript files
└── {action}.php        # Acts as page controllers, bridging backend and frontend
```

Now, let's look at how to implement each of these components in detail.

## Backend Implementation: The Engine of Your Module

1. **Gateway Layer (Database Access)**
   The Gateway layer is responsible for all database interactions. It provides a clean, object-oriented interface to your database tables.

```php
// src/Domain/EquipmentGateway.php
namespace Gibbon\Module\EquipmentTracker\Domain;

use Gibbon\Domain\QueryableGateway;

class EquipmentGateway extends QueryableGateway
{
    // Define the table this gateway will interact with
    private static $tableName = 'equipmentTrackerEquipment';
    
    // Specify which columns can be searched
    private static $searchableColumns = ['name', 'category'];
    
    // Method to select equipment based on given criteria
    public function selectEquipment($criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'id',
                'name',
                'category',
                'status'
            ]);
            
        // This method will apply the criteria and execute the query
        return $this->runQuery($query, $criteria);
    }
}
```

This Gateway class extends `QueryableGateway`, which provides powerful query-building capabilities. The `selectEquipment` method demonstrates how to construct a query using these tools.

2. **Service Layer (Business Logic)**
   The Service layer encapsulates your business logic. It's where you implement the rules and processes specific to your module.

```php
// src/Domain/Services/EquipmentService.php
namespace Gibbon\Module\EquipmentTracker\Domain\Services;

class EquipmentService
{
    protected $gateway;
    protected $validator;
    
    // Constructor injection of dependencies
    public function __construct(EquipmentGateway $gateway)
    {
        $this->gateway = $gateway;
        $this->validator = new EquipmentValidator();
    }
    
    // Method to create new equipment
    public function createEquipment($data)
    {
        // First, validate the input data
        if (!$this->validator->validate($data)) {
            return [
                'success' => false,
                'errors' => $this->validator->getErrors()
            ];
        }
        
        // Apply business rules
        $data['status'] = 'Available';  // New equipment is always available
        $data['dateAdded'] = date('Y-m-d');  // Record the date added
        
        // Use the gateway to insert the data into the database
        $id = $this->gateway->insert($data);
        
        // Return the result
        return [
            'success' => true,
            'id' => $id
        ];
    }
}
```

This Service class demonstrates how to handle business logic, including input validation and applying business rules before interacting with the database through the Gateway.

3. **Process Files (Backend Endpoints)**
   Process files handle form submissions and other backend operations. They act as the entry point for many user actions.

```php
// equipment_manage_addProcess.php
require_once '../../gibbon.php';

// Set up the URL for redirecting after processing
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Equipment Tracker/';

// Check if the user has access to this action
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_manage_add.php')) {
    $URL .= 'error.php&error='.__('Your request failed because you do not have access to this action.');
    header("Location: {$URL}");
    exit();
}

// Get the EquipmentService from the service container
$container = $container ?? new Container();
$equipmentService = $container->get(EquipmentService::class);

// Collect and sanitize form data
$data = [
    'name' => $_POST['name'] ?? '',
    'category' => $_POST['category'] ?? '',
    'serialNumber' => $_POST['serialNumber'] ?? ''
];

// Process the request
try {
    $result = $equipmentService->createEquipment($data);
    
    if ($result['success']) {
        // Redirect to the manage page on success
        $URL .= 'equipment_manage.php&return=success0';
    } else {
        // Redirect back to the add page if there were validation errors
        $URL .= 'equipment_manage_add.php&return=error1';
    }
} catch (Exception $e) {
    // Handle any unexpected errors
    $URL .= 'equipment_manage_add.php&return=error2';
}

// Redirect to the appropriate page
header("Location: {$URL}");
```

This process file demonstrates how to handle form submissions, including access checks, data collection, error handling, and redirection.

## Frontend Implementation: The Face of Your Module

1. **Page Controllers**
   Page controllers act as a bridge between your backend and frontend. They prepare data for display and load the appropriate templates.

```php
// equipment_manage.php
require_once '../../gibbon.php';

// Check if the user has access to this page
if (!isActionAccessible($guid, $connection2, '/modules/Equipment Tracker/equipment_manage.php')) {
    // Deny access if the user doesn't have permission
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Set up page properties, including breadcrumbs
$page->breadcrumbs
    ->add(__('Equipment Tracker'), 'index.php')
    ->add(__('Manage Equipment'));

// Get the EquipmentGateway from the service container
$equipmentGateway = $container->get(EquipmentGateway::class);

// Prepare data for the view
$criteria = new QueryCriteria($pdo);
$equipment = $equipmentGateway->selectEquipment($criteria);

// Load and render the Twig template
$page->write(
    $container->get('twig')->render('equipment/manage.twig.html', [
        'equipment' => $equipment,
        'criteria' => $criteria
    ])
);
```

This page controller demonstrates how to check access, set up page properties, fetch data, and render a template.

2. **Twig Templates**
   Twig templates are used for rendering HTML. They provide a clean, logical syntax for displaying data and implementing page structure.

```twig
{# templates/equipment/manage.twig.html #}
{% extends "module-template.twig.html" %}

{% block content %}
    <h2>{{ __('Manage Equipment') }}</h2>

    {# Add button #}
    <div class="flex justify-end mb-4">
        <a href="{{ absoluteURL }}/index.php?q=/modules/Equipment Tracker/equipment_manage_add.php" 
           class="button">
            {{ __('Add Equipment') }}
        </a>
    </div>

    {# Equipment table #}
    <table class="w-full">
        <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Category') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            {% for item in equipment %}
                <tr>
                    <td>{{ item.name }}</td>
                    <td>{{ item.category }}</td>
                    <td>{{ item.status }}</td>
                    <td>
                        <a href="{{ absoluteURL }}/index.php?q=/modules/Equipment Tracker/equipment_manage_edit.php&id={{ item.id }}"
                           class="button">
                            {{ __('Edit') }}
                        </a>
                    </td>
                </tr>
            {% endfor %}
        </tbody>
    </table>
{% endblock %}
```

This Twig template shows how to structure a page, display data in a table, and create action buttons.

3. **JavaScript Integration**
   JavaScript is used to add interactivity to your pages. In GibbonEdu, we typically use jQuery for DOM manipulation and AJAX requests.

```javascript
// assets/js/module.js
(function(window, document, $) {
    'use strict';
    
    // Create a namespace for our module
    var EquipmentTracker = window.EquipmentTracker || {};
    
    // Equipment management functionality
    EquipmentTracker.Equipment = {
        init: function() {
            this.bindEvents();
            this.setupDataTable();
        },
        
        bindEvents: function() {
            // Handle delete confirmation
            $('.delete-equipment').on('click', function(e) {
                if (!confirm(__('Are you sure you want to delete this equipment?'))) {
                    e.preventDefault();
                }
            });
            
            // Handle status updates
            $('.update-status').on('change', function() {
                var id = $(this).data('id');
                var status = $(this).val();
                
                $.ajax({
                    url: 'modules/Equipment Tracker/equipment_manage_statusAjax.php',
                    method: 'POST',
                    data: {
                        id: id,
                        status: status
                    },
                    success: function(response) {
                        if (response.success) {
                            $.notify(__('Status updated successfully'));
                        } else {
                            $.notify(__('Failed to update status'), 'error');
                        }
                    }
                });
            });
        },
        
        setupDataTable: function() {
            // Initialize DataTable with sorting and filtering
            $('.equipment-table').DataTable({
                pageLength: 25,
                searching: true,
                ordering: true,
                columns: [
                    null,               // Name
                    null,               // Category
                    { orderable: false }, // Status
                    { orderable: false }  // Actions
                ]
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        EquipmentTracker.Equipment.init();
    });
    
})(window, document, jQuery);
```

This JavaScript file demonstrates how to structure client-side code, handle events, make AJAX requests, and initialize plugins like DataTables.

## Modern Frontend Architecture

### HTMX and Alpine.js

GibbonEdu v28+ introduces HTMX for server communication and Alpine.js for client-side interactivity. This modern approach simplifies dynamic interface development while reducing JavaScript code.

### Basic Structure

```plaintext
YourModule/
├── src/
│   └── Domain/
├── templates/
│   └── pages/
├── assets/
│   └── css/
└── routes/
    └── index.php
```

### HTMX-Ready Endpoints

```php
// endpoints/list.php
<?php
namespace Gibbon\Module\Equipment\Endpoints;

class ListEndpoint extends Endpoint
{
    public function handle()
    {
        // Get filter parameters from request
        $status = $_GET['status'] ?? 'all';
        
        // Use the gateway to fetch data
        $equipment = $this->gateway
            ->selectEquipment()
            ->where('status', $status)
            ->fetch();
            
        // Return HTML fragment
        return $this->view->render('components/equipment-list.twig.html', [
            'equipment' => $equipment
        ]);
    }
}
```

### Service Layer with Modern Error Handling

```php
// src/Domain/Services/EquipmentService.php
namespace Gibbon\Module\Equipment\Services;

class EquipmentService
{
    public function createEquipment($data): Response
    {
        try {
            // Validate input
            $this->validator->validate($data);
            
            // Create equipment
            $id = $this->gateway->insert($data);
            
            // Return success response with HTML fragment
            return Response::success()
                ->withHtml($this->view->render('components/equipment-item.twig.html', [
                    'item' => $this->gateway->getById($id)
                ]));
        } catch (ValidationException $e) {
            // Return validation errors for form
            return Response::error()
                ->withErrors($e->getErrors())
                ->withStatus(422);
        }
    }
}
```

### Page Templates with HTMX

```html
<!-- templates/pages/equipment.twig.html -->
{% extends "layout.twig.html" %}

{% block content %}
<div class="equipment-manager"
     x-data="{ 
         filter: 'all',
         loading: false
     }">
    <!-- Filter Controls -->
    <div class="filter-controls">
        <button x-on:click="filter = 'all'"
                x-bind:class="{ active: filter === 'all' }">
            All Equipment
        </button>
        <button x-on:click="filter = 'available'"
                x-bind:class="{ active: filter === 'available' }">
            Available Only
        </button>
    </div>
    
    <!-- Equipment List with HTMX -->
    <div id="equipment-list"
         hx-get="/modules/Equipment/endpoints/list.php"
         hx-trigger="filter-changed from:body"
         hx-include="[name='filter']"
         hx-indicator="#loading">
        <!-- Initial content loaded server-side -->
        {% include "components/equipment-list.twig.html" %}
    </div>
    
    <!-- Loading Indicator -->
    <div id="loading" class="htmx-indicator">
        Loading...
    </div>
    
    <!-- Add Equipment Form -->
    <form hx-post="/modules/Equipment/endpoints/actions.php"
          hx-target="#equipment-list"
          hx-swap="beforeend">
        <input type="text" 
               name="name" 
               placeholder="Equipment Name"
               required>
        <button type="submit">Add Equipment</button>
    </form>
</div>
{% endblock %}
```

### Reusable Components

```html
<!-- templates/components/equipment-item.twig.html -->
<div class="equipment-item"
     x-data="{ expanded: false }"
     hx-get="/modules/Equipment/endpoints/status.php?id={{ item.id }}"
     hx-trigger="every 30s">
    
    <div class="item-header"
         x-on:click="expanded = !expanded">
        <h3>{{ item.name }}</h3>
        <span class="status-badge status-{{ item.status|lower }}">
            {{ item.status }}
        </span>
    </div>
    
    <div x-show="expanded"
         x-collapse>
        <div class="item-details">
            <p>Category: {{ item.category }}</p>
            <p>Serial: {{ item.serialNumber }}</p>
        </div>
        
        <!-- Actions -->
        <div class="item-actions"
             hx-target="closest .equipment-item"
             hx-swap="outerHTML">
            {% if item.status == 'Available' %}
            <button hx-post="/modules/Equipment/endpoints/actions.php"
                    hx-vals='{"action": "borrow", "id": "{{ item.id }}"}'>
                Borrow
            </button>
            {% else %}
            <button hx-post="/modules/Equipment/endpoints/actions.php"
                    hx-vals='{"action": "return", "id": "{{ item.id }}"}'>
                Return
            </button>
            {% endif %}
        </div>
    </div>
</div>
```

### Route Controllers

```php
// routes/index.php
<?php
namespace Gibbon\Module\Equipment\Routes;

class EquipmentController
{
    public function index()
    {
        // Check permissions
        if (!$this->hasAccess('equipment_view')) {
            return $this->error('Access Denied');
        }
        
        // Set up page
        $this->page->breadcrumbs
            ->add(__('Equipment Manager'));
            
        // Initial data load
        $equipment = $this->gateway
            ->selectEquipment()
            ->fetch();
            
        // Render full page
        return $this->view->render('pages/equipment.twig.html', [
            'equipment' => $equipment
        ]);
    }
}
```

## Best Practices for Modern GibbonEdu Development

1. **Progressive Enhancement**
   - Start with basic HTML that works without JavaScript
   - Add Alpine.js for enhanced interactivity
   - Use HTMX for dynamic server interactions

2. **Component Organization**
   - Keep components small and focused
   - Use Twig includes for reusability
   - Follow a consistent naming convention

3. **State Management**
   - Use Alpine.js for local component state
   - Use HTMX for server-driven state changes
   - Keep state management simple and predictable

4. **Performance**
   - Return minimal HTML fragments from endpoints
   - Use HTMX's `hx-trigger` wisely
   - Implement proper caching strategies

5. **Error Handling**
   - Return appropriate HTTP status codes
   - Show user-friendly error messages
   - Implement proper validation feedback

## Common Patterns

1. **Live Search**
```html
<input type="search"
       name="query"
       hx-get="/search"
       hx-trigger="keyup changed delay:500ms"
       hx-target="#results">
<div id="results"></div>
```

2. **Infinite Scroll**
```html
<div hx-get="/more"
     hx-trigger="revealed"
     hx-swap="beforeend">
    <!-- Content -->
</div>
```

3. **Form Validation**
```html
<form hx-post="/submit"
      x-data="{ submitting: false }"
      x-on:htmx:before-request="submitting = true"
      x-on:htmx:after-request="submitting = false">
    <!-- Form fields -->
    <button x-bind:disabled="submitting">
        Submit
    </button>
</form>
```

## Exercise: Build a Modern Equipment Manager

Create an equipment management interface that demonstrates:
1. Live filtering with HTMX
2. Interactive details with Alpine.js
3. Real-time status updates
4. Form submission with validation
5. Loading states and error handling

This exercise will help you understand how all the pieces fit together in a modern GibbonEdu module.

## Next Steps

1. Study the GibbonEdu core modules for more examples
2. Experiment with advanced HTMX features
3. Learn about Alpine.js plugins
4. Practice building reusable components

Remember: The goal is to create maintainable, user-friendly interfaces while keeping the codebase simple and understandable.
