#!/bin/bash
# Post-commit hook: update KB index na elke commit
# Draait op de achtergrond zodat de commit niet blokkeert

PROJECT_NAME=$(basename "$(git rev-parse --show-toplevel)" | tr '[:upper:]' '[:lower:]')
HAVUNCORE_PATH="D:/GitHub/HavunCore"

# Alleen draaien als HavunCore bestaat
if [ -d "$HAVUNCORE_PATH" ]; then
    # Index alleen dit project, op de achtergrond, geen output
    cd "$HAVUNCORE_PATH" && php artisan docs:index "$PROJECT_NAME" > /dev/null 2>&1 &
fi
