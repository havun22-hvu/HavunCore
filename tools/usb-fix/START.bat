@echo off
REM ========================================================
REM  HAVUN PORTABLE DEVELOPMENT - START
REM  Volledig standalone - geen installatie nodig!
REM  Laatste update: 14 februari 2026
REM ========================================================

setlocal enabledelayedexpansion

set DRIVE=%~d0
cd /d "%DRIVE%\"

echo.
echo ========================================================
echo   HAVUN PORTABLE DEVELOPMENT
echo   Alles draait vanaf USB - geen installatie nodig
echo ========================================================
echo.

REM === PORTABLE TOOLS IN PATH ===
set "PATH=%DRIVE%\tools\php;%DRIVE%\tools\git\cmd;%DRIVE%\tools\git\usr\bin;%DRIVE%\tools\nodejs;%DRIVE%\tools\npm-global;%DRIVE%\tools\VSCode;%DRIVE%\tools\7-Zip;%PATH%"

REM Tool checks
set "tools_ok=1"
if exist "%DRIVE%\tools\php\php.exe"       (echo   [OK] PHP portable)       else (echo   [!!] PHP niet gevonden&       set tools_ok=0)
if exist "%DRIVE%\tools\git\cmd\git.exe"   (echo   [OK] Git portable)       else (echo   [!!] Git niet gevonden&       set tools_ok=0)
if exist "%DRIVE%\tools\nodejs\node.exe"   (echo   [OK] Node.js portable)   else (echo   [!!] Node.js niet gevonden)
if exist "%DRIVE%\tools\VSCode\Code.exe"   (echo   [OK] VS Code portable)   else (echo   [!!] VS Code niet gevonden)
if exist "%DRIVE%\tools\npm-global\claude.cmd" (echo   [OK] Claude Code)    else (echo   [!!] Claude Code niet gevonden)
echo.

REM === VAULT: Auto-extract credentials ===
if exist "%DRIVE%\credentials.vault" (
    if not exist "%DRIVE%\.vault-unlocked" (
        echo [VAULT] Credentials zijn beveiligd.
        echo.

        if not exist "%DRIVE%\tools\7-Zip\7za.exe" (
            if not exist "%DRIVE%\tools\7-Zip\7z.exe" (
                echo [WARN] 7-Zip niet gevonden - vault overgeslagen
                goto :skip_vault
            )
            set "SEVENZIP=%DRIVE%\tools\7-Zip\7z.exe"
        ) else (
            set "SEVENZIP=%DRIVE%\tools\7-Zip\7za.exe"
        )

        :vault_password
        set /p vault_pass="Vault wachtwoord: "

        if "!vault_pass!"=="" (
            echo [ERROR] Wachtwoord mag niet leeg zijn!
            goto :vault_password
        )

        "!SEVENZIP!" x -p"!vault_pass!" -o"%DRIVE%\.vault-temp\" "%DRIVE%\credentials.vault" -y >nul 2>&1

        if %ERRORLEVEL% NEQ 0 (
            echo [ERROR] Verkeerd wachtwoord!
            goto :vault_password
        )

        echo [VAULT] Extracting credentials...

        REM Extract .env files to projects
        for %%F in ("%DRIVE%\.vault-temp\*.env") do (
            set "envfile=%%~nF"
            if exist "%DRIVE%\projects\!envfile!\" (
                copy /y "%%F" "%DRIVE%\projects\!envfile!\.env" >nul
                echo   [OK] !envfile! .env
            )
        )

        REM Extract context.md files
        for %%F in ("%DRIVE%\.vault-temp\*-context.md") do (
            set "ctxfile=%%~nF"
            set "projname=!ctxfile:-context=!"
            if exist "%DRIVE%\projects\!projname!\" (
                if not exist "%DRIVE%\projects\!projname!\.claude\" mkdir "%DRIVE%\projects\!projname!\.claude\"
                copy /y "%%F" "%DRIVE%\projects\!projname!\.claude\context.md" >nul
            )
        )

        REM Extract git-credentials (GitHub token)
        if exist "%DRIVE%\.vault-temp\git-credentials" (
            copy /y "%DRIVE%\.vault-temp\git-credentials" "%DRIVE%\tools\git-credentials" >nul
            echo   [OK] GitHub credentials
        )

        echo Vault unlocked: %date% %time% > "%DRIVE%\.vault-unlocked"
        rmdir /s /q "%DRIVE%\.vault-temp\" 2>nul
        echo [OK] Vault unlocked!
        echo.
    ) else (
        echo [VAULT] Al unlocked
        echo.
    )
)
:skip_vault

REM === GIT CONFIGURATIE ===
git config --global user.name "havun22-hvu"
git config --global user.email "henk@havun.nl"
git config --global credential.helper "store --file=%DRIVE%\tools\git-credentials"
if exist "%DRIVE%\tools\git-credentials" (
    echo [OK] GitHub: automatisch ingelogd
) else (
    echo [!!] GitHub: geen credentials - git push werkt niet
)

REM === VS CODE SETTINGS KLAARZETTEN ===
if not exist "%TEMP%\vscode-havun\User" mkdir "%TEMP%\vscode-havun\User" 2>nul
if exist "%DRIVE%\tools\vscode-settings\User\settings.json" (
    copy /y "%DRIVE%\tools\vscode-settings\User\settings.json" "%TEMP%\vscode-havun\User\settings.json" >nul
    echo [OK] VS Code: CMD als default terminal
)

REM === VS CODE WORKSPACE SETTINGS IN ALLE PROJECTEN ===
for /d %%D in ("%DRIVE%\projects\*") do (
    set "VSDIR=%%D\.vscode"
    if not exist "!VSDIR!" mkdir "!VSDIR!" 2>nul
    echo {"terminal.integrated.defaultProfile.windows":"Command Prompt","terminal.integrated.profiles.windows":{"Command Prompt":{"path":"cmd.exe"}},"terminal.integrated.env.windows":{"PATH":"%DRIVE%\\tools\\php;%DRIVE%\\tools\\git\\cmd;%DRIVE%\\tools\\git\\usr\\bin;%DRIVE%\\tools\\nodejs;%DRIVE%\\tools\\npm-global;${env:PATH}"}} > "!VSDIR!\settings.json"
    REM Also in laravel subfolder if exists
    if exist "%%D\laravel\" (
        if not exist "%%D\laravel\.vscode" mkdir "%%D\laravel\.vscode" 2>nul
        echo {"terminal.integrated.defaultProfile.windows":"Command Prompt","terminal.integrated.profiles.windows":{"Command Prompt":{"path":"cmd.exe"}},"terminal.integrated.env.windows":{"PATH":"%DRIVE%\\tools\\php;%DRIVE%\\tools\\git\\cmd;%DRIVE%\\tools\\git\\usr\\bin;%DRIVE%\\tools\\nodejs;%DRIVE%\\tools\\npm-global;${env:PATH}"}} > "%%D\laravel\.vscode\settings.json"
    )
)
echo [OK] Workspace settings: CMD + tools in PATH
echo.

REM === PROJECT SELECTIE ===
:project_menu
echo ========================================================
echo   Kies project:
echo ========================================================
echo.

set count=0
for /d %%D in ("%DRIVE%\projects\*") do (
    set /a count+=1
    echo   [!count!] %%~nxD
    set "proj[!count!]=%%D"
    set "name[!count!]=%%~nxD"
)

echo.
echo   [T] Terminal (alle tools in PATH)
echo   [Q] Afsluiten
echo.
set /p choice="Keuze: "

if /i "%choice%"=="T" goto :terminal
if /i "%choice%"=="Q" goto :quit

set "PROJECT=!proj[%choice%]!"
set "PROJNAME=!name[%choice%]!"

if "%PROJECT%"=="" (
    echo [ERROR] Ongeldige keuze!
    echo.
    goto :project_menu
)

REM Detect laravel subfolder
set "CODEDIR=%PROJECT%"
if exist "%PROJECT%\laravel\artisan" set "CODEDIR=%PROJECT%\laravel"

cd /d "%PROJECT%"

REM Git pull
if exist ".git\" (
    echo [GIT] Code ophalen...
    git pull 2>nul
    echo.
)

REM === ACTIE MENU ===
:action_menu
echo ========================================================
echo   %PROJNAME% - Wat wil je doen?
echo ========================================================
echo.
echo   [1] VS Code openen
echo   [2] VS Code + Claude Code
echo   [3] Claude Code alleen
echo   [4] Git status
echo   [5] Commit + push
if exist "%CODEDIR%\artisan" (
echo   [6] Lokaal draaien (php artisan serve)
echo   [7] Deploy naar server
)
echo.
echo   [B] Terug naar projectkeuze
echo   [Q] Afsluiten
echo.
set /p actie="Keuze: "

if "%actie%"=="1" goto :do_vscode
if "%actie%"=="2" goto :do_both
if "%actie%"=="3" goto :do_claude
if "%actie%"=="4" goto :do_status
if "%actie%"=="5" goto :do_commit
if "%actie%"=="6" goto :do_serve
if "%actie%"=="7" goto :do_deploy
if /i "%actie%"=="B" goto :project_menu
if /i "%actie%"=="Q" goto :quit
echo [ERROR] Ongeldige keuze!
goto :action_menu

:do_vscode
start "" "%DRIVE%\tools\VSCode\Code.exe" --user-data-dir "%TEMP%\vscode-havun" "%CODEDIR%"
echo [OK] VS Code geopend
goto :action_menu

:do_both
start "" "%DRIVE%\tools\VSCode\Code.exe" --user-data-dir "%TEMP%\vscode-havun" "%CODEDIR%"
echo [OK] VS Code geopend
echo [INFO] Claude Code starten...
cd /d "%CODEDIR%"
cmd /k "set PATH=%DRIVE%\tools\php;%DRIVE%\tools\git\cmd;%DRIVE%\tools\git\usr\bin;%DRIVE%\tools\nodejs;%DRIVE%\tools\npm-global;%DRIVE%\tools\7-Zip;%PATH% && claude"
goto :action_menu

:do_claude
cd /d "%CODEDIR%"
cmd /k "set PATH=%DRIVE%\tools\php;%DRIVE%\tools\git\cmd;%DRIVE%\tools\git\usr\bin;%DRIVE%\tools\nodejs;%DRIVE%\tools\npm-global;%DRIVE%\tools\7-Zip;%PATH% && claude"
goto :action_menu

:do_status
cd /d "%PROJECT%"
git status
echo.
git log --oneline -5
echo.
pause
goto :action_menu

:do_commit
cd /d "%PROJECT%"
git status -s
echo.
git status -s > "%TEMP%\havun_git.tmp"
for %%A in ("%TEMP%\havun_git.tmp") do if %%~zA==0 (
    echo [WARN] Geen wijzigingen!
    pause
    goto :action_menu
)
set /p cmsg="Commit message: "
if "!cmsg!"=="" set cmsg=fix: Quick fix
git add -A
git commit -m "!cmsg!"
echo.
echo [INFO] Pushen...
git push
echo [OK] Gepusht!
pause
goto :action_menu

:do_serve
if not exist "%CODEDIR%\artisan" (
    echo [ERROR] Geen Laravel project!
    pause
    goto :action_menu
)
cd /d "%CODEDIR%"
if not exist "vendor\" (
    echo [INFO] Dependencies installeren...
    "%DRIVE%\tools\php\php.exe" "%DRIVE%\tools\php\composer.phar" install --no-dev
)
echo [INFO] Server starten op http://localhost:8000
echo [INFO] Stop met CTRL+C
"%DRIVE%\tools\php\php.exe" artisan serve
goto :action_menu

:do_deploy
cd /d "%PROJECT%"
echo.
echo Gewijzigde bestanden:
git status -s
echo.
git status -s > "%TEMP%\havun_git.tmp"
for %%A in ("%TEMP%\havun_git.tmp") do if not %%~zA==0 (
    set /p dmsg="Commit message: "
    if "!dmsg!"=="" set dmsg=fix: Deploy fix
    git add -A
    git commit -m "!dmsg!"
)
echo [INFO] Pushen...
git push
echo.
set /p dodeploy="SSH deploy? (J/N): "
if /i "!dodeploy!"=="J" (
    echo.
    echo [INFO] Deployen op server...
    echo Welk server pad? (bijv. /var/www/judotoernooi/laravel)
    set /p serverpad="Server pad: "
    ssh root@188.245.159.115 "cd !serverpad! && git pull && php artisan optimize:clear && php artisan optimize"
    echo [OK] Deploy compleet!
)
pause
goto :action_menu

:terminal
echo [INFO] Terminal met alle Havun tools...
cmd /k "set PATH=%DRIVE%\tools\php;%DRIVE%\tools\git\cmd;%DRIVE%\tools\git\usr\bin;%DRIVE%\tools\nodejs;%DRIVE%\tools\npm-global;%DRIVE%\tools\7-Zip;%PATH% && doskey composer=%DRIVE%\tools\php\php.exe %DRIVE%\tools\php\composer.phar $* && cd /d %DRIVE%\projects && title Havun Dev"
goto :project_menu

:quit
echo.
echo [CLEANUP] Credentials opruimen...

REM Git credentials verwijderen van USB
if exist "%DRIVE%\tools\git-credentials" (
    del /q "%DRIVE%\tools\git-credentials" 2>nul
    echo   [OK] Git credentials verwijderd
)

REM .env, context.md en .vscode/settings.json verwijderen uit projecten
for /d %%D in ("%DRIVE%\projects\*") do (
    if exist "%%D\.env" del /q "%%D\.env" 2>nul
    if exist "%%D\laravel\.env" del /q "%%D\laravel\.env" 2>nul
    if exist "%%D\.claude\context.md" del /q "%%D\.claude\context.md" 2>nul
    if exist "%%D\.vscode\settings.json" del /q "%%D\.vscode\settings.json" 2>nul
    if exist "%%D\laravel\.vscode\settings.json" del /q "%%D\laravel\.vscode\settings.json" 2>nul
)

REM Vault unlock marker verwijderen
if exist "%DRIVE%\.vault-unlocked" del /q "%DRIVE%\.vault-unlocked" 2>nul

REM Git global config resetten (niet op vreemde laptop achterlaten)
git config --global --unset user.name 2>nul
git config --global --unset user.email 2>nul
git config --global --unset credential.helper 2>nul

REM VS Code temp data opruimen
if exist "%TEMP%\vscode-havun" rmdir /s /q "%TEMP%\vscode-havun" 2>nul

echo   [OK] Alles opgeruimd!
echo.
echo Tot ziens!
exit /b 0
