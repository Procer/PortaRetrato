# Instala en la notebook: Syncthing (carpeta de fotos) + tareas que arrancan solas al iniciar sesión.
# Ejecutar UNA vez:  powershell -ExecutionPolicy Bypass -File instalar-notebook.ps1
$ErrorActionPreference = 'Stop'
$aqui = Split-Path -Parent $MyInvocation.MyCommand.Path
$proyecto = (Resolve-Path "$aqui\..\..").Path
$fotos = Join-Path $proyecto 'sync'

# 1) Carpeta de fotos con marcador (Syncthing lo reconoce como carpeta ya creada)
New-Item -ItemType Directory -Force "$fotos\.stfolder" | Out-Null

# 2) Syncthing
$st = Get-Command syncthing -ErrorAction SilentlyContinue
if (-not $st) {
    winget install --id Syncthing.Syncthing -e --accept-source-agreements --accept-package-agreements
    $env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')
    $st = Get-Command syncthing -ErrorAction SilentlyContinue
}
if (-not $st) { throw 'No se encontró syncthing.exe tras instalarlo. Reabrí PowerShell y repetí.' }

# 3) Tareas programadas (al iniciar sesión; para que arranque solo tras un corte, activá el inicio de sesión automático)
$yo = "$env:USERDOMAIN\$env:USERNAME"
$cfg = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) -Hidden

$acc = New-ScheduledTaskAction -Execute $st.Source -Argument 'serve --no-browser --no-console'
Register-ScheduledTask -TaskName 'PortaRetrato - Syncthing' -Force -Settings $cfg `
    -Action $acc -Trigger (New-ScheduledTaskTrigger -AtLogOn -User $yo) -User $yo | Out-Null

$acc = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument "`"$aqui\oculto.vbs`" `"$aqui\importar.bat`""
$trg = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
Register-ScheduledTask -TaskName 'PortaRetrato - Importador' -Force -Settings $cfg -Action $acc -Trigger $trg -User $yo | Out-Null

Start-ScheduledTask -TaskName 'PortaRetrato - Syncthing'
Write-Host "Listo. Carpeta de fotos: $fotos"
Write-Host "Abrí http://127.0.0.1:8384 para configurar Syncthing (ver GUIA-SYNC.md)."
