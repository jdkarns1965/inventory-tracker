<?php
/**
 * Database Export Script
 * Exports current database structure and data to SQL file
 * Run this before moving to new environment
 */

require_once 'config/database.php';

function exportDatabase($host, $username, $password, $database, $filename = null) {
    if ($filename === null) {
        $filename = 'inventory_backup_' . date('Y-m-d_H-i-s') . '.sql';
    }
    
    $command = sprintf(
        'mysqldump -h%s -u%s -p%s %s > %s',
        escapeshellarg($host),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($database),
        escapeshellarg($filename)
    );
    
    echo "Exporting database to: $filename\n";
    
    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);
    
    if ($return_code === 0) {
        echo "✅ Database exported successfully!\n";
        echo "File: $filename\n";
        echo "Size: " . formatBytes(filesize($filename)) . "\n";
        return $filename;
    } else {
        echo "❌ Export failed with code: $return_code\n";
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
    if (!defined('DB_HOST')) {
        echo "❌ Database configuration not found. Please check config/database.php\n";
        exit(1);
    }
    
    echo "🔄 Starting database export...\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n";
    
    $filename = exportDatabase(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($filename) {
        echo "\n📁 To use this backup:\n";
        echo "1. Copy $filename to your new environment\n";
        echo "2. Run: php import_database.php $filename\n";
        echo "3. Or manually: mysql -u username -p database_name < $filename\n";
    }
}
?>