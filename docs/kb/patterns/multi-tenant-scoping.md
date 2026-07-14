---
title: "Pattern: Multi-tenant scoping via middleware (Laravel)"
type: pattern
scope: havuncore
last_check: 2026-07-14
---

# Multi-tenant scoping via middleware

> **Referentie-implementatie:** HavunClub (`app/Http/Middleware/ClubScope.php`), incl.
> hardening-tests. **Wanneer:** één DB, meerdere tenants (clubs/scholen/organisaties),
> gescheiden op een `*_id`-kolom.

## Het patroon

Geen Eloquent global-scope-klasse per model, maar **één middleware** die per request
`Model::addGlobalScope('club', fn($q) => $q->where('club_id', $club->id))` toevoegt op een
**expliciete lijst modellen**. Tenant-resolutie via de auth-guard:

- Normale gebruiker → `$user->club` (vast).
- Alleen wie `magClubWisselen()` (superadmin/sitebeheerder) → `session('club_id')`
  (club-switcher). Support-modus (view-only) apart via `SupportMode`-middleware, gewisseld
  wordt geaudit.
- Middleware deelt `currentClub` met alle views.

## Valkuilen (allemaal echt geraakt — HavunClub, jun-jul 2026)

| Valkuil | Les |
|---------|-----|
| **Platform-eigenaar zag 0 records i.p.v. 229** — hij viel in de `session('club_id')`-tak en de sessie stond op een andere/lege club | Platform-eigenaar blijft **altijd** op de eigen club, ook mét sessie-waarde; sluit hem expliciet uit in `magClubWisselen()`. Test: `test_platform_eigenaar_blijft_op_eigen_club_ondanks_sessie` |
| **Tenant-lek**: controllers die buiten de scope-groep query'en (`Judoka::where('status','actief')` zonder club-filter) | Elke web-route die tenant-data raakt hoort ín de `ClubScope`-middleware-groep; audit nieuwe controllers hierop |
| Nieuw model vergeten | De model-lijst in de middleware is handmatig — nieuw tenant-model = regel toevoegen, anders ongescoped |
| Enumeratie via id op platform-routes | Platform-routes draaien bewust zónder scope → gebruik **slug i.p.v. id** in URL's (test geborgd) |
| `Rule::exists()` in Form Requests is niet automatisch gescoped | Handmatig `->where('club_id', ...)` toevoegen (zie HavunClub `JudokaRequest`) |

## Tests om te kopiëren

`tests/Feature/Beheer/MultiTenantHardeningTest.php` + `tests/Feature/Tenancy/ClubScopeTest.php`
— cross-tenant-toegang, sessie-misbruik, slug-vs-id.

## Wanneer dit patroon NIET

Eén-gebruiker-per-dataset zonder gedeelde tenant (bv. Vusista: privébibliotheek per user) →
gewone **Policies + `user_id`-scoping** volstaan; geen tenant-middleware optuigen.

## Zie ook

- `contracts/havunclub-koppelingen.md` — tenant-id-beslissing cross-app (crosswalk, geen centrale id)
- HavunCore-handover 14 jul 2026 — het 0-judoka's-incident dat deze valkuilen blootlegde
