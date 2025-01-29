# Database Relationships

This comprehensive guide explains how to implement and manage database relationships in your Report Template module, providing detailed explanations and examples for each type of relationship.

## 1. Types of Relationships

### 1.1 One-to-One Relationships
A one-to-one relationship exists when each record in one table corresponds to exactly one record in another table. 

Example: Each template has one default header/footer:

```sql
-- This table represents the header for each report template
-- It has a one-to-one relationship with the reportTemplateTemplate table
CREATE TABLE reportTemplateHeader (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    content TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY template_header (templateID),  -- Ensures one header per template
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Implementation in Gateway class:
```php
<?php
class TemplateGateway extends QueryableGateway
{
    // This method retrieves a template along with its associated header
    public function getTemplateWithHeader($templateID)
    {
        $query = $this
            ->newSelect()
            ->from('reportTemplateTemplate')
            ->cols(['reportTemplateTemplate.*', 'header.content as headerContent'])
            ->leftJoin('reportTemplateHeader as header', 'header.templateID=reportTemplateTemplate.id')
            ->where('reportTemplateTemplate.id = :templateID')
            ->bindValue('templateID', $templateID);

        // Execute the query and fetch the result
        return $this->runSelect($query)->fetch();
    }
}
```

### 1.2 One-to-Many Relationships
A one-to-many relationship exists when a record in one table can be associated with multiple records in another table.

Example: Each template has multiple sections:

```sql
-- This table represents sections within a report template
-- It has a one-to-many relationship with the reportTemplateTemplate table
CREATE TABLE reportTemplateSection (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    name VARCHAR(90) NOT NULL,
    content TEXT,
    sequenceNumber INT(3) NOT NULL,  -- Used to order sections within a template
    PRIMARY KEY (id),
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Implementation in Gateway class:
```php
<?php
class TemplateSectionGateway extends QueryableGateway
{
    // This method retrieves all sections for a given template
    public function selectSectionsByTemplate($templateID)
    {
        $query = $this
            ->newSelect()
            ->from('reportTemplateSection')
            ->cols(['*'])
            ->where('templateID = :templateID')
            ->bindValue('templateID', $templateID)
            ->orderBy(['sequenceNumber']);  // Ensure sections are returned in order

        return $this->runSelect($query);
    }

    // This method inserts a new section, automatically determining its sequence number
    public function insertSection(array $data)
    {
        // Get next sequence number
        $query = $this
            ->newSelect()
            ->from('reportTemplateSection')
            ->cols(['MAX(sequenceNumber) as maxSequence'])
            ->where('templateID = :templateID')
            ->bindValue('templateID', $data['templateID']);

        $maxSequence = $this->runSelect($query)->fetchColumn();
        $data['sequenceNumber'] = $maxSequence + 1;  // Set new section as last in sequence

        return $this->insert($data);
    }
}
```

### 1.3 Many-to-Many Relationships
A many-to-many relationship exists when multiple records in one table can be associated with multiple records in another table.

Example: Templates can be shared with multiple user roles:

```sql
-- This table represents the access permissions for templates
-- It implements a many-to-many relationship between templates and roles
CREATE TABLE reportTemplateAccess (
    id INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
    templateID INT(10) UNSIGNED ZEROFILL NOT NULL,
    gibbonRoleID INT(3) UNSIGNED ZEROFILL NOT NULL,
    permission ENUM('View','Edit','Manage') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY template_role (templateID, gibbonRoleID),  -- Ensures unique template-role combinations
    FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (gibbonRoleID) REFERENCES gibbonRole(gibbonRoleID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Implementation in Gateway class:
```php
<?php
class TemplateAccessGateway extends QueryableGateway
{
    // This method retrieves all access entries for a given template
    public function selectAccessByTemplate($templateID)
    {
        $query = $this
            ->newSelect()
            ->from('reportTemplateAccess')
            ->cols(['reportTemplateAccess.*', 'role.name as roleName'])
            ->innerJoin('gibbonRole as role', 'role.gibbonRoleID=reportTemplateAccess.gibbonRoleID')
            ->where('templateID = :templateID')
            ->bindValue('templateID', $templateID);

        return $this->runSelect($query);
    }

    // This method inserts multiple access entries for a template
    public function insertBulkAccess($templateID, array $roleIDs, $permission)
    {
        $data = array_map(function($roleID) use ($templateID, $permission) {
            return [
                'templateID' => $templateID,
                'gibbonRoleID' => $roleID,
                'permission' => $permission
            ];
        }, $roleIDs);

        return $this->insertMultiple($data);
    }
}
```

## 2. Managing Relationships

### 2.1 Cascade Operations
Use appropriate ON DELETE and ON UPDATE clauses to maintain referential integrity:

```sql
-- Cascade: When parent is deleted/updated, delete/update children
-- Use this when child records cannot exist without the parent
FOREIGN KEY (templateID) REFERENCES reportTemplateTemplate(id)
    ON DELETE CASCADE ON UPDATE CASCADE

-- Set NULL: When parent is deleted/updated, set child reference to NULL
-- Use this when child records can exist independently of the parent
FOREIGN KEY (gibbonPersonIDModified) REFERENCES gibbonPerson(gibbonPersonID)
    ON DELETE SET NULL ON UPDATE CASCADE

-- Restrict: Prevent deletion/update of parent if children exist
-- Use this to enforce strict referential integrity
FOREIGN KEY (gibbonSchoolYearID) REFERENCES gibbonSchoolYear(gibbonSchoolYearID)
    ON DELETE RESTRICT ON UPDATE CASCADE
```

### 2.2 Relationship Integrity
Maintain data integrity across relationships by using transactions and checks:

```php
<?php
class TemplateService
{
    public function deleteTemplate($templateID)
    {
        try {
            // Start transaction to ensure all operations succeed or fail together
            $this->db->beginTransaction();

            // Check for existing reports to prevent orphaned data
            $reports = $this->reportGateway->selectBy(['templateID' => $templateID]);
            if ($reports->rowCount() > 0) {
                throw new Exception('Cannot delete template with existing reports');
            }

            // Delete template (cascades to sections and access due to FK constraints)
            $success = $this->templateGateway->delete($templateID);
            if (!$success) {
                throw new Exception('Failed to delete template');
            }

            // Commit transaction if all operations succeeded
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // Rollback transaction if any operation failed
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

### 2.3 Eager Loading
Optimize relationship loading by fetching related data in a single query:

```php
<?php
class TemplateGateway extends QueryableGateway
{
    public function getTemplateWithRelations($templateID)
    {
        // Get template with header
        $template = $this->getTemplateWithHeader($templateID);
        if (empty($template)) {
            return null;
        }

        // Get sections for the template
        $sectionGateway = new TemplateSectionGateway($this->db);
        $template['sections'] = $sectionGateway
            ->selectSectionsByTemplate($templateID)
            ->fetchAll();

        // Get access roles for the template
        $accessGateway = new TemplateAccessGateway($this->db);
        $template['access'] = $accessGateway
            ->selectAccessByTemplate($templateID)
            ->fetchAll();

        return $template;
    }
}
```

## 3. Best Practices

### 3.1 Relationship Design
1. Use appropriate relationship types based on data requirements
2. Consider data integrity requirements and implement necessary constraints
3. Plan for scalability, especially for tables involved in many-to-many relationships
4. Document relationships clearly, including any business rules
5. Use meaningful constraint names for easier debugging and maintenance

### 3.2 Performance
1. Use indexes on foreign keys to speed up join operations
2. Optimize join queries by selecting only necessary columns
3. Consider denormalization when read performance is critical
4. Use eager loading to reduce the number of database queries
5. Monitor query performance and optimize as necessary

### 3.3 Maintenance
1. Regularly check referential integrity to ensure data consistency
2. Implement processes to clean up orphaned records
3. Keep relationship documentation up-to-date as schema evolves
4. Monitor relationship usage to identify potential improvements
5. Plan for schema evolution, considering impact on existing relationships

## Next Steps
Continue to the next section to learn about database best practices and optimization techniques, which will help you further improve the performance and maintainability of your database design.
