# PHP Conventions in GibbonEdu

This comprehensive guide covers the PHP coding standards and best practices used in GibbonEdu module development, with practical examples from our Report Template module. Following these conventions ensures consistency across the codebase and improves maintainability.

## 1. Coding Standards

### 1.1 PSR Standards
GibbonEdu adheres to PSR (PHP Standards Recommendations) for code consistency. This includes PSR-1 (Basic Coding Standard), PSR-2 (Coding Style Guide), and PSR-4 (Autoloading Standard).

Example of a class following PSR standards:

```php
<?php
declare(strict_types=1);

namespace Gibbon\Module\ReportTemplate\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;

/**
 * Template Gateway
 *
 * This class handles database operations for report templates.
 *
 * @version v23
 * @since   v23
 */
class TemplateGateway extends QueryableGateway
{
    use TableAware;

    // Define table properties
    private static $tableName = 'reportTemplateTemplate';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['name', 'description'];

    /**
     * Gets active templates based on the provided criteria
     *
     * @param QueryCriteria $criteria The query criteria for filtering and sorting
     * @return DataSet The resulting dataset of templates
     */
    public function queryTemplates(QueryCriteria $criteria)
    {
        // Build the query using the QueryableGateway methods
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'id',
                'name',
                'description',
                'active'
            ]);

        // Execute the query with the provided criteria
        return $this->runQuery($query, $criteria);
    }
}
```

### 1.2 Code Formatting
Consistent code formatting improves readability and maintainability:

- Use 4 spaces for indentation (not tabs)
- Keep line length under 120 characters for better readability
- Add a blank line before return statements to improve visual separation
- Use type hints and return type declarations for better code clarity and error prevention
- Enable strict typing where possible to catch type-related errors early

## 2. PHP Features

### 2.1 Required PHP Version
GibbonEdu v23+ requires PHP 7.4 or higher, which enables the use of modern PHP features such as:

- Type declarations for properties, parameters, and return values
- Arrow functions for concise anonymous functions
- Null coalescing operator (??) for simplified null checks
- Property type declarations for class properties

### 2.2 Error Handling
Use structured error handling to manage exceptions and provide meaningful error messages:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

use Exception;
use PDOException;
use Gibbon\Domain\QueryableGateway;

class ReportService
{
    private $templateGateway;
    private $reportGateway;

    public function generateReport(int $templateId, array $data): string
    {
        try {
            // Validate template exists
            $template = $this->templateGateway->getByID($templateId);
            if (empty($template)) {
                throw new Exception('Template not found');
            }

            // Generate report
            $report = $this->processTemplate($template, $data);
            
            // Save report
            $this->reportGateway->insert([
                'templateID' => $templateId,
                'content' => $report,
                'generatedAt' => date('Y-m-d H:i:s')
            ]);

            return $report;
        } catch (PDOException $e) {
            // Log database errors for debugging
            error_log('Database error in generateReport: ' . $e->getMessage());
            throw new Exception('Database error while generating report');
        } catch (Exception $e) {
            // Re-throw application errors to be handled by the caller
            throw $e;
        }
    }
}
```

## 3. Common Patterns

### 3.1 Dependency Injection
Use constructor injection for dependencies to improve testability and flexibility:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;

class ReportService
{
    private $settingGateway;
    private $formatter;
    private $templateGateway;

    /**
     * Constructor using dependency injection
     *
     * @param SettingGateway $settingGateway For accessing system settings
     * @param Format $formatter For data formatting
     * @param TemplateGateway $templateGateway For template operations
     */
    public function __construct(
        SettingGateway $settingGateway,
        Format $formatter,
        TemplateGateway $templateGateway
    ) {
        $this->settingGateway = $settingGateway;
        $this->formatter = $formatter;
        $this->templateGateway = $templateGateway;
    }

    // Service methods go here
}
```

### 3.2 Gateway Pattern
Use Gateways for database operations to encapsulate data access logic:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

use Gibbon\Domain\QueryableGateway;

class ReportGateway extends QueryableGateway
{
    private static $tableName = 'reportTemplateReport';
    private static $primaryKey = 'id';

    /**
     * Insert a new report
     *
     * @param array $data The report data to insert
     * @return int|false The inserted ID on success, false on failure
     */
    public function insert(array $data)
    {
        // Add creation timestamp
        $data['timestampCreated'] = date('Y-m-d H:i:s');
        
        return $this->insertAndUpdate($data);
    }

    /**
     * Update an existing report
     *
     * @param int $id The ID of the report to update
     * @param array $data The updated report data
     * @return bool True on success, false on failure
     */
    public function update(int $id, array $data): bool
    {
        // Add modification timestamp
        $data['timestampModified'] = date('Y-m-d H:i:s');
        
        return $this->updateWhere(['id' => $id], $data);
    }
}
```

## 4. Security Practices

### 4.1 Input Validation
Always validate and sanitize input to prevent security vulnerabilities:

```php
<?php
// reports_generateProcess.php

// Validate required inputs
if (empty($_POST['template']) || !is_numeric($_POST['template'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit();
}

// Sanitize inputs
$templateID = intval($_POST['template']);
$data = $_POST['data'] ?? [];

// Validate template access
$template = $templateGateway->getByID($templateID);
if (empty($template)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit();
}

// Further input validation and processing...
```

### 4.2 Output Escaping
Always escape output to prevent XSS (Cross-Site Scripting) attacks:

```php
<?php
// In templates
echo __('Template Name').': '.Format::escape($template['name']);

// In HTML attributes
echo '<input type="hidden" name="templateID" value="'.Format::escape($template['id']).'">';

// In JavaScript
echo "<script>";
echo "var templateData = ".json_encode($template, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG).";";
echo "</script>";
```

### 4.3 SQL Injection Prevention
Use prepared statements via QueryableGateway to prevent SQL injection:

```php
<?php
class TemplateGateway extends QueryableGateway
{
    /**
     * Get a template by its name
     *
     * @param string $name The name of the template
     * @return array|false The template data or false if not found
     */
    public function getByName(string $name)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->where('name = :name')
            ->bindValue('name', $name);

        return $this->runSelect($query)->fetch();
    }
}
```

## Next Steps
Continue to the next section to learn about working with module functions and the moduleFunctions.php file. This file contains shared functions used across your module and is an important part of module development in GibbonEdu.
