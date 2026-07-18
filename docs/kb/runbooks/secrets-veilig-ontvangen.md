---
title: Een wachtwoord of key veilig ontvangen (zonder chat-lek)
type: runbook
scope: alle-projecten
last_updated: 2026-07-19
---

# Een secret veilig ontvangen — zonder dat hij in de chat belandt

> **Waarom:** alles wat Claude "ziet" (elke tool-output, elk chatbericht) reist naar
> Anthropic's servers en staat 30 dagen in het lokale transcript. Een wachtwoord/API-key
> dat door de chat gaat, is daarmee blootgesteld. Henk (18-07-2026): *"je schrijft echte
> wachtwoorden in de chat, iets wat we nooit zouden doen met wachtwoorden van klanten."*
> Kernregel + mandaat: `standards/…` en de credentials-memory.

## De regel

**Een secret komt NOOIT via de chat.** Niet Henk-plakt-'m, niet Claude-leest-'m met `Read`,
niet als argument in een tool-commando dat in het transcript verschijnt.

## Methode A — verborgen invoer via een script dat Henk zélf draait

Voor een **nieuwe** secret die naar een bestemming moet (kluis, server-`.env`, DB):

1. Claude schrijft een script met een **verborgen prompt** (`read -rs`), dat de waarde
   direct naar de bestemming schrijft en config verversst — de waarde nooit echoot.
2. Henk draait het in een **echte terminal** (Git Bash), niet via de chat:
   `& "C:\Program Files\Git\bin\bash.exe" scripts/<naam>.sh`
3. Henk plakt de secret bij de verborgen prompt → gaat rechtstreeks naar bestand/server.
   Niet in het transcript, niet in de shell-history.

Naar de server: geef de waarde via **stdin**, niet als commando-argument:
```bash
printf '%s' "$K" | ssh $SERVER "read -r NK; sed -i \"s#^KEY=.*#KEY=\${NK}#\" /pad/.env; …"
```
Referentie-implementatie: `scripts/rotate-stripe-secret.sh`.

## Methode B — server-side sourcen (secret staat al ergens)

Staat de waarde al in een bestand (bv. `.env` op de server of de app-DB)? **Source 'm
server-side en gebruik 'm direct**, echoot 'm nooit:
```bash
SK=$(grep '^STRIPE_SECRET=' .env | cut -d= -f2-)   # in een var, niet naar stdout
curl -s -o /dev/null -w '%{http_code}' https://api.stripe.com/v1/balance -u "$SK:"
```
Voorbeeld: de admin-wachtwoord-reset las de nieuwe waarde via `fgets(STDIN)` en zette 'm
via `bcrypt()` in de DB — nooit in beeld (`runbooks/…` / commit 18-07).

## Verifiëren zonder lekken

Bevestig dat het werkt met **alleen niet-gevoelige signalen**:
- prefix + **laatste 4 tekens** (`sk_live_…XXXX`) — die toont de provider zelf ook;
- een **werkt/werkt-niet-status** tegen de echte API (bv. `HTTP 200` vs `401`);
- nooit de volledige waarde.

## Nooit doen

- Secret in een chatbericht (door Henk of Claude).
- `Read` op `credentials.md` of een `.env` — dat trekt de waarden het transcript in.
- Secret als zichtbaar argument (`mysql -p'…'`, `sed 's#…#KEY=abc#'` in de chat-tool).
- Secret in een git-commit of een niet-gitignored bestand.
