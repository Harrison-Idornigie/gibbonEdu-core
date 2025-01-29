# Database Schema Design

This comprehensive guide covers the database schema design for the Report Template module, explaining how to structure your tables and relationships for efficient data management and retrieval.

## 1. Core Tables

### 1.1 Template Table
The main table for storing report templates, serving as the foundation for all report generation:

```sql
CREATE TABLE reportTemplateTemplate (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    name VARCHAR(90) NOT NULL,
    description TEXT,
    active ENUM('Y','N') DEFAULT 'Y',
    header TEXT,
    footer TEXT,
    orientation ENUM('P','L') DEFAULT 'P',  -- P: Portrait, L: Landscape
    pageSize VARCHAR(10) DEFAULT 'A4',
    marginTop DECIMAL(4,1) DEFAULT 15.0,
    marginRight DECIMAL(4,1) DEFAULT 15.0,
    marginBottom DECIMAL(4,1) DEFAULT 15.0,
    marginLeft DECIMAL(4,1) DEFAULT 15.0,
    timestampCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    timestampModified TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    gibbonPersonIDCreated INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonPersonIDModified INT(10) UNSIGNED ZEROFILL NULL,
    PRIMARY KEY (id),
    INDEX idx_active (active),  -- Index for quick filtering of active templates
    INDEX idx_creator (gibbonPersonIDCreated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 Report Table
Stores generated reports based on templates, linking each report to its source template:

```sql
CREATE TABLE reportTemplateReport (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    name VARCHAR(90) NOT NULL,
    description TEXT,
    status ENUM('Draft','Published') DEFAULT 'Draft',
    type ENUM('Single','Batch') DEFAULT 'Single',
    gibbonSchoolYearID INT(3) UNSIGNED ZEROFILL NOT NULL,
    gibbonYearGroupIDList VARCHAR(255),  -- Comma-separated list of year group IDs
    accessDate DATE NULL,  -- Date when the report becomes accessible
    timestampCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    timestampModified TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    gibbonPersonIDCreated INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonPersonIDModified INT(10) UNSIGNED ZEROFILL NULL,
    PRIMARY KEY (id),
    INDEX idx_template (templateID),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_schoolYear (gibbonSchoolYearID),
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (gibbonSchoolYearID) REFERENCES gibbonSchoolYear(gibbonSchoolYearID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 Archive Table
Stores archived reports for historical reference, ensuring data retention and auditability:

```sql
CREATE TABLE reportTemplateArchive (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    reportID INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonPersonID INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonFormGroupID INT(5) UNSIGNED ZEROFILL NULL,
    type ENUM('Draft','Final') DEFAULT 'Draft',
    status ENUM('Pending','Complete') DEFAULT 'Pending',
    reportIdentifier VARCHAR(100) NULL,  -- Unique identifier for the archived report
    timestampCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    content MEDIUMTEXT,  -- Stores the full content of the archived report
    PRIMARY KEY (id),
    INDEX idx_report (reportID),
    INDEX idx_person (gibbonPersonID),
    INDEX idx_formGroup (gibbonFormGroupID),
    INDEX idx_type_status (type, status),
    FOREIGN KEY (reportID) REFERENCES reportTemplateReport(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (gibbonPersonID) REFERENCES gibbonPerson(gibbonPersonID)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (gibbonFormGroupID) REFERENCES gibbonFormGroup(gibbonFormGroupID)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. Supporting Tables

### 2.1 Template Sections
Stores different sections within a template, allowing for modular report construction:

```sql
CREATE TABLE reportTemplateSection (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    name VARCHAR(90) NOT NULL,
    description TEXT,
    type VARCHAR(50) NOT NULL,  -- e.g., 'Header', 'Body', 'Footer', 'Custom'
    content TEXT,
    styleSheet TEXT,  -- CSS for section-specific styling
    sequenceNumber INT(3) NOT NULL,  -- Determines the order of sections
    PRIMARY KEY (id),
    INDEX idx_template (templateID),
    INDEX idx_sequence (sequenceNumber),
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Template Settings
Stores template-specific settings, allowing for customization of individual templates:

```sql
CREATE TABLE reportTemplateSetting (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    name VARCHAR(50) NOT NULL,
    value TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY templateSetting (templateID, name),  -- Ensures unique settings per template
    INDEX idx_template (templateID),
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 3. Access Control Tables

### 3.1 Template Access
Controls who can access and edit templates, implementing fine-grained permissions:

```sql
CREATE TABLE reportTemplateAccess (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonRoleID INT(3) UNSIGNED ZEROFILL NOT NULL,
    permission ENUM('View','Edit','Manage') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY templateRole (templateID, gibbonRoleID),  -- Ensures one permission level per role per template
    INDEX idx_template (templateID),
    INDEX idx_role (gibbonRoleID),
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (gibbonRoleID) REFERENCES gibbonRole(gibbonRoleID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Report Access
Controls who can access generated reports, allowing for role-based access control:

```sql
CREATE TABLE reportTemplateReportAccess (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    reportID INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonRoleID INT(3) UNSIGNED ZEROFILL NOT NULL,
    permission ENUM('View','Edit','Manage') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY reportRole (reportID, gibbonRoleID),  -- Ensures one permission level per role per report
    INDEX idx_report (reportID),
    INDEX idx_role (gibbonRoleID),
    FOREIGN KEY (reportID) REFERENCES reportTemplateReport(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (gibbonRoleID) REFERENCES gibbonRole(gibbonRoleID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 4. Implementation Steps

### 4.1 Create Database Tables
Follow these steps to set up your database schema:

1. Create a new file `db/install.sql` in your module directory.
2. Add the table creation SQL statements as shown above.
3. Test the SQL statements in a development environment to ensure they execute without errors.
4. Create a `db/uninstall.sql` file with table removal statements for clean uninstallation.

Example `install.sql`:
```sql
-- Create tables (include all CREATE TABLE statements from above)

-- Add any required indexes (if not already included in CREATE TABLE statements)

-- Add initial data if needed
INSERT INTO gibbonAction SET 
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Report Template'),
    name='Manage Templates',
    precedence=0,
    category='',
    description='Create and manage report templates',
    URLList='templates_manage.php,templates_manage_add.php,templates_manage_edit.php,templates_manage_delete.php',
    entryURL='templates_manage.php',
    entrySidebar='Y',
    menuShow='Y',
    defaultPermissionLevel='Preferences';
```

Example `uninstall.sql`:
```sql
-- Remove tables in reverse order of dependencies
DROP TABLE IF EXISTS reportTemplateReportAccess;
DROP TABLE IF EXISTS reportTemplateAccess;
DROP TABLE IF EXISTS reportTemplateSetting;
DROP TABLE IF EXISTS reportTemplateSection;
DROP TABLE IF EXISTS reportTemplateArchive;
DROP TABLE IF EXISTS reportTemplateReport;
DROP TABLE IF EXISTS reportTemplateTemplate;

-- Remove actions
DELETE FROM gibbonAction WHERE gibbonModuleID=(
    SELECT gibbonModuleID FROM gibbonModule WHERE name='Report Template'
);
```

### 4.2 Version Control
Implement proper version control for your database schema:

1. Add version numbers to your module's `manifest.php`:
```php
$version = '1.0.00';
$author = 'Your Name';
$url = 'http://your.website.com';
```

2. Create version-specific update files in `db/update.php`:
```php
// v1.0.01
$sql = [];
$sql[] = "ALTER TABLE reportTemplateTemplate ADD COLUMN customField VARCHAR(100) NULL AFTER description";

// v1.0.02
$sql[] = "CREATE TABLE reportTemplateNewFeature (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    -- Add other necessary fields
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
```

### 4.3 Data Gateway Classes
Create gateway classes for efficient and secure database access:

```php
<?php
namespace Gibbon\Module\ReportTemplate\Domain;

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class TemplateGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'reportTemplateTemplate';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['name', 'description'];

    /**
     * Queries templates based on given criteria
     * @param QueryCriteria $criteria
     * @return DataSet
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
                'timestampModified'
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Inserts a new template record
     * @param array $data
     * @return int|false
     */
    public function insert(array $data)
    {
        $data['timestampCreated'] = date('Y-m-d H:i:s');
        
        return $this->insertAndUpdate($data);
    }

    // Add more methods for update, delete, and specific queries as needed
}
```

## 5. Best Practices

### 5.1 Database Design
1. Use clear, descriptive table and column names for better readability and maintenance.
2. Always include timestamps for creation and modification to track changes.
3. Use appropriate field types and lengths to optimize storage and performance.
4. Add proper indexes for frequently queried columns to improve query speed.
5. Implement foreign key constraints to maintain data integrity across tables.
6. Use consistent character sets (utf8mb4 recommended) for proper unicode support.

### 5.2 Data Access
1. Always use prepared statements to prevent SQL injection vulnerabilities.
2. Implement proper error handling to gracefully manage database errors.
3. Use transactions for complex operations to ensure data consistency.
4. Cache frequently accessed data to reduce database load.
5. Optimize queries for performance, using EXPLAIN to analyze query execution plans.

### 5.3 Security
1. Validate all input data before inserting or updating database records.
2. Escape output to prevent Cross-Site Scripting (XSS) attacks.
3. Implement proper access controls using the access tables created.
4. Use secure password hashing for any user authentication related to reports.
5. Conduct regular security audits of your database schema and access patterns.

## Next Steps
Continue to the next section to learn about implementing database migrations and handling schema updates efficiently. This will cover techniques for smooth transitions between different versions of your database schema as your module evolves.
