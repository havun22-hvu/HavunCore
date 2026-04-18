# ggshield Setup (Pre-commit Secret Scanning)

> Voorkomt dat secrets in git commits terechtkomen

## Wat het doet

- Scant elke commit VOORDAT die in git komt
- Blokkeert commits met API keys, wachtwoorden, tokens
- Gratis voor persoonlijk gebruik

## Installatie (eenmalig per machine)

```bash
# 1. Installeer ggshield
pip install ggshield

# 2. Login via browser (maakt automatisch token aan)
python -m ggshield auth login --method web
```

### Windows zonder Python

De hook roept `python -m ggshield` aan. Als Python niet op je PATH staat
(controleer met `where python` in PowerShell), dan faalt **elke** commit met:

```
.git/hooks/pre-commit: line 2: python: command not found
```

Installeer Python eerst (eenmalig, user-scope, geen admin nodig):

```powershell
winget install Python.Python.3.12 --scope user
```

Open daarna een **nieuwe** terminal, dan:

```powershell
pip install ggshield
python -m ggshield auth login --method web
```

Vanaf dat moment werkt de hook in alle repos die `.git/hooks/pre-commit`
gezet hebben (HavunCore, HavunAdmin, etc.).

## Pre-commit hook installeren (per repo)

```bash
cd /pad/naar/repo
python -m ggshield install --mode local
```

Dit maakt `.git/hooks/pre-commit` aan met:
```sh
#!/bin/sh
python -m ggshield secret scan pre-commit "$@"
```

## Testen

```bash
# Maak test commit
echo "test" > test.txt && git add test.txt && git commit -m "Test"
# Output: "No secrets have been found" = werkt!
```

## Troubleshooting

| Probleem | Oplossing |
|----------|-----------|
| "Token is missing scope" | `python -m ggshield auth login --method web` |
| Command not found | Gebruik `python -m ggshield` i.p.v. `ggshield` |
| Hook werkt niet | Check of `.git/hooks/pre-commit` executable is |
| `python: command not found` (Windows) | Python niet geïnstalleerd — zie sectie "Windows zonder Python" |

## Handmatig scannen

```bash
# Scan staged files
python -m ggshield secret scan pre-commit

# Scan hele repo
python -m ggshield secret scan repo .

# Scan specifiek bestand
python -m ggshield secret scan path bestand.txt
```

## Repos met hook

Laatste audit: **18-04-2026** — alle 9 projecten hebben de hook.

| Project | Status | Sinds |
|---------|--------|-------|
| HavunCore | ✅ | 27-12-2024 |
| HavunAdmin | ✅ | 27-12-2024 |
| Herdenkingsportaal | ✅ | 27-12-2024 |
| Infosyst | ✅ | 27-12-2024 |
| SafeHavun | ✅ | 27-12-2024 |
| Studieplanner (Expo) | ✅ | 27-12-2024 |
| JudoToernooi | ✅ | ≤ 18-04-2026 (bestond al) |
| JudoScoreBoard | ✅ | ≤ 18-04-2026 (bestond al) |
| Studieplanner-api | ✅ | **18-04-2026** (toegevoegd deze audit) |
| HavunVet | ✅ | **18-04-2026** (toegevoegd deze audit) |

### Audit-commando (alle projecten in één keer)

```bash
for r in D:/GitHub/HavunCore D:/GitHub/HavunAdmin D:/GitHub/Herdenkingsportaal \
         D:/GitHub/JudoToernooi D:/GitHub/SafeHavun D:/GitHub/infosyst \
         D:/GitHub/Studieplanner-api D:/GitHub/HavunVet D:/GitHub/JudoScoreBoard \
         D:/GitHub/Studieplanner; do
  f="$r/.git/hooks/pre-commit"
  [ -f "$f" ] && echo "$(basename $r): ✅" || echo "$(basename $r): ❌ MISSING"
done
```

## Links

- Dashboard: https://dashboard.gitguardian.com
- Docs: https://docs.gitguardian.com/ggshield-docs/getting-started
