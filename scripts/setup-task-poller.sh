#!/bin/bash

#############################################################################
# Claude Task Poller Setup Script
#
# This script installs and configures the Claude Task Poller as a systemd service.
#
# Usage:
#   bash setup-task-poller.sh [project1] [project2] ...
#
# Example:
#   bash setup-task-poller.sh havuncore havunadmin herdenkingsportaal
#
#############################################################################

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Claude Task Poller Setup            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Please run as root (sudo)${NC}"
    exit 1
fi

# Get projects to setup (default: all three)
PROJECTS=("$@")
if [ ${#PROJECTS[@]} -eq 0 ]; then
    PROJECTS=("havuncore" "havunadmin" "herdenkingsportaal")
fi

echo -e "${YELLOW}📦 Projects to setup: ${PROJECTS[*]}${NC}"
echo ""

# Install required packages
echo -e "${BLUE}📥 Installing required packages...${NC}"
apt-get update -qq
apt-get install -y jq curl git > /dev/null 2>&1
echo -e "${GREEN}✅ Required packages installed${NC}"
echo ""

# Copy poller script to /usr/local/bin
echo -e "${BLUE}📁 Installing poller script...${NC}"
cp /var/www/development/HavunCore/scripts/claude-task-poller.sh /usr/local/bin/
chmod +x /usr/local/bin/claude-task-poller.sh
echo -e "${GREEN}✅ Poller script installed to /usr/local/bin/${NC}"
echo ""

# Setup systemd service for each project
for PROJECT in "${PROJECTS[@]}"; do
    echo -e "${BLUE}⚙️  Setting up service for ${PROJECT}...${NC}"

    # Copy service file
    cp /var/www/development/HavunCore/scripts/claude-task-poller.service \
        /etc/systemd/system/claude-task-poller@.service

    # Create log file
    touch "/var/log/claude-task-poller-${PROJECT}.log"
    chmod 644 "/var/log/claude-task-poller-${PROJECT}.log"

    # Enable and start service
    systemctl daemon-reload
    systemctl enable "claude-task-poller@${PROJECT}.service"
    systemctl restart "claude-task-poller@${PROJECT}.service"

    echo -e "${GREEN}✅ Service enabled for ${PROJECT}${NC}"
done

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ Setup complete!${NC}"
echo ""
echo -e "${YELLOW}Service Management:${NC}"
echo ""

for PROJECT in "${PROJECTS[@]}"; do
    echo -e "  ${BLUE}${PROJECT}:${NC}"
    echo "    • Status:  systemctl status claude-task-poller@${PROJECT}"
    echo "    • Logs:    journalctl -u claude-task-poller@${PROJECT} -f"
    echo "    • Stop:    systemctl stop claude-task-poller@${PROJECT}"
    echo "    • Restart: systemctl restart claude-task-poller@${PROJECT}"
    echo ""
done

echo -e "${YELLOW}Current Status:${NC}"
echo ""

for PROJECT in "${PROJECTS[@]}"; do
    STATUS=$(systemctl is-active "claude-task-poller@${PROJECT}.service" || echo "inactive")
    if [ "$STATUS" = "active" ]; then
        echo -e "  ${GREEN}✅ ${PROJECT}: RUNNING${NC}"
    else
        echo -e "  ${RED}❌ ${PROJECT}: STOPPED${NC}"
    fi
done

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}The task pollers are now running and will automatically:"
echo "  • Check for new tasks every 30 seconds"
echo "  • Execute tasks when found"
echo "  • Commit and push changes to GitHub"
echo "  • Report results back to the API"
echo ""
echo "Happy automating! 🤖${NC}"
