# Comprehensive Guide to Modern Gibbon Module Development

## Introduction to Gibbon Modules

Gibbon is a flexible, open-source school management system. One of its key features is the ability to extend functionality through modules. This guide will walk you through creating a modern, feature-rich Gibbon module using the latest best practices and technologies.

## Getting Started: The Starter Module

To begin our journey, we'll use the Gibbon Starter Module as our foundation. This provides a basic structure that we'll build upon.

### Step 1: Cloning the Starter Module

First, let's get the starter module from GitHub. Open your terminal and run the following commands:

```bash
# Clone the starter module repository
git clone https://github.com/GibbonEdu/module-gibbonStarterModule.git my-first-module

# Navigate into the new module directory
cd my-first-module

# Remove the existing git history (we want to start fresh)
rm -rf .git

# Initialize a new git repository for your module
git init
```

These commands create a copy of the starter module, remove its git history, and set up a new git repository for your custom module.

## Modern Module Development: Creating a "Birthday Reminder" Module

We'll create a "Birthday Reminder" module to demonstrate modern Gibbon features. This module will track birthdays and send notifications.

### 1. Module Structure

A well-organized directory structure is crucial for maintainability. Here's the recommended structure for our Birthday Reminder module:

```plaintext
BirthdayReminder/
├── CHANGELOG.txt           # Records version changes
├── CHANGEDB.php            # Database update scripts
├── LICENSE                 # License information
├── manifest.php            # Module configuration
├── src/                    # Source code directory
│   ├── Domain/             # Business logic
│   │   ├── BirthdayGateway.php
│   │   └── BirthdayNotificationGateway.php
│   └── Forms/              # Form definitions
│       └── NotificationSettingsForm.php
├── templates/              # Twig templates
│   └── reminder.twig.html
├── birthdays_view.php      # Page to view birthdays
├── birthdays_manage.php    # Page to manage birthdays
├── notifications_manage.php # Page to manage notifications
├── moduleFunctions.php     # Shared functions
└── css/module.css          # Module-specific styles
```

This structure separates concerns, making the code more organized and easier to maintain.

### 2. manifest.php: The Module's Configuration File

The `manifest.php` file is crucial - it defines your module's properties and requirements. Here's a modern implementation:

```php
<?php
$name = 'Birthday Reminder';
$description = 'Keep track of student and staff birthdays';
$entryURL = 'birthdays_view.php';
$type = 'Additional';
$category = 'People'; 
$version = '1.0.00';
$author = 'Your Name';
$url = 'https://github.com/yourusername/birthday-reminder';

// Define database tables (using modern charset for international support)
$tables = [
    "CREATE TABLE `moduleBirthdayReminder` (
        `id` INT(10) UNSIGNED ZEROFILL AUTO_INCREMENT,
        `person_id` INT(10) UNSIGNED ZEROFILL,
        `role` ENUM('student', 'staff') DEFAULT 'student',
        `birthday` DATE,
        `notify_days_before` INT(3) DEFAULT 7,
        `last_notification` DATE,
        PRIMARY KEY (`id`),
        UNIQUE KEY `person_id` (`person_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Module settings
$gibbonSetting[] = [
    'scope' => 'Birthday Reminder',
    'name' => 'notificationEmail',
    'nameDisplay' => 'Notification Email',
    'description' => 'Email for birthday notifications',
    'value' => '',
];

// Define module actions and permissions
$actionRows[] = [
    'name' => 'View Birthdays',
    'precedence' => '0',
    'category' => '',
    'description' => 'View upcoming birthdays',
    'URLList' => 'birthdays_view.php',
    'entryURL' => 'birthdays_view.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];
```

This configuration sets up your module's basic information, creates necessary database tables, defines settings, and establishes access permissions.

### 3. Modern Gateway Class: Handling Data Access

Gateways manage database interactions. Here's a modern implementation using Gibbon's QueryableGateway:

```php
<?php
namespace Gibbon\Module\BirthdayReminder\Domain;

use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class BirthdayGateway extends QueryableGateway
{
    private static $tableName = 'moduleBirthdayReminder';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname'];

    public function queryUpcomingBirthdays($criteria, $daysAhead = 30)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'moduleBirthdayReminder.id',
                'moduleBirthdayReminder.birthday',
                'moduleBirthdayReminder.notify_days_before',
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.title',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
                'gibbonRole.category'
            ])
            ->innerJoin('gibbonPerson', 'moduleBirthdayReminder.person_id=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonRole', 'gibbonPerson.gibbonRoleIDPrimary=gibbonRole.gibbonRoleID')
            ->where('gibbonPerson.status = "Full"')
            ->where('(DAYOFYEAR(birthday) >= DAYOFYEAR(CURRENT_DATE) 
                     AND DAYOFYEAR(birthday) <= DAYOFYEAR(CURRENT_DATE + INTERVAL :days DAY))', 
                ['days' => $daysAhead]);

        return $this->runQuery($query, $criteria);
    }
}
```

This Gateway class provides a method to query upcoming birthdays, demonstrating how to construct complex database queries using Gibbon's query builder.

### 4. Modern Form Implementation: User Interaction

Forms are crucial for user input. Here's how to create a form using Gibbon's form API:

```php
<?php
namespace Gibbon\Module\BirthdayReminder\Forms;

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

class NotificationSettingsForm extends Form
{
    protected $factory;

    public function __construct($action, $gateway)
    {
        parent::__construct('notificationSettings', $action);
        $this->factory = DatabaseFormFactory::create($gateway);
        $this->addHiddenValue('address', $_SESSION[$guid]['address']);

        $this->setupForm();
    }

    protected function setupForm()
    {
        $row = $this->addRow()->addClass('align-top');
        
        $col = $row->addColumn()->addClass('flex-1');
        $col->addLabel('notifyDaysBefore', __('Notification Days'))
            ->description(__('Days before birthday to send notification'));
        $col->addNumber('notifyDaysBefore')
            ->setValue('7')
            ->required()
            ->minimum(1)
            ->maximum(30);

        $col = $row->addColumn()->addClass('flex-1');
        $col->addLabel('notificationEmail', __('Notification Email'))
            ->description(__('Email address for notifications'));
        $col->addEmail('notificationEmail')
            ->required()
            ->maxLength(50);

        $row = $this->addRow();
        $row->addFooter();
        $row->addSubmit();
    }
}
```

This form class creates a user-friendly interface for setting notification preferences, showcasing Gibbon's form-building capabilities.

### 5. Modern View Implementation: Displaying Data

Views present data to users. Here's a modern approach using Gibbon's DataTable:

```php
<?php
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\BirthdayReminder\Domain\BirthdayGateway;

// Setup page
$page->breadcrumbs->add(__('View Birthdays'));

if (!isActionAccessible($guid, $connection2, '/modules/Birthday Reminder/birthdays_view.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get gateway instance
    $birthdayGateway = $container->get(BirthdayGateway::class);

    // Add today's birthdays notification
    $criteria = new QueryCriteria();
    $todaysBirthdays = $birthdayGateway->queryUpcomingBirthdays($criteria, 0);
    
    if ($todaysBirthdays->getResultCount() > 0) {
        $names = array_map(function($person) {
            return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff');
        }, $todaysBirthdays->toArray());
        
        $session->addMessage(sprintf(__('Today\'s Birthdays: %s'), implode(', ', $names)), 'success');
    }

    // Create table
    $table = DataTable::create('birthdays');
    $table->setTitle(__('Upcoming Birthdays'));

    $table->addMetaData('filterOptions', [
        'role:student'    => __('Role').': '.__('Student'),
        'role:staff'      => __('Role').': '.__('Staff'),
    ]);

    // Define columns
    $table->addColumn('fullName', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($person) {
            return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff');
        });
    
    $table->addColumn('birthday', __('Birthday'))
        ->format(Format::using('date', ['birthday']));
    
    $table->addColumn('daysUntil', __('Days Until'))
        ->format(function($person) {
            return Format::daysTo($person['birthday']);
        });

    // Add action column
    $table->addActionColumn()
        ->addParam('gibbonPersonID')
        ->format(function ($person, $actions) use ($guid) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/User Admin/user_manage_edit.php');
        });

    // Render table
    echo $table->render($birthdayGateway->queryUpcomingBirthdays($criteria, 30));
}
```

This code creates a dynamic table of upcoming birthdays, demonstrating how to use Gibbon's DataTable for presenting data effectively.

### 6. Modern Notification System: Keeping Users Informed

Gibbon provides a powerful notification system. Here's how to use it:

```php
<?php
use Gibbon\Comms\NotificationEvent;

// Create a notification event
$event = new NotificationEvent('Birthday Reminder', 'Upcoming Birthday');
$event->setNotificationText(sprintf(__('Upcoming birthday for %s on %s'), $personName, Format::date($birthday)));
$event->setActionLink('/modules/Birthday Reminder/birthdays_view.php');

// Add people to notify
$event->addRecipient($recipientPersonID);

// Send notification
$event->sendNotifications($pdo, $gibbon->session);
```

This code creates and sends a birthday notification, showcasing Gibbon's built-in notification capabilities.

## Exercise: Extending the Module

To further enhance your module, consider adding these advanced features:

1. RESTful API endpoint using Slim framework
2. Background notification job using CLI command
3. Unit tests using PHPUnit
4. Frontend using Vue.js components

Here are examples to get you started:

### 1. Creating a RESTful API Endpoint

Using the Slim framework, you can easily add API endpoints:

```php
<?php
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app->get('/api/birthdays', function (Request $request, Response $response) {
    $gateway = $this->get(BirthdayGateway::class);
    $birthdays = $gateway->queryUpcomingBirthdays(new QueryCriteria());
    
    return $response->withJson($birthdays->toArray());
});
```

This creates a `/api/birthdays` endpoint that returns upcoming birthdays in JSON format.

### 2. Implementing a CLI Command for Background Jobs

For tasks like sending notifications, a CLI command is useful:

```php
<?php
namespace Gibbon\Module\BirthdayReminder\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendBirthdayNotifications extends Command
{
    protected function configure()
    {
        $this->setName('birthdays:notify')
             ->setDescription('Send birthday notifications');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Implement notification logic here
        $output->writeln('Sending birthday notifications...');
        
        // Your code to fetch upcoming birthdays and send notifications
        
        $output->writeln('Notifications sent successfully!');
        return Command::SUCCESS;
    }
}
```

This command can be scheduled to run daily, ensuring timely birthday notifications.

## Conclusion

Congratulations! You've now learned the fundamentals of modern Gibbon module development. This guide covered:

- Setting up a module structure
- Configuring the module with `manifest.php`
- Implementing data access with Gateway classes
- Creating user interfaces with Forms and DataTables
- Utilizing Gibbon's notification system
- Adding advanced features like API endpoints and CLI commands

Remember, the key to great module development is understanding Gibbon's architecture and leveraging its built-in features. As you continue to develop, always refer to Gibbon's documentation and community resources for the latest best practices and features.

Happy coding, and enjoy building powerful modules for Gibbon!
