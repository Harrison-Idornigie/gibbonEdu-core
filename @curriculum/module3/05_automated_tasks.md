# Lesson 5: Automated Tasks

## Understanding CLI Scripts

GibbonEdu provides a CLI (Command Line Interface) system for running automated tasks. These scripts can be scheduled using cron jobs to perform regular tasks like sending emails, generating reports, or cleaning up old data.

### Basic CLI Script Structure

1. **CLI Script Setup**
```php
<?php
// cli/notifications.php

// Require Gibbon core
require_once '../../gibbon.php';

// Ensure this is a CLI request
if (php_sapi_name() != 'cli') {
    die('This script can only be run from the command line.');
}

// Set up database connection
$pdo = $container->get('db');
```

2. **Task Implementation**
```php
class DailyNotificationTask
{
    protected $pdo;
    protected $session;
    protected $mailer;
    
    public function __construct($pdo, $session, $mailer)
    {
        $this->pdo = $pdo;
        $this->session = $session;
        $this->mailer = $mailer;
    }
    
    public function execute()
    {
        try {
            // Get overdue equipment
            $overdueLoans = $this->getOverdueLoans();
            
            // Send notifications
            foreach ($overdueLoans as $loan) {
                $this->sendOverdueNotification($loan);
            }
            
            echo "Successfully sent " . count($overdueLoans) . " overdue notifications.\n";
            return true;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    protected function getOverdueLoans()
    {
        $sql = "SELECT l.*, e.name as equipmentName, 
                       p.preferredName, p.surname, p.email
                FROM equipmentTrackerLoans l
                JOIN equipmentTrackerEquipment e ON l.equipmentID = e.id
                JOIN gibbonPerson p ON l.gibbonPersonID = p.gibbonPersonID
                WHERE l.dateReturn < CURDATE()
                AND l.dateReturned IS NULL
                AND l.reminderSent = 'N'";
                
        return $this->pdo->select($sql);
    }
    
    protected function sendOverdueNotification($loan)
    {
        // Prepare email content
        $subject = 'Overdue Equipment Reminder';
        $body = sprintf(
            "Dear %s %s,\n\nThis is a reminder that %s was due for return on %s.\n\nPlease return this equipment as soon as possible.",
            $loan['preferredName'],
            $loan['surname'],
            $loan['equipmentName'],
            $loan['dateReturn']
        );
        
        // Send email
        $this->mailer->send(
            $loan['email'],
            $subject,
            $body
        );
        
        // Update reminder status
        $sql = "UPDATE equipmentTrackerLoans 
                SET reminderSent = 'Y' 
                WHERE id = :loanID";
        
        $this->pdo->update($sql, ['loanID' => $loan['id']]);
    }
}

// Execute task
$task = new DailyNotificationTask($pdo, $session, $container->get('mailer'));
$success = $task->execute();

exit($success ? 0 : 1);
```

## Setting Up Cron Jobs

### 1. Cron Job Configuration

```bash
# /etc/cron.d/gibbonedu-tasks

# Run daily at 6 AM
0 6 * * * www-data php /path/to/gibbon/modules/Equipment\ Tracker/cli/notifications.php >> /var/log/gibbon/cron.log 2>&1

# Run weekly on Sunday at midnight
0 0 * * 0 www-data php /path/to/gibbon/modules/Equipment\ Tracker/cli/weekly_report.php >> /var/log/gibbon/cron.log 2>&1

# Run monthly on the 1st at 1 AM
0 1 1 * * www-data php /path/to/gibbon/modules/Equipment\ Tracker/cli/monthly_cleanup.php >> /var/log/gibbon/cron.log 2>&1
```

### 2. Task Registration

```php
// manifest.php
$tasks = [
    [
        'name' => 'Daily Overdue Notifications',
        'description' => 'Sends notifications for overdue equipment',
        'schedule' => 'daily',
        'script' => 'cli/notifications.php',
        'arguments' => ''
    ],
    [
        'name' => 'Weekly Equipment Report',
        'description' => 'Generates and emails weekly equipment status report',
        'schedule' => 'weekly',
        'script' => 'cli/weekly_report.php',
        'arguments' => ''
    ]
];
```

## Task Types

### 1. Email Notifications
```php
class EmailNotificationTask
{
    public function sendDailyDigest()
    {
        // Get daily activity
        $activities = $this->getDailyActivities();
        
        // Group by user
        $userDigests = $this->groupByUser($activities);
        
        // Send digests
        foreach ($userDigests as $userID => $digest) {
            $this->sendDigestEmail($userID, $digest);
        }
    }
    
    protected function getDailyActivities()
    {
        $sql = "SELECT * FROM equipmentTrackerActivity 
                WHERE DATE(timestamp) = CURDATE()
                ORDER BY timestamp DESC";
                
        return $this->pdo->select($sql);
    }
}
```

### 2. Data Cleanup
```php
class DataCleanupTask
{
    public function cleanupOldRecords()
    {
        // Archive old loans
        $this->archiveOldLoans();
        
        // Remove old logs
        $this->removeOldLogs();
        
        // Clean temporary files
        $this->cleanTempFiles();
    }
    
    protected function archiveOldLoans()
    {
        $sql = "INSERT INTO equipmentTrackerLoansArchive 
                SELECT * FROM equipmentTrackerLoans 
                WHERE dateReturned < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                
        $this->pdo->execute($sql);
        
        $sql = "DELETE FROM equipmentTrackerLoans 
                WHERE dateReturned < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                
        $this->pdo->execute($sql);
    }
}
```

### 3. Report Generation
```php
class ReportGenerationTask
{
    public function generateWeeklyReport()
    {
        // Generate report data
        $data = $this->collectReportData();
        
        // Create PDF
        $pdf = $this->createPDF($data);
        
        // Save report
        $filename = 'equipment_report_' . date('Y-m-d') . '.pdf';
        $pdf->save("/path/to/reports/{$filename}");
        
        // Email to administrators
        $this->emailReport($filename);
    }
    
    protected function collectReportData()
    {
        return [
            'totalEquipment' => $this->getTotalEquipment(),
            'activeLoans' => $this->getActiveLoans(),
            'overdueItems' => $this->getOverdueItems(),
            'popularItems' => $this->getPopularItems()
        ];
    }
}
```

## Error Handling and Logging

### 1. Error Logging
```php
class TaskLogger
{
    protected $logFile;
    
    public function __construct($logFile)
    {
        $this->logFile = $logFile;
    }
    
    public function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );
        
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
}

// Usage in task
try {
    $logger = new TaskLogger('/var/log/gibbon/equipment.log');
    $logger->log('Starting daily notification task');
    
    // Task execution
    
    $logger->log('Task completed successfully');
} catch (Exception $e) {
    $logger->log($e->getMessage(), 'ERROR');
    throw $e;
}
```

### 2. Task Monitoring
```php
class TaskMonitor
{
    public function recordExecution($taskName)
    {
        $sql = "INSERT INTO equipmentTrackerTaskLog 
                (taskName, startTime, status) 
                VALUES (:task, NOW(), 'Running')";
                
        return $this->pdo->insert($sql, ['task' => $taskName]);
    }
    
    public function updateStatus($logID, $status, $message = '')
    {
        $sql = "UPDATE equipmentTrackerTaskLog 
                SET status = :status, 
                    message = :message,
                    endTime = NOW() 
                WHERE id = :logID";
                
        $this->pdo->update($sql, [
            'logID' => $logID,
            'status' => $status,
            'message' => $message
        ]);
    }
}
```

## Best Practices

1. **Task Organization**
   - Keep CLI scripts in a dedicated directory
   - Use clear naming conventions
   - Document task purpose and schedule

2. **Error Handling**
   - Implement comprehensive logging
   - Send failure notifications
   - Use appropriate exit codes

3. **Performance**
   - Process in batches
   - Use transactions for data integrity
   - Implement timeouts

4. **Security**
   - Restrict CLI script access
   - Validate input data
   - Use secure file permissions

## Exercise: Create Automated Task

1. Create Basic Task
```php
// cli/your_task.php
<?php
require_once '../../gibbon.php';

class YourTask
{
    public function execute()
    {
        // Implement task
    }
}

$task = new YourTask();
$task->execute();
```

2. Set Up Cron Job
```bash
# Add to crontab
0 0 * * * php /path/to/your_task.php
```

3. Add Logging
```php
// Add logging to your task
$logger = new TaskLogger('your_task.log');
$logger->log('Task started');
```

## Common Mistakes to Avoid

1. **Poor Error Handling**
```php
// Bad
function runTask() {
    // No error handling
    doSomething();
}

// Good
function runTask() {
    try {
        doSomething();
    } catch (Exception $e) {
        logError($e);
        notifyAdmin($e);
        return false;
    }
}
```

2. **Resource Management**
```php
// Bad - no cleanup
function processFiles() {
    $handle = fopen('large_file.txt', 'r');
    // Process file
}

// Good - proper cleanup
function processFiles() {
    $handle = fopen('large_file.txt', 'r');
    try {
        // Process file
    } finally {
        fclose($handle);
    }
}
```

3. **Task Dependencies**
```php
// Bad - assuming dependencies
function sendEmails() {
    $mailer->send(); // Might not be initialized
}

// Good - checking dependencies
function sendEmails() {
    if (!$this->mailer) {
        throw new RuntimeException('Mailer not configured');
    }
    $this->mailer->send();
}
```

## Next Steps

After completing this lesson:
1. Review your module's automation needs
2. Implement necessary CLI scripts
3. Set up appropriate cron jobs
4. Test automated processes

Continue to Module 4 to learn about testing and documentation!
