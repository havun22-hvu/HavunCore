#!/bin/bash
# Havun Health Check — monitors all public apps + reverb broadcasting
# Runs via cron every 5 minutes
# Uses -k flag because server-to-self SSL can have cert issues
#
# SOURCE OF TRUTH: HavunCore/scripts/havun-health-check.sh (version controlled)
# DEPLOY TARGET:   /usr/local/bin/havun-health-check.sh on 188.245.159.115
# After editing here: scp to the server and verify with a manual run.

STATE_DIR="/var/run/havun-health"
LOCK_MINUTES=60
LOG_FILE="/var/log/havun-health.log"

mkdir -p "$STATE_DIR"

check_url() {
    local name="$1"
    local url="$2"
    local state_file="$STATE_DIR/$name"

    status=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 10 "$url" 2>/dev/null)

    if [ "$status" -ge 200 ] && [ "$status" -lt 400 ]; then
        # UP — if was down, send recovery alert
        if [ -f "$state_file" ]; then
            rm -f "$state_file"
            php /usr/local/bin/havun-health-alert.php "$name" "$url" "$status" "up" 2>/dev/null
            echo "$(date '+%Y-%m-%d %H:%M:%S') [OK] $name hersteld (HTTP $status)" >> "$LOG_FILE"
        fi
        return 0
    fi

    echo "$(date '+%Y-%m-%d %H:%M:%S') [DOWN] $name (HTTP $status)" >> "$LOG_FILE"

    # Check if we already alerted recently
    if [ -f "$state_file" ]; then
        last_alert=$(cat "$state_file")
        now=$(date +%s)
        diff=$(( (now - last_alert) / 60 ))
        [ "$diff" -lt "$LOCK_MINUTES" ] && return 1
    fi

    # Send alert via PHP/Laravel mail
    php /usr/local/bin/havun-health-alert.php "$name" "$url" "$status" "down" 2>/dev/null

    date +%s > "$state_file"
    return 1
}

# Reverb broadcasting health — supervisor-managed (JudoToernooi + future projects).
# The check_url checks above only see the website (HTTP); reverb runs on its own
# ports (8080/8081) and can sit FATAL while the site stays up — exactly the
# blind spot that hid the 4-6 June 2026 outage. See reverb-troubleshoot.md §6.
check_reverb() {
    local name="reverb"
    local state_file="$STATE_DIR/$name"

    # All supervisor reverb processes that are NOT running (FATAL/BACKOFF/STOPPED/EXITED)
    local bad
    bad=$(supervisorctl status 2>/dev/null | grep -i reverb | grep -iv 'RUNNING' \
        | awk '{print $1"("$2")"}' | paste -sd ', ' -)

    if [ -z "$bad" ]; then
        # All reverb processes running — if previously down, send recovery alert
        if [ -f "$state_file" ]; then
            rm -f "$state_file"
            php /usr/local/bin/havun-health-alert.php "$name" "supervisor reverb processen" "RUNNING" "up" 2>/dev/null
            echo "$(date '+%Y-%m-%d %H:%M:%S') [OK] reverb hersteld" >> "$LOG_FILE"
        fi
        return 0
    fi

    echo "$(date '+%Y-%m-%d %H:%M:%S') [DOWN] reverb: $bad" >> "$LOG_FILE"

    if [ -f "$state_file" ]; then
        last_alert=$(cat "$state_file")
        now=$(date +%s)
        diff=$(( (now - last_alert) / 60 ))
        [ "$diff" -lt "$LOCK_MINUTES" ] && return 1
    fi

    # Fix hint travels in the URL field so it shows up in the alert mail body
    php /usr/local/bin/havun-health-alert.php "$name" \
        "$bad — fix: supervisorctl restart reverb reverb-staging (zie reverb-troubleshoot.md)" \
        "FATAL" "down" 2>/dev/null

    date +%s > "$state_file"
    return 1
}

# Check all public apps
check_url "HavunCore"          "https://havuncore.havun.nl/health"
check_url "Herdenkingsportaal" "https://herdenkingsportaal.nl"
check_url "JudoToernooi"       "https://judotournament.org"
check_url "HavunAdmin"         "https://havunadmin.havun.nl"
check_url "SafeHavun"          "https://safehavun.havun.nl"
check_url "Infosyst"           "https://infosyst.havun.nl"

# Check reverb broadcasting (separate from the website checks above)
check_reverb
