# Module 2: Basic Module Development

## Lesson 1: Module Manifest and Configuration

### Understanding manifest.php
- Module metadata
- Version information
- Dependencies
- Actions and permissions setup
- Database table creation
- Module settings

### Example manifest.php Structure
```php
<?php
$name = "Your Module Name";
$description = "A brief description of your module";
$entryURL = "page_that_appears_in_main_menu.php";
$type = "Additional";
$category = "Learn"; // Admin, Assess, Learn, People, Other
$version = "1.0.00";
$author = "Your Name";
$url = "https://github.com/yourusername/your-module";
```

## Lesson 2: Database Integration

### Working with CHANGEDB.php
- Version control for database changes
- Adding new tables
- Modifying existing structures
- Best practices for database updates

### Database Operations
- Creating tables
- Adding indexes
- Relationships with core tables
- Data migration

## Lesson 3: Creating Actions and Pages

### Module Actions
- Defining actions in manifest.php
- Permission levels
- URL routing
- Action categories

### Page Structure
- Standard page layout
- Including core functions
- Navigation breadcrumbs
- Form handling
- Data display

## Lesson 4: Module CSS and JavaScript

### Styling Your Module
- Using module.css
- GibbonEdu styling conventions
- Responsive design considerations
- Theme compatibility

### JavaScript Integration
- module.js implementation
- AJAX interactions
- Form validation
- Dynamic content

## Practical Exercises
1. Create a complete manifest.php for your module
2. Implement a database table with CHANGEDB.php
3. Create a basic CRUD interface
4. Style your module pages

## Additional Resources
- Database schema documentation
- UI/UX guidelines
- Code examples
- Common patterns

## Next Steps
Continue to Module 3 to learn about advanced development techniques, including hooks and domain logic.
