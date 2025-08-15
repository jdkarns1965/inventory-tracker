<?php
/**
 * OneDrive Database Backup Script
 * Automatically exports database to OneDrive folder for sync between environments
 */

require_once 'config/database.php';

class OneDriveBackup {
    private $onedrive_paths;
    
    private $backup_folder = 'inventory-tracker-backups';
    
    public function __construct() {
        $current_user = get_current_user();
        $this->onedrive_paths = [
            "/mnt/c/Users/$current_user/OneDrive",
            "/mnt/c/Users/$current_user/OneDrive - Your Company", // Office 365 format
            '/mnt/c/OneDrive',
            '/mnt/c/OneDrive - Your Company'
        ];
    }
    
    public function findOneDrivePath() {
        // Try to find OneDrive folder
        foreach ($this->onedrive_paths as $path) {
            if (is_dir($path)) {
                echo "✅ Found OneDrive at: $path\n";
                return $path;
            }
        }
        
        echo "❌ OneDrive folder not found. Trying common Windows paths...\n";
        
        // Try to find via Windows username
        $cmd = 'powershell.exe -Command "echo $env:USERNAME"';
        $win_user = trim(shell_exec($cmd));
        
        if ($win_user) {
            $win_paths = [
                "/mnt/c/Users/$win_user/OneDrive",
                "/mnt/c/Users/$win_user/OneDrive - *"
            ];
            
            foreach ($win_paths as $path) {
                $matches = glob($path);
                if (!empty($matches)) {
                    $found_path = $matches[0];
                    echo "✅ Found OneDrive at: $found_path\n";
                    return $found_path;
                }
            }
        }
        
        return false;
    }
    
    public function createBackupFolder() {
        $onedrive_path = $this->findOneDrivePath();
        if (!$onedrive_path) {
            echo "Please specify your OneDrive path manually.\n";
            echo "Common paths:\n";
            echo "  /mnt/c/Users/YourUsername/OneDrive\n";
            echo "  /mnt/c/Users/YourUsername/OneDrive - YourCompany\n";
            return false;
        }
        
        $backup_dir = $onedrive_path . '/' . $this->backup_folder;
        
        if (!is_dir($backup_dir)) {
            if (mkdir($backup_dir, 0755, true)) {
                echo "✅ Created backup directory: $backup_dir\n";
            } else {
                echo "❌ Failed to create backup directory: $backup_dir\n";
                return false;
            }
        } else {
            echo "✅ Backup directory exists: $backup_dir\n";
        }
        
        return $backup_dir;
    }
    
    public function exportToOneDrive($keep_backups = 5) {
        $backup_dir = $this->createBackupFolder();
        if (!$backup_dir) {
            return false;
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $location = php_uname('n'); // Computer name
        $filename = "inventory_backup_{$location}_{$timestamp}.sql";
        $filepath = $backup_dir . '/' . $filename;
        
        echo "🔄 Exporting database to OneDrive...\n";
        echo "Location: $location\n";
        echo "File: $filename\n";
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($filepath)
        );
        
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            echo "✅ Database exported successfully!\n";
            echo "File: $filepath\n";
            echo "Size: " . $this->formatBytes(filesize($filepath)) . "\n";
            
            // Create a "latest" symlink for easy importing
            $latest_link = $backup_dir . '/latest.sql';
            if (file_exists($latest_link)) {
                unlink($latest_link);
            }
            symlink($filename, $latest_link);
            echo "✅ Created latest.sql link\n";
            
            // Clean up old backups
            $this->cleanupOldBackups($backup_dir, $keep_backups);
            
            // Show sync status
            echo "\n💡 OneDrive Sync Status:\n";
            echo "   Your backup will sync to all devices with OneDrive\n";
            echo "   At home, run: php onedrive_backup.php import\n";
            
            return $filepath;
        } else {
            echo "❌ Export failed with code: $return_code\n";
            return false;
        }
    }
    
    public function importFromOneDrive() {
        $backup_dir = $this->createBackupFolder();
        if (!$backup_dir) {
            return false;
        }
        
        $latest_file = $backup_dir . '/latest.sql';
        
        if (!file_exists($latest_file)) {
            echo "❌ No backup found in OneDrive\n";
            echo "Looking for SQL files in: $backup_dir\n";
            
            $sql_files = glob($backup_dir . '/*.sql');
            $sql_files = array_filter($sql_files, function($file) {
                return basename($file) !== 'latest.sql';
            });
            
            if (empty($sql_files)) {
                echo "❌ No backup files found\n";
                return false;
            }
            
            // Sort by modification time, newest first
            usort($sql_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            echo "📋 Available backups:\n";
            foreach ($sql_files as $i => $file) {
                $basename = basename($file);
                $date = date('Y-m-d H:i:s', filemtime($file));
                echo "  " . ($i + 1) . ". $basename ($date)\n";
            }
            
            $latest_file = $sql_files[0];
            echo "\n🔄 Using most recent: " . basename($latest_file) . "\n";
        }
        
        echo "📥 Importing from OneDrive...\n";
        echo "File: " . basename($latest_file) . "\n";
        echo "Size: " . $this->formatBytes(filesize($latest_file)) . "\n";
        
        // Use the existing import functionality
        require_once 'import_database.php';
        return importDatabase(DB_HOST, DB_USER, DB_PASS, DB_NAME, $latest_file);
    }
    
    private function cleanupOldBackups($backup_dir, $keep_count) {
        $sql_files = glob($backup_dir . '/inventory_backup_*.sql');
        
        if (count($sql_files) <= $keep_count) {
            return;
        }
        
        // Sort by modification time, oldest first
        usort($sql_files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $delete_count = count($sql_files) - $keep_count;
        for ($i = 0; $i < $delete_count; $i++) {
            $file = $sql_files[$i];
            if (unlink($file)) {
                echo "🗑️  Removed old backup: " . basename($file) . "\n";
            }
        }
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    public function showStatus() {
        $backup_dir = $this->createBackupFolder();
        if (!$backup_dir) {
            return;
        }
        
        echo "OneDrive Backup Status\n";
        echo "=====================\n";
        echo "Backup Directory: $backup_dir\n";
        
        $sql_files = glob($backup_dir . '/*.sql');
        $sql_files = array_filter($sql_files, function($file) {
            return basename($file) !== 'latest.sql';
        });
        
        if (empty($sql_files)) {
            echo "📦 No backups found\n";
            return;
        }
        
        echo "📋 Available Backups (" . count($sql_files) . "):\n";
        
        // Sort by modification time, newest first
        usort($sql_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        foreach ($sql_files as $file) {
            $basename = basename($file);
            $size = $this->formatBytes(filesize($file));
            $date = date('Y-m-d H:i:s', filemtime($file));
            echo "  📁 $basename ($size) - $date\n";
        }
        
        $latest_file = $backup_dir . '/latest.sql';
        if (file_exists($latest_file)) {
            $target = readlink($latest_file);
            echo "\n🔗 Latest points to: $target\n";
        }
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $backup = new OneDriveBackup();
    
    $command = isset($argv[1]) ? $argv[1] : 'export';
    
    switch ($command) {
        case 'export':
        case 'backup':
            $backup->exportToOneDrive();
            break;
            
        case 'import':
        case 'restore':
            $backup->importFromOneDrive();
            break;
            
        case 'status':
            $backup->showStatus();
            break;
            
        case 'setup':
            $backup->createBackupFolder();
            echo "\n💡 OneDrive backup is ready!\n";
            echo "Commands:\n";
            echo "  php onedrive_backup.php export  - Backup to OneDrive\n";
            echo "  php onedrive_backup.php import  - Restore from OneDrive\n";
            echo "  php onedrive_backup.php status  - Show backup status\n";
            break;
            
        default:
            echo "OneDrive Database Backup\n";
            echo "========================\n\n";
            echo "Usage: php onedrive_backup.php <command>\n\n";
            echo "Commands:\n";
            echo "  export   Backup database to OneDrive\n";
            echo "  import   Restore database from OneDrive\n";
            echo "  status   Show backup status\n";
            echo "  setup    Initialize OneDrive backup folder\n";
            break;
    }
}
?>