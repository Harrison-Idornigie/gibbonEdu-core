# Introduction to GibbonEdu Module Development

## What is GibbonEdu?

GibbonEdu is a powerful, flexible, and open-source school management system designed to streamline daily operations in educational institutions. It's built using modern PHP practices and follows a modular architecture, making it easy to extend and customize.

### Core Features of GibbonEdu

GibbonEdu comes with a wide range of built-in features to support school management:

- Student Information Management: Centralized storage and management of student data
- Attendance Tracking: Efficient recording and monitoring of student attendance
- Assessment & Reporting: Comprehensive tools for grading and generating report cards
- Timetabling: Flexible scheduling of classes and activities
- Behavior Management: Tracking and responding to student behavior
- Parent Communication: Tools to keep parents informed and engaged
- Resource Management: Organizing and allocating school resources
- Library System: Managing book loans and library resources

## Modern Module Development in GibbonEdu

GibbonEdu leverages contemporary PHP practices and tools to provide a robust development environment. Let's explore some key concepts and examples:

### 1. Object-Oriented Architecture

GibbonEdu uses object-oriented programming (OOP) principles to organize code into reusable, modular components. Here's an example of a modern GibbonEdu class:

```php
// Example of a modern GibbonEdu class
namespace Gibbon\Module\MyModule\Domain;

class StudentManager
{
    protected $gateway;
    protected $validator;

    public function __construct(StudentGateway $gateway, Validator $validator)
    {
        $this->gateway = $gateway;
        $this->validator = $validator;
    }

    public function addStudent(array $data): Result
    {
        // Validation and business logic would go here
        return $this->gateway->insert($data);
    }
}
```

In this example, we define a `StudentManager` class responsible for managing student-related operations. It uses dependency injection (more on this later) to receive its required dependencies (`StudentGateway` and `Validator`) through the constructor.

### 2. Dependency Injection

Dependency Injection (DI) is a design pattern used in GibbonEdu to achieve loose coupling between classes. It allows for easier testing and maintenance. GibbonEdu uses a service container to manage dependencies:

```php
// Modern service container usage
$container = new Container();

// Register the StudentGateway in the container
$container->set(StudentGateway::class, function ($c) {
    return new StudentGateway($c->get('db'));
});

// Retrieve an instance of StudentManager from the container
$manager = $container->get(StudentManager::class);
```

This approach allows GibbonEdu to automatically resolve and inject dependencies when creating objects, making your code more modular and easier to maintain.

### 3. Database Abstraction

GibbonEdu uses a database abstraction layer to simplify database operations and improve security. The `QueryableGateway` class provides a fluent interface for building database queries:

```php
// Modern database interaction using QueryableGateway
class StudentGateway extends QueryableGateway
{
    private static $tableName = 'gibbonStudent';
    private static $primaryKey = 'gibbonStudentID';

    public function selectActiveStudents()
    {
        return $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['gibbonStudentID', 'firstName', 'lastName'])
            ->where('status = :status')
            ->bindValue('status', 'Full');
    }
}
```

This approach allows you to write database queries in a more object-oriented manner, improving readability and reducing the risk of SQL injection vulnerabilities.

### 4. Form Handling

GibbonEdu provides a powerful Form API to simplify the creation and handling of HTML forms:

```php
// Modern form creation using Form API
$form = Form::create('studentAdd', '');
$form->setFactory(DatabaseFormFactory::create($pdo));

$row = $form->addRow();
    $row->addLabel('firstName', __('First Name'))
        ->description(__('Legal first name'))
        ->required();
    $row->addTextField('firstName')
        ->required()
        ->maxLength(30);
```

This code creates a form with a "First Name" field, complete with a label, description, and validation rules. The Form API handles the HTML generation, making it easier to create consistent and accessible forms.

### 5. Table Display

For displaying data in tables, GibbonEdu offers the DataTable class:

```php
// Modern table creation using DataTable
$table = DataTable::create('students');
$table->setTitle(__('Active Students'));

$table->addColumn('fullName', __('Name'))
    ->sortable(['surname', 'preferredName'])
    ->format(function ($person) {
        return Format::name($person['title'], $person['preferredName'], 
                            $person['surname'], 'Student');
    });
```

This code creates a table to display a list of active students. The DataTable class handles pagination, sorting, and formatting, providing a consistent user interface across the platform.

## Why Create Modules for GibbonEdu?

Developing modules for GibbonEdu allows you to extend the platform's functionality without modifying the core system. Here are some key reasons and examples:

### 1. Extend Core Functionality

Modules let you add new features to GibbonEdu. For example, you can add new menu items:

```php
// Example: Adding a new menu item
$actionRows[] = [
    'name' => 'My Feature',
    'precedence' => '0',
    'category' => 'Learning',
    'description' => 'Access my new feature',
    'URLList' => 'myfeature.php',
    'entryURL' => 'myfeature.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y'
];
```

This code adds a new menu item for your module, making it accessible from the main navigation.

### 2. Integrate with Core Systems

Your modules can interact with GibbonEdu's core features. For instance, you can use the notification system:

```php
// Example: Integrating with the notification system
$event = new NotificationEvent('My Module', 'New Update');
$event->setNotificationText('Something important happened!');
$event->setActionLink('/modules/MyModule/view.php');
$event->addRecipient($gibbon->session->get('gibbonPersonID'));
$event->sendNotifications($pdo, $gibbon->session);
```

This code creates and sends a notification using GibbonEdu's built-in notification system.

### 3. Access Core Services

GibbonEdu provides various services that your module can utilize. Here's an example using the mailer service:

```php
// Example: Using the core mailer service
$mail = $container->get(Mailer::class);
$mail->setTemplate('myModule')
     ->subject('Important Update')
     ->to($recipientEmail)
     ->renderBody('myTemplate.twig.html', [
         'userName' => $userName,
         'content' => $content
     ])
     ->send();
```

This code uses GibbonEdu's mailer service to send an email, leveraging existing templates and configuration.

## Best Practices for GibbonEdu Module Development

When developing modules for GibbonEdu, it's important to follow these best practices:

### 1. Use Modern PHP Features

Leverage modern PHP features to write cleaner, more maintainable code:

```php
// Use type hints and return types
public function processStudent(Student $student): Result
{
    // Processing logic here
}

// Use null coalescing operator for default values
$setting = $gibbon->session->get('setting') ?? 'default';
```

### 2. Follow Coding Standards

Adhere to PSR-12 coding standards for consistency:

```php
// Use PSR-12 coding standard
namespace Gibbon\Module\MyModule;

class MyClass
{
    private $property;

    public function myMethod(): void
    {
        // Method implementation
    }
}
```

### 3. Implement Error Handling

Use try-catch blocks for proper error handling:

```php
try {
    $result = $gateway->insert($data);
    $session->addMessage(__('Success'), 'success');
} catch (QueryException $e) {
    $session->addMessage(__('Error'), 'error');
    Log::error('Database error', ['exception' => $e]);
}
```

## Exercise: Plan Your First GibbonEdu Module

To get started with module development, follow these steps:

1. Identify a Need
   - What problem will your module solve?
   - Who are the target users (e.g., teachers, students, administrators)?
   - What core GibbonEdu features will your module interact with?

2. Design the Solution
   Create a basic structure for your module:

```plaintext
My Module
├── Features
│   ├── Core Functionality (What will your module do?)
│   ├── User Interface (How will users interact with your module?)
│   └── Integration Points (How will it connect with other GibbonEdu features?)
├── Data Structure
│   ├── Database Tables (What data will you need to store?)
│   └── Relationships (How does your data relate to existing GibbonEdu data?)
└── User Roles
    ├── Permissions (Who can access what in your module?)
    └── Access Levels (What levels of access are needed?)
```

3. Technical Planning
   - Which GibbonEdu core services will you use (e.g., notification, mailer)?
   - What new classes will you need to create?
   - How will you handle data validation and error checking?

## Next Steps

In the upcoming lessons, we'll dive deeper into setting up a modern development environment for GibbonEdu module development. We'll cover tools, workflows, and best practices to help you build robust and maintainable modules for GibbonEdu.

Stay tuned for hands-on examples and step-by-step guides to bring your module ideas to life!
