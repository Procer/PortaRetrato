<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#7c5cf0">
    <title>Mensajes</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="../gestion/icons/apple-touch-icon.png">
    <link rel="icon" href="../gestion/icons/icon-192.png">
    <style>
        :root { --bg:#f5f3ff; --card:#fff; --text:#241f3a; --muted:#7b7596; --mine:#7c5cf0; --mine-t:#fff; --line:#e4e0f5; }
        @media (prefers-color-scheme: dark) { :root { --bg:#12101c; --card:#201c31; --text:#f1eefc; --muted:#9d97b8; --line:#322d48; } }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; margin: 0; }
        body { background: var(--bg); color: var(--text); font: 17px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; display: flex; flex-direction: column; }
        header { padding: calc(env(safe-area-inset-top) + 14px) 20px 12px; font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        header span { font-size: 26px; }
        #list { flex: 1; overflow-y: auto; padding: 8px 14px 16px; display: flex; flex-direction: column; gap: 10px; scroll-behavior: smooth; }
        .b { max-width: 82%; padding: 10px 14px; border-radius: 20px; background: var(--card); box-shadow: 0 1px 3px rgba(0,0,0,.08); word-wrap: break-word; overflow-wrap: anywhere; white-space: pre-wrap; align-self: flex-start; border-bottom-left-radius: 6px; }
        .b.mine { align-self: flex-end; background: var(--mine); color: var(--mine-t); border-radius: 20px; border-bottom-right-radius: 6px; }
        .b small { display: block; margin-top: 4px; font-size: 12px; opacity: .65; }
        .b .who { font-weight: 600; opacity: .8; margin-bottom: 2px; font-size: 13px; }
        .empty { margin: auto; text-align: center; color: var(--muted); padding: 30px; font-size: 18px; }
        .empty b { display: block; font-size: 44px; margin-bottom: 8px; }
        form { display: flex; gap: 10px; align-items: flex-end; padding: 10px 12px calc(env(safe-area-inset-bottom) + 12px); background: var(--card); border-top: 1px solid var(--line); }
        textarea { flex: 1; resize: none; border: 1.5px solid var(--line); border-radius: 22px; padding: 12px 16px; font: inherit; color: var(--text); background: var(--bg); max-height: 120px; outline: none; }
        textarea:focus { border-color: var(--mine); }
        button { flex: none; width: 52px; height: 52px; border: 0; border-radius: 50%; background: var(--mine); color: #fff; font-size: 24px; display: grid; place-items: center; cursor: pointer; }
        button:active { transform: scale(.92); }
        button:disabled { opacity: .5; }
        #msg { position: fixed; left: 50%; bottom: 90px; transform: translateX(-50%); background: #2b2542; color: #fff; padding: 10px 18px; border-radius: 20px; font-size: 15px; opacity: 0; transition: opacity .2s; pointer-events: none; }
        #msg.on { opacity: 1; }
    </style>
</head>
<body>
    <header><span>💌</span> Mensajes</header>
    <div id="list"></div>
    <form id="f" autocomplete="off">
        <textarea id="t" rows="1" maxlength="280" placeholder="Escribí tu mensaje…" enterkeyhint="send"></textarea>
        <button id="s" type="submit" aria-label="Enviar">➤</button>
    </form>
    <div id="msg"></div>
<script>
const $ = s => document.querySelector(s);
const list = $('#list'), ta = $('#t'), btn = $('#s');
let last = '';

function toast(t) { const m = $('#msg'); m.textContent = t; m.classList.add('on'); setTimeout(() => m.classList.remove('on'), 2200); }

function fmt(d) {
    const x = new Date(String(d).replace(' ', 'T'));
    const hoy = new Date().toDateString() === x.toDateString();
    const hora = x.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    return hoy ? hora : x.toLocaleDateString('es', { day: 'numeric', month: 'short' }) + ' ' + hora;
}

async function load(scroll) {
    try {
        const res = await fetch('../backend/api.php?action=hija_list', { cache: 'no-store' });
        const rows = (await res.json()).reverse();
        const sig = JSON.stringify(rows.map(r => r.id));
        if (sig === last) return;
        last = sig;
        list.innerHTML = '';
        if (!rows.length) list.innerHTML = '<div class="empty"><b>👋</b>Todavía no hay mensajes.<br>¡Escribí el primero!</div>';
        rows.forEach(r => {
            const d = document.createElement('div');
            d.className = 'b' + (+r.de_hija ? ' mine' : '');
            if (!+r.de_hija && r.autor) { const w = document.createElement('div'); w.className = 'who'; w.textContent = r.autor; d.appendChild(w); }
            const p = document.createElement('div'); p.textContent = r.mensaje; d.appendChild(p);
            const s = document.createElement('small'); s.textContent = fmt(r.fecha_creacion); d.appendChild(s);
            list.appendChild(d);
        });
        list.scrollTop = list.scrollHeight;
    } catch (e) { /* sin red: se reintenta solo */ }
}

ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px'; });
ta.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#f').requestSubmit(); } });

$('#f').addEventListener('submit', async e => {
    e.preventDefault();
    const mensaje = ta.value.trim();
    if (!mensaje) return;
    btn.disabled = true;
    try {
        const res = await fetch('../backend/api.php?action=hija_send', { method: 'POST', body: JSON.stringify({ mensaje }) });
        const j = await res.json();
        if (j.error) throw new Error(j.error);
        ta.value = ''; ta.style.height = 'auto';
        if (navigator.vibrate) navigator.vibrate(15);
        toast('¡Enviado! 💜');
        await load();
    } catch (err) { toast(err.message || 'No se pudo enviar'); }
    btn.disabled = false;
});

load();
setInterval(load, 15000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
</script>
</body>
</html>
