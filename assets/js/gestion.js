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
    document.getElementById('settings-modal').style.display = 'flex';
}

async function saveSlideOnly() {
    const settings = {
        slide_duration: document.getElementById('slide-duration').value,
        slide_animation: document.getElementById('slide-animation').value,
        night_mode_enabled: document.getElementById('night-mode-val').value,
        night_start: document.getElementById('night-start-input').value,
        night_end: document.getElementById('night-end-input').value
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
            card.innerHTML = `${content}${durBadge}<div class="overlay"><div style="display:flex;gap:8px;"><button onclick="event.stopPropagation(); openMediaDurationModal(${item.id}, ${parseInt(item.duracion_img) || 0})" style="background:rgba(255,255,255,0.92); border:none; padding:10px; border-radius:12px; color:#8b5cf6; cursor:pointer; backdrop-filter:blur(8px);"><i class="fas fa-clock"></i></button><button onclick="event.stopPropagation(); deleteMedia(${item.id})" style="background:rgba(255,255,255,0.92); border:none; padding:10px; border-radius:12px; color:#ef4444; cursor:pointer; backdrop-filter:blur(8px);"><i class="fas fa-trash-can"></i></button></div></div><div class="drag-handle"><i class="fas fa-grip-dots-vertical"></i></div>`;
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

    for (let i = 0; i < files.length; i++) {
        if (progressText) progressText.innerText = `SUBIENDO ${i + 1} / ${total}...`;

        const formData = new FormData();
        formData.append('album_id', currentAlbumId);
        formData.append('media[]', files[i]);

        try {
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../backend/upload.php', true);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable && progressBar) {
                        progressBar.value = ((i + e.loaded / e.total) / total) * 100;
                    }
                };
                xhr.onload = () => xhr.status === 200 ? resolve() : reject(new Error(xhr.status));
                xhr.onerror = reject;
                xhr.send(formData);
            });
            uploaded++;
        } catch (e) {
            console.error(`Error subiendo ${files[i].name}:`, e);
        }

        if (progressBar) progressBar.value = ((i + 1) / total) * 100;
    }

    if (wrapper) wrapper.style.display = 'none';
    if (progressBar) progressBar.value = 0;
    input.value = '';
    loadMedia();

    const msg = uploaded === total
        ? `${total} archivo${total !== 1 ? 's' : ''} añadido${total !== 1 ? 's' : ''}`
        : `${uploaded} de ${total} subidos (${total - uploaded} fallaron)`;
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
    for (let i = 0; i < files.length; i++) {
        if (progressText) progressText.innerText = `SUBIENDO ${i + 1} / ${files.length}...`;
        const formData = new FormData();
        formData.append('quick_show', '1');
        formData.append('media[]', files[i]);
        try {
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../backend/upload.php', true);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable && progressBar) {
                        progressBar.value = ((i + e.loaded / e.total) / files.length) * 100;
                    }
                };
                xhr.onload = () => xhr.status === 200 ? resolve() : reject(new Error(xhr.status));
                xhr.onerror = reject;
                xhr.send(formData);
            });
            uploaded++;
        } catch(e) { console.error(`Error subiendo ${files[i].name}:`, e); }
        if (progressBar) progressBar.value = ((i + 1) / files.length) * 100;
    }

    wrapper.style.display = 'none';
    if (progressBar) progressBar.value = 0;
    input.value = '';

    const msg = uploaded === files.length
        ? `${uploaded} imagen${uploaded !== 1 ? 'es' : ''} añadida${uploaded !== 1 ? 's' : ''} al pase`
        : `${uploaded} de ${files.length} subidas (${files.length - uploaded} fallaron)`;
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
