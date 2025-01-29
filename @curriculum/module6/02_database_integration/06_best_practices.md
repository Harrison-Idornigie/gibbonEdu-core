# Database Best Practices

This comprehensive guide outlines best practices for database development in your Report Template module. Following these practices will ensure efficient, secure, and maintainable database operations.

## 1. Database Design

### 1.1 Table Design
1. Use consistent naming conventions:
   ```sql
   -- Module prefix for all tables to avoid conflicts with other modules
   reportTemplateTemplate
   reportTemplateSection
   reportTemplateAccess

   -- Meaningful column names for clarity and self-documentation
   id                  -- Primary key (always use 'id' for consistency)
   gibbonPersonID      -- Foreign key to core tables (use core table name + 'ID')
   timestampCreated    -- Audit timestamps (use 'timestamp' prefix)
   sequenceNumber      -- Ordering columns (use descriptive names)
   ```

2. Choose appropriate data types for efficiency and data integrity:
   ```sql
   -- Use the most efficient type for the data to optimize storage and performance
   id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT  -- For IDs (unsigned for positive values only)
   name VARCHAR(90)                             -- For short text (specify max length)
   content MEDIUMTEXT                           -- For large text (up to 16MB)
   active ENUM('Y','N')                         -- For boolean values (more efficient than TINYINT)
   sequenceNumber INT(3)                        -- For ordering (small range integer)
   timestampCreated DATETIME                    -- For timestamps (includes date and time)
   ```

3. Define proper constraints to maintain data integrity:
   ```sql
   -- Primary keys for unique identification
   PRIMARY KEY (id)

   -- Foreign keys with appropriate actions for referential integrity
   FOREIGN KEY (templateID) 
       REFERENCES reportTemplateTemplate(id)
       ON DELETE CASCADE  -- Automatically delete related records
       ON UPDATE CASCADE  -- Automatically update related records

   -- Unique constraints to prevent duplicate data
   UNIQUE KEY template_name (name)

   -- Check constraints for data validation (MySQL 8.0+)
   CHECK (sequenceNumber > 0)
   ```

### 1.2 Indexing Strategy
Proper indexing is crucial for query performance. Consider the following strategies:

1. Index types and their use cases:
   ```sql
   -- Primary key (automatically indexed)
   PRIMARY KEY (id)

   -- Foreign key indexes for efficient joins
   INDEX idx_template_id (templateID)

   -- Composite indexes for multi-column conditions
   INDEX idx_template_active (templateID, active)

   -- Unique indexes to enforce data uniqueness
   UNIQUE INDEX idx_template_name (name)
   ```

2. Index guidelines:
   - Always index foreign key columns to optimize joins
   - Index frequently searched or filtered columns
   - Use composite indexes for queries that filter on multiple columns
   - Avoid over-indexing as it impacts write performance and storage
   - Regularly monitor index usage and remove unused indexes

## 2. Query Optimization

### 2.1 Writing Efficient Queries
Optimized queries are essential for database performance. Follow these practices:

1. Select only needed columns to reduce data transfer and processing:
   ```php
   // Good practice: Select specific columns
   $query = $this
       ->newSelect()
       ->from('reportTemplateTemplate')
       ->cols(['id', 'name', 'description'])
       ->where('active = :active');

   // Bad practice: Avoid using SELECT * as it retrieves unnecessary data
   $query = $this
       ->newSelect()
       ->from('reportTemplateTemplate')
       ->cols(['*'])
       ->where('active = :active');
   ```

2. Use appropriate joins to efficiently combine data from multiple tables:
   ```php
   // Use LEFT JOIN when the joined data might not exist
   $query = $this
       ->newSelect()
       ->from('reportTemplateTemplate')
       ->cols(['template.*', 'header.content'])
       ->leftJoin('reportTemplateHeader as header', 
           'header.templateID=template.id');

   // Use INNER JOIN when the joined data must exist
   $query = $this
       ->newSelect()
       ->from('reportTemplateTemplate')
       ->cols(['template.*', 'creator.preferredName'])
       ->innerJoin('gibbonPerson as creator', 
           'creator.gibbonPersonID=template.gibbonPersonIDCreated');
   ```

3. Optimize WHERE clauses for efficient filtering:
   ```php
   // Use indexed columns in WHERE clause for faster lookups
   $query = $this
       ->newSelect()
       ->from('reportTemplateTemplate')
       ->where('id = :id')           // Uses primary key index
       ->orWhere('name = :name');    // Uses unique index

   // Avoid functions on indexed columns as they prevent index usage
   // Bad: WHERE YEAR(timestampCreated) = 2024
   // Good: Use a range to allow index usage
   ->where('timestampCreated BETWEEN :start AND :end')
   ->bindValue('start', '2024-01-01')
   ->bindValue('end', '2024-12-31');
   ```

### 2.2 Query Performance
Monitor and optimize query performance:

1. Use EXPLAIN to analyze query execution plans:
   ```sql
   EXPLAIN SELECT t.*, s.name as sectionName
   FROM reportTemplateTemplate t
   LEFT JOIN reportTemplateSection s ON s.templateID = t.id
   WHERE t.active = 'Y'
   ORDER BY t.name;
   ```
   Analyze the output to identify potential improvements like missing indexes or inefficient joins.

2. Implement caching for frequently accessed, rarely changing data:
   ```php
   class TemplateGateway extends QueryableGateway
   {
       private $cache;

       public function getTemplateByID($id)
       {
           $cacheKey = "template_{$id}";

           // Check cache first to reduce database load
           if ($this->cache->has($cacheKey)) {
               return $this->cache->get($cacheKey);
           }

           // Fetch from database if not in cache
           $template = $this->runSelect($query)->fetch();

           // Cache for 5 minutes to balance freshness and performance
           $this->cache->set($cacheKey, $template, 300);

           return $template;
       }
   }
   ```

## 3. Data Integrity

### 3.1 Validation
Ensure data integrity through thorough validation:

1. Input validation to prevent invalid data:
   ```php
   class TemplateGateway extends QueryableGateway
   {
       public function validate(array $data)
       {
           $errors = [];

           // Check for required fields
           if (empty($data['name'])) {
               $errors[] = 'Name is required';
           }

           // Enforce length constraints
           if (strlen($data['name']) > 90) {
               $errors[] = 'Name cannot exceed 90 characters';
           }

           // Validate format (e.g., email)
           if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
               $errors[] = 'Invalid email format';
           }

           return $errors;
       }
   }
   ```

2. Data sanitization to prevent malicious input:
   ```php
   class TemplateService
   {
       public function createTemplate(array $data)
       {
           // Sanitize input to remove potentially harmful content
           $data = $this->sanitize($data);

           // Validate sanitized data
           $errors = $this->gateway->validate($data);
           if (!empty($errors)) {
               throw new ValidationException($errors);
           }

           // Create template with clean, validated data
           return $this->gateway->insert($data);
       }

       private function sanitize(array $data)
       {
           return [
               'name' => strip_tags($data['name'] ?? ''),
               'description' => strip_tags($data['description'] ?? ''),
               'content' => purify($data['content'] ?? ''),  // Use HTML Purifier for rich text
               'active' => in_array($data['active'], ['Y', 'N']) ? $data['active'] : 'N'
           ];
       }
   }
   ```

### 3.2 Transactions
Use transactions for operations that modify multiple tables:

1. Implement transactions for complex operations to ensure data consistency:
   ```php
   class TemplateService
   {
       public function copyTemplate($templateID)
       {
           try {
               // Start transaction
               $this->db->beginTransaction();

               // Copy main template
               $template = $this->gateway->getByID($templateID);
               $template['name'] .= ' (Copy)';
               $newID = $this->gateway->insert($template);

               // Copy associated sections
               $sections = $this->sectionGateway->selectBy(['templateID' => $templateID]);
               foreach ($sections as $section) {
                   $section['templateID'] = $newID;
                   $this->sectionGateway->insert($section);
               }

               // Commit transaction if all operations succeed
               $this->db->commit();
               return $newID;

           } catch (Exception $e) {
               // Rollback all changes if any operation fails
               $this->db->rollBack();
               throw $e;
           }
       }
   }
   ```

## 4. Security

### 4.1 SQL Injection Prevention
Protect against SQL injection attacks:

1. Always use prepared statements to separate SQL logic from data:
   ```php
   // Good practice: Use query builder with parameter binding
   $query = $this
       ->newSelect()
       ->from('reportTemplateTemplate')
       ->where('id = :id')
       ->bindValue('id', $id);

   // Bad practice: Never use string concatenation for SQL
   $query = "SELECT * FROM reportTemplateTemplate WHERE id = " . $id;
   ```

2. Escape special characters when dynamic SQL is unavoidable:
   ```php
   $name = $this->db->quote($data['name']);
   ```

### 4.2 Access Control
Implement robust access control mechanisms:

1. Use row-level security to restrict data access based on user roles:
   ```php
   class TemplateGateway extends QueryableGateway
   {
       public function queryTemplatesByPerson($gibbonPersonID)
       {
           $query = $this
               ->newSelect()
               ->from('reportTemplateTemplate as template')
               ->innerJoin('reportTemplateAccess as access', 
                   'access.templateID=template.id')
               ->innerJoin('gibbonRole as role', 
                   'role.gibbonRoleID=access.gibbonRoleID')
               ->innerJoin('gibbonPerson as person', 
                   'FIND_IN_SET(role.gibbonRoleID, person.gibbonRoleIDAll)')
               ->where('person.gibbonPersonID = :gibbonPersonID')
               ->bindValue('gibbonPersonID', $gibbonPersonID);

           return $this->runSelect($query);
       }
   }
   ```

## 5. Maintenance

### 5.1 Backup Strategy
Implement a robust backup strategy to prevent data loss:

1. Perform regular backups:
   ```bash
   # Daily backup script for Report Template module tables
   mysqldump -u user -p gibbon_db reportTemplate* > backup_$(date +%Y%m%d).sql
   ```

2. Create backups before major operations like migrations:
   ```php
   class TemplateUpdate
   {
       public function preUpdate()
       {
           // Backup affected tables before update
           $tables = ['reportTemplateTemplate', 'reportTemplateSection'];
           foreach ($tables as $table) {
               $backup = $table . '_backup_' . date('Ymd');
               $this->db->exec("CREATE TABLE {$backup} LIKE {$table}");
               $this->db->exec("INSERT INTO {$backup} SELECT * FROM {$table}");
           }
       }
   }
   ```

### 5.2 Monitoring
Implement monitoring to proactively identify issues:

1. Log and analyze slow queries:
   ```php
   class QueryLogger
   {
       public function logQuery($sql, $params, $duration)
       {
           if ($duration > 1.0) { // Log queries taking longer than 1 second
               $this->logger->warning('Slow query detected', [
                   'sql' => $sql,
                   'params' => $params,
                   'duration' => $duration
               ]);
           }
       }
   }
   ```

2. Perform regular health checks:
   ```php
   class DatabaseHealth
   {
       public function checkIntegrity()
       {
           // Check for orphaned records (sections without a parent template)
           $sql = "SELECT s.* FROM reportTemplateSection s
                  LEFT JOIN reportTemplateTemplate t ON t.id = s.templateID
                  WHERE t.id IS NULL";
           
           $orphans = $this->db->query($sql)->fetchAll();
           if (!empty($orphans)) {
               $this->logger->error('Orphaned sections found', [
                   'count' => count($orphans)
               ]);
           }
       }
   }
   ```

## Next Steps
With these database best practices in place, you're well-equipped to build a robust and efficient Report Template module. The next section will cover implementing the user interface, where you'll learn how to create intuitive forms and displays for managing report templates.
