<?php
// Página para elegir el WiFi de la casa. Se abre sola cuando el celular se conecta al WiFi propio
// del portarretrato ("Portaretrato-XXXX"). Funciona sin sesión SOLO en ese modo; si el equipo ya
// tiene red, manda a Gestión (Sistema → WiFi).
require_once '../backend/bootstrap.php';
$modo = redEstado()['modo'];
if ($modo !== 'ap' && $modo !== 'conectando') { header('Location: ../gestion/#/sistema'); exit; }
?><!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5b5bf0">
    <title>Conectar al WiFi</title>
    <style>
        :root { --bg:#f4f4f7; --card:#fff; --text:#131318; --muted:#6c6c78; --accent:#5b5bf0; --line:#e3e3ea; --danger:#e5484d; --ok:#1f9d63; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0a0a0e; --card:#15151b; --text:#f3f3f6; --muted:#9a9aa8; --accent:#7c7cff; --line:#2a2a34; --danger:#ff6369; --ok:#4cd694; } }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 17px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: calc(env(safe-area-inset-top) + 24px) 18px 40px; }
        main { max-width: 440px; margin: 0 auto; }
        h1 { font-size: 28px; letter-spacing: -.02em; margin: 0 0 6px; }
        p.sub { color: var(--muted); margin: 0 0 22px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 22px; padding: 8px; margin-bottom: 16px; }
        .net { display: flex; align-items: center; gap: 12px; width: 100%; padding: 15px 14px; border: 0; background: none; color: inherit; font: inherit; text-align: left; border-radius: 16px; cursor: pointer; }
        .net:active, .net.sel { background: color-mix(in srgb, var(--accent) 14%, transparent); }
        .net b { flex: 1; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .net small { color: var(--muted); }
        .bars { display: inline-flex; gap: 2px; align-items: flex-end; height: 16px; }
        .bars i { width: 4px; background: var(--line); border-radius: 1px; }
        .bars i.on { background: var(--accent); }
        label { display: block; font-weight: 600; margin: 16px 4px 6px; }
        input { width: 100%; min-height: 54px; padding: 0 16px; border-radius: 16px; border: 1.5px solid var(--line); background: var(--card); color: inherit; font: inherit; outline: none; }
        input:focus { border-color: var(--accent); }
        .pw { position: relative; }
        .pw button { position: absolute; right: 6px; top: 6px; height: 42px; padding: 0 14px; border: 0; border-radius: 12px; background: none; color: var(--accent); font: inherit; font-weight: 600; }
        .go { width: 100%; min-height: 56px; margin-top: 20px; border: 0; border-radius: 18px; background: var(--accent); color: #fff; font: inherit; font-weight: 700; font-size: 18px; }
        .go:disabled { opacity: .5; }
        .link { background: none; border: 0; color: var(--accent); font: inherit; padding: 10px 4px; }
        .err { color: var(--danger); font-weight: 600; margin: 12px 4px 0; }
        .done { text-align: center; padding: 30px 10px; }
        .done .big { font-size: 60px; }
        .done ol { text-align: left; padding-left: 22px; }
        .spin { display: inline-block; width: 18px; height: 18px; border: 3px solid var(--line); border-top-color: var(--accent); border-radius: 50%; animation: r 1s linear infinite; vertical-align: -3px; margin-right: 8px; }
        @keyframes r { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<main id="app">
    <h1>Conectemos tu portarretrato</h1>
    <p class="sub">Elegí el WiFi de tu casa para que el portarretrato tenga internet (clima y hora).</p>
    <div id="body"><p class="sub"><span class="spin"></span>Buscando redes…</p></div>
</main>
<script>
const $ = s => document.querySelector(s);
const body = $('#body');
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let nets = [], sel = null;

async function api(action, opt = {}) {
    const r = await fetch('../backend/api.php?action=' + action, opt.body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(opt.body) } : {});
    return r.json();
}
const bars = s => `<span class="bars">${[25, 50, 75, 100].map((t, i) => `<i class="${s >= t - 12 ? 'on' : ''}" style="height:${6 + i * 3}px"></i>`).join('')}</span>`;

function render(err) {
    body.innerHTML = `
        <div class="card">${nets.length ? nets.map((n, i) => `<button class="net ${sel === i ? 'sel' : ''}" data-i="${i}">${bars(n.senal)}<b>${esc(n.ssid)}</b><small>${n.clave ? '🔒' : 'abierta'}</small></button>`).join('') : '<p class="sub" style="padding:14px">No se ven redes. Acercate al router y tocá “Buscar de nuevo”.</p>'}</div>
        <button class="link" id="rescan">↻ Buscar de nuevo</button> <button class="link" id="manual">Mi red no aparece</button>
        <div id="form"></div>${err ? `<div class="err">${esc(err)}</div>` : ''}`;
    body.querySelectorAll('.net').forEach(b => b.onclick = () => { sel = +b.dataset.i; render(); form(nets[sel].ssid, nets[sel].clave); });
    $('#rescan').onclick = scan;
    $('#manual').onclick = () => { sel = null; render(); form('', true, true); };
    if (sel !== null && nets[sel]) form(nets[sel].ssid, nets[sel].clave);
}

function form(ssid, needPass, manual) {
    $('#form').innerHTML = `
        ${manual ? `<label for="ssid">Nombre del WiFi</label><input id="ssid" autocapitalize="none" autocorrect="off" spellcheck="false">` : `<label>Red elegida: <b>${esc(ssid)}</b></label>`}
        ${needPass ? `<label for="pw">Clave del WiFi</label><div class="pw"><input id="pw" type="password" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="off"><button type="button" id="show">Ver</button></div>` : ''}
        <button class="go" id="go">Conectar</button>`;
    const pw = $('#pw');
    if ($('#show')) $('#show').onclick = () => { pw.type = pw.type === 'password' ? 'text' : 'password'; $('#show').textContent = pw.type === 'password' ? 'Ver' : 'Ocultar'; };
    if (pw && !manual) pw.focus();
    $('#go').onclick = async () => {
        const name = manual ? $('#ssid').value.trim() : ssid, clave = pw ? pw.value : '';
        if (!name) return;
        if (clave && clave.length < 8) { $('#go').insertAdjacentHTML('afterend', '<div class="err">La clave del WiFi tiene al menos 8 caracteres.</div>'); return; }
        $('#go').disabled = true; $('#go').textContent = 'Enviando…';
        try {
            const j = await api('wifi_connect', { body: { ssid: name, clave } });
            if (j.error) throw new Error(j.error);
            listo(name);
        } catch (e) { $('#go').disabled = false; $('#go').textContent = 'Conectar'; render(e.message); }
    };
}

function listo(name) {
    body.innerHTML = `<div class="done"><div class="big">📶</div><h1>Conectando a “${esc(name)}”…</h1>
        <p class="sub">Tu celular va a perder la conexión con el portarretrato: es normal.</p>
        <ol><li>Mirá la <b>pantalla del portarretrato</b>: en unos segundos dice si se pudo conectar.</li>
        <li>Si se conectó, volvé a conectar <b>tu celular a tu WiFi de siempre</b>.</li>
        <li>Seguí las instrucciones de la pantalla para terminar la configuración.</li></ol>
        <p class="sub">Si la clave estaba mal, la pantalla te lo dice y volvés a probar desde acá.</p></div>`;
}

async function scan() {
    body.innerHTML = '<p class="sub"><span class="spin"></span>Buscando redes…</p>';
    try { const j = await api('wifi_scan'); nets = Array.isArray(j) ? j : []; sel = null; render(Array.isArray(j) ? '' : (j.error || '')); }
    catch (e) { render('No se pudo buscar redes.'); }
}
scan();
</script>
</body>
</html>
