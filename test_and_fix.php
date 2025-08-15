<?php
echo "<h1>🔧 WSL2 MySQL Connection Test & Fix</h1>";
echo "<div style='font-family: monospace; background: #f8f9fa; padding: 15px; border-radius: 5px;'>";

// Test different MySQL connection methods
$methods = [
    ['desc' => 'Standard root with empty password', 'host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['desc' => 'Standard root with "root" password', 'host' => 'localhost', 'user' => 'root', 'pass' => 'root'],
    ['desc' => 'Standard root with "password"', 'host' => 'localhost', 'user' => 'root', 'pass' => 'password'],
    ['desc' => '127.0.0.1 root with empty password', 'host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['desc' => '127.0.0.1 root with "root" password', 'host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root'],
];

$working_method = null;

foreach ($methods as $i => $method) {
    echo "<strong>Test " . ($i + 1) . ":</strong> {$method['desc']}<br>";
    
    try {
        $pdo = new PDO(
            "mysql:host={$method['host']};charset=utf8mb4",
            $method['user'],
            $method['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ]
        );
        
        echo "✅ <span style='color: green;'>SUCCESS! Connected to MySQL</span><br>";
        
        // Try to create database
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS inventory_tracker");
            echo "✅ <span style='color: green;'>Database creation works</span><br>";
            $working_method = $method;
            break;
        } catch (Exception $e) {
            echo "⚠️ <span style='color: orange;'>Connected but cannot create database: " . $e->getMessage() . "</span><br>";
        }
        
    } catch (Exception $e) {
        echo "❌ <span style='color: red;'>Failed: " . $e->getMessage() . "</span><br>";
    }
    echo "<br>";
}

echo "</div>";

if ($working_method) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h2>🎉 MySQL Connection Found!</h2>";
    echo "<p>Working configuration:</p>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> {$working_method['host']}</li>";
    echo "<li><strong>User:</strong> {$working_method['user']}</li>";
    echo "<li><strong>Password:</strong> " . ($working_method['pass'] ? $working_method['pass'] : '(empty)') . "</li>";
    echo "</ul>";
    
    // Update the database config
    $configContent = "<?php
define('DB_HOST', '{$working_method['host']}');
define('DB_USER', '{$working_method['user']}');
define('DB_PASS', '{$working_method['pass']}');
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
            ]
        );
        return \$pdo;
    } catch (PDOException \$e) {
        error_log(\"Database connection failed: \" . \$e->getMessage());
        die(\"Database connection failed. Please try again later.\");
    }
}
?>";
    
    if (file_put_contents('config/database.php', $configContent)) {
        echo "<p>✅ Database configuration updated automatically!</p>";
        echo "<p><a href='simple_install.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 Run Installation Now</a></p>";
    } else {
        echo "<p>⚠️ Could not update config file. Please update manually.</p>";
    }
    
    echo "</div>";
    
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h2>❌ No Working MySQL Connection Found</h2>";
    echo "<p>None of the standard methods worked. You need to fix MySQL authentication first.</p>";
    
    echo "<h3>🛠️ Fix Commands:</h3>";
    echo "<p><strong>Method 1 - Simple reset:</strong></p>";
    echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 3px; white-space: pre-wrap;'>";
    echo "sudo mysql_secure_installation\n";
    echo "# Press ENTER for current password\n";
    echo "# Set new password to empty or 'root'\n";
    echo "</pre>";
    
    echo "<p><strong>Method 2 - Manual reset:</strong></p>";
    echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 3px; white-space: pre-wrap;'>";
    echo "sudo systemctl stop mysql\n";
    echo "sudo mysqld_safe --skip-grant-tables --skip-networking &\n";
    echo "sleep 5\n";
    echo "mysql -u root\n";
    echo "\n";
    echo "# In MySQL prompt:\n";
    echo "USE mysql;\n";
    echo "UPDATE user SET authentication_string=PASSWORD('') WHERE User='root';\n";
    echo "UPDATE user SET plugin='mysql_native_password' WHERE User='root';\n";
    echo "FLUSH PRIVILEGES;\n";
    echo "EXIT;\n";
    echo "\n";
    echo "sudo pkill mysqld\n";
    echo "sudo systemctl start mysql\n";
    echo "</pre>";
    
    echo "<p>After running either method, refresh this page to test again.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Current MySQL status: ";
if (system('systemctl is-active mysql 2>/dev/null') === false) {
    echo "Unknown";
} else {
    echo "Running";
}
echo "</small></p>";
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 800px; 
    margin: 20px auto; 
    padding: 20px; 
    line-height: 1.6;
}
pre { 
    overflow-x: auto; 
    font-size: 14px;
}
</style>