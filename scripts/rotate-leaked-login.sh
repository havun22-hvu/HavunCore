#!/usr/bin/env bash
# Rotate the leaked henkvu-login on the two apps that have NO magic-link fallback.
# Runs the secret through hidden input (read -rs) straight to the destination on the
# server, over stdin -- the value never appears in the chat, argv, or shell history.
# See docs/kb/runbooks/secrets-veilig-ontvangen.md (Methode A).
#
# Usage (in a REAL terminal, not via the chat):
#   & "C:\Program Files\Git\bin\bash.exe" scripts/rotate-leaked-login.sh
#
# Targets:
#   HavunAdmin  -> users.password (id van henkvu@gmail.com) in production + staging
#   JudoToernooi-> .env ADMIN_PASSWORD in laravel (prod) + staging
set -euo pipefail

SERVER="root@188.245.159.115"
BK="/root/backups/pwreset-2026-07-19"

echo "=== Rotatie gelekte login: HavunAdmin + JudoToernooi ==="
echo "Advies: kies TWEE verschillende sterke wachtwoorden."
echo "Vermijd het teken \" (dubbele quote) i.v.m. .env-parsing."
echo

# ---------------------------------------------------------------------------
# 1) HavunAdmin -- users.password van henkvu@gmail.com (prod + staging)
# ---------------------------------------------------------------------------
read -rsp "Nieuw HavunAdmin-wachtwoord: " HA; echo
[ -n "$HA" ] || { echo "Leeg wachtwoord -- gestopt."; exit 1; }

# Worker naar de server schrijven (bevat GEEN secret); secret volgt via stdin.
ssh "$SERVER" "cat > /tmp/ha-rotate.sh" <<REMOTE
#!/bin/bash
set -e
read -r NK
D="$BK"; mkdir -p "\$D"; umask 077
printf '%s' "\$NK" > "\$D/.nk"; chown www-data:www-data "\$D/.nk"; chmod 600 "\$D/.nk"
for d in /var/www/havunadmin/production /var/www/havunadmin/staging; do
  [ -f "\$d/artisan" ] || { echo "\$d: geen artisan"; continue; }
  cd "\$d"
  tag=\$(echo "\$d" | tr '/' '_')
  sudo -u www-data php artisan tinker <<'PHP'
\$nk = trim(file_get_contents('$BK/.nk'));
\$e  = 'henkvu@gmail.com';
\$r  = DB::table('users')->where('email', \$e)->first();
if (\$r) {
    file_put_contents('$BK/havunadmin-'.md5(getcwd()).'.json', json_encode(\$r));
    DB::table('users')->where('id', \$r->id)->update(['password' => Hash::make(\$nk)]);
    \$ok = Hash::check(\$nk, DB::table('users')->where('id', \$r->id)->value('password'));
    echo getcwd().' ok='.(\$ok ? 'yes' : 'no')."\n";
} else {
    echo getcwd()." NO-USER\n";
}
PHP
done
command -v shred >/dev/null && shred -u "\$D/.nk" || rm -f "\$D/.nk"
REMOTE

printf '%s' "$HA" | ssh "$SERVER" 'bash /tmp/ha-rotate.sh; rm -f /tmp/ha-rotate.sh'
unset HA
echo

# ---------------------------------------------------------------------------
# 2) JudoToernooi -- .env ADMIN_PASSWORD (laravel prod + staging)
# ---------------------------------------------------------------------------
read -rsp "Nieuw JudoToernooi ADMIN_PASSWORD: " JT; echo
[ -n "$JT" ] || { echo "Leeg wachtwoord -- gestopt."; exit 1; }

ssh "$SERVER" "cat > /tmp/jt-rotate.sh" <<REMOTE
#!/bin/bash
set -e
read -r NK
mkdir -p "$BK"
export NK
for d in /var/www/judotoernooi/laravel /var/www/judotoernooi/staging; do
  f="\$d/.env"
  [ -f "\$f" ] || { echo "\$d: geen .env"; continue; }
  cp -a "\$f" "$BK/jt-\$(basename \$d)-env.bak"
  awk 'BEGIN{v=ENVIRON["NK"]; done=0}
       /^ADMIN_PASSWORD=/{print "ADMIN_PASSWORD=\"" v "\""; done=1; next}
       {print}
       END{if(!done) print "ADMIN_PASSWORD=\"" v "\""}' "\$f" > "\$f.tmp"
  mv "\$f.tmp" "\$f"; chown www-data:www-data "\$f"
  (cd "\$d" && sudo -u www-data php artisan config:clear >/dev/null 2>&1) || true
  echo "\$d: ADMIN_PASSWORD gezet + config:clear"
done
unset NK
REMOTE

printf '%s' "$JT" | ssh "$SERVER" 'bash /tmp/jt-rotate.sh; rm -f /tmp/jt-rotate.sh'
unset JT
echo
echo "=== Klaar. Backups: $BK op de server. ==="
echo "Test daarna beide logins in de browser."
