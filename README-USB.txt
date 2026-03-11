HAVUN OP REIS - USB
==================
Code staat NIET op deze stick. Alleen credentials + dit bestand.

OP EEN NIEUWE PC
----------------
1. Git installeren (git-scm.com) als dat nog niet staat.
2. start.bat draaien (op deze USB).
   - Vaultwachtwoord invoeren.
   - Clone path opgeven (bijv. D:\GitHub).
3. Repos clonen, bijv.:
   git clone git@github.com:havun22-hvu/HavunCore.git D:\GitHub\HavunCore
   git clone git@github.com:havun22-hvu/HavunAdmin.git D:\GitHub\HavunAdmin
   (etc.)
4. Editor: VS Code staat op USB (portable) OF Cursor lokaal installeren (cursor.com).
5. Werken. Bij weggaan: stop.bat draaien (verwijdert SSH e.d. van deze PC).

WAT STAAT ER OP DE USB
----------------------
- credentials.vault    (wachtwoord beveiligd)
- ssh-keys.vault       (optioneel,zelfde wachtwoord)
- start.bat            (unlock + credentials klaarzetten)
- stop.bat             (cleanup bij weggaan)
- tools\7-Zip          (voor vault, optioneel als 7-Zip op PC staat)
- VS Code (portable)   (optioneel, als je die op USB zet)

Volledige uitleg: HavunCore/docs/kb/runbooks/op-reis-workflow.md
