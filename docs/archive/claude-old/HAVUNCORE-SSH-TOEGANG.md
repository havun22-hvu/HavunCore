# HavunCore - SSH Toegang Hetzner Server

**Datum:** 19 november 2025
**Server:** Hetzner VPS (188.245.159.115)
**Doel:** HavunCore team SSH toegang geven voor havuncore.havun.nl

---

## 🎯 Overzicht

HavunCore heeft een site opgezet op `https://havuncore.havun.nl` en heeft SSH toegang nodig om hun applicatie te deployen en te beheren.

**Server Details:**
- **IP Address:** 188.245.159.115
- **Hostname:** herdenkingsportaal-prod
- **Provider:** Hetzner CX22 VPS
- **OS:** Ubuntu 22.04 LTS

---

## 🔐 Optie 1: Toegang via Bestaande SSH Key (Snelst)

Als je tijdelijk toegang nodig hebt, kun je de bestaande SSH key gebruiken:

### **Stap 1: Vraag SSH Private Key**

Vraag Henk om de private key file:
```
File: id_ed25519 (ZONDER .pub extensie)
```

⚠️ **BELANGRIJK:** Deze key is PRIVÉ en mag NOOIT gedeeld worden via email/chat. Gebruik een veilige methode (encrypted USB, password manager, etc.)

### **Stap 2: Installeer de Key**

**Op Linux/Mac:**
```bash
# Maak SSH directory aan (als deze nog niet bestaat)
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Kopieer de key naar de juiste locatie
cp /pad/naar/id_ed25519 ~/.ssh/id_ed25519

# Set juiste permissions (KRITIEK!)
chmod 600 ~/.ssh/id_ed25519
```

**Op Windows (PowerShell):**
```powershell
# Maak SSH directory aan (als deze nog niet bestaat)
mkdir ~\.ssh -Force

# Kopieer de key naar de juiste locatie
Copy-Item C:\pad\naar\id_ed25519 ~\.ssh\id_ed25519

# Set juiste permissions
icacls ~\.ssh\id_ed25519 /inheritance:r
icacls ~\.ssh\id_ed25519 /grant:r "$env:USERNAME:R"
```

### **Stap 3: Verbind met Server**

```bash
ssh -i ~/.ssh/id_ed25519 root@188.245.159.115
```

**Test je verbinding:**
```bash
ssh -i ~/.ssh/id_ed25519 root@188.245.159.115 "echo 'Verbinding succesvol!' && whoami && hostname"
```

**Verwachte output:**
```
Verbinding succesvol!
root
herdenkingsportaal-prod
```

---

## 🔑 Optie 2: Eigen SSH Key Aanmaken (Aanbevolen)

Voor langdurige toegang is het beter dat HavunCore hun eigen SSH key gebruikt.

### **Stap 1: Genereer SSH Key Pair**

**Op Linux/Mac/Windows (Git Bash):**
```bash
ssh-keygen -t ed25519 -C "havuncore@havun.nl" -f ~/.ssh/id_ed25519_havuncore
```

**Interactieve prompts:**
- "Enter passphrase": Voer een sterk wachtwoord in (optioneel maar aanbevolen)
- "Enter same passphrase again": Bevestig wachtwoord

**Dit genereert twee files:**
```
~/.ssh/id_ed25519_havuncore       # Private key (GEHEIM!)
~/.ssh/id_ed25519_havuncore.pub   # Public key (kan gedeeld worden)
```

### **Stap 2: Stuur Public Key naar Henk**

```bash
# Toon de public key
cat ~/.ssh/id_ed25519_havuncore.pub
```

**Kopieer de HELE output** (begint met `ssh-ed25519 AAAA...`) en stuur naar Henk.

**Voorbeeld:**
```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAbCdEfGhIjKlMnOpQrStUvWxYz01234567890ABCD havuncore@havun.nl
```

### **Stap 3: Henk Voegt Key Toe aan Server**

Henk moet inloggen op de server en je public key toevoegen:

```bash
# Login op server
ssh root@188.245.159.115

# Voeg public key toe (vervang door jouw key)
echo "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAbCdEfGhIjKlMnOpQrStUvWxYz01234567890ABCD havuncore@havun.nl" >> /root/.ssh/authorized_keys

# Controleer of key is toegevoegd
cat /root/.ssh/authorized_keys

# Exit server
exit
```

### **Stap 4: Test je Verbinding**

```bash
ssh -i ~/.ssh/id_ed25519_havuncore root@188.245.159.115
```

---

## 🛠️ SSH Config Instellen (Makkelijker Gebruik)

In plaats van elke keer `-i ~/.ssh/id_ed25519_havuncore` te typen, kun je een SSH config maken.

### **Maak/Edit SSH Config:**

**Op Linux/Mac:**
```bash
nano ~/.ssh/config
```

**Op Windows (Git Bash/PowerShell):**
```powershell
notepad ~\.ssh\config
```

### **Voeg Toe:**

```
Host hetzner
    HostName 188.245.159.115
    User root
    IdentityFile ~/.ssh/id_ed25519_havuncore
    PubkeyAuthentication yes
    PasswordAuthentication no

Host havuncore
    HostName 188.245.159.115
    User root
    IdentityFile ~/.ssh/id_ed25519_havuncore
    PubkeyAuthentication yes
    PasswordAuthentication no
```

### **Nu Kun Je Simpelweg:**

```bash
ssh hetzner
# of
ssh havuncore
```

---

## 📁 Belangrijke Server Locaties

Na inloggen kun je naar deze directories:

```bash
# HavunCore site
cd /var/www/havuncore.havun.nl
ls -la

# Herdenkingsportaal (production)
cd /var/www/production
ls -la

# Herdenkingsportaal (staging)
cd /var/www/staging
ls -la

# Apache configuratie
cd /etc/apache2/sites-available
ls -la

# SSL certificaten
cd /etc/letsencrypt/live
ls -la

# Logs
tail -f /var/log/apache2/havuncore.havun.nl-error.log
tail -f /var/log/apache2/havuncore.havun.nl-access.log
```

---

## 🚀 Typische Deployment Workflow

### **1. Upload je Applicatie**

**Via SCP (vanaf je lokale machine):**
```bash
scp -i ~/.ssh/id_ed25519_havuncore -r /lokaal/project/* root@188.245.159.115:/var/www/havuncore.havun.nl/
```

**Via Git (op de server):**
```bash
ssh hetzner
cd /var/www/havuncore.havun.nl
git clone https://github.com/jouw-repo/havuncore.git .
```

### **2. Laravel Setup (als je Laravel gebruikt)**

```bash
ssh hetzner
cd /var/www/havuncore.havun.nl

# Composer dependencies
composer install --no-dev --optimize-autoloader

# Environment configuratie
cp .env.example .env
nano .env  # Bewerk database credentials, APP_KEY, etc.

# Genereer application key
php artisan key:generate

# Database migraties
php artisan migrate --force

# Storage symlink
php artisan storage:link

# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Restart PHP-FPM
systemctl restart php8.2-fpm
```

### **3. Test je Site**

```bash
curl -I https://havuncore.havun.nl
```

---

## 🔧 Troubleshooting

### **Probleem: "Permission denied (publickey)"**

**Oorzaak:** Private key heeft verkeerde permissions of key niet gevonden.

**Oplossing:**
```bash
# Check permissions
ls -la ~/.ssh/id_ed25519_havuncore

# Fix permissions
chmod 600 ~/.ssh/id_ed25519_havuncore

# Probeer opnieuw met verbose output
ssh -v -i ~/.ssh/id_ed25519_havuncore root@188.245.159.115
```

---

### **Probleem: "Connection refused" of timeout**

**Oorzaak:** Firewall blokkeert verbinding of SSH service is down.

**Oplossing:**
```bash
# Check of SSH poort open is
nc -zv 188.245.159.115 22

# Als dit niet werkt, vraag Henk om firewall te checken
```

---

### **Probleem: "Host key verification failed"**

**Oorzaak:** Server's host key is veranderd of dit is eerste verbinding.

**Oplossing:**
```bash
# Verwijder oude host key
ssh-keygen -R 188.245.159.115

# Probeer opnieuw (type 'yes' om nieuwe key te accepteren)
ssh -i ~/.ssh/id_ed25519_havuncore root@188.245.159.115
```

---

## ⚠️ Security Best Practices

1. **NOOIT private keys delen via email/chat**
   - Gebruik encrypted USB, password managers, of secure file sharing

2. **Gebruik altijd een passphrase voor je private key**
   - Beschermt je key als je computer gecompromitteerd wordt

3. **Bewaar backups van je private key veilig**
   - Encrypted externe drive of password manager
   - Zonder backup ben je toegang kwijt als je computer crasht

4. **Gebruik geen root user voor daily operations**
   - Vraag Henk om een dedicated user voor HavunCore
   - Gebruik `sudo` voor admin taken

5. **Revoke keys onmiddellijk bij teamleden die vertrekken**
   - Verwijder hun public key uit `/root/.ssh/authorized_keys`

---

## 📞 Contact

**Bij problemen met SSH toegang:**
- **Contact:** Henk van Uum (havun22-hvu)
- **Server Provider:** Hetzner
- **Documentation:** `docs/4-DEPLOYMENT/SERVER-ACCESS.md`

---

## 📋 Checklist SSH Setup

- [ ] SSH key pair gegenereerd (`ssh-keygen`)
- [ ] Public key naar Henk gestuurd
- [ ] Henk heeft key toegevoegd aan server
- [ ] SSH verbinding getest en werkt
- [ ] SSH config opgezet voor makkelijk gebruik
- [ ] Server directories verkend
- [ ] Deployment workflow getest
- [ ] Backup van private key gemaakt

---

**Status:** ✅ Site havuncore.havun.nl is live op HTTPS met SSL
**Volgende stap:** HavunCore kan nu inloggen en hun applicatie deployen
**Support:** Henk beschikbaar voor assistentie bij setup

---

*Laatste Update: 19 november 2025*
