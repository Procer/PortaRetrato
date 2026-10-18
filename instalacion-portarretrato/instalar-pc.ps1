# Instala el espejo en la PC del portarretrato (Windows 10/11, Laragon).
# Lo lanza instalar-pc.bat como administrador.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Write-Host "`n=== Instalador del portarretrato ===`n" -ForegroundColor Cyan
Write-Host "Carpeta del proyecto: $root"
if ($root -ne 'C:\laragon\www\PortaRetrato') {
    Write-Host "AVISO: el Visor espera estar en C:\laragon\www\PortaRetrato (iniciar-visor.bat abre http://localhost/PortaRetrato/)." -ForegroundColor Yellow
}

# 1) PHP de Laragon
$php = Get-ChildItem 'C:\laragon\bin\php\*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
if (-not $php) { throw 'No encuentro PHP de Laragon en C:\laragon\bin\php. Instalá Laragon primero (laragon.org).' }
$php = $php.FullName
Write-Host "PHP: $php"
if (-not ((& $php -m) -contains 'curl')) { throw 'Este PHP no tiene la extension curl. Activala en php.ini (extension=curl) y reintentá.' }

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
if ($code -ne 0) {
    throw "La sincronizacion fallo (codigo $code). Revisá URL/token y mandame el log: $log"
}
Write-Host 'Sincronizacion OK.' -ForegroundColor Green

# 4) Tarea programada cada 1 minuto
$name = 'PortaRetrato Sync'
$action   = New-ScheduledTaskAction -Execute $php -Argument ('"' + (Join-Path $root 'agente\sync.php') + '"') -WorkingDirectory $root
$trigger  = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 2) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -User 'SYSTEM' -RunLevel Highest -Force | Out-Null
Write-Host "Tarea programada '$name' creada (cada 1 minuto)." -ForegroundColor Green

# 5) Visor en modo kiosco al iniciar sesion
$startup = [Environment]::GetFolderPath('Startup')
$lnk = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $startup 'Portaretrato.lnk'))
$lnk.TargetPath = Join-Path $PSScriptRoot 'iniciar-visor.bat'
$lnk.WorkingDirectory = $PSScriptRoot
$lnk.WindowStyle = 7
$lnk.Save()
Write-Host "Acceso directo creado en el inicio de Windows." -ForegroundColor Green

# 6) Que la PC no se duerma ni apague la pantalla
powercfg /change standby-timeout-ac 0 | Out-Null
powercfg /change monitor-timeout-ac 0 | Out-Null
powercfg /change hibernate-timeout-ac 0 | Out-Null
Write-Host 'Suspension y apagado de pantalla desactivados (con corriente).' -ForegroundColor Green

# 7) Verificar que el Visor local responde
try {
    $r = Invoke-WebRequest 'http://localhost/PortaRetrato/' -UseBasicParsing -TimeoutSec 5
    Write-Host 'Visor local OK: http://localhost/PortaRetrato/' -ForegroundColor Green
} catch {
    Write-Host 'El Visor local no responde: abrí Laragon y apretá "Iniciar todo".' -ForegroundColor Yellow
}

Write-Host @"

=== Falta hacer a mano (no se puede automatizar) ===
 1. Laragon: Menu > Preferencias > marcar "Ejecutar Laragon al iniciar Windows" y "Iniciar todo automaticamente".
 2. Inicio de sesion automatico: Win+R > netplwiz > desmarcar "Los usuarios deben escribir su nombre y contrasena".
 3. Reiniciar la PC y comprobar que el portarretrato arranca solo.
"@ -ForegroundColor Cyan
