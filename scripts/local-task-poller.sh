#!/usr/bin/env bash
#
# Local task poller — draait op Henks PC, niet op de server.
#
# Haalt taken op uit de HavunCore-wachtrij en laat Claude CLI ze uitvoeren in de
# juiste projectmap. De PC haalt op; de server duwt niet. Geen open poort nodig,
# en staat de PC uit dan blijft de taak gewoon wachten.
#
#   HAVUNCORE_TASKS_TOKEN=<token uit de Vault> ./scripts/local-task-poller.sh
#   ./scripts/local-task-poller.sh --self-test   # controleert de guards, draait niets
#   ./scripts/local-task-poller.sh --once        # één ronde in plaats van blijven pollen
#
# Grenzen die dit script HARD afdwingt (runbooks/agent-grenzen.md):
#   - nooit op master/main werken; altijd een eigen branch
#   - .env mag niet wijzigen; wijzigt hij toch, dan faalt de taak
#   - nooit pushen naar master/main
#   - geen shell-fallback als Claude CLI ontbreekt — dan faalt de taak, punt
#
set -uo pipefail

API="${HAVUNCORE_TASKS_API:-https://havuncore.havun.nl/api/claude/tasks}"
TOKEN="${HAVUNCORE_TASKS_TOKEN:-}"
POLL_INTERVAL="${POLL_INTERVAL:-60}"
GITHUB_ROOT="${GITHUB_ROOT:-/d/GitHub}"
PROJECTS="${PROJECTS:-judotoernooi herdenkingsportaal}"
BRANCH_PREFIX="hotfix/autofix-"

log()  { printf '[%s] %s\n' "$(date '+%H:%M:%S')" "$1"; }
fail() { printf '[%s] FOUT: %s\n' "$(date '+%H:%M:%S')" "$1" >&2; }

# Projectnaam → lokale map. Onbekend project = geen pad; de taak faalt luid
# in plaats van in een willekeurige map te belanden.
project_path() {
    case "$1" in
        judotoernooi)       echo "$GITHUB_ROOT/JudoToernooi" ;;
        herdenkingsportaal) echo "$GITHUB_ROOT/Herdenkingsportaal" ;;
        havuncore)          echo "$GITHUB_ROOT/HavunCore" ;;
        *)                  echo "" ;;
    esac
}

api() {
    local method="$1" path="$2" body="${3:-}"
    if [ -n "$body" ]; then
        curl -sS -X "$method" "${API}${path}" \
            -H "Authorization: Bearer ${TOKEN}" \
            -H "Content-Type: application/json" -d "$body"
    else
        curl -sS -X "$method" "${API}${path}" -H "Authorization: Bearer ${TOKEN}"
    fi
}

# JSON lezen/schrijven via PHP. Bewust geen jq: die staat niet op Henks machine
# en zou een extra installatie zijn, terwijl PHP er voor dit project toch al is.
json_get() {
    php -r '$d = json_decode(stream_get_contents(STDIN), true); $v = $d; foreach (explode(".", $argv[1]) as $k) { $v = is_array($v) ? ($v[$k] ?? null) : null; } echo $v ?? "";' "$1"
}

json_string() {
    php -r 'echo json_encode(["error" => $argv[1]]);' "$1"
}

json_result() {
    php -r 'echo json_encode(["result" => $argv[1], "metadata" => ["branch" => $argv[2]]]);' "$1" "$2"
}

env_hash() {
    [ -f "$1/.env" ] && sha256sum "$1/.env" | cut -d' ' -f1 || echo "geen-env"
}

# --- guards -----------------------------------------------------------------

# Weigert master/main. Dit is de grens die er het meest toe doet: een agent die
# per ongeluk op master werkt, schrijft in de hoofdlijn van een productie-app.
guard_not_on_main() {
    local branch
    branch=$(git -C "$1" rev-parse --abbrev-ref HEAD 2>/dev/null)
    case "$branch" in
        master|main) return 1 ;;
        "")          return 1 ;;
        *)           return 0 ;;
    esac
}

guard_env_unchanged() {
    [ "$(env_hash "$1")" = "$2" ]
}

self_test() {
    local ok=0
    printf 'Guards controleren (er wordt niets uitgevoerd)\n\n'

    local tmp; tmp=$(mktemp -d)
    git -C "$tmp" init -q 2>/dev/null
    git -C "$tmp" -c user.email=t@t -c user.name=t commit -q --allow-empty -m init 2>/dev/null
    git -C "$tmp" branch -M master 2>/dev/null

    if guard_not_on_main "$tmp"; then
        fail "guard_not_on_main liet master door"; ok=1
    else
        printf '  ✓ master wordt geweigerd\n'
    fi

    git -C "$tmp" checkout -q -b "${BRANCH_PREFIX}test" 2>/dev/null
    if guard_not_on_main "$tmp"; then
        printf '  ✓ een eigen branch wordt toegelaten\n'
    else
        fail "guard_not_on_main weigerde een gewone branch"; ok=1
    fi

    printf 'geheim\n' > "$tmp/.env"
    local before; before=$(env_hash "$tmp")
    if guard_env_unchanged "$tmp" "$before"; then
        printf '  ✓ ongewijzigde .env wordt geaccepteerd\n'
    else
        fail "guard_env_unchanged faalde op een ongewijzigd bestand"; ok=1
    fi

    printf 'aangepast\n' >> "$tmp/.env"
    if guard_env_unchanged "$tmp" "$before"; then
        fail "guard_env_unchanged zag een gewijzigde .env niet"; ok=1
    else
        printf '  ✓ gewijzigde .env wordt gedetecteerd\n'
    fi

    if command -v claude >/dev/null 2>&1; then
        printf '  ✓ Claude CLI gevonden\n'
    else
        printf '  ! Claude CLI niet gevonden — taken zouden falen (geen shell-fallback)\n'
    fi

    [ -n "$TOKEN" ] && printf '  ✓ token staat in de omgeving\n' \
                    || printf '  ! HAVUNCORE_TASKS_TOKEN niet gezet\n'

    rm -rf "$tmp"
    printf '\n%s\n' "$([ $ok -eq 0 ] && echo 'Alle guards doen wat ze moeten doen.' || echo 'Er is een guard stuk — niet gebruiken.')"
    return $ok
}

# --- uitvoeren --------------------------------------------------------------

run_task() {
    local id="$1" project="$2" instruction="$3"
    local path; path=$(project_path "$project")

    if [ -z "$path" ] || [ ! -d "$path" ]; then
        api POST "/${id}/fail" "$(printf '{"error":"Onbekend of ontbrekend projectpad voor %s"}' "$project")" >/dev/null
        fail "geen map voor project $project"; return 1
    fi

    if ! command -v claude >/dev/null 2>&1; then
        api POST "/${id}/fail" '{"error":"Claude CLI niet beschikbaar op deze machine"}' >/dev/null
        fail "Claude CLI ontbreekt — taak gefaald, niets uitgevoerd"; return 1
    fi

    log "taak #${id} (${project}) — start"
    api POST "/${id}/start" >/dev/null

    local branch="${BRANCH_PREFIX}${project}-$(date +%Y-%m-%d-%H%M)"
    git -C "$path" fetch --quiet origin 2>/dev/null
    if ! git -C "$path" checkout -q -b "$branch" 2>/dev/null; then
        api POST "/${id}/fail" '{"error":"Kon geen branch aanmaken; werkmap waarschijnlijk niet schoon"}' >/dev/null
        fail "branch aanmaken mislukt in $path"; return 1
    fi

    if ! guard_not_on_main "$path"; then
        api POST "/${id}/fail" '{"error":"Checkout staat op master/main; geweigerd"}' >/dev/null
        fail "checkout staat op master/main"; return 1
    fi

    local env_before; env_before=$(env_hash "$path")
    local started; started=$(date +%s)

    local output
    output=$(cd "$path" && claude --permission-mode acceptEdits "$instruction" 2>&1)
    local exit_code=$?
    local duration=$(( $(date +%s) - started ))

    if ! guard_env_unchanged "$path" "$env_before"; then
        git -C "$path" checkout -- .env 2>/dev/null
        api POST "/${id}/fail" '{"error":".env is gewijzigd tijdens de taak; teruggedraaid en afgebroken"}' >/dev/null
        fail ".env gewijzigd — teruggedraaid"; return 1
    fi

    if [ $exit_code -ne 0 ]; then
        api POST "/${id}/fail" "$(json_string "$output")" >/dev/null
        fail "taak #${id} mislukt (exit ${exit_code})"; return 1
    fi

    # Wijzigingen blijven op de branch staan. Pushen en een PR openen doet Henk
    # of een aparte stap — dit script raakt de hoofdlijn niet aan.
    if [ -n "$(git -C "$path" status --porcelain)" ]; then
        log "taak #${id}: wijzigingen staan op branch ${branch} (niet gecommit, niet gepusht)"
    else
        log "taak #${id}: geen wijzigingen"
    fi

    api POST "/${id}/complete" "$(json_result "$output" "$branch")" >/dev/null
    log "taak #${id} klaar in ${duration}s"
}

poll_once() {
    for project in $PROJECTS; do
        local response; response=$(api GET "/pending/${project}")

        if printf '%s' "$response" | grep -q '"error"'; then
            fail "wachtrij weigerde het verzoek voor ${project} (token juist?)"; continue
        fi

        local count; count=$(printf '%s' "$response" | json_get count)
        [ -z "$count" ] && count=0
        [ "$count" = "0" ] && continue

        local id project_name instruction
        id=$(printf '%s' "$response" | json_get "tasks.0.id")
        project_name=$(printf '%s' "$response" | json_get "tasks.0.project")
        instruction=$(printf '%s' "$response" | json_get "tasks.0.task")
        run_task "$id" "$project_name" "$instruction"
    done
}

# --- start ------------------------------------------------------------------

case "${1:-}" in
    --self-test) self_test; exit $? ;;
esac

if [ -z "$TOKEN" ]; then
    fail "HAVUNCORE_TASKS_TOKEN is niet gezet. Haal het token uit de Vault — zie .claude/credentials.md."
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    fail "php is nodig voor het lezen van de API-antwoorden, maar is niet gevonden."
    exit 1
fi

if [ "${1:-}" = "--once" ]; then
    poll_once; exit $?
fi

log "poller gestart — projecten: ${PROJECTS} | interval: ${POLL_INTERVAL}s"
while true; do
    poll_once
    sleep "$POLL_INTERVAL"
done
