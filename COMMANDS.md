# Quick Command Reference

## 🚀 Database Backup Commands (OneDrive Sync)
```bash
backup-db       # Export database to OneDrive
restore-db      # Import latest backup from OneDrive  
backup-status   # Show OneDrive backup information
backup-setup    # Initialize OneDrive backup folder
```

## 📁 Navigation & Utilities
```bash
inv             # Navigate to project directory (/var/www/html/inventory-tracker)
inv-logs        # View Apache error logs
db-test         # Test database connection
db-status       # Show database statistics
```

## 🔄 Git Workflow
```bash
git pull        # Get latest code changes
git add .       # Stage all changes
git commit -m "message"  # Commit changes
git push        # Push to GitHub
```

## 🏠 Home Setup (First Time)
```bash
git clone https://github.com/jdkarns1965/inventory-tracker.git
cd inventory-tracker
./setup_aliases.sh     # Install aliases
restore-db              # Import latest database
```

## 📋 Daily Workflow

### Office → Home
1. `backup-db` (export database)
2. `git push` (push code changes)

### Home → Office  
1. `git pull` (get latest code)
2. `restore-db` (get latest database)

## 🆘 Help Commands
```bash
php simple_backup.php          # Show backup help
php migrate.php help           # Show migration help
alias | grep inventory         # Show all inventory aliases
cat ~/.bash_aliases            # View all custom aliases
```

## 📂 Important Paths
- **Project:** `/var/www/html/inventory-tracker`
- **OneDrive Backups:** `OneDrive - Greenfield Precision Plastics, LLC/inventory-tracker-backups/`
- **GitHub:** `https://github.com/jdkarns1965/inventory-tracker`