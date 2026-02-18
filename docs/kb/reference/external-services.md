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

## Brevo (Email / SMTP)

**Dashboard:** https://app.brevo.com
**Voorheen:** SendGrid (proefperiode verlopen, niet meer actief)

### SMTP Credentials vinden
1. Log in op https://app.brevo.com
2. Ga naar **Settings** → **SMTP & API**
3. SMTP credentials staan daar

### Laravel .env configuratie
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<brevo-email>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@domein.nl"
MAIL_FROM_NAME="${APP_NAME}"
```

> **Let op:** MAIL_PASSWORD is de Brevo SMTP key, NIET je account wachtwoord

### Gebruikt in
- Herdenkingsportaal (production - live emails)
- JudoToernooi (production - AutoFix failure notifications)
- Studieplanner (gepland)

### Migratie van SendGrid
SendGrid proefperiode is verlopen (feb 2026). Alle projecten zijn/worden gemigreerd naar Brevo.

| Project | Status |
|---------|--------|
| Herdenkingsportaal | ✅ Brevo actief |
| JudoToernooi | ✅ Brevo actief |
| Studieplanner | ⏳ Gepland |

---

## Hetzner (Hosting & Backups)

**Console:** https://console.hetzner.com

### Storage Box (Backups)
- Host: `u510616.your-storagebox.de`
- Port: 23 (SFTP)
- Credentials: zie `.claude/context.md`

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
