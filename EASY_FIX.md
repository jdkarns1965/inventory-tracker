# 🔧 WSL2 MySQL Easy Fix Guide

Your MySQL root user is using `auth_socket` authentication instead of password authentication. This is very common in WSL2 Ubuntu setups.

## 🚀 **Quick Fix (Copy these commands exactly):**

### **Option 1: Reset with mysql_secure_installation**
```bash
sudo mysql_secure_installation
```
- When asked for current password: **Press ENTER** (empty)
- Set root password? **Y**
- New password: **Press ENTER** (empty) or type `root`
- Re-enter password: **Press ENTER** (empty) or type `root`
- Answer remaining questions as you prefer

### **Option 2: Manual Reset (if Option 1 doesn't work)**
```bash
# Stop MySQL
sudo systemctl stop mysql

# Start in safe mode
sudo mysqld_safe --skip-grant-tables --skip-networking &

# Wait 5 seconds, then connect
sleep 5
mysql -u root

# In MySQL prompt, run these one by one:
USE mysql;
UPDATE user SET authentication_string=PASSWORD('') WHERE User='root';
UPDATE user SET plugin='mysql_native_password' WHERE User='root';
FLUSH PRIVILEGES;
EXIT;

# Kill safe mode and restart normally
sudo pkill mysqld
sudo systemctl start mysql

# Test connection
mysql -u root -e "SELECT 'Success!' as status;"
```

### **Option 3: Create a new MySQL user instead**
```bash
# If you can access MySQL any other way, create a new user:
mysql -u [any_working_user] -p[password]

# In MySQL:
CREATE USER 'webapp'@'localhost' IDENTIFIED BY 'webapp123';
GRANT ALL PRIVILEGES ON *.* TO 'webapp'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EXIT;
```

## 📝 **After ANY method works:**

1. **Test the connection:**
   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory_tracker;"
   ```

2. **Go to:** `http://localhost/inventory-tracker/simple_install.php`

3. **If you used Option 3 (webapp user):** Modify the installer to use:
   - Username: `webapp`
   - Password: `webapp123`

## 🔍 **Debugging Commands:**

Check MySQL status:
```bash
systemctl status mysql
```

Check MySQL processes:
```bash
ps aux | grep mysql
```

Check MySQL error log:
```bash
sudo tail -f /var/log/mysql/error.log
```

View current MySQL users:
```bash
mysql -u root -e "SELECT user, host, plugin FROM mysql.user;"
```

## 💡 **Why this happens:**

MySQL 8.0 on Ubuntu uses `auth_socket` by default for the root user, which means:
- Root can only connect via `sudo mysql` 
- Web applications can't connect as root without password
- This is actually more secure, but not convenient for development

The fix changes root to use password authentication instead.

## ✅ **Success Indicators:**

You'll know it's working when:
- `mysql -u root` connects without sudo
- `mysql -u root -e "SELECT 1;"` returns "1"
- The simple_install.php script completes successfully

---

**Once MySQL is fixed, your inventory tracker will work perfectly! All the application code is ready and waiting.**