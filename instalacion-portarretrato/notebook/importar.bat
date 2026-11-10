@echo off
rem Corre el importador con el PHP más nuevo de Laragon (con las extensiones que necesita).
setlocal enabledelayedexpansion
set PHPEXE=
for /d %%D in (C:\laragon\bin\php\php-*) do if exist "%%D\php.exe" set PHPEXE=%%D\php.exe
if "%PHPEXE%"=="" exit /b 1
"%PHPEXE%" -d extension=gd -d extension=exif -d extension=pdo_mysql -d extension=mbstring "%~dp0..\..\agente\importar.php" 1>nul 2>nul
