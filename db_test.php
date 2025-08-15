<?php
echo "=== WSL2 LAMP MySQL Connection Test ===\n\n";

// Common WSL2 MySQL configurations to try
$configs = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'password'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'user' => 'inventory_user', 'pass' => 'your_password_here']
];

$working_config = null;

foreach ($configs as $i => $config) {
    echo "Testing config " . ($i + 1) . ": {$config['host']} / {$config['user']} / " . ($config['pass'] ? 'with password' : 'no password') . "\n";
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};charset=utf8mb4", 
            $config['user'], 
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        
        echo "✅ SUCCESS! Connected to MySQL\n";
        
        // Test creating database
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS inventory_tracker");
            echo "✅ Database 'inventory_tracker' created/exists\n";
            $working_config = $config;
            break;
        } catch (Exception $e) {
            echo "❌ Cannot create database: " . $e->getMessage() . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

if ($working_config) {
    echo "=== UPDATING DATABASE CONFIGURATION ===\n";
    
    $config_content = "<?php
define('DB_HOST', '{$working_config['host']}');
define('DB_USER', '{$working_config['user']}');
define('DB_PASS', '{$working_config['pass']}');
define('DB_NAME', 'inventory_tracker');

function getDatabase() {
    try {
        \$pdo = new PDO(
            \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ]
        );
        return \$pdo;
    } catch (PDOException \$e) {
        error_log(\"Database connection failed: \" . \$e->getMessage());
        die(\"Database connection failed. Please check your database configuration.\");
    }
}
?>";

    file_put_contents('/var/www/html/inventory-tracker/config/database.php', $config_content);
    echo "✅ Database configuration updated!\n\n";
    
    echo "=== TESTING NEW CONFIGURATION ===\n";
    try {
        require_once '/var/www/html/inventory-tracker/config/database.php';
        $db = getDatabase();
        echo "✅ New configuration works!\n";
        
        // Check if tables exist
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            echo "⚠️  Database is empty. You need to run install.php to create tables.\n";
        } else {
            echo "✅ Found " . count($tables) . " tables: " . implode(', ', $tables) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Configuration test failed: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ No working configuration found!\n";
    echo "\nTroubleshooting for WSL2:\n";
    echo "1. Check if MySQL is running: systemctl status mysql\n";
    echo "2. Try resetting MySQL root password:\n";
    echo "   sudo mysql -u root -e \"ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';\"\n";
    echo "3. Check MySQL bind address in /etc/mysql/mysql.conf.d/mysqld.cnf\n";
    echo "4. Restart MySQL: sudo systemctl restart mysql\n";
}
?>