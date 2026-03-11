@echo off
REM ========================================================
REM  HAVUN OP REIS - STOP (cleanup)
REM  Verwijdert SSH keys en credentials van deze PC.
REM  Draai dit voordat je weggaat.
REM ========================================================

set DRIVE=%~d0
echo.
echo [CLEANUP] Credentials van deze PC verwijderen...
echo.

REM Git credentials van USB
if exist "%DRIVE%\git-credentials" (
    del /q "%DRIVE%\git-credentials" 2>nul
    echo   [OK] Git credentials van USB verwijderd
)

REM Git config leegmaken
git config --global --unset user.name 2>nul
git config --global --unset user.email 2>nul
git config --global --unset credential.helper 2>nul

REM SSH keys van deze PC
if exist "%USERPROFILE%\.ssh\id_ed25519" del /q "%USERPROFILE%\.ssh\id_ed25519" 2>nul
if exist "%USERPROFILE%\.ssh\id_ed25519.pub" del /q "%USERPROFILE%\.ssh\id_ed25519.pub" 2>nul
if exist "%USERPROFILE%\.ssh\id_rsa" del /q "%USERPROFILE%\.ssh\id_rsa" 2>nul
if exist "%USERPROFILE%\.ssh\id_rsa.pub" del /q "%USERPROFILE%\.ssh\id_rsa.pub" 2>nul
if exist "%USERPROFILE%\.ssh\known_hosts" del /q "%USERPROFILE%\.ssh\known_hosts" 2>nul
echo   [OK] SSH keys verwijderd

REM Vault marker (zodat volgende keer opnieuw unlock nodig is)
if exist "%DRIVE%\.vault-unlocked" del /q "%DRIVE%\.vault-unlocked" 2>nul

echo.
echo [OK] Cleanup klaar. Veilig om te vertrekken.
echo.
pause
