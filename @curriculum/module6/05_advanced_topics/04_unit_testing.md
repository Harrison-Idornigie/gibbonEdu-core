# Unit Testing

A comprehensive guide to implementing unit tests in the Report Template module. This guide covers setting up the testing framework, writing effective tests, and following best practices.

## 1. Testing Framework Setup

Setting up a robust testing framework is crucial for maintaining code quality and preventing regressions.

### 1.1 PHPUnit Configuration

Create a PHPUnit configuration file to define test suites, coverage settings, and environment variables:

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <!-- Define test suites -->
    <testsuites>
        <testsuite name="Reports">
            <directory>tests/Reports</directory>
        </testsuite>
    </testsuites>
    
    <!-- Configure code coverage -->
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">modules/Reports</directory>
        </include>
        <exclude>
            <directory>modules/Reports/tests</directory>
        </exclude>
    </coverage>
    
    <!-- Set environment variables for testing -->
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="testing"/>
    </php>
</phpunit>
```

### 1.2 Test Case Base Class

Create a base TestCase class to set up common functionality for all your tests:

```php
// tests/Reports/TestCase.php
namespace Tests\Reports;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Gibbon\Domain\Reports\TemplateGateway;
use Gibbon\Domain\Reports\ReportGenerator;

abstract class TestCase extends BaseTestCase
{
    protected $container;
    protected $db;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize the dependency injection container
        $this->container = require __DIR__ . '/../../config/container.php';
        
        // Get the database connection from the container
        $this->db = $this->container->get('db');
        
        // Begin a database transaction for each test
        // This allows us to roll back changes after each test, keeping the database clean
        $this->db->beginTransaction();
    }
    
    protected function tearDown(): void
    {
        // Roll back the transaction after each test
        // This ensures that each test starts with a clean database state
        $this->db->rollBack();
        
        parent::tearDown();
    }
    
    // Helper method to create a test template
    protected function createTemplate(array $data = [])
    {
        $gateway = $this->container->get(TemplateGateway::class);
        
        // Merge default data with any custom data provided
        return $gateway->insert(array_merge([
            'name' => 'Test Template',
            'description' => 'Test Description',
            'active' => 'Y',
            'gibbonSchoolYearID' => 1
        ], $data));
    }
}

## 2. Test Implementation

Writing comprehensive tests for your module's components ensures that they work as expected and helps catch regressions.

### 2.1 Template Tests

These tests cover the CRUD operations and validation for report templates:

```php
// tests/Reports/Domain/TemplateTest.php
namespace Tests\Reports\Domain;

use Tests\Reports\TestCase;
use Gibbon\Domain\Reports\TemplateGateway;
use Gibbon\Domain\Reports\TemplateValidator;

class TemplateTest extends TestCase
{
    private $gateway;
    private $validator;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get instances of the gateway and validator from the container
        $this->gateway = $this->container->get(TemplateGateway::class);
        $this->validator = $this->container->get(TemplateValidator::class);
    }
    
    public function testCreateTemplate()
    {
        // Arrange: Prepare the data for a new template
        $data = [
            'name' => 'Progress Report',
            'description' => 'End of term progress report',
            'active' => 'Y'
        ];
        
        // Act: Insert the new template
        $template = $this->gateway->insert($data);
        
        // Assert: Check that the template was created correctly
        $this->assertNotEmpty($template['gibbonReportTemplateID']);
        $this->assertEquals($data['name'], $template['name']);
        $this->assertEquals($data['description'], $template['description']);
        $this->assertEquals($data['active'], $template['active']);
    }
    
    public function testValidateTemplate()
    {
        // Arrange: Prepare invalid data
        $data = [
            'name' => '', // Invalid: empty name
            'active' => 'X' // Invalid: wrong value
        ];
        
        // Act: Validate the template data
        $errors = $this->validator->validateTemplate($data);
        
        // Assert: Check that the correct validation errors were returned
        $this->assertCount(2, $errors);
        $this->assertContains('Name is required', $errors);
        $this->assertContains('Active must be Y or N', $errors);
    }
    
    public function testUpdateTemplate()
    {
        // Arrange: Create a template and prepare update data
        $template = $this->createTemplate();
        $updates = ['name' => 'Updated Name'];
        
        // Act: Update the template
        $updated = $this->gateway->update($template['gibbonReportTemplateID'], $updates);
        
        // Assert: Check that the update was successful and the name was changed
        $this->assertTrue($updated);
        $this->assertEquals(
            $updates['name'],
            $this->gateway->getByID($template['gibbonReportTemplateID'])['name']
        );
    }
    
    public function testDeleteTemplate()
    {
        // Arrange: Create a template to delete
        $template = $this->createTemplate();
        
        // Act: Delete the template
        $deleted = $this->gateway->delete($template['gibbonReportTemplateID']);
        
        // Assert: Check that the delete was successful and the template no longer exists
        $this->assertTrue($deleted);
        $this->assertNull($this->gateway->getByID($template['gibbonReportTemplateID']));
    }
}

### 2.2 Report Generation Tests

These tests ensure that reports are generated correctly under various conditions:

```php
// tests/Reports/Domain/ReportGeneratorTest.php
namespace Tests\Reports\Domain;

use Tests\Reports\TestCase;
use Gibbon\Domain\Reports\ReportGenerator;
use Gibbon\Domain\Reports\TemplateRenderer;

class ReportGeneratorTest extends TestCase
{
    private $generator;
    private $renderer;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get instances of the report generator and template renderer
        $this->generator = $this->container->get(ReportGenerator::class);
        $this->renderer = $this->container->get(TemplateRenderer::class);
    }
    
    public function testGenerateReport()
    {
        // Arrange: Create a template and set up a student ID
        $template = $this->createTemplate();
        $studentID = 1;
        
        // Act: Generate the report
        $report = $this->generator->generateReport($template['gibbonReportTemplateID'], $studentID);
        
        // Assert: Check that the report was generated successfully
        $this->assertNotEmpty($report['path']);
        $this->assertFileExists($report['path']);
        $this->assertEquals('application/pdf', mime_content_type($report['path']));
    }
    
    public function testGenerateReportWithCustomData()
    {
        // Arrange: Create a template, set up a student ID, and prepare custom data
        $template = $this->createTemplate();
        $studentID = 1;
        $customData = [
            'term' => 'Term 1',
            'year' => '2025',
            'comments' => 'Excellent progress'
        ];
        
        // Act: Generate the report with custom data
        $report = $this->generator->generateReport(
            $template['gibbonReportTemplateID'],
            $studentID,
            $customData
        );
        
        // Assert: Check that the report was generated and contains the custom data
        $this->assertNotEmpty($report['path']);
        $this->assertStringContainsString($customData['term'], file_get_contents($report['path']));
        $this->assertStringContainsString($customData['comments'], file_get_contents($report['path']));
    }
    
    public function testGenerateReportWithInvalidTemplate()
    {
        // Arrange: Set up an invalid template ID and a student ID
        $invalidTemplateID = 999;
        $studentID = 1;
        
        // Assert: Expect an exception to be thrown
        $this->expectException(\InvalidArgumentException::class);
        
        // Act: Attempt to generate a report with an invalid template ID
        $this->generator->generateReport($invalidTemplateID, $studentID);
    }
}

### 2.3 Permission Tests

These tests ensure that user permissions are correctly enforced:

```php
// tests/Reports/Domain/PermissionTest.php
namespace Tests\Reports\Domain;

use Tests\Reports\TestCase;
use Gibbon\Domain\Reports\PermissionManager;

class PermissionTest extends TestCase
{
    private $permissionManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get an instance of the permission manager
        $this->permissionManager = $this->container->get(PermissionManager::class);
    }
    
    public function testUserCanViewTemplate()
    {
        // Arrange: Create a template and set up a user ID
        $template = $this->createTemplate();
        $userID = 1;
        
        // Act: Check if the user can view the template
        $canView = $this->permissionManager->canViewTemplate($template['gibbonReportTemplateID'], $userID);
        
        // Assert: The user should be able to view the template
        $this->assertTrue($canView);
    }
    
    public function testUserCanEditTemplate()
    {
        // Arrange: Create a template and set up a user ID
        $template = $this->createTemplate();
        $userID = 1;
        
        // Act: Check if the user can edit the template
        $canEdit = $this->permissionManager->canEditTemplate($template['gibbonReportTemplateID'], $userID);
        
        // Assert: The user should be able to edit the template
        $this->assertTrue($canEdit);
    }
    
    public function testUserCannotEditTemplateWithoutPermission()
    {
        // Arrange: Create a template and set up a user ID without permission
        $template = $this->createTemplate();
        $userID = 2; // User without permission
        
        // Act: Check if the user can edit the template
        $canEdit = $this->permissionManager->canEditTemplate($template['gibbonReportTemplateID'], $userID);
        
        // Assert: The user should not be able to edit the template
        $this->assertFalse($canEdit);
    }
}

## 3. Running Tests

Regularly running your tests helps catch issues early in the development process.

### 3.1 Command Line

Run all tests:
```bash
./vendor/bin/phpunit
```

Run specific test suite:
```bash
./vendor/bin/phpunit --testsuite Reports
```

Run specific test file:
```bash
./vendor/bin/phpunit tests/Reports/Domain/TemplateTest.php
```

### 3.2 Continuous Integration

Add this configuration to your CI pipeline to automatically run tests on every push and pull request:

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          extensions: mbstring, xml, ctype, iconv, intl, pdo_sqlite, mysql
          coverage: xdebug
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Run test suite
        run: ./vendor/bin/phpunit --coverage-clover=coverage.xml
        
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v1
```

## 4. Best Practices

Follow these best practices to ensure your tests are effective, maintainable, and efficient:

1. **Test Organization**
   - Group related tests in the same file or directory
   - Use meaningful names for test methods (e.g., `testCreateTemplateWithValidData`)
   - Follow naming conventions consistently (e.g., `test` prefix for PHPUnit)
   - Maintain a clear test hierarchy (suite > case > scenario)
   - Keep tests focused on a single piece of functionality

2. **Test Data**
   - Use data providers for testing multiple scenarios
   - Create test factories to generate complex test objects
   - Clean up test data after each test to avoid interdependencies
   - Use realistic sample data that mimics production scenarios
   - Avoid hard coding test data; use constants or configuration files

3. **Assertions**
   - Be specific with assertions (e.g., `assertEquals` instead of `assertTrue`)
   - Test edge cases and boundary conditions
   - Check for expected error conditions and exceptions
   - Verify side effects of operations (e.g., database changes)
   - Create custom assertions for complex validations

4. **Performance**
   - Use database transactions to speed up tests involving database operations
   - Mock external services to avoid network calls and improve test speed
   - Cache test data or expensive computations when possible
   - Optimize setup and teardown methods to run quickly
   - Consider running tests in parallel to reduce overall execution time

5. **Maintenance**
   - Regularly update tests as the codebase evolves
   - Remove or update obsolete tests promptly
   - Keep individual tests simple and focused
   - Document any non-obvious test scenarios or setups
   - Regularly review and improve test coverage

By following these practices, you'll create a robust test suite that helps maintain code quality and catch issues early in the development process.
