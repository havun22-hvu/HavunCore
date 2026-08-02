#!/bin/bash
# Rotate a MySQL user's password without ever putting it through a transcript.
#
# Usage:  roteer-db-wachtwoord.sh <mysql-user> <test-database> <.env> [<.env> ...]
#
# The new password is generated on the server, verified against the database
# before a single .env is touched, and only ever written to those .env files.
# It is never printed. Run as root on the server that hosts the database.
#
# Every .env listed gets its DB_PASSWORD replaced; back-ups of all of them (and
# a dump of the test database) are written to /root/backups/ first.

set -u

[ $# -ge 3 ] || { echo "Gebruik: $0 <mysql-user> <test-database> <.env> [<.env> ...]"; exit 1; }

USER=$1
TESTDB=$2
shift 2
ENVS=("$@")

for e in "${ENVS[@]}"; do
    [ -f "$e" ] || { echo "FOUT: $e bestaat niet"; exit 1; }
    grep -qE '^DB_PASSWORD=' "$e" || { echo "FOUT: $e heeft geen DB_PASSWORD-regel"; exit 1; }
done

STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP=/root/backups/rotatie-$USER-$STAMP
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"

echo "== 1. Back-up: database + alle .env-bestanden =="
mysqldump --single-transaction --routines --triggers "$TESTDB" | gzip > "$BACKUP/$TESTDB.sql.gz" || {
    echo "FOUT: dump van $TESTDB mislukt -- niets gewijzigd"; exit 1;
}
gzip -t "$BACKUP/$TESTDB.sql.gz" || { echo "FOUT: dump is corrupt -- niets gewijzigd"; exit 1; }
echo "   $TESTDB.sql.gz  $(zcat "$BACKUP/$TESTDB.sql.gz" | grep -c '^CREATE TABLE') tabellen"
i=0
for e in "${ENVS[@]}"; do
    cp -a "$e" "$BACKUP/env-$i-$(echo "$e" | tr / _)"
    i=$((i + 1))
done
chmod 600 "$BACKUP"/*
echo "   -> $BACKUP"

echo "== 2. Nieuw wachtwoord genereren (48 tekens, alleen shell-veilige tekens) =="
NIEUW=$(tr -dc 'A-Za-z0-9_.-' < /dev/urandom | head -c 48)
[ ${#NIEUW} -eq 48 ] || { echo "FOUT: generatie mislukt"; exit 1; }

echo "== 3. MySQL-gebruiker bijwerken =="
mysql -e "ALTER USER '$USER'@'localhost' IDENTIFIED BY '$NIEUW'; FLUSH PRIVILEGES;" || {
    echo "FOUT: ALTER USER mislukt -- niets gewijzigd aan de .env's"; exit 1;
}

echo "== 4. Verbinding testen met het nieuwe wachtwoord =="
mysql -u "$USER" -p"$NIEUW" -e "SELECT 1;" "$TESTDB" >/dev/null 2>&1 || {
    echo "FOUT: nieuw wachtwoord werkt niet -- HERSTEL HANDMATIG uit $BACKUP"; exit 1;
}
echo "   verbinding OK"

echo "== 5. .env-bestanden bijwerken =="
# sed struikelt over / en & in een gegenereerde waarde; python neemt hem letterlijk.
NIEUW="$NIEUW" python3 - "${ENVS[@]}" <<'PYEOF'
import io, os, re, sys
nieuw = os.environ["NIEUW"]
for pad in sys.argv[1:]:
    s = io.open(pad, encoding="utf-8").read()
    s = re.sub(r'(?m)^(DB_PASSWORD)=.*$', lambda m: f"{m.group(1)}={nieuw}", s)
    io.open(pad, "w", encoding="utf-8", newline="\n").write(s)
    print(f"   bijgewerkt: {pad}")
PYEOF

echo "== 6. Caches verversen =="
for e in "${ENVS[@]}"; do
    d=$(dirname "$e")
    [ -f "$d/artisan" ] || { echo "   $d: geen artisan, overgeslagen"; continue; }
    (cd "$d" && sudo -u www-data php artisan config:clear >/dev/null 2>&1 \
             && sudo -u www-data php artisan config:cache >/dev/null 2>&1) \
        && echo "   $d ok" || echo "   $d FOUT bij cache -- controleer handmatig"
done

echo
echo "KLAAR. Het nieuwe wachtwoord staat alleen in de opgegeven .env-bestanden."
echo "Rooktest de site(s) zelf, en controleer of queue-workers herstart moeten worden."
echo "Werkt er iets niet: herstel uit $BACKUP en draai stap 3 met de oude waarde."
