---
title: Password Hashing Pattern
type: pattern
scope: havuncore
last_check: 2026-04-22
---

# Password Hashing Pattern

> **Geldt voor:** Alle Laravel 11 projecten met `'password' => 'hashed'` cast

## Regel

**Gebruik NOOIT `Hash::make()` of `bcrypt()` als het User model de `'hashed'` cast heeft.**

```php
// User model
protected function casts(): array {
    return [
        'password' => 'hashed',  // ← Hasht automatisch bij assignment
    ];
}
```

## Goed vs Fout

```php
// ✅ GOED - Cast doet het werk
User::create(['password' => $request->password]);
$user->update(['password' => $newPassword]);

// ❌ FOUT - Redundant, risico op problemen
User::create(['password' => Hash::make($request->password)]);
```

## Waarom

- De `hashed` cast roept automatisch `Hash::make()` aan bij elke password assignment
- De cast heeft een `isHashed()` check die double-hashing voorkomt
- Maar handmatige `Hash::make()` is redundant en verwarrend
- Kan leiden tot `RuntimeException: This password does not use the Bcrypt algorithm`

## Uitzondering

Als je NIET via het Eloquent model gaat (bijv. `DB::table()->update()`), dan MOET je wel `Hash::make()` gebruiken.

## Status per project

| Project | Cast aanwezig | Redundante calls | Status |
|---------|--------------|-----------------|--------|
| HavunAdmin | ✅ | Verwijderd (2026-03-02) | ✅ Clean |
| Herdenkingsportaal | ✅ | Verwijderd (2026-03-02) | ✅ Clean |
| Infosyst | ✅ | Nooit gehad | ✅ Clean |
| SafeHavun | ✅ | Nooit gehad | ✅ Clean |
| HavunClub | ✅ | Nooit gehad | ✅ Clean |
| HavunCore | N/A | Eigen systeem (AuthUser) | ✅ OK |

*Laatst bijgewerkt: 2 maart 2026*
