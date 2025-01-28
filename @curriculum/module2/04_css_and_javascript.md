# Lesson 4: Modern Frontend Development in GibbonEdu

In this lesson, we'll learn how to create interactive and responsive user interfaces in your GibbonEdu modules using modern frontend technologies. We'll focus on three main areas:
1. Styling your module with CSS
2. Adding interactivity with Alpine.js
3. Making dynamic server requests with HTMX

## Part 1: Styling Your Module with CSS

CSS (Cascading Style Sheets) controls how your module looks. GibbonEdu uses a consistent styling approach to ensure all modules feel like a natural part of the system.

### Basic CSS Structure

Create a `module.css` file in your module's `css` directory:

```css
/* css/module.css */

/* Module Container Styles */
.equipment-tracker {
    /* Add padding inside the container */
    padding: 20px;
    /* Use a white background */
    background: #fff;
    /* Slightly round the corners */
    border-radius: 4px;
    /* Add a subtle shadow */
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Status Indicators */
.status-badge {
    /* Make it an inline block so it sits nicely in text */
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.9em;
    font-weight: 600;
}

/* Different status colors */
.status-available {
    background-color: #DFF0D8;
    color: #3C763D;
}

.status-borrowed {
    background-color: #FCF8E3;
    color: #8A6D3B;
}

/* Responsive Grid Layout */
.equipment-grid {
    /* Use CSS Grid for layout */
    display: grid;
    /* Create columns that are at least 250px wide */
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    padding: 20px;
}
```

### Including Your CSS

Add your CSS file to your module's manifest.php:

```php
// manifest.php
$stylesheets = [
    'css/module.css'
];
```

## Part 2: Interactive UI with Alpine.js

Alpine.js is a lightweight JavaScript framework that makes it easy to add interactivity to your pages. It's now the preferred way to handle frontend interactions in GibbonEdu v28+.

### What is Alpine.js?

Alpine.js lets you add interactivity directly in your HTML using special x- attributes. It's much simpler than traditional JavaScript and perfect for most module needs.

### Basic Alpine.js Example

Here's a simple equipment status toggle:

```html
<!-- templates/equipment.twig.html -->
<div x-data="{ status: 'available' }">
    <!-- The text updates automatically when status changes -->
    <p>Current Status: <span x-text="status"></span></p>
    
    <!-- Button changes the status when clicked -->
    <button x-on:click="status = (status === 'available' ? 'borrowed' : 'available')">
        Toggle Status
    </button>
    
    <!-- Conditionally show different badges -->
    <div>
        <span x-show="status === 'available'" 
              class="status-badge status-available">
            Available
        </span>
        <span x-show="status === 'borrowed'" 
              class="status-badge status-borrowed">
            Borrowed
        </span>
    </div>
</div>
```

### Common Alpine.js Directives

1. `x-data`: Defines a scope for your component
```html
<div x-data="{ count: 0 }">
    <!-- This div and its children can access 'count' -->
</div>
```

2. `x-text`: Updates text content
```html
<span x-text="count"></span>
```

3. `x-show`: Conditionally shows/hides elements
```html
<div x-show="count > 0">You have items</div>
```

4. `x-on`: (or @) Handles events
```html
<button x-on:click="count++">Add One</button>
```

## Part 3: Dynamic Server Requests with HTMX

HTMX allows you to make server requests and update page content without writing JavaScript. It's perfect for creating dynamic interfaces in GibbonEdu modules.

### What is HTMX?

HTMX extends HTML to allow you to access modern browser features directly in HTML attributes. This means you can update content, make server requests, and handle responses all in your HTML.

### Basic HTMX Example

Here's how to create a live-updating equipment list:

```html
<!-- templates/equipment-list.twig.html -->
<div class="equipment-tracker">
    <!-- This button will load more equipment when clicked -->
    <button hx-get="/modules/Equipment/fetchMore.php"
            hx-target="#equipment-list"
            hx-swap="beforeend">
        Load More Equipment
    </button>
    
    <!-- The equipment list that will be updated -->
    <div id="equipment-list" class="equipment-grid">
        {% for item in equipment %}
        <div class="equipment-card"
             hx-get="/modules/Equipment/status.php?id={{ item.id }}"
             hx-trigger="every 30s">
            <h3>{{ item.name }}</h3>
            <p>Status: {{ item.status }}</p>
        </div>
        {% endfor %}
    </div>
</div>
```

### Common HTMX Attributes

1. `hx-get`: Makes a GET request
```html
<button hx-get="/api/data">Load Data</button>
```

2. `hx-post`: Makes a POST request
```html
<form hx-post="/api/submit">
    <input name="data">
    <button type="submit">Submit</button>
</form>
```

3. `hx-target`: Specifies where to put the response
```html
<button hx-get="/api/data" hx-target="#result">
    Load into #result
</button>
<div id="result"></div>
```

4. `hx-trigger`: Specifies when to make the request
```html
<!-- Refresh every 30 seconds -->
<div hx-get="/status" hx-trigger="every 30s">
    Status will update automatically
</div>
```

## Putting It All Together

Here's a complete example combining CSS, Alpine.js, and HTMX:

```html
<!-- templates/equipment-manager.twig.html -->
<div class="equipment-tracker" 
     x-data="{ filterStatus: 'all' }">
    
    <!-- Filter Buttons -->
    <div class="filter-buttons">
        <button x-on:click="filterStatus = 'all'"
                x-bind:class="{ 'active': filterStatus === 'all' }">
            All Equipment
        </button>
        <button x-on:click="filterStatus = 'available'"
                x-bind:class="{ 'active': filterStatus === 'available' }">
            Available Only
        </button>
    </div>
    
    <!-- Equipment List -->
    <div id="equipment-list" 
         class="equipment-grid"
         hx-get="/modules/Equipment/list.php"
         hx-trigger="filterStatus changed from:body"
         hx-include="[name='filterStatus']">
        <!-- Equipment items will be loaded here -->
    </div>
    
    <!-- Add New Equipment Form -->
    <form hx-post="/modules/Equipment/add.php"
          hx-target="#equipment-list"
          hx-swap="beforeend">
        <input type="text" 
               name="equipmentName" 
               placeholder="New Equipment Name">
        <button type="submit">Add Equipment</button>
    </form>
</div>
```

### The Backend Part (PHP)

For the above HTML to work, you'll need corresponding PHP endpoints:

```php
// modules/Equipment/list.php
<?php
// Get the filter status from the request
$filterStatus = $_GET['filterStatus'] ?? 'all';

// Use the QueryableGateway to fetch equipment
$gateway = $container->get(EquipmentGateway::class);
$criteria = new QueryCriteria();

if ($filterStatus !== 'all') {
    $criteria->addFilterRules([
        'status' => $filterStatus
    ]);
}

$equipment = $gateway->queryEquipment($criteria);

// Render only the equipment items (no layout)
echo $page->fetchFromTemplate('equipment-items.twig.html', [
    'equipment' => $equipment
]);
```

## Best Practices

1. Progressive Enhancement
   - Start with basic HTML that works without JavaScript
   - Add Alpine.js for enhanced interactivity
   - Use HTMX for dynamic server interactions

2. Performance
   - Keep Alpine.js components small and focused
   - Use HTMX's `hx-trigger` wisely to avoid too many requests
   - Optimize images and CSS

3. Accessibility
   - Use semantic HTML elements
   - Include ARIA attributes where needed
   - Ensure keyboard navigation works

4. Error Handling
   - Show loading states with `htmx-indicator`
   - Handle errors gracefully
   - Provide feedback to users

## Common Pitfalls to Avoid

1. Over-complicating Components
   - Keep Alpine.js data simple
   - Split complex functionality into multiple components

2. Too Many Server Requests
   - Don't set HTMX refresh intervals too low
   - Use debouncing for frequent updates

3. CSS Conflicts
   - Use specific class names
   - Avoid generic selectors
   - Follow GibbonEdu's CSS naming conventions

## Exercise: Create an Interactive Equipment List

Try creating a simple equipment list with these features:
1. Filter by status (available/borrowed)
2. Add new equipment items
3. Auto-refresh status every minute
4. Show loading states

This will help you practice using CSS, Alpine.js, and HTMX together in a real module.

## Next Steps

Now that you understand the frontend technologies used in GibbonEdu:
1. Experiment with more complex Alpine.js components
2. Try different HTMX features like websockets
3. Create responsive layouts with CSS Grid and Flexbox
4. Learn about GibbonEdu's built-in CSS classes

Remember: The goal is to create interfaces that are both user-friendly and maintainable. Start simple and add complexity only when needed.
