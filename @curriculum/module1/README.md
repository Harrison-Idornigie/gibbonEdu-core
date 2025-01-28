# Module 1: Getting Started

## Lesson 1: Introduction to GibbonEdu

### What is GibbonEdu?
GibbonEdu is a flexible, open-source school platform designed to make life better for teachers, students, parents and others involved in school life. The platform can be extended through modules, which add new functionality to meet specific school needs.

### Why Create Modules?
- Extend platform functionality
- Customize for specific needs
- Contribute to the community
- Maintain upgradability without core modifications

## Lesson 2: Development Environment Setup

### Requirements
1. Local development server (e.g., XAMPP, MAMP)
2. PHP 7.3 or higher
3. MySQL/MariaDB
4. Git for version control
5. Code editor (VSCode recommended)

### Setup Steps
1. Clone GibbonEdu repository
2. Install dependencies
3. Configure development environment
4. Set up database

## Lesson 3: Understanding Module Structure

### Core Module Components
- `manifest.php`: Module configuration and setup
- `CHANGEDB.php`: Database version control
- `CHANGELOG.txt`: Version history
- `version.php`: Code version tracking
- Module-specific files:
  - CSS (`css/module.css`)
  - JavaScript (`js/module.js`)
  - Images (`img/`)
  - Domain logic (`src/Domain/`)

## Lesson 4: The Starter Module Template

### Getting Started with the Template
1. Download the [starter module](https://github.com/GibbonEdu/module-gibbonStarterModule)
2. Understanding each component
3. Customizing the template
4. Basic configuration

### Practical Exercise
Create your first basic module using the starter template:
1. Clone the starter module
2. Modify manifest.php with your module details
3. Create a simple landing page
4. Test installation and basic functionality

## Additional Resources
- [GibbonEdu Documentation](https://docs.gibbonedu.org)
- [Developer Forums](https://ask.gibbonedu.org)
- [GitHub Repository](https://github.com/GibbonEdu)

## Next Steps
Once you're comfortable with the basics, proceed to Module 2 where we'll dive into actual module development and creating functional features.
