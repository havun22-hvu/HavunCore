#!/bin/bash
# Havun Health Check — monitors all public apps + reverb broadcasting
# Runs via cron every 5 minutes
# Uses -k flag because server-to-self SSL can have cert issues
#
# SOURCE OF TRUTH: HavunCore/scripts/havun-health-check.sh (version controlled)
# DEPLOY TARGET:   /usr/local/bin/havun-health-check.sh on 188.245.159.115
# After editing here: scp to the server and verify with a manual run.
#
# NO EMAIL. Alerts are recorded in-app via `php artisan health:alert` and surface
# in the HavunCore webapp notification panel (server/general) and, in a later
# phase, in each project's own app. External total-outage coverage is handled by
# UptimeRobot. See docs/kb/runbooks/uptime-monitoring.md.

LOG_FILE="/var/log/havun-health.log"
HAVUNCORE_DIR="/var/www/havuncore/production"

# Record an in-app alert via HavunCore (no email). Runs artisan as www-data so
# cache/logs stay owned correctly. Stateless: the health_alerts table dedupes
# (down upserts an open alert, up resolves it — up on a healthy key is a no-op).
emit_alert() {  # key scope project status severity title
    local out rc
    out=$( cd "$HAVUNCORE_DIR" && sudo -u www-data php artisan health:alert "$1" \
        --scope="$2" --project="$3" --status="$4" --severity="$5" --title="$6" 2>&1 )
    rc=$?
    # Don't swallow failures silently — a broken alert path is why an outage can go unseen.
    if [ "$rc" -ne 0 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') [ALERT-FAIL rc=$rc] $1: $(echo "$out" | tr '\n' ' ')" >> "$LOG_FILE"
    fi
}

check_url() {  # name url scope project
    local name="$1" url="$2" scope="$3" project="$4"

    status=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 10 "$url" 2>/dev/null)

    if [ "$status" -ge 200 ] && [ "$status" -lt 400 ]; then
        emit_alert "$name" "$scope" "$project" "up" "info" "$name is bereikbaar"
        return 0
    fi

    echo "$(date '+%Y-%m-%d %H:%M:%S') [DOWN] $name (HTTP $status)" >> "$LOG_FILE"
    emit_alert "$name" "$scope" "$project" "down" "warning" "$name onbereikbaar (HTTP $status)"
    return 1
}

# Reverb broadcasting health — supervisor-managed (JudoToernooi + future projects).
# The website checks above only see the HTTP app; reverb runs on its own ports
# and can sit FATAL while the site stays up. See reverb-troubleshoot.md §6.
check_reverb() {
    local bad
    bad=$(supervisorctl status 2>/dev/null | grep -i reverb | grep -iv 'RUNNING' \
        | awk '{print $1"("$2")"}' | paste -sd ', ' -)

    if [ -z "$bad" ]; then
        emit_alert "reverb" "project" "JudoToernooi" "up" "info" "reverb broadcasting draait"
        return 0
    fi

    echo "$(date '+%Y-%m-%d %H:%M:%S') [DOWN] reverb: $bad" >> "$LOG_FILE"
    emit_alert "reverb" "project" "JudoToernooi" "down" "critical" \
        "reverb niet RUNNING: $bad — fix: supervisorctl restart reverb reverb-staging"
    return 1
}

# Check all public apps (scope/project per app — HavunCore itself = server/general)
check_url "HavunCore"          "https://havuncore.havun.nl/health" "server"  ""
check_url "Herdenkingsportaal" "https://herdenkingsportaal.nl"     "project" "Herdenkingsportaal"
check_url "JudoToernooi"       "https://judotournament.org"        "project" "JudoToernooi"
check_url "HavunAdmin"         "https://havunadmin.havun.nl"       "project" "HavunAdmin"
check_url "SafeHavun"          "https://safehavun.havun.nl"        "project" "SafeHavun"
check_url "Infosyst"           "https://infosyst.havun.nl"         "project" "Infosyst"

# Other supervisor processes (queue workers, heartbeat) — reverb has its own check.
# Alarms on FATAL/BACKOFF (always a real fault); server-scope so critical infra
# surfaces at the top of the panel, not buried under a project. The 23 jun–2 jul
# outage also took down laravel-worker + toernooi-heartbeat, which were unmonitored.
check_supervisor() {
    local bad
    bad=$(supervisorctl status 2>/dev/null | grep -iv reverb \
        | grep -iE 'FATAL|BACKOFF' | awk '{print $1"("$2")"}' | paste -sd ', ' -)

    if [ -z "$bad" ]; then
        emit_alert "supervisor-workers" "server" "" "up" "info" "supervisor-workers draaien"
        return 0
    fi

    echo "$(date '+%Y-%m-%d %H:%M:%S') [DOWN] supervisor: $bad" >> "$LOG_FILE"
    emit_alert "supervisor-workers" "server" "" "down" "critical" \
        "supervisor-processen niet gezond: $bad — fix: supervisorctl restart <naam>"
    return 1
}

# Check reverb broadcasting (separate from the website checks above)
check_reverb
# Check remaining supervisor-managed processes (workers, heartbeat, ...)
check_supervisor
