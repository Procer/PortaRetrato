// GESTIÓN COMPLETA (Independiente)
let currentAlbumId = null;
let currentAlbumName = "";

document.addEventListener('DOMContentLoaded', () => {
    loadAlbums();
    const form = document.getElementById('upload-form');
    if(form) form.addEventListener('submit', (e) => { e.preventDefault(); uploadFiles(); });
});

function setWeatherSize(value, element) {
    setSegmentValue('weather-size-val', value, element);
    const preview = document.getElementById('weather-size-preview');
    if (preview) preview.className = preview.className.replace(/weather-size-\S+/, `weather-size-${value}`);
}

function setWeatherForecastSize(value, element) {
    setSegmentValue('weather-forecast-size-val', value, element);
    const preview = document.getElementById('weather-size-preview');
    if (preview) preview.className = preview.className.replace(/wf-size-\S+/, `wf-size-${value}`);
}

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

function openLightbox(url, tipo) {
    const img = document.getElementById('lightbox-img');
    const video = document.getElementById('lightbox-video');
    if (tipo === 'video') {
        video.src = '../' + url;
        video.style.display = 'block';
        img.style.display = 'none';
        img.src = '';
    } else {
        img.src = '../' + url;
        img.style.display = 'block';
        video.style.display = 'none';
        video.src = '';
    }
    document.getElementById('lightbox-modal').style.display = 'flex';
}

function closeLightbox() {
    document.getElementById('lightbox-img').src = '';
    document.getElementById('lightbox-video').src = '';
    document.getElementById('lightbox-modal').style.display = 'none';
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

    const icons = settings.weather_icons || 'neo-flat';
    document.getElementById('weather-icons-style').value = icons;
    document.querySelectorAll('.weather-icon-card').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-value') === icons);
    });

    const size = settings.weather_size || 'standard';
    document.getElementById('weather-size-val').value = size;
    document.querySelectorAll('#weather-size-control .segment-item').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-value') === size);
    });

    const forecastSize = settings.weather_forecast_size || 'standard';
    document.getElementById('weather-forecast-size-val').value = forecastSize;
    document.querySelectorAll('#weather-forecast-control .segment-item').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-value') === forecastSize);
    });

    const preview = document.getElementById('weather-size-preview');
    if (preview) preview.className = `weather-preview-box weather-size-${size} wf-size-${forecastSize}`;

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
            weather_lat: location.latitude.toString(),
            weather_lon: location.longitude.toString(),
            weather_size: document.getElementById('weather-size-val').value,
            weather_forecast_size: document.getElementById('weather-forecast-size-val').value,
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
    const nightMode = settings.night_mode_enabled || '0';
    document.getElementById('night-mode-val').value = nightMode;
    document.querySelectorAll('#night-mode-control .segment-item').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-value') === nightMode);
    });
    document.getElementById('night-start-input').value = settings.night_start || '23:00';
    document.getElementById('night-end-input').value = settings.night_end || '07:00';

    // Horario de encendido del Visor
    const sched = settings.visor_schedule_enabled || '0';
    document.getElementById('visor-schedule-val').value = sched;
    document.querySelectorAll('#visor-schedule-control .segment-item').forEach(el => {
        el.classList.toggle('selected', el.getAttribute('data-value') === sched);
    });
    document.getElementById('visor-on-input').value = settings.visor_on || '07:00';
    document.getElementById('visor-off-input').value = settings.visor_off || '23:00';

    // Imagen de reposo
    const restImg = settings.visor_off_image || '';
    const prev = document.getElementById('rest-image-preview');
    const clr = document.getElementById('rest-image-clear-btn');
    if (restImg) {
        document.getElementById('rest-image-thumb').src = '../' + restImg.replace(/\?.*$/, '') + '?t=' + Date.now();
        prev.style.display = 'block';
        clr.style.display = 'inline-flex';
    } else {
        prev.style.display = 'none';
        clr.style.display = 'none';
    }
    const ri = document.getElementById('rest-image-input');
    ri.onchange = () => uploadRestImage(ri);

    document.getElementById('settings-modal').style.display = 'flex';
}

async function saveSlideOnly() {
    const settings = {
        slide_duration: document.getElementById('slide-duration').value,
        slide_animation: document.getElementById('slide-animation').value,
        night_mode_enabled: document.getElementById('night-mode-val').value,
        night_start: document.getElementById('night-start-input').value,
        night_end: document.getElementById('night-end-input').value,
        visor_schedule_enabled: document.getElementById('visor-schedule-val').value,
        visor_on: document.getElementById('visor-on-input').value,
        visor_off: document.getElementById('visor-off-input').value
    };
    const response = await fetch('../backend/api.php?action=update_weather_settings', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings)
    });
    const result = await response.json();
    if (result.success) { showNotification("Slide guardado", "fa-save"); closeModal('settings-modal'); }
}

async function uploadRestImage(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    const wrap = document.getElementById('rest-image-progress');
    const bar = document.getElementById('rest-image-bar');
    wrap.style.display = 'block';

    const formData = new FormData();
    formData.append('rest_image', '1');
    formData.append('media[]', file);

    try {
        const resp = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../backend/upload.php', true);
            xhr.upload.onprogress = (e) => { if (e.lengthComputable && bar) bar.value = (e.loaded / e.total) * 100; };
            xhr.onload = () => { try { resolve(JSON.parse(xhr.responseText)); } catch (err) { reject(err); } };
            xhr.onerror = reject;
            xhr.send(formData);
        });
        wrap.style.display = 'none';
        bar.value = 0;
        input.value = '';
        if (resp && resp.success) {
            document.getElementById('rest-image-thumb').src = '../' + resp.ruta + '?t=' + Date.now();
            document.getElementById('rest-image-preview').style.display = 'block';
            document.getElementById('rest-image-clear-btn').style.display = 'inline-flex';
            showNotification('Imagen de reposo cargada', 'fa-image');
        } else {
            showNotification((resp && resp.error) || 'No se pudo cargar la imagen', 'fa-triangle-exclamation');
        }
    } catch (e) {
        wrap.style.display = 'none';
        showNotification('Error al subir la imagen', 'fa-bug');
    }
}

async function clearRestImage() {
    showConfirm('¿Quitar imagen de reposo?', 'El Visor quedará en negro fuera del horario de encendido.', async () => {
        await fetch('../backend/api.php?action=clear_rest_image');
        document.getElementById('rest-image-preview').style.display = 'none';
        document.getElementById('rest-image-clear-btn').style.display = 'none';
        showNotification('Imagen de reposo quitada', 'fa-trash-can');
    }, 'warning');
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

let draggedCard = null;

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
            const card = document.createElement('div');
            card.className = 'card';
            card.dataset.id = item.id;
            card.setAttribute('draggable', 'true');
            const content = item.tipo === 'video' ? `<video src="../${item.ruta}" muted></video>` : `<img src="../${item.ruta}" loading="lazy">`;
            const durBadge = parseInt(item.duracion_img) > 0 ? `<div class="card-duration-badge">${item.duracion_img}s</div>` : '';
            const rotateBtn = item.tipo !== 'video' ? `<button onclick="event.stopPropagation(); rotateMedia(${item.id})" style="background:rgba(255,255,255,0.92); border:none; padding:10px; border-radius:12px; color:#0ea5e9; cursor:pointer; backdrop-filter:blur(8px);"><i class="fas fa-rotate-right"></i></button>` : '';
            card.innerHTML = `${content}${durBadge}<div class="overlay"><div style="display:flex;gap:8px;"><button onclick="event.stopPropagation(); openMediaDurationModal(${item.id}, ${parseInt(item.duracion_img) || 0})" style="background:rgba(255,255,255,0.92); border:none; padding:10px; border-radius:12px; color:#8b5cf6; cursor:pointer; backdrop-filter:blur(8px);"><i class="fas fa-clock"></i></button>${rotateBtn}<button onclick="event.stopPropagation(); deleteMedia(${item.id})" style="background:rgba(255,255,255,0.92); border:none; padding:10px; border-radius:12px; color:#ef4444; cursor:pointer; backdrop-filter:blur(8px);"><i class="fas fa-trash-can"></i></button></div></div><div class="drag-handle"><i class="fas fa-grip-dots-vertical"></i></div>`;
            card.onclick = () => openLightbox(item.ruta, item.tipo);

            card.addEventListener('dragstart', (e) => {
                draggedCard = card;
                e.dataTransfer.effectAllowed = 'move';
                setTimeout(() => card.classList.add('dragging'), 0);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                grid.querySelectorAll('.card').forEach(c => c.classList.remove('drag-over'));
                saveMediaOrder();
                draggedCard = null;
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (!draggedCard || card === draggedCard) return;
                grid.querySelectorAll('.card').forEach(c => c.classList.remove('drag-over'));
                card.classList.add('drag-over');
                e.dataTransfer.dropEffect = 'move';
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!draggedCard || card === draggedCard) return;
                const cards = [...grid.querySelectorAll('.card')];
                const dragIdx = cards.indexOf(draggedCard);
                const dropIdx = cards.indexOf(card);
                if (dragIdx < dropIdx) grid.insertBefore(draggedCard, card.nextSibling);
                else grid.insertBefore(draggedCard, card);
                card.classList.remove('drag-over');
            });

            grid.appendChild(card);
        });
    } catch (e) { console.error("Error media", e); }
}

async function saveMediaOrder() {
    const cards = document.querySelectorAll('#media-list .card');
    const order = [...cards].map((c, i) => ({ id: parseInt(c.dataset.id), orden: i }));
    await fetch('../backend/api.php?action=reorder_media', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(order)
    });
}

async function uploadFiles() {
    const input = document.getElementById('file-input');
    if (!input || input.files.length === 0) return;

    const files = Array.from(input.files);
    const total = files.length;
    const wrapper = document.getElementById('progress-wrapper');
    const progressBar = document.getElementById('upload-progress');
    const progressText = document.getElementById('upload-progress-text');

    if (wrapper) wrapper.style.display = 'block';
    let uploaded = 0;
    const failedNames = [];

    for (let i = 0; i < files.length; i++) {
        if (progressText) progressText.innerText = `SUBIENDO ${i + 1} / ${total}...`;

        const formData = new FormData();
        formData.append('album_id', currentAlbumId);
        formData.append('media[]', files[i]);

        try {
            const ok = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../backend/upload.php', true);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable && progressBar) {
                        progressBar.value = ((i + e.loaded / e.total) / total) * 100;
                    }
                };
                xhr.onload = () => {
                    if (xhr.status !== 200) { reject(new Error(xhr.status)); return; }
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        resolve(resp.success && resp.uploaded > 0);
                    } catch (e) { reject(e); }
                };
                xhr.onerror = reject;
                xhr.send(formData);
            });
            if (ok) uploaded++; else failedNames.push(files[i].name);
        } catch (e) {
            console.error(`Error subiendo ${files[i].name}:`, e);
            failedNames.push(files[i].name);
        }

        if (progressBar) progressBar.value = ((i + 1) / total) * 100;
    }

    if (wrapper) wrapper.style.display = 'none';
    if (progressBar) progressBar.value = 0;
    input.value = '';
    loadMedia();

    const msg = uploaded === total
        ? `${total} archivo${total !== 1 ? 's' : ''} añadido${total !== 1 ? 's' : ''}`
        : `${uploaded} de ${total} subidos — fallaron: ${failedNames.join(', ')} (formato no soportado, ej. HEIC de iPhone)`;
    showNotification(msg, uploaded === total ? "fa-cloud-arrow-up" : "fa-triangle-exclamation");
    closeModal('upload-modal');
}

function openMediaDurationModal(id, currentDuration) {
    document.getElementById('media-duration-id').value = id;
    document.getElementById('media-duration-input').value = currentDuration > 0 ? currentDuration : '';
    document.getElementById('media-duration-modal').style.display = 'flex';
}

async function saveMediaDuration() {
    const id = parseInt(document.getElementById('media-duration-id').value);
    const duracion = parseInt(document.getElementById('media-duration-input').value) || 0;
    const response = await fetch('../backend/api.php?action=update_media_duration', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, duracion })
    });
    const result = await response.json();
    if (result.success) {
        showNotification(duracion > 0 ? `Duración: ${duracion}s` : "Duración del álbum/global", "fa-clock");
        closeModal('media-duration-modal');
        loadMedia();
    }
}

async function openAlbumSettingsModal() {
    if (!currentAlbumId) return;
    const response = await fetch(`../backend/api.php?action=get_album_settings&id=${currentAlbumId}`);
    const settings = await response.json();
    document.getElementById('album-duration-input').value = parseInt(settings.duracion_default) > 0 ? settings.duracion_default : '';
    document.getElementById('album-animation-select').value = settings.animacion_tipo || '';
    document.getElementById('album-settings-modal').style.display = 'flex';
}

async function saveAlbumSettings() {
    if (!currentAlbumId) return;
    const duracion = parseInt(document.getElementById('album-duration-input').value) || 0;
    const animacion = document.getElementById('album-animation-select').value;
    const response = await fetch('../backend/api.php?action=update_album_settings', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentAlbumId, duracion, animacion })
    });
    const result = await response.json();
    if (result.success) {
        showNotification("Ajustes del álbum guardados", "fa-sliders");
        closeModal('album-settings-modal');
    }
}

async function rotateMedia(id) {
    const res = await fetch('../backend/api.php?action=rotate_media', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, degrees: 90 })
    });
    const data = await res.json();
    if (data && data.error) { showNotification(data.error, "fa-triangle-exclamation"); return; }
    loadMedia();
    showNotification("Imagen rotada", "fa-rotate-right");
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

function togglePreviewModal() {
    const modal = document.getElementById('preview-modal');
    if (modal.style.display === 'none') {
        document.getElementById('viewer-iframe').src = '../';
        modal.style.display = 'flex';
    } else {
        closePreview();
    }
}

function closePreview() {
    document.getElementById('viewer-iframe').src = '';
    document.getElementById('preview-modal').style.display = 'none';
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

// ─── PASE RÁPIDO ─────────────────────────────────────────────────────────────

async function openQuickShowModal() {
    document.getElementById('quick-show-modal').style.display = 'flex';
    await loadQsDuration();
    await loadQsMedia();

    // Conectar input de archivo
    const fileInput = document.getElementById('qs-file-input');
    fileInput.onchange = () => uploadQuickShowFiles(fileInput);
}

async function loadQsDuration() {
    try {
        const res = await fetch('../backend/api.php?action=get_weather_settings');
        const settings = await res.json();
        document.getElementById('qs-duration-input').value = settings.quick_show_duration || '8';
    } catch(e) {}
}

async function saveQsDuration() {
    const dur = Math.max(1, parseInt(document.getElementById('qs-duration-input').value) || 8);
    await fetch('../backend/api.php?action=update_weather_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quick_show_duration: String(dur) })
    });
    showNotification(`Duración: ${dur}s por foto`, 'fa-bolt');
}

async function loadQsMedia() {
    try {
        const res = await fetch('../backend/api.php?action=get_quick_show_media');
        const data = await res.json();
        const grid = document.getElementById('qs-grid');
        const empty = document.getElementById('qs-empty');
        const footer = document.getElementById('qs-footer');
        grid.innerHTML = '';

        if (!data || data.error || data.length === 0) {
            empty.style.display = 'block';
            footer.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        footer.style.display = 'block';

        const DAYS = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];

        data.forEach(item => {
            const diaVal   = parseInt(item.dia_semana) || 1;
            const horaVal  = (item.horario || '08:00:00').substring(0, 5);
            const dayOpts  = DAYS.map((d, i) =>
                `<option value="${i}"${i === diaVal ? ' selected' : ''}>${d}</option>`
            ).join('');

            const wrap = document.createElement('div');
            wrap.className = 'qs-card-wrap';
            wrap.innerHTML = `
                <div class="qs-thumb">
                    <img src="../${item.ruta}" loading="lazy">
                    <div class="overlay">
                        <button onclick="event.stopPropagation(); deleteQsMedia(${item.id})" style="background:rgba(255,255,255,0.92);border:none;padding:10px;border-radius:12px;color:#ef4444;cursor:pointer;backdrop-filter:blur(8px);">
                            <i class="fas fa-trash-can"></i>
                        </button>
                    </div>
                </div>
                <div class="qs-card-footer">
                    <select class="qs-day-sel">${dayOpts}</select>
                    <input type="time" class="qs-hora-inp" value="${horaVal}">
                </div>`;

            wrap.querySelector('.qs-thumb').onclick = () => openLightbox(item.ruta, 'imagen');

            const sel = wrap.querySelector('.qs-day-sel');
            const inp = wrap.querySelector('.qs-hora-inp');
            const save = () => updateQsMedia(item.id, parseInt(sel.value), inp.value);
            sel.addEventListener('change', save);
            inp.addEventListener('change', save);

            grid.appendChild(wrap);
        });
    } catch(e) { console.error('Error cargando pase rápido', e); }
}

async function updateQsMedia(id, dia_semana, horario) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('dia_semana', dia_semana);
    formData.append('horario', horario);
    try {
        const res = await fetch('../backend/api.php?action=update_quick_show_media', {
            method: 'POST', body: formData
        });
        const data = await res.json();
        if (data.success) showNotification('Programación guardada', 'fa-calendar-check');
        else showNotification('Error al guardar', 'fa-triangle-exclamation');
    } catch(e) { showNotification('Error de conexión', 'fa-triangle-exclamation'); }
}

async function uploadQuickShowFiles(input) {
    const files = Array.from(input.files);
    if (!files.length) return;

    const wrapper = document.getElementById('qs-progress-wrapper');
    const progressBar = document.getElementById('qs-upload-progress');
    const progressText = document.getElementById('qs-progress-text');
    wrapper.style.display = 'block';

    let uploaded = 0;
    const failedNames = [];
    for (let i = 0; i < files.length; i++) {
        if (progressText) progressText.innerText = `SUBIENDO ${i + 1} / ${files.length}...`;
        const formData = new FormData();
        formData.append('quick_show', '1');
        formData.append('media[]', files[i]);
        try {
            const ok = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../backend/upload.php', true);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable && progressBar) {
                        progressBar.value = ((i + e.loaded / e.total) / files.length) * 100;
                    }
                };
                xhr.onload = () => {
                    if (xhr.status !== 200) { reject(new Error(xhr.status)); return; }
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        resolve(resp.success && resp.uploaded > 0);
                    } catch (e) { reject(e); }
                };
                xhr.onerror = reject;
                xhr.send(formData);
            });
            if (ok) uploaded++; else failedNames.push(files[i].name);
        } catch(e) {
            console.error(`Error subiendo ${files[i].name}:`, e);
            failedNames.push(files[i].name);
        }
        if (progressBar) progressBar.value = ((i + 1) / files.length) * 100;
    }

    wrapper.style.display = 'none';
    if (progressBar) progressBar.value = 0;
    input.value = '';

    const msg = uploaded === files.length
        ? `${uploaded} imagen${uploaded !== 1 ? 'es' : ''} añadida${uploaded !== 1 ? 's' : ''} al pase`
        : `${uploaded} de ${files.length} subidas — fallaron: ${failedNames.join(', ')} (formato no soportado, ej. HEIC de iPhone)`;
    showNotification(msg, uploaded === files.length ? 'fa-bolt' : 'fa-triangle-exclamation');
    await loadQsMedia();
}

async function deleteQsMedia(id) {
    showConfirm('¿Eliminar imagen?', 'Se borrará del pase rápido permanentemente.', async () => {
        await fetch(`../backend/api.php?action=delete_quick_show_media&id=${id}`);
        showNotification('Imagen eliminada del pase', 'fa-trash-can');
        await loadQsMedia();
    });
}

async function clearQuickShow() {
    showConfirm('¿Vaciar pase rápido?', 'Se eliminarán todas las imágenes del pase.', async () => {
        await fetch('../backend/api.php?action=clear_quick_show');
        showNotification('Pase rápido vaciado', 'fa-broom');
        await loadQsMedia();
    }, 'warning');
}

// ─── RECORDATORIOS FAMILIARES ────────────────────────────────────────────────

async function openRecordatoriosModal() {
    document.getElementById('recordatorio-autor-input').value = '';
    document.getElementById('recordatorio-mensaje-input').value = '';
    document.getElementById('recordatorios-modal').style.display = 'flex';
    await loadRecordatoriosDuration();
    await loadRecordatorios();
}

async function loadRecordatoriosDuration() {
    try {
        const res = await fetch('../backend/api.php?action=get_weather_settings');
        const settings = await res.json();
        document.getElementById('recordatorios-duration-input').value = settings.recordatorios_duration || '20';
    } catch (e) {}
}

async function saveRecordatoriosDuration() {
    const dur = Math.max(5, parseInt(document.getElementById('recordatorios-duration-input').value) || 20);
    await fetch('../backend/api.php?action=update_weather_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recordatorios_duration: String(dur) })
    });
    showNotification(`Duración del panel: ${dur}s`, 'fa-clock');
}

async function loadRecordatorios() {
    try {
        const res = await fetch('../backend/api.php?action=get_recordatorios');
        const data = await res.json();
        const list = document.getElementById('recordatorios-list');
        const empty = document.getElementById('recordatorios-empty');
        list.innerHTML = '';

        if (!data || data.error || data.length === 0) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';

        data.forEach(item => {
            const row = document.createElement('div');
            row.className = 'recordatorio-item';
            const fecha = new Date(item.fecha_creacion.replace(' ', 'T')).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            const autor = item.autor ? `${item.autor} · ` : '';
            row.innerHTML = `
                <div class="rec-body">
                    <div class="rec-msg"></div>
                    <div class="rec-meta">${autor}${fecha}</div>
                </div>
                <button class="rec-del" onclick="deleteRecordatorio(${item.id})"><i class="fas fa-trash-can"></i></button>`;
            row.querySelector('.rec-msg').textContent = item.mensaje;
            list.appendChild(row);
        });
    } catch (e) { console.error('Error cargando recordatorios', e); }
}

async function addRecordatorio() {
    const mensaje = document.getElementById('recordatorio-mensaje-input').value.trim();
    if (!mensaje) return;
    const autor = document.getElementById('recordatorio-autor-input').value.trim();
    const res = await fetch('../backend/api.php?action=add_recordatorio', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mensaje, autor })
    });
    const data = await res.json();
    if (data && data.error) { showNotification(data.error, 'fa-triangle-exclamation'); return; }
    document.getElementById('recordatorio-mensaje-input').value = '';
    showNotification('Mensaje dejado en el portarretrato', 'fa-note-sticky');
    await loadRecordatorios();
}

async function deleteRecordatorio(id) {
    showConfirm('¿Eliminar mensaje?', 'Se borrará permanentemente.', async () => {
        await fetch(`../backend/api.php?action=delete_recordatorio&id=${id}`);
        showNotification('Mensaje eliminado', 'fa-trash-can');
        await loadRecordatorios();
    });
}

// ─── QR ──────────────────────────────────────────────────────────────────────

let _qrUrl = '';

function openQRModal() {
    _qrUrl = window.location.href.split('?')[0];
    document.getElementById('qr-url').textContent = _qrUrl;
    document.getElementById('qr-modal').style.display = 'flex';

    const canvas = document.getElementById('qr-canvas');
    QRCode.toCanvas(canvas, _qrUrl, {
        width: 220,
        margin: 2,
        color: { dark: '#0f172a', light: '#ffffff' }
    });
}

function copyQRUrl() {
    navigator.clipboard.writeText(_qrUrl).then(() => {
        showNotification('Enlace copiado', 'fa-copy');
    }).catch(() => {
        showNotification('No se pudo copiar', 'fa-triangle-exclamation');
    });
}

// ─── DIAGNÓSTICO / MÉTRICAS DE DISPOSITIVOS ──────────────────────────────────

function openMetricsModal() {
    document.getElementById('metrics-modal').style.display = 'flex';
    loadDeviceMetrics();
}

function mFmtBytes(n) {
    n = Number(n) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(0) + ' KB';
    if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
    return (n / 1073741824).toFixed(2) + ' GB';
}

function mParseDate(sql) {
    if (!sql) return NaN;
    // "2026-09-02 14:03:11" → timestamp local
    const t = new Date(String(sql).replace(' ', 'T')).getTime();
    return isNaN(t) ? NaN : t;
}

function mFmtAgo(sql) {
    const t = mParseDate(sql);
    if (isNaN(t)) return sql || 'nunca';
    let s = Math.floor((Date.now() - t) / 1000);
    if (s < 0) s = 0;
    if (s < 60) return 'hace ' + s + ' s';
    if (s < 3600) return 'hace ' + Math.floor(s / 60) + ' min';
    if (s < 86400) return 'hace ' + Math.floor(s / 3600) + ' h';
    return 'hace ' + Math.floor(s / 86400) + ' días';
}

function mEsc(s) {
    return String(s).replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

async function loadDeviceMetrics() {
    const list = document.getElementById('metrics-list');
    const empty = document.getElementById('metrics-empty');
    const summary = document.getElementById('metrics-summary');
    list.innerHTML = '<p style="text-align:center; color:#94a3b8; font-size:0.8rem; padding:20px;">Cargando...</p>';
    summary.style.display = 'none';
    empty.style.display = 'none';

    let devices;
    try {
        const res = await fetch('../backend/api.php?action=get_devices');
        devices = await res.json();
    } catch (e) {
        list.innerHTML = '<p style="text-align:center; color:#ef4444; font-size:0.8rem; padding:20px;">Error al cargar</p>';
        return;
    }

    if (!Array.isArray(devices) || devices.length === 0) {
        list.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    const totBytes = devices.reduce((a, d) => a + (Number(d.descarga_bytes) || 0), 0);
    const totArch = devices.reduce((a, d) => a + (Number(d.descarga_archivos) || 0), 0);

    // Barra: consumo acumulado (aprox.) contra el tope deseado de 100 GB/mes.
    // El color va de verde (hue 130) a rojo (hue 0) a medida que se acerca al tope.
    const LIMITE_GB = 100;
    const totGB = totBytes / 1073741824;
    const pct = Math.min(100, (totGB / LIMITE_GB) * 100);
    const hue = Math.max(0, 130 * (1 - pct / 100));
    const col = `hsl(${hue.toFixed(0)}, 72%, 45%)`;
    const excedido = totGB > LIMITE_GB;
    const gbTxt = totGB < 0.01 ? '<0,01' : totGB.toFixed(totGB < 10 ? 2 : 1);

    summary.innerHTML = `
    <div class="metrics-total">
        <div>Descargado del hosting<small>Suma de ${devices.length} equipo(s), acumulado desde el último reinicio de cada uno</small></div>
        <div style="text-align:right; font-size:1.05rem;">${mFmtBytes(totBytes)}<small>${totArch} archivos</small></div>
    </div>
    <div class="metrics-bar-wrap">
        <div class="metrics-bar-head">
            <span>Consumo vs. tope</span>
            <small>${gbTxt} GB de ${LIMITE_GB} GB${excedido ? ' &middot; ⚠ excedido' : ''}</small>
        </div>
        <div class="metrics-bar-track">
            <div class="metrics-bar-fill" style="width:${pct.toFixed(1)}%; background-color:${col};"></div>
        </div>
        <div class="metrics-bar-foot">${pct < 0.1 ? '0' : pct.toFixed(1)}% del tope &mdash; el consumo mensual exacto está en cPanel &rarr; Ancho de banda.</div>
    </div>`;
    summary.style.display = 'block';

    list.innerHTML = devices.map(renderDeviceCard).join('');
}

function renderDeviceCard(d) {
    const sw = parseInt(d.sw_activo) === 1;
    const lastMs = Date.now() - mParseDate(d.ultimo_reporte);
    const vivo = !isNaN(lastMs) && lastMs < 15 * 60 * 1000;
    const online = parseInt(d.online) === 1;
    const cacheN = parseInt(d.cache_archivos) || 0;
    const totalN = parseInt(d.media_total) || 0;
    const completo = totalN > 0 && cacheN >= totalN;
    const nombre = (d.nombre && d.nombre.trim())
        ? d.nombre.trim()
        : 'Dispositivo ' + String(d.device_id || '').slice(0, 6);

    const swBadge = sw
        ? '<span class="dev-badge ok"><i class="fas fa-check"></i> Service Worker activo</span>'
        : '<span class="dev-badge bad"><i class="fas fa-triangle-exclamation"></i> SW inactivo — sin ahorro</span>';
    const netBadge = !vivo
        ? '<span class="dev-badge bad">sin señal</span>'
        : (online ? '<span class="dev-badge ok">en línea</span>'
                  : '<span class="dev-badge warn">sin conexión</span>');
    const syncBadge = completo
        ? '<span class="dev-badge ok">álbum sincronizado</span>'
        : (sw && totalN > 0 ? '<span class="dev-badge warn">descargando…</span>' : '');

    let desde = '';
    if (d.descarga_desde && Number(d.descarga_desde) > 0) {
        const dt = new Date(Number(d.descarga_desde));
        if (!isNaN(dt.getTime())) desde = ' · desde ' + dt.toLocaleDateString();
    }
    const ver = d.version_hash ? String(d.version_hash).slice(0, 8) : '—';

    return `
    <div class="device-card">
        <div class="dev-head">
            <strong>${mEsc(nombre)}</strong>
            <span class="dev-ago">${mFmtAgo(d.ultimo_reporte)}</span>
        </div>
        <div class="dev-badges">${swBadge} ${netBadge} ${syncBadge}</div>
        <div class="dev-grid">
            <div class="dev-stat"><span>Archivos en caché</span><b class="${completo ? 'g' : ''}">${cacheN} / ${totalN || '?'}</b></div>
            <div class="dev-stat"><span>Espacio usado en el equipo</span><b>${mFmtBytes(d.cache_bytes)}</b></div>
            <div class="dev-stat"><span>Descargado del hosting</span><b>${mFmtBytes(d.descarga_bytes)} · ${parseInt(d.descarga_archivos) || 0} arch.${desde}</b></div>
            <div class="dev-stat"><span>Versión de contenido</span><b>${mEsc(ver)}</b></div>
        </div>
    </div>`;
}
