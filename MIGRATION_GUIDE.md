# Database Migration & Environment Setup Guide

## 🏠 Moving to Your Home Development Environment

### Before You Leave (Current Environment)

1. **Export your current database:**
   ```bash
   php export_database.php
   ```
   This creates a timestamped backup file like `inventory_backup_2024-01-15_14-30-25.sql`

2. **Commit and push any final changes:**
   ```bash
   git add .
   git commit -m "Final updates before migration"
   git push
   ```

### At Home (New Environment Setup)

#### Step 1: Clone Repository
```bash
git clone https://github.com/jdkarns1965/inventory-tracker.git
cd inventory-tracker
```

#### Step 2: Set Up LAMP Stack
**On Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-mysqli
sudo systemctl start apache2
sudo systemctl start mysql
```

**On CentOS/RHEL:**
```bash
sudo yum install httpd mariadb-server php php-mysql php-mysqli
sudo systemctl start httpd
sudo systemctl start mariadb
```

**On Windows (XAMPP):**
- Download and install XAMPP
- Start Apache and MySQL services
- Place project in `htdocs` folder

**On macOS (Homebrew):**
```bash
brew install php mysql
brew services start mysql
```

#### Step 3: Configure Database Connection
1. **Copy the backup file** you created from your original environment
2. **Edit database configuration:**
   ```bash
   cp config/database.php.example config/database.php  # if needed
   nano config/database.php
   ```
   Update with your local credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'inventory_tracker');
   ```

#### Step 4: Import Database
```bash
php import_database.php your_backup_file.sql
```

#### Step 5: Set Permissions (Linux/macOS)
```bash
sudo chown -R www-data:www-data /var/www/html/inventory-tracker
sudo chmod -R 755 /var/www/html/inventory-tracker
```

#### Step 6: Test Setup
1. **Test database connection:**
   ```bash
   php test_connection.php
   ```

2. **Access via browser:**
   - Navigate to `http://localhost/inventory-tracker`
   - Login with existing credentials

## 🔄 Alternative Migration Methods

### Method 1: Quick SQL Export/Import (Recommended)
- **Export:** `php export_database.php`
- **Import:** `php import_database.php backup_file.sql`
- **Pros:** Simple, includes all data and structure
- **Cons:** Requires command line access

### Method 2: Manual MySQL Commands
```bash
# Export
mysqldump -u username -p inventory_tracker > backup.sql

# Import
mysql -u username -p -e "CREATE DATABASE inventory_tracker;"
mysql -u username -p inventory_tracker < backup.sql
```

### Method 3: phpMyAdmin (GUI Method)
1. **Export:** Use phpMyAdmin export feature (SQL format)
2. **Import:** Use phpMyAdmin import feature
3. **Pros:** User-friendly GUI
4. **Cons:** May have file size limitations

### Method 4: Fresh Install + Manual Data Entry
If you have minimal data:
```bash
php install.php  # Creates fresh database
# Then manually re-enter your data
```

## 🛠️ Development Environment Configuration

### Local Development Settings
Create a `config/local.php` file for environment-specific settings:
```php
<?php
// Local development overrides
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
define('SESSION_TIMEOUT', 3600 * 8); // 8 hours for development
?>
```

### Git Workflow for Ongoing Development
```bash
# Daily workflow
git pull                    # Get latest changes
# ... make changes ...
git add .
git commit -m "Description"
git push
```

### Backup Strategy
Set up automated backups in your home environment:
```bash
# Create a backup script
echo '#!/bin/bash
php /path/to/inventory-tracker/export_database.php
' > daily_backup.sh

# Add to crontab for daily backups
crontab -e
# Add: 0 2 * * * /path/to/daily_backup.sh
```

## 🚨 Common Issues & Solutions

### Issue: "Access denied for user"
**Solution:** Check MySQL user permissions
```sql
CREATE USER 'inventory_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON inventory_tracker.* TO 'inventory_user'@'localhost';
FLUSH PRIVILEGES;
```

### Issue: "Table doesn't exist"
**Solution:** Import didn't complete properly
- Check the import script output for errors
- Verify SQL file integrity
- Try manual MySQL import

### Issue: Permission errors (Linux)
**Solution:** Fix file permissions
```bash
sudo chown -R www-data:www-data /var/www/html/inventory-tracker
sudo chmod -R 755 /var/www/html/inventory-tracker
```

### Issue: PHP modules missing
**Solution:** Install required PHP extensions
```bash
# Ubuntu/Debian
sudo apt install php-mysql php-mysqli php-pdo

# CentOS
sudo yum install php-mysql php-mysqli php-pdo
```

## 📋 Verification Checklist

After migration, verify these work:
- [ ] Database connection successful
- [ ] Login page loads
- [ ] Can authenticate with existing users
- [ ] All inventory data visible
- [ ] Can add/edit records
- [ ] All menu items accessible
- [ ] No PHP errors in logs

## 🔐 Security Notes for Home Environment

1. **Change default passwords** if using sample data
2. **Use strong database passwords**
3. **Consider using HTTPS** even in development
4. **Keep backups in secure location**
5. **Don't commit database credentials** to Git

## 📞 Need Help?

If you encounter issues:
1. Check the error logs: `/var/log/apache2/error.log`
2. Enable PHP error reporting in development
3. Use `test_connection.php` to debug database issues
4. Verify all PHP extensions are installed