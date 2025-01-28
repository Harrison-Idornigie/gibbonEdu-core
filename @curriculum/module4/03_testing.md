# Lesson 3: Testing

## Testing Approaches

### Manual Testing

1. **Test Plan Template**
```markdown
# Test Plan: Equipment Tracker Module

## Test Environment
- Gibbon Version: 23.0.0
- PHP Version: 7.4
- MySQL Version: 8.0
- Browser: Chrome 120

## Test Cases

### 1. Equipment Management
#### 1.1 Add Equipment
- [ ] Access equipment add page
- [ ] Fill in required fields
- [ ] Test validation
- [ ] Submit form
- [ ] Verify database entry
- [ ] Check success message

#### 1.2 Edit Equipment
- [ ] Access edit page
- [ ] Modify fields
- [ ] Save changes
- [ ] Verify updates

### 2. Loan System
#### 2.1 Create Loan
...

## Test Results
| Test Case | Status | Notes |
|-----------|--------|-------|
| 1.1       | Pass   |       |
| 1.2       | Pass   |       |
```

2. **Testing Checklist**
```markdown
## Pre-Release Testing

### Installation
- [ ] Fresh install successful
- [ ] Upgrade from previous version
- [ ] Database updates applied
- [ ] File permissions correct

### Core Features
- [ ] CRUD operations working
- [ ] Search functionality
- [ ] Filtering options
- [ ] Sorting capabilities

### User Interface
- [ ] Responsive design
- [ ] Form validation
- [ ] Error messages
- [ ] Success notifications

### Security
- [ ] Access controls
- [ ] Input validation
- [ ] SQL injection prevention
- [ ] XSS protection

### Performance
- [ ] Page load times
- [ ] Database queries optimized
- [ ] Memory usage acceptable
- [ ] No JavaScript errors
```

### Automated Testing

1. **PHPUnit Setup**
```php
// composer.json
{
    "require-dev": {
        "phpunit/phpunit": "^9.5"
    }
}

// phpunit.xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="Equipment Tracker Tests">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

2. **Unit Tests**
```php
// tests/Domain/EquipmentGatewayTest.php
namespace Gibbon\Module\EquipmentTracker\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\EquipmentTracker\Domain\EquipmentGateway;

class EquipmentGatewayTest extends TestCase
{
    protected $gateway;
    protected $pdo;
    
    protected function setUp(): void
    {
        // Set up test database connection
        $this->pdo = new \PDO(
            'mysql:host=localhost;dbname=test_db',
            'test_user',
            'test_pass'
        );
        
        // Create gateway instance
        $this->gateway = new EquipmentGateway($this->pdo);
    }
    
    public function testInsertEquipment()
    {
        // Test data
        $data = [
            'name' => 'Test Equipment',
            'category' => 'Test Category',
            'condition' => 'New'
        ];
        
        // Insert record
        $result = $this->gateway->insert($data);
        
        // Assertions
        $this->assertTrue($result);
        
        // Verify database record
        $equipment = $this->gateway->getByID($this->pdo->lastInsertId());
        $this->assertEquals($data['name'], $equipment['name']);
        $this->assertEquals($data['category'], $equipment['category']);
    }
    
    public function testUpdateEquipment()
    {
        // Insert test record
        $id = $this->insertTestEquipment();
        
        // Update data
        $data = [
            'name' => 'Updated Equipment',
            'condition' => 'Good'
        ];
        
        // Update record
        $result = $this->gateway->update($id, $data);
        
        // Assertions
        $this->assertTrue($result);
        
        // Verify updates
        $equipment = $this->gateway->getByID($id);
        $this->assertEquals($data['name'], $equipment['name']);
        $this->assertEquals($data['condition'], $equipment['condition']);
    }
    
    public function testDeleteEquipment()
    {
        // Insert test record
        $id = $this->insertTestEquipment();
        
        // Delete record
        $result = $this->gateway->delete($id);
        
        // Assertions
        $this->assertTrue($result);
        
        // Verify deletion
        $equipment = $this->gateway->getByID($id);
        $this->assertFalse($equipment);
    }
    
    protected function insertTestEquipment()
    {
        $data = [
            'name' => 'Test Equipment',
            'category' => 'Test Category',
            'condition' => 'New'
        ];
        
        $this->gateway->insert($data);
        return $this->pdo->lastInsertId();
    }
}
```

3. **Integration Tests**
```php
// tests/Integration/EquipmentLoanTest.php
namespace Gibbon\Module\EquipmentTracker\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\EquipmentTracker\Domain\EquipmentGateway;
use Gibbon\Module\EquipmentTracker\Domain\LoanGateway;
use Gibbon\Module\EquipmentTracker\Domain\Services\LoanService;

class EquipmentLoanTest extends TestCase
{
    protected $equipmentGateway;
    protected $loanGateway;
    protected $loanService;
    protected $pdo;
    
    protected function setUp(): void
    {
        // Set up test database
        $this->pdo = new \PDO(
            'mysql:host=localhost;dbname=test_db',
            'test_user',
            'test_pass'
        );
        
        // Create instances
        $this->equipmentGateway = new EquipmentGateway($this->pdo);
        $this->loanGateway = new LoanGateway($this->pdo);
        $this->loanService = new LoanService(
            $this->equipmentGateway,
            $this->loanGateway
        );
    }
    
    public function testCreateLoan()
    {
        // Create test equipment
        $equipmentID = $this->createTestEquipment();
        
        // Create loan
        $loanData = [
            'equipmentID' => $equipmentID,
            'gibbonPersonID' => '001',
            'dateOut' => date('Y-m-d'),
            'dateReturn' => date('Y-m-d', strtotime('+1 week'))
        ];
        
        $result = $this->loanService->createLoan($loanData);
        
        // Assertions
        $this->assertTrue($result['success']);
        
        // Verify equipment status
        $equipment = $this->equipmentGateway->getByID($equipmentID);
        $this->assertEquals('On Loan', $equipment['status']);
        
        // Verify loan record
        $loan = $this->loanGateway->getByID($result['loanID']);
        $this->assertEquals($equipmentID, $loan['equipmentID']);
        $this->assertEquals('001', $loan['gibbonPersonID']);
    }
    
    public function testReturnLoan()
    {
        // Create test loan
        $loanID = $this->createTestLoan();
        
        // Process return
        $result = $this->loanService->returnLoan($loanID);
        
        // Assertions
        $this->assertTrue($result['success']);
        
        // Verify equipment status
        $loan = $this->loanGateway->getByID($loanID);
        $equipment = $this->equipmentGateway->getByID($loan['equipmentID']);
        $this->assertEquals('Available', $equipment['status']);
        
        // Verify loan record
        $this->assertNotNull($loan['dateReturned']);
    }
    
    protected function createTestEquipment()
    {
        $data = [
            'name' => 'Test Equipment',
            'status' => 'Available'
        ];
        
        $this->equipmentGateway->insert($data);
        return $this->pdo->lastInsertId();
    }
    
    protected function createTestLoan()
    {
        $equipmentID = $this->createTestEquipment();
        
        $loanData = [
            'equipmentID' => $equipmentID,
            'gibbonPersonID' => '001',
            'dateOut' => date('Y-m-d'),
            'dateReturn' => date('Y-m-d', strtotime('+1 week'))
        ];
        
        $result = $this->loanService->createLoan($loanData);
        return $result['loanID'];
    }
}
```

### Test Documentation

1. **Test Documentation Template**
```markdown
# Test Documentation

## Test Environment
- Development Server: test.gibbonedu.org
- Database: test_gibbon
- PHP Version: 7.4
- Test Users:
  - Admin: admin_user
  - Teacher: teacher_user
  - Student: student_user

## Test Cases

### Equipment Management

#### TC001: Add New Equipment
**Description**: Test adding new equipment to the system

**Prerequisites**:
- Logged in as admin user
- Access to equipment management

**Steps**:
1. Navigate to Add Equipment page
2. Fill in required fields:
   - Name: "Test Laptop"
   - Category: "Electronics"
   - Serial: "TST123"
3. Click Submit

**Expected Results**:
- Equipment added successfully
- Success message displayed
- Record visible in equipment list

**Actual Results**:
- Equipment added as expected
- Success message: "Equipment added successfully"
- Record found in database and list view

**Status**: Pass

#### TC002: Edit Equipment
...
```

2. **Test Report Template**
```markdown
# Test Report: Equipment Tracker v1.0.0

## Summary
- Test Date: 2024-01-27
- Tester: John Doe
- Build Version: 1.0.0-beta
- Test Cases: 25
- Pass Rate: 96%

## Test Results

### Passed Tests (24)
1. Equipment Management
   - Add Equipment
   - Edit Equipment
   - Delete Equipment
   - Search Equipment
   
2. Loan System
   - Create Loan
   - Return Equipment
   - View Loan History

### Failed Tests (1)
1. TC015: Advanced Search
   - Issue: Filter by date range not working
   - Severity: Minor
   - Fix Status: In Progress

## Issues Found

### Critical (0)
None

### Major (0)
None

### Minor (1)
1. Date range filter in advanced search
   - Steps to reproduce
   - Expected behavior
   - Current behavior
   - Screenshot

## Recommendations
1. Fix date range filter
2. Add more validation for serial numbers
3. Improve error messages

## Sign-off
- [ ] Ready for production
- [x] Needs fixes before release
- [ ] Major issues present
```

### Common Test Scenarios

1. **CRUD Operations**
```php
class CRUDTest extends TestCase
{
    public function testCreate()
    {
        // Test creation
    }
    
    public function testRead()
    {
        // Test retrieval
    }
    
    public function testUpdate()
    {
        // Test updates
    }
    
    public function testDelete()
    {
        // Test deletion
    }
}
```

2. **Validation Tests**
```php
class ValidationTest extends TestCase
{
    public function testRequiredFields()
    {
        $data = ['name' => '']; // Missing required field
        $result = $this->validator->validate($data);
        $this->assertFalse($result);
    }
    
    public function testInvalidFormat()
    {
        $data = ['email' => 'invalid-email'];
        $result = $this->validator->validate($data);
        $this->assertFalse($result);
    }
    
    public function testMaxLength()
    {
        $data = ['name' => str_repeat('a', 101)]; // Too long
        $result = $this->validator->validate($data);
        $this->assertFalse($result);
    }
}
```

3. **Permission Tests**
```php
class PermissionTest extends TestCase
{
    public function testAdminAccess()
    {
        $this->loginAsAdmin();
        $response = $this->accessPage('equipment_manage.php');
        $this->assertTrue($response['access']);
    }
    
    public function testTeacherAccess()
    {
        $this->loginAsTeacher();
        $response = $this->accessPage('equipment_view.php');
        $this->assertTrue($response['access']);
    }
    
    public function testStudentAccess()
    {
        $this->loginAsStudent();
        $response = $this->accessPage('equipment_manage.php');
        $this->assertFalse($response['access']);
    }
}
```

## Exercise: Implement Testing

1. Create Test Structure
```plaintext
tests/
├── bootstrap.php
├── phpunit.xml
├── Unit/
│   └── Domain/
│       ├── EquipmentGatewayTest.php
│       └── LoanGatewayTest.php
└── Integration/
    └── LoanServiceTest.php
```

2. Write Basic Tests
```php
// Unit test
class YourGatewayTest extends TestCase
{
    public function testInsert()
    {
        // Test insert
    }
}

// Integration test
class YourServiceTest extends TestCase
{
    public function testProcess()
    {
        // Test process
    }
}
```

3. Run Tests
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/YourTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Common Mistakes to Avoid

1. **Insufficient Test Coverage**
```php
// Bad - only testing happy path
public function testCreateLoan()
{
    $result = $this->service->createLoan($validData);
    $this->assertTrue($result);
}

// Good - testing multiple scenarios
public function testCreateLoan()
{
    // Test valid data
    $result = $this->service->createLoan($validData);
    $this->assertTrue($result);
    
    // Test invalid data
    $result = $this->service->createLoan($invalidData);
    $this->assertFalse($result);
    
    // Test edge cases
    $result = $this->service->createLoan($edgeCaseData);
    $this->assertFalse($result);
}
```

2. **Hard-coded Test Data**
```php
// Bad
$data = [
    'id' => 1,
    'name' => 'Test'
];

// Good
$data = $this->createTestData();
```

3. **Missing Cleanup**
```php
// Bad - no cleanup
public function testCreate()
{
    $this->service->create($data);
}

// Good - with cleanup
protected function setUp(): void
{
    // Setup
}

protected function tearDown(): void
{
    // Cleanup
}
```

## Next Steps

After completing this lesson:
1. Set up testing environment
2. Write basic tests
3. Implement test documentation
4. Create testing workflow

In the next lesson, we'll learn about documenting your module!
