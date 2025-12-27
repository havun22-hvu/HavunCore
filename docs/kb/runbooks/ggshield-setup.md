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

- [x] HavunCore (27-12-2024)

## Links

- Dashboard: https://dashboard.gitguardian.com
- Docs: https://docs.gitguardian.com/ggshield-docs/getting-started
