<?php
// Database installation script for Inventory Tracker
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Inventory Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Inventory Tracker Installation</h3>
                    </div>
                    <div class="card-body">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $host = $_POST['host'] ?? 'localhost';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $database = $_POST['database'] ?? 'inventory_tracker';
    
    try {
        // Connect to MySQL server (without database name first)
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database`");
        $pdo->exec("USE `$database`");
        
        // Create tables
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            full_name VARCHAR(255),
            last_login TIMESTAMP NULL,
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            active BOOLEAN DEFAULT TRUE
        );
        
        CREATE TABLE IF NOT EXISTS permissions (
            permission_id INT PRIMARY KEY AUTO_INCREMENT,
            permission_name VARCHAR(50) UNIQUE NOT NULL,
            permission_description VARCHAR(255),
            category VARCHAR(50)
        );
        
        CREATE TABLE IF NOT EXISTS user_permissions (
            user_id INT,
            permission_id INT,
            granted_by INT,
            granted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, permission_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES users(user_id) ON DELETE SET NULL
        );
        
        CREATE TABLE IF NOT EXISTS parts (
            part_id INT PRIMARY KEY AUTO_INCREMENT,
            part_number VARCHAR(50) UNIQUE NOT NULL,
            part_name VARCHAR(255),
            part_type ENUM('shoot_ship', 'value_added') NOT NULL,
            description TEXT,
            reorder_point INT DEFAULT 0,
            current_stock INT DEFAULT 0,
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS molds (
            mold_id INT PRIMARY KEY AUTO_INCREMENT,
            mold_number VARCHAR(50) UNIQUE NOT NULL,
            total_cavities INT NOT NULL,
            shot_size DECIMAL(8,4) NOT NULL,
            shot_size_unit VARCHAR(10) DEFAULT 'lbs',
            notes TEXT
        );
        
        CREATE TABLE IF NOT EXISTS mold_cavities (
            cavity_id INT PRIMARY KEY AUTO_INCREMENT,
            mold_id INT,
            cavity_number INT,
            part_id INT,
            parts_per_shot INT DEFAULT 1,
            UNIQUE KEY unique_cavity (mold_id, cavity_number),
            FOREIGN KEY (mold_id) REFERENCES molds(mold_id) ON DELETE CASCADE,
            FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS materials (
            material_id INT PRIMARY KEY AUTO_INCREMENT,
            material_name VARCHAR(255) NOT NULL,
            material_type VARCHAR(100),
            supplier VARCHAR(255),
            lead_time_days INT,
            current_stock DECIMAL(10,2) DEFAULT 0,
            unit_of_measure VARCHAR(20),
            reorder_point DECIMAL(10,2) DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS components (
            component_id INT PRIMARY KEY AUTO_INCREMENT,
            component_name VARCHAR(255) NOT NULL,
            component_type VARCHAR(100),
            supplier VARCHAR(255),
            lead_time_days INT,
            current_stock INT DEFAULT 0,
            reorder_point INT DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS consumables (
            consumable_id INT PRIMARY KEY AUTO_INCREMENT,
            consumable_name VARCHAR(255) NOT NULL,
            consumable_type VARCHAR(100),
            supplier VARCHAR(255),
            lead_time_days INT,
            current_stock INT DEFAULT 0,
            container_size VARCHAR(50),
            reorder_point INT DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS packaging (
            packaging_id INT PRIMARY KEY AUTO_INCREMENT,
            packaging_name VARCHAR(255) NOT NULL,
            packaging_type VARCHAR(100),
            supplier VARCHAR(255),
            lead_time_days INT,
            current_stock INT DEFAULT 0,
            reorder_point INT DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS part_materials (
            part_id INT,
            material_id INT,
            quantity_per_part DECIMAL(10,4),
            PRIMARY KEY (part_id, material_id),
            FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
            FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS part_components (
            part_id INT,
            component_id INT,
            quantity_per_part INT,
            PRIMARY KEY (part_id, component_id),
            FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
            FOREIGN KEY (component_id) REFERENCES components(component_id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS part_consumables (
            part_id INT,
            consumable_id INT,
            required BOOLEAN DEFAULT TRUE,
            application_step INT,
            notes TEXT,
            PRIMARY KEY (part_id, consumable_id),
            FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
            FOREIGN KEY (consumable_id) REFERENCES consumables(consumable_id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS part_packaging (
            part_id INT,
            packaging_id INT,
            quantity_per_part INT,
            PRIMARY KEY (part_id, packaging_id),
            FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
            FOREIGN KEY (packaging_id) REFERENCES packaging(packaging_id) ON DELETE CASCADE
        );
        
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
        );
        ";
        
        $pdo->exec($sql);
        
        // Insert default permissions
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
        
        // Create default admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'admin', ?)")
            ->execute(['admin', $adminPassword, 'System Administrator']);
        
        // Create sample users
        $sampleUsers = [
            ['inventory_clerk', 'clerk123', 'Inventory Clerk', ['view_inventory', 'update_inventory', 'reorder_management']],
            ['supervisor', 'super123', 'Production Supervisor', ['view_inventory', 'update_inventory', 'manage_materials', 'manage_components', 'manage_consumables', 'manage_packaging', 'view_parts', 'manage_parts', 'view_molds', 'manage_molds', 'production_planning', 'view_bom', 'manage_bom', 'view_reports', 'reorder_management', 'export_data', 'view_transactions']]
        ];
        
        foreach ($sampleUsers as $userData) {
            $userPassword = password_hash($userData[1], PASSWORD_DEFAULT);
            $userStmt = $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, role, full_name) VALUES (?, ?, 'user', ?)");
            $userStmt->execute([$userData[0], $userPassword, $userData[2]]);
            
            $userId = $pdo->lastInsertId();
            if ($userId) {
                foreach ($userData[3] as $permission) {
                    $pdo->prepare("
                        INSERT IGNORE INTO user_permissions (user_id, permission_id) 
                        SELECT ?, permission_id FROM permissions WHERE permission_name = ?
                    ")->execute([$userId, $permission]);
                }
            }
        }
        
        // Insert sample data
        $pdo->exec("
            INSERT IGNORE INTO materials (material_name, material_type, supplier, lead_time_days, current_stock, unit_of_measure, reorder_point) VALUES
            ('PA66 Black Resin', 'Resin', 'DuPont', 14, 500.00, 'lbs', 100.00),
            ('PP Natural Resin', 'Resin', 'ExxonMobil', 10, 750.00, 'lbs', 150.00),
            ('Black Colorant', 'Colorant', 'Clariant', 7, 25.50, 'lbs', 5.00);
            
            INSERT IGNORE INTO parts (part_number, part_name, part_type, description, reorder_point, current_stock) VALUES
            ('20636', 'Base Component', 'shoot_ship', 'Injection molded base part', 100, 250),
            ('20638', 'Complete Assembly', 'value_added', 'Assembled part with components', 50, 75);
            
            INSERT IGNORE INTO molds (mold_number, total_cavities, shot_size, shot_size_unit, notes) VALUES
            ('20636', 4, 2.5, 'lbs', 'Standard 4-cavity mold'),
            ('20638-BASE', 2, 3.2, 'lbs', '2-cavity mold for base part');
            
            INSERT IGNORE INTO components (component_name, component_type, supplier, lead_time_days, current_stock, reorder_point) VALUES
            ('Hardware Kit A', 'Hardware', 'Fastener Co', 5, 150, 25),
            ('Gasket Seal', 'Gasket', 'Seal Tech', 10, 200, 50);
            
            INSERT IGNORE INTO consumables (consumable_name, consumable_type, supplier, lead_time_days, current_stock, container_size, reorder_point) VALUES
            ('Adhesive Promoter', 'Promoter', 'Henkel', 14, 3, '1 gallon', 1),
            ('Assembly Adhesive', 'Adhesive', '3M', 7, 5, '32 oz', 2);
            
            INSERT IGNORE INTO packaging (packaging_name, packaging_type, supplier, lead_time_days, current_stock, reorder_point) VALUES
            ('Shipping Box Large', 'Box', 'PackCorp', 3, 100, 20),
            ('Protective Insert', 'Insert', 'FoamTech', 5, 50, 10);
        ");
        
        // Update database configuration file
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
        
        echo '<div class="alert alert-success">
                <h5>Installation Successful!</h5>
                <p>Database has been created and configured successfully.</p>
                <hr>
                <h6>Default Login Credentials:</h6>
                <ul>
                    <li><strong>Admin:</strong> username: admin, password: admin123</li>
                    <li><strong>Inventory Clerk:</strong> username: inventory_clerk, password: clerk123</li>
                    <li><strong>Supervisor:</strong> username: supervisor, password: super123</li>
                </ul>
                <div class="alert alert-warning mt-3">
                    <strong>Security Notice:</strong> Please change these default passwords immediately after first login!
                </div>
                <a href="login.php" class="btn btn-primary">Go to Login Page</a>
              </div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">
                <h5>Installation Failed</h5>
                <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
              </div>';
    }
} else {
?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="host" class="form-label">MySQL Host</label>
                                <input type="text" class="form-control" id="host" name="host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">MySQL Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">MySQL Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>
                            <div class="mb-3">
                                <label for="database" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="database" name="database" value="inventory_tracker" required>
                            </div>
                            <button type="submit" name="install" class="btn btn-primary">Install Database</button>
                        </form>
<?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>