@echo off
REM Instalador de la PC del portarretrato. Hace doble clic: pide permiso de administrador solo.
net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -Verb RunAs -FilePath '%~f0'"
    exit /b
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-pc.ps1"
echo.
pause
