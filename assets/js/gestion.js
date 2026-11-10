// Gestión — Aura. Aplicación de una sola página, sin librerías externas.
(() => {
'use strict';

const API = '../backend/api.php';
const UPLOAD = '../backend/upload.php';
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const U = p => '../' + p;   // ruta de archivo del proyecto vista desde /gestion/

// ─── Íconos ──────────────────────────────────────────────────────────────────
const ICONS = {
    plus: 'M12 5v14M5 12h14',
    back: 'm15 18-6-6 6-6',
    next: 'm9 18 6-6-6-6',
    x: 'M18 6 6 18M6 6l12 12',
    check: 'M20 6 9 17l-5-5',
    trash: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
    rotateCw: 'M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5',
    rotateCcw: 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5',
    more: 'M12 12h.01M19 12h.01M5 12h.01',
    folder: 'M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
    monitor: 'M2 5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zM8 21h8M12 17v4',
    zap: 'M13 2 3 14h9l-1 8 10-12h-9z',
    message: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
    sliders: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
    upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
    play: 'm6 3 14 9-14 9z',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 6v6l4 2',
    image: 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2M21 15l-5-5L5 21',
    eye: 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
    copy: 'M8 8h12v12H8zM4 16V4h12',
    link: 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7',
    refresh: 'M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5',
    logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
    search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16M21 21l-4.3-4.3',
    help: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01',
    phone: 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2M12 18h.01',
    sliders2: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
    select: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
};
const ic = n => `<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="${ICONS[n] || ''}"/></svg>`;

// ─── Utilidades ──────────────────────────────────────────────────────────────
async function api(action, { query = {}, body } = {}) {
    const qs = new URLSearchParams({ action, ...query });
    const opt = {};
    if (body !== undefined) {
        opt.method = 'POST';
        opt.headers = { 'Content-Type': 'application/json' };
        opt.body = JSON.stringify(body);
    }
    const r = await fetch(`${API}?${qs}`, opt);
    if (r.status === 401) { location.href = 'login.php'; throw new Error('Sesión vencida'); }
    let j;
    try { j = await r.json(); } catch (e) { throw new Error('Respuesta inválida del servidor'); }
    if (j && j.error) throw new Error(j.error);
    return j;
}

function toast(msg, kind = '') {
    const el = document.createElement('div');
    el.className = 'toast ' + kind;
    el.innerHTML = (kind === 'ok' ? ic('check') : '') + `<span>${esc(msg)}</span>`;
    $('#toasts').appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2400);
}
const fail = e => toast(e && e.message ? e.message : 'Algo salió mal', 'err');

// Vibración suave en acciones táctiles (si el celular la permite)
const buzz = (ms = 8) => { try { navigator.vibrate && navigator.vibrate(ms); } catch (e) {} };

function stepper(attrs, val, min, max, unit = '') {
    return `<div class="stepper">${unit ? `<small>${unit}</small>` : ''}<button type="button" data-step="-1" aria-label="Menos">−</button>
        <input type="number" inputmode="numeric" min="${min}" max="${max}" value="${esc(val)}" ${attrs}>
        <button type="button" data-step="1" aria-label="Más">+</button></div>`;
}
document.addEventListener('click', e => {
    const b = e.target.closest('[data-step]');
    if (!b) return;
    const inp = $('input', b.parentElement);
    const min = +inp.min || 0, max = +inp.max || 999;
    inp.value = Math.max(min, Math.min(max, (+inp.value || min) + (+b.dataset.step)));
    buzz(6);
    inp.dispatchEvent(new Event('change', { bubbles: true }));
});

function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

function fmtBytes(b) {
    if (b >= 1e12) return (b / 1e12).toFixed(1) + ' TB';
    if (b >= 1e9) return (b / 1e9).toFixed(1) + ' GB';
    if (b >= 1e6) return Math.round(b / 1e6) + ' MB';
    return Math.max(1, Math.round(b / 1e3)) + ' KB';
}
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

// Hojas modales
const layer = $('#layer');
function openSheet(html, mount) {
    const ov = document.createElement('div');
    ov.className = 'overlay';
    ov.innerHTML = `<div class="sheet" role="dialog">${html}</div>`;
    const close = () => { ov.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = e => { if (e.key === 'Escape') close(); };
    ov.addEventListener('mousedown', e => { if (e.target === ov) close(); });
    document.addEventListener('keydown', onKey);
    layer.appendChild(ov);

    // Deslizar hacia abajo para cerrar (solo en pantallas angostas)
    const sh = $('.sheet', ov);
    let y0 = null, dy = 0;
    sh.addEventListener('touchstart', e => { y0 = (sh.scrollTop <= 0 && innerWidth < 700) ? e.touches[0].clientY : null; dy = 0; }, { passive: true });
    sh.addEventListener('touchmove', e => {
        if (y0 === null) return;
        dy = e.touches[0].clientY - y0;
        if (dy > 0) { sh.classList.add('dragging'); sh.style.transform = `translateY(${dy}px)`; }
    }, { passive: true });
    sh.addEventListener('touchend', () => {
        if (y0 === null) return;
        sh.classList.remove('dragging');
        if (dy > 110) { buzz(6); close(); }
        else { sh.classList.add('snap'); sh.style.transform = ''; setTimeout(() => sh.classList.remove('snap'), 260); }
        y0 = null;
    });
    if (mount) mount($('.sheet', ov), close);
    return close;
}

function confirmSheet({ title, text, ok = 'Eliminar', danger = true }) {
    return new Promise(res => {
        let done = false;
        const close = openSheet(`
            <h3>${esc(title)}</h3><p class="sub">${esc(text || '')}</p>
            <div class="btns"><button class="btn" data-no>Cancelar</button>
            <button class="btn ${danger ? 'btn-solid-danger' : 'btn-primary'}" data-ok>${esc(ok)}</button></div>`,
        (sh, c) => {
            $('[data-no]', sh).onclick = () => { done = true; c(); res(false); };
            $('[data-ok]', sh).onclick = () => { done = true; c(); res(true); };
        });
        const ov = layer.lastElementChild;
        new MutationObserver((_, o) => { if (!ov.isConnected) { o.disconnect(); if (!done) res(false); } }).observe(layer, { childList: true });
        return close;
    });
}

// ─── Estado y rutas ──────────────────────────────────────────────────────────
const main = $('#main');
let pollTimer = null;
let navToken = 0;
const TABS = [
    ['albumes', 'Álbumes', 'folder'],
    ['pantalla', 'Pantalla', 'monitor'],
    ['pase', 'Pase Rápido', 'zap'],
    ['mensajes', 'Mensajes', 'message'],
    ['sistema', 'Sistema', 'sliders'],
    ['ayuda', 'Ayuda', 'help'],
];

// Funciones que el panel de administración puede desactivar (feat_x = '0' en los ajustes)
let FEAT = {};
const TAB_FEAT = { pase: 'pase_rapido', mensajes: 'mensajes' };
const SECTION_FEAT = { 'Clima': 'clima', 'Modo noche': 'modo_noche', 'Horario de encendido': 'horario', 'Sincronizar desde el celular': 'sincronizacion' };
const featOk = k => !k || FEAT[k] !== false;
function hideDisabledSections() {
    main.querySelectorAll('.section-title').forEach(t => {
        const k = SECTION_FEAT[t.textContent.trim()];
        if (k && !featOk(k)) { const nx = t.nextElementSibling; if (nx && nx.classList.contains('card')) nx.remove(); t.remove(); }
    });
}

function renderTabs(active) {
    $('#tabbar').innerHTML = TABS.filter(t => featOk(TAB_FEAT[t[0]])).map(([id, label, icon]) =>
        `<a class="tab ${active === id ? 'on' : ''}" href="#/${id}">${ic(icon)}<span>${label}</span></a>`).join('');
    $$('.tab').forEach(t => t.addEventListener('click', () => buzz(6)));
}

function route() {
    clearTimeout(pollTimer);
    const parts = (location.hash.replace(/^#\/?/, '') || 'albumes').split('/');
    const token = ++navToken;
    const tab = parts[0] === 'bienvenida' ? 'bienvenida' : TABS.find(t => t[0] === parts[0]) ? parts[0] : 'albumes';
    renderTabs(tab === 'bienvenida' ? '' : tab);
    const views = { albumes: () => parts[1] ? viewAlbum(+parts[1], token) : viewAlbums(token), pantalla: viewPantalla, pase: viewPase, mensajes: viewMensajes, sistema: viewSistema, ayuda: viewAyuda, bienvenida: viewBienvenida };
    main.innerHTML = tab === 'albumes'
        ? '<div class="skel-wrap"><div class="skel skel-title"></div><div class="skel skel-hero"></div><div class="skel-cards"><div class="skel"></div><div class="skel"></div></div></div>'
        : '<div class="skel-wrap"><div class="skel skel-title"></div><div class="skel skel-block"></div><div class="skel skel-block"></div><div class="skel skel-block"></div></div>';
    main.dataset.fresh = '1';
    window.scrollTo(0, 0);
    // Primera vez: llevar al asistente de bienvenida (una sola comprobación por carga)
    if (setupChecked || tab === 'bienvenida') return void Promise.resolve(views[tab](token)).catch(fail);
    setupChecked = true;
    api('get_settings').then(cfg => {
        FEAT = {}; Object.keys(cfg).forEach(k => { if (k.indexOf('feat_') === 0) FEAT[k.slice(5)] = cfg[k] !== '0'; });
        renderTabs(tab === 'bienvenida' ? '' : tab);
        if (!featOk(TAB_FEAT[tab])) { location.hash = '#/albumes'; return; }
        if (cfg.setup_done !== '1') { location.hash = '#/bienvenida'; if (tab === 'bienvenida') route(); }
        else views[tab](token);
    }).catch(fail);
}
let setupChecked = false;
new MutationObserver(hideDisabledSections).observe(main, { childList: true });
window.addEventListener('hashchange', route);

// Animación de entrada solo cuando cambia de pantalla (no en cada refresco)
new MutationObserver(() => {
    if (main.dataset.fresh && !main.querySelector('.skel-wrap')) {
        delete main.dataset.fresh;
        main.classList.remove('view-in'); void main.offsetWidth; main.classList.add('view-in');
    }
}).observe(main, { childList: true });

// Tirar hacia abajo para actualizar
(() => {
    const ptr = document.createElement('div');
    ptr.className = 'ptr';
    ptr.innerHTML = ic('refresh');
    document.body.appendChild(ptr);
    let y0 = null, pull = 0;
    addEventListener('touchstart', e => { y0 = (scrollY <= 0 && !layer.children.length) ? e.touches[0].clientY : null; pull = 0; }, { passive: true });
    addEventListener('touchmove', e => {
        if (y0 === null) return;
        pull = Math.max(0, Math.min(120, (e.touches[0].clientY - y0) * 0.5));
        ptr.style.opacity = Math.min(1, pull / 50);
        ptr.style.transform = `translateY(${pull - 50}px)`;
        $('svg', ptr).style.transform = `rotate(${pull * 5}deg)`;
    }, { passive: true });
    addEventListener('touchend', () => {
        if (y0 === null) return;
        if (pull > 55) { buzz(10); route(); }
        ptr.style.transition = 'opacity .25s, transform .25s'; ptr.style.opacity = 0; ptr.style.transform = 'translateY(-60px)';
        setTimeout(() => { ptr.style.transition = ''; }, 260);
        y0 = null; pull = 0;
    });
})();
const alive = t => t === navToken;

// ─── ÁLBUMES ─────────────────────────────────────────────────────────────────
async function viewAlbums(token) {
    const [albums, status] = await Promise.all([api('list_albums'), api('get_status')]);
    if (!alive(token)) return;
    const active = albums.find(a => +a.activo);

    const hero = active
        ? `<div class="hero fade-in" data-open="${active.id}">
               <div class="bg" style="${active.portada ? `background-image:url('${esc(U(active.portada))}')` : ''}"></div>
               <span class="tag">En pantalla ahora</span>
               <h2>${esc(active.nombre)}</h2>
               <p>${albumCount(active)}</p>
           </div>`
        : `<div class="hero empty fade-in"><span class="tag" style="background:rgba(255,255,255,.18)">Sin álbum activo</span><h2>Nada en pantalla</h2><p>Abrí un álbum y elegí “Mostrar en pantalla”.</p></div>`;

    const cards = albums.map(a => `
        <button class="album-card fade-in" data-open="${a.id}">
            <div class="cover" style="${a.portada ? `background-image:url('${esc(U(a.portada))}')` : ''}">
                ${a.portada ? '' : ic('image')}
                ${+a.activo ? '<span class="live">En pantalla</span>' : ''}
            </div>
            <div class="meta"><b>${esc(a.nombre)}</b><span>${albumCount(a)}</span></div>
        </button>`).join('');

    main.innerHTML = `
        <div class="page-head"><h1>Álbumes</h1><button class="btn btn-primary" data-new>${ic('plus')} Nuevo</button></div>
        ${status.pendientes > 0 ? `<div class="banner"><span class="spin"></span>Procesando ${plural(status.pendientes, 'archivo nuevo', 'archivos nuevos')}…</div>` : ''}
        ${hero}
        ${albums.length ? `<div class="album-grid">${cards}</div>` : `
            <div class="empty">${ic('folder')}<b>Todavía no hay álbumes</b>Creá uno, o sincronizá una carpeta desde el celular (Sistema → Sincronización).</div>`}`;

    $$('[data-open]').forEach(el => el.onclick = () => { location.hash = `#/albumes/${el.dataset.open}`; });
    $('[data-new]').onclick = newAlbumSheet;
    if (status.pendientes > 0) pollTimer = setTimeout(() => { if (alive(token)) viewAlbums(token).catch(() => {}); }, 4000);
}

const albumCount = a => {
    const fotos = (+a.total) - (+a.videos);
    const parts = [];
    if (fotos) parts.push(plural(fotos, 'foto', 'fotos'));
    if (+a.videos) parts.push(plural(+a.videos, 'video', 'videos'));
    return parts.join(' · ') || 'Vacío';
};

function newAlbumSheet() {
    openSheet(`
        <h3>Nuevo álbum</h3><p class="sub">Se crea una carpeta con este nombre; también aparece en la carpeta sincronizada del celular.</p>
        <input type="text" id="na-name" placeholder="Ej: Vacaciones 2026" maxlength="80" autocomplete="off">
        <div class="btns"><button class="btn" data-no>Cancelar</button><button class="btn btn-primary" data-ok>Crear</button></div>`,
    (sh, close) => {
        const input = $('#na-name', sh);
        setTimeout(() => input.focus(), 50);
        const go = async () => {
            const nombre = input.value.trim();
            if (!nombre) { input.focus(); return; }
            try {
                const r = await api('create_album', { body: { nombre } });
                close();
                location.hash = `#/albumes/${r.id}`;
            } catch (e) { fail(e); }
        };
        $('[data-ok]', sh).onclick = go;
        $('[data-no]', sh).onclick = close;
        input.onkeydown = e => { if (e.key === 'Enter') go(); };
    });
}

// ─── ÁLBUM ───────────────────────────────────────────────────────────────────
async function viewAlbum(id, token) {
    const [albums, media, status] = await Promise.all([api('list_albums'), api('get_album_media', { query: { id } }), api('get_status')]);
    if (!alive(token)) return;
    const album = albums.find(a => +a.id === id);
    if (!album) { location.hash = '#/albumes'; return; }
    const st = { selecting: false, sel: new Set() };
    const isGeneral = album.carpeta === '';
    const isActive = !!+album.activo;

    const draw = () => {
        const fotos = media.filter(m => m.tipo === 'imagen').length;
        const vids = media.length - fotos;
        main.innerHTML = `
            <div class="topbar">
                ${st.selecting
                    ? `<button class="icon-btn" data-cancel>${ic('x')}</button><h2>${st.sel.size ? plural(st.sel.size, 'seleccionada', 'seleccionadas') : 'Elegí fotos'}</h2>
                       <button class="icon-btn" style="color:var(--danger)" data-del-sel ${st.sel.size ? '' : 'disabled'}>${ic('trash')}</button>`
                    : `<a class="icon-btn" href="#/albumes">${ic('back')}</a><h2>${esc(album.nombre)}</h2>
                       <button class="icon-btn" data-menu>${ic('more')}</button>`}
            </div>
            ${status.pendientes > 0 ? `<div class="banner"><span class="spin"></span>Procesando ${plural(status.pendientes, 'archivo nuevo', 'archivos nuevos')}…</div>` : ''}
            <div class="chips">
                ${isActive ? '<span class="chip live">En pantalla</span>' : ''}
                <span class="chip">${plural(fotos, 'foto', 'fotos')}</span>
                ${vids ? `<span class="chip">${plural(vids, 'video', 'videos')}</span>` : ''}
                ${album.duracion_default ? `<span class="chip">${album.duracion_default} s por foto</span>` : ''}
                ${album.animacion_tipo ? `<span class="chip">${esc(ANIM_LABEL[album.animacion_tipo] || album.animacion_tipo)}</span>` : ''}
            </div>
            <div class="action-row">
                ${isActive ? '' : `<button class="btn btn-primary" data-activate>${ic('monitor')} Mostrar en pantalla</button>`}
                ${media.length ? `<button class="btn" data-select>${ic('select')} Seleccionar</button>` : ''}
            </div>
            ${media.length ? `<div class="media-grid ${st.selecting ? 'selecting' : ''}">${media.map((m, i) => `
                <button class="tile ${st.sel.has(m.id) ? 'sel' : ''}" data-i="${i}">
                    ${m.thumb ? `<img src="${esc(U(m.thumb))}" loading="lazy" alt="" onload="this.classList.add('ld')">` : ''}
                    <span class="chk">${ic('check')}</span>
                    ${m.tipo === 'video' ? `<span class="vid">${ic('play')}</span>` : ''}
                    ${+m.duracion_img > 0 ? `<span class="dur">${m.duracion_img}s</span>` : ''}
                </button>`).join('')}</div>`
            : `<div class="empty">${ic('image')}<b>Álbum vacío</b>Tocá el botón + para agregar, o copiá fotos a la carpeta <b style="display:inline">${esc(album.carpeta === '' ? 'Fotos' : (album.carpeta || album.nombre))}</b> desde el celular.</div>`}
            ${st.selecting ? '' : `<button class="fab" data-add aria-label="Agregar fotos y videos">${ic('plus')}</button>`}`;

        // Tocar abre; mantener apretado empieza a seleccionar
        $$('.tile').forEach(t => {
            let timer = null;
            const m = media[+t.dataset.i];
            t.addEventListener('pointerdown', () => {
                timer = setTimeout(() => {
                    buzz(15); st.selecting = true; st.sel.add(m.id);
                    st.justLong = true; setTimeout(() => { st.justLong = false; }, 600);   // ignora el "click" que sigue al soltar
                    draw();
                }, 480);
            });
            ['pointerup', 'pointerleave', 'pointercancel'].forEach(ev => t.addEventListener(ev, () => clearTimeout(timer)));
            t.addEventListener('contextmenu', e => e.preventDefault());
            t.onclick = () => {
                if (st.justLong) return;
                if (st.selecting) { buzz(6); st.sel.has(m.id) ? st.sel.delete(m.id) : st.sel.add(m.id); draw(); }
                else openLightbox(media, +t.dataset.i, onChanged);
            };
        });
        const q = s => $(s);
        if (q('[data-add]')) q('[data-add]').onclick = () => { buzz(8); uploadSheet(album, onUploaded); };
        if (q('[data-activate]')) q('[data-activate]').onclick = async () => {
            try { await api('set_active_album', { query: { id } }); toast('Ahora se muestra en la pantalla', 'ok'); viewAlbum(id, token); } catch (e) { fail(e); }
        };
        if (q('[data-select]')) q('[data-select]').onclick = () => { st.selecting = true; st.sel.clear(); draw(); };
        if (q('[data-cancel]')) q('[data-cancel]').onclick = () => { st.selecting = false; st.sel.clear(); draw(); };
        if (q('[data-del-sel]')) q('[data-del-sel]').onclick = async () => {
            if (!await confirmSheet({ title: `¿Eliminar ${plural(st.sel.size, 'archivo', 'archivos')}?`, text: 'También se borran de la carpeta sincronizada y del celular. Quedan 30 días en la papelera.' })) return;
            try {
                for (const mid of st.sel) await api('delete_media', { query: { id: mid } });
                toast('Eliminado', 'ok');
                viewAlbum(id, token);
            } catch (e) { fail(e); }
        };
        if (q('[data-menu]')) q('[data-menu]').onclick = () => albumMenu(album, token);
    };

    const onChanged = () => viewAlbum(id, token);
    const onUploaded = () => { toast('Archivos subidos, procesando…', 'ok'); viewAlbum(id, token); };
    draw();
    if (status.pendientes > 0) pollTimer = setTimeout(() => { if (alive(token)) viewAlbum(id, token).catch(() => {}); }, 4000);
}

const ANIM_LABEL = { fade: 'Desvanecer', slide: 'Deslizar', zoom: 'Zoom', none: 'Sin animación' };
const ANIM_OPTS = Object.entries(ANIM_LABEL).map(([v, l]) => `<option value="${v}">${l}</option>`).join('');

function albumMenu(album, token) {
    openSheet(`
        <h3>${esc(album.nombre)}</h3>
        <div class="menu" style="margin-top:10px">
            <button data-s>${ic('sliders2')} Ajustes del álbum</button>
            <button data-e>${ic('trash')} Vaciar álbum</button>
            ${album.carpeta === '' ? '' : `<button class="danger" data-d>${ic('trash')} Eliminar álbum</button>`}
        </div>`,
    (sh, close) => {
        $('[data-s]', sh).onclick = () => { close(); albumSettingsSheet(album, token); };
        $('[data-e]', sh).onclick = async () => {
            close();
            if (!await confirmSheet({ title: '¿Vaciar el álbum?', text: 'Se borran todas las fotos y videos, también del celular. Quedan 30 días en la papelera.', ok: 'Vaciar' })) return;
            try { await api('empty_album', { query: { id: album.id } }); toast('Álbum vaciado', 'ok'); viewAlbum(+album.id, token); } catch (e) { fail(e); }
        };
        const d = $('[data-d]', sh);
        if (d) d.onclick = async () => {
            close();
            if (!await confirmSheet({ title: '¿Eliminar el álbum?', text: 'Se borra el álbum y su carpeta, también del celular. Queda 30 días en la papelera.' })) return;
            try { await api('delete_album', { query: { id: album.id } }); toast('Álbum eliminado', 'ok'); location.hash = '#/albumes'; } catch (e) { fail(e); }
        };
    });
}

function albumSettingsSheet(album, token) {
    openSheet(`
        <h3>Ajustes del álbum</h3><p class="sub">Si lo dejás vacío se usa lo configurado en Pantalla.</p>
        <div class="card" style="margin:0;box-shadow:none;background:var(--surface-2);border:none">
            <div class="row"><div class="lbl"><b>Segundos por foto</b></div><input type="number" id="as-dur" min="0" max="300" placeholder="Global" value="${album.duracion_default || ''}"></div>
            <div class="row"><div class="lbl"><b>Animación</b></div><select id="as-anim"><option value="">Global</option>${ANIM_OPTS}</select></div>
        </div>
        <div class="btns"><button class="btn" data-no>Cancelar</button><button class="btn btn-primary" data-ok>Guardar</button></div>`,
    (sh, close) => {
        $('#as-anim', sh).value = album.animacion_tipo || '';
        $('[data-no]', sh).onclick = close;
        $('[data-ok]', sh).onclick = async () => {
            try {
                await api('update_album_settings', { body: { id: +album.id, duracion: +$('#as-dur', sh).value || 0, animacion: $('#as-anim', sh).value } });
                close(); toast('Guardado', 'ok'); viewAlbum(+album.id, token);
            } catch (e) { fail(e); }
        };
    });
}

// Subida de archivos (uno por uno, con progreso)
function uploadSheet(album, done, extra = {}) {
    const accept = extra.accept || 'image/*,video/*';
    openSheet(`
        <h3>${esc(extra.title || 'Agregar al álbum')}</h3>
        <p class="sub">${esc(extra.sub || 'Elegí fotos y videos de este dispositivo. Se guardan en la carpeta del álbum.')}</p>
        <label class="dropzone" id="up-drop">${ic('upload')}<b>Elegir archivos</b>o arrastralos hasta acá
            <input type="file" id="up-file" multiple accept="${accept}" hidden></label>
        <div id="up-list" style="margin-top:14px"></div>
        <div class="btns" id="up-btns" hidden><button class="btn btn-primary" data-close>Listo</button></div>`,
    (sh, close) => {
        const input = $('#up-file', sh), drop = $('#up-drop', sh), list = $('#up-list', sh);
        let busy = false;
        const start = async files => {
            files = [...files];
            if (!files.length || busy) return;
            busy = true;
            drop.hidden = true;
            list.innerHTML = files.map((f, i) => `<div class="upload-item"><span class="nm">${esc(f.name)}</span><span class="st" id="up-st-${i}">En espera</span></div><div class="bar" style="margin-bottom:6px"><i id="up-bar-${i}"></i></div>`).join('');
            let ok = 0;
            for (let i = 0; i < files.length; i++) {
                const st = $('#up-st-' + i, sh), bar = $('#up-bar-' + i, sh);
                try {
                    await sendFile(files[i], extra.fields || { album_id: album.id }, pct => { st.textContent = pct + '%'; bar.style.width = pct + '%'; });
                    st.textContent = 'Listo'; st.className = 'st ok'; bar.style.width = '100%'; ok++;
                } catch (e) { st.textContent = 'Error'; st.className = 'st err'; st.title = e.message; }
            }
            busy = false;
            $('#up-btns', sh).hidden = false;
            $('[data-close]', sh).onclick = () => { close(); if (ok) done && done(); };
        };
        input.onchange = () => start(input.files);
        ['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
        drop.addEventListener('drop', e => start(e.dataTransfer.files));
    });
}

function sendFile(file, fields, onProgress) {
    return new Promise((resolve, reject) => {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
        fd.append('media[]', file);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', UPLOAD);
        xhr.upload.onprogress = e => { if (e.lengthComputable) onProgress(Math.round(e.loaded / e.total * 100)); };
        xhr.onload = () => {
            if (xhr.status === 401) { location.href = 'login.php'; return; }
            try {
                const j = JSON.parse(xhr.responseText);
                if (j.error) return reject(new Error(j.error));
                if (j.failed && j.failed.length) return reject(new Error('Formato no soportado'));
                resolve(j);
            } catch (e) { reject(new Error('Respuesta inválida')); }
        };
        xhr.onerror = () => reject(new Error('Sin conexión'));
        xhr.send(fd);
    });
}

// ─── Visor de fotos a pantalla completa ─────────────────────────────────────
function openLightbox(media, start, onChanged) {
    let i = start, changed = false;
    const ov = document.createElement('div');
    ov.className = 'lightbox';
    layer.appendChild(ov);

    const close = () => { document.removeEventListener('keydown', onKey); ov.remove(); if (changed) onChanged(); };
    const onKey = e => {
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') go(-1);
        else if (e.key === 'ArrowRight') go(1);
    };
    document.addEventListener('keydown', onKey);

    function go(d) { if (!media.length) return close(); i = (i + d + media.length) % media.length; show(); }

    function show() {
        const m = media[i];
        ov.innerHTML = `
            <div class="lb-top"><button class="icon-btn" data-close>${ic('x')}</button><span class="count">${i + 1} / ${media.length}</span><span style="width:42px"></span></div>
            <button class="icon-btn nav prev" data-prev>${ic('back')}</button>
            <div class="stage">${m.tipo === 'video'
                ? `<video src="${esc(U(m.ruta))}" controls autoplay playsinline></video>`
                : `<img src="${esc(U(m.ruta))}" alt="">`}</div>
            <button class="icon-btn nav next" data-next>${ic('next')}</button>
            <div class="lb-bottom">
                ${m.tipo === 'imagen' ? `<button class="lb-pill" data-rot="270">${ic('rotateCcw')}</button><button class="lb-pill" data-rot="90">${ic('rotateCw')}</button>` : ''}
                <button class="lb-pill" data-dur>${ic('clock')} ${+m.duracion_img > 0 ? m.duracion_img + ' s' : 'Duración'}</button>
                <button class="lb-pill danger" data-del>${ic('trash')}</button>
            </div>`;
        $('[data-close]', ov).onclick = close;
        $('[data-prev]', ov).onclick = () => go(-1);
        $('[data-next]', ov).onclick = () => go(1);
        $$('[data-rot]', ov).forEach(b => b.onclick = async () => {
            try {
                await api('rotate_media', { body: { id: +m.id, degrees: +b.dataset.rot } });
                const bust = '?r=' + Date.now();
                m.ruta = m.ruta.replace(/\?.*$/, '') + bust;
                if (m.thumb) m.thumb = m.thumb.replace(/\?.*$/, '') + bust;
                changed = true; show();
            } catch (e) { fail(e); }
        });
        $('[data-dur]', ov).onclick = () => durationSheet(m, () => { changed = true; show(); });
        $('[data-del]', ov).onclick = async () => {
            if (!await confirmSheet({ title: '¿Eliminar?', text: 'Se borra también de la carpeta sincronizada y del celular. Queda 30 días en la papelera.' })) return;
            try {
                await api('delete_media', { query: { id: m.id } });
                media.splice(i, 1); changed = true;
                if (!media.length) return close();
                i = Math.min(i, media.length - 1); show(); toast('Eliminado', 'ok');
            } catch (e) { fail(e); }
        };
    }

    // Gestos: deslizar de costado cambia de foto, hacia abajo cierra, doble toque hace zoom
    let x0 = null, y0 = null, dyNow = 0;
    ov.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; dyNow = 0; }, { passive: true });
    ov.addEventListener('touchmove', e => {
        if (x0 === null) return;
        const st = $('.stage', ov), img = $('img', st);
        if (img && img.style.transform.includes('scale(2')) return;   // con zoom no se arrastra
        dyNow = e.touches[0].clientY - y0;
        const dx = e.touches[0].clientX - x0;
        if (dyNow > 0 && Math.abs(dyNow) > Math.abs(dx)) { st.style.transform = `translateY(${dyNow}px)`; st.style.opacity = Math.max(.3, 1 - dyNow / 500); }
    }, { passive: true });
    ov.addEventListener('touchend', e => {
        if (x0 === null) return;
        const dx = e.changedTouches[0].clientX - x0; x0 = null;
        const st = $('.stage', ov);
        if (dyNow > 110 && dyNow > Math.abs(dx)) { buzz(8); return close(); }
        if (st) { st.style.transform = ''; st.style.opacity = ''; }
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dyNow)) { buzz(5); go(dx < 0 ? 1 : -1); }
    });
    ov.addEventListener('dblclick', e => {
        const img = e.target.closest('img');
        if (!img) return;
        const zoomed = img.style.transform.includes('scale(2');
        img.style.transformOrigin = `${(e.offsetX / img.clientWidth) * 100}% ${(e.offsetY / img.clientHeight) * 100}%`;
        img.style.transform = zoomed ? '' : 'scale(2.4)';
        buzz(6);
    });
    show();
}

function durationSheet(m, done) {
    openSheet(`
        <h3>Duración en pantalla</h3><p class="sub">Cuántos segundos se muestra esta ${m.tipo === 'video' ? 'portada' : 'foto'}. Vacío = la del álbum o la global.</p>
        <input type="number" id="md-dur" min="0" max="300" placeholder="Automática" value="${+m.duracion_img > 0 ? m.duracion_img : ''}">
        <div class="btns"><button class="btn" data-no>Cancelar</button><button class="btn btn-primary" data-ok>Guardar</button></div>`,
    (sh, close) => {
        $('[data-no]', sh).onclick = close;
        $('[data-ok]', sh).onclick = async () => {
            const v = +$('#md-dur', sh).value || 0;
            try { await api('update_media_duration', { body: { id: +m.id, duracion: v } }); m.duracion_img = v || null; close(); done(); toast('Guardado', 'ok'); } catch (e) { fail(e); }
        };
    });
}

// ─── PANTALLA (ajustes del Visor) ───────────────────────────────────────────
let queued = {};
const flushSettings = debounce(async () => {
    const data = queued; queued = {};
    try { await api('update_settings', { body: data }); toast('Guardado', 'ok'); } catch (e) { fail(e); }
}, 450);
const setSetting = (k, v) => { queued[k] = String(v); flushSettings(); };

const seg = (id, opts, cur) => `<div class="seg" data-seg="${id}">${opts.map(([v, l]) => `<button data-v="${v}" class="${String(cur) === String(v) ? 'on' : ''}">${l}</button>`).join('')}</div>`;
const sw = (id, on) => `<label class="switch"><input type="checkbox" data-sw="${id}" ${on ? 'checked' : ''}><i></i></label>`;
const SIZES = [['small', 'Pequeño'], ['standard', 'Estándar'], ['large', 'Grande'], ['extra', 'Extra']];
const WX_STYLES = [
    ['aura-glow', 'Glow', 'fill', 'filter:drop-shadow(0 4px 8px rgba(0,0,0,.35))'],
    ['neo-flat', 'Flat', 'fill', ''],
    ['minimal-line', 'Línea', 'line', 'filter:brightness(0) invert(1)'],
    ['vibrant-anim', 'Vibrante', 'fill', 'filter:sepia(1) saturate(3) hue-rotate(320deg) brightness(1.2)'],
    ['glassmorphism', 'Glass', 'fill', 'filter:opacity(.7) drop-shadow(0 0 6px rgba(255,255,255,.6))'],
];

async function viewPantalla(token) {
    const s = await api('get_settings');
    if (!alive(token)) return;
    const g = (k, d) => (s[k] !== undefined && s[k] !== '') ? s[k] : d;
    const clock = { style: g('clock_style', 'v-1'), size: g('clock_size', 'standard'), date: g('date_format', 'full') };

    const dateStr = fmt => {
        const n = new Date();
        const dn = n.toLocaleDateString('es-ES', { weekday: 'short' }).toUpperCase().replace('.', '');
        const mn = n.toLocaleDateString('es-ES', { month: 'short' }).toUpperCase().replace('.', '');
        const d = n.getDate(), mo = String(n.getMonth() + 1).padStart(2, '0'), y = n.getFullYear();
        if (fmt === 'standard') return `${d} ${mn} ${y}`;
        if (fmt === 'numeric') return `${d} / ${mo} / ${y}`;
        if (fmt === 'short') return `${dn} ${d} ${mn}`;
        if (fmt === 'minimal') return `${dn} ${d}`;
        return `${dn} ${d} ${mn} ${y}`;
    };
    const hhmm = () => { const n = new Date(); return String(n.getHours()).padStart(2, '0') + ':' + String(n.getMinutes()).padStart(2, '0'); };
    const clockHtml = (style, size, withDate) => `<div class="cp size-${size}"><div class="clock-glass style-${style}"><div class="ct">${hhmm()}</div>${withDate ? `<div class="cd">${dateStr(clock.date)}</div>` : ''}</div></div>`;

    const restImg = s.visor_off_image || '';
    main.innerHTML = `
        <div class="page-head"><h1>Pantalla</h1><button class="btn" data-preview>${ic('eye')} Ver</button></div>
        <p class="page-sub">Lo que cambies se guarda solo y se ve en el portarretrato en menos de un minuto.</p>

        <div class="section-title">Presentación</div>
        <div class="card">
            <div class="row"><div class="lbl"><b>Segundos por foto</b><small>Un álbum o una foto pueden tener la suya</small></div>${stepper('data-s="slide_duration"', g('slide_duration', '10'), 1, 120)}</div>
            <div class="row"><div class="lbl"><b>Animación</b></div><select data-s="slide_animation">${ANIM_OPTS}</select></div>
        </div>

        <div class="section-title">Reloj</div>
        <div class="card pad">
            <div class="preview-stage" id="clk-prev">${clockHtml(clock.style, clock.size, true)}</div>
            <div class="style-scroller" id="clk-styles" style="margin-top:14px">
                ${Array.from({ length: 25 }, (_, n) => `<button class="style-opt ${clock.style === 'v-' + (n + 1) ? 'on' : ''}" data-style="v-${n + 1}" aria-label="Estilo ${n + 1}">${clockHtml('v-' + (n + 1), 'standard', false)}</button>`).join('')}
            </div>
            <div class="row col" style="padding-bottom:4px"><div class="lbl"><b>Tamaño</b></div>${seg('clock_size', SIZES, clock.size)}</div>
            <div class="row col" style="border-top:1px solid var(--line)"><div class="lbl"><b>Formato de fecha</b></div>${seg('date_format', [['full', 'Completo'], ['standard', 'Estándar'], ['numeric', 'Numérico'], ['short', 'Corto']], clock.date)}</div>
        </div>

        <div class="section-title">Clima</div>
        <div class="card pad">
            <div class="lbl" style="margin-bottom:8px"><b>Ciudad</b></div>
            <div class="inline-form"><input type="text" id="wx-city" placeholder="Ej: Zárate, Argentina" value="${esc(g('weather_city', ''))}"><button class="btn btn-primary" id="wx-find">${ic('search')}</button></div>
            <p class="hint" id="wx-found" style="margin-top:8px">${s.weather_lat ? 'Ubicación guardada.' : 'Escribí una ciudad y tocá buscar.'}</p>
            <div class="lbl" style="margin:18px 0 8px"><b>Estilo de íconos</b></div>
            <div class="wx-opts">${WX_STYLES.map(([id, label, folder, css]) => `
                <button class="wx-opt ${g('weather_icons', 'neo-flat') === id ? 'on' : ''}" data-wx="${id}">
                    <img src="../assets/icons/weather/${folder}/partly-cloudy-day.svg" style="${css}" alt="" onerror="this.style.opacity=.2">${label}</button>`).join('')}</div>
            <div class="row col" style="padding-bottom:4px;margin-top:8px"><div class="lbl"><b>Tamaño del clima actual</b></div>${seg('weather_size', SIZES, g('weather_size', 'standard'))}</div>
            <div class="row col" style="border-top:1px solid var(--line)"><div class="lbl"><b>Tamaño del pronóstico</b></div>${seg('weather_forecast_size', SIZES, g('weather_forecast_size', 'standard'))}</div>
        </div>

        <div class="section-title">Modo noche</div>
        <div class="card">
            <div class="row"><div class="lbl"><b>Atenuar de noche</b><small>Baja el brillo durante este horario</small></div>${sw('night_mode_enabled', g('night_mode_enabled', '0') === '1')}</div>
            <div class="row" data-when="night_mode_enabled"><div class="lbl"><b>Desde / hasta</b></div>
                <div class="time-pair"><input type="time" data-s="night_start" value="${esc(g('night_start', '23:00'))}"><input type="time" data-s="night_end" value="${esc(g('night_end', '07:00'))}"></div></div>
        </div>

        <div class="section-title">Horario de encendido</div>
        <div class="card">
            <div class="row"><div class="lbl"><b>Encender por horario</b><small>Fuera de horario se muestra la imagen de reposo</small></div>${sw('visor_schedule_enabled', g('visor_schedule_enabled', '0') === '1')}</div>
            <div class="row" data-when="visor_schedule_enabled"><div class="lbl"><b>Enciende / apaga</b></div>
                <div class="time-pair"><input type="time" data-s="visor_on" value="${esc(g('visor_on', '07:00'))}"><input type="time" data-s="visor_off" value="${esc(g('visor_off', '23:00'))}"></div></div>
            <div class="row col" data-when="visor_schedule_enabled">
                <div class="lbl"><b>Imagen de reposo</b><small>Se muestra mientras la pantalla está “apagada”</small></div>
                <img class="rest-thumb" id="rest-img" ${restImg ? `src="${esc(U(restImg))}"` : 'hidden'} alt="">
                <div style="display:flex;gap:8px"><button class="btn btn-sm" id="rest-up">${ic('upload')} Elegir imagen</button><button class="btn btn-sm btn-danger" id="rest-clear" ${restImg ? '' : 'hidden'}>Quitar</button></div>
            </div>
        </div>`;

    // Secciones plegables: abierta solo la primera, así no hay que scrollear tanto
    $$('.section-title').forEach((t, i) => {
        const body = t.nextElementSibling;
        t.classList.add('acc');
        const set = open => { t.classList.toggle('open', open); body.hidden = !open; };
        set(i === 0);
        t.onclick = () => {
            const open = !t.classList.contains('open');
            buzz(6); set(open);
            if (open) {
                const cur = $('.style-opt.on', body);
                if (cur) cur.scrollIntoView({ inline: 'center', block: 'nearest' });
                setTimeout(() => t.scrollIntoView({ behavior: 'smooth', block: 'start' }), 60);
            }
        };
    });

    // Valores iniciales de controles
    $('[data-s=slide_animation]').value = g('slide_animation', 'fade');
    $$('[data-s]').forEach(el => el.onchange = () => {
        let v = el.value;
        if (el.type === 'number') v = Math.max(+el.min || 1, Math.min(+el.max || 999, +v || +el.min || 1));
        setSetting(el.dataset.s, v);
    });
    const whens = () => $$('[data-when]').forEach(r => { r.hidden = !$(`[data-sw=${r.dataset.when}]`).checked; });
    $$('[data-sw]').forEach(el => el.onchange = () => { setSetting(el.dataset.sw, el.checked ? '1' : '0'); whens(); });
    whens();

    // Segmentados
    $$('[data-seg]').forEach(box => $$('button', box).forEach(b => b.onclick = () => {
        $$('button', box).forEach(x => x.classList.toggle('on', x === b));
        const key = box.dataset.seg;
        setSetting(key, b.dataset.v);
        if (key === 'clock_size') { clock.size = b.dataset.v; redrawClock(); }
        if (key === 'date_format') { clock.date = b.dataset.v; redrawClock(); }
    }));

    function redrawClock() { $('#clk-prev').innerHTML = clockHtml(clock.style, clock.size, true); }
    $$('.style-opt').forEach(b => b.onclick = () => {
        $$('.style-opt').forEach(x => x.classList.toggle('on', x === b));
        clock.style = b.dataset.style; redrawClock(); setSetting('clock_style', clock.style);
    });

    // Clima
    $$('[data-wx]').forEach(b => b.onclick = () => { $$('[data-wx]').forEach(x => x.classList.toggle('on', x === b)); setSetting('weather_icons', b.dataset.wx); });
    const find = async () => {
        const name = $('#wx-city').value.trim();
        if (!name) return;
        const btn = $('#wx-find'); btn.disabled = true;
        try {
            const q = name.split(',')[0].trim();
            const r = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(q)}&count=1&language=es&format=json`);
            const j = await r.json();
            if (!j.results || !j.results.length) { $('#wx-found').textContent = 'No encontré esa ciudad.'; return; }
            const l = j.results[0];
            const label = `${l.name}, ${l.admin1 || l.country || ''}`.replace(/, $/, '');
            $('#wx-city').value = label;
            $('#wx-found').textContent = `Ubicación: ${label}`;
            await api('update_settings', { body: { weather_city: label, weather_lat: String(l.latitude), weather_lon: String(l.longitude) } });
            toast('Ciudad guardada', 'ok');
        } catch (e) { $('#wx-found').textContent = 'No se pudo buscar (¿hay internet?).'; }
        finally { btn.disabled = false; }
    };
    $('#wx-find').onclick = find;
    $('#wx-city').onkeydown = e => { if (e.key === 'Enter') find(); };

    // Imagen de reposo
    $('#rest-up').onclick = () => uploadSheet({}, () => viewPantalla(navToken), { title: 'Imagen de reposo', sub: 'Elegí una foto para mostrar mientras el portarretrato está apagado por horario.', accept: 'image/*', fields: { rest_image: '1' } });
    $('#rest-clear').onclick = async () => { try { await api('clear_rest_image'); toast('Quitada', 'ok'); viewPantalla(navToken); } catch (e) { fail(e); } };

    $('[data-preview]').onclick = () => openSheet(`<h3>Así se ve ahora</h3><p class="sub">Vista previa del Visor.</p><iframe class="preview-frame" src="../"></iframe><div class="btns"><button class="btn" data-no>Cerrar</button></div>`, (sh, c) => { $('[data-no]', sh).onclick = c; });
}

// ─── PASE RÁPIDO ─────────────────────────────────────────────────────────────
const DAYS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
async function viewPase(token) {
    const [items, s] = await Promise.all([api('get_quick_show_media'), api('get_settings')]);
    if (!alive(token)) return;
    main.innerHTML = `
        <div class="page-head"><h1>Pase Rápido</h1><button class="btn btn-primary" data-add>${ic('plus')} Agregar</button></div>
        <p class="page-sub">Imágenes sorpresa que aparecen al tocar la pantalla o apretar el botón 1. Cada una tiene un día y una hora: antes de esa hora se muestran las del día; después, las del día siguiente. Al terminar vuelve al álbum.</p>
        <div class="card">
            <div class="row"><div class="lbl"><b>Segundos por imagen</b></div>${stepper('id="qs-dur"', s.quick_show_duration || '8', 1, 120)}</div>
        </div>
        ${items.length ? `<div class="card">${items.map(q => `
            <div class="qs-item" data-id="${q.id}">
                <img src="${esc(U(q.thumb || q.ruta))}" alt="">
                <div class="ctl">
                    <select data-dow>${DAYS.map((d, n) => `<option value="${n}" ${+q.dia_semana === n ? 'selected' : ''}>${d}</option>`).join('')}</select>
                    <input type="time" data-time value="${esc((q.horario || '08:00').slice(0, 5))}">
                </div>
                <button class="icon-btn" style="color:var(--danger)" data-del>${ic('trash')}</button>
            </div>`).join('')}</div>
            <button class="btn btn-danger btn-block" data-clear>Quitar todas</button>`
        : `<div class="empty">${ic('zap')}<b>No hay imágenes en el Pase Rápido</b>Agregá algunas con el botón de arriba.</div>`}`;

    $('#qs-dur').onchange = e => { const v = Math.max(1, Math.min(120, +e.target.value || 8)); e.target.value = v; setSetting('quick_show_duration', v); };
    $('[data-add]').onclick = () => uploadSheet({}, () => viewPase(navToken), { title: 'Agregar al Pase Rápido', sub: 'Solo imágenes.', accept: 'image/*', fields: { quick_show: '1' } });
    $$('.qs-item').forEach(el => {
        const id = +el.dataset.id;
        const save = async () => {
            try { await api('update_quick_show_media', { body: { id, dia_semana: +$('[data-dow]', el).value, horario: $('[data-time]', el).value || '08:00' } }); toast('Guardado', 'ok'); } catch (e) { fail(e); }
        };
        $('[data-dow]', el).onchange = save; $('[data-time]', el).onchange = save;
        $('[data-del]', el).onclick = async () => { try { await api('delete_quick_show_media', { query: { id } }); viewPase(navToken); } catch (e) { fail(e); } };
    });
    const clr = $('[data-clear]');
    if (clr) clr.onclick = async () => {
        if (!await confirmSheet({ title: '¿Quitar todas?', text: 'Se borran todas las imágenes del Pase Rápido.', ok: 'Quitar' })) return;
        try { await api('clear_quick_show'); viewPase(navToken); } catch (e) { fail(e); }
    };
}

// ─── MENSAJES ────────────────────────────────────────────────────────────────
async function viewMensajes(token) {
    const [list, s] = await Promise.all([api('get_recordatorios'), api('get_settings')]);
    if (!alive(token)) return;
    main.innerHTML = `
        <div class="page-head"><h1>Mensajes</h1></div>
        <p class="page-sub">Notas para la familia: aparecen en el portarretrato con un aviso. Tocá el ícono de nota en pantalla (o el botón 3) para leerlas.</p>
        <div class="card pad">
            <input type="text" id="m-autor" placeholder="Tu nombre (opcional)" maxlength="60" style="margin-bottom:10px" value="${esc(localStorage.getItem('pr_autor') || '')}">
            <textarea id="m-text" maxlength="280" placeholder="Ej: Comprar leche, cumple de la abuela el sábado…"></textarea>
            <div class="counter"><span id="m-count">0</span> / 280</div>
            <button class="btn btn-primary btn-block" id="m-send" style="margin-top:10px">${ic('message')} Enviar al portarretrato</button>
        </div>
        <div class="card">
            <div class="row"><div class="lbl"><b>Escribir desde otro celular</b><small>Abrí <b>${esc(location.origin)}/hija/</b> en su celular y agregala a la pantalla de inicio: solo ve y escribe mensajes</small></div><a class="btn" href="/hija/" target="_blank">Abrir</a></div>
            <div class="row"><div class="lbl"><b>Segundos en pantalla</b><small>Cuánto tiempo se muestran al abrirlas</small></div>${stepper('id="m-dur"', s.recordatorios_duration || '20', 5, 120)}</div>
        </div>
        ${list.length ? `<div class="section-title">Enviados</div><div class="card">${list.map(r => `
            <div class="msg ${+r.visto ? '' : 'new'}"><div class="txt"><p>${esc(r.mensaje)}</p><small>${+r.de_hija ? '💌 ' : ''}${esc(r.autor || 'Anónimo')} ·${esc(String(r.fecha_creacion).slice(0, 16))}</small></div>
            <button class="icon-btn" style="color:var(--danger)" data-del="${r.id}">${ic('trash')}</button></div>`).join('')}</div>`
        : `<div class="empty">${ic('message')}<b>No hay mensajes</b></div>`}`;

    const ta = $('#m-text');
    ta.oninput = () => { $('#m-count').textContent = ta.value.length; };
    $('#m-send').onclick = async () => {
        const mensaje = ta.value.trim();
        if (!mensaje) { ta.focus(); return; }
        const autor = $('#m-autor').value.trim();
        try {
            await api('add_recordatorio', { body: { mensaje, autor } });
            try { localStorage.setItem('pr_autor', autor); } catch (e) {}
            toast('Enviado', 'ok'); viewMensajes(navToken);
        } catch (e) { fail(e); }
    };
    $('#m-dur').onchange = e => { const v = Math.max(5, Math.min(120, +e.target.value || 20)); e.target.value = v; setSetting('recordatorios_duration', v); };
    $$('[data-del]').forEach(b => b.onclick = async () => { try { await api('delete_recordatorio', { query: { id: b.dataset.del } }); viewMensajes(navToken); } catch (e) { fail(e); } });
}

// ─── SISTEMA ─────────────────────────────────────────────────────────────────
function qrSvg(text) {
    const q = qrcode(0, 'M');
    q.addData(text); q.make();
    return q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
}

async function viewSistema(token) {
    const [st, cfg, net] = await Promise.all([api('get_status'), api('get_settings'), api('wifi_status').catch(() => ({ error: 'x' }))]);
    if (!alive(token)) return;
    const usedPct = st.disco_total ? Math.round((1 - st.disco_libre / st.disco_total) * 100) : 0;
    const url = `http://${st.host}.local/gestion/`;
    const ipUrl = st.ips && st.ips[0] ? `http://${st.ips[0]}/gestion/` : '';
    const sc = st.syncthing;

    main.innerHTML = `
        <div class="page-head"><h1>Sistema</h1></div>

        <div class="section-title">Contenido</div>
        <div class="card pad">
            <div class="stats">
                <div class="stat"><b>${st.albumes}</b><span>Álbumes</span></div>
                <div class="stat"><b>${st.fotos}</b><span>Fotos</span></div>
                <div class="stat"><b>${st.videos}</b><span>Videos</span></div>
            </div>
            <div class="row" style="padding-bottom:6px"><div class="lbl"><b>Espacio en disco</b><small>${fmtBytes(st.disco_libre)} libres de ${fmtBytes(st.disco_total)}</small></div><b>${usedPct}%</b></div>
            <div class="bar ${usedPct > 85 ? 'warn' : ''}"><i style="width:${usedPct}%"></i></div>
            ${st.pendientes ? `<div class="banner" style="margin:16px 0 0"><span class="spin"></span>Procesando ${plural(st.pendientes, 'archivo nuevo', 'archivos nuevos')}…</div>` : ''}
        </div>

        ${equipoHtml(cfg, net)}

        <div class="section-title">Sincronizar desde el celular</div>
        <div class="card pad">
            ${sc ? `
            <p class="hint" style="margin-bottom:12px">Con <b>Syncthing</b> una carpeta del celular queda copiada en el portarretrato, en casa o afuera. Cada subcarpeta es un álbum. Borrar una foto en el celular no la borra del portarretrato.</p>
            <ol class="steps">
                <li><span>Instalá <b>Syncthing</b> en el celular (Android: “Syncthing-Fork”).</span></li>
                <li><span>En el celular: <b>Dispositivos → +</b> y escaneá este código (o pegá el ID).</span></li>
                <li><span>Aparecerá acá abajo un pedido: tocá <b>Aceptar</b>. Después, en el celular aceptá la carpeta <b>Fotos</b> y elegí qué carpeta del teléfono sincronizar. En <b>Tipo de carpeta</b> elegí <b>Solo enviar</b>: así el celular sube las fotos nuevas pero no baja las del portarretrato.</span></li>
            </ol>
            <div class="qr-box" style="margin-top:16px">${qrSvg(sc.id)}</div>
            <div class="mono" id="sc-id">${esc(sc.id)}</div>
            <button class="btn btn-block" id="sc-copy" style="margin-top:10px">${ic('copy')} Copiar ID</button>
            ${sc.pendientes.map(d => `<div class="banner" style="margin:18px 0 0;justify-content:space-between"><span>${esc(d.nombre || 'Un celular')} quiere sincronizar</span><button class="btn btn-sm btn-primary" data-accept="${esc(d.id)}" data-name="${esc(d.nombre || 'Celular')}">Aceptar</button></div>`).join('')}
            <div class="section-title" style="margin:20px 0 4px">Celulares emparejados</div>
            ${sc.dispositivos.length ? sc.dispositivos.map(d => `<div class="dev"><span class="dot ${d.conectado ? 'on' : ''}"></span>${esc(d.nombre)}<span class="hint" style="margin-left:auto">${d.conectado ? 'Conectado' : 'Sin conexión'}</span><button class="icon-btn" style="width:34px;height:34px;color:var(--danger)" data-unpair="${esc(d.id)}" aria-label="Quitar">${ic('x')}</button></div>`).join('') : '<p class="hint">Ninguno todavía.</p>'}`
            : `<p class="hint">Syncthing no responde. Si acaba de encenderse, esperá un minuto y volvé a entrar.</p>`}
        </div>

        <div class="section-title">Entrar desde el celular</div>
        <div class="card pad">
            <p class="hint" style="margin-bottom:12px">Conectado al mismo WiFi, abrí esta dirección (o escaneá el código).</p>
            <div class="qr-box">${qrSvg(url)}</div>
            <div class="mono" style="text-align:center">${esc(url)}</div>
            ${ipUrl ? `<p class="hint" style="text-align:center;margin-top:8px">Alternativa: ${esc(ipUrl)}</p>` : ''}
        </div>

        <div class="section-title">Instalar como app</div>
        <div class="card pad">
            ${location.protocol === 'https:' ? `
            <p class="hint">Estás en una conexión segura. En el menú <b>⋮</b> del navegador tocá <b>Instalar app</b> y queda con su ícono, sin barra del navegador.</p>`
            : `
            <p class="hint" style="margin-bottom:12px">Para que el celular la instale como una app (con ícono y a pantalla completa) hace falta una conexión segura. Se configura una sola vez:</p>
            <ol class="steps">
                <li><span>Descargá el certificado con el botón de abajo.</span></li>
                <li><span>Instalalo: <b>Ajustes → Seguridad → Cifrado y credenciales → Instalar un certificado → Certificado de CA</b> y elegí el archivo descargado.</span></li>
                <li><span>Abrí la versión segura (botón de abajo) y en el menú <b>⋮</b> tocá <b>Instalar app</b>.</span></li>
            </ol>
            <a class="btn btn-block" style="margin-top:14px" href="ca.crt" download="aura-portarretrato.crt">${ic('upload')} Descargar certificado</a>
            <a class="btn btn-block btn-primary" style="margin-top:10px" href="https://${esc(st.host)}.local/gestion/">${ic('link')} Abrir versión segura</a>`}
        </div>

        <div class="section-title">Mantenimiento</div>
        <div class="card">
            <div class="row"><div class="lbl"><b>Buscar archivos nuevos</b><small>Normalmente es automático, cada minuto</small></div><button class="btn btn-sm" id="sys-import">${ic('refresh')} Ahora</button></div>
            <div class="row"><div class="lbl"><b>Abrir el Visor</b><small>Lo que muestra el portarretrato</small></div><a class="btn btn-sm" href="../" target="_blank">${ic('monitor')} Abrir</a></div>
            <div class="row"><div class="lbl"><b>Cerrar sesión</b></div><a class="btn btn-sm" href="logout.php">${ic('logout')} Salir</a></div>
        </div>`;

    equipoBind(token);
    const copy = $('#sc-copy');
    if (copy) copy.onclick = async () => {
        try { await navigator.clipboard.writeText(sc.id); }
        catch (e) { const r = document.createRange(); r.selectNodeContents($('#sc-id')); const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r); document.execCommand('copy'); }
        toast('ID copiado', 'ok');
    };
    $$('[data-accept]').forEach(b => b.onclick = async () => {
        try { await api('syncthing_accept', { body: { id: b.dataset.accept, nombre: b.dataset.name } }); toast('Celular aceptado', 'ok'); viewSistema(token); } catch (e) { fail(e); }
    });
    $$('[data-unpair]').forEach(b => b.onclick = async () => {
        if (!await confirmSheet({ title: '¿Quitar este celular?', text: 'Deja de sincronizar. Las fotos ya copiadas se quedan.', ok: 'Quitar' })) return;
        try { await api('syncthing_remove', { body: { id: b.dataset.unpair } }); viewSistema(token); } catch (e) { fail(e); }
    });
    $('#sys-import').onclick = async () => { try { await api('run_import'); toast('Buscando…', 'ok'); setTimeout(() => alive(token) && viewSistema(token), 3000); } catch (e) { fail(e); } };
}

// ─── AYUDA ───────────────────────────────────────────────────────────────────
function viewAyuda() {
    const sec = (title, icon, body, open) => `<details class="help" ${open ? 'open' : ''}><summary>${ic(icon)}<span>${title}</span></summary><div class="help-body">${body}</div></details>`;
    main.innerHTML = `
        <div class="page-head"><h1>Ayuda</h1></div>
        <p class="page-sub">Todo lo que necesitás saber para usar tu portarretrato. Tocá cada tema para abrirlo.</p>

        ${sec('Lo básico: qué hace', 'monitor', `
            <p>El portarretrato muestra tus fotos y videos una tras otra, con la hora y el clima abajo. <b>No hace falta tocar nada</b>: se prende solo y arranca solo.</p>
            <p>Tenés <b>dos lugares</b> para manejarlo: la pantalla del portarretrato (el <b>Visor</b>) y esta página, que se llama <b>Gestión</b> y se abre desde el celular.</p>`, true)}

        ${sec('Los 3 botones (o tocar la pantalla)', 'zap', `
            <ol class="steps">
                <li><span><b>Botón 1 — Pase Rápido.</b> Muestra las imágenes sorpresa del día (cumpleaños, recuerdos, avisos). Al terminar vuelve a las fotos. Tocar la pantalla hace lo mismo.</span></li>
                <li><span><b>Botón 2 — Sonido.</b> Si está pasando un video, activa o apaga su sonido. Los videos arrancan en silencio.</span></li>
                <li><span><b>Botón 3 — Mensajes.</b> Abre las notas de la familia. Para cerrarlas, tocá la pantalla o la X.</span></li>
            </ol>
            <p class="hint">Si no tenés botones, el teclado también sirve: teclas <b>1</b>, <b>2</b> y <b>3</b>.</p>`)}

        ${sec('Agregar fotos y videos', 'upload', `
            <p><b>Desde el celular, a mano:</b></p>
            <ol class="steps">
                <li><span>Entrá a <b>Álbumes</b> y elegí uno (o creá uno con <b>Nuevo</b>).</span></li>
                <li><span>Tocá el botón <b>+</b> y elegí las fotos o videos del celular.</span></li>
                <li><span>Esperá a que cada una diga <b>Listo</b> y tocá <b>Listo</b>. Aparecen en el portarretrato en un minuto.</span></li>
            </ol>
            <p><b>Automático:</b> en <b>Sistema → Sincronizar desde el celular</b> podés enlazar una carpeta del teléfono: las fotos nuevas viajan solas, sin abrir nada.</p>
            <p class="hint">Formatos: JPG, PNG, HEIC (iPhone) y videos MP4. Los videos muy largos conviene recortarlos.</p>`)}

        ${sec('Álbumes: qué se ve en pantalla', 'folder', `
            <p>Un álbum es una carpeta de fotos. El portarretrato muestra <b>un álbum a la vez</b>.</p>
            <ul class="help-list">
                <li>Para cambiar cuál se ve: abrí el menú <b>⋯</b> del álbum → <b>Mostrar en pantalla</b>.</li>
                <li>Para borrar fotos: <b>Seleccionar</b>, marcá las que sobran y tocá el tacho.</li>
                <li>Para enderezar una foto de costado, usá <b>Rotar</b>.</li>
                <li><b>Ajustes del álbum</b>: cuántos segundos dura cada foto y la animación, solo para ese álbum.</li>
            </ul>
            <p class="hint">Las fotos se muestran en orden aleatorio, sin repetir hasta haber pasado todas.</p>`)}

        ${sec('Pantalla: reloj, clima y horario', 'sliders', `
            <ul class="help-list">
                <li><b>Presentación:</b> segundos por foto y tipo de animación.</li>
                <li><b>Reloj y clima:</b> elegí estilo, tamaño y tu ciudad. Si se corta internet, el clima muestra el último dato con un aviso.</li>
                <li><b>Modo noche:</b> baja el brillo en el horario que elijas.</li>
                <li><b>Horario de encendido:</b> fuera de horario se muestra una imagen de reposo (o queda negra), así no queda prendida toda la noche.</li>
            </ul>
            <p>Con <b>Vista previa</b> ves cómo queda sin ir hasta el portarretrato.</p>`)}

        ${sec('Pase Rápido', 'zap', `
            <p>Son imágenes sorpresa con día y hora: por ejemplo, la tarjeta de cumpleaños de la abuela el sábado. Antes de esa hora se muestran las del día; después, las del día siguiente.</p>
            <p>Agregalas en <b>Pase Rápido → Agregar</b> y elegí el día y la hora de cada una.</p>`)}

        ${sec('Mensajes familiares', 'message', `
            <p>Escribí una nota en <b>Mensajes</b> y aparece en el portarretrato con un aviso titilando. Se lee con el botón 3 o tocando el ícono de nota.</p>
            <p>También hay una página aparte, <b>${esc(location.origin)}/hija/</b>, para que un familiar lea y conteste desde su celular sin entrar a Gestión. Abrila en su teléfono y agregala a la pantalla de inicio.</p>`)}

        ${sec('Entrar desde el celular', 'phone', `
            <p>El celular tiene que estar en <b>el mismo WiFi</b> que el portarretrato. Abrí <b>${esc(location.origin)}/gestion/</b> o escaneá el código de <b>Sistema → Entrar desde el celular</b>.</p>
            <p>La clave es la que te dejó quien lo instaló. Podés instalarlo como app: <b>Sistema → Instalar como app</b>.</p>`)}

        ${sec('Algo no anda', 'refresh', `
            <ul class="help-list">
                <li><b>La pantalla está negra o con la imagen de reposo:</b> puede ser el horario de encendido (<b>Pantalla → Horario</b>).</li>
                <li><b>No aparecen las fotos nuevas:</b> andá a <b>Sistema → Buscar archivos nuevos → Ahora</b>. Si subiste muchas, tarda unos minutos.</li>
                <li><b>Se ve “Sin conexión” en rojo:</b> revisá el WiFi o el router. Las fotos siguen pasando; solo se frena el clima.</li>
                <li><b>No puedo entrar desde el celular:</b> comprobá que esté en el mismo WiFi y probá con la dirección numérica que figura en <b>Sistema</b>.</li>
                <li><b>Nada responde:</b> desenchufá el portarretrato 10 segundos y volvé a enchufarlo. Tarda 1–2 minutos en arrancar solo.</li>
            </ul>
            <p class="hint">Si está subiendo fotos, esperá a que termine antes de desenchufarlo.</p>`)}`;
}

// ─── CONFIGURACIÓN DEL EQUIPO (asistente de primer uso y Sistema) ────────────
const TZ_LIST = ['America/Argentina/Buenos_Aires', 'America/Montevideo', 'America/Santiago', 'America/Asuncion', 'America/La_Paz', 'America/Lima', 'America/Bogota', 'America/Guayaquil', 'America/Caracas', 'America/Mexico_City', 'America/Panama', 'America/Costa_Rica', 'America/Havana', 'America/Sao_Paulo', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/Madrid', 'Europe/Lisbon', 'Europe/Paris', 'Europe/Rome', 'Europe/London'];
function tzOptions(cur) {
    const list = TZ_LIST.includes(cur) || !cur ? TZ_LIST : [cur, ...TZ_LIST];
    return list.map(z => `<option value="${esc(z)}" ${z === cur ? 'selected' : ''}>${esc(z.replace(/_/g, ' '))}</option>`).join('');
}
const ROT_LABELS = { left: 'Vertical (girada a la izquierda)', right: 'Vertical (girada a la derecha)', normal: 'Horizontal', inverted: 'Horizontal invertida' };
function rotOptions(cur) { return Object.entries(ROT_LABELS).map(([v, l]) => `<option value="${v}" ${v === cur ? 'selected' : ''}>${l}</option>`).join(''); }

async function geocodeCity(name) {
    const q = name.split(',')[0].trim();
    const r = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(q)}&count=1&language=es&format=json`);
    const j = await r.json();
    if (!j.results || !j.results.length) return null;
    const l = j.results[0];
    return { label: `${l.name}, ${l.admin1 || l.country || ''}`.replace(/, $/, ''), lat: String(l.latitude), lon: String(l.longitude) };
}

// Primera vez: una sola pantalla con lo imprescindible
async function viewBienvenida(token) {
    const s = await api('get_settings');
    if (!alive(token)) return;
    const browserTz = (Intl.DateTimeFormat().resolvedOptions().timeZone) || 'America/Argentina/Buenos_Aires';
    let city = null;
    main.innerHTML = `
        <div class="page-head"><h1>¡Bienvenido!</h1></div>
        <p class="page-sub">Cuatro datos y queda listo. Todo se puede cambiar después en <b>Sistema</b> y <b>Pantalla</b>.</p>
        <div class="section-title">1 · Tu clave</div>
        <div class="card pad">
            <p class="hint" style="margin-bottom:10px">Elegí una clave que recuerdes (mínimo 6 caracteres). Reemplaza a la clave inicial de la pantalla.</p>
            <input type="password" id="b-p1" placeholder="Clave nueva" autocomplete="new-password" style="margin-bottom:10px">
            <input type="password" id="b-p2" placeholder="Repetí la clave" autocomplete="new-password">
        </div>
        <div class="section-title">2 · Tu ciudad (para el clima)</div>
        <div class="card pad">
            <div class="inline-form"><input type="text" id="b-city" placeholder="Ej: Rosario, Argentina"><button class="btn btn-primary" id="b-find">${ic('search')}</button></div>
            <p class="hint" id="b-found" style="margin-top:8px"></p>
        </div>
        <div class="section-title">3 · Mensajes desde el celular</div>
        <div class="card pad">
            <p class="hint" style="margin-bottom:10px">Si alguien de la familia va a escribir mensajes al portarretrato desde su propio celular, ¿cómo se llama? (opcional)</p>
            <input type="text" id="b-fam" maxlength="40" placeholder="Ej: Sofía" value="${esc(s.familiar_nombre || '')}">
        </div>
        <div class="section-title">4 · Pantalla</div>
        <div class="card">
            <div class="row"><div class="lbl"><b>Orientación</b></div><select id="b-rot">${rotOptions(s.rotate || 'left')}</select></div>
            <div class="row"><div class="lbl"><b>Zona horaria</b></div><select id="b-tz">${tzOptions(browserTz)}</select></div>
        </div>
        <div class="banner" id="b-err" hidden style="background:var(--danger);color:#fff"></div>
        <button class="btn btn-primary btn-block" id="b-go" style="margin-top:8px">Listo, empezar</button>`;

    const find = async () => {
        const name = $('#b-city').value.trim(); if (!name) return;
        $('#b-find').disabled = true;
        try {
            city = await geocodeCity(name);
            $('#b-found').textContent = city ? `✓ ${city.label}` : 'No encontré esa ciudad. Probá con otro nombre.';
            if (city) $('#b-city').value = city.label;
        } catch (e) { $('#b-found').textContent = 'No se pudo buscar (¿hay internet?).'; }
        $('#b-find').disabled = false;
    };
    $('#b-find').onclick = find;
    $('#b-city').onkeydown = e => { if (e.key === 'Enter') find(); };
    $('#b-go').onclick = async () => {
        const err = m => { const b = $('#b-err'); b.textContent = m; b.hidden = false; b.scrollIntoView({ block: 'center' }); };
        const p1 = $('#b-p1').value;
        if (p1.length < 6) return err('La clave debe tener al menos 6 caracteres.');
        if (p1 !== $('#b-p2').value) return err('Las dos claves no coinciden.');
        if ($('#b-city').value.trim() && !city) await find();
        $('#b-go').disabled = true;
        try {
            await api('change_password', { body: { nueva: p1 } });
            const set = { familiar_nombre: $('#b-fam').value.trim() };
            if (city) Object.assign(set, { weather_city: city.label, weather_lat: city.lat, weather_lon: city.lon });
            await api('update_settings', { body: set });
            await api('sys_action', { body: { accion: 'zona', valor: $('#b-tz').value } });
            await api('sys_action', { body: { accion: 'rotar', valor: $('#b-rot').value } });
            await api('setup_done', { body: {} });
            toast('¡Todo listo!', 'ok');
            location.hash = '#/ayuda';
        } catch (e) { $('#b-go').disabled = false; err(e.message || 'No se pudo guardar'); }
    };
}

// WiFi: elegir otra red (misma lógica que la página /conectar/ del primer arranque)
function wifiSheet(onDone) {
    openSheet(`<h3>Cambiar de WiFi</h3><p class="sub">Elegí la red y escribí su clave. El portarretrato puede tardar un minuto en reconectarse.</p>
        <div id="w-list"><p class="hint">Buscando redes…</p></div><div id="w-form"></div>
        <div class="btns"><button class="btn" data-no>Cerrar</button></div>`, (sh, close) => {
        $('[data-no]', sh).onclick = close;
        (async () => {
            let nets = [];
            try { const j = await api('wifi_scan'); nets = Array.isArray(j) ? j : []; } catch (e) {}
            $('#w-list', sh).innerHTML = (nets.length ? `<div class="card">${nets.map((n, i) => `<div class="row" data-i="${i}" style="cursor:pointer"><div class="lbl"><b>${esc(n.ssid)}</b><small>${n.clave ? 'Con clave' : 'Abierta'} · señal ${n.senal}%</small></div></div>`).join('')}</div>` : '<p class="hint">No se ven redes.</p>') + '<button class="btn btn-block" id="w-manual" style="margin-top:6px">Mi red no aparece</button>';
            const form = (ssid, needPass, manual) => {
                $('#w-form', sh).innerHTML = `${manual ? '<input type="text" id="w-ssid" placeholder="Nombre del WiFi" style="margin:12px 0 8px" autocapitalize="none">' : `<p style="margin:14px 0 8px"><b>${esc(ssid)}</b></p>`}
                    ${needPass ? '<input type="password" id="w-pw" placeholder="Clave del WiFi" autocomplete="off" autocapitalize="none">' : ''}
                    <button class="btn btn-primary btn-block" id="w-go" style="margin-top:12px">Conectar</button>`;
                $('#w-go', sh).onclick = async () => {
                    const name = manual ? $('#w-ssid', sh).value.trim() : ssid, clave = $('#w-pw', sh) ? $('#w-pw', sh).value : '';
                    if (!name) return;
                    try {
                        const j = await api('wifi_connect', { body: { ssid: name, clave } });
                        if (j.error) throw new Error(j.error);
                        close(); toast('Conectando… mirá la pantalla del portarretrato', 'ok'); onDone && onDone();
                    } catch (e) { fail(e); }
                };
            };
            $$('[data-i]', sh).forEach(r => r.onclick = () => { const n = nets[+r.dataset.i]; form(n.ssid, n.clave); });
            $('#w-manual', sh).onclick = () => form('', true, true);
        })();
    });
}

function passwordSheet() {
    openSheet(`<h3>Cambiar clave</h3><p class="sub">Es la clave con la que entrás a Gestión.</p>
        <input type="password" id="cp-a" placeholder="Clave actual" autocomplete="current-password" style="margin-bottom:10px">
        <input type="password" id="cp-1" placeholder="Clave nueva (mínimo 6)" autocomplete="new-password" style="margin-bottom:10px">
        <input type="password" id="cp-2" placeholder="Repetí la clave nueva" autocomplete="new-password">
        <div class="btns"><button class="btn" data-no>Cancelar</button><button class="btn btn-primary" data-ok>Guardar</button></div>`, (sh, close) => {
        $('[data-no]', sh).onclick = close;
        $('[data-ok]', sh).onclick = async () => {
            if ($('#cp-1', sh).value !== $('#cp-2', sh).value) return toast('Las claves nuevas no coinciden', 'err');
            try { await api('change_password', { body: { actual: $('#cp-a', sh).value, nueva: $('#cp-1', sh).value } }); close(); toast('Clave cambiada', 'ok'); } catch (e) { fail(e); }
        };
    });
}

function equipoHtml(s, net) {
    const redTxt = net.error ? 'No disponible' : net.ssid ? `Conectado a “${esc(net.ssid)}”` : net.cable ? 'Conectado por cable' : 'Sin conexión';
    return `
        <div class="section-title">Mi portarretrato</div>
        <div class="card">
            <div class="row"><div class="lbl"><b>WiFi</b><small>${redTxt}</small></div>${net.hay_wifi === false ? '' : `<button class="btn btn-sm" id="eq-wifi">${ic('refresh')} Cambiar</button>`}</div>
            <div class="row"><div class="lbl"><b>Orientación de la pantalla</b></div><select id="eq-rot">${rotOptions(s.rotate || 'left')}</select></div>
            <div class="row"><div class="lbl"><b>Zona horaria</b></div><select id="eq-tz">${tzOptions(s.timezone || 'America/Argentina/Buenos_Aires')}</select></div>
            <div class="row"><div class="lbl"><b>Quién escribe desde su celular</b><small>Nombre en sus mensajes (página /hija/)</small></div><input type="text" id="eq-fam" maxlength="40" value="${esc(s.familiar_nombre || '')}" placeholder="Hija" style="width:130px"></div>
            <div class="row"><div class="lbl"><b>Clave de Gestión</b></div><button class="btn btn-sm" id="eq-pass">Cambiar</button></div>
            <div class="row"><div class="lbl"><b>Volver a abrir el Visor</b><small>Si la pantalla se ve mal o quedó trabada</small></div><button class="btn btn-sm" id="eq-kiosk">Reabrir</button></div>
            <div class="row"><div class="lbl"><b>Reiniciar</b><small>Tarda 1–2 minutos en volver</small></div><button class="btn btn-sm" id="eq-reboot">Reiniciar</button></div>
            <div class="row"><div class="lbl"><b>Apagar</b><small>Para prenderlo hay que usar el botón del equipo</small></div><button class="btn btn-sm btn-danger" id="eq-off">Apagar</button></div>
        </div>`;
}

function equipoBind(token) {
    const act = async (accion, valor, msg) => { try { const j = await api('sys_action', { body: { accion, valor } }); if (j && j.error) throw new Error(j.error); toast(msg, 'ok'); } catch (e) { fail(e); } };
    const w = $('#eq-wifi'); if (w) w.onclick = () => wifiSheet(() => setTimeout(() => alive(token) && viewSistema(token), 4000));
    $('#eq-rot').onchange = e => act('rotar', e.target.value, 'Pantalla girada');
    $('#eq-tz').onchange = e => act('zona', e.target.value, 'Zona horaria guardada');
    $('#eq-fam').onchange = async e => { try { await api('update_settings', { body: { familiar_nombre: e.target.value.trim() } }); toast('Guardado', 'ok'); } catch (x) { fail(x); } };
    $('#eq-pass').onclick = passwordSheet;
    $('#eq-kiosk').onclick = () => act('reabrir_visor', '', 'Reabriendo el Visor…');
    $('#eq-reboot').onclick = async () => { if (await confirmSheet({ title: '¿Reiniciar el portarretrato?', text: 'Se apaga la pantalla y vuelve en 1–2 minutos.', ok: 'Reiniciar', danger: false })) act('reiniciar', '', 'Reiniciando…'); };
    $('#eq-off').onclick = async () => { if (await confirmSheet({ title: '¿Apagar el portarretrato?', text: 'Para volver a prenderlo hay que apretar el botón de encendido del equipo.', ok: 'Apagar' })) act('apagar', '', 'Apagando…'); };
}

// Para poder instalarla como app (solo con conexión segura); no guarda nada en caché.
if ('serviceWorker' in navigator && location.protocol === 'https:') navigator.serviceWorker.register('sw.js').catch(() => {});

route();
})();
