# Beslissing: Geen Polling — Altijd WebSocket

> **Geldt voor:** Alle Havun projecten met real-time data
> **Datum:** 21 maart 2026
> **Aanleiding:** JudoScoreBoard ontwerp

## Beslissing

**NOOIT polling gebruiken voor real-time data.** Altijd WebSocket (Laravel Reverb).

## Waarom

- Polling verspilt bandbreedte en server resources
- Polling heeft inherente vertraging (interval-afhankelijk)
- WebSocket is instant push — geen onnodige requests
- Laravel Reverb is al opgezet in onze stack

## Wanneer wel een GET endpoint

- **Initieel ophalen** bij app start of reconnect (1x, niet in een loop)
- **Fallback** na WebSocket disconnect — eenmalig huidige state ophalen, daarna weer WebSocket

## Wanneer NIET

- Nooit `setInterval` + fetch voor real-time updates
- Nooit "poll elke X seconden" patronen

## Toepassing

| Project | Real-time methode |
|---------|------------------|
| JudoToernooi | Reverb (mat updates, chat, weging) |
| JudoScoreBoard | Reverb (wedstrijd assignment, display sync) |
