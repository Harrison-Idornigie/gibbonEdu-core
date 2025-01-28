# Lesson 2: Version Control

## Git Best Practices

### Branch Management

1. **Branch Naming**
```bash
# Feature branches
feature/equipment-tracking
feature/loan-system

# Bug fix branches
fix/equipment-status-error
fix/loan-date-validation

# Release branches
release/v1.0.0
release/v1.1.0

# Hotfix branches
hotfix/v1.0.1
```

2. **Branch Strategy**
```plaintext
main (stable)
  └── develop
      ├── feature/equipment-tracking
      │   └── feature/equipment-status
      ├── feature/loan-system
      └── fix/validation-error
```

3. **Branch Commands**
```bash
# Create feature branch
git checkout -b feature/equipment-tracking develop

# Update feature branch
git checkout feature/equipment-tracking
git pull origin develop

# Merge feature to develop
git checkout develop
git merge --no-ff feature/equipment-tracking
git push origin develop
```

### Commit Messages

1. **Commit Structure**
```plaintext
<type>(<scope>): <subject>

<body>

<footer>
```

2. **Commit Types**
```plaintext
feat: New feature
fix: Bug fix
docs: Documentation
style: Code style/formatting
refactor: Code refactoring
test: Adding tests
chore: Maintenance tasks
```

3. **Example Commits**
```bash
# Feature commit
git commit -m "feat(equipment): add equipment tracking system

Implement basic equipment tracking functionality:
- Add equipment table
- Create CRUD operations
- Implement search feature

Resolves: #123"

# Bug fix commit
git commit -m "fix(loans): correct date validation

Fix issue with loan dates allowing past dates to be selected.
Update date picker to enforce future dates only.

Fixes: #456"

# Documentation commit
git commit -m "docs(readme): update installation instructions

Add detailed steps for database setup and configuration.
Include troubleshooting section."
```

### Pull Requests

1. **PR Template**
```markdown
## Description
[Description of changes]

## Related Issue
Fixes #[issue]

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Manual testing completed

## Screenshots
[If applicable]

## Checklist
- [ ] Code follows style guidelines
- [ ] Comments added/updated
- [ ] Documentation updated
- [ ] Tests added/updated
- [ ] All tests passing
```

2. **PR Best Practices**
```plaintext
1. Keep PRs focused and small
2. Include tests
3. Update documentation
4. Link related issues
5. Add screenshots for UI changes
6. Respond to review comments
```

### Release Tagging

1. **Version Tags**
```bash
# Create annotated tag
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"

# Push tag
git push origin v1.0.0

# List tags
git tag -l "v*"
```

2. **Release Notes**
```markdown
# Release v1.0.0

## Features
- Equipment tracking system
- Loan management
- Reporting functionality

## Bug Fixes
- Fixed date validation
- Corrected status updates

## Breaking Changes
- Updated database schema
- Modified API endpoints
```

## Version Management

### Semantic Versioning

1. **Version Format**
```plaintext
MAJOR.MINOR.PATCH

Example: 1.2.3
- MAJOR: Breaking changes
- MINOR: New features
- PATCH: Bug fixes
```

2. **Version Implementation**
```php
// manifest.php
$manifest = [
    'name'        => 'Equipment Tracker',
    'description' => 'Track and manage equipment loans',
    'version'     => '1.2.3',
    'author'      => 'Your Name',
    'dependencies' => [
        'core' => '>=23.0.0'
    ]
];
```

### Database Versions

1. **Version Table**
```sql
CREATE TABLE `equipmentTrackerVersion` (
    `version` VARCHAR(10) NOT NULL,
    `installDate` DATETIME NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

2. **Version Updates**
```php
// CHANGEDB.php
$sql = [];
$count = 0;

// Version 1.0.0
$sql[$count][0] = '1.0.0';
$sql[$count][1] = '
CREATE TABLE `equipmentTrackerEquipment` (
    `id` INT(10) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
';

// Version 1.1.0
$count++;
$sql[$count][0] = '1.1.0';
$sql[$count][1] = "
ALTER TABLE `equipmentTrackerEquipment` 
ADD COLUMN `category` VARCHAR(50) NULL AFTER `name`;
";

// Version 1.1.1
$count++;
$sql[$count][0] = '1.1.1';
$sql[$count][1] = "
UPDATE `equipmentTrackerEquipment` 
SET `category` = 'General' 
WHERE `category` IS NULL;
";
```

3. **Update Process**
```php
// Update function in moduleFunctions.php
function updateModule($pdo, $version)
{
    // Get all updates
    include __DIR__ . '/CHANGEDB.php';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Apply each update
        foreach ($sql as $version) {
            $pdo->exec($version[1]);
            
            // Update version
            $stmt = $pdo->prepare('
                INSERT INTO equipmentTrackerVersion 
                SET version=?, installDate=NOW()
            ');
            $stmt->execute([$version[0]]);
        }
        
        // Commit transaction
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
```

### Code Versions

1. **Version Checks**
```php
// Version compatibility check
function isCompatible($coreVersion, $moduleVersion)
{
    return version_compare($coreVersion, $moduleVersion, '>=');
}

// Usage
if (!isCompatible($session->get('version'), '23.0.0')) {
    $page->addError(__('This module requires Gibbon v23.0.0 or higher.'));
    return;
}
```

2. **Feature Flags**
```php
// config.php
$moduleConfig = [
    'features' => [
        'advancedReporting' => true,
        'barcodeScanning' => false,
        'apiAccess' => false
    ]
];

// Usage
if ($moduleConfig['features']['advancedReporting']) {
    // Show advanced reporting options
}
```

### Update Management

1. **Update Checker**
```php
class UpdateChecker
{
    private $currentVersion;
    private $latestVersion;
    
    public function __construct($currentVersion)
    {
        $this->currentVersion = $currentVersion;
    }
    
    public function checkForUpdates()
    {
        // Check for updates
        $this->latestVersion = $this->getLatestVersion();
        
        return [
            'hasUpdate' => $this->hasUpdate(),
            'currentVersion' => $this->currentVersion,
            'latestVersion' => $this->latestVersion
        ];
    }
    
    private function hasUpdate()
    {
        return version_compare(
            $this->currentVersion, 
            $this->latestVersion, 
            '<'
        );
    }
}
```

2. **Update Notification**
```php
// In module admin page
$checker = new UpdateChecker($moduleVersion);
$updateInfo = $checker->checkForUpdates();

if ($updateInfo['hasUpdate']) {
    $page->addWarning(__(
        'A new version ({version}) is available.',
        ['version' => $updateInfo['latestVersion']]
    ));
}
```

## Exercise: Set Up Version Control

1. Initialize Git Repository
```bash
# Initialize
git init

# Add .gitignore
cat > .gitignore << EOL
.DS_Store
/vendor/
/node_modules/
*.log
EOL

# Initial commit
git add .
git commit -m "feat: initial commit

Set up basic module structure with:
- Directory organization
- Base classes
- Configuration files"
```

2. Create Version System
```php
// Create version table
$sql = "CREATE TABLE `moduleVersion` (
    `version` VARCHAR(10) NOT NULL,
    `installDate` DATETIME NOT NULL,
    PRIMARY KEY (`version`)
)";

// Add version tracking
function getModuleVersion($pdo)
{
    $stmt = $pdo->query('
        SELECT version FROM moduleVersion 
        ORDER BY installDate DESC LIMIT 1
    ');
    return $stmt->fetchColumn();
}
```

3. Set Up Branches
```bash
# Create develop branch
git checkout -b develop

# Create feature branch
git checkout -b feature/your-feature

# Work on feature
git add .
git commit -m "feat: implement feature

Add new functionality:
- Feature details
- Related changes

Resolves: #789"
```

## Common Mistakes to Avoid

1. **Poor Commit Messages**
```bash
# Bad
git commit -m "fixed stuff"
git commit -m "updates"

# Good
git commit -m "fix: correct date validation in loan form"
git commit -m "feat: add equipment search functionality"
```

2. **Large Commits**
```bash
# Bad - mixing multiple changes
git commit -m "add features and fix bugs"

# Good - separate commits
git commit -m "feat: add equipment search"
git commit -m "fix: correct status display"
```

3. **Ignoring Version Control**
```php
// Bad - hardcoded version
$version = '1.0.0';

// Good - version from manifest
$version = $manifest['version'];
```

## Next Steps

After completing this lesson:
1. Set up Git repository
2. Create branching strategy
3. Implement version tracking
4. Document update process

In the next lesson, we'll learn about testing your module!
