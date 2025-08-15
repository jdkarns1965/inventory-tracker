<?php
// Quick database setup for common configurations
$error = '';
$success = '';

if ($_POST) {
    $host = $_POST['host'] ?? 'localhost';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    
    try {
        // Try to connect and create database
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS inventory_tracker");
        $pdo->exec("USE inventory_tracker");
        
        // Update config file
        $configContent = "<?php
define('DB_HOST', '$host');
define('DB_USER', '$username');
define('DB_PASS', '$password');
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
        
        file_put_contents('config/database.php', $configContent);
        $success = "Database connection configured successfully! Now run install.php to create tables.";
        
    } catch (Exception $e) {
        $error = "Connection failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .preset { background: #e9ecef; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .preset button { background: #6c757d; font-size: 12px; padding: 5px 10px; }
    </style>
</head>
<body>
    <h1>🗄️ Quick Database Setup</h1>
    <p>Let's get your database connected. Try these common configurations:</p>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?>
            <br><br>
            <a href="install.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">🚀 Run Installation Now</a>
        </div>
    <?php else: ?>
    
    <div class="preset">
        <strong>Common MySQL Configurations:</strong>
        <button onclick="setPreset('localhost', 'root', '')">XAMPP/WAMP (root, no password)</button>
        <button onclick="setPreset('localhost', 'root', 'root')">MAMP (root/root)</button>
        <button onclick="setPreset('localhost', 'root', 'password')">Ubuntu (root/password)</button>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label for="host">MySQL Host:</label>
            <input type="text" id="host" name="host" value="localhost" required>
        </div>
        
        <div class="form-group">
            <label for="username">MySQL Username:</label>
            <input type="text" id="username" name="username" value="root" required>
        </div>
        
        <div class="form-group">
            <label for="password">MySQL Password:</label>
            <input type="password" id="password" name="password" placeholder="Leave empty if no password">
        </div>
        
        <button type="submit">🔌 Test Connection & Setup Database</button>
    </form>
    
    <hr>
    <p><small>
        <strong>Troubleshooting:</strong><br>
        • Make sure MySQL is running: <code>sudo systemctl start mysql</code><br>
        • For XAMPP: Start MySQL in the control panel<br>
        • For Docker: Make sure MySQL container is running<br>
        • Common passwords: empty, "root", "password", "admin"
    </small></p>
    
    <?php endif; ?>
    
    <script>
    function setPreset(host, username, password) {
        document.getElementById('host').value = host;
        document.getElementById('username').value = username;
        document.getElementById('password').value = password;
    }
    </script>
</body>
</html>