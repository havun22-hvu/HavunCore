---
title: Server OS-updates — kwartaalcheck
type: runbook
scope: havuncore
last_check: 2026-07-19
---

# Server OS-updates — kwartaalcheck

**Server:** 188.245.159.115 · Ubuntu 22.04 LTS (EOL april 2027)

## Wat doet unattended-upgrades automatisch

Security-only patches worden automatisch geïnstalleerd. Check:

```bash
systemctl is-active unattended-upgrades
cat /etc/apt/apt.conf.d/20auto-upgrades
```

## Wat je zelf kwartaals doet

Niet-security updates (kernel, PHP minor, netplan, cloud-init) worden **niet** automatisch
geïnstalleerd. Dit doe je handmatig, eens per kwartaal:

```bash
# 1. Check wat er staat te wachten
apt list --upgradable 2>/dev/null | grep -v "^Listing"

# 2. Upgrade (reboot nodig na kernelupdate)
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y
DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -y   # voor "kept back" packages

# 3. Autoremove
apt-get autoremove -y

# 4. Reboot (kort, ~30-60s downtime)
reboot
```

Na reboot verifiëren:

```bash
uname -r                                              # nieuwe kernel actief?
apt list --upgradable 2>/dev/null | grep -v Listing   # 0 regels = klaar
```

## Nginx-PPA brotli (403-probleem, 19-07-2026)

De `ondrej/nginx` PPA werd verwijderd — blokkeerde op IPv6 met 403. `libbrotli1` komt
nu uit Ubuntu main. Geen actie nodig; nginx-brotli-compressie werkt ongewijzigd.

## Schema

| Maand | Actie |
|-------|-------|
| januari | kwartaalcheck + upgrade |
| april | kwartaalcheck + upgrade |
| juli | kwartaalcheck + upgrade |
| oktober | kwartaalcheck + upgrade |

Zet een reminder in `/start` handover als het meer dan 3 maanden geleden is.
