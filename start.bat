@echo off
REM ========================================================
REM  HAVUN OP REIS - START (credentials only)
REM  USB = vault + dit script. Code via git clone.
REM  Zie: docs/kb/runbooks/op-reis-workflow.md
REM  Laatste update: maart 2026
REM ========================================================

setlocal enabledelayedexpansion

set DRIVE=%~d0
cd /d "%DRIVE%\"

echo.
echo ========================================================
echo   HAVUN OP REIS - Credentials
echo   Code staat niet op USB; clone via git
echo ========================================================
echo.

REM === 7-Zip (voor vault) ===
set "SEVENZIP="
if exist "%DRIVE%\tools\7-Zip\7za.exe" set "SEVENZIP=%DRIVE%\tools\7-Zip\7za.exe"
if exist "%DRIVE%\tools\7-Zip\7z.exe"  set "SEVENZIP=%DRIVE%\tools\7-Zip\7z.exe"
if "!SEVENZIP!"=="" (
    where 7z >nul 2>&1
    if !ERRORLEVEL! EQU 0 set "SEVENZIP=7z"
)
if "!SEVENZIP!"=="" (
    echo [WARN] 7-Zip niet gevonden. Zet tools\7-Zip op USB of installeer 7-Zip.
    echo        Vault wordt overgeslagen.
    goto :no_vault
)

REM === VAULT UNLOCK ===
if not exist "%DRIVE%\credentials.vault" goto :no_vault

if exist "%DRIVE%\.vault-unlocked" (
    echo [VAULT] Al eerder unlocked deze sessie.
    goto :vault_done
)

echo [VAULT] Credentials beveiligd met wachtwoord.
echo.

:vault_password
set /p vault_pass="Vault wachtwoord: "
if "!vault_pass!"=="" (
    echo [ERROR] Wachtwoord mag niet leeg zijn.
    goto :vault_password
)

"!SEVENZIP!" x -p"!vault_pass!" -o"%DRIVE%\.vault-temp\" "%DRIVE%\credentials.vault" -y >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    echo [ERROR] Verkeerd wachtwoord.
    goto :vault_password
)

echo [VAULT] Uitpakken...

REM SSH keys -> gebruiker .ssh
if not exist "%USERPROFILE%\.ssh\" mkdir "%USERPROFILE%\.ssh\" 2>nul
set "ssh_found=0"
for %%F in ("%DRIVE%\.vault-temp\id_*") do (
    copy /y "%%F" "%USERPROFILE%\.ssh\%%~nxF" >nul
    set "ssh_found=1"
)
if exist "%DRIVE%\.vault-temp\known_hosts" (
    copy /y "%DRIVE%\.vault-temp\known_hosts" "%USERPROFILE%\.ssh\known_hosts" >nul
)
if "!ssh_found!"=="1" echo   [OK] SSH keys

REM ssh-keys.vault (aparte map, zelfde wachtwoord)
if exist "%DRIVE%\ssh-keys.vault" (
    "!SEVENZIP!" x -p"!vault_pass!" -o"%DRIVE%\.vault-temp-ssh\" "%DRIVE%\ssh-keys.vault" -y >nul 2>&1
    if !ERRORLEVEL! EQU 0 (
        for %%F in ("%DRIVE%\.vault-temp-ssh\id_*") do copy /y "%%F" "%USERPROFILE%\.ssh\%%~nxF" >nul
        if exist "%DRIVE%\.vault-temp-ssh\known_hosts" copy /y "%DRIVE%\.vault-temp-ssh\known_hosts" "%USERPROFILE%\.ssh\known_hosts" >nul
        echo   [OK] SSH keys uit ssh-keys.vault
        rmdir /s /q "%DRIVE%\.vault-temp-ssh\" 2>nul
    )
)

REM Git credentials (op USB, voor credential.helper)
if exist "%DRIVE%\.vault-temp\git-credentials" (
    copy /y "%DRIVE%\.vault-temp\git-credentials" "%DRIVE%\git-credentials" >nul
    echo   [OK] Git credentials
)

REM Clone path vragen -> .env en context.md naar bestaande mappen
echo.
set /p CLONEPATH="Waar staan of komen je repos? (bijv. D:\GitHub): "
if "!CLONEPATH!"=="" set "CLONEPATH=D:\GitHub"
REM Trim spaties
for /f "tokens=* delims= " %%a in ("!CLONEPATH!") do set "CLONEPATH=%%a"

if exist "!CLONEPATH!\" (
    REM .env per project
    for %%F in ("%DRIVE%\.vault-temp\*.env") do (
        set "proj=%%~nF"
        if exist "!CLONEPATH!\!proj!\laravel\" (
            copy /y "%%F" "!CLONEPATH!\!proj!\laravel\.env" >nul 2>&1
            echo   [OK] !proj! .env
        ) else if exist "!CLONEPATH!\!proj!\" (
            copy /y "%%F" "!CLONEPATH!\!proj!\.env" >nul 2>&1
            echo   [OK] !proj! .env
        )
    )
    REM context.md per project (*-context.md -> projnaam)
    for %%F in ("%DRIVE%\.vault-temp\*-context.md") do (
        set "ctx=%%~nF"
        set "proj=!ctx:-context=!"
        if exist "!CLONEPATH!\!proj!\" (
            if not exist "!CLONEPATH!\!proj!\.claude\" mkdir "!CLONEPATH!\!proj!\.claude\" 2>nul
            copy /y "%%F" "!CLONEPATH!\!proj!\.claude\context.md" >nul 2>&1
            echo   [OK] !proj! context.md
        )
    )
) else (
    echo [INFO] Pad !CLONEPATH! bestaat nog niet. Na git clone opnieuw start.bat draaien om .env/context te kopieren.
)

echo Vault unlocked: %date% %time% > "%DRIVE%\.vault-unlocked"
rmdir /s /q "%DRIVE%\.vault-temp\" 2>nul
echo [OK] Vault unlocked.
goto :vault_done

:no_vault
echo [INFO] Geen credentials.vault op USB - alleen Git config hierna.
echo.

:vault_done

REM === GIT CONFIG ===
git config --global user.name "havun22-hvu"
git config --global user.email "havun22@gmail.com"
if exist "%DRIVE%\git-credentials" (
    git config --global credential.helper "store --file=%DRIVE%/git-credentials"
    echo [OK] Git: credentials op USB
) else (
    git config --global credential.helper store
    echo [INFO] Git: geen credentials op USB - handmatig inloggen bij eerste push
)

echo.
echo ========================================================
echo   Klaar.
echo ========================================================
echo   - SSH keys en Git staan klaar.
echo   - Repos: git clone git^@github.com:havun22-hvu/HavunCore.git etc.
echo   - Editor: VS Code op USB of Cursor lokaal installeren.
echo.
echo   Als je weggaat: run stop.bat (verwijdert SSH e.d. van deze PC^).
echo ========================================================
echo.
pause
