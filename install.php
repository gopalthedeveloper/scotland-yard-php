<?php
// Scotland Yard Game Installation Script

echo "=== Scotland Yard Game Installation ===\n\n";

// Check PHP version
echo "Checking PHP version...\n";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "ERROR: PHP 7.4 or higher is required. Current version: " . PHP_VERSION . "\n";
    exit(1);
}
echo "✓ PHP version " . PHP_VERSION . " is compatible\n\n";

// Check required extensions
echo "Checking required extensions...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        echo "ERROR: Required extension '$ext' is not loaded\n";
        exit(1);
    }
    echo "✓ Extension '$ext' is loaded\n";
}
echo "\n";

// Check if config file exists
echo "Checking configuration...\n";
if (file_exists('config.php')) {
    echo "✓ config.php already exists\n";
} else {
    echo "Creating config.php...\n";
    
    $config_content = '<?php
// Database configuration
define(\'DB_HOST\', \'localhost\');
define(\'DB_NAME\', \'scotland_yard\');
define(\'DB_USER\', \'root\');
define(\'DB_PASS\', \'\');

// Game configuration
define(\'GAME_CONFIG\', [
    \'max_players\' => 6,
    \'max_rounds\' => 24,
    \'reveal_rounds\' => [3, 8, 13, 18, 23, 28, 33, 38],
    \'tickets\' => [
        \'detective\' => [
            \'taxi\' => 11,
            \'bus\' => 8,
            \'underground\' => 4
        ],
        \'mr_x\' => [
            \'hidden\' => 5,
            \'double\' => 2,
            \'taxi\' => 99,
            \'bus\' => 99,
            \'underground\' => 99
        ]
    ],
    \'transport_types\' => [
        \'T\' => \'Taxi\',
        \'B\' => \'Bus\', 
        \'U\' => \'Underground\',
        \'F\' => \'Ferry\',
        \'X\' => \'Hidden\',
        \'2\' => \'Double\',
        \'.\' => \'Stay\'
    ]
]);

// Session configuration
ini_set(\'session.cookie_httponly\', 1);
ini_set(\'session.use_only_cookies\', 1);
ini_set(\'session.cookie_secure\', 0);

// Error reporting
error_reporting(E_ALL);
ini_set(\'display_errors\', 1);

// Timezone
date_default_timezone_set(\'UTC\');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>';
    
    if (file_put_contents('config.php', $config_content)) {
        echo "✓ config.php created successfully\n";
    } else {
        echo "ERROR: Could not create config.php\n";
        exit(1);
    }
}
echo "\n";

// Check if database.sql exists
echo "Checking database schema...\n";
if (file_exists('database.sql')) {
    echo "✓ database.sql exists\n";
} else {
    echo "ERROR: database.sql not found. Please ensure it exists in the current directory.\n";
    exit(1);
}
echo "\n";

// Test database connection
echo "Testing database connection...\n";
try {
    require_once 'config.php';
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connection successful\n";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if ($stmt->fetch()) {
        echo "✓ Database '" . DB_NAME . "' exists\n";
    } else {
        echo "Creating database '" . DB_NAME . "'...\n";
        $pdo->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✓ Database created successfully\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    echo "Please update the database credentials in config.php\n";
    exit(1);
}
echo "\n";

// Import database schema
echo "Importing database schema...\n";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    echo "✓ Database schema imported successfully\n";
    
} catch (PDOException $e) {
    echo "ERROR: Failed to import database schema: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Check file permissions
echo "Checking file permissions...\n";
$writable_files = ['config.php'];
foreach ($writable_files as $file) {
    if (file_exists($file)) {
        if (is_writable($file)) {
            echo "✓ $file is writable\n";
        } else {
            echo "WARNING: $file is not writable\n";
        }
    }
}

// Check if session directory is writable
$session_path = session_save_path();
if ($session_path && is_writable($session_path)) {
    echo "✓ Session directory is writable\n";
} else {
    echo "WARNING: Session directory may not be writable\n";
}
echo "\n";

// Create .htaccess for security
echo "Creating .htaccess file...\n";
$htaccess_content = "# Security settings
<Files \"config.php\">
    Order allow,deny
    Deny from all
</Files>

<Files \"Database.php\">
    Order allow,deny
    Deny from all
</Files>

<Files \"GameEngine.php\">
    Order allow,deny
    Deny from all
</Files>

# Prevent access to sensitive files
<FilesMatch \"\.(sql|log|txt)$\">
    Order allow,deny
    Deny from all
</FilesMatch>

# Enable URL rewriting (optional)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]";

if (file_put_contents('.htaccess', $htaccess_content)) {
    echo "✓ .htaccess created for security\n";
} else {
    echo "WARNING: Could not create .htaccess file\n";
}
echo "\n";

// Installation complete
echo "=== Installation Complete! ===\n\n";
echo "Next steps:\n";
echo "1. Update database credentials in config.php if needed\n";
echo "2. Configure your web server to point to this directory\n";
echo "3. Access the application through your web browser\n";
echo "4. Register a new account and start playing!\n\n";
echo "Default database credentials:\n";
echo "- Host: localhost\n";
echo "- Database: scotland_yard\n";
echo "- Username: root\n";
echo "- Password: (empty)\n\n";
echo "For help, see README.md\n";
?> 