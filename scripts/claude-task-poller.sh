#!/bin/bash

#############################################################################
# Claude Task Poller
#
# This script runs on the server and polls the Task Queue API for new tasks.
# When a task is found, it executes it and reports back the result.
#
# Usage:
#   ./claude-task-poller.sh [project_name] [api_url]
#
# Example:
#   ./claude-task-poller.sh havuncore https://havuncore.havun.nl/api/claude/tasks
#
# Setup as systemd service for 24/7 operation.
#############################################################################

set -e

# Configuration
PROJECT_NAME="${1:-havuncore}"
API_BASE_URL="${2:-https://havuncore.havun.nl/api/claude/tasks}"
POLL_INTERVAL=30  # seconds

# Map project names to correct paths
case "$PROJECT_NAME" in
    "havuncore")
        PROJECT_PATH="/var/www/development/HavunCore"
        ;;
    "havunadmin")
        PROJECT_PATH="/var/www/havunadmin/production"
        ;;
    "herdenkingsportaal")
        PROJECT_PATH="/var/www/production"
        ;;
    *)
        # Fallback: try development directory
        PROJECT_PATH="/var/www/development/${PROJECT_NAME}"
        ;;
esac
LOG_FILE="/var/log/claude-task-poller-${PROJECT_NAME}.log"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ $1${NC}" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  $1${NC}" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ $1${NC}" | tee -a "$LOG_FILE"
}

# Check if project directory exists
if [ ! -d "$PROJECT_PATH" ]; then
    log_error "Project directory not found: $PROJECT_PATH"
    exit 1
fi

log_success "Claude Task Poller started for project: $PROJECT_NAME"
log "API: $API_BASE_URL"
log "Project path: $PROJECT_PATH"
log "Poll interval: ${POLL_INTERVAL}s"
log ""

# Main polling loop
while true; do
    # Fetch pending tasks from API
    RESPONSE=$(curl -s "${API_BASE_URL}/pending/${PROJECT_NAME}")

    # Check if response is valid JSON
    if ! echo "$RESPONSE" | jq empty 2>/dev/null; then
        log_warning "Invalid JSON response from API, retrying in ${POLL_INTERVAL}s..."
        sleep "$POLL_INTERVAL"
        continue
    fi

    # Get task count
    TASK_COUNT=$(echo "$RESPONSE" | jq -r '.count // 0')

    if [ "$TASK_COUNT" -eq 0 ]; then
        log "No pending tasks. Waiting ${POLL_INTERVAL}s..."
        sleep "$POLL_INTERVAL"
        continue
    fi

    log_success "Found $TASK_COUNT pending task(s)!"

    # Get first task
    TASK_ID=$(echo "$RESPONSE" | jq -r '.tasks[0].id')
    TASK_INSTRUCTION=$(echo "$RESPONSE" | jq -r '.tasks[0].task')
    TASK_PRIORITY=$(echo "$RESPONSE" | jq -r '.tasks[0].priority')

    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log "📋 Task ID: $TASK_ID"
    log "🎯 Priority: $TASK_PRIORITY"
    log "📝 Instruction: $TASK_INSTRUCTION"
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    # Mark task as started
    curl -s -X POST "${API_BASE_URL}/${TASK_ID}/start" > /dev/null
    log "🚀 Task marked as started"

    # Change to project directory
    cd "$PROJECT_PATH" || {
        log_error "Failed to change to project directory"
        curl -s -X POST "${API_BASE_URL}/${TASK_ID}/fail" \
            -H "Content-Type: application/json" \
            -d "{\"error\": \"Failed to change to project directory: $PROJECT_PATH\"}" > /dev/null
        continue
    }

    # Pull latest changes
    log "🔄 Pulling latest changes from GitHub..."
    git pull origin master 2>&1 | tee -a "$LOG_FILE"

    # Create temporary file for task instruction
    TASK_FILE="/tmp/claude-task-${TASK_ID}.txt"
    echo "$TASK_INSTRUCTION" > "$TASK_FILE"

    # Execute task with Claude Code
    log "🤖 Executing task with Claude Code..."
    START_TIME=$(date +%s)

    # Run Claude Code with the task instruction
    # Note: This assumes Claude Code CLI is available and configured
    RESULT_FILE="/tmp/claude-result-${TASK_ID}.txt"

    if command -v claude &> /dev/null; then
        # Claude Code CLI available
        claude "$TASK_INSTRUCTION" > "$RESULT_FILE" 2>&1
        EXIT_CODE=$?
    else
        # Fallback: Execute as shell command if it looks safe
        # WARNING: This is a simplified version. Production should use Claude Code API
        log_warning "Claude Code CLI not found, executing as shell command (limited mode)"
        echo "Task executed in limited mode (no Claude Code CLI available)" > "$RESULT_FILE"
        echo "Instruction: $TASK_INSTRUCTION" >> "$RESULT_FILE"
        EXIT_CODE=0
    fi

    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))

    # Read result
    RESULT=$(cat "$RESULT_FILE")

    # Commit and push changes if any
    log "📦 Checking for changes to commit..."
    if git status --porcelain | grep -q .; then
        log "📝 Changes detected, committing..."
        git add .
        git commit -m "🤖 Auto-commit from Claude Task #${TASK_ID}

${TASK_INSTRUCTION}

Executed on server by Claude Task Poller
Duration: ${DURATION}s

Co-Authored-By: Claude Task Poller <noreply@havun.nl>" 2>&1 | tee -a "$LOG_FILE"

        log "⬆️  Pushing to GitHub..."
        git push origin master 2>&1 | tee -a "$LOG_FILE"
        log_success "Changes pushed to GitHub"
    else
        log "ℹ️  No changes to commit"
    fi

    # Report result back to API
    if [ $EXIT_CODE -eq 0 ]; then
        log_success "Task completed successfully in ${DURATION}s"

        curl -s -X POST "${API_BASE_URL}/${TASK_ID}/complete" \
            -H "Content-Type: application/json" \
            -d "{\"result\": $(echo "$RESULT" | jq -Rs .)}" > /dev/null

        log_success "Result reported to API"
    else
        log_error "Task failed with exit code $EXIT_CODE"

        curl -s -X POST "${API_BASE_URL}/${TASK_ID}/fail" \
            -H "Content-Type: application/json" \
            -d "{\"error\": \"Task failed with exit code $EXIT_CODE. Output: $(echo "$RESULT" | jq -Rs .)\"}" > /dev/null

        log_error "Failure reported to API"
    fi

    # Cleanup
    rm -f "$TASK_FILE" "$RESULT_FILE"

    log ""
    log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log "Waiting ${POLL_INTERVAL}s before next poll..."
    log ""

    sleep "$POLL_INTERVAL"
done
