// Visor - Porta Retrato v1.1
let playlist = [];
let currentIndex = 0;
let currentSettings = { duration: 10, animation: 'fade', clock_style: 'classic', clock_size: 'standard', date_format: 'full', quick_show_duration: 8 };
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

const display = document.getElementById('media-display');

document.addEventListener('DOMContentLoaded', async () => {
    loadPlaylist();
    await loadSettings();
    loadWeatherData();
    updateClock();
    checkNightMode();
    setInterval(loadPlaylist, 60000);
    setInterval(loadSettings, 60000);
    setInterval(loadWeatherData, 1800000);
    setInterval(rotateWeather, 8000);
    setInterval(updateClock, 1000);
    setInterval(checkNightMode, 60000);

    window.addEventListener('offline', () => setConnectionState(false));
    window.addEventListener('online', () => {
        setConnectionState(true);
        loadPlaylist();
    });

    document.addEventListener('click', () => {
        if (!quickShowActive) startQuickShow();
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
        case 'standard': dateStr = `${dayNum} de ${monthName}, ${year}`; break;
        case 'numeric': dateStr = `${dayNum} / ${monthNum} / ${year}`; break;
        case 'short': dateStr = `${dayShort} ${dayNum} ${monthShort}`; break;
        case 'minimal': dateStr = `${dayName} ${dayNum}`; break;
        default: dateStr = `${dayName} ${dayNum} de ${monthName} ${year}`;
    }

    const dateEl = document.getElementById('clock-date');
    if(dateEl) dateEl.innerText = dateStr;

    const widget = document.getElementById('clock-widget');
    if(widget) widget.className = `clock-glass style-${currentSettings.clock_style} size-${currentSettings.clock_size}`;
}

async function loadSettings() {
    try {
        const setRes = await fetch('backend/api.php?action=get_weather_settings');
        const settings = await setRes.json();
        if (settings.error) return;

        currentSettings.duration = parseInt(settings.slide_duration) || 10;
        currentSettings.animation = settings.slide_animation || 'fade';
        currentSettings.clock_style = settings.clock_style || 'classic';
        currentSettings.clock_size = settings.clock_size || 'standard';
        currentSettings.date_format = settings.date_format || 'full';
        currentSettings.night_mode_enabled = settings.night_mode_enabled || '0';
        currentSettings.night_start = settings.night_start || '23:00';
        currentSettings.night_end = settings.night_end || '07:00';
        currentSettings.quick_show_duration = parseInt(settings.quick_show_duration) || 8;

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

        if (weatherData) {
            if (prevLat !== weatherConfig.weather_lat || prevLon !== weatherConfig.weather_lon) {
                await loadWeatherData();
            } else {
                updateWeatherUI();
            }
        }
    } catch (e) { console.error("Error settings", e); }
}

async function loadWeatherData() {
    try {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${weatherConfig.weather_lat}&longitude=${weatherConfig.weather_lon}&current=temperature_2m,weather_code&hourly=temperature_2m,weather_code&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto`;
        const res = await fetch(url);
        weatherData = await res.json();
        updateWeatherUI();
    } catch (e) { console.error("Error weather data", e); }
}

function updateWeatherUI() {
    const widget = document.getElementById('weather-widget');
    const nowTemp = document.getElementById('now-temp');
    const nowCity = document.getElementById('now-city');
    const nowIcon = document.getElementById('now-icon');
    if(!widget || !weatherData) return;

    widget.className = `weather-glass style-${weatherConfig.weather_icons} weather-size-${weatherConfig.weather_size} weather-forecast-${weatherConfig.weather_forecast_size}`;
    const currentT = Math.round(weatherData.current.temperature_2m);
    const todayMax = Math.round(weatherData.daily.temperature_2m_max[0]);
    const todayMin = Math.round(weatherData.daily.temperature_2m_min[0]);

    nowTemp.innerHTML = `<div style="display:flex; flex-direction:column; align-items:flex-start;"><span>${currentT}°</span><small style="font-size:0.7rem; opacity:0.8; margin-top:2px;">MÁX ${todayMax}° MÍN ${todayMin}°</small></div>`;
    nowCity.innerText = weatherConfig.weather_city.split(',')[0].trim();

    const iconStyle = weatherConfig.weather_icons;
    const imgStyle = getCustomFilter(iconStyle);
    nowIcon.innerHTML = `<img src="${getPremiumIconURL(weatherData.current.weather_code, iconStyle)}" onerror="this.src='${getPremiumIconURL(weatherData.current.weather_code, 'aura-glow')}'" style="width:58px; height:54px; ${imgStyle}">`;

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
        hoursHtml += `<div class="weather-item"><img src="${getPremiumIconURL(code, iconStyle)}" onerror="this.src='${getPremiumIconURL(code, 'aura-glow')}'" class="wi-icon" style="${imgStyle}"><span>${Math.round(weatherData.hourly.temperature_2m[i])}°</span><small>${time}:00</small></div>`;
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

function getPremiumIconURL(code, styleName) {
    let name = 'clear-day';
    const c = parseInt(code);
    if (c === 0) name = 'clear-day';
    else if (c === 1 || c === 2) name = 'partly-cloudy-day';
    else if (c === 3) name = 'overcast';
    else if (c >= 45 && c <= 48) name = 'fog';
    else if (c >= 51 && c <= 55) name = 'drizzle';
    else if (c >= 61 && c <= 65) name = 'rain';
    else if (c >= 71 && c <= 77) name = 'snow';
    else if (c >= 80 && c <= 82) name = 'rain';
    else if (c >= 95) name = 'thunderstorms';
    else name = 'cloudy';
    let folder = 'fill';
    if (styleName === 'minimal-line') folder = 'line';
    if (styleName === 'neo-flat') folder = 'outline';
    if (styleName === 'vibrant-anim') folder = 'monochrome';
    return `https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/${folder}/all/${name}.svg`;
}

async function loadPlaylist() {
    try {
        const response = await fetch('backend/api.php?action=get_active_media');
        const data = await response.json();
        if (data.error) return;
        if (JSON.stringify(data) !== JSON.stringify(playlist)) {
            const isFirstLoad = playlist.length === 0;
            playlist = data;
            if (playlist.length > 0) { if (isFirstLoad) { currentIndex = 0; showNext(); } }
            else { display.innerHTML = '<h2 class="no-content">No hay contenido activo</h2>'; }
        }
    } catch (e) {
        console.error("Error playlist", e);
        setConnectionState(false);
    }
}

function showNext() {
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
        video.autoplay = true;
        video.muted = true;
        video.onended = () => { advanceIndex(); showNext(); };
        video.onerror = () => { video.remove(); advanceIndex(); showNext(); };

        enterState(video);
        display.appendChild(video);

        let shown = false;
        const show = () => {
            if (shown) return;
            shown = true;
            hideEntryOverlay();
            preloadNext();
            void video.offsetHeight;
            activeState(video);
            setTimeout(() => {
                [...display.children].forEach(el => { if (el !== video) el.remove(); });
            }, FADE);
        };

        video.addEventListener('canplay', show, { once: true });
        setTimeout(show, 1500);

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
    if (playlist.length <= 1) return;
    const next = playlist[(currentIndex + 1) % playlist.length];
    if (next && next.tipo === 'imagen') new Image().src = next.ruta;
}

function checkNightMode() {
    const enabled = currentSettings.night_mode_enabled === '1';
    const overlay = document.getElementById('night-overlay');
    if (!overlay) return;
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

// ─── PASE RÁPIDO ─────────────────────────────────────────────────────────────

async function startQuickShow() {
    if (quickShowActive) return;
    try {
        const res = await fetch('backend/api.php?action=get_quick_show_media');
        const data = await res.json();
        if (!data || data.error || data.length === 0) return;

        const now = new Date();
        const today = now.getDay(); // 0=dom … 6=sáb (igual que Date.getDay())
        const nowMinutes = now.getHours() * 60 + now.getMinutes();

        // Necesitamos el horario de hoy para saber si "ya pasó" o "no llegó aún"
        const todayItems = data.filter(item => parseInt(item.dia_semana) === today);
        if (todayItems.length === 0) return; // sin imágenes para hoy → silencioso

        const [h, m] = (todayItems[0].horario || '08:00').split(':').map(Number);
        const horarioMinutes = h * 60 + m;

        // Si ya pasó la hora de hoy → mostrar las del día siguiente
        const targetDay = nowMinutes >= horarioMinutes ? (today + 1) % 7 : today;

        const filtered = data.filter(item => parseInt(item.dia_semana) === targetDay);
        if (filtered.length === 0) return; // sin imágenes para el día target → silencioso

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
    img.style.cssText = `position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity ${FADE}ms ease`;

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
    showWidgets();
    showNext();
}

function hideWidgets() {
    ['weather-widget', 'clock-widget'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.transition = 'opacity 0.5s ease'; el.style.opacity = '0'; el.style.pointerEvents = 'none'; }
    });
}

function showWidgets() {
    ['weather-widget', 'clock-widget'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.opacity = '1'; el.style.pointerEvents = ''; }
    });
}
