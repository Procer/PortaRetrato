# Arranque completo del portarretrato (sin Laragon): servidor local + sincronizacion + Chrome kiosco.
# Se ejecuta oculto desde el inicio de Windows (acceso directo creado por instalar-pc.ps1).
$root  = Split-Path -Parent $PSScriptRoot
$php   = Join-Path $root 'php\php.exe'
$sync  = Join-Path $root 'agente\sync.php'
$port  = 8080
$url   = "http://127.0.0.1:$port/"
$perfil = 'C:\PortaRetratoChrome'
$log   = Join-Path $root 'mirror\arranque.log'
New-Item -ItemType Directory -Force (Join-Path $root 'mirror') | Out-Null
function Log($m) { Add-Content $log ("{0} {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $m) }
if ((Test-Path $log) -and (Get-Item $log).Length -gt 200000) { Remove-Item $log -Force }

# Un solo supervisor a la vez
$mutex = New-Object Threading.Mutex($false, 'Global\PortaRetratoSupervisor')
if (-not $mutex.WaitOne(0)) { exit }

function Start-Server {
    Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$port", '-t', "`"$root`"") `
        -WorkingDirectory $root -WindowStyle Hidden -PassThru
}
function Test-Server { try { (Invoke-WebRequest $url -UseBasicParsing -TimeoutSec 3).StatusCode -eq 200 } catch { $false } }

function Find-Browser {
    $c = @(
        "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
        "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
        "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe",
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
    )
    $c | Where-Object { Test-Path $_ } | Select-Object -First 1
}
function Test-BrowserOpen {
    [bool](Get-CimInstance Win32_Process -Filter "Name='chrome.exe' OR Name='msedge.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like "*$perfil*" })
}
function Start-Browser {
    $exe = Find-Browser
    if (-not $exe) { Log 'No hay Chrome ni Edge instalado'; return }
    $kioskArgs = @('--kiosk', "--user-data-dir=$perfil", '--noerrdialogs', '--disable-session-crashed-bubble',
              '--disable-infobars', '--disable-features=Translate', '--autoplay-policy=no-user-gesture-required', $url)
    if ($exe -like '*msedge.exe') { $kioskArgs += '--edge-kiosk-type=fullscreen' }
    Start-Process -FilePath $exe -ArgumentList $kioskArgs
    Log "Navegador abierto: $exe"
}

Log 'Supervisor iniciado'
$server = Start-Server
$lastSync = [DateTime]::MinValue
while ($true) {
    # 1) Servidor local siempre vivo
    if ($server.HasExited) { Log 'Servidor caido, reiniciando'; $server = Start-Server; Start-Sleep 2 }

    # 2) Sincronizar cada 60 s (solo si el servidor ya responde, para que el Visor tenga datos)
    if (((Get-Date) - $lastSync).TotalSeconds -ge 60) {
        $lastSync = Get-Date
        try { & $php $sync | Out-Null } catch { Log "sync: $_" }
    }

    # 3) Navegador siempre abierto
    if ((Test-Server) -and -not (Test-BrowserOpen)) { Start-Browser }

    Start-Sleep -Seconds 15
}
