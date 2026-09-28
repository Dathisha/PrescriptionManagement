@echo off
title Prescription Management — Scanner Agent
color 0A

echo.
echo  ============================================================
echo    Prescription Management System — Local Scanner Agent
echo  ============================================================
echo.

:: ── Check Node.js is installed ───────────────────────────────────
node --version >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo  [ERROR] Node.js is not installed.
    echo.
    echo  Please download and install Node.js from:
    echo    https://nodejs.org  (LTS version recommended)
    echo.
    echo  After installing, close this window and run start-agent.bat again.
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%v in ('node --version') do set NODE_VER=%%v
echo  Node.js detected: %NODE_VER%

:: ── Install npm packages if node_modules is missing ──────────────
if not exist "%~dp0node_modules\" (
    echo.
    echo  Installing dependencies (one-time setup)...
    echo.
    cd /d "%~dp0"
    call npm install --silent
    if %errorlevel% neq 0 (
        color 0C
        echo.
        echo  [ERROR] npm install failed. Please check your internet connection.
        pause
        exit /b 1
    )
    echo  Dependencies installed successfully.
)

:: ── Start the scanner agent ───────────────────────────────────────
echo.
echo  Starting Scanner Agent on http://127.0.0.1:7854 ...
echo.
echo  ┌─────────────────────────────────────────────────────────────┐
echo  │  Keep this window open while scanning prescriptions.        │
echo  │  Press Ctrl+C to stop the agent.                            │
echo  └─────────────────────────────────────────────────────────────┘
echo.

cd /d "%~dp0"
node server.js

if %errorlevel% neq 0 (
    color 0C
    echo.
    echo  [ERROR] Scanner agent stopped unexpectedly.
    echo  Check the error above and restart this window.
    pause
)
