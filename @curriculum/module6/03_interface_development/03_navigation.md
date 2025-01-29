# Navigation and Menus

This comprehensive guide explains how to implement navigation and menus in your Report Template module, ensuring a user-friendly and intuitive interface.

## 1. Module Navigation

### 1.1 Module Menu
Define the module menu in `manifest.php`. This is crucial for integrating your module into the Gibbon framework:

```php
<?php
// manifest.php

// Define action rows for the module menu
$actionRows[] = [
    'name'                      => __('Manage Templates'), // The name that appears in the menu
    'precedence'                => '0',                    // Lower numbers appear first in the menu
    'category'                  => __('Templates'),        // Groups related actions together
    'description'               => __('Create and edit report templates'), // Hover text for more info
    'URLList'                   => 'templates_manage.php,templates_manage_add.php,templates_manage_edit.php,templates_manage_delete.php', // All pages related to this action
    'entryURL'                  => 'templates_manage.php', // The main page for this action
    'entrySidebar'              => 'Y',                    // 'Y' to show in sidebar, 'N' to hide
    'menuShow'                  => 'Y',                    // 'Y' to show in main menu, 'N' to hide
    'defaultPermissionAdmin'    => 'Y',                    // 'Y' allows access by default for admins
    'defaultPermissionTeacher'  => 'N',                    // 'N' restricts access by default for teachers
    'defaultPermissionStudent'  => 'N',                    // 'N' restricts access by default for students
    'defaultPermissionParent'   => 'N',                    // 'N' restricts access by default for parents
    'defaultPermissionSupport'  => 'N',                    // 'N' restricts access by default for support staff
    'categoryPermissionStaff'   => 'Y',                    // 'Y' allows all staff to view this category
    'categoryPermissionStudent' => 'N',                    // 'N' hides this category from students
    'categoryPermissionParent'  => 'N',                    // 'N' hides this category from parents
    'categoryPermissionOther'   => 'N',                    // 'N' hides this category from other user types
];

// Define another action for report creation
$actionRows[] = [
    'name'                      => __('Create Report'),
    'precedence'                => '1',
    'category'                  => __('Reports'),
    'description'               => __('Generate reports using templates'),
    'URLList'                   => 'reports_create.php,reports_create_step1.php,reports_create_step2.php,reports_create_step3.php',
    'entryURL'                  => 'reports_create.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];
```

### 1.2 Breadcrumbs
Add breadcrumb navigation to help users understand their location within the module:

```php
<?php
// templates_manage_edit.php

// Setup breadcrumbs for easy navigation
$page->breadcrumbs
    ->add(__('Manage Templates'), 'templates_manage.php')
    ->add(__('Edit Template'));

// Retrieve the template to be edited
$templateID = $_GET['id'] ?? '';
$template = $templateGateway->getByID($templateID);

// Check if the template exists
if (empty($template)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

// Add page title for clarity
$page->write('<h2>');
$page->write(__('Edit Template'));
$page->write('</h2>');
```

### 1.3 Sidebar Navigation
Create a sidebar menu to provide quick access to related actions:

```php
<?php
// templates/sidebar.php

use Gibbon\Forms\Prefab\MenuPanel;

// Create a sidebar menu panel
$sidebar = $container->get(MenuPanel::class);

// Define template-related actions
$templateActions = [
    [
        'name' => __('Add Template'),
        'url' => 'templates_manage_add.php',
        'icon' => 'page_add'
    ],
    [
        'name' => __('Import Template'),
        'url' => 'templates_manage_import.php',
        'icon' => 'upload'
    ],
    [
        'name' => __('Export Template'),
        'url' => 'templates_manage_export.php',
        'icon' => 'download'
    ]
];

// Add template actions to the sidebar
foreach ($templateActions as $action) {
    if (isActionAccessible($guid, $connection2, '/modules/ReportTemplate/'.$action['url'])) {
        $sidebar->addItem($action['name'], $action['url'])
            ->setIcon($action['icon']);
    }
}

// Add report-specific actions when editing a template
if (!empty($templateID)) {
    $reportActions = [
        [
            'name' => __('Preview Template'),
            'url' => 'templates_manage_preview.php',
            'icon' => 'preview'
        ],
        [
            'name' => __('Duplicate Template'),
            'url' => 'templates_manage_duplicate.php',
            'icon' => 'copy'
        ],
        [
            'name' => __('Delete Template'),
            'url' => 'templates_manage_delete.php',
            'icon' => 'garbage',
            'modal' => true
        ]
    ];
    
    foreach ($reportActions as $action) {
        $url = $action['url'].'?id='.$templateID;
        $item = $sidebar->addItem($action['name'], $url)
            ->setIcon($action['icon']);
            
        // Open action in a modal window if specified
        if ($action['modal'] ?? false) {
            $item->modalWindow(650, 400);
        }
    }
}

return $sidebar;
```

## 2. Page Navigation

### 2.1 Tabs Navigation
Create tabbed interfaces for organizing complex forms or related content:

```php
<?php
// templates_manage_edit.php

// Define tabs for template editing
$tabs = [
    'details' => __('Details'),
    'sections' => __('Sections'),
    'layout' => __('Layout'),
    'access' => __('Access'),
    'history' => __('History')
];

// Create tab navigation
$page->write('<div class="tabs">');
$page->write('<ul class="nav nav-tabs">');

foreach ($tabs as $tab => $label) {
    $active = ($_GET['tab'] ?? 'details') == $tab ? 'active' : '';
    $url = "templates_manage_edit.php?id=$templateID&tab=$tab";
    
    $page->write("<li class='$active'>");
    $page->write("<a href='$url'>$label</a>");
    $page->write('</li>');
}

$page->write('</ul>');
$page->write('</div>');

// Load content based on the selected tab
$currentTab = $_GET['tab'] ?? 'details';
switch ($currentTab) {
    case 'details':
        include __DIR__.'/templates/edit_details.php';
        break;
    case 'sections':
        include __DIR__.'/templates/edit_sections.php';
        break;
    case 'layout':
        include __DIR__.'/templates/edit_layout.php';
        break;
    case 'access':
        include __DIR__.'/templates/edit_access.php';
        break;
    case 'history':
        include __DIR__.'/templates/edit_history.php';
        break;
}
```

### 2.2 Pagination
Add pagination to lists for better performance and user experience:

```php
<?php
// templates_manage.php

use Gibbon\Tables\DataTable;
use Gibbon\Domain\QueryCriteria;

// Setup query criteria for filtering and sorting
$criteria = $container->get(QueryCriteria::class);

// Add filters based on user input
$criteria->filterBy('name', $_GET['search'] ?? '')
         ->filterBy('active', $_GET['active'] ?? '');

// Set up sorting and pagination
$criteria->sortBy(['name', 'active'])
         ->pageSize(50)
         ->fromPOST();

// Fetch templates based on criteria
$templates = $templateGateway->queryTemplates($criteria);

// Create a paginated data table
$table = DataTable::createPaginated('templates', $criteria);

// Modify row appearance based on template status
$table->modifyRows(function($template, $row) {
    if ($template['active'] == 'N') {
        $row->addClass('error');
    }
    return $row;
});

// Add columns to the table
$table->addColumn('name', __('Name'))
      ->sortable(['name']);
      
$table->addColumn('description', __('Description'));

$table->addColumn('active', __('Active'))
      ->format(Format::using('yesNo', ['active']))
      ->sortable(['active']);

// Add action buttons for each template
$table->addActionColumn()
      ->addParam('id')
      ->format(function($template, $actions) {
          $actions->addAction('edit', __('Edit'))
              ->setURL('/modules/ReportTemplate/templates_manage_edit.php');
              
          $actions->addAction('delete', __('Delete'))
              ->setURL('/modules/ReportTemplate/templates_manage_delete.php')
              ->modalWindow(650, 400);
              
          $actions->addAction('preview', __('Preview'))
              ->setURL('/modules/ReportTemplate/templates_manage_preview.php')
              ->setIcon('preview');
      });

// Render the table
echo $table->render($templates);
```

### 2.3 Search and Filters
Add search functionality to help users find specific items:

```php
<?php
// templates_manage.php

// Create a search form
$form = Form::create('search', '');
$form->setMethod('get');

// Add a search field for template name
$row = $form->addRow();
    $row->addLabel('search', __('Search'));
    $row->addTextField('search')
        ->setValue($_GET['search'] ?? '')
        ->placeholder(__('Search by name'));

// Add a dropdown to filter by active status
$row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addSelect('active')
        ->fromArray(['' => __('All'), 'Y' => __('Yes'), 'N' => __('No')])
        ->selected($_GET['active'] ?? '');

// Add submit button
$row = $form->addRow();
    $row->addSearchSubmit($gibbon->session);

// Output the form
echo $form->getOutput();
```

## 3. User Flow

### 3.1 Multi-Step Forms
Create step-by-step navigation for complex processes:

```php
<?php
// reports_create.php

// Define steps for report creation
$steps = [
    1 => ['name' => __('Choose Template'), 'file' => 'step1.php'],
    2 => ['name' => __('Select Students'), 'file' => 'step2.php'],
    3 => ['name' => __('Configure Options'), 'file' => 'step3.php'],
    4 => ['name' => __('Generate Reports'), 'file' => 'step4.php']
];

// Get current step from URL parameter
$step = $_GET['step'] ?? 1;

// Add progress bar to show current step
$page->write('<div class="progress-bar">');
foreach ($steps as $number => $stepInfo) {
    $class = $number == $step ? 'current' : 
            ($number < $step ? 'complete' : '');
            
    $page->write("<div class='step $class'>");
    $page->write("<span class='number'>$number</span>");
    $page->write("<span class='name'>{$stepInfo['name']}</span>");
    $page->write('</div>');
}
$page->write('</div>');

// Include content for the current step
include __DIR__.'/reports/create_'.$steps[$step]['file'];

// Add navigation buttons
$form = Form::create('stepNavigation', '');

$row = $form->addRow();
    // Add 'Previous' button if not on the first step
    if ($step > 1) {
        $row->addButton(__('Previous'))
            ->onClick("window.location = 'reports_create.php?step=".($step-1)."'");
    }
    
    // Add 'Next' or 'Generate' button
    if ($step < count($steps)) {
        $row->addSubmit(__('Next'));
    } else {
        $row->addSubmit(__('Generate'));
    }

// Output the navigation form
echo $form->getOutput();
```

### 3.2 Action Buttons
Add consistent action buttons for common operations:

```php
<?php
// templates_manage_edit.php

// Add action buttons for saving and canceling
$page->write('<div class="action-buttons">');

// Save button
$page->write('<a href="#" class="button" onclick="document.getElementById(\'templateEdit\').submit();">');
$page->write('<img src="'.$session->get('absoluteURL').'/themes/'.$session->get('gibbonThemeName').'/img/icons/save.png" alt="'.__('Save').'" title="'.__('Save').'" /> ');
$page->write(__('Save'));
$page->write('</a>');

// Cancel button
$page->write('<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/ReportTemplate/templates_manage.php" class="button">');
$page->write('<img src="'.$session->get('absoluteURL').'/themes/'.$session->get('gibbonThemeName').'/img/icons/cancel.png" alt="'.__('Cancel').'" title="'.__('Cancel').'" /> ');
$page->write(__('Cancel'));
$page->write('</a>');

$page->write('</div>');
```

## 4. Best Practices

### 4.1 Navigation Design
1. Use clear hierarchy: Organize content logically
2. Show current location: Use breadcrumbs and highlight active items
3. Provide context: Use descriptive labels and icons
4. Enable quick access: Implement shortcuts for frequent actions
5. Support keyboard navigation: Ensure all navigation is accessible via keyboard

### 4.2 User Experience
1. Consistent layout: Maintain a uniform design across all pages
2. Clear feedback: Provide visual cues for user actions
3. Logical flow: Design intuitive user journeys
4. Error recovery: Offer clear error messages and recovery options
5. Progress indication: Show loading states and progress bars

### 4.3 Performance
1. Minimize page loads: Use AJAX for dynamic content where appropriate
2. Cache navigation: Store frequently accessed data client-side
3. Lazy load content: Load content as needed to improve initial page load times
4. Optimize queries: Ensure database queries are efficient
5. Use AJAX judiciously: Implement asynchronous loading for better responsiveness

## Next Steps
Continue to the next section to learn about implementing permissions and access control in your Report Template module. This will ensure that users can only access the parts of your module that are relevant to their role and permissions.
