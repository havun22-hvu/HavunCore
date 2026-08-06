#!/usr/bin/env bash
#
# Roteert het Hetzner Storage Box-wachtwoord.
#
# Draai dit op de SERVER, als root:
#   ssh root@188.245.159.115
#   bash /root/roteer-hetzner-wachtwoord.sh
#
# Het nieuwe wachtwoord zet je eerst zelf in de Hetzner Robot
# (https://robot.hetzner.com → Storage Box → Wachtwoord wijzigen). Dit script
# vraagt er daarna om met verborgen invoer, test de verbinding vóór het iets
# vervangt, en draait terug als de test faalt.
#
# De waarde verschijnt nergens: niet op het scherm, niet in de shell-historie,
# niet in de procestabel. Zie runbooks/secrets-veilig-ontvangen.md, methode D.
#
set -uo pipefail

ENVF=/etc/havun-backup.env
HOST="u510616.your-storagebox.de"
USER="u510616"

[ "$(id -u)" -eq 0 ] || { echo "Draai dit als root."; exit 1; }

echo "Nieuw Hetzner Storage Box-wachtwoord (invoer blijft onzichtbaar):"
read -rs NIEUW
echo
[ -n "$NIEUW" ] || { echo "Niets ingevoerd — gestopt, er is niets gewijzigd."; exit 1; }

echo "Verbinding testen met het nieuwe wachtwoord..."
if ! SSHPASS="$NIEUW" timeout 30 sshpass -e sftp -P 23 \
        -oBatchMode=no -oStrictHostKeyChecking=accept-new \
        "${USER}@${HOST}" <<< "ls backups" >/dev/null 2>&1; then
    unset NIEUW
    echo "MISLUKT: Hetzner accepteert dit wachtwoord niet. Er is niets gewijzigd."
    echo "Klopt het wachtwoord in de Robot, en is het daar al opgeslagen?"
    exit 1
fi
echo "Verbinding werkt."

# Pas nu vervangen, met een reservekopie ernaast.
cp -p "$ENVF" "${ENVF}.vorige" 2>/dev/null || true
umask 077
# printf %q quote de waarde zó dat de shell hem identiek terugleest, ongeacht
# welke leestekens erin zitten. Een handgemaakte sed-escape hiervoor is broos:
# de eerste versie hiervan brak op ' en " tegelijk.
printf 'HETZNER_PASS=%q\n' "$NIEUW" > "$ENVF"
chown root:root "$ENVF"; chmod 600 "$ENVF"
unset NIEUW

# Controleren in een subshell: alleen een oordeel komt naar buiten, nooit de waarde.
if ( . "$ENVF" 2>/dev/null; [ -n "${HETZNER_PASS:-}" ] ); then
    echo "Opgeslagen in $ENVF (600, alleen root)."
else
    mv "${ENVF}.vorige" "$ENVF" 2>/dev/null
    echo "FOUT bij het inlezen — oude waarde teruggezet."
    exit 1
fi

echo "Backupscript proefdraaien op alleen de verbinding..."
if ( . "$ENVF"; SSHPASS="$HETZNER_PASS" timeout 30 sshpass -e sftp -P 23 \
        -oBatchMode=no -oStrictHostKeyChecking=accept-new \
        "${USER}@${HOST}" <<< "ls backups" >/dev/null 2>&1 ); then
    rm -f "${ENVF}.vorige"
    echo
    echo "Klaar. De nachtelijke backup (03:00) gebruikt het nieuwe wachtwoord."
    echo "Nog te doen: het oude wachtwoord staat nog in oude backups onder"
    echo "/root/backups/ — die zijn root-only en verliezen hun waarde zodra"
    echo "de rotatie rond is."
else
    mv "${ENVF}.vorige" "$ENVF" 2>/dev/null
    echo "Proefdraaien mislukte — oude waarde teruggezet."
    exit 1
fi
