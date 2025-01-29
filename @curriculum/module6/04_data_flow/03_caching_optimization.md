# Caching and Optimization

Comprehensive guide to implementing caching and optimization strategies in GibbonEdu modules.

## 1. Template Caching

Efficient template caching reduces rendering time and server load.

### 1.1 Twig Template Caching

Implement Twig caching to store compiled templates:

```php
// config.php
// Set up Twig caching for improved performance
$twig->setCache(new FilesystemCache('/path/to/cache'));

// Enable auto-reload during development for easier debugging
$twig->setAutoReload(true);

// Optimize for production environment
if (ENVIRONMENT === 'production') {
    // Disable auto-reload to maximize performance
    $twig->setAutoReload(false);
    
    // Use bytecode cache for faster template loading
    $twig->setCache(new FilesystemCache('/path/to/cache', FilesystemCache::FORCE_BYTECODE_INVALIDATION));
}
```

### 1.2 Fragment Caching

Implement fragment caching for reusable template sections:

```php
// Domain/CacheManager.php
class CacheManager
{
    private $cache;
    
    public function __construct()
    {
        // Initialize cache adapter for report fragments
        $this->cache = new FilesystemAdapter('reports', 0, '/path/to/cache');
    }
    
    /**
     * Retrieve a cached template fragment or generate if not exists
     *
     * @param int $templateID The ID of the template
     * @param string $section The specific section of the template
     * @return string The cached or freshly generated template fragment
     */
    public function getTemplateFragment($templateID, $section)
    {
        $key = sprintf('template_%d_%s', $templateID, $section);
        
        return $this->cache->get($key, function(ItemInterface $item) use ($templateID, $section) {
            // Set cache expiration time
            $item->expiresAfter(3600); // Cache for 1 hour
            
            // Generate the fragment if not in cache
            $gateway = $this->container->get(TemplateGateway::class);
            return $gateway->getTemplateSection($templateID, $section);
        });
    }
    
    /**
     * Invalidate all cached fragments for a specific template
     *
     * @param int $templateID The ID of the template to invalidate
     */
    public function invalidateTemplate($templateID)
    {
        // Use wildcard to delete all related cache items
        $this->cache->delete(sprintf('template_%d_*', $templateID));
    }
}
```

## 2. Query Optimization

Optimize database queries to improve response times and reduce server load.

### 2.1 Query Caching

Cache frequently used query results:

```php
// Domain/TemplateGateway.php
/**
 * Retrieve active templates for a school year, with caching
 *
 * @param int $schoolYearID The ID of the school year
 * @return array List of active templates
 */
public function getActiveTemplates($schoolYearID)
{
    $cacheKey = sprintf('active_templates_%d', $schoolYearID);
    
    return $this->cache->get($cacheKey, function(ItemInterface $item) use ($schoolYearID) {
        // Cache for a short time to balance freshness and performance
        $item->expiresAfter(300); // 5 minutes
        
        // Fetch active templates from the database
        return $this->selectWhere([
            'gibbonSchoolYearID' => $schoolYearID,
            'active' => 'Y'
        ]);
    });
}
```

### 2.2 Eager Loading

Implement eager loading to reduce the number of database queries:

```php
// Domain/TemplateGateway.php
/**
 * Retrieve a template with its related data in a single query
 *
 * @param int $templateID The ID of the template to retrieve
 * @return array Template data with related information
 */
public function getTemplateWithRelations($templateID)
{
    $query = $this
        ->newSelect()
        ->from($this->getTableName())
        ->cols([
            'gibbonReportTemplate.*',
            'GROUP_CONCAT(DISTINCT gibbonReportTemplateCriteria.name) as criteriaNames',
            'COUNT(DISTINCT gibbonReportTemplateAccess.gibbonPersonID) as accessCount'
        ])
        ->leftJoin('gibbonReportTemplateCriteria', 'gibbonReportTemplateCriteria.gibbonReportTemplateID=gibbonReportTemplate.gibbonReportTemplateID')
        ->leftJoin('gibbonReportTemplateAccess', 'gibbonReportTemplateAccess.gibbonReportTemplateID=gibbonReportTemplate.gibbonReportTemplateID')
        ->where('gibbonReportTemplate.gibbonReportTemplateID=:templateID')
        ->groupBy(['gibbonReportTemplate.gibbonReportTemplateID'])
        ->bindValue('templateID', $templateID);
        
    return $this->runSelect($query)->fetch();
}
```

## 3. Memory Management

Efficiently manage memory to handle large datasets and prevent out-of-memory errors.

### 3.1 Batch Processing

Process large datasets in smaller chunks:

```php
// Domain/ReportGenerator.php
/**
 * Generate reports for multiple students in batches
 *
 * @param int $templateID The ID of the report template
 * @param array $studentIDs Array of student IDs
 */
public function generateBatchReports($templateID, array $studentIDs)
{
    // Process in chunks to manage memory efficiently
    $chunks = array_chunk($studentIDs, 50);
    
    foreach ($chunks as $chunk) {
        // Generate reports for this chunk of students
        foreach ($chunk as $studentID) {
            $this->generateReport($templateID, $studentID);
        }
        
        // Clear entity manager to free memory after each chunk
        $this->em->clear();
        // Manually trigger garbage collection
        gc_collect_cycles();
    }
}
```

### 3.2 Resource Cleanup

Ensure proper cleanup of resources to prevent memory leaks:

```php
// Domain/PDFGenerator.php
class PDFGenerator
{
    private $tempFiles = [];
    
    /**
     * Clean up temporary files on object destruction
     */
    public function __destruct()
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Generate a PDF file from a template and data
     *
     * @param array $template The report template
     * @param array $data The data to populate the template
     * @return string Path to the generated PDF file
     * @throws Exception If PDF generation fails
     */
    public function generatePDF($template, $data)
    {
        // Create a temporary file for the PDF
        $tempFile = tempnam(sys_get_temp_dir(), 'report_');
        $this->tempFiles[] = $tempFile;
        
        try {
            // Generate PDF using TCPDF
            $pdf = new TCPDF();
            // ... PDF generation code ...
            $pdf->Output($tempFile, 'F');
            
            return $tempFile;
        } catch (Exception $e) {
            // Clean up the temporary file if an error occurs
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }
}
```

## 4. Performance Monitoring

Implement tools to monitor and analyze system performance.

### 4.1 Query Logging

Log and analyze database queries for performance optimization:

```php
// Domain/QueryLogger.php
class QueryLogger
{
    private $log = [];
    
    /**
     * Log a database query
     *
     * @param string $sql The SQL query
     * @param array $params Query parameters
     * @param float $duration Query execution time in seconds
     */
    public function logQuery($sql, $params, $duration)
    {
        $this->log[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Retrieve slow queries exceeding a specified duration threshold
     *
     * @param float $threshold Duration threshold in seconds
     * @return array Slow queries
     */
    public function getSlowQueries($threshold = 1.0)
    {
        return array_filter($this->log, function($entry) use ($threshold) {
            return $entry['duration'] >= $threshold;
        });
    }
}
```

### 4.2 Performance Metrics

Track and analyze various performance metrics:

```php
// Domain/PerformanceMonitor.php
class PerformanceMonitor
{
    private $metrics = [];
    private $startTimes = [];
    
    /**
     * Start timing an operation
     *
     * @param string $name Name of the operation
     */
    public function startOperation($name)
    {
        $this->startTimes[$name] = microtime(true);
    }
    
    /**
     * End timing an operation and record its duration
     *
     * @param string $name Name of the operation
     */
    public function endOperation($name)
    {
        if (!isset($this->startTimes[$name])) {
            return;
        }
        
        $duration = microtime(true) - $this->startTimes[$name];
        
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = [
                'count' => 0,
                'total_time' => 0,
                'max_time' => 0
            ];
        }
        
        $this->metrics[$name]['count']++;
        $this->metrics[$name]['total_time'] += $duration;
        $this->metrics[$name]['max_time'] = max($this->metrics[$name]['max_time'], $duration);
        
        unset($this->startTimes[$name]);
    }
    
    /**
     * Get collected performance metrics with average time calculation
     *
     * @return array Performance metrics
     */
    public function getMetrics()
    {
        return array_map(function($metric) {
            $metric['avg_time'] = $metric['total_time'] / $metric['count'];
            return $metric;
        }, $this->metrics);
    }
}
```

## 5. Best Practices

Implement these best practices to ensure optimal performance and maintainability:

1. **Cache Management**
   - Choose appropriate cache drivers based on your infrastructure and requirements
   - Set reasonable expiration times to balance data freshness and performance
   - Implement a robust cache invalidation strategy to ensure data consistency
   - Regularly monitor cache hit rates to optimize caching strategies

2. **Query Optimization**
   - Use database indexes effectively to speed up data retrieval
   - Implement eager loading to reduce the number of database queries
   - Avoid N+1 query problems by optimizing related data fetching
   - Use query caching judiciously, considering data volatility

3. **Memory Management**
   - Process large datasets in smaller chunks to avoid memory exhaustion
   - Properly clean up resources, especially when dealing with file operations
   - Regularly monitor memory usage in your application
   - Utilize garbage collection when appropriate to free up memory

4. **Performance Monitoring**
   - Implement logging for slow queries to identify performance bottlenecks
   - Track key performance metrics to gain insights into system behavior
   - Set up monitoring alerts to proactively address performance issues
   - Conduct regular performance reviews to continuously improve your system

5. **Development Practices**
   - Profile code in development environments to identify performance issues early
   - Utilize debugging tools to diagnose and resolve performance problems
   - Maintain comprehensive documentation of optimization strategies
   - Conduct regular performance testing, especially before major releases

By following these best practices and implementing the provided code examples, you can significantly improve the performance and efficiency of your GibbonEdu modules.
