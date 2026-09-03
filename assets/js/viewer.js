// Visor - Porta Retrato v1.1
let playlist = [];
let currentIndex = 0;
let currentSettings = { duration: 10, animation: 'fade', clock_style: 'classic', clock_size: 'standard', date_format: 'full', quick_show_duration: 8, recordatorios_duration: 20 };
let weatherConfig = { city: '', lat: '', lon: '', days: 3, hours: 6, icons: 'aura-glow' };
let weatherData = null;
let weatherCarouselIndex = 0;
let weatherCarouselItems = [];
let entryOverlayHidden = false;

// Pase Rápido
let quickShowPlaylist = [];
let quickShowIndex = 0;
let quickShowActive = false;
let currentTimer = null;
let quickShowCache = null;
let quickShowCacheTs = 0;
const QS_CACHE_TTL = 15 * 60 * 1000;

// Modo sync — el Visor descarga cada archivo una sola vez (Service Worker) y
// después reproduce desde el cache del navegador, incluso sin conexión.
let lastManifestHash = null;
const SYNC_INTERVAL = 10 * 60 * 1000; // cada 10 min pide el manifest (unos KB; 304 si nada cambió)

// Horario de encendido del Visor: fuera de él, reproducción frenada + imagen de reposo.
let visorOff = false;
let restOverlayEl = null;

// Audio por video — solo el video que se está mostrando puede destaparse
let currentVideoEl = null;

// Recordatorios familiares
let recordatoriosCache = [];
let recordatoriosUnseenCount = 0;
let recordatoriosCloseTimer = null;

const display = document.getElementById('media-display');

document.addEventListener('DOMContentLoaded', async () => {
    // Service Worker: cachea la media para que el hosting solo la envíe una vez.
    // Si no se registra (p.ej. sitio sin HTTPS) el Visor sigue funcionando igual,
    // solo que sin el ahorro de ancho de banda.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(e => console.warn('SW no registrado', e));
    }
    // Almacenamiento persistente: sin esto, si al equipo le queda poco disco
    // Chrome puede EXPULSAR todo el cache de media bajo presión → el Visor
    // vuelve a descargar el álbum entero. Con persist() concedido, Chrome no lo
    // borra solo (aunque una foto nueva puede fallar si el disco está realmente
    // lleno). En un kiosco suele concederse sin preguntar.
    if (navigator.storage && navigator.storage.persist) {
        navigator.storage.persisted().then(p => {
            if (!p) navigator.storage.persist().then(g => console.log('storage.persist:', g));
        }).catch(() => {});
    }
    restOverlayEl = document.getElementById('rest-overlay');

    await syncFromManifest(true);   // primera carga: aplica settings y arranca el slideshow
    loadWeatherData();
    updateClock();
    checkVisorSchedule();
    checkNightMode();

    setInterval(syncFromManifest, SYNC_INTERVAL);
    setInterval(reportDeviceStats, 5 * 60 * 1000);   // métricas para "Diagnóstico" de Gestión
    setInterval(loadRecordatorios, 60000);   // los mensajes familiares siguen casi en vivo
    setInterval(loadWeatherData, 1800000);
    setInterval(rotateWeather, 8000);
    setInterval(updateClock, 1000);
    setInterval(checkNightMode, 60000);
    setInterval(checkVisorSchedule, 30000);

    window.addEventListener('offline', () => setConnectionState(false));
    window.addEventListener('online', () => {
        setConnectionState(true);
        syncFromManifest();
    });

    document.addEventListener('click', () => {
        if (!visorOff && !quickShowActive) startQuickShow();
    });

    // Botonera física (USB HID tipo teclado, p.ej. Arduino Pro Micro con
    // botones arcade): cada botón envía un keydown como si fuera un teclado
    // normal, sin drivers. Mapeo: 1=Pase Rápido, 2=destapar audio del video
    // actual, 3=ver mensajes familiares. No requiere hardware conectado para
    // funcionar — el mouse/touch normal sigue andando igual.
    document.addEventListener('keydown', (e) => {
        if (visorOff) return;
        if (e.key === '1') { if (!quickShowActive) startQuickShow(); }
        else if (e.key === '2') { toggleVideoSound(); }
        else if (e.key === '3') { openRecordatoriosOverlay(); }
    });
});

function setConnectionState(online) {
    const ind = document.getElementById('offline-indicator');
    if (!ind) return;
    if (online) {
        ind.className = 'connection-online';
        ind.innerHTML = '<i class="fas fa-wifi"></i><span>En línea</span>';
    } else {
        ind.className = 'connection-offline';
        ind.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Sin conexión';
    }
}

function updateClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const timeEl = document.getElementById('clock-time');
    if(timeEl) timeEl.innerText = `${hours}:${minutes}`;

    let dateStr = "";
    const dayName = now.toLocaleDateString('es-ES', { weekday: 'long' });
    const dayNum = now.getDate();
    const monthName = now.toLocaleDateString('es-ES', { month: 'long' });
    const monthNum = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    const dayShort = now.toLocaleDateString('es-ES', { weekday: 'short' }).toUpperCase();
    const monthShort = now.toLocaleDateString('es-ES', { month: 'short' }).toUpperCase();

    switch(currentSettings.date_format) {
        case 'standard': dateStr = `${dayNum} ${monthShort} ${year}`; break;
        case 'numeric': dateStr = `${dayNum} / ${monthNum} / ${year}`; break;
        case 'short': dateStr = `${dayShort} ${dayNum} ${monthShort}`; break;
        case 'minimal': dateStr = `${dayShort} ${dayNum}`; break;
        default: dateStr = `${dayShort} ${dayNum} ${monthShort} ${year}`;
    }

    const dateEl = document.getElementById('clock-date');
    if(dateEl) dateEl.innerText = dateStr;

    const widget = document.getElementById('clock-widget');
    if(widget) widget.className = `clock-glass style-${currentSettings.clock_style} size-${currentSettings.clock_size}`;
}

// Aplica el bloque de settings que viene dentro del manifest (key-value).
// No toca la red.
function applySettings(settings) {
    if (!settings || settings.error) return;

    currentSettings.duration = parseInt(settings.slide_duration) || 10;
    currentSettings.animation = settings.slide_animation || 'fade';
    currentSettings.clock_style = settings.clock_style || 'classic';
    currentSettings.clock_size = settings.clock_size || 'standard';
    currentSettings.date_format = settings.date_format || 'full';
    currentSettings.night_mode_enabled = settings.night_mode_enabled || '0';
    currentSettings.night_start = settings.night_start || '23:00';
    currentSettings.night_end = settings.night_end || '07:00';
    currentSettings.quick_show_duration = parseInt(settings.quick_show_duration) || 8;
    currentSettings.recordatorios_duration = parseInt(settings.recordatorios_duration) || 20;

    // Horario de encendido del Visor + imagen de reposo
    currentSettings.visor_schedule_enabled = settings.visor_schedule_enabled || '0';
    currentSettings.visor_on = settings.visor_on || '07:00';
    currentSettings.visor_off = settings.visor_off || '23:00';
    currentSettings.visor_off_image = settings.visor_off_image || '';

    const prevLat = weatherConfig.weather_lat;
    const prevLon = weatherConfig.weather_lon;

    weatherConfig = {
        weather_city: settings.weather_city || 'Buenos Aires',
        weather_lat: settings.weather_lat || '-34.6037',
        weather_lon: settings.weather_lon || '-58.3816',
        weather_days: settings.weather_days || '3',
        weather_hours: settings.weather_hours || '6',
        weather_icons: settings.weather_icons || 'aura-glow',
        weather_size: settings.weather_size || 'standard',
        weather_forecast_size: settings.weather_forecast_size || 'standard'
    };

    preloadWeatherIcons(weatherConfig.weather_icons);

    if (weatherData) {
        if (prevLat !== weatherConfig.weather_lat || prevLon !== weatherConfig.weather_lon) {
            loadWeatherData();
        } else {
            updateWeatherUI();
        }
    }

    // Si cambió la config de horario, re-evaluar en el acto.
    if (restOverlayEl) checkVisorSchedule();
}

async function loadWeatherData() {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${weatherConfig.weather_lat}&longitude=${weatherConfig.weather_lon}&current=temperature_2m,weather_code,is_day&hourly=temperature_2m,weather_code,is_day&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto`;
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data && data.current) {
            weatherData = data;
            localStorage.setItem('pr_weather_data', JSON.stringify(data));
            localStorage.setItem('pr_weather_ts', Date.now());
            hideWeatherStale();
        } else {
            throw new Error('Respuesta inválida');
        }
    } catch (e) {
        console.warn("Clima sin conexión — cargando caché", e);
        const cached = localStorage.getItem('pr_weather_data');
        const ts = parseInt(localStorage.getItem('pr_weather_ts') || '0');
        if (cached) {
            weatherData = JSON.parse(cached);
            showWeatherStale(ts);
        }
    }
    if (weatherData) updateWeatherUI();
}

function showWeatherStale(ts) {
    const el = document.getElementById('weather-stale');
    if (!el) return;
    const mins = Math.round((Date.now() - ts) / 60000);
    const label = mins < 60 ? `hace ${mins} min` : `hace ${Math.round(mins / 60)} h`;
    el.textContent = label;
    el.style.display = 'block';
}

function hideWeatherStale() {
    const el = document.getElementById('weather-stale');
    if (el) el.style.display = 'none';
}

function updateWeatherUI() {
    const widget = document.getElementById('weather-widget');
    const nowTemp = document.getElementById('now-temp');
    const nowCity = document.getElementById('now-city');
    const nowIcon = document.getElementById('now-icon');
    if(!widget || !weatherData) return;

    const unified = document.getElementById('unified-widget');
    if (unified) unified.className = `unified-glass weather-size-${weatherConfig.weather_size} weather-forecast-${weatherConfig.weather_forecast_size}`;
    const currentT = Math.round(weatherData.current.temperature_2m);
    const todayMax = Math.round(weatherData.daily.temperature_2m_max[0]);
    const todayMin = Math.round(weatherData.daily.temperature_2m_min[0]);

    nowTemp.innerHTML = `<div style="display:flex; flex-direction:column; align-items:flex-start;"><span>${currentT}°</span><small style="font-size:0.7rem; opacity:0.8; margin-top:2px;">MÁX ${todayMax}° MÍN ${todayMin}°</small></div>`;
    nowCity.innerText = weatherConfig.weather_city.split(',')[0].trim();

    const iconStyle = weatherConfig.weather_icons;
    const imgStyle = getCustomFilter(iconStyle);
    const nowIsNight = parseInt(weatherData.current.is_day) === 0;
    nowIcon.innerHTML = `<img src="${getPremiumIconURL(weatherData.current.weather_code, iconStyle, nowIsNight)}" onerror="this.src='${getPremiumIconURL(weatherData.current.weather_code, 'aura-glow', nowIsNight)}'" style="width:58px; height:54px; ${imgStyle}">`;

    weatherCarouselItems = [];
    const _now = new Date();
    const _pad = n => String(n).padStart(2, '0');
    const _curStr = `${_now.getFullYear()}-${_pad(_now.getMonth()+1)}-${_pad(_now.getDate())}T${_pad(_now.getHours())}:00`;
    let _startIdx = weatherData.hourly.time.findIndex(t => t >= _curStr);
    if (_startIdx < 0) _startIdx = 0;
    _startIdx++; // la hora actual ya se muestra como temperatura principal

    let hoursHtml = '<div style="display:flex; gap:20px;">';
    const _maxH = parseInt(weatherConfig.weather_hours);
    for (let i = _startIdx; i < _startIdx + _maxH && i < weatherData.hourly.time.length; i++) {
        const time = new Date(weatherData.hourly.time[i]).getHours();
        const code = weatherData.hourly.weather_code[i];
        const hourIsNight = parseInt(weatherData.hourly.is_day[i]) === 0;
        hoursHtml += `<div class="weather-item"><img src="${getPremiumIconURL(code, iconStyle, hourIsNight)}" onerror="this.src='${getPremiumIconURL(code, 'aura-glow', hourIsNight)}'" class="wi-icon" style="${imgStyle}"><span>${Math.round(weatherData.hourly.temperature_2m[i])}°</span><small>${time}:00</small></div>`;
    }
    hoursHtml += '</div>';
    weatherCarouselItems.push(hoursHtml);

    const daysArr = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
    let daysHtml = '<div style="display:flex; gap:20px;">';
    for(let i=1; i<=parseInt(weatherConfig.weather_days); i++) {
        const date = new Date(weatherData.daily.time[i] + 'T12:00:00');
        const code = weatherData.daily.weather_code[i];
        const max = Math.round(weatherData.daily.temperature_2m_max[i]);
        const min = Math.round(weatherData.daily.temperature_2m_min[i]);
        daysHtml += `<div class="weather-item"><img src="${getPremiumIconURL(code, iconStyle)}" onerror="this.src='${getPremiumIconURL(code, 'aura-glow')}'" class="wi-icon" style="${imgStyle}"><span>${max}° <small class="wi-min">${min}°</small></span><small>${daysArr[date.getDay()]}</small></div>`;
    }
    daysHtml += '</div>';
    weatherCarouselItems.push(daysHtml);

    widget.style.display = 'flex';
    weatherCarouselIndex = 0;
    rotateWeather();
    updateWeatherAlertUI();
}

function rotateWeather() {
    if (!weatherCarouselItems || weatherCarouselItems.length === 0) return;
    const container = document.getElementById('weather-dynamic-section');
    if(container) container.innerHTML = weatherCarouselItems[weatherCarouselIndex];
    weatherCarouselIndex = (weatherCarouselIndex + 1) % weatherCarouselItems.length;
}

function getCustomFilter(style) {
    if (style === 'glassmorphism') return 'filter: opacity(0.8) blur(0.4px) drop-shadow(0 0 4px rgba(255,255,255,0.5));';
    if (style === 'vibrant-anim') return 'filter: sepia(1) saturate(3) hue-rotate(320deg) brightness(1.2) drop-shadow(0 0 5px rgba(255,215,0,0.5));';
    if (style === 'minimal-line') return 'filter: brightness(0) invert(1);';
    return 'filter: drop-shadow(0 8px 15px rgba(0,0,0,0.2));';
}

const WEATHER_ICON_NAMES = ['clear-day', 'clear-night', 'partly-cloudy-day', 'partly-cloudy-night', 'overcast', 'fog', 'drizzle', 'rain', 'snow', 'thunderstorms', 'cloudy'];
let preloadedIconStyle = null;

function iconFolderForStyle(styleName) {
    if (styleName === 'minimal-line') return 'line';
    if (styleName === 'neo-flat') return 'outline';
    if (styleName === 'vibrant-anim') return 'monochrome';
    return 'fill';
}

// Precarga en segundo plano los íconos del estilo activo para que, al llegar el
// código de clima que corresponda, el navegador ya lo tenga cacheado (evita el
// "tardan en cargar" cuando se pide por primera vez justo al mostrarse).
function preloadWeatherIcons(styleName) {
    if (preloadedIconStyle === styleName) return;
    preloadedIconStyle = styleName;
    const folder = iconFolderForStyle(styleName);
    WEATHER_ICON_NAMES.forEach(name => {
        new Image().src = `https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/${folder}/all/${name}.svg`;
    });
}

function getPremiumIconURL(code, styleName, isNight = false) {
    let name = 'clear-day';
    const c = parseInt(code);
    if (c === 0) name = isNight ? 'clear-night' : 'clear-day';
    else if (c === 1 || c === 2) name = isNight ? 'partly-cloudy-night' : 'partly-cloudy-day';
    else if (c === 3) name = 'overcast';
    else if (c >= 45 && c <= 48) name = 'fog';
    else if (c >= 51 && c <= 55) name = 'drizzle';
    else if (c >= 61 && c <= 65) name = 'rain';
    else if (c >= 71 && c <= 77) name = 'snow';
    else if (c >= 80 && c <= 82) name = 'rain';
    else if (c >= 95) name = 'thunderstorms';
    else name = 'cloudy';
    const folder = iconFolderForStyle(styleName);
    return `https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/${folder}/all/${name}.svg`;
}

// ─── ALERTA DE CLIMA SEVERO ───────────────────────────────────────────────────
// Ventana de anticipación: además del clima actual, mira las próximas N horas
// para avisar ANTES de que llegue el fenómeno, no solo cuando ya está pasando.
const SEVERE_WEATHER_LOOKAHEAD_HOURS = 5;
const WEATHER_ALERT_RANK = { amarilla: 1, naranja: 2, roja: 3 };
const HEAT_ALERT_THRESHOLD = 35; // °C
const FROST_ALERT_THRESHOLD = 0; // °C

// Clasifica un código WMO puntual en un nivel de alerta, o null si no es severo.
function classifyWeatherCode(code) {
    const c = parseInt(code);
    if (c === 96 || c === 99) return { level: 'roja', label: 'Tormenta con granizo' };
    if (c === 95) return { level: 'naranja', label: 'Tormenta eléctrica' };
    if (c === 65 || c === 82) return { level: 'naranja', label: 'Lluvia intensa' };
    if (c === 75 || c === 86) return { level: 'naranja', label: 'Nevada intensa' };
    if ([56, 57, 66, 67].includes(c)) return { level: 'amarilla', label: 'Lluvia helada' };
    return null;
}

// Revisa el clima actual + próximas SEVERE_WEATHER_LOOKAHEAD_HOURS horas + extremos
// del día, y devuelve la alerta más severa encontrada (o null si no hay ninguna).
function getWeatherAlert() {
    if (!weatherData || !weatherData.hourly) return null;
    let best = null;
    const consider = (alert, whenSuffix) => {
        if (!alert) return;
        if (!best || WEATHER_ALERT_RANK[alert.level] > WEATHER_ALERT_RANK[best.level]) {
            best = { level: alert.level, label: whenSuffix ? `${alert.label} ${whenSuffix}` : alert.label };
        }
    };

    consider(classifyWeatherCode(weatherData.current.weather_code));

    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const curStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:00`;
    let startIdx = weatherData.hourly.time.findIndex(t => t >= curStr);
    if (startIdx < 0) startIdx = 0;

    for (let i = startIdx; i < startIdx + SEVERE_WEATHER_LOOKAHEAD_HOURS && i < weatherData.hourly.time.length; i++) {
        const hoursAhead = i - startIdx;
        consider(classifyWeatherCode(weatherData.hourly.weather_code[i]), hoursAhead === 0 ? null : `en ${hoursAhead}h`);
    }

    if (weatherData.daily) {
        const tMax = weatherData.daily.temperature_2m_max[0];
        const tMin = weatherData.daily.temperature_2m_min[0];
        if (tMax >= HEAT_ALERT_THRESHOLD) consider({ level: 'naranja', label: `Calor extremo (${Math.round(tMax)}°)` });
        if (tMin <= FROST_ALERT_THRESHOLD) consider({ level: 'amarilla', label: 'Riesgo de helada' });
    }

    return best;
}

function updateWeatherAlertUI() {
    const el = document.getElementById('weather-alert');
    if (!el) return;
    const alert = getWeatherAlert();
    if (alert) {
        el.textContent = alert.label;
        el.className = `alert-${alert.level}`;
        el.style.display = 'inline-block';
    } else {
        el.style.display = 'none';
    }
}

// ─── MODO SYNC ───────────────────────────────────────────────────────────────
// Pide el manifest (un JSON chico: toda la config + la lista de media del álbum
// activo, cada archivo con su tamaño/mtime). Si algo cambió respecto de la
// última vez: aplica settings, actualiza recordatorios / pase rápido, cambia la
// playlist y precarga al cache SOLO los archivos nuevos. Sin conexión: no hace
// nada y el Visor sigue reproduciendo lo que ya está cacheado.
async function syncFromManifest(isFirst = false) {
    let manifest;
    try {
        const res = await fetch('backend/api.php?action=get_sync_manifest');
        if (res.status === 304) { setConnectionState(true); return; }
        manifest = await res.json();
        setConnectionState(true);
    } catch (e) {
        setConnectionState(false);
        return;
    }
    if (!manifest || manifest.error) return;
    if (!isFirst && manifest.version_hash && manifest.version_hash === lastManifestHash) return;
    lastManifestHash = manifest.version_hash || null;

    if (manifest.settings) applySettings(manifest.settings);

    if (Array.isArray(manifest.recordatorios)) {
        recordatoriosCache = manifest.recordatorios;
        recordatoriosUnseenCount = manifest.recordatorios.filter(r => !parseInt(r.visto)).length;
        updateMessagesBadge();
    }
    if (Array.isArray(manifest.quick_show)) {
        quickShowCache = manifest.quick_show;
        quickShowCacheTs = Date.now();
    }

    const media = Array.isArray(manifest.media) ? manifest.media : [];
    const mapped = media.map(m => ({
        id: m.id,
        ruta: m.ruta,
        tipo: m.tipo,
        duracion_img: m.duracion_img,
        album_duracion: m.album_duracion,
        animacion_tipo: m.animacion_tipo
    }));

    // El orden puede cambiar legítimamente entre syncs (foto agregada/borrada/
    // reordenada). Ubicamos por id la foto que se está mostrando para no
    // repetir/saltear al reemplazar la playlist.
    if (JSON.stringify(mapped) !== JSON.stringify(playlist)) {
        const wasEmpty = playlist.length === 0;
        const currentId = playlist[currentIndex] ? playlist[currentIndex].id : null;
        playlist = mapped;
        if (playlist.length === 0) {
            display.innerHTML = '<h2 class="no-content">No hay contenido activo</h2>';
        } else if (wasEmpty) {
            currentIndex = 0;
            if (!visorOff) showNext();
        } else {
            const idx = currentId !== null ? playlist.findIndex(p => p.id === currentId) : -1;
            currentIndex = idx >= 0 ? idx : 0;
        }
    }

    // Precarga + limpieza del cache en segundo plano (no bloquea el primer cuadro).
    const settings = manifest.settings || {};
    setTimeout(() => precacheAndPrune(media, settings), isFirst ? 4000 : 500);

    // Reporte de métricas al backend (pantalla "Diagnóstico" de Gestión). En la
    // primera carga se da tiempo a que arranque la precarga; después va con
    // throttle propio.
    setTimeout(() => reportDeviceStats(isFirst), isFirst ? 9000 : 1500);
}

// ─── MÉTRICAS DEL DISPOSITIVO ────────────────────────────────────────────────
// El Visor le cuenta al backend cómo está funcionando el modo sync en este
// equipo: si el Service Worker está activo, cuántos archivos tiene cacheados,
// cuánto espacio ocupan y cuántos bytes bajó realmente del hosting. Es
// telemetría: si algo falla, se ignora en silencio.
function getDeviceId() {
    let id = localStorage.getItem('pr_device_id');
    if (!id) {
        id = (self.crypto && crypto.randomUUID)
            ? crypto.randomUUID()
            : 'dev-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
        localStorage.setItem('pr_device_id', id);
    }
    return id;
}

// Nombre legible del portarretrato. Se fija abriendo el Visor una vez con
// ?name=Living (queda guardado); si no, va vacío y Gestión muestra el id corto.
function getDeviceName() {
    const q = new URLSearchParams(location.search).get('name');
    if (q) localStorage.setItem('pr_device_name', q.slice(0, 80));
    return localStorage.getItem('pr_device_name') || '';
}

function askSwStats(timeoutMs = 3000) {
    return new Promise((resolve) => {
        if (!navigator.serviceWorker || !navigator.serviceWorker.controller) return resolve(null);
        let done = false;
        const finish = (v) => { if (!done) { done = true; resolve(v); } };
        const ch = new MessageChannel();
        const to = setTimeout(() => finish(null), timeoutMs);
        ch.port1.onmessage = (e) => { clearTimeout(to); finish(e.data || null); };
        try {
            navigator.serviceWorker.controller.postMessage({ type: 'GET_STATS' }, [ch.port2]);
        } catch (e) { clearTimeout(to); finish(null); }
    });
}

let lastReportTs = 0;
async function reportDeviceStats(force = false) {
    const now = Date.now();
    if (!force && now - lastReportTs < 5 * 60 * 1000) return;
    lastReportTs = now;
    try {
        const swActive = !!(navigator.serviceWorker && navigator.serviceWorker.controller);
        let cacheBytes = 0, quotaBytes = 0, persistente = 0;
        if (navigator.storage && navigator.storage.estimate) {
            try {
                const est = await navigator.storage.estimate();
                cacheBytes = est.usage || 0;
                quotaBytes = est.quota || 0;
            } catch (e) { /* no soportado */ }
        }
        if (navigator.storage && navigator.storage.persisted) {
            try { persistente = (await navigator.storage.persisted()) ? 1 : 0; } catch (e) {}
        }
        const sw = await askSwStats();
        const payload = {
            device_id:         getDeviceId(),
            nombre:            getDeviceName(),
            sw_activo:         swActive ? 1 : 0,
            cache_bytes:       cacheBytes,
            quota_bytes:       quotaBytes,
            persistente:       persistente,
            cache_archivos:    sw ? sw.cacheEntries : 0,
            descarga_bytes:    sw ? sw.dlBytes : 0,
            descarga_archivos: sw ? sw.dlCount : 0,
            descarga_desde:    sw ? sw.since : 0,
            media_total:       playlist.length,
            version_hash:      lastManifestHash || '',
            online:            navigator.onLine ? 1 : 0,
            ua:                navigator.userAgent.slice(0, 255)
        };
        await fetch('backend/api.php?action=report_device_stats', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
    } catch (e) { /* telemetría: nunca rompe el Visor */ }
}

// Pide una vez cada archivo del álbum (+ la imagen de reposo). El Service
// Worker lo baja del hosting si falta y lo guarda; si ya está, no toca la red.
// Después le manda al SW la lista vigente para que borre lo que sobra.
let precacheRunning = false;
async function precacheAndPrune(media, settings) {
    if (precacheRunning) return;
    precacheRunning = true;
    try {
        const urls = media.map(m => m.ruta).filter(Boolean);
        if (settings.visor_off_image) urls.push(settings.visor_off_image);

        for (const u of urls) {
            try { await fetch(u, { cache: 'no-store' }); } catch (e) { /* seguirá offline */ }
        }

        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'PRUNE_MEDIA', keep: urls });
        }
    } finally {
        precacheRunning = false;
    }
}

function showNext() {
    if (visorOff) return;
    if (quickShowActive) return;
    if (playlist.length === 0) return;
    const item = playlist[currentIndex];
    const FADE = 700;
    const anim = item.animacion_tipo || currentSettings.animation || 'fade';

    function enterState(el) {
        el.style.opacity = '0';
        if (anim === 'slide') {
            el.style.transform = 'translateX(100%)';
            el.style.transition = `opacity ${FADE}ms ease, transform ${FADE}ms cubic-bezier(0.25,0.46,0.45,0.94)`;
        } else if (anim === 'zoom') {
            el.style.transform = 'scale(1.07)';
            el.style.transition = `opacity ${FADE}ms ease, transform ${FADE}ms ease`;
        } else {
            el.style.transition = `opacity ${FADE}ms ease`;
        }
    }

    function activeState(el) {
        el.style.opacity = '1';
        if (anim === 'slide') el.style.transform = 'translateX(0)';
        if (anim === 'zoom') el.style.transform = 'scale(1)';
    }

    function reveal(newEl, onVisible) {
        enterState(newEl);
        display.appendChild(newEl);
        void newEl.offsetHeight;
        activeState(newEl);
        setTimeout(() => {
            [...display.children].forEach(el => { if (el !== newEl) el.remove(); });
            if (onVisible) onVisible();
        }, FADE);
    }

    hideSoundButton();

    if (item.tipo === 'imagen') {
        const img = new Image();
        img.src = item.ruta;
        const imgDur = parseInt(item.duracion_img);
        const albumDur = parseInt(item.album_duracion);
        const duration = ((imgDur > 0 ? imgDur : albumDur > 0 ? albumDur : currentSettings.duration) || 10) * 1000;

        img.onerror = () => { clearTimeout(currentTimer); advanceIndex(); showNext(); };

        const show = () => {
            reveal(img, () => {
                hideEntryOverlay();
                preloadNext();
                currentTimer = setTimeout(() => { advanceIndex(); showNext(); }, duration);
            });
        };

        if (img.complete && img.naturalWidth > 0) show();
        else img.onload = show;

    } else if (item.tipo === 'video') {
        const video = document.createElement('video');
        video.src = item.ruta;
        video.preload = 'auto';
        video.muted = true;
        video.autoplay = true;
        currentVideoEl = video;
        video.onended = () => { hideSoundButton(); currentVideoEl = null; advanceIndex(); showNext(); };
        video.onerror = () => { hideSoundButton(); currentVideoEl = null; video.remove(); advanceIndex(); showNext(); };

        enterState(video);
        display.appendChild(video);

        let shown = false;
        const show = () => {
            // Nunca revelar sin un frame decodificado (readyState >= HAVE_CURRENT_DATA):
            // si se hace, el <video> pasa a opacity:1 mostrando negro y tapa la
            // diapositiva anterior que seguía visible debajo. Mejor esperar más.
            if (shown || video.readyState < 2) return;
            shown = true;
            clearInterval(readyPoll);
            hideEntryOverlay();
            preloadNext();
            showSoundButton();
            void video.offsetHeight;
            activeState(video);
            setTimeout(() => {
                [...display.children].forEach(el => { if (el !== video) el.remove(); });
            }, FADE);
        };

        // "loadeddata" ya trae el primer frame decodificado; "canplay" puede tardar más.
        // Cualquiera de los dos habilita mostrarlo sin pantalla negra de por medio.
        video.addEventListener('loadeddata', show, { once: true });
        video.addEventListener('canplay', show, { once: true });

        // Red de seguridad: sondea readyState (por si el evento no llega en algunos
        // navegadores) sin forzar nunca un reveal sin frame. Si tras 20s de espera
        // real (video grande / conexión lenta) sigue sin haber datos, se asume
        // atascado y se salta al siguiente ítem en vez de mostrar negro.
        let waited = 0;
        const readyPoll = setInterval(() => {
            if (shown) { clearInterval(readyPoll); return; }
            if (video.readyState >= 2) { show(); return; }
            waited += 500;
            if (waited >= 20000) {
                clearInterval(readyPoll);
                hideSoundButton();
                currentVideoEl = null;
                video.remove();
                advanceIndex();
                showNext();
            }
        }, 500);

    } else {
        advanceIndex();
        showNext();
    }
}

function advanceIndex() {
    currentIndex++;
    if (currentIndex >= playlist.length) { currentIndex = 0; }
}

function hideEntryOverlay() {
    if (entryOverlayHidden) return;
    entryOverlayHidden = true;
    const overlay = document.getElementById('entry-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 900);
    }
}

function preloadNext() {
    // Solo se precargan imágenes (livianas, ~cientos de KB tras la compresión WebP).
    // Los videos NO se precargan por adelantado: pueden pesar hasta 100MB y si el
    // Pase Rápido interrumpe el slideshow o la playlist cambia antes de mostrarse,
    // esos bytes ya se habrían descargado del hosting para nada. El video se pide
    // recién al mostrarse (ver showNext) — la espera se cubre dejando la diapositiva
    // anterior visible en pantalla (sin negro) hasta que haya frame decodificado.
    if (playlist.length <= 1) return;
    const next = playlist[(currentIndex + 1) % playlist.length];
    if (next && next.tipo === 'imagen') new Image().src = next.ruta;
}

function checkNightMode() {
    const enabled = currentSettings.night_mode_enabled === '1';
    const overlay = document.getElementById('night-overlay');
    if (!overlay) return;
    // En reposo manda la imagen de reposo; el overlay de noche no debe taparla.
    if (visorOff) { overlay.style.opacity = '0'; return; }
    if (!enabled) { overlay.style.opacity = '0'; return; }
    const now = new Date();
    const cur = now.getHours() * 60 + now.getMinutes();
    const [sh, sm] = (currentSettings.night_start || '23:00').split(':').map(Number);
    const [eh, em] = (currentSettings.night_end || '07:00').split(':').map(Number);
    const start = sh * 60 + sm;
    const end = eh * 60 + em;
    const isNight = start > end ? (cur >= start || cur < end) : (cur >= start && cur < end);
    overlay.style.opacity = isNight ? '1' : '0';
}

// ─── HORARIO DE ENCENDIDO DEL VISOR ──────────────────────────────────────────
// Fuera del horario configurado el Visor frena la reproducción (timers, video)
// y muestra la imagen de reposo a pantalla completa. Distinto del Modo Noche,
// que solo atenúa y sigue reproduciendo (y descargando) por detrás.

function isWithinOffWindow(now, onStr, offStr) {
    const cur = now.getHours() * 60 + now.getMinutes();
    const [onH, onM] = (onStr || '07:00').split(':').map(Number);
    const [offH, offM] = (offStr || '23:00').split(':').map(Number);
    const on = onH * 60 + onM;
    const off = offH * 60 + offM;
    if (on === off) return false;
    const encendido = on < off ? (cur >= on && cur < off) : (cur >= on || cur < off);
    return !encendido;
}

function checkVisorSchedule() {
    const enabled = currentSettings.visor_schedule_enabled === '1';
    const shouldRest = enabled && isWithinOffWindow(new Date(), currentSettings.visor_on, currentSettings.visor_off);
    if (shouldRest && !visorOff) enterRestMode();
    else if (!shouldRest && visorOff) exitRestMode();
}

function enterRestMode() {
    visorOff = true;
    clearTimeout(currentTimer);
    if (currentVideoEl) { try { currentVideoEl.pause(); } catch (e) {} }
    currentVideoEl = null;
    if (quickShowActive) endQuickShow();   // el showNext() interno sale solo por el guard visorOff
    closeRecordatoriosOverlay();
    hideSoundButton();
    hideEntryOverlay();   // si el Visor arrancó en horario de reposo, el overlay negro de inicio nunca se quitó
    display.innerHTML = '';

    const widget = document.getElementById('unified-widget');
    if (widget) { widget.style.transition = 'opacity 0.5s ease'; widget.style.opacity = '0'; }
    const msg = document.getElementById('messages-btn');
    if (msg) msg.style.display = 'none';

    if (restOverlayEl) {
        restOverlayEl.innerHTML = '';
        if (currentSettings.visor_off_image) {
            const img = document.createElement('img');
            img.src = currentSettings.visor_off_image;
            restOverlayEl.appendChild(img);
        }
        restOverlayEl.classList.add('active');
    }
}

function exitRestMode() {
    visorOff = false;
    if (restOverlayEl) { restOverlayEl.classList.remove('active'); restOverlayEl.innerHTML = ''; }
    const widget = document.getElementById('unified-widget');
    if (widget) widget.style.opacity = '1';
    updateMessagesBadge();
    checkNightMode();
    currentIndex = 0;
    showNext();
}

// ─── PASE RÁPIDO ─────────────────────────────────────────────────────────────

async function startQuickShow() {
    if (visorOff) return;
    if (quickShowActive) return;
    try {
        const now = Date.now();
        let data = quickShowCache;
        if (!data || (now - quickShowCacheTs) > QS_CACHE_TTL) {
            const res = await fetch('backend/api.php?action=get_quick_show_media');
            data = await res.json();
            if (!data || data.error) return;
            quickShowCache = data;
            quickShowCacheTs = now;
        }
        if (data.length === 0) return;

        const nowDate = new Date();
        const today = nowDate.getDay(); // 0=dom … 6=sáb
        const nowMinutes = nowDate.getHours() * 60 + nowDate.getMinutes();

        // Buscar el próximo día con imágenes (desde hoy hacia adelante hasta 7 días)
        let targetDay = null;
        for (let offset = 0; offset < 7; offset++) {
            const checkDay = (today + offset) % 7;
            const checkItems = data.filter(item => parseInt(item.dia_semana) === checkDay);
            if (checkItems.length === 0) continue;

            if (offset === 0) {
                // Para hoy, verificar si el horario ya pasó
                const [h, m] = (checkItems[0].horario || '08:00').split(':').map(Number);
                if (nowMinutes >= h * 60 + m) continue; // ya pasó → buscar siguiente día
            }
            targetDay = checkDay;
            break;
        }

        // Si todos los horarios de la semana pasaron, tomar el primer día disponible (vuelta al inicio)
        if (targetDay === null) {
            for (let offset = 0; offset < 7; offset++) {
                const checkDay = (today + offset) % 7;
                if (data.some(item => parseInt(item.dia_semana) === checkDay)) {
                    targetDay = checkDay;
                    break;
                }
            }
        }

        if (targetDay === null) return;

        const filtered = data.filter(item => parseInt(item.dia_semana) === targetDay);
        if (filtered.length === 0) return;

        quickShowPlaylist = filtered;
        quickShowIndex = 0;
        quickShowActive = true;
        clearTimeout(currentTimer);
        showQuickItem();
        hideWidgets();
    } catch(e) { console.error('Quick show error', e); }
}

function showQuickItem() {
    if (!quickShowActive) return;
    if (quickShowIndex >= quickShowPlaylist.length) {
        endQuickShow();
        return;
    }
    const item = quickShowPlaylist[quickShowIndex];
    const duration = (currentSettings.quick_show_duration || 8) * 1000;
    const FADE = 700;

    const img = new Image();
    img.src = item.ruta;
    img.style.cssText = `position:absolute;top:0;left:0;width:100%;height:100%;object-fit:contain;opacity:0;transition:opacity ${FADE}ms ease`;

    img.onerror = () => { quickShowIndex++; showQuickItem(); };

    const show = () => {
        display.appendChild(img);
        void img.offsetHeight;
        img.style.opacity = '1';
        setTimeout(() => {
            [...display.children].forEach(el => { if (el !== img) el.remove(); });
            quickShowIndex++;
            currentTimer = setTimeout(showQuickItem, duration);
        }, FADE);
    };

    if (img.complete && img.naturalWidth > 0) show();
    else img.onload = show;
}

function endQuickShow() {
    quickShowActive = false;
    quickShowPlaylist = [];
    quickShowIndex = 0;
    // quickShowCache se conserva — próximo click no toca la red
    showWidgets();
    showNext();
}

function hideWidgets() {
    const el = document.getElementById('unified-widget');
    if (el) { el.style.transition = 'opacity 0.5s ease'; el.style.opacity = '0'; el.style.pointerEvents = 'none'; }
    const msg = document.getElementById('messages-btn');
    if (msg) msg.style.display = 'none';
    hideSoundButton();
}

function showWidgets() {
    const el = document.getElementById('unified-widget');
    if (el) { el.style.opacity = '1'; el.style.pointerEvents = ''; }
    updateMessagesBadge();
}

// ─── AUDIO POR VIDEO ─────────────────────────────────────────────────────────
// Los videos siempre arrancan mudos (autoplay con sonido lo bloquean los
// navegadores sin gesto del usuario). Este botón permite destapar el audio
// del video que se está mostrando en este momento, nada más — el próximo
// video vuelve a arrancar mudo.

function showSoundButton() {
    const btn = document.getElementById('video-sound-btn');
    if (!btn || !currentVideoEl) return;
    btn.style.display = 'flex';
    updateSoundButtonIcon();
}

function hideSoundButton() {
    const btn = document.getElementById('video-sound-btn');
    if (btn) btn.style.display = 'none';
}

function updateSoundButtonIcon() {
    const btn = document.getElementById('video-sound-btn');
    if (!btn || !currentVideoEl) return;
    const muted = currentVideoEl.muted;
    btn.classList.toggle('unmuted', !muted);
    btn.innerHTML = `<i class="fas ${muted ? 'fa-volume-xmark' : 'fa-volume-high'}"></i>`;
}

function toggleVideoSound() {
    if (!currentVideoEl) return;
    currentVideoEl.muted = !currentVideoEl.muted;
    updateSoundButtonIcon();
}

// ─── RECORDATORIOS FAMILIARES ────────────────────────────────────────────────
// Aviso discreto (sin sonido) cuando alguien deja un mensaje desde Gestión:
// un badge con contador sobre el ícono de nota. Al abrir el panel se marcan
// como vistos para que el badge no quede pegado para siempre.

async function loadRecordatorios() {
    try {
        const res = await fetch('backend/api.php?action=get_recordatorios');
        if (res.status === 304) return;
        const data = await res.json();
        if (!data || data.error) return;
        recordatoriosCache = data;
        recordatoriosUnseenCount = data.filter(r => !parseInt(r.visto)).length;
        updateMessagesBadge();
    } catch (e) { console.error('Error recordatorios', e); }
}

function updateMessagesBadge() {
    const wrap = document.getElementById('messages-btn');
    const badge = document.getElementById('messages-badge');
    if (!wrap || !badge) return;
    if (quickShowActive) return; // hideWidgets() ya lo ocultó
    if (visorOff) { wrap.style.display = 'none'; return; }

    // Sin mensajes (ni siquiera viejos), el ícono no tiene nada que ofrecer.
    if (recordatoriosCache.length === 0) { wrap.style.display = 'none'; return; }

    wrap.style.display = 'flex';
    wrap.classList.toggle('has-unseen', recordatoriosUnseenCount > 0);
    if (recordatoriosUnseenCount > 0) {
        badge.style.display = 'flex';
        badge.textContent = recordatoriosUnseenCount > 9 ? '9+' : recordatoriosUnseenCount;
    } else {
        badge.style.display = 'none';
    }
}

function openRecordatoriosOverlay() {
    const list = document.getElementById('recordatorios-overlay-list');
    list.innerHTML = '';
    if (recordatoriosCache.length === 0) {
        list.innerHTML = '<div id="recordatorios-overlay-empty">No hay mensajes todavía</div>';
    } else {
        recordatoriosCache.forEach(item => {
            const div = document.createElement('div');
            div.className = 'rec-overlay-item';
            const fecha = new Date(item.fecha_creacion.replace(' ', 'T')).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            const autor = item.autor ? `${item.autor} · ` : '';
            div.innerHTML = `<div class="rec-o-msg"></div><div class="rec-o-meta">${autor}${fecha}</div>`;
            div.querySelector('.rec-o-msg').textContent = item.mensaje;
            list.appendChild(div);
        });
    }
    document.getElementById('recordatorios-overlay').style.display = 'flex';

    if (recordatoriosUnseenCount > 0) {
        recordatoriosUnseenCount = 0;
        updateMessagesBadge();
        fetch('backend/api.php?action=mark_recordatorios_vistos').catch(() => {});
    }

    // Se cierra solo — el Visor corre 24/7 sin nadie mirando, no puede quedar
    // un panel tapando el slideshow indefinidamente si alguien lo abre y se va.
    clearTimeout(recordatoriosCloseTimer);
    recordatoriosCloseTimer = setTimeout(closeRecordatoriosOverlay, (currentSettings.recordatorios_duration || 20) * 1000);
}

function closeRecordatoriosOverlay() {
    clearTimeout(recordatoriosCloseTimer);
    document.getElementById('recordatorios-overlay').style.display = 'none';
}
