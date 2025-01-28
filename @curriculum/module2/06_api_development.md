# Module Integration in GibbonEdu

## Current Integration Methods

GibbonEdu currently provides several ways to integrate your module with the core system and other modules. While a REST API is planned for future releases, here are the current recommended integration methods:

### 1. Module Hooks

Hooks allow your module to respond to system events and integrate with core functionality:

```php
<?php
// modules/YourModule/hooks.php

$hooks = array(
    'studentEnrolment' => array(
        'name' => 'Student Enrolment',
        'description' => 'Processes new student enrolment',
        'type' => 'Student',
        'function' => 'studentEnrolment'
    )
);

function studentEnrolment($args) {
    // Hook implementation
    $gibbonPersonID = $args['gibbonPersonID'];
    
    // Your module's logic here
}
```

### 2. Database Access via QueryableGateway

Use the QueryableGateway pattern for database operations:

```php
<?php
namespace Gibbon\Module\YourModule\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\QueryCriteria;

class YourGateway extends QueryableGateway
{
    private static $tableName = 'moduleYourTable';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['name'];
    
    public function queryRecords(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['id', 'name', 'description']);
            
        return $this->runQuery($query, $criteria);
    }
}
```

### 3. Actions and Pages

Create standardized module pages and actions:

```php
<?php
// modules/YourModule/manifest.php

$actionRows[] = [
    'name'                      => 'Manage Records',
    'precedence'                => '0',
    'category'                  => 'Records',
    'description'               => 'Manage module records',
    'URLList'                   => 'records_manage.php',
    'entryURL'                 => 'records_manage.php',
    'entrySidebar'             => 'Y',
    'menuShow'                 => 'Y',
    'defaultPermissionLevel'    => '3'
];
```

## Best Practices for Module Integration

1. **Use Core Patterns**
   - Follow the QueryableGateway pattern for database access
   - Implement hooks for system event integration
   - Use standard action and page structures

2. **Data Consistency**
   - Use transactions for related operations
   - Implement proper error handling
   - Follow core data validation patterns

3. **Security**
   - Always validate input data
   - Use prepared statements
   - Check permissions using core functions
   - Follow core security patterns

4. **Code Organization**
   ```php
   <?php
   // Domain logic in classes
   namespace Gibbon\Module\YourModule\Domain;
   
   class YourService
   {
       private $gateway;
       
       public function __construct(YourGateway $gateway)
       {
           $this->gateway = $gateway;
       }
       
       public function processRecord($data)
       {
           // Validation and processing
           return $this->gateway->insert($data);
       }
   }
   ```

## Future API Development

GibbonEdu plans to implement a REST API in future releases. The roadmap includes:

1. **Authentication**
   - OAuth 2.0 support
   - API key authentication
   - Role-based access control

2. **Core Endpoints**
   - User management
   - Course and class management
   - Attendance tracking
   - Grade recording

3. **Integration Features**
   - Webhook support
   - Real-time events
   - Batch operations

## Preparing for Future API Integration

While waiting for the official API, follow these practices:

1. **Use Service Layer Pattern**
   ```php
   <?php
   namespace Gibbon\Module\YourModule\Domain;
   
   class RecordService
   {
       private $gateway;
       
       public function __construct(RecordGateway $gateway)
       {
           $this->gateway = $gateway;
       }
       
       // Methods that could map to future API endpoints
       public function getRecord($id)
       {
           return $this->gateway->getByID($id);
       }
       
       public function createRecord($data)
       {
           // Validation and processing that could be exposed via API
           return $this->gateway->insert($data);
       }
   }
   ```

2. **Structure Data Models**
   ```php
   <?php
   namespace Gibbon\Module\YourModule\Domain;
   
   class Record
   {
       private $id;
       private $name;
       private $data;
       
       // Methods that will map well to API responses
       public function toArray()
       {
           return [
               'id' => $this->id,
               'name' => $this->name,
               'data' => $this->data
           ];
       }
   }
   ```

## Exercise: Create an Integrated Module

Build a module that demonstrates proper integration:

1. Create hooks for system events
2. Implement QueryableGateway for data access
3. Add proper validation and error handling
4. Structure code to be API-ready
5. Follow security best practices
