// Visor - Porta Retrato v1.1
// Actualizado: 05-05-2026
let playlist = [];
let currentIndex = 0;
let currentSettings = { duration: 10, animation: 'fade', clock_style: 'classic', clock_size: 'standard', date_format: 'full' };
let weatherConfig = { city: '', lat: '', lon: '', days: 3, hours: 6, icons: 'aura-glow' };
let weatherData = null;
let weatherCarouselIndex = 0;
let weatherCarouselItems = [];

const display = document.getElementById('media-display');

document.addEventListener('DOMContentLoaded', async () => {
    loadPlaylist();
    await loadSettings();
    loadWeatherData();
    updateClock();
    setInterval(loadPlaylist, 60000);
    setInterval(loadSettings, 60000);
    setInterval(loadWeatherData, 1800000);
    setInterval(rotateWeather, 8000);
    setInterval(updateClock, 1000);
});

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
        default: dateStr = `${dayName} ${dayNum} de ${monthName} ${year}`; // full
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
    let hoursHtml = '<div style="display:flex; gap:20px;">';
    for(let i=1; i<=parseInt(weatherConfig.weather_hours); i++) {
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
    } catch (e) { console.error("Error playlist", e); }
}

function showNext() {
    if (playlist.length === 0) return;
    const item = playlist[currentIndex];
    display.innerHTML = ''; 
    if (item.tipo === 'imagen') {
        const img = new Image();
        img.src = item.ruta;
        img.className = `anim-${currentSettings.animation}`;
        display.appendChild(img);
        const duration = currentSettings.duration || 10;
        setTimeout(() => { advanceIndex(); showNext(); }, duration * 1000);
    } else if (item.tipo === 'video') {
        const video = document.createElement('video');
        video.src = item.ruta;
        video.autoplay = true;
        video.muted = true;
        video.onended = () => { advanceIndex(); showNext(); };
        video.onerror = () => { advanceIndex(); showNext(); }
        display.appendChild(video);
    }
}

function advanceIndex() {
    currentIndex++;
    if (currentIndex >= playlist.length) { currentIndex = 0; }
}
