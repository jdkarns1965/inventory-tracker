#!/bin/bash
echo "=== WSL2 MySQL Fix Script ==="
echo "This script will fix MySQL authentication for the inventory tracker"
echo

# Method 1: Try connecting with sudo mysql (common in WSL2)
echo "Method 1: Testing sudo mysql connection..."
if sudo mysql -e "SELECT 1;" 2>/dev/null; then
    echo "✅ sudo mysql works! Setting up root password..."
    
    # Reset root password to empty
    sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';"
    sudo mysql -e "FLUSH PRIVILEGES;"
    
    # Test connection
    if mysql -u root -e "SELECT 1;" 2>/dev/null; then
        echo "✅ Root password reset successful!"
        
        # Create database and user
        mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory_tracker;"
        mysql -u root -e "CREATE USER IF NOT EXISTS 'inventory_user'@'localhost' IDENTIFIED BY 'inventory_pass';"
        mysql -u root -e "GRANT ALL PRIVILEGES ON inventory_tracker.* TO 'inventory_user'@'localhost';"
        mysql -u root -e "FLUSH PRIVILEGES;"
        
        echo "✅ Database and user created!"
        echo "Database: inventory_tracker"
        echo "User: inventory_user"
        echo "Password: inventory_pass"
        
        # Update config file
        cat > /var/www/html/inventory-tracker/config/database.php << 'EOF'
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'inventory_user');
define('DB_PASS', 'inventory_pass');
define('DB_NAME', 'inventory_tracker');

function getDatabase() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check your database configuration.");
    }
}
?>
EOF
        
        echo "✅ Configuration file updated!"
        echo
        echo "🚀 Ready! Now run: http://localhost/inventory-tracker/install.php"
        
    else
        echo "❌ Still can't connect with root. Trying alternative method..."
        
        # Alternative: Use socket authentication
        sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket;"
        sudo mysql -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '';"
        sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;"
        sudo mysql -e "FLUSH PRIVILEGES;"
        
        echo "✅ Socket authentication configured. Try connecting to 127.0.0.1"
    fi
    
else
    echo "❌ sudo mysql failed. Trying other methods..."
    
    # Method 2: Check if mysql is configured for socket authentication
    if sudo cat /etc/mysql/debian.cnf 2>/dev/null | grep -q "user.*=.*debian-sys-maint"; then
        echo "Found debian-sys-maint user, using it..."
        
        DEBIAN_USER=$(sudo cat /etc/mysql/debian.cnf | grep "user.*=" | head -1 | cut -d= -f2 | tr -d ' ')
        DEBIAN_PASS=$(sudo cat /etc/mysql/debian.cnf | grep "password.*=" | head -1 | cut -d= -f2 | tr -d ' ')
        
        echo "Using debian maintenance user: $DEBIAN_USER"
        
        mysql -u "$DEBIAN_USER" -p"$DEBIAN_PASS" -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';" 2>/dev/null
        mysql -u "$DEBIAN_USER" -p"$DEBIAN_PASS" -e "FLUSH PRIVILEGES;" 2>/dev/null
        
        if mysql -u root -e "SELECT 1;" 2>/dev/null; then
            echo "✅ Successfully reset root password using debian-sys-maint!"
        fi
    fi
fi

echo
echo "=== Manual Commands if Script Fails ==="
echo "Run these commands manually:"
echo "1. sudo mysql"
echo "2. ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';"
echo "3. CREATE DATABASE inventory_tracker;"
echo "4. EXIT;"
echo
echo "Then run: http://localhost/inventory-tracker/install.php"