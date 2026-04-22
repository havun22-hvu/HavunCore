---
title: Runbook: Op reis werkwijze (USB)
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# Runbook: Op reis werkwijze (USB)

> **Doel:** Op een willekeurige PC Cursor installeren, repos pullen en direct aan de slag — zonder Bitwarden of andere lookups. Alles wat nodig is vloeit vanaf de USB in één keer in.
>
> **Principe:** Code staat niet op de USB; die haal je via GitHub. De USB bevat **alleen credentials + setup** zodat je na unlock direct kunt clonen en werken.

---

## Wat staat er op de USB?

| Onderdeel | Wat | Waarom |
|-----------|-----|--------|
| **VS Code (portable)** | ZIP-versie met `data`-map; draait vanaf USB | Editor direct beschikbaar, geen installatie op de PC |
| **credentials.vault** | 7-Zip AES-256 archief met o.a. SSH keys, git-credentials, server-wachtwoorden, `.env`-bestanden, `context.md` per project | Geen Bitwarden nodig; één wachtwoord en alles is beschikbaar |
| **ssh-keys.vault** (optioneel) | Apart archief, zelfde wachtwoord, alleen SSH keys | Als je keys apart wilt bewaren |
| **start.bat** | Unlock vault → SSH/git klaar → vraag clone path → kopieer .env/context.md naar bestaande mappen | In HavunCore repo; kopieer naar USB-root |
| **stop.bat** | Cleanup: SSH keys en git-credentials van de PC verwijderen (draaien bij weggaan) | In HavunCore repo; kopieer naar USB-root |
| **README-USB.txt** | Korte stappen; kopieer naar USB als README.txt | In HavunCore repo |

**Cursor:** Geen portable versie. Als je Cursor wilt: lokaal downloaden en installeren op de reis-PC (cursor.com). VS Code op de USB is al voldoende voor editor; Cursor alleen als je AI/Claude in de editor nodig hebt.

**Staat bewust niet op de USB:**
- Geen projectcode (alles via `git clone` / `git pull`)
- Geen Cursor (lokaal installeren indien gewenst)

---

## Inhoud van credentials.vault (aanbevolen)

Na uitpakken komen bestanden op een tijdelijke of vaste plek; het startscript kopieert ze naar de juiste locaties.

| Bestand in vault | Doel |
|------------------|------|
| **id_ed25519** (+ .pub) | SSH key voor GitHub (en server) |
| **id_rsa** (+ .pub) | Eventueel extra SSH key (bijv. deploy keys) |
| **known_hosts** | Voorkomt SSH host prompts (o.a. github.com, server) |
| **git-credentials** | Git credential helper (HTTPS token of SSH al voldoende) |
| **HavunCore-context.md** | → `HavunCore/.claude/context.md` |
| **HavunAdmin-context.md** | → `HavunAdmin/.claude/context.md` |
| **projectnaam-context.md** per project | → `{project}/.claude/context.md` |
| **HavunAdmin.env**, **JudoToernooi.env**, etc. | → `{project}/.env` of `{project}/laravel/.env` |
| **server-passwords.txt** (of in context.md) | Server root/wachtwoorden, DB-wachtwoorden indien nodig | Optioneel; kan ook alleen in context.md |
| **havuncore-kb-token.txt** | `HAVUNCORE_KB_TOKEN` voor remote KB Search API | Claude kan overal de kennisbank doorzoeken |

Zo hoef je voor SSH, Git, server en projectconfig **niets** in Bitwarden op te zoeken.

---

## Workflow op een nieuwe PC

1. **Git installeren** (als dat nog niet staat)  
   - https://git-scm.com → installeren.

2. **USB in de PC**  
   - Map openen waar `start.bat`, `credentials.vault` en (eventueel) portable VS Code staan.

3. **Editor:** VS Code staat al op de USB (portable) — direct gebruiken. Wil je Cursor (AI): lokaal installeren via https://cursor.com.

4. **start.bat uitvoeren**  
   - Vaultwachtwoord invoeren.  
   - Script doet (of begeleidt):
     - Uitpakken van de vault (bijv. naar een temp map);
     - SSH keys → `%USERPROFILE%\.ssh\`;
     - `known_hosts` → `%USERPROFILE%\.ssh\`;
     - Git credential helper instellen (als je git-credentials gebruikt);
     - Vragen: “Waar staan je repos?” (bijv. `D:\GitHub` of `C:\Dev\Havun`);
     - Voor elke bekende projectmap in die root: juiste `.env` en `.claude/context.md` uit de vault naar die map kopiëren (als de map al bestaat).

5. **KB token instellen**
   - `set HAVUNCORE_KB_TOKEN=<token uit vault>` (of in gebruiker env vars)
   - Hiermee kan Claude in elk project de HavunCore kennisbank doorzoeken via HTTP.

6. **Repos clonen (als ze nog niet bestaan)**
   - Handmatig of met een meegeleverd script/lijst, bijv.:
     - `git clone git@github.com:havun22-hvu/HavunCore.git D:\GitHub\HavunCore`
     - Idem voor HavunAdmin, Herdenkingsportaal, JudoToernooi, SafeHavun, HavunClub, Studieplanner, Infosyst, etc.  
   - Lijst van repos staat in `docs/kb/projects-index.md` (GitHub: havun22-hvu/*).

7. **Credentials in projectmappen (als nog niet gedaan)**
   - Als het startscript geen “clone path” vraagt: handmatig uit de uitgepakte vault de juiste `*-context.md` en `*.env` naar de betreffende projectmappen kopiëren.

8. **Editor openen**
   - VS Code (vanaf USB) of Cursor (lokaal geïnstalleerd) → workspace openen (bijv. HavunCore of een project) en aan de slag.

Bij afsluiten (optioneel): een **stop/cleanup-script** kan SSH keys en git-credentials van de laptop verwijderen; de vault op de USB blijft ongewijzigd.

---

## Aanpassen bestaande start.bat

De huidige `HavunCore/start.bat` is gericht op **alle code op de USB** (portable tools, projecten in `H:\projects\`, sync-to-usb).

Voor de **nieuwe** werkwijze:

- **Behoud:** Vault unlock (7-Zip), uitpakken van SSH keys, known_hosts, git-credentials, .env en context.md.
- **Aanpassen:** Geen projectcode op USB; geen verplichte portable PHP/Node/VS Code.
- **Toevoegen:** Vraag “Clone path?” (bijv. `D:\GitHub`); kopieer .env en context.md alleen naar mappen die **al bestaan** onder die path (of naar een “staging” map met duidelijke instructie).
- **Optioneel:** Na unlock een lijst tonen van `git clone`-commando’s voor alle Havun-repos, of een klein script dat ze één voor één uitvoert als de map nog niet bestaat.

Concreet:
- Logica voor “projectkeuze” en “git pull” in `projects\` kan blijven voor wie toch met USB-projectmappen werkt, of worden vervangen door “clone path + kopieer credentials”.
- Cleanup bij afsluiten (SSH keys, git-credentials van de laptop verwijderen) blijft nuttig.

---

## Kennisbank (KB) toegang op reis

Op je thuis-PC gebruikt Claude `php artisan docs:search` direct in HavunCore.
Op een reis-PC is HavunCore niet lokaal beschikbaar — Claude gebruikt dan de **KB Search API** op de server.

### Hoe werkt het?

```bash
# Thuis (HavunCore lokaal):
cd D:\GitHub\HavunCore && php artisan docs:search "zoekterm" --project=projectnaam

# Op reis (via HTTP):
curl -s -H "Authorization: Bearer $HAVUNCORE_KB_TOKEN" \
  "https://havuncore.havun.nl/api/docs/search?q=zoekterm&project=projectnaam"
```

### Wat moet er in de vault?

De `HAVUNCORE_KB_TOKEN` moet in de credentials.vault staan (zie tabel hieronder).

### Wat moet er in de globale CLAUDE.md op de reis-PC?

```markdown
## Kennisbank (HCai)
- Als `D:\GitHub\HavunCore` bestaat: gebruik `php artisan docs:search`
- Anders: gebruik de KB Search API:
  curl -s -H "Authorization: Bearer $HAVUNCORE_KB_TOKEN" \
    "https://havuncore.havun.nl/api/docs/search?q=zoekterm&project=projectnaam"
- Token staat in environment variable HAVUNCORE_KB_TOKEN
```

### Beschikbare endpoints

| Endpoint | Doel |
|----------|------|
| `GET /api/docs/search?q=...&project=...&limit=5` | Zoek in KB |
| `GET /api/docs/read?project=...&path=...` | Lees specifiek document |
| `GET /api/docs/issues?project=...` | Open doc issues |
| `GET /api/docs/stats` | Indexering statistieken |

Alle endpoints vereisen `Authorization: Bearer <token>`.

Zie ook: `docs/kb/reference/api-kb-search.md`

---

## Beveiliging (ongewijzigd)

- **credentials.vault** en **ssh-keys.vault**: 7-Zip met AES-256, sterk wachtwoord.
- **Eén wachtwoord** voor beide vaults (of twee als je expliciet scheiding wilt).
- Vaultwachtwoord **niet** op de USB in platte tekst; wel in Bitwarden/fysieke notitie als backup.
- Na gebruik op reis: cleanup draaien zodat de gast-PC geen SSH keys of credentials achterhoudt.

Zie ook: `docs/kb/reference/security.md` (sectie USB Beveiliging).

---

## Samenvatting

| Oud | Nieuw |
|-----|--------|
| Code + credentials op USB; sync-to-usb voor vertrek | Alleen credentials (+ startscript + optioneel README) op USB |
| Code in `H:\projects\` | Code via `git clone` in een map naar keuze (bijv. `D:\GitHub`) |
| Portable PHP/Git/Node/VS Code op USB | Cursor (en eventueel Git) op de PC installeren; rest naar wens |
| Bitwarden soms nodig voor server/wachtwoorden | Alles uit de vault; geen Bitwarden nodig om aan de slag te gaan |

**Resultaat:** USB = “install Cursor + Git → start.bat → clone → klaar”. Geen code op de stick, wel alles om in één keer te kunnen werken zonder extra lookups.
