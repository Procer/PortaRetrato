# Instala el portarretrato en esta PC SIN Laragon: PHP portable dentro de la carpeta del proyecto.
# Lo lanza instalar-pc.bat. Se puede volver a correr sin problema.
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$root = Split-Path -Parent $PSScriptRoot
Write-Host "`n=== Instalador del portarretrato ===`n" -ForegroundColor Cyan
Write-Host "Carpeta del proyecto: $root"

# 1) PHP portable
$phpDir = Join-Path $root 'php'
$php = Join-Path $phpDir 'php.exe'
if (-not (Test-Path $php)) {
    Write-Host 'Descargando PHP portable (34 MB)...'
    $zip = Join-Path $env:TEMP 'php-portable.zip'
    Invoke-WebRequest 'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip' -OutFile $zip -UseBasicParsing
    Expand-Archive $zip -DestinationPath $phpDir -Force
    Remove-Item $zip
}
$cacert = Join-Path $phpDir 'cacert.pem'
if (-not (Test-Path $cacert)) {
    Invoke-WebRequest 'https://curl.se/ca/cacert.pem' -OutFile $cacert -UseBasicParsing
}
$ini = @"
extension_dir="$phpDir\ext"
extension=curl
extension=openssl
extension=mbstring
curl.cainfo="$cacert"
openssl.cafile="$cacert"
date.timezone=America/Argentina/Buenos_Aires
display_errors=0
memory_limit=256M
"@
[IO.File]::WriteAllText((Join-Path $phpDir 'php.ini'), $ini, (New-Object Text.UTF8Encoding($false)))
$mods = & $php -m
if (-not ($mods -contains 'curl')) {
    throw 'PHP no pudo cargar curl. Suele faltar "Visual C++ Redistributable 2015-2022 x64" (https://aka.ms/vs/17/release/vc_redist.x64.exe). Instalalo y volve a correr este instalador.'
}
Write-Host "PHP listo: $(& $php -r 'echo PHP_VERSION;')" -ForegroundColor Green

# 2) Datos del hosting
$cfgFile = Join-Path $root 'backend\mirror.config.php'
$keep = $false
if (Test-Path $cfgFile) {
    $r = Read-Host 'Ya existe mirror.config.php. ¿Conservarlo? (S/n)'
    $keep = ($r -ne 'n' -and $r -ne 'N')
}
if (-not $keep) {
    $remote = (Read-Host 'URL del proyecto en el hosting (ej: https://fotos.midominio.com.ar/PortaRetrato)').Trim().TrimEnd('/')
    $token  = (Read-Host 'DEVICE_TOKEN (el mismo que pusiste en backend/config.php del hosting)').Trim()
    if ($remote -notmatch '^https?://' -or $token.Length -lt 16) { throw 'URL o token invalidos (el token debe tener al menos 16 caracteres).' }
    $content = "<?php`r`nreturn [`r`n    'remote' => '" + $remote.Replace("'", "\'") + "',`r`n    'token'  => '" + $token.Replace("'", "\'") + "',`r`n];`r`n"
    [IO.File]::WriteAllText($cfgFile, $content, (New-Object Text.UTF8Encoding($false)))
    Write-Host 'mirror.config.php creado.' -ForegroundColor Green
}

# 3) Primera sincronizacion (prueba real)
Write-Host "`nSincronizando por primera vez (puede tardar si hay videos)..."
New-Item -ItemType Directory -Force (Join-Path $root 'uploads') | Out-Null
& $php (Join-Path $root 'agente\sync.php')
$code = $LASTEXITCODE
$log = Join-Path $root 'mirror\sync.log'
if (Test-Path $log) { Write-Host "`nUltimas lineas del log:"; Get-Content $log -Tail 5 }
if ($code -ne 0) { throw "La sincronizacion fallo (codigo $code). Revisa URL/token y mandame el log: $log" }
Write-Host 'Sincronizacion OK.' -ForegroundColor Green

# 4) Arranque automatico al iniciar sesion (oculto)
Get-ScheduledTask -TaskName 'PortaRetrato Sync' -ErrorAction SilentlyContinue | Unregister-ScheduledTask -Confirm:$false -ErrorAction SilentlyContinue   # tarea de la version con Laragon
$startup = [Environment]::GetFolderPath('Startup')
$old = Join-Path $startup 'Portaretrato.lnk'; if (Test-Path $old) { Remove-Item $old -Force }
$lnk = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $startup 'Portaretrato.lnk'))
$lnk.TargetPath = 'powershell.exe'
$lnk.Arguments = '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + (Join-Path $PSScriptRoot 'portaretrato.ps1') + '"'
$lnk.WorkingDirectory = $root
$lnk.WindowStyle = 7
$lnk.Save()
Write-Host 'Arranque automatico configurado (carpeta Inicio de Windows).' -ForegroundColor Green

# 5) Que la PC no se duerma ni apague la pantalla
powercfg /change standby-timeout-ac 0 | Out-Null
powercfg /change monitor-timeout-ac 0 | Out-Null
powercfg /change hibernate-timeout-ac 0 | Out-Null
Write-Host 'Suspension y apagado de pantalla desactivados (con corriente).' -ForegroundColor Green

# 6) Arrancar ahora
$r = Read-Host "`n¿Iniciar el portarretrato ahora? (S/n)"
if ($r -ne 'n' -and $r -ne 'N') {
    Start-Process powershell.exe -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-WindowStyle','Hidden','-File',('"' + (Join-Path $PSScriptRoot 'portaretrato.ps1') + '"')
    Write-Host 'Iniciando... en unos segundos se abre el Visor en pantalla completa (salir: Alt+F4).' -ForegroundColor Green
}

Write-Host @"

=== Falta hacer a mano (Windows no deja automatizarlo) ===
 Inicio de sesion automatico: Win+R > netplwiz > desmarcar "Los usuarios deben escribir su nombre y contrasena".
 Despues reinicia la PC y comprueba que el portarretrato arranca solo.
"@ -ForegroundColor Cyan
