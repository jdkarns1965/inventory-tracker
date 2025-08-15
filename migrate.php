<?php
/**
 * Migration Utility Script
 * One-stop script for database migration tasks
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

function showHelp() {
    echo "Inventory Tracker Migration Utility\n";
    echo "===================================\n\n";
    echo "Usage: php migrate.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  export [filename]     Export current database to SQL file\n";
    echo "  import <filename>     Import SQL file to database\n";
    echo "  test                  Test database connection\n";
    echo "  status                Show database status\n";
    echo "  help                  Show this help message\n\n";
    echo "Examples:\n";
    echo "  php migrate.php export                    # Export with timestamp\n";
    echo "  php migrate.php export my_backup.sql     # Export to specific file\n";
    echo "  php migrate.php import backup.sql        # Import from backup file\n";
    echo "  php migrate.php test                      # Test connection\n";
    echo "  php migrate.php status                    # Show database info\n\n";
}

function testConnection() {
    if (!file_exists('config/database.php')) {
        echo "❌ config/database.php not found\n";
        return false;
    }
    
    require_once 'config/database.php';
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        echo "✅ Database server connection: OK\n";
        
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Database '" . DB_NAME . "' exists: OK\n";
            
            // Connect to specific database
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            
            // Count tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "📋 Tables found: " . count($tables) . "\n";
            
            if (count($tables) > 0) {
                echo "✅ Database appears to be set up\n";
                return true;
            } else {
                echo "⚠️  Database exists but has no tables\n";
                echo "💡 Run: php install.php to create tables\n";
                return false;
            }
        } else {
            echo "❌ Database '" . DB_NAME . "' does not exist\n";
            echo "💡 Run import script or install.php to create it\n";
            return false;
        }
    } catch (PDOException $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function showStatus() {
    if (!file_exists('config/database.php')) {
        echo "❌ config/database.php not found\n";
        return;
    }
    
    require_once 'config/database.php';
    
    echo "Database Configuration\n";
    echo "=====================\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "Password: " . (DB_PASS ? str_repeat('*', strlen(DB_PASS)) : '(empty)') . "\n\n";
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        
        echo "Database Statistics\n";
        echo "==================\n";
        
        // Get all tables and their row counts
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $total_records = 0;
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            $total_records += $count;
            echo sprintf("  %-20s %6d records\n", $table, $count);
        }
        
        echo str_repeat("-", 30) . "\n";
        echo sprintf("  %-20s %6d total\n", "TOTAL RECORDS:", $total_records);
        
        // Get database size
        $stmt = $pdo->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'size_mb' 
            FROM information_schema.tables 
            WHERE table_schema = '" . DB_NAME . "'
        ");
        $size = $stmt->fetchColumn();
        echo sprintf("  %-20s %6.1f MB\n", "DATABASE SIZE:", $size);
        
    } catch (PDOException $e) {
        echo "❌ Could not connect to database: " . $e->getMessage() . "\n";
    }
}

// Main script execution
if ($argc < 2) {
    showHelp();
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'export':
        $filename = isset($argv[2]) ? $argv[2] : null;
        require_once 'export_database.php';
        break;
        
    case 'import':
        if (!isset($argv[2])) {
            echo "❌ Please specify SQL file to import\n";
            echo "Usage: php migrate.php import <filename>\n";
            exit(1);
        }
        $filename = $argv[2];
        require_once 'import_database.php';
        break;
        
    case 'test':
        echo "🔍 Testing database connection...\n\n";
        if (testConnection()) {
            echo "\n🎉 Database connection test passed!\n";
        } else {
            echo "\n💥 Database connection test failed!\n";
            exit(1);
        }
        break;
        
    case 'status':
        showStatus();
        break;
        
    case 'help':
    case '--help':
    case '-h':
        showHelp();
        break;
        
    default:
        echo "❌ Unknown command: $command\n\n";
        showHelp();
        exit(1);
}
?>