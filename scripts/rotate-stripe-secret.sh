#!/usr/bin/env bash
#
# rotate-stripe-secret.sh — zet een nieuwe Stripe sk_live_ sleutel veilig op alle
# plekken ZONDER 'm ergens te tonen. De sleutel wordt VERBORGEN ingelezen (read -s),
# gaat rechtstreeks naar de kluis + de live .env's, en komt nooit in een chat/transcript
# of in je shell-history.
#
# Draai dit ZELF in een gewone terminal (niet via de Claude-chat):
#     bash D:/GitHub/HavunCore/scripts/rotate-stripe-secret.sh
#
set -euo pipefail

SERVER="root@188.245.159.115"
CRED="D:/GitHub/HavunCore/.claude/credentials.md"

# LIVE .env-mappen die de nieuwe sleutel moeten krijgen.
# HavunAdmin staat er bewust uit-gecommentarieerd bij: activeer 'm ALLEEN als HavunAdmin
# echt Stripe gebruikt (anders die .env juist leegmaken).
LIVE_ENVS=(
  "/var/www/judotoernooi/repo-prod/laravel"
  "/var/www/havunadmin/production"   # HavunAdmin gebruikt Stripe echt (StripeService, payment-sync)
)

read -rs -p "Plak de nieuwe Stripe sk_live_ sleutel (verborgen): " K; echo
[[ "$K" == sk_live_* ]] || { echo "Geen geldige sk_live_ sleutel — gestopt."; exit 1; }
echo "Sleutel ontvangen (${#K} tekens). Verwerken..."

# 1) Centrale kluis — append een gelabelde entry (idempotent: niet dubbel bij re-run)
if grep -q "Stripe secret (JudoToernooi) — GEROTEERD 2026-07-19" "$CRED" 2>/dev/null; then
  echo "  kluis: entry bestaat al, overgeslagen"
else
  printf '\n## Stripe secret (JudoToernooi + HavunAdmin) — GEROTEERD 2026-07-19\n- Secret key: %s\n- Oude `...4l13` verloopt binnen 24u; ruim de verouderde Stripe-regel hierboven op.\n' "$K" >> "$CRED"
  echo "  kluis bijgewerkt: $CRED"
fi

# 2) Live server-.env's bijwerken + config verversen (sleutel via stdin, niet via args)
for d in "${LIVE_ENVS[@]}"; do
  printf '%s' "$K" | ssh "$SERVER" "read -r NK; \
    sed -i \"s#^STRIPE_SECRET=.*#STRIPE_SECRET=\${NK}#\" '$d/.env'; \
    cd '$d' && php artisan config:clear >/dev/null && php artisan config:cache >/dev/null && \
    echo '  bijgewerkt + config ververst: $d'"
done

unset K
echo "Klaar. Test nu een betaling; laat daarna de oude sleutel in Stripe verlopen."
