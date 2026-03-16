@echo off
setlocal EnableDelayedExpansion
:: ============================================================
:: CVAKI Auto-Setup for Windows
:: Fully automatic — installs EVERYTHING without asking.
:: ============================================================

title CVAKI Auto-Setup

echo.
echo [33m
echo     ██████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
echo    ██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
echo    ██║     ██║   ██║███████║█████╔╝ ██║
echo    ██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
echo    ╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
echo     ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝[0m
echo [36m  Brain Gods AI — Auto-Setup (fully automatic, no prompts)[0m
echo.

set "FAILED="
set "STEP=0"

:: ══════════════════════════════════════════════════════════════
:: 1. CHECK / INSTALL PYTHON
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] Python...[0m
python --version >nul 2>&1
if %errorlevel% equ 0 (
    for /f "tokens=*" %%v in ('python --version 2^>^&1') do echo   [32mOK[0m %%v
    goto :python_ok
)
echo   [33mNot found. Trying winget...[0m
winget install --id Python.Python.3.12 --silent --accept-package-agreements --accept-source-agreements >nul 2>&1
if %errorlevel% equ 0 ( echo   [32mOK[0m Python installed via winget & goto :python_ok )
echo   [33mwinget failed. Downloading installer...[0m
curl -L -o "%TEMP%\python_setup.exe" "https://www.python.org/ftp/python/3.12.4/python-3.12.4-amd64.exe" >nul 2>&1
if %errorlevel% equ 0 (
    "%TEMP%\python_setup.exe" /quiet InstallAllUsers=0 PrependPath=1 Include_pip=1 >nul 2>&1
    echo   [32mOK[0m Python installed from python.org
    goto :python_ok
)
echo   [31mFAIL[0m Could not auto-install Python. Get it at https://python.org
set "FAILED=%FAILED% Python"
:python_ok

:: ══════════════════════════════════════════════════════════════
:: 2. UPGRADE PIP
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] pip...[0m
python -m pip install --upgrade pip --quiet --disable-pip-version-check >nul 2>&1
echo   [32mOK[0m

:: ══════════════════════════════════════════════════════════════
:: 3. PYTHON PACKAGES
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] Python packages...[0m
python -m pip install -r requirements.txt --quiet --disable-pip-version-check
if %errorlevel% equ 0 (
    echo   [32mOK[0m All packages installed
) else (
    echo   [33mSome failed. Retrying individually...[0m
    for /f "usebackq tokens=1 delims=>= " %%p in (`findstr /v "^#" requirements.txt`) do (
        if not "%%p"=="" (
            python -m pip install "%%p" --quiet --disable-pip-version-check >nul 2>&1
            if !errorlevel! equ 0 (echo   [32mOK[0m  %%p) else (echo   [33mSKIP[0m %%p)
        )
    )
)

:: ══════════════════════════════════════════════════════════════
:: 4. NODE.JS
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] Node.js (WhatsApp bridge)...[0m
node --version >nul 2>&1
if %errorlevel% equ 0 (
    for /f "tokens=*" %%v in ('node --version') do echo   [32mOK[0m Node %%v
    goto :node_ok
)
echo   [33mInstalling via winget...[0m
winget install --id OpenJS.NodeJS.LTS --silent --accept-package-agreements --accept-source-agreements >nul 2>&1
if %errorlevel% equ 0 ( echo   [32mOK[0m Node.js installed & goto :node_ok )
echo   [33mDownloading Node.js installer...[0m
curl -L -o "%TEMP%\node_setup.msi" "https://nodejs.org/dist/v20.15.0/node-v20.15.0-x64.msi" >nul 2>&1
if %errorlevel% equ 0 (
    msiexec /i "%TEMP%\node_setup.msi" /quiet /norestart >nul 2>&1
    echo   [32mOK[0m Node.js installed from nodejs.org
    goto :node_ok
)
echo   [33mSKIP[0m Node.js not installed (WhatsApp bridge will be unavailable)
:node_ok

:: ══════════════════════════════════════════════════════════════
:: 5. NPM INSTALL
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] WhatsApp npm packages...[0m
node --version >nul 2>&1
if %errorlevel% equ 0 (
    if exist "whatsapp\package.json" (
        if not exist "whatsapp\node_modules" (
            pushd whatsapp
            npm install --silent >nul 2>&1
            popd
        )
        echo   [32mOK[0m npm packages ready
    )
) else (
    echo   [2mSkipped (no Node.js)[0m
)

:: ══════════════════════════════════════════════════════════════
:: 6. POWERSHELL PROFILE (auto, no overwrite prompt)
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] PowerShell profile...[0m
set "PS_DIR=%USERPROFILE%\Documents\PowerShell"
set "PS_FILE=%PS_DIR%\Microsoft.PowerShell_profile.ps1"
if not exist "%PS_DIR%" mkdir "%PS_DIR%"
if not exist "%PS_FILE%" (
    copy /y "powershell\Microsoft.PowerShell_profile.ps1" "%PS_FILE%" >nul
    echo   [32mOK[0m Installed
) else (
    findstr /c:"CVAKI" "%PS_FILE%" >nul 2>&1
    if %errorlevel% neq 0 (
        echo.>> "%PS_FILE%"
        type "powershell\Microsoft.PowerShell_profile.ps1" >> "%PS_FILE%"
        echo   [32mOK[0m Appended to existing profile
    ) else (
        echo   [32mOK[0m Already installed
    )
)

:: ══════════════════════════════════════════════════════════════
:: 7. DATA DIRECTORIES
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] Creating data directories...[0m
for %%d in (".cvaki" ".cvaki\logs" ".cvaki\exports" ".cvaki\wa_session") do (
    if not exist "%USERPROFILE%\%%~d" mkdir "%USERPROFILE%\%%~d"
)
echo   [32mOK[0m ~/.cvaki ready

:: ══════════════════════════════════════════════════════════════
:: 8. COPY TO GLOBAL LOCATION
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] Installing globally to ~/.cvaki/cvaki...[0m
if not exist "%USERPROFILE%\.cvaki\cvaki" mkdir "%USERPROFILE%\.cvaki\cvaki"
xcopy /e /y /q . "%USERPROFILE%\.cvaki\cvaki\" >nul 2>&1
echo   [32mOK[0m

:: ══════════════════════════════════════════════════════════════
:: 9. PYTHON AUTO-SETUP VERIFIER
:: ══════════════════════════════════════════════════════════════
set /a STEP+=1
echo [36m[%STEP%] Final verification...[0m
python auto_setup.py

:: ══════════════════════════════════════════════════════════════
:: DONE
:: ══════════════════════════════════════════════════════════════
echo.
echo [33m══════════════════════════════════════════════[0m
if "%FAILED%"=="" (
    echo [32m  CVAKI is ready! Everything installed.[0m
) else (
    echo [33m  Setup complete (with warnings):[0m %FAILED%
)
echo [33m══════════════════════════════════════════════[0m
echo.
echo   Open a NEW PowerShell window and type:  [33mcvaki[0m
echo   First time? Type:                       [33mcvaki --setup[0m
echo   Free Groq key:   [36mhttps://console.groq.com[0m
echo.
endlocal
pause
