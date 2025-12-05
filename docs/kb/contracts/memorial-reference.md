# Contract: Memorial Reference

> Gedeelde definitie tussen Herdenkingsportaal en HavunAdmin

## Definitie

**Memorial Reference** = Unieke identificatie voor een monument/gedenkpagina

## Format

```
12 hexadecimale karakters (lowercase)
Voorbeeld: 550e8400e29b
```

## Herkomst

Eerste 12 karakters van de monument UUID (zonder hyphens):

```
UUID:      550e8400-e29b-41d4-a716-446655440000
Reference: 550e8400e29b
```

## Gebruik per project

| Project | Gebruik |
|---------|---------|
| Herdenkingsportaal | In checkout, QR codes, URLs |
| HavunAdmin | In Mollie payment metadata, facturatie |

## Validatie

```
- Exact 12 karakters
- Alleen: a-f, 0-9
- Lowercase
- Geen hyphens
```

## Sync

Memorial reference wordt gebruikt om betalingen (HavunAdmin) te koppelen aan gedenkpagina's (Herdenkingsportaal).
