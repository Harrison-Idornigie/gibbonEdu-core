# Internationalization (i18n) in GibbonEdu

This comprehensive guide outlines the implementation of internationalization (i18n) in GibbonEdu modules, based on the core system's approach.

## 1. Language System Overview

GibbonEdu leverages the GNU gettext system for translations, managed through the System Admin module. The key components of this system include:

1. Language files: Stored in `/i18n/{locale}/` directories
2. Locale class: Handles translation functionality
3. System Admin interface: For managing languages
4. Translation string management: Organized by modules

## 2. Detailed Implementation

### 2.1 Using Translations

The primary method for translating strings is the `__()` function. Here are examples of its usage:

```php
// Basic translation
echo __('Welcome to our school');

// Translation with variable substitution
echo __('Hello {name}!', ['name' => $userName]);

// Translation with context (useful for words with multiple meanings)
echo __('Term', ['context' => 'Academic']);
```

### 2.2 Language File Structure

Language files follow a specific directory structure:

```
/i18n/
    /en_GB/           # British English
        LC_MESSAGES/
            gibbon.po # Translation file (editable)
            gibbon.mo # Compiled translations (binary)
    /zh_CN/           # Simplified Chinese
        LC_MESSAGES/
            gibbon.po
            gibbon.mo
```

### 2.3 Module-Specific Translations

For translations specific to your module:

1. Create a language folder within your module:
```
/modules/MyModule/
    /i18n/
        /en_GB/
            /LC_MESSAGES/
                mymodule.po
                mymodule.mo
```

2. Register your module's translation domain in the manifest:

```php
// manifest.php
$I18N = [
    'sourceModuleName' => 'My Module',
    'stringPrefix' => 'mymodule',
    'files' => ['mymodule.po']
];
```

## 3. Translation Management

### 3.1 Managing Languages via System Admin

The System Admin module provides interfaces for:

1. Installing new languages (`i18n_manage_install.php`)
2. Updating existing languages (`i18n_manage_updateAll.php`)
3. Managing active languages (`i18n_manage.php`)

Example of language management interface:

```php
// modules/System Admin/i18n_manage.php
$i18nGateway = $container->get(I18nGateway::class);

// Retrieve installed languages
$languages = $i18nGateway->queryI18n($criteria, 'Y');

// Create and populate the language management interface
$table = DataTable::create('i18n_installed');
$table->addColumn('name', __('Name'));
$table->addColumn('code', __('Code'));
$table->addColumn('version', __('Version'));
// ... additional columns and actions ...
```

### 3.2 Translation File Format

Create translation files using standard gettext format. Here's an example:

```po
# mymodule.po
msgid ""
msgstr ""
"Project-Id-Version: Gibbon\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"

# Simple string translation
msgid "Welcome to our school"
msgstr "欢迎来到我们学校"

# String with variable
msgid "Hello {name}!"
msgstr "你好 {name}！"

# String with context
msgctxt "Academic"
msgid "Term"
msgstr "学期"
```

### 3.3 Locale Class Implementation

The core `Locale` class manages translation functionality:

```php
// src/Gibbon/Locale.php
class Locale implements LocaleInterface
{
    protected $i18ncode;
    protected $absolutePath;
    protected $stringReplacements;
    
    public function __construct($absolutePath, SessionInterface $session)
    {
        $this->absolutePath = $absolutePath;
        $this->i18ncode = $session->get('i18n');
        
        // Initialize gettext
        bindtextdomain('gibbon', $this->absolutePath.'/i18n');
        textdomain('gibbon');
        bind_textdomain_codeset('gibbon', 'UTF-8');
    }
    
    public function translate($text, $args = [])
    {
        // Retrieve translation
        $translated = dgettext('gibbon', $text);
        
        // Replace variables if present
        if (!empty($args)) {
            $translated = $this->replaceParameters($translated, $args);
        }
        
        return $translated;
    }
}
```

## 4. Best Practices for i18n Implementation

1. **Effective String Management**
   - Use descriptive and context-aware string IDs
   - Provide additional context for ambiguous terms
   - Keep strings concise and clear
   - Use consistent placeholder syntax (e.g., `{variableName}`)

2. **Optimal Translation File Organization**
   - Organize translations by module or component
   - Include contextual comments for translators
   - Adhere to standard gettext format
   - Ensure all files are UTF-8 encoded

3. **Code Organization for i18n**
   - Centralize string definitions when possible
   - Use translation functions consistently throughout the codebase
   - Avoid string concatenation in favor of placeholders
   - Implement proper pluralization handling

4. **Performance Considerations**
   - Regularly compile .mo files from .po files
   - Implement caching for frequently used translations
   - Load only the necessary translation domains
   - Optimize string replacement operations

5. **Ongoing Maintenance**
   - Schedule regular translation updates
   - Use version control for translation files
   - Provide comprehensive documentation for translators
   - Conduct thorough testing across different locales

## 5. Comprehensive Implementation Example

Here's a detailed example of implementing translations in a module:

```php
// modules/MyModule/moduleFunctions.php

// Import the translation function
use function Gibbon\Functions\__; 

/**
 * Displays a localized welcome message
 *
 * @param string $name The user's name
 * @return string HTML content with translated strings
 */
function displayWelcome($name)
{
    // Basic translation
    $welcome = __('Welcome to our school');
    
    // Translation with variable replacement
    $greeting = __('Hello {name}!', ['name' => $name]);
    
    // Translation with context for potentially ambiguous terms
    $term = __('Term', ['context' => 'Academic']);
    
    // Complex translation with multiple replacements and nested translations
    $currentTerm = __('Current {context}: {term}', [
        'context' => __('Academic Term'),
        'term' => $term
    ]);
    
    // Construct and return the HTML with translated strings
    return "
        <div class='welcome'>
            <h1>{$welcome}</h1>
            <p>{$greeting}</p>
            <p>{$currentTerm}</p>
        </div>
    ";
}
```

This example demonstrates various translation techniques, including basic translation, variable replacement, context-aware translation, and complex nested translations.
