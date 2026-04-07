#!/bin/bash
# Installeer post-commit KB update hook in alle Havun projecten

HOOK_SOURCE="D:/GitHub/HavunCore/scripts/post-commit-kb-update.sh"
PROJECTS=(
    "D:/GitHub/HavunCore"
    "D:/GitHub/HavunAdmin"
    "D:/GitHub/Herdenkingsportaal"
    "D:/GitHub/JudoToernooi"
    "D:/GitHub/Studieplanner"
    "D:/GitHub/Studieplanner-api"
    "D:/GitHub/SafeHavun"
    "D:/GitHub/infosyst"
    "D:/GitHub/havun"
    "D:/GitHub/JudoScoreBoard"
)

for project in "${PROJECTS[@]}"; do
    if [ -d "$project/.git" ]; then
        hook_dir="$project/.git/hooks"
        hook_file="$hook_dir/post-commit"

        # Maak hooks dir als die niet bestaat
        mkdir -p "$hook_dir"

        # Als er al een post-commit hook is, voeg KB update toe
        if [ -f "$hook_file" ]; then
            # Check of KB update al erin zit
            if grep -q "docs:index" "$hook_file" 2>/dev/null; then
                echo "SKIP $(basename $project) — KB hook al geïnstalleerd"
                continue
            fi
            # Voeg toe aan bestaande hook
            echo "" >> "$hook_file"
            echo "# KB auto-update" >> "$hook_file"
            cat "$HOOK_SOURCE" >> "$hook_file"
            echo "UPDATED $(basename $project) — KB hook toegevoegd aan bestaande post-commit"
        else
            # Maak nieuwe hook
            cp "$HOOK_SOURCE" "$hook_file"
            chmod +x "$hook_file"
            echo "OK $(basename $project) — KB hook geïnstalleerd"
        fi
    else
        echo "SKIP $(basename $project) — geen git repo"
    fi
done

echo ""
echo "Klaar. KB wordt nu automatisch bijgewerkt na elke commit."
