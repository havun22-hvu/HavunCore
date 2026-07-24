---
title: Let's Encrypt YE1/YR1-intermediate — ERR_SSL_PROTOCOL_ERROR / CRYPT_E_NO_REVOCATION_CHECK
type: runbook
scope: havun-servers
last_updated: 2026-07-20
---

# Nieuw Let's Encrypt-cert: browser SSL-fout / curl revocation-fout

**Symptoom (19-07-2026, veen.havun.nl):** een vers uitgegeven cert geeft in de
browser `ERR_SSL_PROTOCOL_ERROR` en met `curl.exe` op Windows
`CRYPT_E_NO_REVOCATION_CHECK (0x80092012)`. Een ouder cert op dezelfde server
(havun.nl) werkt wel.

**Oorzaak:** Let's Encrypt rolde in 2026 nieuwe intermediates uit (ECDSA:
`YE1` → `Root YE` → `ISRG Root X2`; RSA: `YR1` → `Root YR` → `ISRG Root X1`).
Vlak na uitrol kunnen clients de intrekkingsstatus (CRL) van de nieuwste
intermediate nog niet valideren. `curl.exe` (schannel) doet een verplichte,
strikte revocation-check en faalt hard; browsers doen soft-fail — maar sommige
tonen alsnog een protocol-fout tot de chain gepropageerd is.

**Fix die werkte:** heruitgifte als **RSA**-cert, dat via de al jaren
universeel-vertrouwde `ISRG Root X1` keten gaat:

```bash
certbot certonly --force-renewal --cert-name <domein> --key-type rsa --nginx --non-interactive
systemctl reload nginx
```

Daarna: browsers (Brave/Chrome/Edge/Firefox) werken. `curl.exe` kan strikt
blijven falen — dat is een curl-eigenaardigheid, geen sitefout.

**Diagnose-commando's:**
```bash
# welke intermediate/chain serveert het cert?
openssl crl2pkcs7 -nocrl -certfile /etc/letsencrypt/live/<domein>/fullchain.pem \
  | openssl pkcs7 -print_certs -noout | grep -E "subject=|issuer="
# exacte client-fout (Windows):
curl.exe -v https://<domein> 2>&1 | Select-String "schannel|SSL|TLS"
```

**Let op:** ECDSA-heruitgifte (`--key-type ecdsa`) helpt NIET — die blijft op
YE1/Root YE/ISRG Root X2. De RSA-keten (X1) is de bredere vertrouwensbasis.
