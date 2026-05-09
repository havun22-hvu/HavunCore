#!/usr/bin/env python3
"""
Insert the session-flow-policy reference block into every project's
.claude/commands/start.md and .claude/commands/end.md exactly once.

Idempotent: skips files that already contain 'session-flow-policy'.
"""
import sys
from pathlib import Path

PROJECTS = [
    "Aeterna",
    "HavunAdmin",
    "HavunClub",
    "HavunCore",
    "havuncore-webapp",
    "Havunity",
    "HavunVet",
    "Herdenkingsportaal",
    "IDSee",
    "Infosyst",
    "JudoScoreBoard",
    "JudoToernooi",
    "Munus",
    "SafeHavun",
    "Studieplanner",
    "Studieplanner-api",
    "VPDUpdate",
]

BASE = Path("D:/GitHub")

START_BLOCK = (
    "\n"
    "> **Sessie-policy:** Henk bepaalt wanneer de sessie stopt — Claude stelt **nooit** voor om af te sluiten "
    "of te pauzeren, en blijft altijd klaar voor de volgende taak. Volledige policy: "
    "`HavunCore/docs/kb/reference/session-flow-policy.md`.\n"
)

END_BLOCK = (
    "\n"
    "> **Sync-en-deploy verplicht:** vóór afsluiten alle wijzigingen committen + pushen, deploy naar staging "
    "(waar beschikbaar), en altijd expliciet vragen of ook naar productie gedeployed moet worden. "
    "Volledige policy: `HavunCore/docs/kb/reference/session-flow-policy.md`.\n"
)


def patch(path: Path, heading: str, block: str) -> str:
    if not path.exists():
        return f"  skip (missing): {path}"
    text = path.read_text(encoding="utf-8")
    if "session-flow-policy" in text:
        return f"  skip (already): {path}"
    if heading not in text:
        return f"  skip (no heading): {path}"
    new = text.replace(heading, heading + block, 1)
    path.write_text(new, encoding="utf-8", newline="\n")
    return f"  patched: {path}"


def main() -> int:
    for proj in PROJECTS:
        proj_dir = BASE / proj
        start = proj_dir / ".claude" / "commands" / "start.md"
        end = proj_dir / ".claude" / "commands" / "end.md"
        print(f"--- {proj} ---")
        print(patch(start, "# Start Session Command\n", START_BLOCK))
        print(patch(end, "# End Session Command\n", END_BLOCK))
    return 0


if __name__ == "__main__":
    sys.exit(main())
