# Database Migrations

This comprehensive guide explains how to effectively manage database changes and migrations in your Report Template module for GibbonEdu.

## 1. Migration Basics

### 1.1 Migration Files
Migrations in GibbonEdu are managed through SQL files in the `db` directory. This structure allows for organized and versioned database changes:

```plaintext
ReportTemplate/
├── db/
│   ├── install.sql       # Initial database setup for new installations
│   ├── uninstall.sql     # Clean removal of database structures and data
│   └── updates/          # Version-specific updates for existing installations
│       ├── v1.0.01.sql   # Incremental update files
│       ├── v1.0.02.sql
│       └── ...
```

### 1.2 Version Numbering
Adhere to GibbonEdu's version numbering convention for consistency and clarity:
- Format: `v[major].[minor].[patch]`
- Examples: `v1.0.01`, `v1.2.03`
- Increment patch for bug fixes (e.g., v1.0.01 to v1.0.02)
- Increment minor for new features (e.g., v1.0.00 to v1.1.00)
- Increment major for breaking changes (e.g., v1.2.03 to v2.0.00)

This versioning system helps users and developers understand the nature and impact of each update.

## 2. Creating Migrations

### 2.1 Initial Migration
The `install.sql` file contains your initial database setup. This file is crucial as it establishes the base structure for new installations:

```sql
-- Create module record in the core Gibbon modules table
INSERT INTO gibbonModule SET 
    name='Report Template',
    description='A module for creating and managing report templates.',
    entryURL='templates_manage.php',
    type='Additional',
    category='Assess',
    version='1.0.00';

-- Store the newly created gibbonModuleID for use in subsequent queries
SET @moduleID := (SELECT gibbonModuleID FROM gibbonModule WHERE name='Report Template');

-- Create initial tables
-- Note: Replace this comment with your actual table creation SQL
-- Example:
-- CREATE TABLE reportTemplateTemplate (
--     id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(100) NOT NULL,
--     description TEXT,
--     created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add module actions to allow access through the Gibbon interface
INSERT INTO gibbonAction SET 
    gibbonModuleID=@moduleID,
    name='Manage Templates',
    precedence=0,
    category='Templates',
    description='Create and manage report templates',
    URLList='templates_manage.php,templates_manage_add.php,templates_manage_edit.php,templates_manage_delete.php',
    entryURL='templates_manage.php',
    entrySidebar='Y',
    menuShow='Y',
    defaultPermissionLevel='Preferences';

-- Set initial permissions for the module actions
-- This grants access to all staff roles by default
INSERT INTO gibbonPermission 
    SELECT @moduleID, gibbonRoleID, 'Manage Templates' 
    FROM gibbonRole WHERE category='Staff';
```

### 2.2 Update Migrations
Create version-specific SQL files for each update. These files should be named according to the version they implement and placed in the `updates` directory:

```sql
-- File: db/updates/v1.0.01.sql

-- Add a new column to an existing table
ALTER TABLE reportTemplateTemplate 
    ADD COLUMN customField VARCHAR(100) NULL AFTER description;

-- Update the module version in the core Gibbon modules table
UPDATE gibbonModule SET 
    version='1.0.01' 
    WHERE name='Report Template';

-- Add a new action for template preview functionality
INSERT INTO gibbonAction SET 
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Report Template'),
    name='Preview Template',
    precedence=0,
    category='Templates',
    description='Preview a report template',
    URLList='templates_preview.php',
    entryURL='templates_preview.php',
    entrySidebar='Y',
    menuShow='N',
    defaultPermissionLevel='Preferences';

-- Note: Always include comments explaining the purpose of each change
```

### 2.3 Uninstall Migration
The `uninstall.sql` file ensures clean module removal. It should reverse all changes made by the install and update scripts:

```sql
-- Remove permissions associated with this module
DELETE FROM gibbonPermission WHERE 
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Report Template');

-- Remove all actions associated with this module
DELETE FROM gibbonAction WHERE 
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Report Template');

-- Remove tables in the correct order to avoid foreign key constraints
-- Note: Order is important - remove child tables before parent tables
DROP TABLE IF EXISTS reportTemplateReportAccess;
DROP TABLE IF EXISTS reportTemplateAccess;
DROP TABLE IF EXISTS reportTemplateSetting;
DROP TABLE IF EXISTS reportTemplateSection;
DROP TABLE IF EXISTS reportTemplateArchive;
DROP TABLE IF EXISTS reportTemplateReport;
DROP TABLE IF EXISTS reportTemplateTemplate;

-- Finally, remove the module entry from the core Gibbon modules table
DELETE FROM gibbonModule WHERE name='Report Template';

-- Note: Always test this script thoroughly to ensure it doesn't accidentally remove data from other modules
```

## 3. Managing Updates

### 3.1 Update Process
Create an `update.php` file to handle version updates. This file orchestrates the update process:

```php
<?php
// File: ReportTemplate/update.php

require_once __DIR__.'/../../../gibbon.php';
require_once __DIR__.'/../moduleFunctions.php';

$returns = array();
$moduleVersion = getModuleVersion('Report Template');

// Update from v1.0.00 to v1.0.01
if (version_compare($moduleVersion, '1.0.01', '<')) {
    try {
        // Execute the SQL from the update file
        $sql = file_get_contents(__DIR__.'/db/updates/v1.0.01.sql');
        $pdo->exec($sql);
        
        $returns[] = array(
            'success' => true,
            'message' => 'Update to v1.0.01 successful'
        );
    } catch (PDOException $e) {
        $returns[] = array(
            'success' => false,
            'message' => 'Update to v1.0.01 failed: '.$e->getMessage()
        );
    }
}

// Update from v1.0.01 to v1.0.02
if (version_compare($moduleVersion, '1.0.02', '<')) {
    try {
        $sql = file_get_contents(__DIR__.'/db/updates/v1.0.02.sql');
        $pdo->exec($sql);
        
        $returns[] = array(
            'success' => true,
            'message' => 'Update to v1.0.02 successful'
        );
    } catch (PDOException $e) {
        $returns[] = array(
            'success' => false,
            'message' => 'Update to v1.0.02 failed: '.$e->getMessage()
        );
    }
}

// Add more version updates as needed

// Return results of all update operations
return $returns;
```

### 3.2 Version Helper Functions
Add these helper functions to `moduleFunctions.php` to assist with version management:

```php
<?php
/**
 * Gets the current version of a module from the gibbonModule table
 *
 * @param string $moduleName The name of the module
 * @return string The current version of the module
 */
function getModuleVersion($moduleName)
{
    global $pdo;
    
    try {
        $data = array('name' => $moduleName);
        $sql = "SELECT version FROM gibbonModule WHERE name=:name";
        $result = $pdo->prepare($sql);
        $result->execute($data);
        
        return $result->fetchColumn(0);
    } catch (PDOException $e) {
        // Log error and return empty string if query fails
        error_log('Error getting module version: ' . $e->getMessage());
        return '';
    }
}

/**
 * Updates a module's version number in the gibbonModule table
 *
 * @param string $moduleName The name of the module
 * @param string $version The new version number
 * @return bool True if update was successful, false otherwise
 */
function updateModuleVersion($moduleName, $version)
{
    global $pdo;
    
    try {
        $data = array('version' => $version, 'name' => $moduleName);
        $sql = "UPDATE gibbonModule SET version=:version WHERE name=:name";
        $result = $pdo->prepare($sql);
        
        return $result->execute($data);
    } catch (PDOException $e) {
        // Log error and return false if update fails
        error_log('Error updating module version: ' . $e->getMessage());
        return false;
    }
}
```

## 4. Best Practices

### 4.1 Migration Design
Follow these principles for robust migration design:
1. Make migrations atomic: Each migration should represent one logical change
2. Include both up and down migrations where possible for reversibility
3. Thoroughly test migrations in a development environment before deployment
4. Document all changes in a CHANGELOG.txt file for easy reference

### 4.2 Data Safety
Prioritize data integrity with these practices:
1. Always backup data before applying migrations
2. Use transactions for complex updates to ensure all-or-nothing operations
3. Validate data before and after migration to catch any inconsistencies
4. Include rollback procedures in case of migration failure

Example of a safe transaction:
```sql
START TRANSACTION;

-- Attempt the migration
ALTER TABLE reportTemplateTemplate 
    ADD COLUMN newField VARCHAR(100);

-- Verify the change
SET @columnExists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'reportTemplateTemplate' 
    AND COLUMN_NAME = 'newField'
);

-- Commit or rollback based on verification
SET @sql = IF(@columnExists = 1, 
    'COMMIT', 
    'ROLLBACK'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

### 4.3 Performance Considerations
Optimize your migrations for performance:
1. Consider table size when adding or removing indexes
2. Use batching for large data updates to reduce memory usage and lock times
3. Schedule migrations during off-peak hours to minimize user impact
4. Monitor server resources during migration to catch any performance issues

Example of a batched update:
```sql
-- Update records in batches of 1000
SET @batch = 0;
REPEAT
    UPDATE reportTemplateArchive 
    SET status = 'Complete'
    WHERE status = 'Pending'
    LIMIT 1000;
    
    SET @batch = ROW_COUNT();
UNTIL @batch = 0 END REPEAT;
```

## Next Steps
With a solid understanding of database migrations, proceed to the next section to learn about implementing efficient data access patterns and working with the database in your module's PHP code.
