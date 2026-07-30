---
title: Omwegen — __TITLE__
type: reference
scope: __SLUG__
last_check: TODO
---

# Omwegen — __TITLE__

> Code die bestaat omdat de stack iets niet kan. **Bij de tweede regel in deze
> tabel is het een architectuurreview, geen commit.**
> Norm: HavunCore `docs/kb/patterns/omwegen-tellen.md`.

| Datum | Wat | Wat wordt omzeild | Review |
|---|---|---|---|
| — | _(nog geen omwegen)_ | | |

## Wat telt als omweg

- code die het framework **bewust omzeilt** om het snel genoeg te krijgen;
- een **tweede runtime** naast de eerste, voor één taak;
- een **eigen poort** voor een proces dat bij de app hoort;
- een **vangnet** voor iets wat de infrastructuur zelf hoort te doen;
- een **sidecar** omdat de taal iets niet kan (FFI, threads, langlevende processen).

**Niet:** een externe binary voor werk dat nergens in de taal thuishoort
(exiftool, ffmpeg), een cache, een queue-worker, of een bibliotheek van derden.
Dat is normaal gebruik van een stack, geen ontwijking ervan.
