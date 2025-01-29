# Secure CRUD Operations

A comprehensive guide to implementing secure Create, Read, Update, and Delete (CRUD) operations in GibbonEdu.

## TODO Topics

1. Create Operations
   - Data validation: Ensuring input data meets specified criteria
   - Permission checks: Verifying user authorization for create actions
   - Error handling: Gracefully managing and reporting errors
   - Transaction management: Ensuring data consistency across multiple operations
   - Audit logging: Recording create actions for accountability

2. Read Operations
   - Query optimization: Improving database query performance
   - Data filtering: Limiting data retrieval based on specific criteria
   - Access control: Restricting data access based on user permissions
   - Result pagination: Managing large result sets efficiently
   - Search functionality: Implementing effective data search capabilities

3. Update Operations
   - Concurrency handling: Managing simultaneous update attempts
   - Version control: Tracking changes to data over time
   - Change tracking: Recording specific modifications made to data
   - Validation rules: Ensuring updated data meets required criteria
   - Update triggers: Automating actions based on data updates

4. Delete Operations
   - Soft deletes: Marking records as deleted without permanent removal
   - Cascade deletes: Handling related data deletion
   - Recovery options: Implementing data restoration capabilities
   - Delete confirmation: Preventing accidental data deletion
   - Archiving: Storing deleted data for future reference

## Practical Example
We'll implement secure CRUD operations for the following areas:
- Template management: Handling report templates
- Report generation: Creating and managing reports
- User preferences: Storing and updating user-specific settings
- System settings: Managing global application configurations

## 1. Create Operations

### 1.1 Template Creation

```php
// Domain/TemplateGateway.php
class TemplateGateway extends QueryableGateway
{
    private static $tableName = 'gibbonReportTemplate';
    private static $primaryKey = 'gibbonReportTemplateID';
    
    public function insert(array $data)
    {
        // Step 1: Validate required fields
        $validator = new TemplateValidator();
        if ($errors = $validator->validateTemplate($data)) {
            throw new ValidationException($errors);
        }
        
        // Step 2: Begin database transaction
        $this->db->beginTransaction();
        
        try {
            // Step 3: Prepare data for insertion
            $data['created'] = date('Y-m-d H:i:s');
            $data['gibbonPersonIDCreated'] = $this->session->get('gibbonPersonID');
            
            // Step 4: Insert the template
            $template = $this->insertAndReturn($data);
            
            // Step 5: Create default sections for the template
            $sectionGateway = $this->container->get(TemplateSectionGateway::class);
            $sectionGateway->insertDefaultSections($template['gibbonReportTemplateID']);
            
            // Step 6: Commit the transaction if all operations succeed
            $this->db->commit();
            return $template;
            
        } catch (Exception $e) {
            // Step 7: Rollback the transaction if any operation fails
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

### 1.2 Audit Logging

```php
// Domain/AuditLogGateway.php
class AuditLogGateway extends QueryableGateway
{
    public function logTemplateAction($templateID, $action, $data = [])
    {
        // Record an audit log entry for template-related actions
        return $this->insert([
            'module' => 'Reports',
            'action' => $action,
            'recordID' => $templateID,
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
            'data' => json_encode($data)
        ]);
    }
}
```

## 2. Read Operations

### 2.1 Optimized Queries

```php
// Domain/TemplateGateway.php
public function selectTemplatesBySchoolYear($gibbonSchoolYearID)
{
    // Construct an optimized query to fetch templates with related criteria count
    $query = $this
        ->newSelect()
        ->from($this->getTableName())
        ->cols([
            'gibbonReportTemplate.gibbonReportTemplateID',
            'gibbonReportTemplate.name',
            'gibbonReportTemplate.active',
            'COUNT(DISTINCT gibbonReportTemplateCriteria.gibbonReportTemplateCriteriaID) as totalCriteria'
        ])
        ->leftJoin('gibbonReportTemplateCriteria', 'gibbonReportTemplateCriteria.gibbonReportTemplateID=gibbonReportTemplate.gibbonReportTemplateID')
        ->where('gibbonReportTemplate.gibbonSchoolYearID=:gibbonSchoolYearID')
        ->groupBy(['gibbonReportTemplate.gibbonReportTemplateID'])
        ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        
    return $this->runSelect($query);
}
```

### 2.2 Access Control

```php
// Domain/TemplateAccessGateway.php
class TemplateAccessGateway extends QueryableGateway
{
    public function getTemplateAccess($templateID, $gibbonPersonID)
    {
        // Fetch direct access permissions for a specific template and user
        $query = $this
            ->newSelect()
            ->from('gibbonReportTemplateAccess')
            ->cols(['gibbonReportTemplateAccess.*'])
            ->where('gibbonReportTemplateID=:templateID')
            ->where('gibbonPersonID=:personID')
            ->bindValue('templateID', $templateID)
            ->bindValue('personID', $gibbonPersonID);
            
        return $this->runSelect($query)->fetch();
    }
    
    public function hasTemplateAccess($templateID, $gibbonPersonID)
    {
        // Check direct access
        $access = $this->getTemplateAccess($templateID, $gibbonPersonID);
        if ($access) return true;
        
        // Check role-based access
        $roleAccess = $this->getTemplateRoleAccess($templateID);
        $userRoles = $this->session->get('gibbonRoleIDAll');
        
        // Return true if user has any role that grants access
        return !empty(array_intersect($roleAccess, explode(',', $userRoles)));
    }
}
```

### 2.3 Pagination

```php
// Domain/TemplateGateway.php
public function queryTemplates($criteria, $skipRows = 0, $pageSize = 50)
{
    // Construct a query with search, filtering, and pagination
    $query = $this
        ->newSelect()
        ->from($this->getTableName())
        ->cols(['*'])
        ->orderBy(['name']);
        
    // Apply search criteria if provided
    if (!empty($criteria['search'])) {
        $query->where('name LIKE :search')
              ->bindValue('search', '%'.$criteria['search'].'%');
    }
    
    // Apply active/inactive filter if specified
    if (!empty($criteria['active'])) {
        $query->where('active = :active')
              ->bindValue('active', $criteria['active']);
    }
    
    // Add pagination to limit result set
    $query->limit($pageSize)->offset($skipRows);
    
    return $this->runSelect($query);
}
```

## 3. Update Operations

### 3.1 Concurrency Control

```php
// Domain/TemplateGateway.php
public function update($id, array $data)
{
    // Step 1: Check version to prevent concurrent modifications
    $current = $this->getByID($id);
    if ($current['version'] != $data['version']) {
        throw new ConcurrencyException('Template has been modified by another user');
    }
    
    // Step 2: Validate changes
    $validator = new TemplateValidator();
    if ($errors = $validator->validateTemplate($data)) {
        throw new ValidationException($errors);
    }
    
    // Step 3: Begin transaction
    $this->db->beginTransaction();
    
    try {
        // Step 4: Update version and modification info
        $data['version'] = $current['version'] + 1;
        $data['modified'] = date('Y-m-d H:i:s');
        $data['gibbonPersonIDModified'] = $this->session->get('gibbonPersonID');
        
        // Step 5: Update template
        $updated = parent::update($id, $data);
        
        // Step 6: Log changes for audit trail
        $auditLog = $this->container->get(AuditLogGateway::class);
        $auditLog->logTemplateAction($id, 'Update', [
            'changes' => array_diff_assoc($data, $current)
        ]);
        
        // Step 7: Commit transaction if all operations succeed
        $this->db->commit();
        return $updated;
        
    } catch (Exception $e) {
        // Step 8: Rollback transaction if any operation fails
        $this->db->rollBack();
        throw $e;
    }
}
```

### 3.2 Change Tracking

```php
// Domain/TemplateHistoryGateway.php
class TemplateHistoryGateway extends QueryableGateway
{
    public function recordChange($templateID, $data, $type = 'Update')
    {
        // Record a change in the template history
        return $this->insert([
            'gibbonReportTemplateID' => $templateID,
            'gibbonPersonID' => $this->session->get('gibbonPersonID'),
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => json_encode($data)
        ]);
    }
    
    public function getHistory($templateID)
    {
        // Fetch the change history for a specific template
        $query = $this
            ->newSelect()
            ->from('gibbonReportTemplateHistory')
            ->cols(['*'])
            ->where('gibbonReportTemplateID=:templateID')
            ->orderBy(['timestamp DESC'])
            ->bindValue('templateID', $templateID);
            
        return $this->runSelect($query);
    }
}
```

## 4. Delete Operations

### 4.1 Soft Delete Implementation

```php
// Domain/TemplateGateway.php
public function softDelete($id)
{
    // Step 1: Begin transaction
    $this->db->beginTransaction();
    
    try {
        // Step 2: Mark the template as deleted
        $updated = parent::update($id, [
            'deleted' => 'Y',
            'deletedTimestamp' => date('Y-m-d H:i:s'),
            'deletedBy' => $this->session->get('gibbonPersonID')
        ]);
        
        // Step 3: Log the deletion for audit purposes
        $auditLog = $this->container->get(AuditLogGateway::class);
        $auditLog->logTemplateAction($id, 'Delete');
        
        // Step 4: Commit the transaction if all operations succeed
        $this->db->commit();
        return $updated;
        
    } catch (Exception $e) {
        // Step 5: Rollback the transaction if any operation fails
        $this->db->rollBack();
        throw $e;
    }
}
```

### 4.2 Cascade Delete

```php
// Domain/TemplateGateway.php
public function hardDelete($id)
{
    // Step 1: Check permissions
    if (!$this->session->has('gibbonRoleIDAdmin')) {
        throw new PermissionException('Only administrators can permanently delete templates');
    }
    
    // Step 2: Begin transaction
    $this->db->beginTransaction();
    
    try {
        // Step 3: Delete related records
        $this->db->delete('gibbonReportTemplateCriteria', ['gibbonReportTemplateID' => $id]);
        $this->db->delete('gibbonReportTemplateAccess', ['gibbonReportTemplateID' => $id]);
        $this->db->delete('gibbonReportTemplateHistory', ['gibbonReportTemplateID' => $id]);
        
        // Step 4: Delete the template itself
        parent::delete($id);
        
        // Step 5: Log the permanent deletion for audit purposes
        $auditLog = $this->container->get(AuditLogGateway::class);
        $auditLog->logTemplateAction($id, 'Permanent Delete');
        
        // Step 6: Commit the transaction if all operations succeed
        $this->db->commit();
        
    } catch (Exception $e) {
        // Step 7: Rollback the transaction if any operation fails
        $this->db->rollBack();
        throw $e;
    }
}
```

### 4.3 Recovery

```php
// Domain/TemplateGateway.php
public function restore($id)
{
    // Step 1: Check if template exists and is deleted
    $template = $this->getByID($id);
    if (empty($template) || $template['deleted'] != 'Y') {
        throw new Exception('Template cannot be restored');
    }
    
    // Step 2: Begin transaction
    $this->db->beginTransaction();
    
    try {
        // Step 3: Restore the template by updating its status
        $updated = parent::update($id, [
            'deleted' => 'N',
            'deletedTimestamp' => null,
            'deletedBy' => null
        ]);
        
        // Step 4: Log the restoration for audit purposes
        $auditLog = $this->container->get(AuditLogGateway::class);
        $auditLog->logTemplateAction($id, 'Restore');
        
        // Step 5: Commit the transaction if all operations succeed
        $this->db->commit();
        return $updated;
        
    } catch (Exception $e) {
        // Step 6: Rollback the transaction if any operation fails
        $this->db->rollBack();
        throw $e;
    }
}
```

## 5. Best Practices

1. **Data Validation**
   - Validate all input data to ensure integrity and security
   - Use type hints and strict types to catch errors early
   - Implement comprehensive validation rules for each field
   - Handle validation errors gracefully and provide clear feedback

2. **Transaction Management**
   - Use transactions for operations that involve multiple database changes
   - Implement proper rollback handling to maintain data consistency
   - Ensure all related operations are included in the transaction
   - Handle potential deadlocks by implementing retry logic where appropriate

3. **Audit Logging**
   - Log all significant operations for accountability and troubleshooting
   - Include relevant user information in log entries
   - Record accurate timestamps for each logged action
   - Store relevant data changes to track the history of modifications

4. **Security**
   - Implement proper access control checks before each operation
   - Use prepared statements to prevent SQL injection attacks
   - Validate user permissions at both the application and database levels
   - Handle sensitive data carefully, using encryption where necessary

5. **Performance**
   - Optimize database queries to reduce execution time and resource usage
   - Use appropriate indexes to speed up data retrieval
   - Implement caching mechanisms for frequently accessed, rarely changed data
   - Regularly monitor and analyze query performance to identify bottlenecks
