# Lesson 3: Module Translation

## Internationalization (i18n)

Internationalization is the process of designing your module so it can be adapted to various languages without engineering changes. In GibbonEdu, this means properly managing all text strings to support multiple languages.

### Language File Structure

Create a directory structure for translations:

```plaintext
YourModule/
├── i18n/
│   ├── en_GB/
│   │   └── messages.php
│   ├── es_ES/
│   │   └── messages.php
│   └── zh_CN/
│       └── messages.php
```

### Setting Up Language Files

1. **English (Default) - en_GB/messages.php**
```php
<?php
// Basic string translations
$strings = array(
    // Module information
    'Equipment Tracker' => 'Equipment Tracker',
    
    // Navigation
    'View Equipment' => 'View Equipment',
    'Manage Equipment' => 'Manage Equipment',
    'Equipment Settings' => 'Equipment Settings',
    
    // Form labels
    'Equipment Name' => 'Equipment Name',
    'Serial Number' => 'Serial Number',
    'Condition' => 'Condition',
    'Location' => 'Location',
    'Date Added' => 'Date Added',
    
    // Status messages
    'Equipment added successfully' => 'Equipment added successfully',
    'Failed to add equipment' => 'Failed to add equipment',
    'Equipment updated successfully' => 'Equipment updated successfully',
    'Failed to update equipment' => 'Failed to update equipment',
    
    // Conditions
    'New' => 'New',
    'Good' => 'Good',
    'Fair' => 'Fair',
    'Poor' => 'Poor',
    
    // Complex messages with placeholders
    'Equipment {name} is currently on loan to {student}' => 
        'Equipment {name} is currently on loan to {student}',
    'Due for return on {date}' => 'Due for return on {date}',
    '{count} items are overdue' => '{count} items are overdue',
    
    // Help text
    'equipmentNameHelp' => 'Enter a descriptive name for the equipment',
    'serialNumberHelp' => 'Enter the unique serial number or asset tag',
    'conditionHelp' => 'Select the current condition of the equipment'
);
```

2. **Spanish - es_ES/messages.php**
```php
<?php
$strings = array(
    // Module information
    'Equipment Tracker' => 'Seguimiento de Equipos',
    
    // Navigation
    'View Equipment' => 'Ver Equipos',
    'Manage Equipment' => 'Gestionar Equipos',
    'Equipment Settings' => 'Configuración de Equipos',
    
    // Form labels
    'Equipment Name' => 'Nombre del Equipo',
    'Serial Number' => 'Número de Serie',
    'Condition' => 'Estado',
    'Location' => 'Ubicación',
    'Date Added' => 'Fecha de Registro',
    
    // Status messages
    'Equipment added successfully' => 'Equipo agregado exitosamente',
    'Failed to add equipment' => 'Error al agregar equipo',
    'Equipment updated successfully' => 'Equipo actualizado exitosamente',
    'Failed to update equipment' => 'Error al actualizar equipo',
    
    // Conditions
    'New' => 'Nuevo',
    'Good' => 'Bueno',
    'Fair' => 'Regular',
    'Poor' => 'Malo',
    
    // Complex messages with placeholders
    'Equipment {name} is currently on loan to {student}' => 
        'El equipo {name} está prestado actualmente a {student}',
    'Due for return on {date}' => 'Fecha de devolución: {date}',
    '{count} items are overdue' => '{count} equipos están vencidos',
    
    // Help text
    'equipmentNameHelp' => 'Ingrese un nombre descriptivo para el equipo',
    'serialNumberHelp' => 'Ingrese el número de serie único o etiqueta de activo',
    'conditionHelp' => 'Seleccione el estado actual del equipo'
);
```

### Using Translation Functions

1. **Basic String Translation**
```php
// In your PHP files
echo __('Equipment Name');  // Will output "Equipment Name" or "Nombre del Equipo"

// In forms
$row = $form->addRow();
    $row->addLabel('name', __('Equipment Name'))
        ->description(__('equipmentNameHelp'));
    $row->addTextField('name')
        ->required()
        ->maxLength(50);
```

2. **Translations with Variables**
```php
// Using sprintf
$message = sprintf(
    __('Equipment %1$s is currently on loan to %2$s'),
    $equipmentName,
    Format::name('', $student['preferredName'], $student['surname'], 'Student')
);

// Using named placeholders
$message = Format::text(
    __('Equipment {name} is currently on loan to {student}'),
    [
        'name' => $equipmentName,
        'student' => Format::name('', $student['preferredName'], 
                                $student['surname'], 'Student')
    ]
);
```

3. **Pluralization**
```php
// Create plural forms
$strings['item'] = 'item';
$strings['items'] = 'items';

// Usage
$count = 5;
echo sprintf(
    __('You have %d %s'),
    $count,
    __($count == 1 ? 'item' : 'items')
);
```

### Managing Multiple Languages

1. **Language Detection**
```php
// Get user's preferred language
$lang = $session->get('i18n')['code'] ?? 'en_GB';

// Load appropriate language file
$langFile = __DIR__ . '/i18n/' . $lang . '/messages.php';
if (file_exists($langFile)) {
    include $langFile;
} else {
    // Fall back to English
    include __DIR__ . '/i18n/en_GB/messages.php';
}
```

2. **Language Switching**
```php
// Add language selector to settings
$form = Form::create('languageSettings', '');

$row = $form->addRow();
    $row->addLabel('language', __('Module Language'));
    $row->addSelect('language')
        ->fromArray([
            'en_GB' => 'English',
            'es_ES' => 'Español',
            'zh_CN' => '中文'
        ])
        ->selected($currentLang)
        ->required();
```

## Translation Best Practices

### 1. String Organization

```php
// Group related strings together
$strings = array(
    // Form labels
    'label.name' => 'Name',
    'label.description' => 'Description',
    'label.category' => 'Category',
    
    // Buttons
    'button.save' => 'Save',
    'button.cancel' => 'Cancel',
    'button.delete' => 'Delete',
    
    // Messages
    'message.success.add' => 'Added successfully',
    'message.success.update' => 'Updated successfully',
    'message.success.delete' => 'Deleted successfully',
    
    // Errors
    'error.required' => '{field} is required',
    'error.invalid' => '{field} is invalid',
    'error.duplicate' => '{field} already exists'
);
```

### 2. Context Comments

```php
// Add context for translators
$strings = array(
    // CONTEXT: Used as a label for equipment condition selection
    'condition' => 'Condition',
    
    // CONTEXT: Used when equipment is not available for loan
    'status.unavailable' => 'Unavailable',
    
    // CONTEXT: {name} is the equipment name, {student} is the student's full name
    'loan.current' => 'Equipment {name} is on loan to {student}'
);
```

### 3. Placeholder Usage

```php
// Bad - Hard to translate
$message = 'Equipment ' . $name . ' loaned to ' . $student;

// Good - Flexible word order for different languages
$message = Format::text(
    __('loan.message'),
    [
        'equipment' => $name,
        'student' => $student
    ]
);
```

### 4. Date and Number Formatting

```php
// Use Format class for consistent formatting
$date = Format::date($equipment['dateAdded']);
$currency = Format::currency($equipment['value']);
$number = Format::number($equipment['quantity'], 0);

// Consider locale-specific formats
$dateFormat = $session->get('i18n')['dateFormat'] ?? 'Y-m-d';
$date = Format::date($equipment['dateAdded'], $dateFormat);
```

## Exercise: Add Translation Support

1. Create Language Structure
```plaintext
i18n/
├── en_GB/
│   └── messages.php
└── es_ES/
    └── messages.php
```

2. Add Basic Translations
```php
// en_GB/messages.php
$strings = array(
    // Add your strings
);

// es_ES/messages.php
$strings = array(
    // Add translations
);
```

3. Implement in Code
```php
// Use in forms
$form = Form::create('yourForm', '');
$row = $form->addRow();
    $row->addLabel('name', __('Your Label'));
    $row->addTextField('name');

// Use in templates
echo Format::text(
    __('Your message with {placeholder}'),
    ['placeholder' => $value]
);
```

## Common Mistakes to Avoid

1. **Concatenating Strings**
```php
// Bad
$message = __('Hello') . ' ' . $name . ' ' . __('welcome back');

// Good
$message = Format::text(
    __('Hello {name}, welcome back'),
    ['name' => $name]
);
```

2. **Hard-coding Text**
```php
// Bad
echo 'Equipment not found';

// Good
echo __('error.equipment.notFound');
```

3. **Missing Context**
```php
// Bad - Ambiguous
$strings['open'] = 'Open';

// Good - Clear context
$strings['status.loan.open'] = 'Open';
$strings['button.modal.open'] = 'Open';
```

## Next Steps

After completing this lesson:
1. Plan your translation strategy
2. Create language files
3. Update code to use translation functions
4. Test with different languages

In the next lesson, we'll learn about security and permissions!
