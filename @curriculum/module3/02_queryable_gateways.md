# Lesson 2: QueryableGateways and Domain Logic

## Domain-Driven Design

Domain-Driven Design (DDD) is an approach to software development that centers the project around the core domain and domain logic. In GibbonEdu, this means organizing your module's code around the core business concepts it handles.

### Understanding the Domain Directory

The Domain directory structure for a module:

```plaintext
YourModule/
├── Domain/
│   ├── EquipmentGateway.php
│   ├── LoanGateway.php
│   ├── CategoryGateway.php
│   └── Services/
│       ├── EquipmentService.php
│       └── LoanService.php
```

### Namespace Conventions

```php
<?php
// Example namespace structure
namespace Gibbon\Module\EquipmentTracker\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\DataSet;
```

## QueryableGateways

QueryableGateways provide a clean, safe way to interact with your database tables. They handle:
- Query building
- Data filtering
- Sorting
- Pagination

### Creating Custom Gateways

Basic Gateway Structure:

```php
<?php
namespace Gibbon\Module\EquipmentTracker\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\DataSet;

class EquipmentGateway extends QueryableGateway
{
    /**
     * @var string Table name in the database
     */
    private static $tableName = 'equipmentTrackerEquipment';
    
    /**
     * @var string Primary key column name
     */
    private static $primaryKey = 'equipmentID';
    
    /**
     * @var array Columns that can be searched
     */
    private static $searchableColumns = [
        'name',
        'serialNumber',
        'category',
        'location'
    ];
    
    /**
     * Query equipment with various filters and joins
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryEquipment(QueryCriteria $criteria) 
    {
        // Create base query
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'equipmentID',
                'name',
                'serialNumber',
                'category',
                'condition',
                'location',
                'dateAdded',
                'lastModified'
            ]);
            
        // Add loan status subquery
        $query->joinSub(
            $this->newQuery()
                ->from('equipmentTrackerLoan')
                ->cols(['equipmentID', 'status'])
                ->where('dateReturned IS NULL'),
            'currentLoan',
            'currentLoan.equipmentID=equipmentTrackerEquipment.equipmentID'
        );
        
        // Apply user-provided criteria
        $criteria->addFilterRules([
            'category' => function($query, $category) {
                return $query->where('category = :category')
                           ->bindValue('category', $category);
            },
            'condition' => function($query, $condition) {
                return $query->where('condition = :condition')
                           ->bindValue('condition', $condition);
            },
            'status' => function($query, $status) {
                return $query->where('currentLoan.status = :status')
                           ->bindValue('status', $status);
            }
        ]);
        
        return $this->runQuery($query, $criteria);
    }
    
    /**
     * Get equipment details by ID with current loan info
     *
     * @param string $equipmentID
     * @return array|false
     */
    public function getEquipmentByID($equipmentID) 
    {
        // Build query with joins
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'equipmentID',
                'name',
                'serialNumber',
                'category',
                'condition',
                'location',
                'dateAdded',
                'lastModified'
            ])
            ->leftJoin(
                'equipmentTrackerLoan',
                'currentLoan.equipmentID=equipmentTrackerEquipment.equipmentID 
                 AND dateReturned IS NULL'
            )
            ->where('equipmentID = :equipmentID')
            ->bindValue('equipmentID', $equipmentID);
            
        return $this->db()->selectOne($query);
    }
    
    /**
     * Get overdue equipment with student info
     *
     * @return array
     */
    public function selectOverdueEquipment() 
    {
        $sql = "
            SELECT 
                e.equipmentID,
                e.name as equipmentName,
                l.dateOut,
                l.dateExpected,
                p.gibbonPersonID,
                p.preferredName,
                p.surname,
                p.email,
                t.gibbonPersonID as tutorID
            FROM equipmentTrackerEquipment as e
            JOIN equipmentTrackerLoan as l ON 
                l.equipmentID = e.equipmentID
            JOIN gibbonPerson as p ON 
                p.gibbonPersonID = l.gibbonPersonID
            LEFT JOIN gibbonFormGroup as fg ON 
                fg.gibbonFormGroupID = p.gibbonFormGroupID
            LEFT JOIN gibbonPerson as t ON 
                t.gibbonPersonID = fg.gibbonPersonIDTutor
            WHERE l.dateReturned IS NULL
            AND l.dateExpected < CURRENT_DATE
            ORDER BY l.dateExpected ASC
        ";
        
        return $this->db()->select($sql);
    }
}
```

### Query Building

Example of complex query building:

```php
<?php
class LoanGateway extends QueryableGateway
{
    private static $tableName = 'equipmentTrackerLoan';
    private static $primaryKey = 'loanID';
    
    /**
     * Query loans with various filters and joins
     */
    public function queryLoans(QueryCriteria $criteria) 
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'loanID',
                'equipmentID',
                'gibbonPersonID',
                'dateOut',
                'dateExpected',
                'dateReturned',
                'status',
                'notes'
            ])
            ->leftJoin(
                'equipmentTrackerEquipment',
                'equipmentTrackerEquipment.equipmentID=equipmentTrackerLoan.equipmentID'
            )
            ->leftJoin(
                'gibbonPerson',
                'gibbonPerson.gibbonPersonID=equipmentTrackerLoan.gibbonPersonID'
            );
            
        // Add filtering rules
        $criteria->addFilterRules([
            'status' => function($query, $status) {
                return $query
                    ->where('equipmentTrackerLoan.status = :status')
                    ->bindValue('status', $status);
            },
            'dateRange' => function($query, $dateRange) {
                list($start, $end) = explode(',', $dateRange);
                return $query
                    ->where('dateOut BETWEEN :start AND :end')
                    ->bindValue('start', $start)
                    ->bindValue('end', $end);
            },
            'overdue' => function($query, $value) {
                if ($value == 'Y') {
                    return $query
                        ->where('dateReturned IS NULL')
                        ->where('dateExpected < CURRENT_DATE');
                }
                return $query;
            }
        ]);
        
        return $this->runQuery($query, $criteria);
    }
}
```

### Data Services

Services handle business logic and coordinate between gateways:

```php
<?php
namespace Gibbon\Module\EquipmentTracker\Domain\Services;

use Gibbon\Module\EquipmentTracker\Domain\EquipmentGateway;
use Gibbon\Module\EquipmentTracker\Domain\LoanGateway;

class LoanService
{
    protected $equipmentGateway;
    protected $loanGateway;
    
    public function __construct(
        EquipmentGateway $equipmentGateway,
        LoanGateway $loanGateway
    ) {
        $this->equipmentGateway = $equipmentGateway;
        $this->loanGateway = $loanGateway;
    }
    
    /**
     * Process a new equipment loan
     *
     * @param array $data Loan details
     * @return array Result with success/error info
     */
    public function processLoan(array $data)
    {
        // Start transaction
        $this->loanGateway->getConnection()->beginTransaction();
        
        try {
            // Check equipment availability
            $equipment = $this->equipmentGateway
                ->getEquipmentByID($data['equipmentID']);
                
            if (empty($equipment)) {
                throw new \Exception(__('Equipment not found'));
            }
            
            // Check current loans
            $currentLoan = $this->loanGateway->selectBy([
                'equipmentID' => $data['equipmentID'],
                'dateReturned' => null
            ])->fetch();
            
            if (!empty($currentLoan)) {
                throw new \Exception(__('Equipment is already on loan'));
            }
            
            // Create loan record
            $loanData = [
                'equipmentID' => $data['equipmentID'],
                'gibbonPersonID' => $data['gibbonPersonID'],
                'dateOut' => date('Y-m-d'),
                'dateExpected' => $data['dateExpected'],
                'status' => 'On Loan',
                'notes' => $data['notes'] ?? null
            ];
            
            $inserted = $this->loanGateway->insert($loanData);
            
            if (!$inserted) {
                throw new \Exception(__('Could not create loan record'));
            }
            
            // Update equipment status
            $updated = $this->equipmentGateway->update($data['equipmentID'], [
                'lastLoanDate' => date('Y-m-d'),
                'status' => 'On Loan'
            ]);
            
            if (!$updated) {
                throw new \Exception(__('Could not update equipment status'));
            }
            
            // Commit transaction
            $this->loanGateway->getConnection()->commit();
            
            return [
                'success' => true,
                'loanID' => $inserted
            ];
            
        } catch (\Exception $e) {
            // Rollback on error
            $this->loanGateway->getConnection()->rollBack();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

## Best Practices

### 1. Gateway Organization

```php
// Separate concerns in gateway methods
class EquipmentGateway extends QueryableGateway
{
    // Simple queries
    public function selectActive()
    {
        return $this->selectBy(['active' => 'Y']);
    }
    
    // Complex queries
    public function queryEquipmentWithFilters(QueryCriteria $criteria)
    {
        // Complex query building
    }
    
    // Specific use cases
    public function getOverdueCount()
    {
        return $this->db()->selectOne(
            "SELECT COUNT(*) FROM {$this->getTableName()} 
             WHERE dueDate < CURRENT_DATE"
        );
    }
}
```

### 2. Error Handling

```php
try {
    $result = $gateway->insert($data);
    if ($result === false) {
        // Handle database error
        $error = $gateway->getConnection()->errorInfo();
        Log::write('Database Error', json_encode($error));
        return false;
    }
} catch (PDOException $e) {
    // Handle more serious errors
    Log::write('Critical Error', $e->getMessage());
    throw new Exception('Database operation failed');
}
```

### 3. Transaction Management

```php
$connection = $gateway->getConnection();

try {
    $connection->beginTransaction();
    
    // Multiple operations
    $gateway1->insert($data1);
    $gateway2->update($data2);
    
    $connection->commit();
} catch (Exception $e) {
    $connection->rollBack();
    throw $e;
}
```

## Exercise: Create Your First Gateway

1. Basic Gateway
```php
<?php
namespace Gibbon\Module\YourModule\Domain;

use Gibbon\Domain\QueryableGateway;

class YourGateway extends QueryableGateway
{
    private static $tableName = 'yourTable';
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

2. Add Complex Queries
```php
public function queryWithRelations(QueryCriteria $criteria)
{
    $query = $this
        ->newQuery()
        ->from($this->getTableName())
        ->cols(['id', 'name'])
        ->leftJoin('otherTable', 'otherTable.id=yourTable.otherID')
        ->cols(['otherTable.name as otherName']);
        
    return $this->runQuery($query, $criteria);
}
```

## Common Mistakes to Avoid

1. **Not Using Prepared Statements**
```php
// Bad
$query = "SELECT * FROM table WHERE id = $id";

// Good
$query = $this
    ->newQuery()
    ->from('table')
    ->where('id = :id')
    ->bindValue('id', $id);
```

2. **Mixing Business Logic in Gateways**
```php
// Bad - Business logic in gateway
public function processLoan($data)
{
    // Business logic here
}

// Good - Keep gateway focused on data access
public function getLoanByID($id)
{
    // Simple data retrieval
}
```

3. **Not Handling Transactions**
```php
// Bad - No transaction
$gateway->insert($data1);
$gateway->insert($data2); // What if this fails?

// Good - Use transaction
$connection->beginTransaction();
try {
    $gateway->insert($data1);
    $gateway->insert($data2);
    $connection->commit();
} catch (Exception $e) {
    $connection->rollBack();
    throw $e;
}
```

## Next Steps

After completing this lesson:
1. Design your data model
2. Create gateway classes
3. Implement business logic in services
4. Test database operations

In the next lesson, we'll learn about module translation and internationalization!
