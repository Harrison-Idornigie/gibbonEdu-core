# Data Access Patterns

This comprehensive guide explains how to implement data access patterns in your Report Template module using GibbonEdu's best practices. We'll cover the Gateway Pattern, Data Models, and Services, providing detailed explanations and examples for each concept.

## 1. Gateway Pattern

The Gateway Pattern provides a clean separation between your domain logic and data source. It encapsulates the logic required to access data sources, making your code more maintainable and testable.

### 1.1 Gateway Classes
GibbonEdu uses the Gateway pattern for database access. Create a gateway class for each main entity in your module. Here's an example of a TemplateGateway class:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;

class TemplateGateway extends QueryableGateway
{
    use TableAware;

    // Define table properties
    private static $tableName = 'reportTemplateTemplate';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['name', 'description'];

    /**
     * Queries all templates based on given criteria
     *
     * @param QueryCriteria $criteria The query criteria for filtering and sorting
     * @return DataSet The resulting dataset of templates
     */
    public function queryTemplates(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'id',
                'name',
                'description',
                'active',
                'header',
                'footer',
                'orientation',
                'pageSize',
                'timestampModified'
            ]);

        // Apply the criteria to the query and return the result
        return $this->runQuery($query, $criteria);
    }

    /**
     * Retrieves a single template by its ID
     *
     * @param int $id The ID of the template to retrieve
     * @return array|null The template data as an array, or null if not found
     */
    public function getByID($id)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->where('id = :id')
            ->bindValue('id', $id);

        // Execute the query and fetch the result
        return $this->runSelect($query)->fetch();
    }

    /**
     * Inserts a new template into the database
     *
     * @param array $data The template data to insert
     * @return int|false The ID of the newly inserted template, or false on failure
     */
    public function insert(array $data)
    {
        // Add creation timestamp
        $data['timestampCreated'] = date('Y-m-d H:i:s');
        
        // Insert the data and return the new ID
        return $this->insertAndUpdate($data);
    }

    /**
     * Updates an existing template in the database
     *
     * @param int $id The ID of the template to update
     * @param array $data The updated template data
     * @return bool True if the update was successful, false otherwise
     */
    public function update($id, array $data)
    {
        // Add modification timestamp
        $data['timestampModified'] = date('Y-m-d H:i:s');
        
        // Update the template and return the result
        return $this->updateWhere(['id' => $id], $data);
    }
}
```

### 1.2 Query Building
Use QueryCriteria for flexible querying. This allows for easy filtering, sorting, and pagination of your data. Here's an example of how to use QueryCriteria in a route file:

```php
<?php
// templates_manage.php

use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;

// Get the QueryCriteria object from the container
$criteria = $container->get(QueryCriteria::class);

// Add filters to the criteria
$criteria->filterBy('active', 'Y')  // Only active templates
         ->filterBy('name', $search)  // Filter by name (if search is provided)
         ->sortBy(['name']);  // Sort by name

// Add pagination
$criteria->fromPOST()  // Get pagination info from POST data
         ->pageSize(50);  // Set page size to 50 items

// Get the TemplateGateway from the container and execute the query
$templateGateway = $container->get(TemplateGateway::class);
$templates = $templateGateway->queryTemplates($criteria);

// Display results using a DataTable
$table = DataTable::createPaginated('templates', $criteria);
$table->addColumn('name', __('Name'));
$table->addColumn('description', __('Description'));
// ... add more columns as needed
```

## 2. Data Models

Data Models represent the entities in your module. They encapsulate the data and provide methods to interact with it.

### 2.1 Model Classes
Create model classes to represent your data entities. Here's an example of a Template model:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

class Template
{
    protected $id;
    protected $name;
    protected $description;
    protected $active;
    protected $header;
    protected $footer;
    protected $orientation;
    protected $pageSize;
    protected $timestampCreated;
    protected $timestampModified;

    /**
     * Constructor
     *
     * @param array $data Initial data to populate the model
     */
    public function __construct(array $data = [])
    {
        // Initialize properties with data from the array, or set default values
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->active = $data['active'] ?? 'Y';
        $this->header = $data['header'] ?? '';
        $this->footer = $data['footer'] ?? '';
        $this->orientation = $data['orientation'] ?? 'P';
        $this->pageSize = $data['pageSize'] ?? 'A4';
        $this->timestampCreated = $data['timestampCreated'] ?? '';
        $this->timestampModified = $data['timestampModified'] ?? '';
    }

    /**
     * Get the template ID
     *
     * @return int
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get the template name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    // Add getters for other properties...

    /**
     * Convert the model to an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'header' => $this->header,
            'footer' => $this->footer,
            'orientation' => $this->orientation,
            'pageSize' => $this->pageSize,
            'timestampCreated' => $this->timestampCreated,
            'timestampModified' => $this->timestampModified
        ];
    }
}
```

### 2.2 Model Factory
Create a factory class to instantiate models. This centralizes the creation of model objects and allows for easy modifications if needed:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

class TemplateFactory
{
    /**
     * Creates a Template instance from database data
     *
     * @param array $data The data from the database
     * @return Template
     */
    public function createFromData(array $data)
    {
        return new Template($data);
    }

    /**
     * Creates an array of Template instances from a database result set
     *
     * @param array $dataSet An array of data from the database
     * @return array An array of Template instances
     */
    public function createFromDataSet(array $dataSet)
    {
        return array_map(function ($data) {
            return $this->createFromData($data);
        }, $dataSet);
    }
}
```

## 3. Services

Services encapsulate the business logic of your module. They coordinate the work of multiple objects and provide a higher-level interface to other parts of your application.

### 3.1 Service Classes
Create service classes for business logic. Here's an example of a TemplateService:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

use Exception;
use Gibbon\Domain\System\SettingGateway;

class TemplateService
{
    protected $templateGateway;
    protected $templateFactory;
    protected $settingGateway;

    /**
     * Constructor
     *
     * @param TemplateGateway $templateGateway
     * @param TemplateFactory $templateFactory
     * @param SettingGateway $settingGateway
     */
    public function __construct(
        TemplateGateway $templateGateway,
        TemplateFactory $templateFactory,
        SettingGateway $settingGateway
    ) {
        $this->templateGateway = $templateGateway;
        $this->templateFactory = $templateFactory;
        $this->settingGateway = $settingGateway;
    }

    /**
     * Creates a new template
     *
     * @param array $data The template data
     * @return Template The newly created template
     * @throws Exception If the template couldn't be created
     */
    public function createTemplate(array $data)
    {
        // Validate data
        if (empty($data['name'])) {
            throw new Exception('Name is required');
        }

        // Create template
        $templateID = $this->templateGateway->insert($data);
        if (!$templateID) {
            throw new Exception('Unable to create template');
        }

        // Return new template
        return $this->getTemplateByID($templateID);
    }

    /**
     * Retrieves a template by its ID
     *
     * @param int $templateID The ID of the template to retrieve
     * @return Template The retrieved template
     * @throws Exception If the template is not found
     */
    public function getTemplateByID($templateID)
    {
        $data = $this->templateGateway->getByID($templateID);
        if (empty($data)) {
            throw new Exception('Template not found');
        }

        return $this->templateFactory->createFromData($data);
    }

    /**
     * Updates an existing template
     *
     * @param int $templateID The ID of the template to update
     * @param array $data The updated template data
     * @return Template The updated template
     * @throws Exception If the template couldn't be updated
     */
    public function updateTemplate($templateID, array $data)
    {
        // Validate template exists
        $template = $this->getTemplateByID($templateID);

        // Update template
        $success = $this->templateGateway->update($templateID, $data);
        if (!$success) {
            throw new Exception('Unable to update template');
        }

        return $this->getTemplateByID($templateID);
    }
}
```

### 3.2 Using Services
Here's an example of how to use the TemplateService in a route file:

```php
<?php
// templates_manage_addProcess.php

use Gibbon\Module\ReportTemplate\Domain\TemplateService;

// Get the TemplateService from the container
$templateService = $container->get(TemplateService::class);

try {
    // Get form data
    $data = [
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'active' => $_POST['active'] ?? 'Y',
        'header' => $_POST['header'] ?? '',
        'footer' => $_POST['footer'] ?? '',
        'orientation' => $_POST['orientation'] ?? 'P',
        'pageSize' => $_POST['pageSize'] ?? 'A4',
        'gibbonPersonIDCreated' => $session->get('gibbonPersonID')
    ];

    // Create template using the service
    $template = $templateService->createTemplate($data);

    // Redirect on success
    $URL .= "&return=success0&editID=".$template->getID();
    header("Location: {$URL}");
    exit();
} catch (Exception $e) {
    // Redirect on error
    $URL .= "&return=error2";
    header("Location: {$URL}");
    exit();
}
```

## 4. Best Practices

### 4.1 Data Access
1. Always use prepared statements to prevent SQL injection attacks.
2. Validate and sanitize all input data before using it in database operations.
3. Handle errors appropriately, using try-catch blocks and logging errors when necessary.
4. Use transactions for complex operations that involve multiple database changes.
5. Cache frequently accessed data to reduce database load.

### 4.2 Code Organization
1. Keep classes focused and adhere to the Single Responsibility Principle.
2. Use dependency injection to manage object dependencies and improve testability.
3. Follow PSR standards for consistent code style and structure.
4. Document your code thoroughly using PHPDoc comments.
5. Write unit tests for all your classes to ensure reliability and ease refactoring.

### 4.3 Performance
1. Optimize database queries by selecting only necessary columns and using appropriate indexes.
2. Use indexes effectively on frequently queried columns.
3. Cache query results when appropriate to reduce database load.
4. Batch operations when possible to reduce the number of database calls.
5. Monitor query performance and use tools like EXPLAIN to identify slow queries.

## Next Steps
Continue to the next section to learn about implementing the user interface and forms for your Report Template module. This will include creating views, handling form submissions, and integrating your data access layer with the user interface.
