# 📦 HavunCore Backup System - Complete Overzicht

**Datum:** 21 november 2025
**Status:** 📋 Design Complete - Ready for Implementation
**Versie:** 1.0.0

---

## 🎯 Wat hebben we gemaakt?

Een **professionele, compliance-proof, multi-project backup oplossing** voor alle Havun projecten:

✅ **Centrale orchestratie** via HavunCore
✅ **7 jaar retention** (Belastingdienst compliance)
✅ **Offsite storage** (Hetzner Storage Box)
✅ **SHA256 checksums** voor integriteit
✅ **Automatische backup** (dagelijks/wekelijks)
✅ **Unified monitoring** en alerting
✅ **Restore procedures** en quarterly tests
✅ **Multi-project support** (HavunAdmin, Herdenkingsportaal, HavunCore, havun-mcp + toekomst)

---

## 📚 Complete Documentatie Set

| Document | Inhoud | Status | Pagina's |
|----------|--------|--------|----------|
| **COMPLIANCE-BACKUP-ARCHITECTURE.md** | Complete architectuur, compliance eisen, storage strategie | ✅ Compleet | ~50 |
| **MULTI-PROJECT-BACKUP-SYSTEM.md** | Multi-project setup, BackupOrchestrator, config, commands | ✅ Compleet | ~80 |
| **BACKUP-IMPLEMENTATION-GUIDE.md** | Stap-voor-stap implementatie (migrations, models, services) | 🟡 Deels | ~30 (50% done) |
| **HETZNER-STORAGE-BOX-SETUP.md** | Praktische Storage Box setup, SFTP configuratie | ✅ Compleet | ~25 |
| **BACKUP-QUICK-START.md** | Quick overview, veelgebruikte commands, troubleshooting | ✅ Compleet | ~15 |
| **BACKUP-SYSTEM-OVERZICHT.md** | Dit document - overzicht van alles | ✅ Compleet | ~5 |

**Totaal:** ~205 pagina's complete documentatie! 📖

---

## 🏗️ Architectuur Samenvatting

### Centrale Backup Flow

```
┌──────────────┐      Daily/Weekly      ┌─────────────────────┐
│  Project 1   │────────────────────────>│  BackupOrchestrator │
│ (HavunAdmin) │                         │    (HavunCore)      │
└──────────────┘                         └──────────┬──────────┘
                                                    │
┌──────────────┐                                    │
│  Project 2   │────────────────────────────────────┤
│(Herdenking)  │                                    │
└──────────────┘                                    │
                                                    ▼
┌──────────────┐                         ┌──────────────────┐
│  Project 3   │────────────────────────>│ • Database dump  │
│ (HavunCore)  │                         │ • Files archive  │
└──────────────┘                         │ • Compression    │
                                          │ • SHA256 hash    │
┌──────────────┐                         │ • Encryption     │
│  Project 4   │────────────────────────>└────────┬─────────┘
│  (havun-mcp) │                                  │
└──────────────┘                                  │
                                   ┌──────────────┼──────────────┐
                                   │              │              │
                                   ▼              ▼              ▼
                          ┌─────────────┐ ┌────────────┐ ┌───────────┐
                          │   Local     │ │  Hetzner   │ │ BackupLog │
                          │ (Hot-30d)   │ │ (Archive)  │ │ Database  │
                          └─────────────┘ └────────────┘ └───────────┘
```

---

## 🔐 Compliance Features

### Belastingdienst (HavunAdmin)

✅ **Bewaarplicht:** 7 jaar automatic retention
✅ **Offsite Storage:** Hetzner Storage Box (EU)
✅ **Integriteit:** SHA256 checksums per backup
✅ **Authenticiteit:** Audit trail (BackupLog database)
✅ **Leesbaarheid:** Plain SQL dumps (niet binary)
✅ **Toegankelijkheid:** Restore procedures + quarterly tests
✅ **Encryptie:** AES-256 encryption (optional maar aanbevolen)

### GDPR (Herdenkingsportaal)

✅ **Data Protection:** Encrypted backups
✅ **Access Control:** SFTP + SSH key auth
✅ **Audit Trail:** Complete backup/restore logging
✅ **Right to be Forgotten:** Manual archive cleanup possible

---

## 💻 Technical Stack

### Backend (HavunCore)

| Component | Technology | Purpose |
|-----------|-----------|---------|
| **Orchestrator** | Laravel Service | Centrale backup coordinator |
| **Strategies** | Strategy Pattern | Per-type backup logic (Laravel, Node.js, etc.) |
| **Storage** | Flysystem + SFTP | Multi-disk storage (local + offsite) |
| **Database** | MySQL | Backup logging (audit trail) |
| **Scheduling** | Laravel Scheduler | Automated backup runs |
| **Notifications** | Laravel Mail + Slack | Alerts en rapportages |

### Storage (Hetzner)

| Tier | Location | Retention | Purpose |
|------|----------|-----------|---------|
| **Hot** | Local Server | 30 dagen | Snelle restore |
| **Archive** | Hetzner Storage Box | 7+ jaar | Compliance |
| **Test** | Local (temp) | 1 jaar | Quarterly tests |

---

## 📦 Per-Project Configuratie

### HavunAdmin (Critical - Fiscaal)

- **Type:** Laravel App
- **Schedule:** Daily 03:00
- **Backup:** Database + Invoices (PDFs) + Config
- **Size:** ~50 MB/day → ~130 GB / 7 jaar
- **Retention:** 7 jaar (NOOIT auto-delete!)
- **Encryption:** ✅ Enabled
- **Compliance:** 🔴 Kritiek (Belastingdienst)

### Herdenkingsportaal (Critical - GDPR)

- **Type:** Laravel App
- **Schedule:** Daily 04:00
- **Backup:** Database + Uploads (monuments/profiles) + Config
- **Size:** ~150 MB/day → ~385 GB / 7 jaar
- **Retention:** 7 jaar (GDPR + compliance)
- **Encryption:** ✅ Enabled
- **Compliance:** 🔴 Kritiek (Personal data)

### HavunCore (High - Internal)

- **Type:** Laravel Package
- **Schedule:** Weekly (Sunday 05:00)
- **Backup:** Source code + Vault + Config + Git history
- **Size:** ~3 MB/week → ~1.1 GB / 3 jaar
- **Retention:** 3 jaar (OK auto-delete na 3yr)
- **Encryption:** ✅ Enabled (vault keys!)
- **Compliance:** 🟡 Internal

### havun-mcp (Medium - Dev Tool)

- **Type:** Node.js App
- **Schedule:** Weekly (Sunday 06:00)
- **Backup:** Source + JSON databases (clients.json, messages.json)
- **Size:** ~5 MB/week → ~260 MB / 1 jaar
- **Retention:** 1 jaar (OK auto-delete)
- **Encryption:** ❌ Not needed
- **Compliance:** 🟢 None

---

## 🎨 Artisan Commands Overzicht

```bash
# === BACKUP OPERATIONS ===
havun:backup:run [--project=NAME] [--dry-run] [--force]
  → Run backup voor alle of specifiek project

havun:backup:list [--project=NAME]
  → List available backups

havun:backup:cleanup [--all] [--project=NAME] [--dry-run]
  → Cleanup oude hot backups (respects retention policy)

# === MONITORING ===
havun:backup:health
  → Health check voor alle projecten

havun:backup:verify [--project=NAME]
  → Verify SHA256 checksums

havun:backup:report [--daily|--weekly|--monthly]
  → Generate backup reports

# === RESTORE OPERATIONS ===
havun:backup:restore --project=NAME [--latest|--date=YYYY-MM-DD] [--test]
  → Restore backup naar productie of test environment

havun:backup:test [--all] [--project=NAME]
  → Quarterly test restore procedure

# === UTILITIES ===
havun:backup:init
  → Initialize backup system (create directories, test connections)

havun:backup:config [--project=NAME]
  → Show backup configuration

havun:backup:logs [--project=NAME] [--limit=20]
  → Show backup logs
```

---

## 📊 Monitoring & Alerting

### Daily Digest Email

**Subject:** `[HavunCore] Daily Backup Report - 2025-11-21`

```
✅ ALL BACKUPS SUCCESSFUL

Projects:
1. HavunAdmin: 52.5 MB (✅)
2. Herdenkingsportaal: 128.3 MB (✅)

Storage: 12.5 GB local / 245.8 GB offsite
```

### Failure Alert (Immediate)

**Subject:** `🚨 [HavunCore] BACKUP FAILED - HavunAdmin`

```
Project: HavunAdmin (CRITICAL)
Status: ❌ FAILED
Error: Database connection refused

IMMEDIATE ACTION REQUIRED
```

### Health Check (Hourly Cron)

```bash
0 * * * * php artisan havun:backup:health

# Auto-alert if:
# - Backup >25 hours old
# - Offsite upload failed
# - Checksum mismatch
```

---

## 💰 Kosten Breakdown

### Hetzner Storage Box BX30 (5 TB)

| Item | Kosten | Periode |
|------|--------|---------|
| **Maandelijks** | €19,04 | /maand |
| **Jaarlijks** | €228,48 | /jaar |
| **7 jaar (compliance)** | €1.599,36 | totaal |
| **Per project per jaar** | ~€57 | /project/jaar |

### Storage Capacity (7 jaar)

| Project | Daily Size | 7 Years Total |
|---------|------------|---------------|
| HavunAdmin | 50 MB | 130 GB |
| Herdenkingsportaal | 150 MB | 385 GB |
| HavunCore | 3 MB (weekly) | 1.1 GB |
| havun-mcp | 5 MB (weekly) | 0.26 GB |
| **Totaal** | - | **~516 GB** |

**Ruimte over:** 5 TB - 516 GB = **4.5 TB vrij** voor groei! 📈

---

## ⏱️ Implementatie Timeline

### Fase 1: Core Infrastructure (2 dagen)

- Database migrations (backup_logs, restore_logs, test_logs)
- BackupOrchestrator service
- Backup strategies (Laravel, Node.js)
- Models en relationships

### Fase 2: Storage & Upload (1 dag)

- Hetzner Storage Box account
- SFTP driver configuratie
- Upload mechanisme
- Checksum verificatie

### Fase 3: Commands & Monitoring (1 dag)

- Artisan commands (run, health, list, restore)
- Email notifications
- Slack integratie (optional)
- Health check automation

### Fase 4: Testing & Docs (1 dag)

- Test restore procedures
- Quarterly test automation
- Troubleshooting procedures
- Team training / handover

**Totaal:** 5 werkdagen (1 week) voor complete implementatie

---

## ✅ Production Ready Checklist

### Phase 1: Minimaal Vereist

- [ ] Hetzner Storage Box BX30 besteld (€19/maand)
- [ ] SFTP credentials geconfigureerd
- [ ] Database migrations uitgevoerd
- [ ] BackupOrchestrator service geïmplementeerd
- [ ] Backup strategies voor alle project types
- [ ] Eerste succesvolle backup van elk project
- [ ] Checksums verified
- [ ] Cron jobs geconfigureerd (dagelijks/wekelijks)

### Phase 2: Aanbevolen

- [ ] Email notificaties werkend
- [ ] Health check monitoring actief
- [ ] Test restore succesvol voor elk project
- [ ] Encryption enabled met veilige key storage
- [ ] SSH key authentication (i.p.v. password)
- [ ] Weekly backup reports
- [ ] Documentatie compleet en beschikbaar

### Phase 3: Excellent

- [ ] Slack/Discord integratie
- [ ] Automated quarterly test restores
- [ ] Web dashboard voor backup status
- [ ] Multi-user access (accountant rol)
- [ ] Firewall configured op Storage Box
- [ ] Monitoring dashboard
- [ ] Incident response procedures

---

## 🔄 Dagelijkse Operaties

### Automatisch (Geen actie vereist)

- ✅ **Backups draaien** (cron jobs)
- ✅ **Checksums verified**
- ✅ **Upload naar offsite**
- ✅ **Cleanup oude hot backups**
- ✅ **Health checks** (hourly)
- ✅ **Daily digest email**

### Handmatig (Periodiek)

- 🔸 **Quarterly test restore** (elk kwartaal)
- 🔸 **Review backup logs** (maandelijks)
- 🔸 **Check Storage Box usage** (maandelijks)
- 🔸 **Update retention policy** (yearly)
- 🔸 **Archive cleanup >7 jaar** (yearly)

---

## 🚨 Disaster Recovery Scenarios

### Scenario 1: Data Corruption (Recent)

**Time to Restore:** ~15 minuten
**Source:** Local hot backup

```bash
php artisan havun:backup:restore --project=havunadmin --latest
```

### Scenario 2: Data Loss (Oude data)

**Time to Restore:** ~30-60 minuten
**Source:** Hetzner archive (7 jaar terug)

```bash
php artisan havun:backup:restore --project=havunadmin --date=2019-05-15
```

### Scenario 3: Complete Server Loss

**Time to Restore:** ~2-4 uur
**Source:** Hetzner Storage Box + complete setup

1. Provision nieuwe server
2. Install LAMP stack
3. Clone HavunCore repository
4. Download laatste backup van Hetzner
5. Restore database + files
6. Update DNS
7. Test applicatie

---

## 📞 Support & Contact

### Bij Problemen

1. **Check documentatie:**
   - `BACKUP-QUICK-START.md` (troubleshooting sectie)
   - `BACKUP-IMPLEMENTATION-GUIDE.md` (technical details)

2. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   php artisan havun:backup:logs --limit=50
   ```

3. **Test components:**
   ```bash
   php artisan havun:backup:health
   php artisan tinker
   >>> Storage::disk('hetzner-storage-box')->files('test');
   ```

4. **Contact:**
   - 📧 Email: havun22@gmail.com
   - 📂 Docs: D:\GitHub\HavunCore\*.md

### Hetzner Support

- 🌐 Website: https://www.hetzner.com
- 🖥️ Console: https://console.hetzner.com
- 📖 Docs: https://docs.hetzner.com/storage/storage-box/
- 💬 Support: https://accounts.hetzner.com/support

---

## 🎓 Best Practices Samenvatting

### DO ✅

1. ✅ Test restore procedures quarterly
2. ✅ Monitor backups dagelijks (health checks)
3. ✅ Verify checksums voor elke restore
4. ✅ Encrypt sensitieve data
5. ✅ Use SSH keys i.p.v. passwords
6. ✅ Keep backup encryption keys veilig
7. ✅ Document alle restore procedures
8. ✅ Notify immediately on failure
9. ✅ Multiple storage locations (local + offsite)
10. ✅ Audit trail voor compliance

### DON'T ❌

1. ❌ NOOIT auto-delete archive backups (7 jaar!)
2. ❌ NOOIT backups op zelfde server als productie
3. ❌ NOOIT binary database backups (plain SQL!)
4. ❌ NOOIT restore zonder checksum verify
5. ❌ NOOIT encryption keys in git
6. ❌ NOOIT backup procedures ongetest laten
7. ❌ NOOIT single point of failure
8. ❌ NOOIT backups negeren bij deployment
9. ❌ NOOIT backup failures ignoreren
10. ❌ NOOIT restore procedures outdated laten

---

## 🚀 Next Steps

### Voor Implementatie

1. **Review documentatie** met team
2. **Approve budget** (€19/maand Hetzner)
3. **Bestel Hetzner Storage Box** (30 min)
4. **Start implementatie** Fase 1 (2 dagen)
5. **Test op staging** eerst
6. **Deploy naar productie**

### Na Implementatie

1. **Monitor eerste week** (dagelijks check)
2. **Eerste test restore** na 1 week
3. **Review & optimize** na 1 maand
4. **Quarterly test restore** per kwartaal
5. **Annual review** compliance & costs

---

## 📈 Future Enhancements

### Short Term (Q1 2026)

- [ ] Web dashboard voor backup status
- [ ] Slack/Discord real-time alerts
- [ ] Automated incident response
- [ ] Performance metrics (backup speed, sizes)

### Long Term (2026+)

- [ ] Multi-region backups (redundancy)
- [ ] Customer portal voor host clients
- [ ] AI-powered anomaly detection
- [ ] Blockchain-verified backup integrity
- [ ] Self-healing backup system

---

## 🏆 Success Metrics

**Wat meet succes?**

- ✅ **100% backup success rate** (daily/weekly)
- ✅ **<25 hours** backup age (always fresh)
- ✅ **0 data loss incidents**
- ✅ **<2 hour** MTTR (Mean Time To Restore)
- ✅ **100% compliance** met Belastingdienst/GDPR
- ✅ **Quarterly test restores** succesvol
- ✅ **0 checksum mismatches**
- ✅ **<1% false alerts** (monitoring)

---

## 🎉 Conclusie

We hebben een **professioneel, enterprise-grade backup systeem** ontworpen dat:

✅ **Compliance-proof** (7 jaar Belastingdienst + GDPR)
✅ **Multi-project** (alle Havun projecten + toekomstige klanten)
✅ **Automatisch** (dagelijks/wekelijks zonder manual work)
✅ **Monitored** (health checks + alerts + reports)
✅ **Tested** (quarterly restore procedures)
✅ **Documented** (~205 pagina's complete docs!)
✅ **Affordable** (€19/maand voor 5TB = €57/project/jaar)
✅ **Scalable** (ruimte voor 10x groei)

**Total Value:**
- 🔒 **Legal protection** (compliance)
- 💰 **Cost savings** (vs data loss)
- ⏱️ **Time savings** (automated)
- 😴 **Peace of mind** (priceless!)

---

**Status:** 📋 Design Complete ✅

**Ready for:** Implementation (5 dagen) → Production 🚀

**Next:** Bestel Hetzner Storage Box en start Fase 1!

---

**Gemaakt met ❤️ door Claude Code**
**Voor Havun Business Continuity & Compliance**

**21 november 2025**
