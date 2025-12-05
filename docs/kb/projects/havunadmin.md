# HavunAdmin

> Boekhouding en facturatie systeem

## Overview

| | |
|---|---|
| **Type** | Laravel 11 Application |
| **URL** | https://havunadmin.havun.nl |
| **Local** | D:\GitHub\HavunAdmin |
| **Server staging** | /var/www/havunadmin/staging |
| **Server production** | /var/www/havunadmin/production |
| **Database** | havunadmin_production |

## Features

- Facturatie en offertes
- Mollie betalingen
- Memorial reference sync met Herdenkingsportaal
- PDF generatie
- Bunq bank integratie (planned)

## Tech Stack

- Laravel 11
- Livewire
- TailwindCSS
- Mollie API
- WebAuthn (biometrische login)

## Auth

Eigen Laravel auth + `laragear/webauthn` package.
Niet via HavunCore - elke app beheert eigen auth.

## Task Queue

HavunAdmin kan taken ontvangen via Task Queue:
- Poller: `claude-task-poller@havunadmin.service`
- Logs: `/var/log/claude-task-poller-havunadmin.log`

## Related

- [[projects/havuncore]] - Task Queue host
- [[projects/herdenkingsportaal]] - Deelt memorial data
- [[runbooks/deploy]] - Deploy procedure
