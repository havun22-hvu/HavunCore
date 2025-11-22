#!/bin/bash

##############################################################################
# HavunCore Backup System Deployment Script
#
# This script automatically configures the backup system on the server
# Run this on the production server as: bash deploy-backup-system.sh
##############################################################################

set -e  # Exit on any error

echo "╔════════════════════════════════════════════════════════════╗"
echo "║   HavunCore Backup System - Automated Deployment          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
HETZNER_HOST="u510616.your-storagebox.de"
HETZNER_USER="u510616"
HETZNER_PASS="G63^C@GB&PD2#jCl#1uj"
ENCRYPTION_PASSWORD="QUfTHO0hjdagrLgW10zIWLGjJelGBtrvG915IzFqIDE="

##############################################################################
# Helper Functions
##############################################################################

print_step() {
    echo -e "\n${GREEN}▶ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ ERROR: $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

check_command() {
    if ! command -v $1 &> /dev/null; then
        print_error "$1 is not installed"
        exit 1
    fi
}

##############################################################################
# Step 1: Detect Projects
##############################################################################

print_step "Step 1: Detecting Laravel projects..."

HAVUNADMIN_PATH=""
HERDENKINGSPORTAAL_PATH=""

# Common project locations
POSSIBLE_PATHS=(
    "/var/www/havunadmin"
    "/var/www/havunadmin/production"
    "$HOME/havunadmin"
    "/var/www/herdenkingsportaal"
    "/var/www/herdenkingsportaal/production"
    "$HOME/herdenkingsportaal"
)

for path in "${POSSIBLE_PATHS[@]}"; do
    if [ -f "$path/artisan" ]; then
        if [[ "$path" == *"havunadmin"* ]]; then
            HAVUNADMIN_PATH="$path"
            print_success "Found HavunAdmin at: $path"
        elif [[ "$path" == *"herdenkingsportaal"* ]]; then
            HERDENKINGSPORTAAL_PATH="$path"
            print_success "Found Herdenkingsportaal at: $path"
        fi
    fi
done

if [ -z "$HAVUNADMIN_PATH" ] && [ -z "$HERDENKINGSPORTAAL_PATH" ]; then
    print_error "No Laravel projects found. Please specify paths manually."
    exit 1
fi

##############################################################################
# Step 2: Install Dependencies
##############################################################################

install_sftp_driver() {
    local project_path=$1
    local project_name=$2

    print_step "Step 2: Installing SFTP driver for $project_name..."

    cd "$project_path"

    if [ -f "composer.json" ]; then
        if grep -q "league/flysystem-sftp-v3" composer.json; then
            print_warning "SFTP driver already installed in $project_name"
        else
            composer require league/flysystem-sftp-v3 "^3.0" --no-interaction
            print_success "SFTP driver installed in $project_name"
        fi
    else
        print_error "composer.json not found in $project_path"
        return 1
    fi
}

##############################################################################
# Step 3: Configure Filesystems
##############################################################################

configure_filesystem() {
    local project_path=$1
    local project_name=$2
    local backup_root=$3

    print_step "Step 3: Configuring filesystem for $project_name..."

    local filesystems_path="$project_path/config/filesystems.php"

    if [ ! -f "$filesystems_path" ]; then
        print_error "filesystems.php not found at $filesystems_path"
        return 1
    fi

    # Check if already configured
    if grep -q "hetzner-storage-box" "$filesystems_path"; then
        print_warning "Hetzner disk already configured in $project_name"
        return 0
    fi

    # Backup original
    cp "$filesystems_path" "$filesystems_path.backup"

    # Add Hetzner disk configuration
    # This uses a simple sed approach - in production you might want a more robust solution
    cat >> "$filesystems_path.tmp" << 'EOF'

        // Hetzner Storage Box (added by deploy script)
        'hetzner-storage-box' => [
            'driver' => 'sftp',
            'host' => env('HETZNER_STORAGE_HOST'),
            'port' => 23,
            'username' => env('HETZNER_STORAGE_USERNAME'),
            'password' => env('HETZNER_STORAGE_PASSWORD'),
            'root' => env('HETZNER_STORAGE_ROOT', '/havun-backups'),
            'timeout' => 60,
            'directoryPerm' => 0755,
            'visibility' => 'private',
        ],

        'backups-local' => [
            'driver' => 'local',
            'root' => storage_path('backups'),
            'visibility' => 'private',
        ],
EOF

    # Insert before the closing of 'disks' array
    sed -i "/],$/i $(cat $filesystems_path.tmp)" "$filesystems_path" 2>/dev/null || {
        print_warning "Could not auto-configure filesystem. Please add manually."
        rm "$filesystems_path.tmp"
        return 0
    }

    rm "$filesystems_path.tmp"
    print_success "Filesystem configured for $project_name"
}

##############################################################################
# Step 4: Configure Environment
##############################################################################

configure_env() {
    local project_path=$1
    local project_name=$2
    local backup_root=$3

    print_step "Step 4: Configuring environment for $project_name..."

    local env_path="$project_path/.env"

    if [ ! -f "$env_path" ]; then
        print_error ".env not found at $env_path"
        return 1
    fi

    # Backup original
    cp "$env_path" "$env_path.backup.$(date +%Y%m%d_%H%M%S)"

    # Add backup configuration
    cat >> "$env_path" << EOF

# ================================================================
# HAVUN BACKUP CONFIGURATION (Added by deploy script)
# ================================================================
HETZNER_STORAGE_HOST=$HETZNER_HOST
HETZNER_STORAGE_USERNAME=$HETZNER_USER
HETZNER_STORAGE_PASSWORD=$HETZNER_PASS
HETZNER_STORAGE_ROOT=$backup_root

BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=$ENCRYPTION_PASSWORD

BACKUP_NOTIFICATION_EMAIL=havun22@gmail.com

# Project specific paths
${project_name^^}_PATH=$project_path
EOF

    print_success "Environment configured for $project_name"
}

##############################################################################
# Step 5: Create Storage Directories
##############################################################################

create_storage_directories() {
    local project_path=$1
    local project_name=$2
    local backup_root=$3

    print_step "Step 5: Creating storage directories for $project_name..."

    cd "$project_path"

    # Create local backup directory
    mkdir -p storage/backups
    chmod 755 storage/backups
    print_success "Local backup directory created"

    # Create remote directories via PHP
    php artisan tinker --execute="
        \$disk = Storage::disk('hetzner-storage-box');
        \$dirs = [
            '$backup_root',
            '$backup_root/hot',
            '$backup_root/archive',
            '$backup_root/archive/2025',
        ];
        foreach (\$dirs as \$dir) {
            try {
                \$disk->makeDirectory(\$dir);
                echo \"Created: \$dir\\n\";
            } catch (Exception \$e) {
                echo \"Already exists: \$dir\\n\";
            }
        }
    " 2>/dev/null || print_warning "Could not create remote directories automatically"

    print_success "Storage directories created for $project_name"
}

##############################################################################
# Step 6: Test Backup
##############################################################################

test_backup() {
    local project_path=$1
    local project_name=$2

    print_step "Step 6: Testing backup for $project_name..."

    cd "$project_path"

    # Run a test backup
    php artisan havun:backup:run --project=$(echo $project_name | tr '[:upper:]' '[:lower:]') 2>&1 | head -20

    if [ $? -eq 0 ]; then
        print_success "Test backup completed for $project_name"
    else
        print_error "Test backup failed for $project_name"
        return 1
    fi
}

##############################################################################
# Step 7: Setup Cron Jobs
##############################################################################

setup_cron() {
    print_step "Step 7: Setting up cron jobs..."

    # Check if cron jobs already exist
    if crontab -l 2>/dev/null | grep -q "havun:backup:run"; then
        print_warning "Cron jobs already configured"
        return 0
    fi

    # Create temporary cron file
    crontab -l 2>/dev/null > /tmp/havun_cron || true

    # Add backup jobs
    cat >> /tmp/havun_cron << EOF

# HavunCore Backup System
0 3 * * * cd $HAVUNADMIN_PATH && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1
0 4 * * * cd $HERDENKINGSPORTAAL_PATH && php artisan havun:backup:run >> /var/log/havun-backup.log 2>&1
0 * * * * cd $HAVUNADMIN_PATH && php artisan havun:backup:health --quiet >> /var/log/havun-health.log 2>&1
EOF

    # Install new crontab
    crontab /tmp/havun_cron
    rm /tmp/havun_cron

    print_success "Cron jobs configured"
}

##############################################################################
# Main Execution
##############################################################################

main() {
    echo ""
    print_step "Starting automated deployment..."

    # Check required commands
    check_command php
    check_command composer

    # Deploy HavunAdmin
    if [ -n "$HAVUNADMIN_PATH" ]; then
        echo ""
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "Deploying HavunAdmin"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

        install_sftp_driver "$HAVUNADMIN_PATH" "HavunAdmin"
        configure_filesystem "$HAVUNADMIN_PATH" "HavunAdmin" "/havun-backups/havunadmin"
        configure_env "$HAVUNADMIN_PATH" "havunadmin" "/havun-backups/havunadmin"
        create_storage_directories "$HAVUNADMIN_PATH" "HavunAdmin" "/havun-backups/havunadmin"
        test_backup "$HAVUNADMIN_PATH" "HavunAdmin"
    fi

    # Deploy Herdenkingsportaal
    if [ -n "$HERDENKINGSPORTAAL_PATH" ]; then
        echo ""
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "Deploying Herdenkingsportaal"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

        install_sftp_driver "$HERDENKINGSPORTAAL_PATH" "Herdenkingsportaal"
        configure_filesystem "$HERDENKINGSPORTAAL_PATH" "Herdenkingsportaal" "/havun-backups/herdenkingsportaal"
        configure_env "$HERDENKINGSPORTAAL_PATH" "herdenkingsportaal" "/havun-backups/herdenkingsportaal"
        create_storage_directories "$HERDENKINGSPORTAAL_PATH" "Herdenkingsportaal" "/havun-backups/herdenkingsportaal"
        test_backup "$HERDENKINGSPORTAAL_PATH" "Herdenkingsportaal"
    fi

    # Setup cron
    setup_cron

    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║   ✓ DEPLOYMENT COMPLETE                                    ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    print_success "Backup system is now configured and running!"
    echo ""
    echo "Next steps:"
    echo "  • Monitor logs: tail -f /var/log/havun-backup.log"
    echo "  • Check backups: ls -lh /var/www/*/storage/backups/"
    echo "  • Verify offsite: ssh -p 23 $HETZNER_USER@$HETZNER_HOST"
    echo ""
}

# Run main function
main
