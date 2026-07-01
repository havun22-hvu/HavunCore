#!/usr/bin/env bash
#
# setup-deploy-key.sh — geef een Havun-repo GitHub-Actions-deploytoegang tot de server.
#
# Rolt in één commando (idempotent) uit:
#   1. een DEDICATED ed25519 deploy-key per repo op de server
#      (/root/.ssh/github_deploy_<slug>) — nooit hergebruik van een andere key,
#      zodat je bij een lek maar één repo hoeft te roteren.
#   2. de public key in root's authorized_keys (zodat de key kan inloggen).
#   3. de private key als GitHub-secret SSH_PRIVATE_KEY in de repo.
#
# De private key wordt NOOIT geprint of lokaal opgeslagen — hij stroomt via een
# pipe rechtstreeks van de server naar de GitHub-secret.
#
# LET OP: dit regelt alleen de key + secret. Een repo deployt pas als er ook een
# workflow (.github/workflows/deploy-*.yml) is die secrets.SSH_PRIVATE_KEY gebruikt.
#
# Vereist: SSH-toegang root@<server> + `gh auth` met scopes repo + workflow.
#
# Gebruik:  scripts/setup-deploy-key.sh <RepoNaam> [github-owner] [server-ip]
# Voorbeeld: scripts/setup-deploy-key.sh HavunClub

set -euo pipefail

REPO="${1:?Geef de repo-naam, bv: HavunClub}"
OWNER="${2:-havun22-hvu}"
SERVER="${3:-188.245.159.115}"
SLUG="$(echo "$REPO" | tr '[:upper:]' '[:lower:]')"
KEY="/root/.ssh/github_deploy_${SLUG}"

echo "→ $OWNER/$REPO : deploy-key $KEY op $SERVER"

# 1 + 2: dedicated key genereren (indien nieuw) en public in authorized_keys (idempotent)
ssh -o BatchMode=yes "root@${SERVER}" "bash -s" <<EOF
set -e
if [ ! -f "${KEY}" ]; then
  ssh-keygen -t ed25519 -N "" -C "github-deploy-${SLUG}" -f "${KEY}" >/dev/null
  echo "   key aangemaakt"
else
  echo "   key bestond al"
fi
PUB="\$(cat "${KEY}.pub")"
if grep -qF "\$PUB" /root/.ssh/authorized_keys 2>/dev/null; then
  echo "   authorized_keys: al aanwezig"
else
  echo "\$PUB" >> /root/.ssh/authorized_keys
  echo "   authorized_keys: toegevoegd"
fi
EOF

# 3: private key rechtstreeks doorpipen naar de GitHub-secret (nooit in beeld)
ssh -o BatchMode=yes "root@${SERVER}" "cat ${KEY}" \
  | gh secret set SSH_PRIVATE_KEY --repo "${OWNER}/${REPO}"

echo "✓ ${REPO}: SSH_PRIVATE_KEY gezet — repo kan nu naar de server deployen (mits deploy-workflow aanwezig)"
