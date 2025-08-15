<?php
/**
 * Database Import Script
 * Imports SQL backup file to recreate database in new environment
 * Run this in your new development environment
 */

require_once 'config/database.php';

function importDatabase($host, $username, $password, $database, $sql_file) {
    if (!file_exists($sql_file)) {
        echo "❌ SQL file not found: $sql_file\n";
        return false;
    }
    
    echo "📂 Found SQL file: $sql_file\n";
    echo "📊 File size: " . formatBytes(filesize($sql_file)) . "\n";
    
    // First, create database if it doesn't exist
    $create_db_command = sprintf(
        'mysql -h%s -u%s -p%s -e "CREATE DATABASE IF NOT EXISTS %s;"',
        escapeshellarg($host),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($database)
    );
    
    echo "🔧 Creating database if needed...\n";
    exec($create_db_command, $output, $return_code);
    
    if ($return_code !== 0) {
        echo "❌ Failed to create database\n";
        return false;
    }
    
    // Import the SQL file
    $import_command = sprintf(
        'mysql -h%s -u%s -p%s %s < %s',
        escapeshellarg($host),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($database),
        escapeshellarg($sql_file)
    );
    
    echo "📥 Importing database...\n";
    
    $output = [];
    $return_code = 0;
    exec($import_command, $output, $return_code);
    
    if ($return_code === 0) {
        echo "✅ Database imported successfully!\n";
        
        // Test connection
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "📋 Tables found: " . count($tables) . "\n";
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                echo "  - $table: $count records\n";
            }
            
        } catch (PDOException $e) {
            echo "⚠️  Import completed but connection test failed: " . $e->getMessage() . "\n";
        }
        
        return true;
    } else {
        echo "❌ Import failed with code: $return_code\n";
        echo "Output: " . implode("\n", $output) . "\n";
        return false;
    }
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// CLI usage
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php import_database.php <sql_file>\n";
        echo "Example: php import_database.php inventory_backup_2024-01-15_14-30-25.sql\n";
        exit(1);
    }
    
    $sql_file = $argv[1];
    
    if (!defined('DB_HOST')) {
        echo "❌ Database configuration not found. Please check config/database.php\n";
        exit(1);
    }
    
    echo "🔄 Starting database import...\n";
    echo "Target Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n";
    echo "SQL File: $sql_file\n\n";
    
    $success = importDatabase(DB_HOST, DB_USER, DB_PASS, DB_NAME, $sql_file);
    
    if ($success) {
        echo "\n🎉 Database migration complete!\n";
        echo "You can now access your application with all existing data.\n";
    } else {
        echo "\n💥 Migration failed. Please check the error messages above.\n";
        exit(1);
    }
}
?>