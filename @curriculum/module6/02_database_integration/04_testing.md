# Database Testing

This guide provides a comprehensive explanation of how to test your database code in the Report Template module, including detailed examples and best practices.

## 1. Unit Testing

Unit tests focus on testing individual components in isolation. For database operations, this often means testing gateway classes and service classes.

### 1.1 Gateway Tests
Gateway tests ensure that your data access layer is functioning correctly. Here's an example of how to create tests for your gateway classes:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;

class TemplateGatewayTest extends TestCase
{
    protected $pdo;
    protected $gateway;

    protected function setUp(): void
    {
        // Setup a test database connection
        // Note: It's crucial to use a separate test database to avoid affecting production data
        $this->pdo = new \PDO('mysql:host=localhost;dbname=gibbon_test', 'user', 'pass');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Create an instance of the gateway we're testing
        $this->gateway = new TemplateGateway($this->pdo);
        
        // Setup test data before each test
        $this->setupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test to ensure a clean slate
        $this->cleanupTestData();
    }

    protected function setupTestData()
    {
        // Insert a test template into the database
        $sql = "INSERT INTO reportTemplateTemplate 
                (name, description, active) VALUES 
                ('Test Template', 'Test Description', 'Y')";
        $this->pdo->exec($sql);
    }

    protected function cleanupTestData()
    {
        // Remove the test template from the database
        $sql = "DELETE FROM reportTemplateTemplate 
                WHERE name = 'Test Template'";
        $this->pdo->exec($sql);
    }

    public function testGetByID()
    {
        // Get the ID of our test template
        $sql = "SELECT id FROM reportTemplateTemplate 
                WHERE name = 'Test Template'";
        $id = $this->pdo->query($sql)->fetchColumn();

        // Test the getByID method of our gateway
        $template = $this->gateway->getByID($id);

        // Assert that the returned data matches what we expect
        $this->assertNotNull($template);
        $this->assertEquals('Test Template', $template['name']);
        $this->assertEquals('Test Description', $template['description']);
        $this->assertEquals('Y', $template['active']);
    }

    public function testInsert()
    {
        // Prepare test data for a new template
        $data = [
            'name' => 'New Template',
            'description' => 'New Description',
            'active' => 'Y'
        ];

        // Test the insert method
        $id = $this->gateway->insert($data);
        $this->assertNotFalse($id);

        // Verify that the template was inserted correctly
        $template = $this->gateway->getByID($id);
        $this->assertEquals($data['name'], $template['name']);
        $this->assertEquals($data['description'], $template['description']);
        $this->assertEquals($data['active'], $template['active']);

        // Clean up by deleting the newly inserted template
        $this->gateway->delete($id);
    }

    public function testUpdate()
    {
        // Get the ID of our test template
        $sql = "SELECT id FROM reportTemplateTemplate 
                WHERE name = 'Test Template'";
        $id = $this->pdo->query($sql)->fetchColumn();

        // Prepare update data
        $data = [
            'name' => 'Updated Template',
            'description' => 'Updated Description'
        ];

        // Test the update method
        $success = $this->gateway->update($id, $data);
        $this->assertTrue($success);

        // Verify that the template was updated correctly
        $template = $this->gateway->getByID($id);
        $this->assertEquals($data['name'], $template['name']);
        $this->assertEquals($data['description'], $template['description']);
    }
}
```

### 1.2 Service Tests
Service tests focus on the business logic layer. They often involve mocking dependencies to isolate the service being tested. Here's an example:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\ReportTemplate\Domain\TemplateService;
use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;
use Gibbon\Module\ReportTemplate\Domain\TemplateFactory;
use Gibbon\Domain\System\SettingGateway;

class TemplateServiceTest extends TestCase
{
    protected $service;
    protected $templateGateway;
    protected $templateFactory;
    protected $settingGateway;

    protected function setUp(): void
    {
        // Create mock objects for all dependencies
        $this->templateGateway = $this->createMock(TemplateGateway::class);
        $this->templateFactory = $this->createMock(TemplateFactory::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create an instance of the service we're testing, injecting mock dependencies
        $this->service = new TemplateService(
            $this->templateGateway,
            $this->templateFactory,
            $this->settingGateway
        );
    }

    public function testCreateTemplate()
    {
        // Prepare test data
        $data = [
            'name' => 'Test Template',
            'description' => 'Test Description',
            'active' => 'Y'
        ];

        // Set up expectations for mock objects
        $this->templateGateway
            ->expects($this->once())
            ->method('insert')
            ->with($data)
            ->willReturn(1);

        $this->templateGateway
            ->expects($this->once())
            ->method('getByID')
            ->with(1)
            ->willReturn($data);

        $this->templateFactory
            ->expects($this->once())
            ->method('createFromData')
            ->with($data)
            ->willReturn(new Template($data));

        // Test the createTemplate method
        $template = $this->service->createTemplate($data);

        // Assert that the returned object is correct
        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals($data['name'], $template->getName());
        $this->assertEquals($data['description'], $template->getDescription());
        $this->assertEquals($data['active'], $template->getActive());
    }

    public function testCreateTemplateWithInvalidData()
    {
        // Test that an exception is thrown when invalid data is provided
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Name is required');

        $this->service->createTemplate(['description' => 'Test']);
    }
}
```

## 2. Integration Testing

Integration tests verify that different parts of your system work together correctly. For database operations, this often involves testing with a real database.

### 2.1 Database Tests
These tests interact with a real test database to ensure your code works correctly with actual database operations:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\ReportTemplate\Domain\TemplateGateway;
use Gibbon\Module\ReportTemplate\Domain\TemplateService;

class DatabaseIntegrationTest extends TestCase
{
    protected static $pdo;
    protected $gateway;
    protected $service;

    public static function setUpBeforeClass(): void
    {
        // Setup test database connection
        self::$pdo = new \PDO('mysql:host=localhost;dbname=gibbon_test', 'user', 'pass');
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Run database migrations to ensure the correct schema
        $sql = file_get_contents(__DIR__.'/../db/install.sql');
        self::$pdo->exec($sql);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up database after all tests have run
        $sql = file_get_contents(__DIR__.'/../db/uninstall.sql');
        self::$pdo->exec($sql);
    }

    protected function setUp(): void
    {
        // Create real instances of gateway and service for each test
        $this->gateway = new TemplateGateway(self::$pdo);
        $this->service = new TemplateService(
            $this->gateway,
            new TemplateFactory(),
            new SettingGateway(self::$pdo)
        );
    }

    public function testFullTemplateWorkflow()
    {
        // Test creating a template
        $data = [
            'name' => 'Integration Test Template',
            'description' => 'Test Description',
            'active' => 'Y'
        ];

        $template = $this->service->createTemplate($data);
        $this->assertNotNull($template);

        // Test updating the template
        $updateData = [
            'name' => 'Updated Template',
            'description' => 'Updated Description'
        ];

        $updatedTemplate = $this->service->updateTemplate(
            $template->getID(), 
            $updateData
        );
        $this->assertEquals($updateData['name'], $updatedTemplate->getName());

        // Test deleting the template
        $success = $this->service->deleteTemplate($template->getID());
        $this->assertTrue($success);

        // Verify that the template was actually deleted
        $this->expectException(\Exception::class);
        $this->service->getTemplateByID($template->getID());
    }
}
```

### 2.2 API Tests
API tests ensure that your module's API endpoints are functioning correctly:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class APIIntegrationTest extends TestCase
{
    protected $client;
    protected $baseUrl;

    protected function setUp(): void
    {
        // Setup HTTP client for making requests to your API
        $this->baseUrl = 'http://localhost/gibbon';
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => true  // Enable cookies to maintain session
        ]);

        // Login to get a valid session
        $this->login();
    }

    protected function login()
    {
        // Perform login request
        $response = $this->client->post('/login.php', [
            'form_params' => [
                'username' => 'admin',
                'password' => 'password'
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTemplateAPI()
    {
        // Test creating a template via API
        $response = $this->client->post('/modules/ReportTemplate/templates_manage_addProcess.php', [
            'form_params' => [
                'name' => 'API Test Template',
                'description' => 'API Test Description',
                'active' => 'Y'
            ]
        ]);

        $this->assertEquals(302, $response->getStatusCode());  // Expect a redirect after successful creation
        $location = $response->getHeader('Location')[0];
        preg_match('/editID=(\d+)/', $location, $matches);
        $templateID = $matches[1];

        // Test getting the template via API
        $response = $this->client->get("/modules/ReportTemplate/templates_manage_edit.php?id={$templateID}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('API Test Template', (string)$response->getBody());

        // Test updating the template via API
        $response = $this->client->post("/modules/ReportTemplate/templates_manage_editProcess.php", [
            'form_params' => [
                'id' => $templateID,
                'name' => 'Updated API Template',
                'description' => 'Updated API Description'
            ]
        ]);

        $this->assertEquals(302, $response->getStatusCode());  // Expect a redirect after successful update

        // Test deleting the template via API
        $response = $this->client->post("/modules/ReportTemplate/templates_manage_deleteProcess.php", [
            'form_params' => [
                'id' => $templateID
            ]
        ]);

        $this->assertEquals(302, $response->getStatusCode());  // Expect a redirect after successful deletion
    }
}
```

## 3. Test Configuration

Proper configuration is crucial for running your tests efficiently and consistently.

### 3.1 PHPUnit Configuration
Create a `phpunit.xml` file in your module's root directory to configure PHPUnit:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="DB_HOST" value="localhost"/>
        <env name="DB_DATABASE" value="gibbon_test"/>
        <env name="DB_USERNAME" value="user"/>
        <env name="DB_PASSWORD" value="pass"/>
    </php>
</phpunit>
```

### 3.2 Test Bootstrap
Create a `tests/bootstrap.php` file to set up your test environment:

```php
<?php
// Load Composer autoloader
require_once __DIR__.'/../../../vendor/autoload.php';

// Set up test environment
putenv('APP_ENV=testing');

// Create test database if it doesn't exist
$pdo = new PDO(
    'mysql:host='.getenv('DB_HOST'),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD')
);

$pdo->exec('CREATE DATABASE IF NOT EXISTS '.getenv('DB_DATABASE'));
```

## 4. Best Practices

Following these best practices will help ensure your tests are effective, maintainable, and efficient.

### 4.1 Testing Strategy
1. Write tests before code (Test-Driven Development)
2. Test both success and failure cases
3. Use meaningful test names that describe the scenario being tested
4. Keep tests focused and isolated
5. Use data providers for testing multiple scenarios

### 4.2 Test Data
1. Use factories or fixtures to generate consistent test data
2. Clean up after tests to avoid interdependencies
3. Don't rely on test execution order
4. Use database transactions when possible to speed up tests
5. Avoid external dependencies in unit tests

### 4.3 Performance
1. Use database transactions to speed up tests involving database operations
2. Mock external services to avoid network calls
3. Use test doubles (mocks, stubs, etc.) appropriately
4. Cache test dependencies when possible
5. Run tests in parallel when possible to reduce overall execution time

## Next Steps
Now that you've learned about testing your database code, continue to the next section to learn about implementing the user interface and forms for your Report Template module. This will involve creating views, handling user input, and integrating your tested database operations into a functional user interface.
