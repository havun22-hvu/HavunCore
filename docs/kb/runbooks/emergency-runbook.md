# Emergency Runbook — Voor Noodgevallen

> **Bron:** Externe audit Q1 2026 (VP-07)
> **Doel:** Dit runbook kan gevolgd worden door iemand ZONDER voorkennis van de Havun-projecten.
> **Laatst getest:** [NOG NIET GETEST — plan droogtest met contactpersoon]

---

## Noodcontactpersoon

| Rol | Naam | Telefoon | Email |
|-----|------|----------|-------|
| Eigenaar | Henk van Unen | 06-25058214 | havun22@gmail.com |
| Backup contact | Zoon van Henk | [bekend bij beiden] | [bekend bij beiden] |

> **Protocol:** Zoon volgt `docs/kb/runbooks/noodcontactpersoon-protocol.md` — opent VS Code, start Claude Code, en volgt stap-voor-stap instructies. Geen technische voorkennis nodig.

---

## Wat is Havun?

Havun beheert meerdere webapplicaties op één server. De belangrijkste:

| App | Wat doet het | Hoe kritiek? |
|-----|--------------|-------------|
| **Herdenkingsportaal** | Publieke website voor herdenkingspagina's, verwerkt betalingen | Zeer kritiek |
| **JudoToernooi** | Toernooibeheer voor judo-organisatoren | Kritiek tijdens toernooien |
| **HavunCore** | Achterliggende dienst waar andere apps op draaien | Kritiek (andere apps afhankelijk) |
| **HavunAdmin** | Intern beheerpaneel | Minder kritiek |

---

## Stap 1: Inloggen op de server

```bash
# Server gegevens:
# IP: 188.245.159.115
# User: root
# Auth: SSH key (id_ed25519) — zie verzegelde credentials

ssh root@188.245.159.115
```

**Geen SSH key beschikbaar?**
→ Log in via Hetzner Cloud Console:
1. Ga naar https://console.hetzner.cloud
2. Login met Havun Hetzner-account (credentials in verzegelde envelop)
3. Selecteer de server → klik "Console"

---

## Stap 2: Wat is het probleem?

### Check 1: Draait de webserver?
```bash
systemctl status nginx
# Als "inactive" of "failed":
systemctl restart nginx
```

### Check 2: Draait PHP?
```bash
systemctl status php8.3-fpm
# Als "inactive" of "failed":
systemctl restart php8.3-fpm
```

### Check 3: Is de schijf vol?
```bash
df -h /
# Als > 95% vol:
# Verwijder oude logs:
find /var/www/*/production/storage/logs -name "*.log" -mtime +7 -delete
```

### Check 4: Is het geheugen op?
```bash
free -h
# Als "available" < 200M:
systemctl restart php8.3-fpm
systemctl restart nginx
```

---

## Stap 3: Specifieke app herstarten

### Herdenkingsportaal
```bash
cd /var/www/herdenkingsportaal/production
php artisan config:clear
php artisan cache:clear
# Check of het werkt:
curl -s -o /dev/null -w "%{http_code}" https://herdenkingsportaal.nl
# Moet 200 teruggeven
```

### JudoToernooi
```bash
cd /var/www/judotoernooi/laravel
php artisan config:clear
php artisan cache:clear
curl -s -o /dev/null -w "%{http_code}" https://judotoernooi.havun.nl
```

### HavunCore
```bash
cd /var/www/havuncore/production
php artisan config:clear
php artisan cache:clear
curl -s -o /dev/null -w "%{http_code}" https://havuncore.havun.nl/health
```

### HavunCore Webapp (Node.js)
```bash
pm2 status
# Als "stopped" of "errored":
pm2 restart havuncore-backend
```

---

## Stap 4: Maintenance mode (als fix niet lukt)

Zet de app in maintenance mode zodat bezoekers een nette melding zien:

```bash
cd /var/www/[project]/production
php artisan down --render="errors::503"
```

**Maintenance mode uitzetten na fix:**
```bash
php artisan up
```

---

## Stap 5: Backup herstellen (alleen als laatste redmiddel)

### Waar staan backups?

| Locatie | Type | Retentie |
|---------|------|----------|
| `/var/backups/havun/YYYY-MM-DD/` | Dagelijks | 7 dagen |
| `/var/backups/havun/hot/` | Elke 5 min | Laatste snapshot |

### Database herstellen
```bash
# Lijst beschikbare backups:
ls -la /var/backups/havun/

# Herstel database (VOORBEELD — pas projectnaam aan):
mysql -u root [projectnaam] < /var/backups/havun/[datum]/[projectnaam].sql
```

### Bestanden herstellen
```bash
# Kopieer backup terug:
cp /var/backups/havun/[datum]/[project]/* /var/www/[project]/production/ -r
```

---

## Stap 6: Communicatie

### Bij downtime > 30 minuten op kritieke apps:

**Herdenkingsportaal klanten:**
- Geen directe klantcommunicatie nodig (bezoekers zien 503 pagina)
- Na herstel: check of betalingen correct zijn verwerkt

**JudoToernooi:**
- Bij downtime TIJDENS toernooi: contact opnemen met organisator
- Organisatoren staan in de database (niet publiek beschikbaar)

### Na herstel:
1. Documenteer wat er fout ging
2. Documenteer wat de oplossing was
3. Stuur eigenaar (Henk) een bericht

---

## Hosting Provider

| Gegeven | Waarde |
|---------|--------|
| Provider | Hetzner |
| Console | https://console.hetzner.cloud |
| Support | https://docs.hetzner.com |
| Server type | VPS (Ubuntu) |

---

## Checklist voor noodcontactpersoon

Vóór je dit runbook nodig hebt, zorg dat je:

- [ ] SSH key hebt ontvangen of Hetzner Console-toegang hebt
- [ ] Dit runbook een keer hebt doorlopen (droogtest)
- [ ] Weet hoe je Henk kunt bereiken
- [ ] Weet waar de verzegelde credentials-envelop ligt

---

*Aangemaakt: 29 maart 2026 — VP-07*
*Laatst bijgewerkt: 29 maart 2026*
