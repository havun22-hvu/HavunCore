# Runbook: Chrome Testing met Claude Code

> Browser testing, console errors lezen en debuggen via Claude for Chrome extensie

## Vereisten

| Item | Minimum versie | Check |
|------|----------------|-------|
| Claude Code | v2.0.73+ | `claude --version` |
| Chrome extensie | v1.0.36+ | Chrome Web Store |
| Abonnement | Pro/Team/Enterprise | - |

**Let op:** WSL wordt niet ondersteund.

## Installatie

### 1. Chrome extensie installeren

1. Open: https://chromewebstore.google.com/detail/claude/fcoeoabgfenejglbffodgkkbkcdhcgfn
2. Klik "Toevoegen aan Chrome"
3. Accepteer permissions

### 2. Claude Code updaten

```bash
claude --version
# Als < 2.0.73:
claude upgrade
```

### 3. Claude Code starten met Chrome

```bash
claude --chrome
```

### 4. Verbinding testen

In Claude Code terminal:
```
/chrome
```

Selecteer "Enabled by default" om altijd te gebruiken.

## Workflow: Code Bouwen + Browser Testen

```
┌─────────────────────────────────────────────────────────────┐
│  1. Claude Code bouwt/wijzigt code                          │
│                    ↓                                        │
│  2. Claude opent localhost in Chrome                        │
│                    ↓                                        │
│  3. Claude leest console errors, DOM, network               │
│                    ↓                                        │
│  4. Errors gaan terug naar Claude Code                      │
│                    ↓                                        │
│  5. Claude Code past code aan                               │
│                    ↓                                        │
│  6. Herhaal tot werkend                                     │
└─────────────────────────────────────────────────────────────┘
```

## Capabilities

| Functie | Beschrijving |
|---------|--------------|
| **Navigatie** | Tabs openen, URL's bezoeken |
| **Interactie** | Klikken, typen, scrollen, formulieren |
| **Console logs** | Errors, warnings, info lezen |
| **Network** | Requests/responses monitoren |
| **DOM** | Pagina-inhoud analyseren |
| **Screenshots** | Visuele state vastleggen |
| **GIF opnamen** | Interacties opnemen |

## Voorbeeldcommando's

### Basis navigatie
```
Open localhost:8000 in Chrome
```

### Console errors checken
```
Check de browser console voor errors
```

### Interactie testen
```
Klik op de login button en vul het formulier in met test@example.com
```

### Screenshot maken
```
Maak een screenshot van de huidige pagina
```

### Volledige test
```
Open localhost:8000, log in met test@test.com/password,
ga naar /dashboard en check of er console errors zijn
```

## Lokale Development Ports

| Project | URL |
|---------|-----|
| HavunCore | http://localhost:8000 |
| HavunAdmin | http://localhost:8001 |
| Herdenkingsportaal | http://localhost:8002 |
| Studieplanner-api | http://localhost:8003 |
| SafeHavun | http://localhost:8004 |
| Infosyst | http://localhost:8005 |
| IDSee | http://localhost:8006 |
| JudoToernooi | http://localhost:8007 |
| HavunVet | http://localhost:8008 |

## Troubleshooting

### Extensie niet verbonden

```bash
# Herstart Claude Code met chrome flag
claude --chrome

# Check status
/chrome
```

### Permissions geweigerd

1. Chrome → Extensies → Claude for Chrome
2. Klik op "Details"
3. Zet "Toegang tot sitegegevens" aan

### Localhost niet bereikbaar

```bash
# Check of server draait
php artisan serve --port=8000

# Of voor npm projecten
npm run dev
```

## Integratie met andere Claude Code features

### Gecombineerd met /init
```
/init
# Genereert CLAUDE.md, dan:
Open de app in Chrome en test de basis flow
```

### Gecombineerd met tests
```
Run de PHPUnit tests, open daarna Chrome en test handmatig de checkout flow
```

### Gecombineerd met git
```
Fix alle console errors, commit daarna met message "fix: resolve console warnings"
```

## Best Practices

1. **Start server eerst** - Zorg dat `php artisan serve` draait
2. **Check poort** - Gebruik de juiste poort per project
3. **Clear cache** - Bij vreemde errors: `php artisan cache:clear`
4. **Console open** - Vraag Claude expliciet om console te checken
5. **Screenshots** - Bij visuele bugs, vraag om screenshot

## Zie ook

- `docs/setup/MCP-SETUP.md` - MCP server configuratie
- `docs/kb/runbooks/troubleshoot.md` - Algemene troubleshooting
- `.claude/context.md` - Local development ports
