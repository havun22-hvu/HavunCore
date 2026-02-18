@echo off
REM ========================================================
REM  SNELLE SHORTCUT: JudoToernooi direct openen
REM  Voor alle projecten: gebruik START.bat
REM ========================================================

set USB=%~d0
set PATH=%USB%\tools\php;%USB%\tools\git\cmd;%USB%\tools\git\usr\bin;%USB%\tools\nodejs;%USB%\tools\npm-global;%USB%\tools\7-Zip;%PATH%

REM Git credentials automatisch instellen
git config --global user.name "havun22-hvu"
git config --global user.email "henk@havun.nl"
git config --global credential.helper "store --file=%USB%\tools\git-credentials"

REM VS Code settings klaarzetten (CMD als default terminal)
if not exist "%TEMP%\vscode-havun\User" mkdir "%TEMP%\vscode-havun\User" 2>nul
copy /y "%USB%\tools\vscode-settings\User\settings.json" "%TEMP%\vscode-havun\User\settings.json" >nul 2>nul

echo.
echo [INFO] JudoToernooi: code ophalen + VS Code openen...
cd /d "%USB%\projects\JudoToernooi"
"%USB%\tools\git\cmd\git.exe" pull origin main 2>nul
start "" "%USB%\tools\VSCode\Code.exe" --user-data-dir "%TEMP%\vscode-havun" "%USB%\projects\JudoToernooi\laravel"
echo [OK] VS Code geopend!
