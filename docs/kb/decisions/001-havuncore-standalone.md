# ADR-001: HavunCore als Standalone App

> Architecture Decision Record

## Status

**Accepted** - 25 november 2025

## Context

HavunCore was oorspronkelijk een Composer package dat gedeeld werd tussen projecten. De Task Queue API was gehost in HavunAdmin.

Problemen:
- Task Queue in HavunAdmin was onlogisch (boekhoudsoftware host orchestratie?)
- Geen eigen database voor HavunCore
- Moeilijk te deployen als package

## Decision

HavunCore wordt een **standalone Laravel 11 applicatie** met:
- Eigen database (`havuncore`)
- Eigen webinterface (https://havuncore.havun.nl)
- Task Queue API hier gehost
- Vault API hier gehost

## Consequences

### Positief
- Logische architectuur (orchestratie in orchestrator)
- Eigen database voor tasks, vault, logs
- Onafhankelijk deploybaar
- Webapp voor remote beheer

### Negatief
- Extra applicatie om te beheren
- Meer server resources nodig

## Related

- [[projects/havuncore]]
- [[002-decentrale-auth]]
