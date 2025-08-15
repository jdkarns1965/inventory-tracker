<?php
/**
 * OneDrive Configuration
 * Set your OneDrive path here for database backups
 */

// Manually set your OneDrive path here
// Common paths for Windows 11 + WSL2:
// '/mnt/c/Users/YourUsername/OneDrive'
// '/mnt/c/Users/YourUsername/OneDrive - CompanyName'

// Your actual OneDrive path (converted to WSL2 format)
define('ONEDRIVE_PATH', '/mnt/c/Users/SeanKarns/OneDrive - Greenfield Precision Plastics, LLC');

// Alternative: uncomment and modify the line below if you have Office 365
// define('ONEDRIVE_PATH', '/mnt/c/Users/jdkarns/OneDrive - YourCompanyName');

// Backup folder name within OneDrive
define('BACKUP_FOLDER', 'inventory-tracker-backups');

// How many backup files to keep (oldest are auto-deleted)
define('KEEP_BACKUPS', 5);
?>