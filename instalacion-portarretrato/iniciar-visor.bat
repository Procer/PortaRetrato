@echo off
REM ---------------------------------------------------------------------------
REM Arranque del Visor en la PC del portarretrato.
REM Poner un acceso directo a este archivo en shell:startup (Win+R → shell:startup).
REM Espera a que Laragon levante Apache y abre Chrome en modo kiosco contra el
REM servidor LOCAL: las fotos y videos se leen del disco, no de internet.
REM ---------------------------------------------------------------------------

set "VISOR_URL=http://localhost/PortaRetrato/"
set "CHROME=C:\Program Files\Google\Chrome\Application\chrome.exe"
set "PERFIL=C:\PortaRetratoChrome"

REM Laragon tarda unos segundos en arrancar Apache y MySQL al iniciar Windows.
:esperar
curl -s -o nul "%VISOR_URL%" || (timeout /t 3 /nobreak >nul & goto esperar)

start "" "%CHROME%" --kiosk --user-data-dir="%PERFIL%" ^
  --noerrdialogs --disable-session-crashed-bubble --disable-infobars ^
  --disable-features=Translate --autoplay-policy=no-user-gesture-required ^
  "%VISOR_URL%"
