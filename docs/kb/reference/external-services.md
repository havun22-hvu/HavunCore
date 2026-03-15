# External Services Reference

> Hoe toegang te krijgen tot externe service dashboards en API keys

## Mollie (Betalingen)

**Dashboard:** https://my.mollie.com

### API Keys vinden
1. Log in op https://my.mollie.com
2. Klik op **"Meer"** (rechtsboven in navigatiebalk)
3. Klik op **"Developers"**
4. Klik op **"API-sleutels"**

**Direct link:** https://my.mollie.com/dashboard/developers/api-keys

### Key types
- `test_...` - Test mode (geen echte betalingen)
- `live_...` - Live mode (echte betalingen)

### Gebruikt in
- Herdenkingsportaal (live betalingen)

---

## Anthropic / Claude (AI API)

**Dashboard:** https://console.anthropic.com

### API Keys vinden
1. Log in op https://console.anthropic.com
2. Klik op **"API Keys"** (linker menu)
3. Klik op **"Create Key"** voor nieuwe key

### Key format
- `sk-ant-...` - API key

### Gebruikt in
- Herdenkingsportaal (AI features)
- Infosyst

---

## GitGuardian (Security Monitoring)

**Dashboard:** https://dashboard.gitguardian.com

### API Token
- Zie `.claude/context.md` voor huidige token
- Token aanmaken: Settings → API → Personal access tokens

---

## Brevo (Email / SMTP — alle projecten)

**Dashboard:** https://app.brevo.com (login: havun22@gmail.com)
**Gratis tier:** 300 emails/dag, onbeperkte domeinen
**Voorheen:** Resend voor Herdenkingsportaal (opgezegd maart 2026, 1 domein limiet)

### Laravel .env configuratie
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<zie credentials.md>
MAIL_PASSWORD=<zie credentials.md>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@<domein>"
MAIL_FROM_NAME="${APP_NAME}"
```

> **Let op:** MAIL_PASSWORD is de Brevo SMTP key, NIET je account wachtwoord. Elke SMTP key is per project uniek. Alle credentials staan in `.claude/credentials.md`.

### Domein verificatie
- DNS records (DKIM + SPF) toevoegen bij **mijn.host** (DNS provider)
- Brevo → Settings → Senders, Domains & Dedicated IPs → Add Domain

### Gebruikt in

| Project | Status | From address |
|---------|--------|-------------|
| JudoToernooi | ✅ Actief | noreply@judotournament.org |
| Herdenkingsportaal | ⏳ Domein verifiëren | noreply@herdenkingsportaal.nl |
| Studieplanner | ⏳ Gepland | noreply@studieplanner.havun.nl |

---

## Hetzner (Hosting & Backups)

**Console:** https://console.hetzner.com

### Storage Box (Backups)
- Host: `u510616.your-storagebox.de`
- Port: 23 (SFTP)
- Credentials: zie `.claude/context.md`

---

## Google Analytics (GA4)

**Dashboard:** https://analytics.google.com

### Measurement ID
- `G-42KGYDWS5J` (JudoToernooi property, Sport categorie)

### Implementatie
- Geladen via `<x-seo />` Blade component
- Alleen in production (`app()->environment('production')`)
- Geen `.env` configuratie nodig (hardcoded in component)

### Gebruikt in
| Project | Measurement ID | Status |
|---------|---------------|--------|
| JudoToernooi | G-42KGYDWS5J | ✅ Actief |

---

## Google Search Console

**Dashboard:** https://search.google.com/search-console

### Verificatie
- DNS TXT record via mijn.host nameservers
- Sitemap ingediend (`/sitemap.xml`)

### Gebruikt in
| Project | Domein | Status |
|---------|--------|--------|
| JudoToernooi | judotournament.org | ✅ Geverifieerd |

---

## Bing Webmaster Tools

**Dashboard:** https://www.bing.com/webmasters

### Verificatie
- Geïmporteerd vanuit Google Search Console
- Sitemap handmatig ingediend (`/sitemap.xml`)

### Gebruikt in
| Project | Domein | Status |
|---------|--------|--------|
| JudoToernooi | judotournament.org | ✅ Geverifieerd |

---

## Key Rotatie Checklist

Bij het roteren van API keys:

1. [ ] Genereer nieuwe key in service dashboard
2. [ ] Update lokale `.env` files
3. [ ] Update server `.env` files
4. [ ] Test functionaliteit
5. [ ] Deactiveer/verwijder oude key in dashboard
6. [ ] Update `.claude/context.md` indien nodig

**Let op:** Oude key pas deactiveren NA verificatie dat nieuwe key werkt!
