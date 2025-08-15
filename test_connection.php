<?php
echo "<h2>Database Connection Test</h2>";

// Test 1: Check if we can connect to MySQL server
echo "<h3>Test 1: MySQL Server Connection</h3>";
try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Successfully connected to MySQL server<br>";
    
    // Show databases
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available databases: " . implode(', ', $databases) . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Failed to connect to MySQL server: " . $e->getMessage() . "<br>";
    echo "<p>Common solutions:</p>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running: <code>sudo systemctl start mysql</code></li>";
    echo "<li>Check if you need a password: try 'root' with password 'root' or empty password</li>";
    echo "<li>Make sure MySQL is listening on localhost:3306</li>";
    echo "</ul>";
}

// Test 2: Check if inventory_tracker database exists
echo "<h3>Test 2: Inventory Tracker Database</h3>";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=inventory_tracker;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Successfully connected to inventory_tracker database<br>";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "⚠️ Database exists but no tables found. Run install.php to create tables.<br>";
    } else {
        echo "Found tables: " . implode(', ', $tables) . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Cannot connect to inventory_tracker database: " . $e->getMessage() . "<br>";
    echo "<p>You need to run the installation script to create the database.</p>";
    echo "<p><a href='install.php' class='btn btn-primary'>Run Installation Script</a></p>";
}

// Test 3: Check current database config
echo "<h3>Test 3: Current Configuration</h3>";
$configFile = 'config/database.php';
if (file_exists($configFile)) {
    $config = file_get_contents($configFile);
    if (strpos($config, 'your_password_here') !== false) {
        echo "⚠️ Database configuration still has placeholder values<br>";
        echo "<p>The database config file exists but needs to be updated with real credentials.</p>";
    } else {
        echo "✅ Database configuration file exists and appears configured<br>";
    }
} else {
    echo "❌ Database configuration file not found<br>";
}

echo "<hr>";
echo "<h3>Quick Setup Instructions</h3>";
echo "<ol>";
echo "<li><strong>Run the installer:</strong> Go to <a href='install.php'>install.php</a></li>";
echo "<li><strong>Use these common MySQL credentials:</strong>";
echo "<ul>";
echo "<li>Host: localhost</li>";
echo "<li>Username: root</li>";
echo "<li>Password: (try empty password first, then 'root' or 'password')</li>";
echo "<li>Database: inventory_tracker (will be created automatically)</li>";
echo "</ul></li>";
echo "<li><strong>After installation:</strong> The database.php file will be automatically updated</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='install.php' class='btn btn-success btn-lg'>🚀 Run Installation Now</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
.btn-success { background: #28a745; }
.btn-lg { padding: 15px 30px; font-size: 18px; }
code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style>