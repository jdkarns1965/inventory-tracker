#!/bin/bash
echo "=== WSL2 MySQL 8.0 Authentication Fix ==="
echo "This script will try multiple methods to fix MySQL root access"
echo

# Method 1: Try with debian-sys-maint user (Ubuntu/Debian specific)
echo "Method 1: Checking debian-sys-maint credentials..."
if [ -f /etc/mysql/debian.cnf ]; then
    echo "Found debian.cnf file"
    DEBIAN_USER=$(sudo grep "user" /etc/mysql/debian.cnf | head -1 | cut -d= -f2 | tr -d ' ')
    DEBIAN_PASS=$(sudo grep "password" /etc/mysql/debian.cnf | head -1 | cut -d= -f2 | tr -d ' ')
    
    echo "Trying to connect with debian-sys-maint user..."
    if mysql -u "$DEBIAN_USER" -p"$DEBIAN_PASS" -e "SELECT 'Connected!' as status;" 2>/dev/null; then
        echo "✅ Connected with debian-sys-maint! Fixing root user..."
        
        mysql -u "$DEBIAN_USER" -p"$DEBIAN_PASS" << 'EOF'
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
        
        echo "✅ Root user updated! Testing connection..."
        if mysql -u root -e "SELECT 'Root connection works!' as status;" 2>/dev/null; then
            echo "🎉 SUCCESS! Root user is now accessible without password"
            
            # Create database
            mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory_tracker;"
            echo "✅ Database 'inventory_tracker' created"
            
            exit 0
        else
            echo "❌ Root connection still not working"
        fi
    else
        echo "❌ Cannot connect with debian-sys-maint"
    fi
else
    echo "❌ No debian.cnf file found"
fi

echo
echo "Method 2: Trying MySQL safe mode reset..."

# Stop MySQL
echo "Stopping MySQL..."
sudo systemctl stop mysql

# Start MySQL in safe mode
echo "Starting MySQL in safe mode (skip grant tables)..."
sudo mysqld_safe --skip-grant-tables --skip-networking &
SAFE_PID=$!

# Wait for MySQL to start
sleep 5

echo "Connecting to MySQL in safe mode..."
if mysql -u root << 'EOF'
USE mysql;
UPDATE user SET authentication_string=PASSWORD('') WHERE User='root';
UPDATE user SET plugin='mysql_native_password' WHERE User='root';
FLUSH PRIVILEGES;
EOF
then
    echo "✅ Root password reset in safe mode"
else
    echo "❌ Failed to reset in safe mode"
fi

# Kill safe mode MySQL
sudo kill $SAFE_PID 2>/dev/null
sleep 2

# Restart MySQL normally
echo "Restarting MySQL normally..."
sudo systemctl start mysql

# Test connection
sleep 3
if mysql -u root -e "SELECT 'Connection successful!' as status;" 2>/dev/null; then
    echo "🎉 SUCCESS! MySQL root access is now working"
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory_tracker;"
    echo "✅ Database 'inventory_tracker' created"
else
    echo "❌ Method 2 also failed"
    
    echo
    echo "Method 3: Manual reset instructions"
    echo "=============================================="
    echo "Please run these commands manually:"
    echo
    echo "1. sudo systemctl stop mysql"
    echo "2. sudo mysqld_safe --skip-grant-tables --skip-networking &"
    echo "3. mysql -u root"
    echo "4. In MySQL prompt:"
    echo "   USE mysql;"
    echo "   UPDATE user SET authentication_string=PASSWORD('') WHERE User='root';"
    echo "   UPDATE user SET plugin='mysql_native_password' WHERE User='root';"
    echo "   FLUSH PRIVILEGES;"
    echo "   EXIT;"
    echo "5. sudo pkill mysqld"
    echo "6. sudo systemctl start mysql"
    echo "7. mysql -u root"
    echo
    echo "Alternative:"
    echo "sudo mysql_secure_installation"
    echo "(Set root password to empty or 'root')"
fi