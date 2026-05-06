// GESTIÓN COMPLETA (Independiente)
let currentAlbumId = null;
let currentAlbumName = "";

document.addEventListener('DOMContentLoaded', () => {
    loadAlbums();
    const form = document.getElementById('upload-form');
    if(form) form.addEventListener('submit', (e) => { e.preventDefault(); uploadFiles(); });
});

function setSegmentValue(inputId, value, element) {
    document.getElementById(inputId).value = value;
    const parent = element.parentElement;
    parent.querySelectorAll('.segment-item').forEach(item => item.classList.remove('selected'));
    element.classList.add('selected');
}

function openUploadModal() {
    if(!currentAlbumId) { showNotification("Selecciona un álbum primero", "fas fa-info-circle"); return; }
    document.getElementById('modal-album-name').innerText = currentAlbumName;
    document.getElementById('upload-modal').style.display = 'flex';
}

function openNewAlbumModal() { document.getElementById('album-modal').style.display = 'flex'; }

function closeModal(modalId) {
    if(modalId) { document.getElementById(modalId).style.display = 'none'; }
    else { document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none'); }
}

function showNotification(message, icon = 'fas fa-check-circle', duration = 3000) {
    const container = document.getElementById('notification-container');
    const toast = document.createElement('div');
    toast.className = 'aura-toast';
    toast.innerHTML = `<i class="fas ${icon}" style="color:var(--accent); font-size:1.2rem;"></i><div class="message">${message}</div><div class="toast-progress" style="animation-duration: ${duration}ms"></div>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0'; toast.style.transform = 'translateY(-20px) scale(0.9)'; toast.style.transition = '0.4s';
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

function showConfirm(title, message, onConfirm, type = 'danger') {
    const modal = document.getElementById('confirm-modal');
    document.getElementById('confirm-title').innerText = title;
    document.getElementById('confirm-message').innerText = message;
    const btn = document.getElementById('confirm-button');
    btn.onclick = () => { onConfirm(); closeModal('confirm-modal'); };
    modal.style.display = 'flex';
}

async function loadAlbums() {
    try {
        const response = await fetch('../backend/api.php?action=list_albums');
        const albums = await response.json();
        const list = document.getElementById('album-list');
        if(!list) return;
        list.innerHTML = '';
        const addBtn = document.createElement('div');
        addBtn.className = 'album-pill'; addBtn.style.background = 'white'; addBtn.style.border = '2px dashed var(--accent)'; addBtn.style.color = 'var(--accent)';
        addBtn.innerHTML = '<i class="fas fa-plus"></i>'; addBtn.onclick = openNewAlbumModal; list.appendChild(addBtn);
        if (!albums.error) {
            albums.forEach(album => {
                const pill = document.createElement('div');
                pill.className = `album-pill ${album.id == currentAlbumId ? 'active' : ''}`;
                const nameSpan = document.createElement('span'); nameSpan.innerText = album.nombre; pill.appendChild(nameSpan);
                if (album.activo == 1) {
                    const dot = document.createElement('div'); dot.className = 'live-dot'; pill.appendChild(dot);
                }
                pill.onclick = () => selectAlbum(album);
                list.appendChild(pill);
            });
        }
    } catch (e) { console.error("Error albums", e); }
}

function selectAlbum(album) {
    currentAlbumId = album.id; currentAlbumName = album.nombre;
    document.getElementById('current-active-indicator').style.display = album.activo == 1 ? 'block' : 'none';
    document.getElementById('album-quick-actions').style.display = 'flex';
    const uploadBtn = document.getElementById('main-upload-btn'); if(uploadBtn) uploadBtn.style.display = 'flex';
    loadAlbums(); loadMedia();
}

function selectClockStyle(style, element) {
    document.querySelectorAll('.clock-style-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('clock-style-val').value = style;
}

async function openClockModal() {
    const response = await fetch('../backend/api.php?action=get_weather_settings');
    const settings = await response.json();
    const style = settings.clock_style || 'v-1';
    document.getElementById('clock-style-val').value = style;
    
    // Marcar tarjeta
    document.querySelectorAll('.clock-style-card').forEach(card => {
        if(card.getAttribute('data-value') === style) card.classList.add('selected');
        else card.classList.remove('selected');
    });

    // Marcar Tamaño
    const size = settings.clock_size || 'standard';
    document.getElementById('clock-size-val').value = size;
    document.querySelectorAll('[data-value="' + size + '"]').forEach(el => {
        if(el.parentElement.contains(document.getElementById('clock-size-val').nextElementSibling)) return; // Evitar conflictos
        if(el.classList.contains('segment-item')) {
            el.parentElement.querySelectorAll('.segment-item').forEach(i => i.classList.remove('selected'));
            el.classList.add('selected');
        }
    });

    // Marcar Fecha
    const dateF = settings.date_format || 'full';
    document.getElementById('date-format-val').value = dateF;
    document.querySelectorAll('[data-value="' + dateF + '"]').forEach(el => {
        if(el.classList.contains('segment-item')) {
            el.parentElement.querySelectorAll('.segment-item').forEach(i => i.classList.remove('selected'));
            el.classList.add('selected');
        }
    });

    document.getElementById('clock-modal').style.display = 'flex';
}

async function saveClockSettings() {
    const settings = {
        clock_style: document.getElementById('clock-style-val').value,
        clock_size: document.getElementById('clock-size-val').value,
        date_format: document.getElementById('date-format-val').value
    };
    const response = await fetch('../backend/api.php?action=update_weather_settings', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings)
    });
    const result = await response.json();
    if (result.success) { showNotification("Reloj configurado", "fa-clock"); closeModal('clock-modal'); }
}

function selectWeatherStyle(style, element) {
    document.querySelectorAll('.style-card:not(.clock-style-card)').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('weather-icons-style').value = style;
}

async function openWeatherModal() {
    const response = await fetch('../backend/api.php?action=get_weather_settings');
    const settings = await response.json();
    document.getElementById('weather-city-input').value = settings.weather_city || '';
    document.getElementById('weather-days-input').value = settings.weather_days || '3';
    document.getElementById('weather-hours-input').value = settings.weather_hours || '6';
    const currentStyle = settings.weather_icons || 'aura-glow';
    document.getElementById('weather-icons-style').value = currentStyle;
    document.querySelectorAll('.style-card:not(.clock-style-card)').forEach(card => {
        if(card.getAttribute('data-value') === currentStyle) card.classList.add('selected');
        else card.classList.remove('selected');
    });
    document.getElementById('weather-modal').style.display = 'flex';
}

async function saveWeatherOnly() {
    let cityInput = document.getElementById('weather-city-input').value;
    if (!cityInput) return;
    try {
        let searchName = cityInput.split(',')[0].trim();
        const geoRes = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(searchName)}&count=1&language=es&format=json`);
        const geoData = await geoRes.json();
        if (!geoData.results || geoData.results.length === 0) { showNotification("Ciudad no encontrada", "fa-exclamation-circle"); return; }
        const location = geoData.results[0];
        const settings = {
            weather_city: `${location.name}, ${location.admin1 || ''}`,
            weather_lat: location.latitude.toString(), weather_lon: location.longitude.toString(),
            weather_days: document.getElementById('weather-days-input').value,
            weather_hours: document.getElementById('weather-hours-input').value,
            weather_icons: document.getElementById('weather-icons-style').value
        };
        const response = await fetch('../backend/api.php?action=update_weather_settings', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings)
        });
        const result = await response.json();
        if (result.success) { showNotification(`Clima guardado`, "fa-cloud-sun"); closeModal('weather-modal'); }
    } catch (e) { showNotification("Error al guardar", "fa-bug"); }
}

async function openGeneralSettingsModal() {
    const response = await fetch('../backend/api.php?action=get_weather_settings');
    const settings = await response.json();
    document.getElementById('slide-duration').value = settings.slide_duration || '10';
    document.getElementById('slide-animation').value = settings.slide_animation || 'fade';
    document.getElementById('settings-modal').style.display = 'flex';
}

async function saveSlideOnly() {
    const settings = {
        slide_duration: document.getElementById('slide-duration').value,
        slide_animation: document.getElementById('slide-animation').value
    };
    const response = await fetch('../backend/api.php?action=update_weather_settings', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings)
    });
    const result = await response.json();
    if (result.success) { showNotification("Slide guardado", "fa-save"); closeModal('settings-modal'); }
}

async function createAlbum() {
    const name = document.getElementById('new-album-name').value;
    if (!name) return;
    const response = await fetch('../backend/api.php?action=create_album', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nombre: name })
    });
    const result = await response.json();
    document.getElementById('new-album-name').value = '';
    await loadAlbums();
    if (result.id) selectAlbum({ id: result.id, nombre: name, activo: 0 });
    showNotification("Álbum creado", "fa-folder-plus"); closeModal('album-modal');
}

async function setActiveAlbum(id, name) {
    if(!id) return;
    await fetch(`../backend/api.php?action=set_active_album&id=${id}`);
    loadAlbums(); 
    document.getElementById('current-active-indicator').style.display = 'block';
    showNotification(`Lanzado: ${name}`, "fa-rocket");
}

async function loadMedia() {
    try {
        const response = await fetch(`../backend/api.php?action=get_album_media&id=${currentAlbumId}`);
        const media = await response.json();
        const grid = document.getElementById('media-list'); if(!grid) return; grid.innerHTML = '';
        if (media.length === 0) {
            grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:60px; color:#94a3b8;"><i class="fas fa-mountain-sun" style="font-size:3rem; margin-bottom:20px; display:block; opacity:0.3;"></i><p>Este álbum está esperando tus fotos</p></div>`;
            return;
        }
        media.forEach(item => {
            const card = document.createElement('div'); card.className = 'card';
            const content = item.tipo === 'video' ? `<video src="../${item.ruta}" muted></video>` : `<img src="../${item.ruta}" loading="lazy">`;
            card.innerHTML = `${content}<div class="overlay"><button onclick="deleteMedia(${item.id})" style="background:rgba(255,255,255,0.9); border:none; padding:10px; border-radius:12px; color:#ef4444; cursor:pointer;"><i class="fas fa-trash-can"></i></button></div>`;
            grid.appendChild(card);
        });
    } catch (e) { console.error("Error media", e); }
}

function uploadFiles() {
    const input = document.getElementById('file-input');
    if (!input || input.files.length === 0) return;
    const formData = new FormData();
    formData.append('album_id', currentAlbumId);
    for (let i = 0; i < input.files.length; i++) { formData.append('media[]', input.files[i]); }
    const wrapper = document.getElementById('progress-wrapper'); if(wrapper) wrapper.style.display = 'block';
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../backend/upload.php', true);
    xhr.upload.onprogress = (e) => { if (e.lengthComputable) { document.getElementById('upload-progress').value = (e.loaded / e.total) * 100; } };
    xhr.onload = () => {
        if (xhr.status === 200) {
            if(wrapper) wrapper.style.display = 'none'; input.value = ''; loadMedia();
            showNotification("Fotos añadidas", "fa-cloud-arrow-up"); closeModal('upload-modal');
        }
    };
    xhr.send(formData);
}

async function deleteMedia(id) {
    showConfirm('¿Eliminar imagen?', 'Esta imagen se borrará permanentemente.', async () => {
        await fetch(`../backend/api.php?action=delete_media&id=${id}`); loadMedia(); showNotification("Imagen eliminada", "fa-trash-can");
    });
}

async function emptyAlbum() {
    if (!currentAlbumId) return;
    showConfirm('¿Vaciar álbum?', 'Se eliminarán todos los archivos.', async () => {
        await fetch(`../backend/api.php?action=empty_album&id=${currentAlbumId}`); loadMedia(); showNotification("Álbum vacío", "fa-broom");
    }, 'warning');
}

async function deleteAlbum() {
    if (!currentAlbumId) return;
    showConfirm('¿Eliminar álbum?', 'Se borrará el álbum y todo su contenido.', async () => {
        await fetch(`../backend/api.php?action=delete_album&id=${currentAlbumId}`); currentAlbumId = null; loadAlbums();
        document.getElementById('media-list').innerHTML = ''; document.getElementById('album-quick-actions').style.display = 'none';
        const uploadBtn = document.getElementById('main-upload-btn'); if(uploadBtn) uploadBtn.style.display = 'none';
        showNotification("Álbum eliminado", "fa-folder-minus");
    });
}
