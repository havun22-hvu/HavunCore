---
title: Geen hardcoded secrets in tests — ook wegwerksleutels
type: runbook
scope: havuncore
last_updated: 2026-06-11
tags: [security, tests, gitguardian, secrets, principe]
---

# Geen hardcoded secrets in tests — ook wegwerksleutels

> **Principe (Henk, 11 jun 2026):** ook een waardeloze testsleutel hoort niet
> hardcoded in de repo. Het gaat niet om de waarde van de sleutel, maar om de
> gewoonte. Secrets-scanners (GitGuardian) moeten 0 treffers houden.

## Regel

- **Nooit** een sleutel/secret als string-literal in test- of productiecode.
  Ook niet als 'ie nergens toegang toe geeft (mock-backend, throwaway key).
- Heeft een test structureel een sleutel nodig? **Genereer 'm at runtime.**
- Echte secrets horen in env-vars / de Vault, nooit in git.

## Voorbeeld — WebAuthn virtual authenticator (Playwright)

De Chromium virtual authenticator wil een structureel geldige P-256 PKCS#8
sleutel voor `addCredential`. Niet hardcoden — per testrun genereren:

```js
import { generateKeyPairSync } from 'crypto';

function generateThrowawayPrivateKeyB64() {
  const { privateKey } = generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
  return privateKey.export({ type: 'pkcs8', format: 'der' }).toString('base64');
}
```

Bron: `havuncore-webapp:frontend/e2e/webauthn.js` (commit 502c125).

## Een gelekte testsleutel uit de historie purgen

Zit het secret al in een commit, dan is verwijderen-in-een-nieuwe-commit niet
genoeg — de string blijft in de historie en blijft de scanner triggeren. Purgen:

1. **Rotatie nodig?** Bij een echte sleutel: eerst roteren/intrekken. Een
   throwaway testsleutel geeft nergens toegang → geen rotatie, alleen purgen.
2. **Eén commit getroffen** (zoals hier): backup-ref, dan de commit herschrijven
   zodat het secret er nooit in zat:
   ```bash
   git branch backup-pre-purge <head>
   git reset --soft <parent-van-de-vervuilde-commit>
   git commit -C <oude-commit>            # zelfde message/author, schone tree
   git diff backup-pre-purge HEAD --stat  # moet leeg zijn (geen inhoudsverlies)
   git push --force-with-lease origin <branch>
   git branch -D backup-pre-purge
   ```
3. **Meerdere commits / meerdere files:** `git filter-repo --replace-text`
   (niet standaard geïnstalleerd) of BFG. Daarna force-push.
4. **Andere checkouts** (productieserver pullt deze repo): die hebben de oude
   historie nog lokaal → bij de eerstvolgende deploy
   `git fetch origin && git reset --hard origin/<branch>`.
5. **GitGuardian-incident** in het dashboard op *Resolved / False positive*
   zetten met reden.

## Checklist bij een GitGuardian-melding

- [ ] Is het een echt secret of een wegwerp/testwaarde? (bepaalt rotatie)
- [ ] Bron vervangen door runtime-generatie of env-var
- [ ] Uit de historie purgen (zie boven) — niet alleen uit HEAD
- [ ] Andere checkouts (prod) resetten naar de herschreven historie
- [ ] Incident in GitGuardian afmelden
