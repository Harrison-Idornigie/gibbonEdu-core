# Lesson 2: Database Integration in Gibbon Modules

## Understanding and Working with CHANGEDB.php

The `CHANGEDB.php` file plays a crucial role in managing your module's database changes over time. This file is essential because it ensures that when users upgrade your module, their database structure stays synchronized with your code. Let's dive deep into how this file works and best practices for using it effectively.

### Basic Structure of CHANGEDB.php

The `CHANGEDB.php` file follows a specific structure. Here's a breakdown of its components:

```php
<?php
// CHANGEDB.php for Equipment Tracker module

// Initialize the SQL array and counter
$sql = array();
$count = 0;

// Version 1.0.00 - Initial version
$sql[$count][0] = "1.0.00";
$sql[$count][1] = "
-- First version, create initial tables
CREATE TABLE moduleEquipmentItem (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT NULL,
    condition ENUM('New', 'Good', 'Fair', 'Poor') DEFAULT 'Good',
    dateAdded DATE NOT NULL,
    PRIMARY KEY (id),
    INDEX name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE moduleEquipmentLoan (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    equipmentItemID INT(10) UNSIGNED NOT NULL,
    gibbonPersonID INT(10) UNSIGNED NOT NULL,
    dateOut DATE NOT NULL,
    dateExpected DATE NOT NULL,
    dateReturned DATE NULL,
    status ENUM('On Loan', 'Returned', 'Overdue') DEFAULT 'On Loan',
    notes TEXT NULL,
    PRIMARY KEY (id),
    INDEX equipmentItem (equipmentItemID),
    INDEX person (gibbonPersonID),
    FOREIGN KEY (equipmentItemID) REFERENCES moduleEquipmentItem(id),
    FOREIGN KEY (gibbonPersonID) REFERENCES gibbonPerson(gibbonPersonID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Version 1.1.00 - Adding new features
$count++;
$sql[$count][0] = "1.1.00";
$sql[$count][1] = "
-- Add condition on return field
ALTER TABLE moduleEquipmentLoan 
ADD COLUMN conditionOnReturn ENUM('New', 'Good', 'Fair', 'Poor') NULL 
AFTER dateReturned;

-- Add new index for date tracking
CREATE INDEX loan_dates ON moduleEquipmentLoan(dateOut, dateExpected, dateReturned);
";
```

Let's break down what's happening here:

1. We start by initializing an array `$sql` and a counter `$count`. These will be used to store our database changes.

2. For each version of your module, you create a new entry in the `$sql` array. The first element `$sql[$count][0]` is the version number, and the second element `$sql[$count][1]` contains the SQL statements for that version.

3. In version 1.0.00, we're creating two tables: `moduleEquipmentItem` and `moduleEquipmentLoan`. These tables are designed to track equipment and equipment loans.

4. In version 1.1.00, we're making changes to the existing structure. We're adding a new column `conditionOnReturn` to the `moduleEquipmentLoan` table and creating a new index for date tracking.

### Version Control Best Practices

When working with `CHANGEDB.php`, it's important to follow these best practices:

1. **Version Numbering**
   - Use semantic versioning (MAJOR.MINOR.PATCH)
   - Always pad with zeros: "1.0.00" not "1.0.0"
   - Increment version numbers logically:
     ```php
     // Incorrect way
     $sql[$count][0] = "1.0.00";
     $sql[$count+1][0] = "1.0.01";  // Don't use count+1
     
     // Correct way
     $count = 0;
     $sql[$count][0] = "1.0.00";
     $count++;
     $sql[$count][0] = "1.0.01";
     ```

2. **SQL Comments**
   - Document each change clearly
   - Explain why changes are made
   - Group related changes together

### Database Operations in Gibbon Modules

Let's look at some common database operations you might perform in your Gibbon module:

#### Creating Tables

When creating tables for your module, follow these guidelines:

```php
// Example of creating a well-structured table
$sql[$count][1] = "
CREATE TABLE moduleEquipmentCategory (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT NULL,
    active ENUM('Y','N') DEFAULT 'Y',
    sequenceNumber INT(5) NOT NULL DEFAULT 0,
    timestampCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    timestampModified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    gibbonPersonIDCreated INT(10) UNSIGNED NOT NULL,
    gibbonPersonIDModified INT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY name (name),
    INDEX active (active),
    FOREIGN KEY (gibbonPersonIDCreated) REFERENCES gibbonPerson(gibbonPersonID),
    FOREIGN KEY (gibbonPersonIDModified) REFERENCES gibbonPerson(gibbonPersonID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Equipment categories for organization';
";
```

Key points to remember when creating tables:
1. Always use `ENGINE=InnoDB` for better performance and reliability.
2. Always use `DEFAULT CHARSET=utf8mb4` to support a wide range of characters.
3. Include appropriate indexes to improve query performance.
4. Add helpful table comments to explain the purpose of the table.
5. Consider adding audit fields (created/modified timestamps and users) for tracking changes.

#### Adding Indexes

Indexes are crucial for improving query performance. Here's how to add them:

```php
// Adding single-column index
$sql[$count][1] = "
CREATE INDEX status ON moduleEquipmentLoan(status);
";

// Adding multi-column index
$sql[$count][1] = "
CREATE INDEX loan_search ON moduleEquipmentLoan(gibbonPersonID, status, dateOut);
";

// Adding unique constraint
$sql[$count][1] = "
ALTER TABLE moduleEquipmentItem 
ADD UNIQUE INDEX serialNumber (serialNumber);
";
```

#### Relationships with Core Gibbon Tables

When your module needs to interact with core Gibbon data, you'll need to create relationships with core tables:

1. **Common Core Tables**
    - `gibbonPerson`: Contains user accounts and personal data
    - `gibbonRole`: Stores user roles and permissions
    - `gibbonSchoolYear`: Holds information about academic years
    - `gibbonCourse`: Contains course information
    - `gibbonCourseClass`: Stores class-specific data

2. **Relationship Guidelines**
    - Always use proper foreign key constraints to maintain data integrity
    - Include appropriate ON DELETE actions (CASCADE, SET NULL, etc.)
    - Reference the correct primary key fields
    - Consider the implications on data lifecycle

3. **Example of Core Table Relations**
```php
CREATE TABLE moduleEquipmentBooking (
     id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
     gibbonSchoolYearID INT(3) UNSIGNED NOT NULL,
     gibbonPersonIDBooker INT(10) UNSIGNED NOT NULL,
     gibbonSpaceID INT(10) UNSIGNED NULL,
     PRIMARY KEY (id),
     FOREIGN KEY (gibbonSchoolYearID) 
          REFERENCES gibbonSchoolYear(gibbonSchoolYearID),
     FOREIGN KEY (gibbonPersonIDBooker) 
          REFERENCES gibbonPerson(gibbonPersonID)
          ON DELETE CASCADE,
     FOREIGN KEY (gibbonSpaceID) 
          REFERENCES gibbonSpace(gibbonSpaceID)
          ON DELETE SET NULL
);
```

In this example, we're creating a table that relates to three core Gibbon tables: `gibbonSchoolYear`, `gibbonPerson`, and `gibbonSpace`.

4. **Data Integrity Considerations**
    - Use CASCADE sparingly, as it can lead to unintended data loss
    - Consider SET NULL for optional relationships
    - Always strive to maintain referential integrity
    - Clearly document any dependencies in your code comments

#### Data Migration

As your module evolves, you may need to move or transform data. Here's a step-by-step guide:

1. **Plan Your Migration Strategy**
    - Document the current and desired data structure
    - Identify any data dependencies
    - Create backup points
    - Test with sample data before applying to production

2. **Step-by-Step Migration Example**
```php
$count++;
$sql[$count][0] = "1.3.00";
$sql[$count][1] = "
-- Step 1: Create temporary backup
CREATE TABLE tmp_equipment_backup AS 
SELECT * FROM moduleEquipmentItem;

-- Step 2: Add new structure
ALTER TABLE moduleEquipmentItem 
ADD COLUMN categoryID INT(10) UNSIGNED NULL,
ADD FOREIGN KEY (categoryID) REFERENCES moduleEquipmentCategory(id);

-- Step 3: Transform and migrate data
UPDATE moduleEquipmentItem ei
SET ei.categoryID = (
     SELECT id FROM moduleEquipmentCategory ec 
     WHERE ec.name = ei.oldCategoryField
     LIMIT 1
);

-- Step 4: Verify migration
CREATE TEMPORARY TABLE migration_check AS
SELECT * FROM moduleEquipmentItem 
WHERE categoryID IS NULL 
AND oldCategoryField IS NOT NULL;

-- Step 5: Cleanup
DROP TABLE IF EXISTS tmp_equipment_backup;
ALTER TABLE moduleEquipmentItem DROP COLUMN oldCategoryField;
";
```

This example shows a migration process where we're adding a new category relationship to the `moduleEquipmentItem` table.

3. **Safety Measures**
    - Use transactions for complex migrations to ensure all-or-nothing operations
    - Include validation checks to verify data integrity
    - Provide rollback instructions in case something goes wrong
    - Keep changes atomic (small, single-purpose updates)
    - Log migration results for troubleshooting

4. **Data Transformation Guidelines**
    - Handle NULL values explicitly to avoid unexpected results
    - Consider data type conversions carefully
    - Maintain data integrity throughout the migration process
    - Document your transformation logic clearly in comments

### Best Practices for Database Updates

1. **Always Backup First**
   ```sql
   -- Always provide backup steps in comments
   -- BACKUP: mysqldump -u username -p database_name table_name > backup.sql
   ```

2. **Use Safe Alterations**
   When making changes, always check if the change is necessary:
   ```php
   // Check if column exists before adding
   $sql[$count][1] = "
   SET @exist := (
       SELECT COUNT(*)
       FROM information_schema.COLUMNS 
       WHERE TABLE_NAME = 'moduleEquipmentItem'
       AND COLUMN_NAME = 'newColumn'
   );
   
   SET @query = IF(@exist = 0,
       'ALTER TABLE moduleEquipmentItem ADD COLUMN newColumn VARCHAR(50) NULL',
       'SELECT \"Column already exists\"'
   );
   
   PREPARE stmt FROM @query;
   EXECUTE stmt;
   DEALLOCATE PREPARE stmt;
   ";
   ```

3. **Validate Data Before Changes**
   Always validate your data before making significant changes:
   ```php
   // Validate data before migration
   $sql[$count][1] = "
   -- Create temporary validation table
   CREATE TABLE tmp_validation AS
   SELECT id, name, description
   FROM moduleEquipmentItem
   WHERE name IS NULL OR LENGTH(name) = 0;
   
   -- Only proceed if validation passes
   SET @invalid := (SELECT COUNT(*) FROM tmp_validation);
   SET @query = IF(@invalid = 0,
       'ALTER TABLE moduleEquipmentItem MODIFY name VARCHAR(50) NOT NULL',
       'SELECT \"Data validation failed - check tmp_validation table\"'
   );
   
   PREPARE stmt FROM @query;
   EXECUTE stmt;
   DEALLOCATE PREPARE stmt;
   
   -- Clean up
   DROP TABLE tmp_validation;
   ";
   ```

## Exercise: Database Management in Your Module

To practice what you've learned, try the following exercise:

1. Create a CHANGEDB.php file for your module
   ```php
   <?php
   $sql = array();
   $count = 0;
   
   // Your first version
   $sql[$count][0] = "1.0.00";
   $sql[$count][1] = "
   -- Create your initial tables here
   CREATE TABLE yourModuleMainTable (
       id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
       name VARCHAR(50) NOT NULL,
       description TEXT NULL,
       PRIMARY KEY (id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ";
   ```

2. Add a new feature requiring database changes
   ```php
   $count++;
   $sql[$count][0] = "1.1.00";
   $sql[$count][1] = "
   -- Add a new column to your table
   ALTER TABLE yourModuleMainTable
   ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active';
   
   -- Create an index on the new column
   CREATE INDEX status_index ON yourModuleMainTable(status);
   ";
   ```

3. Practice data migration
   ```php
   $count++;
   $sql[$count][0] = "1.2.00";
   $sql[$count][1] = "
   -- Create a new table
   CREATE TABLE yourModuleDetailTable (
       id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
       mainTableID INT(10) UNSIGNED NOT NULL,
       detailInfo VARCHAR(100) NOT NULL,
       PRIMARY KEY (id),
       FOREIGN KEY (mainTableID) REFERENCES yourModuleMainTable(id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- Migrate data from main table to detail table
   INSERT INTO yourModuleDetailTable (mainTableID, detailInfo)
   SELECT id, description FROM yourModuleMainTable
   WHERE description IS NOT NULL;
   
   -- Remove the migrated column from the main table
   ALTER TABLE yourModuleMainTable DROP COLUMN description;
   ";
   ```

## Common Mistakes to Avoid in Gibbon Module Database Management

1. **Not Using Foreign Keys**
   - Always define relationships properly using foreign keys
   - Use appropriate ON DELETE/UPDATE actions to maintain data integrity

2. **Poor Index Choices**
   - Don't index every column; only index columns used in WHERE, JOIN, and ORDER BY clauses
   - Consider query patterns when creating indexes
   - Use the EXPLAIN statement to verify index usage in your queries

3. **Unsafe Migrations**
   - Always validate data before performing migrations
   - Provide rollback steps in case something goes wrong
   - Test your migrations with real data in a safe environment before applying to production

4. **Version Number Mistakes**
   - Don't skip version numbers; increment logically
   - Keep changes atomic (one feature or fix per version)
   - Document all changes clearly in your code comments

## Next Steps in Your Gibbon Module Development Journey

After completing this lesson on database integration:
1. Review your database design to ensure it follows best practices
2. Test all migrations thoroughly in a development environment
3. Verify that all foreign key relationships are correctly defined
4. Document your schema clearly for future reference and for other developers

In the next lesson, we'll explore how to create actions and pages in your Gibbon module to interact with your newly created database structure. This will involve learning about Gibbon's routing system, form creation, and data retrieval/manipulation techniques.

Remember, effective database management is crucial for creating robust and maintainable Gibbon modules. Take your time to understand these concepts thoroughly, as they form the foundation of your module's data layer.
