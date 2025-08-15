<?php
/**
 * Simple OneDrive Backup Script
 * Easy-to-use backup/restore with manual OneDrive path configuration
 */

require_once 'config/database.php';

// Check if OneDrive config exists
if (file_exists('config/onedrive.php')) {
    require_once 'config/onedrive.php';
} else {
    echo "❌ OneDrive configuration not found!\n";
    echo "Please edit config/onedrive.php to set your OneDrive path.\n";
    exit(1);
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function createBackupDir() {
    $backup_path = ONEDRIVE_PATH . '/' . BACKUP_FOLDER;
    
    if (!is_dir(ONEDRIVE_PATH)) {
        echo "❌ OneDrive path not found: " . ONEDRIVE_PATH . "\n";
        echo "Please check your OneDrive path in config/onedrive.php\n";
        return false;
    }
    
    if (!is_dir($backup_path)) {
        if (mkdir($backup_path, 0755, true)) {
            echo "✅ Created backup directory: $backup_path\n";
        } else {
            echo "❌ Failed to create backup directory: $backup_path\n";
            return false;
        }
    }
    
    return $backup_path;
}

function exportDatabase() {
    $backup_dir = createBackupDir();
    if (!$backup_dir) return false;
    
    $timestamp = date('Y-m-d_H-i-s');
    $location = php_uname('n'); // Computer name  
    $filename = "inventory_backup_{$location}_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;
    
    echo "🔄 Exporting database to OneDrive...\n";
    echo "Location: $location\n";
    echo "OneDrive: " . ONEDRIVE_PATH . "\n";
    echo "File: $filename\n";
    
    $command = sprintf(
        'mysqldump -h%s -u%s -p%s %s > %s 2>/dev/null',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );
    
    $return_code = 0;
    system($command, $return_code);
    
    if ($return_code === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        echo "✅ Database exported successfully!\n";
        echo "File: $filepath\n";
        echo "Size: " . formatBytes(filesize($filepath)) . "\n";
        
        // Create latest.sql link
        $latest_link = $backup_dir . '/latest.sql';
        if (file_exists($latest_link)) unlink($latest_link);
        copy($filepath, $latest_link);
        echo "✅ Created latest.sql copy\n";
        
        // Cleanup old backups
        cleanupOldBackups($backup_dir);
        
        echo "\n💡 Backup complete! OneDrive will sync this file to all your devices.\n";
        echo "   At home: php simple_backup.php import\n";
        
        return true;
    } else {
        echo "❌ Export failed!\n";
        if (file_exists($filepath)) {
            echo "File size: " . filesize($filepath) . " bytes\n";
        }
        return false;
    }
}

function importDatabase() {
    $backup_dir = createBackupDir();
    if (!$backup_dir) return false;
    
    $latest_file = $backup_dir . '/latest.sql';
    
    if (!file_exists($latest_file)) {
        echo "❌ No latest backup found: $latest_file\n";
        
        // Look for other backup files
        $backups = glob($backup_dir . '/inventory_backup_*.sql');
        if (empty($backups)) {
            echo "❌ No backup files found in OneDrive\n";
            return false;
        }
        
        // Use most recent
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latest_file = $backups[0];
        echo "🔍 Using most recent backup: " . basename($latest_file) . "\n";
    }
    
    echo "📥 Importing database from OneDrive...\n";
    echo "File: " . basename($latest_file) . "\n";
    echo "Size: " . formatBytes(filesize($latest_file)) . "\n";
    
    // Create database if needed
    $create_cmd = sprintf(
        'mysql -h%s -u%s -p%s -e "CREATE DATABASE IF NOT EXISTS %s;" 2>/dev/null',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME)
    );
    system($create_cmd);
    
    // Import database
    $import_cmd = sprintf(
        'mysql -h%s -u%s -p%s %s < %s 2>/dev/null',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($latest_file)
    );
    
    $return_code = 0;
    system($import_cmd, $return_code);
    
    if ($return_code === 0) {
        echo "✅ Database imported successfully!\n";
        
        // Show what was imported
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "📋 Tables imported: " . count($tables) . "\n";
            $total_records = 0;
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $total_records += $count;
            }
            echo "📊 Total records: $total_records\n";
            
        } catch (PDOException $e) {
            echo "⚠️ Import completed but verification failed\n";
        }
        
        echo "\n🎉 Database restoration complete!\n";
        return true;
    } else {
        echo "❌ Import failed!\n";
        return false;
    }
}

function cleanupOldBackups($backup_dir) {
    $backups = glob($backup_dir . '/inventory_backup_*.sql');
    if (count($backups) <= KEEP_BACKUPS) return;
    
    usort($backups, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $delete_count = count($backups) - KEEP_BACKUPS;
    for ($i = 0; $i < $delete_count; $i++) {
        if (unlink($backups[$i])) {
            echo "🗑️ Removed old backup: " . basename($backups[$i]) . "\n";
        }
    }
}

function showStatus() {
    $backup_dir = createBackupDir();
    if (!$backup_dir) return;
    
    echo "OneDrive Backup Status\n";
    echo "=====================\n";
    echo "OneDrive Path: " . ONEDRIVE_PATH . "\n";
    echo "Backup Dir: $backup_dir\n";
    echo "Keep Backups: " . KEEP_BACKUPS . "\n\n";
    
    $backups = glob($backup_dir . '/*.sql');
    if (empty($backups)) {
        echo "📦 No backups found\n";
        return;
    }
    
    echo "📋 Available Backups:\n";
    usort($backups, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($backups as $backup) {
        $name = basename($backup);
        $size = formatBytes(filesize($backup));
        $date = date('Y-m-d H:i:s', filemtime($backup));
        $latest = ($name === 'latest.sql') ? ' (latest)' : '';
        echo "  📁 $name ($size) - $date$latest\n";
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $command = isset($argv[1]) ? $argv[1] : 'help';
    
    switch ($command) {
        case 'export':
        case 'backup':
            exportDatabase();
            break;
            
        case 'import':  
        case 'restore':
            importDatabase();
            break;
            
        case 'status':
            showStatus();
            break;
            
        case 'setup':
            $backup_dir = createBackupDir();
            if ($backup_dir) {
                echo "✅ OneDrive backup setup complete!\n";
                echo "Ready to use: php simple_backup.php export\n";
            }
            break;
            
        default:
            echo "Simple OneDrive Database Backup\n";
            echo "===============================\n\n";
            echo "Commands:\n";
            echo "  php simple_backup.php export  - Backup database to OneDrive\n";
            echo "  php simple_backup.php import  - Restore from OneDrive backup\n";
            echo "  php simple_backup.php status  - Show backup information\n";
            echo "  php simple_backup.php setup   - Initialize backup directory\n\n";
            echo "Configuration: config/onedrive.php\n";
            break;
    }
}
?>