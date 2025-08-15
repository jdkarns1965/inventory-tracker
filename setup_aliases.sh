#!/bin/bash
# Setup convenient aliases for OneDrive database backup

ALIAS_FILE="$HOME/.bash_aliases"
PROJECT_DIR="/var/www/html/inventory-tracker"

echo "🔧 Setting up OneDrive backup aliases..."

# Create aliases file if it doesn't exist
touch "$ALIAS_FILE"

# Remove existing aliases if they exist
sed -i '/# Inventory Tracker OneDrive Backup/,/# End Inventory Tracker/d' "$ALIAS_FILE"

# Add new aliases
cat >> "$ALIAS_FILE" << 'EOF'

# Inventory Tracker OneDrive Backup
alias backup-db='cd /var/www/html/inventory-tracker && php simple_backup.php export'
alias restore-db='cd /var/www/html/inventory-tracker && php simple_backup.php import'
alias backup-status='cd /var/www/html/inventory-tracker && php simple_backup.php status'
alias backup-setup='cd /var/www/html/inventory-tracker && php simple_backup.php setup'

# Quick navigation
alias inv='cd /var/www/html/inventory-tracker'
alias inv-logs='sudo tail -f /var/log/apache2/error.log'

# Database utilities
alias db-test='cd /var/www/html/inventory-tracker && php migrate.php test'
alias db-status='cd /var/www/html/inventory-tracker && php migrate.php status'
# End Inventory Tracker

EOF

echo "✅ Aliases added to $ALIAS_FILE"

# Make sure .bash_aliases is sourced in .bashrc
if ! grep -q ".bash_aliases" "$HOME/.bashrc"; then
    echo "" >> "$HOME/.bashrc"
    echo "# Load custom aliases" >> "$HOME/.bashrc"
    echo "if [ -f ~/.bash_aliases ]; then" >> "$HOME/.bashrc"
    echo "    . ~/.bash_aliases" >> "$HOME/.bashrc"
    echo "fi" >> "$HOME/.bashrc"
    echo "✅ Added .bash_aliases loading to .bashrc"
fi

echo ""
echo "🎉 Setup complete! Available commands:"
echo "  backup-db       - Backup database to OneDrive"  
echo "  restore-db      - Restore database from OneDrive"
echo "  backup-status   - Show OneDrive backup status"
echo "  backup-setup    - Initialize OneDrive folder"
echo "  inv             - Navigate to project directory"
echo "  db-test         - Test database connection"
echo "  db-status       - Show database status"
echo ""
echo "💡 Run 'source ~/.bash_aliases' or restart terminal to activate aliases"
echo "💡 First time setup: run 'backup-setup' to initialize OneDrive folder"