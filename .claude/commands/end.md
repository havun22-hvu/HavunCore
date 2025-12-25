# End Session Command

Voer de volgende stappen uit om de sessie netjes af te ronden:

## 1. Update MD bestanden
- Update CLAUDE.md met relevante wijzigingen uit deze sessie
- Update CHANGELOG.md als er functionele wijzigingen zijn
- Check of andere docs bijgewerkt moeten worden

## 2. Git commit & push
- `git add .`
- Maak een duidelijke commit message met samenvatting van de wijzigingen
- `git push origin master`

## 3. Deploy naar server
- SSH naar 188.245.159.115
- Pull in /var/www/development/HavunCore
- Clear caches indien nodig (php artisan config:clear, cache:clear)

## 4. Branch cleanup
- Check op open branches: `git branch -a`
- Verwijder gemergte lokale branches: `git branch --merged | grep -v master | xargs git branch -d`
- Check open pull requests: `gh pr list`
- Sluit/merge open PRs indien gereed

## 5. USB Stick bijwerken
- Sync alle projecten naar USB (H: drive):
  ```powershell
  powershell -ExecutionPolicy Bypass -File "D:\GitHub\sync-to-usb.ps1"
  ```
- Dit synct: HavunCore, HavunAdmin, Herdenkingsportaal, havuncore-webapp, havun-mcp, BertvanderHeide, VPDUpdate, SafeHavun, Studieplanner
- Tools (VS Code, Node.js, Claude Code) staan al op de USB

## 6. Bevestig aan gebruiker
- Geef korte samenvatting van wat er gedaan is
- Vermeld eventuele openstaande items
- Bevestig dat USB stick is bijgewerkt

## 7. Sluit Claude af
- Zeg: "Sessie afgerond. USB bijgewerkt. Druk Ctrl+D of typ 'exit' om Claude te sluiten."
