# Herdenkingsportaal

> Memorial portal voor gedenkpagina's

## Overview

| | |
|---|---|
| **Type** | Laravel 11 Application |
| **URL** | https://herdenkingsportaal.nl |
| **Local** | D:\GitHub\Herdenkingsportaal |
| **Server staging** | /var/www/herdenkingsportaal/staging |
| **Server production** | /var/www/herdenkingsportaal/production |
| **Database** | herdenkingsportaal |

## Features

- Gedenkpagina's aanmaken
- Foto's en verhalen uploaden
- QR codes voor grafstenen
- Betalingen via Mollie
- Biometrische login (WebAuthn)

## Tech Stack

- Laravel 11
- Livewire
- TailwindCSS
- Alpine.js
- WebAuthn (`laragear/webauthn`)

## Auth

Eigen auth systeem met:
- Email/password login
- WebAuthn biometrische login
- QR code login (planned)

**Niet via HavunCore** - onafhankelijk per app.

## Memorial Reference

12-karakter code (eerste deel van UUID):
- Voorbeeld: `550e8400e29b`
- Gebruikt voor sync met HavunAdmin
- In Mollie payment metadata

## Task Queue

Kan taken ontvangen via Task Queue:
- Poller: `claude-task-poller@herdenkingsportaal.service`
- Logs: `/var/log/claude-task-poller-herdenkingsportaal.log`

## Related

- [[projects/havunadmin]] - Facturatie sync
- [[projects/havuncore]] - Task Queue host
- [[runbooks/deploy]] - Deploy procedure
