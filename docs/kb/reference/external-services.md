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

## Resend (Email)

**Dashboard:** https://resend.com (login: havun22@gmail.com)
**Voorheen:** SendGrid (proefperiode verlopen dec 2025, niet meer actief)
**Gratis tier:** 3000 emails/maand, 1 domein

### Laravel .env configuratie
```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="noreply@herdenkingsportaal.nl"
MAIL_FROM_NAME="Herdenkingsportaal"
RESEND_KEY=re_KQABaL9N_...  (volledige key op server .env)
```

> **Let op:** Resend gratis tier staat maar 1 domein toe. Voor extra domeinen: betaald plan ($20/mnd) of ander provider (bijv. Brevo).

### Domein verificatie
- DNS records (DKIM + SPF) toevoegen bij **mijn.host** (DNS provider)
- Resend → Domains → Add Domain → kopieer records naar mijn.host DNS

### Gebruikt in

| Project | Status | From address |
|---------|--------|-------------|
| Herdenkingsportaal | ✅ Resend actief | noreply@herdenkingsportaal.nl |
| JudoToernooi | ⏳ Nog in te stellen | - |
| Studieplanner | ⏳ Gepland | - |

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
