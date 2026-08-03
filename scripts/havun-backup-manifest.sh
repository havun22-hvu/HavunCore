#!/bin/bash
#
# Schrijft de uitkomst van de nachtelijke backuprun naar een manifest dat de
# V&K-check zonder root-rechten kan lezen.
#
# Waarom dit bestaat: `qv:scan --only=backup-coverage` draait op de server als
# `www-data` en vroeg de backupmap op via SSH naar `root@`. Die sleutel heeft
# www-data niet -- en die geven zou de webserver-user root maken. Van 01-08 tot
# 02-08-2026 rapporteerde de cron daardoor elke nacht `errors=1, high=0`:
# bewaking die niets meet, en niets las dat eerste veld. Sinds 03-08-2026 legt
# het backupscript (dat wél root is) zijn eigen resultaat hier neer.
#
# Er staan geen wachtwoorden in: bestandsnamen, groottes, tijden en de
# databasenaam per app. Daarom mag het wereldleesbaar zijn.
#
# Aanroep (aan het eind van /usr/local/bin/havun-backup.sh):
#   /usr/local/bin/havun-backup-manifest.sh
#
# Plan: HavunCore/docs/kb/plans/registry-drift-check-plan.md

set -euo pipefail

BACKUP_ROOT="${1:-/var/backups/havun}"
MANIFEST="${2:-/var/lib/havun/backup-manifest.json}"

command -v jq >/dev/null || { echo "havun-backup-manifest: jq ontbreekt" >&2; exit 1; }

# Tab-gescheiden regels naar JSON. `jq -R -s` leest de hele stdin als één
# string, vandaar het splitsen; lege regels vallen weg.
tsv_naar_json() {
    jq -R -s --arg vorm "$1" 'split("\n") | map(select(length > 0) | split("\t"))
        | if $vorm == "bestanden"
          then map({naam: .[0], bytes: (.[1] | tonumber), mtime: (.[2] | tonumber | floor)})
          else (map({(.[0]): .[1]}) | add // {}) end'
}

# Nieuwste datummap. Bestaat er geen, dan is dat zelf de meting: een manifest
# met een lege bestandenlijst laat de check "er is niets geback-upt" melden --
# géén manifest zou hem laten denken dat er niet gemeten is.
DIR="$(ls -1d "$BACKUP_ROOT"/[0-9]*-[0-9]*-[0-9]* 2>/dev/null | sort | tail -1 || true)"

if [ -n "$DIR" ]; then
    # Recursief: de run zet production/ en staging/ in aparte submappen.
    BESTANDEN="$(find "$DIR" -type f -printf '%f\t%s\t%T@\n' 2>/dev/null | tsv_naar_json bestanden)"
else
    BESTANDEN='[]'
fi

# Welke database elke app volgens zijn eigen .env gebruikt. Dit is de regel die
# het geval-Herdenkingsportaal vangt: vier maanden lang werd er elke nacht een
# keurige dump van de verkéérde database gemaakt. Alleen de app weet welke de
# echte is. Alleen de DB_DATABASE-regel wordt gelezen.
#
# De `|| true` na de loop is nodig, niet cosmetisch: onder `set -e -o pipefail`
# bepaalt de láátste iteratie de exitstatus. Matcht de repo-prod-glob niet, of
# heeft de laatste .env geen DB_DATABASE, dan zou het script hier stoppen --
# vóórdat het manifest geschreven is.
DATABASES="$(
    {
        for env in /var/www/*/production/.env /var/www/*/repo-prod/laravel/.env; do
            [ -f "$env" ] || continue
            naam="$(grep -m1 '^DB_DATABASE=' "$env" 2>/dev/null | cut -d= -f2 | tr -d '"'"'"' \r' || true)"
            [ -n "$naam" ] && printf '%s\t%s\n' "$naam" "$env"
        done || true
    } | tsv_naar_json databases
)"

install -d -m 755 "$(dirname "$MANIFEST")"

# Atomair: een half geschreven manifest zou als geldige meting gelezen worden.
TMP="$(mktemp "${MANIFEST}.XXXXXX")"
trap 'rm -f "$TMP"' EXIT

# `root` leest de check niet; het staat erin zodat je bij een vreemde uitslag
# ziet welke map gemeten is.
jq -n \
    --argjson bestanden "$BESTANDEN" \
    --argjson databases "$DATABASES" \
    --arg root "$DIR" \
    '{gemaakt_op: (now | floor), root: $root, bestanden: $bestanden, app_databases: $databases}' \
    > "$TMP"

chmod 644 "$TMP"
mv -f "$TMP" "$MANIFEST"
trap - EXIT

echo "$(date): manifest geschreven -> $MANIFEST ($(jq 'length' <<<"$BESTANDEN") bestanden)"
