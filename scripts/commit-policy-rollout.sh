#!/bin/bash
# Commit + push the session-flow-policy reference rollout per repo.
# Run after insert-session-policy.py.

PROJECTS=(
    "Aeterna"
    "HavunAdmin"
    "HavunClub"
    "HavunCore"
    "havuncore-webapp"
    "Havunity"
    "HavunVet"
    "Herdenkingsportaal"
    "IDSee"
    "Infosyst"
    "JudoScoreBoard"
    "JudoToernooi"
    "Munus"
    "SafeHavun"
    "Studieplanner"
    "Studieplanner-api"
    "VPDUpdate"
)

MSG=$(cat <<'EOF'
docs(commands): reference session-flow-policy in /start and /end

Adds a one-line callout at the top of /start.md and /end.md pointing
to HavunCore/docs/kb/reference/session-flow-policy.md, which is the
single source of truth for:

- /start: Henk decides when sessions stop, Claude never proposes
  ending or pausing
- /end: sync-and-deploy verplicht — commit + push, deploy to staging
  where available, always ask explicitly before deploying to prod

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)

for proj in "${PROJECTS[@]}"; do
    dir="/d/GitHub/$proj"
    echo "=== $proj ==="
    cd "$dir" || { echo "  skip: cannot cd"; continue; }

    # Stage only the two files
    git add .claude/commands/start.md .claude/commands/end.md 2>&1 | tail -3

    # Commit only if there is staged change for these files
    if git diff --cached --quiet -- .claude/commands/start.md .claude/commands/end.md; then
        echo "  no changes staged"
        continue
    fi

    git commit -m "$MSG" 2>&1 | tail -2

    # Try to push; tolerate failures (e.g. no remote, no upstream)
    git push 2>&1 | tail -2 || echo "  push failed (likely no remote / no upstream)"
done

echo "=== done ==="
