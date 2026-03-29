# Runbook: Security Headers Check

> **Bron:** Externe audit Q1 2026 (VP-04)
> **Frequentie:** Kwartaallijks
> **Geldt voor:** Alle publieke apps

## Vereiste Headers

| Header | Waarde | Waarom |
|--------|--------|--------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Forceert HTTPS |
| `X-Content-Type-Options` | `nosniff` | Voorkomt MIME-type sniffing |
| `X-Frame-Options` | `DENY` of `SAMEORIGIN` | Voorkomt clickjacking |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Beperkt referrer-lekkage |
| `Content-Security-Policy` | Per app configureren | Voorkomt XSS |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Beperkt browser APIs |

## Check uitvoeren

### Snelle check per app:
```bash
curl -sI https://herdenkingsportaal.nl | grep -iE "strict-transport|x-content-type|x-frame|referrer-policy|content-security|permissions-policy"
```

### Alle publieke apps checken:
```bash
for url in herdenkingsportaal.nl judotoernooi.havun.nl havuncore.havun.nl havunadmin.havun.nl; do
  echo "=== $url ==="
  curl -sI "https://$url" | grep -iE "strict-transport|x-content-type|x-frame|referrer-policy|content-security|permissions-policy"
  echo ""
done
```

## Nginx configuratie

Voeg toe aan elke server block in nginx:

```nginx
# Security headers
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
```

**Let op:** Content-Security-Policy moet per app worden geconfigureerd (afhankelijk van externe scripts, CDNs, etc.)

## OWASP ZAP Scan (jaarlijks)

**Tool:** OWASP ZAP (gratis, open source)
**Prioriteit:** Herdenkingsportaal (publiek verkeer + betalingen)

```bash
# Docker-based scan:
docker run -t ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t https://herdenkingsportaal.nl
```

Resultaten opslaan in `docs/audit/owasp-scan-[datum].md`

---

*Aangemaakt: 29 maart 2026 — VP-04*
