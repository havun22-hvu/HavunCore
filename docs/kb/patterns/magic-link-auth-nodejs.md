---
title: Magic Link Authentication Pattern (raw Node.js)
type: pattern
scope: havuncore
last_check: 2026-07-28
---

# Magic Link Authentication Pattern (raw Node.js)

> **Voor projecten zonder Laravel en zonder database.** De Laravel-variant staat in
> `patterns/magic-link-auth.md`; dit is dezelfde standaard (auth v5.1) vertaald naar een
> kale Node-server met JSON-opslag.
> **Referentie-implementatie:** VPDUpdate (`magic-link.js`, `mailer.js`, jul 2026).

## Wanneer dit en niet de Laravel-variant

Geen Eloquent, geen migrations, geen `RateLimiter`-facade, geen `Mail::`. Wat overblijft is
het idee: een kortlevend eenmalig token dat per e-mail naar een geverifieerd adres gaat.

## De vier dingen die je zelf moet bouwen

### 1. Token-opslag zonder database

JSON op schijf, elke request opnieuw gelezen — hetzelfde patroon als de gebruikersopslag in
zo'n project. Twee afwijkingen van de Laravel-referentie, allebei bewust:

| Laravel-variant | Node-variant | Waarom |
|---|---|---|
| token in plaintext in de tabel | **SHA-256 van het token** | Wie het bestand of een oude backup leest kan er niets mee. JSON-bestanden belanden nu eenmaal in backups |
| `email`-kolom | **`userId`** | Er hoeft geen e-mailadres in het tokenbestand te staan. Bij een onbekend adres maak je toch geen token aan |

Vergelijken met `crypto.timingSafeEqual`, niet met `===`.

Schrijf via **write-then-rename** (`writeFileSync` naar `.tmp`, dan `renameSync`). Een crash
halverwege laat anders een afgekapt bestand achter en iedereen is zijn openstaande link kwijt.

Een **corrupt of ontbrekend bestand mag de server niet neerhalen** — vang het af en behandel het
als leeg. Slechtste geval: iedereen vraagt een nieuwe link aan.

### 2. Rate limiting zonder framework

In-memory `Map` van sleutel → array van timestamps, gefilterd op een schuivend venster. Bewust
niet op schijf: een herstart die de teller wist is acceptabel, elke inlogpoging naar disk
schrijven niet.

**Twee assen, niet één:**
- **per IP** — houdt één host tegen die staat te hameren;
- **per e-mailadres** — houdt een verspreide vloed tegen die één mailbox volgooit en je
  SMTP-quota opmaakt (zie `runbooks/mail-credentials-vault.md`).

Alleen op IP limiteren beschermt de mailbox niet; alleen op adres beschermt de server niet.

### 3. Geen enumeratie — ook niet via timing

Eén neutraal antwoord voor élke uitkomst: onbekend adres, rate-limited, mail mislukt. Geen 429,
want dat verklapt dat je te vaak iets probeerde dat bestaat.

De valkuil zit in de **timing**: mail versturen duurt honderden milliseconden en gebeurt alleen
bij een bestaand account. Wie het antwoord ná het versturen geeft, lekt het bestaan van het
account alsnog via de responstijd. Dus: **eerst antwoorden, dan pas versturen.**

```js
neutralResponse();          // antwoord staat vast, ongeacht de uitkomst
if (!user) return;          // pas hierna vertakken
await sendMagicLinkMail(...);
```

### 4. De cookie bij inwisseling: `SameSite=Lax`, niet `Strict`

De klik komt uit een mailclient, dus de navigatie is cross-site. Onder `Strict` houdt de browser
de cookie tegen bij de redirect die erop volgt: de gebruiker landt weer op de loginpagina en
heeft zijn eenmalige token voor niets verbrand. Dit kost je een half uur zoeken als je het niet
weet.

## Verder mis te gaan

- **Het token in je request-log.** Een standaard `console.log(req.url)` schrijft werkende
  login-links naar je pm2-logs. Maskeer de querystring voor de inwissel-route.
- **De link naar een redirect-domein sturen.** Wijst `APP_URL` naar een host die 301't, dan
  breekt het bij elke client die de methode niet behoudt. Altijd het eind-domein.
- **`requireTLS: true`** op de SMTP-transport. Zonder dat mag nodemailer terugvallen op een
  verbinding zonder TLS als STARTTLS mislukt.
- **Mailverbinding bij startup verifiëren** (`transporter.verify()`), zodat een kapotte config in
  de logs staat en niet pas blijkt wanneer iemand wil inloggen.

## Testen zonder framework

`node:test` zit in Node, geen dependency nodig. Maak de klok en het bestandspad injecteerbaar —
dan zijn expiry-grenzen exact te testen in plaats van te racen met de echte tijd:

```js
createMagicLinkStore({ filePath: tmpFile, clock: () => now })
```

Zinnige gevallen: verlopen, de laatste milliseconde vóór expiry, tweede gebruik, misvormd token,
ruwe token niet op schijf, geen e-mailadres in de store, oude link vervalt bij een nieuwe
aanvraag, opruimen na de retentie, corrupt bestand, en de vier rate-limit-gevallen.

> **Node-versies lopen uiteen tussen server en werkplek.** Globs in `node --test` bestaan pas
> vanaf 22, de directory-vorm is stuk op Windows, en `node --test` zonder argument pakt ook losse
> `test-*.js`-scripts in de projectroot op. Een runner van tien regels die zelf `test/*.test.js`
> verzamelt en expliciete bestandsnamen doorgeeft, werkt overal.

## Zie ook

- `standards/unified-auth-strategy.md` — de standaard zelf (v5.1)
- `patterns/magic-link-auth.md` — Laravel-variant
- `runbooks/mail-credentials-vault.md` — Brevo binnen Havun, quota-waarschuwing
