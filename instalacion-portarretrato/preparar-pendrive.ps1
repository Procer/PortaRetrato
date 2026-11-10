# Prepara (o actualiza) el pendrive Ventoy para instalar portarretratos.
#   powershell -ExecutionPolicy Bypass -File preparar-pendrive.ps1 [-Panel https://panel.midominio.com] [-Enroll <codigo>] [-Canal stable|main] [-Clave <clave>] [-Unidad D]
#
# - Necesita un pendrive con Ventoy y la ISO de Debian 13 netinst copiada en su raiz.
# - Escribe ventoy\script\preseed.cfg (instalacion automatica) y ventoy\ventoy.json.
# - Sin -Panel el equipo se instala igual, pero no queda registrado en ningun panel.
# - El preseed contiene claves: queda SOLO en el pendrive, nunca se guarda en el repositorio.
# Se puede volver a correr cuando quieras (por ejemplo al tener el panel listo).
param(
    [string]$Panel = '',
    [string]$Enroll = '',
    [ValidateSet('stable', 'main')][string]$Canal = 'stable',
    [string]$Clave = '',
    [string]$Unidad = ''
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$linux = Join-Path $PSScriptRoot 'linux'

# 1) Pendrive
if (-not $Unidad) {
    $v = Get-Volume | Where-Object { $_.FileSystemLabel -eq 'Ventoy' -and $_.DriveLetter } | Select-Object -First 1
    if (-not $v) { throw 'No encuentro un pendrive con Ventoy. Conectalo o indica la letra con -Unidad D' }
    $Unidad = [string]$v.DriveLetter
}
$drv = "$($Unidad.TrimEnd(':')):\"
$iso = Get-ChildItem $drv -Filter 'debian-*.iso' -ErrorAction SilentlyContinue | Select-Object -First 1
if (-not $iso) { throw "No hay una ISO debian-*.iso en $drv . Copiala (https://www.debian.org/distrib/netinst)." }
Write-Host "Pendrive: $drv   ISO: $($iso.Name)"

# 2) Clave del usuario "frame" del equipo: se conserva la del preseed anterior, o se pide / genera
$pre = Join-Path $drv 'ventoy\script\preseed.cfg'
if (-not $Clave -and (Test-Path $pre)) {
    $m = Select-String -Path $pre -Pattern '^d-i passwd/user-password password (\S+)' | Select-Object -First 1
    if ($m -and $m.Matches[0].Groups[1].Value -ne '__PASSWORD__') { $Clave = $m.Matches[0].Groups[1].Value; Write-Host 'Se mantiene la clave del usuario frame que ya tenia el pendrive.' }
}
$generada = $false
if (-not $Clave) {
    $Clave = -join ((48..57) + (97..122) | Get-Random -Count 10 | ForEach-Object { [char]$_ }); $generada = $true
}
if ($Clave -match '\s') { throw 'La clave no puede tener espacios.' }

# 3) Piezas embebidas (en base64 para que ningun caracter rompa el shell del instalador)
function B64([string]$text) { [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($text.Replace("`r`n", "`n"))) }
$envTxt = "PANEL_URL='$Panel'`nPANEL_ENROLL='$Enroll'`nPANEL_CHANNEL='$Canal'`n"
$firstboot = Get-Content (Join-Path $linux 'firstboot.sh') -Raw
$unit = @"
[Unit]
Description=Portaretrato - instalacion del primer arranque
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/pr-firstboot
TimeoutStartSec=infinity
StandardOutput=journal+console
StandardError=journal+console

[Install]
WantedBy=multi-user.target
"@
$tpl = Get-Content (Join-Path $linux 'preseed.template.cfg') -Raw
$cfg = $tpl.Replace('__PASSWORD__', $Clave).Replace('__ENV_B64__', (B64 $envTxt)).Replace('__FIRSTBOOT_B64__', (B64 $firstboot)).Replace('__UNIT_B64__', (B64 $unit))
if ($cfg -match '__[A-Z0-9_]+__') { throw "Quedaron marcadores sin reemplazar: $($Matches[0])" }
$cfg = $cfg.Replace("`r`n", "`n")

# 4) Escribir en el pendrive
New-Item -ItemType Directory -Force (Join-Path $drv 'ventoy\script') | Out-Null
[IO.File]::WriteAllText($pre, $cfg, (New-Object Text.UTF8Encoding($false)))
$json = '{"auto_install":[{"image":"/' + $iso.Name + '","template":"/ventoy/script/preseed.cfg"}]}'
[IO.File]::WriteAllText((Join-Path $drv 'ventoy\ventoy.json'), $json, (New-Object Text.UTF8Encoding($false)))
$leeme = @"
INSTALAR UN PORTARRETRATO
1. Conectar la notebook por CABLE de red al router (necesita internet).
2. Arrancar desde este pendrive (F12 / F2 / Esc al encender) y elegir Debian.
3. CUIDADO: se borra TODO el disco interno de la notebook, sin preguntar.
4. Esperar. Se instala Debian, se reinicia solo y en el primer arranque descarga e instala el
   portarretrato (10-20 minutos; se ve el avance en pantalla). Al terminar se reinicia solo.
5. Sacar el pendrive cuando reinicie la primera vez.
6. Probar. Para entregarlo: sudo bash /opt/portaretrato/instalacion-portarretrato/linux/preparar-entrega.sh
"@
[IO.File]::WriteAllText((Join-Path $drv 'LEEME-PORTARRETRATO.txt'), $leeme, (New-Object Text.UTF8Encoding($false)))

Write-Host "`nPendrive listo." -ForegroundColor Green
Write-Host ("Panel: " + $(if ($Panel) { "$Panel (canal $Canal)" } else { 'sin panel (los equipos no quedan registrados)' }))
if ($generada) { Write-Host "Clave del usuario 'frame' (SSH del equipo): $Clave   <- anotala" -ForegroundColor Yellow }
