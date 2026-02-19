# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 19 februari 2026 (avond)

### Wat is gedaan:
- **AutoFix voor Herdenkingsportaal:** Volledig systeem geïmplementeerd en gedeployd
  - Migration, Model, Config, Service, Mail, Admin view, Exception handler
  - Tenant: `herdenkingsportaal` (was al geconfigureerd in HavunCore AI Proxy)
  - Admin overzicht: `/admin/autofix` met dark mode support
  - Dashboard knop met 24h badge counter (orange kleur voor dark mode)
  - Context: user_id/user_name/user_email/memorial_id/memorial_naam
- **AutoFix security fix (beide projecten):** `isProjectFile()` check in `applyFix()`
  - Bug: Claude kon vendor/artisan bestanden aanpassen — nu geblokkeerd
  - Fix gedeployd naar zowel JudoToernooi als Herdenkingsportaal production
  - Artisan bestand hersteld uit backup op Herdenkingsportaal production
- **JudoToernooi AutoFix user/toernooi context:** Eerder in sessie toegevoegd
  - organisator_id/naam, toernooi_id/naam, http_method, route_name
- **HavunCore KB docs geüpdatet:**
  - `docs/kb/reference/autofix.md` — Herdenkingsportaal sectie, context kolommen per project
  - `docs/kb/projects/judotoernooi.md` — AutoFix sectie bijgewerkt

### Openstaande items:
- [ ] Admin auth middleware voor Vault admin routes
- [ ] Restore functionaliteit in LaravelAppBackupStrategy
- [ ] HavunCore workflow docs consolideren (5 → 1)
- [ ] 63 broken links in andere projecten
- [ ] JudoToernooi Sprint 1 (4 taken, zie `D:\GitHub\JudoToernooi\.claude\code-review-2026-02-14.md`)
- [ ] JudoToernooi Sprint 2-5 (tech debt, zie zelfde bestand)
- [ ] JudoToernooi `routes/api.php` is dode code (niet geladen) - verwijderen of correct laden
- [ ] HavunClub: Mollie API key nog niet geconfigureerd in .env
- [ ] HavunClub: SMTP email nog niet geconfigureerd
- [ ] HavunClub hoofdkey toegang: `id_ed25519` heeft geen toegang tot HavunClub repo
- [ ] Herdenkingsportaal KB project doc aanmaken in HavunCore (`docs/kb/projects/herdenkingsportaal.md`)

### Belangrijke context:
- **AutoFix actief op:** JudoToernooi production + Herdenkingsportaal production
- **AutoFix .env:** Identiek voor alle projecten (AUTOFIX_ENABLED, AUTOFIX_EMAIL, HAVUNCORE_API_URL, AUTOFIX_RATE_LIMIT)
- **AutoFix tenant verschil:** JudoToernooi = `judotoernooi`, Herdenkingsportaal = `herdenkingsportaal`
- **AutoFix auth verschil:** JudoToernooi = `auth('organisator')`, Herdenkingsportaal = `auth()` (web guard)
- **AutoFix view verschil:** JudoToernooi = `@extends('layouts.app')`, Herdenkingsportaal = `<x-app-layout>` + dark mode
- **HavunCore AI Proxy config key:** `CLAUDE_API_KEY` (niet ANTHROPIC_API_KEY)
- **USB vault wachtwoord:** 3224
- **SSH keys:** Encrypted in `H:\ssh-keys.vault` (zelfde wachtwoord)
- PWA frontend source: `D:\GitHub\havuncore-webapp` (geen remote)
- JudoToernooi login: `/organisator/login` (niet `/login`)
- Chrome integratie: UITGESCHAKELD (globale CLAUDE.md)
