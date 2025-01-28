# Setting Up Your Development Environment for GibbonEdu Module Development

Welcome to the world of GibbonEdu module development! This guide will walk you through setting up your development environment step-by-step. We'll cover everything you need to get started, from installing the necessary software to configuring your local server.

## System Requirements

Before we begin, ensure your system meets these requirements:

### Server Requirements
- Apache 2 or Nginx with mod_rewrite enabled
- HTTPS setup (recommended for production) via SSL Certificate
- Support for Linux servers (recommended)

### PHP Requirements
- PHP 7.4 or higher (PHP 8.x supported)
- Required PHP Extensions:
  - gettext: for internationalization
  - mbstring: for multi-byte string handling
  - curl: for making HTTP requests
  - zip: for package handling
  - xml: for XML processing
  - gd: for image processing
  - intl: for internationalization
- Recommended PHP Settings:
  - display_errors = Off (in production)
  - max_input_vars = 5000
  - post_max_size = 20M
  - upload_max_filesize = 20M
  - memory_limit = 128M

### Database Requirements
- MySQL 5.7 or higher (or comparable MariaDB version)
- Database collation: utf8_general_ci or utf8mb3_general_ci
- InnoDB storage engine (recommended)

## Required Software

Let's set up the foundational software you'll need for web development.

### 1. Local Server Stack Options

You have several options for your local development environment:

#### Option 1: XAMPP (Recommended for Beginners)
- Free and open-source
- Includes Apache, MySQL, PHP, and phpMyAdmin
- Easy to install and configure
- Download from [Apache Friends website](https://www.apachefriends.org/)

#### Option 2: Docker (Recommended for Advanced Users)
- Consistent development environment
- Easy to match production environment
- Better isolation of services
- We'll provide Docker setup instructions in a separate guide

### 2. Git Version Control

Git is essential for modern software development and contributing to GibbonEdu.

#### Installation Instructions

For Ubuntu/Debian Linux:
```bash
# Update your package list
sudo apt-get update

# Install Git
sudo apt-get install git
```

For macOS (using Homebrew):
```bash
# If you don't have Homebrew, install it from https://brew.sh/
# Then run:
brew install git
```

For Windows:
1. Download the installer from https://git-scm.com/download/win
2. Run the installer, accepting the default options unless you have specific preferences

### 3. Code Editor: Visual Studio Code

While you can use any text editor, we recommend Visual Studio Code with these extensions:
- PHP Intelephense
- PHP Debug
- GitLens
- HTMX/Alpine.js Support
- EditorConfig for VS Code
- MySQL

## Modern Development Environment with Docker

Docker provides a consistent and isolated development environment that matches production settings. Here's how to set up a Docker-based development environment for GibbonEdu.

### System Requirements

Before starting, ensure you have:
- Docker Engine 20.10.0 or higher
- Docker Compose v2.0.0 or higher
- Git

### Docker Setup

1. Create a `docker-compose.yml` file in your project root:

```yaml
version: '3.8'

services:
  web:
    image: php:7.4-apache
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    environment:
      - PHP_DISPLAY_ERRORS=1
      - PHP_ERROR_REPORTING=E_ALL
    depends_on:
      - db

  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: gibbon
      MYSQL_USER: gibbon
      MYSQL_PASSWORD: gibbon
    volumes:
      - db_data:/var/lib/mysql
    command: --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    ports:
      - "8081:80"
    environment:
      - PMA_HOST=db
    depends_on:
      - db

volumes:
  db_data:
```

2. Create a custom Dockerfile for PHP configuration:

```dockerfile
FROM php:7.4-apache

# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    gettext \
    && docker-php-ext-install -j$(nproc) \
    mysqli \
    pdo_mysql \
    gd \
    zip \
    gettext \
    intl

# Enable Apache modules
RUN a2enmod rewrite

# Configure PHP
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
```

### Development Workflow

1. **Initial Setup**
```bash
# Clone the repository
git clone https://github.com/GibbonEdu/core.git
cd core

# Start the containers
docker-compose up -d

# Install dependencies
docker-compose exec web composer install --no-dev

# Set permissions
docker-compose exec web chown -R www-data:www-data /var/www/html
```

2. **Daily Development**
```bash
# Start the environment
docker-compose up -d

# View logs
docker-compose logs -f

# Access the containers
docker-compose exec web bash
docker-compose exec db mysql -u root -p
```

3. **Installing Gibbon**
- Access http://localhost:8080
- Follow the web installer
- Use the following database settings:
  - Host: db
  - Database: gibbon
  - Username: gibbon
  - Password: gibbon

### Development Tools Integration

1. **Xdebug Setup**
Add to your Dockerfile:

```dockerfile
# Install Xdebug
RUN pecl install xdebug-3.1.6 && docker-php-ext-enable xdebug

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
```

2. **VS Code Integration**
Create `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}"
            }
        }
    ]
}
```

### Best Practices

1. **Environment Management**
- Use `.env` files for environment-specific settings
- Never commit sensitive data to version control
- Use different configurations for development and production

2. **Performance Optimization**
- Enable PHP OPcache in production
- Use volume mounts for development only
- Configure appropriate PHP memory limits

3. **Security Considerations**
- Regularly update base images
- Scan for vulnerabilities using Docker Scan
- Follow principle of least privilege for container permissions

### Troubleshooting

Common issues and solutions:

1. **Permission Issues**
```bash
# Fix permissions if files are created as root
docker-compose exec web chown -R www-data:www-data /var/www/html
```

2. **Database Connection Issues**
- Ensure the database container is running: `docker-compose ps`
- Check database logs: `docker-compose logs db`
- Verify connection settings in config.php

3. **PHP Extension Missing**
- Modify the Dockerfile to install additional extensions
- Rebuild the container: `docker-compose build --no-cache web`

## Setting Up GibbonEdu for Module Development

Now that we have our basic tools installed, let's set up GibbonEdu for module development.

### 1. Clone the GibbonEdu Repository

We'll use Git to clone (download) the GibbonEdu source code to your local machine.

```bash
# Create a directory for your GibbonEdu development
mkdir ~/gibbon-dev
cd ~/gibbon-dev

# Clone the GibbonEdu repository
git clone https://github.com/GibbonEdu/core.git gibbonedu

# Move into the GibbonEdu directory
cd gibbonedu
```

### 2. Database Setup

GibbonEdu uses a MySQL database to store its data. Let's set up a database and user for our development environment.

```sql
-- Run these commands in phpMyAdmin or MySQL command line

-- Create a new database with proper collation
CREATE DATABASE gibbonedu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

-- Create a new user with a secure password
CREATE USER 'gibbon_admin'@'localhost' IDENTIFIED BY 'choose_a_secure_password';

-- Grant the user all privileges on the gibbonedu database
GRANT ALL PRIVILEGES ON gibbonedu.* TO 'gibbon_admin'@'localhost';

-- Apply the changes
FLUSH PRIVILEGES;
```

Replace 'choose_a_secure_password' with a strong password of your choice.

### 3. Configure Apache

We need to tell Apache where to find our GibbonEdu files. We'll create a new configuration file for this.

```apache
# Create this file: /etc/apache2/sites-available/gibbonedu.conf

<VirtualHost *:80>
    # This is the domain name we'll use locally
    ServerName local.gibbonedu.com
    DocumentRoot /path/to/gibbonedu
    
    # This should point to your GibbonEdu directory
    DocumentRoot /home/your_username/gibbon-dev/gibbonedu
    
    <Directory /home/your_username/gibbon-dev/gibbonedu>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    # Enable mod_rewrite
    <IfModule mod_rewrite.c>
        RewriteEngine On
    </IfModule>
    
    # SSL Configuration (recommended for production)
    # SSLEngine on
    # SSLCertificateFile /path/to/certificate.crt
    # SSLCertificateKeyFile /path/to/private.key
    
    ErrorLog ${APACHE_LOG_DIR}/gibbonedu-error.log
    CustomLog ${APACHE_LOG_DIR}/gibbonedu-access.log combined
</VirtualHost>
```

Remember to replace 'your_username' with your actual username.

### 4. Configure PHP

GibbonEdu requires specific PHP settings. Edit your php.ini file (usually found in /etc/php/7.x/apache2/php.ini) and update these settings:

```ini
; Required PHP settings for GibbonEdu
max_input_vars = 5000
post_max_size = 20M
upload_max_filesize = 20M
memory_limit = 128M
```

After making these changes, restart Apache:

```bash
sudo service apache2 restart
```

## Testing Your Setup

Let's create some test files to ensure everything is working correctly.

### 1. Check PHP Configuration

Create a file named `phpinfo.php` in your GibbonEdu directory:

```php
<?php
// This function outputs information about PHP's configuration
phpinfo();
?>
```

Visit `http://local.gibbonedu.com/phpinfo.php` in your browser. You should see a page with PHP configuration information.

### 2. Verify Database Connection

Create a file named `dbtest.php`:

```php
<?php
try {
    // Attempt to connect to the database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=gibbonedu',
        'gibbon_admin',
        'your_chosen_password'
    );
    echo "Success! Connected to the database.";
} catch (PDOException $e) {
    // If connection fails, display the error message
    echo "Connection failed: " . $e->getMessage();
}
```

Replace 'your_chosen_password' with the password you set earlier.

### 3. Install GibbonEdu

1. In your web browser, go to `http://local.gibbonedu.com/install.php`
2. Follow the installation wizard, using the database details you set up earlier
3. For the initial login, use these credentials:
   ```
   Username: admin
   Password: gibbon
   ```

## Troubleshooting Common Issues

If you encounter problems, here are some common issues and their solutions:

### 1. Permission Problems

GibbonEdu needs to be able to write to certain directories. If you're having permission issues:

```bash
# Change the owner of the GibbonEdu directory to your web server user (usually www-data)
sudo chown -R www-data:www-data /path/to/gibbonedu

# Set the correct permissions
sudo chmod -R 755 /path/to/gibbonedu

# Make the uploads directory writable
sudo chmod -R 777 /path/to/gibbonedu/uploads
```

### 2. Database Connection Issues

If you can't connect to the database:

- Ensure MySQL is running:
  ```bash
  sudo service mysql status
  ```
- Verify your database credentials:
  ```sql
  mysql -u gibbon_admin -p
  SHOW GRANTS;
  ```

### 3. Apache Issues

If Apache isn't serving your GibbonEdu site:

- Check Apache error logs:
  ```bash
  sudo tail -f /var/log/apache2/error.log
  ```
- Ensure Apache is running and restart it:
  ```bash
  sudo service apache2 restart
  ```

## Exercise: Verify Your Development Environment

To make sure everything is set up correctly, create a file named `environment_test.php` in your GibbonEdu directory with the following content:

```php
<?php
// environment_test.php

echo "<h1>GibbonEdu Development Environment Test</h1>";

// 1. Check PHP Version and Extensions
$requiredVersion = '7.4.0';
$requiredExtensions = ['gettext', 'mbstring', 'curl', 'zip', 'xml', 'gd', 'intl'];

echo "<h2>PHP Configuration</h2>";
echo "PHP Version: " . phpversion() . " (Required: >= {$requiredVersion})<br>";
echo "Extensions:<br>";
foreach ($requiredExtensions as $ext) {
    echo "- {$ext}: " . (extension_loaded($ext) ? '✅' : '❌') . "<br>";
}

// Test MySQL connection
echo "<h2>MySQL Connection</h2>";
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=gibbonedu',
        'gibbon_admin',
        'your_chosen_password'
    );
    echo "Status: Connected successfully<br>";
    echo "MySQL version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
} catch (PDOException $e) {
    echo "Status: Connection failed - " . $e->getMessage() . "<br>";
}

// Test file permissions
echo "<h2>File Permissions</h2>";
$uploadDir = __DIR__ . '/uploads';
if (is_writable($uploadDir)) {
    echo "Upload directory is writable: OK<br>";
} else {
    echo "Upload directory is not writable: Please check permissions<br>";
}

// Test PHP extensions
echo "<h2>Required PHP Extensions</h2>";
$required_extensions = [
    'mysqli',
    'pdo_mysql',
    'gd',
    'curl',
    'zip',
    'xml'
];

foreach ($required_extensions as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "Loaded" : "Missing") . "<br>";
}

// Test GibbonEdu specific requirements
echo "<h2>GibbonEdu Specific Requirements</h2>";
echo "max_input_vars: " . ini_get('max_input_vars') . " (Should be at least 5000)<br>";
echo "post_max_size: " . ini_get('post_max_size') . " (Should be at least 20M)<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . " (Should be at least 20M)<br>";
echo "memory_limit: " . ini_get('memory_limit') . " (Should be at least 128M)<br>";
```

Run this script by visiting `http://local.gibbonedu.com/environment_test.php` in your web browser. It will give you a comprehensive overview of your development environment's readiness for GibbonEdu module development.

## Next Steps

Once your development environment is set up:
1. Complete the GibbonEdu installation process
2. Set up the demo data using `gibbon_demo.sql`
3. Create your first module using the starter template
4. Join the GibbonEdu development community

For any issues or questions, visit the [GibbonEdu Support Forums](https://ask.gibbonedu.org).
