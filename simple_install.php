<?php
// Simple WSL2 LAMP Installation Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WSL2 LAMP Inventory Tracker Installer</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = 'localhost';
    $username = 'root';
    $password = 'passgas1989';  // Correct password for this WSL2 setup
    $database = 'inventory_tracker';
    
    echo "<h2>Installation Progress</h2>";
    echo "<div style='font-family: monospace; background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
    
    try {
        echo "1. Connecting to MySQL server...<br>";
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "✅ Connected to MySQL server<br><br>";
        
        echo "2. Creating database...<br>";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database`");
        $pdo->exec("USE `$database`");
        echo "✅ Database '$database' created/selected<br><br>";
        
        echo "3. Creating tables...<br>";
        
        // Create users table
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            full_name VARCHAR(255),
            last_login TIMESTAMP NULL,
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            active BOOLEAN DEFAULT TRUE
        )");
        echo "✅ Users table created<br>";
        
        // Create permissions table
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            permission_id INT PRIMARY KEY AUTO_INCREMENT,
            permission_name VARCHAR(50) UNIQUE NOT NULL,
            permission_description VARCHAR(255),
            category VARCHAR(50)
        )");
        echo "✅ Permissions table created<br>";
        
        // Create user_permissions table
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            user_id INT,
            permission_id INT,
            granted_by INT,
            granted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, permission_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES users(user_id) ON DELETE SET NULL
        )");
        echo "✅ User permissions table created<br>";
        
        // Create remaining tables
        $tables = [
            "parts" => "
            CREATE TABLE IF NOT EXISTS parts (
                part_id INT PRIMARY KEY AUTO_INCREMENT,
                part_number VARCHAR(50) UNIQUE NOT NULL,
                part_name VARCHAR(255),
                part_type ENUM('shoot_ship', 'value_added') NOT NULL,
                description TEXT,
                reorder_point INT DEFAULT 0,
                current_stock INT DEFAULT 0,
                created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "molds" => "
            CREATE TABLE IF NOT EXISTS molds (
                mold_id INT PRIMARY KEY AUTO_INCREMENT,
                mold_number VARCHAR(50) UNIQUE NOT NULL,
                total_cavities INT NOT NULL,
                shot_size DECIMAL(8,4) NOT NULL,
                shot_size_unit VARCHAR(10) DEFAULT 'lbs',
                notes TEXT
            )",
            "materials" => "
            CREATE TABLE IF NOT EXISTS materials (
                material_id INT PRIMARY KEY AUTO_INCREMENT,
                material_name VARCHAR(255) NOT NULL,
                material_type VARCHAR(100),
                supplier VARCHAR(255),
                lead_time_days INT,
                current_stock DECIMAL(10,2) DEFAULT 0,
                unit_of_measure VARCHAR(20),
                reorder_point DECIMAL(10,2) DEFAULT 0
            )",
            "components" => "
            CREATE TABLE IF NOT EXISTS components (
                component_id INT PRIMARY KEY AUTO_INCREMENT,
                component_name VARCHAR(255) NOT NULL,
                component_type VARCHAR(100),
                supplier VARCHAR(255),
                lead_time_days INT,
                current_stock INT DEFAULT 0,
                reorder_point INT DEFAULT 0
            )",
            "consumables" => "
            CREATE TABLE IF NOT EXISTS consumables (
                consumable_id INT PRIMARY KEY AUTO_INCREMENT,
                consumable_name VARCHAR(255) NOT NULL,
                consumable_type VARCHAR(100),
                supplier VARCHAR(255),
                lead_time_days INT,
                current_stock INT DEFAULT 0,
                container_size VARCHAR(50),
                reorder_point INT DEFAULT 0
            )",
            "packaging" => "
            CREATE TABLE IF NOT EXISTS packaging (
                packaging_id INT PRIMARY KEY AUTO_INCREMENT,
                packaging_name VARCHAR(255) NOT NULL,
                packaging_type VARCHAR(100),
                supplier VARCHAR(255),
                lead_time_days INT,
                current_stock INT DEFAULT 0,
                reorder_point INT DEFAULT 0
            )",
            "inventory_transactions" => "
            CREATE TABLE IF NOT EXISTS inventory_transactions (
                transaction_id INT PRIMARY KEY AUTO_INCREMENT,
                transaction_type ENUM('material', 'component', 'packaging', 'consumable', 'finished_part') NOT NULL,
                item_id INT NOT NULL,
                transaction_action ENUM('physical_count', 'received', 'used', 'adjust') NOT NULL,
                new_quantity DECIMAL(10,2) NOT NULL,
                notes TEXT,
                transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_id INT,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
            )"
        ];
        
        foreach ($tables as $name => $sql) {
            $pdo->exec($sql);
            echo "✅ $name table created<br>";
        }
        
        // Create junction tables
        $junctionTables = [
            "mold_cavities" => "
            CREATE TABLE IF NOT EXISTS mold_cavities (
                cavity_id INT PRIMARY KEY AUTO_INCREMENT,
                mold_id INT,
                cavity_number INT,
                part_id INT,
                parts_per_shot INT DEFAULT 1,
                UNIQUE KEY unique_cavity (mold_id, cavity_number),
                FOREIGN KEY (mold_id) REFERENCES molds(mold_id) ON DELETE CASCADE,
                FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE
            )",
            "part_materials" => "
            CREATE TABLE IF NOT EXISTS part_materials (
                part_id INT,
                material_id INT,
                quantity_per_part DECIMAL(10,4),
                PRIMARY KEY (part_id, material_id),
                FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
                FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE
            )",
            "part_components" => "
            CREATE TABLE IF NOT EXISTS part_components (
                part_id INT,
                component_id INT,
                quantity_per_part INT,
                PRIMARY KEY (part_id, component_id),
                FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
                FOREIGN KEY (component_id) REFERENCES components(component_id) ON DELETE CASCADE
            )",
            "part_consumables" => "
            CREATE TABLE IF NOT EXISTS part_consumables (
                part_id INT,
                consumable_id INT,
                required BOOLEAN DEFAULT TRUE,
                application_step INT,
                notes TEXT,
                PRIMARY KEY (part_id, consumable_id),
                FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
                FOREIGN KEY (consumable_id) REFERENCES consumables(consumable_id) ON DELETE CASCADE
            )",
            "part_packaging" => "
            CREATE TABLE IF NOT EXISTS part_packaging (
                part_id INT,
                packaging_id INT,
                quantity_per_part INT,
                PRIMARY KEY (part_id, packaging_id),
                FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
                FOREIGN KEY (packaging_id) REFERENCES packaging(packaging_id) ON DELETE CASCADE
            )"
        ];
        
        foreach ($junctionTables as $name => $sql) {
            $pdo->exec($sql);
            echo "✅ $name table created<br>";
        }
        
        echo "<br>4. Creating permissions...<br>";
        
        // Insert permissions
        $permissions = [
            ['view_inventory', 'View current stock levels and inventory data', 'inventory'],
            ['update_inventory', 'Perform physical counts and inventory adjustments', 'inventory'],
            ['manage_materials', 'Add/edit raw materials', 'inventory'],
            ['manage_components', 'Add/edit components', 'inventory'],
            ['manage_consumables', 'Add/edit consumables', 'inventory'],
            ['manage_packaging', 'Add/edit packaging materials', 'inventory'],
            ['view_parts', 'View parts information and BOMs', 'parts'],
            ['manage_parts', 'Add/edit parts and their specifications', 'parts'],
            ['view_molds', 'View mold information', 'production'],
            ['manage_molds', 'Add/edit mold specifications', 'production'],
            ['production_planning', 'Access production calculation tools', 'production'],
            ['view_bom', 'View bills of materials', 'parts'],
            ['manage_bom', 'Create/edit bills of materials', 'parts'],
            ['view_reports', 'Access inventory status and reports', 'reporting'],
            ['reorder_management', 'View and manage reorder lists', 'reporting'],
            ['export_data', 'Export reports and data', 'reporting'],
            ['view_transactions', 'View inventory transaction history', 'reporting'],
            ['admin_panel', 'Access user administration', 'system'],
            ['system_settings', 'Modify system configuration', 'system']
        ];
        
        $permStmt = $pdo->prepare("INSERT IGNORE INTO permissions (permission_name, permission_description, category) VALUES (?, ?, ?)");
        foreach ($permissions as $perm) {
            $permStmt->execute($perm);
        }
        echo "✅ " . count($permissions) . " permissions created<br><br>";
        
        echo "5. Creating default users...<br>";
        
        // Create admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'admin', ?)")
            ->execute(['admin', $adminPassword, 'System Administrator']);
        echo "✅ Admin user created (admin/admin123)<br>";
        
        // Create sample users with permissions
        $sampleUsers = [
            ['inventory_clerk', 'clerk123', 'Inventory Clerk'],
            ['supervisor', 'super123', 'Production Supervisor']
        ];
        
        foreach ($sampleUsers as $userData) {
            $userPassword = password_hash($userData[1], PASSWORD_DEFAULT);
            $userStmt = $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'user', ?)");
            $userStmt->execute([$userData[0], $userPassword, $userData[2]]);
            echo "✅ User created: {$userData[0]}/{$userData[1]}<br>";
        }
        
        echo "<br>6. Adding sample data...<br>";
        
        // Insert sample data
        $pdo->exec("
            INSERT IGNORE INTO materials (material_name, material_type, supplier, lead_time_days, current_stock, unit_of_measure, reorder_point) VALUES
            ('PA66 Black Resin', 'Resin', 'DuPont', 14, 500.00, 'lbs', 100.00),
            ('PP Natural Resin', 'Resin', 'ExxonMobil', 10, 750.00, 'lbs', 150.00),
            ('Black Colorant', 'Colorant', 'Clariant', 7, 25.50, 'lbs', 5.00)
        ");
        
        $pdo->exec("
            INSERT IGNORE INTO parts (part_number, part_name, part_type, description, reorder_point, current_stock) VALUES
            ('20636', 'Base Component', 'shoot_ship', 'Injection molded base part', 100, 250),
            ('20638', 'Complete Assembly', 'value_added', 'Assembled part with components', 50, 75)
        ");
        
        $pdo->exec("
            INSERT IGNORE INTO molds (mold_number, total_cavities, shot_size, shot_size_unit, notes) VALUES
            ('20636', 4, 2.5, 'lbs', 'Standard 4-cavity mold'),
            ('20638-BASE', 2, 3.2, 'lbs', '2-cavity mold for base part')
        ");
        
        echo "✅ Sample data added<br><br>";
        
        echo "7. Updating configuration...<br>";
        
        // Update database configuration
        $configContent = "<?php
define('DB_HOST', '$host');
define('DB_USER', '$username');
define('DB_PASS', '$password');
define('DB_NAME', '$database');

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
        
        file_put_contents('config/database.php', $configContent);
        echo "✅ Database configuration updated<br><br>";
        
        echo "</div>";
        
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h2>🎉 Installation Complete!</h2>";
        echo "<p><strong>Your inventory tracker is ready to use!</strong></p>";
        echo "<h3>Login Credentials:</h3>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> username = admin, password = admin123</li>";
        echo "<li><strong>Supervisor:</strong> username = supervisor, password = super123</li>";
        echo "<li><strong>Inventory Clerk:</strong> username = inventory_clerk, password = clerk123</li>";
        echo "</ul>";
        echo "<p style='background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px;'>";
        echo "<strong>⚠️ Security Notice:</strong> Please change these default passwords after your first login!";
        echo "</p>";
        echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 Go to Login Page</a></p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "</div>";
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>❌ Installation Failed</h3>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>This usually means MySQL root access is not configured properly.</p>";
        echo "<h4>Try these commands in your WSL2 terminal:</h4>";
        echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 3px;'>";
        echo "sudo mysql\n";
        echo "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';\n";
        echo "FLUSH PRIVILEGES;\n";
        echo "EXIT;\n";
        echo "</pre>";
        echo "<p>Then refresh this page and try again.</p>";
        echo "</div>";
    }
    
} else {
    echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h2>Ready to Install</h2>";
    echo "<p>This will install the Inventory Tracker with your WSL2 LAMP settings:</p>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> localhost</li>";
    echo "<li><strong>Username:</strong> root</li>";
    echo "<li><strong>Password:</strong> passgas1989</li>";
    echo "<li><strong>Database:</strong> inventory_tracker</li>";
    echo "</ul>";
    echo "<p>If you're getting access denied errors, run these commands first:</p>";
    echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 3px;'>";
    echo "sudo mysql\n";
    echo "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';\n";
    echo "FLUSH PRIVILEGES;\n";
    echo "EXIT;";
    echo "</pre>";
    echo "</div>";
    
    echo "<form method='POST'>";
    echo "<button type='submit' style='background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 18px; cursor: pointer;'>🚀 Install Inventory Tracker</button>";
    echo "</form>";
}
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
    white-space: pre-wrap;
}
</style>