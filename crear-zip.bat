@echo off
REM ---------------------------------------------------------------------------
REM Genera PortaRetrato.zip con TODO el codigo necesario (sin fotos, sin
REM backend\config.php ni mirror.config.php). Sirve para dos cosas:
REM   1) Subirlo al hosting (cPanel > Administrador de archivos > Cargar > Extraer)
REM      sobre public_html\PortaRetrato. NO pisa config.php ni uploads\.
REM   2) Copiarlo a la PC del portarretrato y extraerlo en C:\laragon\www\PortaRetrato
REM Toma lo que esta COMMITEADO en git (HEAD).
REM ---------------------------------------------------------------------------
cd /d "%~dp0"
set "ZIP=%~dp0PortaRetrato.zip"
if exist "%ZIP%" del "%ZIP%"

git archive --format=zip --prefix=PortaRetrato/ -o "%ZIP%" HEAD -- . ":(exclude)uploads" ":(exclude)clean_temp" ":(exclude)assets.zip" ":(exclude)*.sql" ":(exclude)Gemini.md" ":(exclude)COMO-PROBAR-MODO-SYNC.md"
if errorlevel 1 (
    echo.
    echo ERROR: no se pudo crear el zip. Hace falta tener Git instalado.
    pause
    exit /b 1
)

REM El hosting necesita PARA-WNPOWER.sql / setup.sql solo la primera vez; si lo necesitas, quitalos de la lista de arriba.
echo.
echo Listo: %ZIP%
for %%A in ("%ZIP%") do echo Tamano: %%~zA bytes
echo.
echo HOSTING : subir el zip a public_html (cPanel), extraer. Queda en public_html\PortaRetrato\
echo PC      : extraer en C:\laragon\www\  y luego correr instalacion-portarretrato\instalar-pc.bat
pause
