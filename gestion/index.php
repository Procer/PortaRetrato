<?php
require_once '../backend/config.php';
session_start();
if (!isset($_SESSION['gestion_auth']) || $_SESSION['gestion_auth'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Aura - Gestión Visual</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <link rel="stylesheet" href="../assets/css/gestion.css?v=20260831">
</head>
<body>
    <div id="notification-container" class="notification-area"></div>

    <div class="app-container">
        <header class="app-header">
            <div class="header-left">
                <h1>Aura.</h1>
                <div id="current-active-indicator" style="display:none; margin-left: 10px;">
                    <span class="badge-active">EN VIVO</span>
                </div>
                <div style="margin-left: 15px; display:flex; gap:10px;">
                    <button onclick="openGeneralSettingsModal()" class="btn-action-round" title="Configurar Slide"><i class="fas fa-sliders"></i></button>
                    <button onclick="openClockModal()" class="btn-action-round" title="Configurar Reloj"><i class="fas fa-clock"></i></button>
                    <button onclick="openWeatherModal()" class="btn-action-round" title="Configurar Clima"><i class="fas fa-cloud-sun"></i></button>
                    <a href="logout.php" class="btn-action-round" title="Cerrar sesión" style="color:#94a3b8; text-decoration:none;"><i class="fas fa-right-from-bracket"></i></a>
                </div>
            </div>
            
            <div id="album-quick-actions" class="header-right" style="display:none; gap:12px;">
                <button onclick="openAlbumSettingsModal()" class="btn-action-round" title="Ajustes del Álbum"><i class="fas fa-sliders"></i></button>
                <button onclick="setActiveAlbum(currentAlbumId, currentAlbumName)" class="btn-action-round aura-btn-success" title="Lanzar al Visor"><i class="fas fa-rocket"></i></button>
                <button onclick="emptyAlbum()" class="btn-action-round aura-btn-warning" title="Vaciar Álbum"><i class="fas fa-broom"></i></button>
                <button onclick="deleteAlbum()" class="btn-action-round aura-btn-danger" title="Eliminar Álbum"><i class="fas fa-folder-minus"></i></button>
            </div>
        </header>

        <div class="album-scroller-container">
            <div id="album-list" class="album-scroller"></div>
        </div>
        <main id="media-list" class="media-grid"></main>
    </div>

    <nav class="tab-bar">
        <div class="tab-item active" onclick="location.reload()" title="Ver todos"><i class="fas fa-grip"></i></div>
        <div id="main-upload-btn" class="btn-add aura-gradient-bg" onclick="openUploadModal()" title="Subir"><i class="fas fa-cloud-arrow-up"></i></div>
        <div class="tab-item" onclick="openQuickShowModal()" title="Pase Rápido"><i class="fas fa-bolt"></i></div>
        <div class="tab-item" onclick="openRecordatoriosModal()" title="Mensajes familiares"><i class="fas fa-note-sticky"></i></div>
        <div class="tab-item" onclick="togglePreviewModal()" title="Vista previa inline"><i class="fas fa-eye"></i></div>
        <div class="tab-item" onclick="openQRModal()" title="Compartir enlace QR"><i class="fas fa-qrcode"></i></div>
        <div class="tab-item" onclick="window.open('../', '_blank')" title="Ver Visor"><i class="fas fa-tv"></i></div>
    </nav>

    <!-- MODAL RELOJ -->
    <div id="clock-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="max-width: 520px;">
            <button class="close-btn" onclick="closeModal('clock-modal')"><i class="fas fa-times"></i></button>
            
            <div style="display:flex; align-items:center; gap:20px; margin-bottom:25px;">
                <h2 style="margin:0;">Reloj</h2>
                <button onclick="saveClockSettings()" class="btn-primary-aura" style="margin:0; width:auto; padding:8px 20px; background:var(--accent-gradient); font-size:0.8rem; border-radius:12px; height:40px;">
                    <i class="fas fa-save" style="margin-right:8px;"></i> GUARDAR
                </button>
            </div>

            <div class="modal-section" style="max-height: 65vh; overflow-y: auto; padding-right: 10px;">
                <p class="section-label">Estilos Visuales</p>
                <div class="icon-style-grid">
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-1', this)" data-value="v-1"><div style="font-weight:100; font-size:1.2rem;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-2', this)" data-value="v-2"><div style="font-family:serif; font-style:italic; border-bottom:1px solid #1e293b;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-3', this)" data-value="v-3"><div style="background:#0f172a; color:white; font-weight:800; padding:2px 5px;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-4', this)" data-value="v-4"><div style="border:1px solid #cbd5e1; border-radius:50%; width:35px; height:35px; display:flex; align-items:center; justify-content:center; font-size:0.7rem;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-5', this)" data-value="v-5"><div style="font-weight:200; letter-spacing:4px;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-6', this)" data-value="v-6"><div style="color:#2dd4bf; font-weight:100;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-7', this)" data-value="v-7"><div style="border-bottom:2px solid #ef4444; font-weight:900;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-8', this)" data-value="v-8"><div style="background:#f1f5f9; box-shadow:inset 2px 2px 5px #cbd5e1; padding:2px 5px; border-radius:5px;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-9', this)" data-value="v-9"><div style="font-family:monospace; color:#1e293b;">> 12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-10', this)" data-value="v-10"><div style="border-left:4px solid #1e293b; padding-left:5px; font-weight:bold;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-11', this)" data-value="v-11"><div style="font-weight:900; -webkit-text-stroke:1px #1e293b; color:transparent;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-12', this)" data-value="v-12"><div style="font-family:serif; letter-spacing:2px; font-weight:100;">XII:OO</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-13', this)" data-value="v-13"><div style="background:linear-gradient(#ff00ff, #00ffff); -webkit-background-clip:text; -webkit-text-fill-color:transparent; font-weight:900;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-14', this)" data-value="v-14"><div style="color:white; text-shadow:0 0 10px #2dd4bf; background:#000; padding:2px 5px;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-15', this)" data-value="v-15"><div style="background:var(--accent-gradient); color:white; border-radius:8px; padding:3px 6px;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-16', this)" data-value="v-16"><div style="color:#ef4444; font-weight:900; transform:skew(-10deg);">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-17', this)" data-value="v-17"><div style="background:#fde047; color:#000; border:2px solid #000; padding:2px 5px; box-shadow:3px 3px 0px #000;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-18', this)" data-value="v-18"><div style="color:#f472b6; font-family:cursive; font-size:1.1rem;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-19', this)" data-value="v-19"><div style="background:black; color:red; border-radius:50%; width:35px; height:35px; display:flex; align-items:center; justify-content:center; font-weight:bold;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-20', this)" data-value="v-20"><div style="background:linear-gradient(45deg, #f59e0b, #ef4444); -webkit-background-clip:text; -webkit-text-fill-color:transparent; font-weight:900; font-size:1.1rem;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-21', this)" data-value="v-21"><div style="border:2px dashed #6366f1; padding:2px 5px; color:#6366f1;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-22', this)" data-value="v-22"><div style="font-family:serif; color:#000; text-shadow:2px 0 #00fff9, -2px 0 #ff00c1;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-23', this)" data-value="v-23"><div style="background:#ddd; border-radius:20px; color:#333; padding:2px 8px; font-weight:800; font-size:0.8rem;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-24', this)" data-value="v-24"><div style="color:#4ade80; border-bottom:3px double #4ade80; font-family:monospace;">12:00</div></div>
                    <div class="style-card clock-style-card" onclick="selectClockStyle('v-25', this)" data-value="v-25"><div style="background:linear-gradient(90deg, red, green, blue); -webkit-background-clip:text; -webkit-text-fill-color:transparent; font-weight:900;">12:00</div></div>
                </div>
                <input type="hidden" id="clock-style-val" value="v-1">
                
                <div class="divider-aura"></div>

                <p class="section-label">Tamaño</p>
                <div class="segmented-control">
                    <div class="segment-item" onclick="setSegmentValue('clock-size-val', 'small', this)" data-value="small">PEQUEÑO</div>
                    <div class="segment-item selected" onclick="setSegmentValue('clock-size-val', 'standard', this)" data-value="standard">ESTÁNDAR</div>
                    <div class="segment-item" onclick="setSegmentValue('clock-size-val', 'large', this)" data-value="large">GRANDE</div>
                    <div class="segment-item" onclick="setSegmentValue('clock-size-val', 'extra', this)" data-value="extra">EXTRA</div>
                </div>
                <input type="hidden" id="clock-size-val" value="standard">

                <p class="section-label" style="margin-top:25px;">Formato de Fecha</p>
                <div class="segmented-control" style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                    <div class="segment-item selected" onclick="setSegmentValue('date-format-val', 'full', this)" data-value="full">COMPLETO</div>
                    <div class="segment-item" onclick="setSegmentValue('date-format-val', 'standard', this)" data-value="standard">ESTÁNDAR</div>
                    <div class="segment-item" onclick="setSegmentValue('date-format-val', 'numeric', this)" data-value="numeric">NUMÉRICO</div>
                    <div class="segment-item" onclick="setSegmentValue('date-format-val', 'short', this)" data-value="short">CORTO</div>
                </div>
                <input type="hidden" id="date-format-val" value="full">
            </div>
        </div>
    </div>

    <!-- OTROS MODALES -->
    <div id="settings-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal">
            <button class="close-btn" onclick="closeModal('settings-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header"><h2>Ajustes del Slide</h2></div>
            <div class="modal-section" style="max-height:65vh; overflow-y:auto; padding-right:8px;">
                <p class="section-label">Duración (segundos)</p>
                <div class="input-wrapper"><i class="fas fa-clock"></i><input type="number" id="slide-duration" min="1" max="120"></div>
                
                <p class="section-label">Animación</p>
                <div class="input-wrapper">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <select id="slide-animation" class="aura-select-premium" style="padding-left: 45px;">
                        <option value="fade">Desvanecer (Fade)</option>
                        <option value="slide">Deslizar (Slide)</option>
                        <option value="zoom">Zoom</option>
                        <option value="none">Sin animación</option>
                    </select>
                </div>

                <div class="divider-aura"></div>

                <p class="section-label">Modo Noche</p>
                <div id="night-mode-control" class="segmented-control" style="width:fit-content;">
                    <div class="segment-item selected" onclick="setSegmentValue('night-mode-val', '0', this)" data-value="0">APAGADO</div>
                    <div class="segment-item" onclick="setSegmentValue('night-mode-val', '1', this)" data-value="1">ENCENDIDO</div>
                </div>
                <input type="hidden" id="night-mode-val" value="0">
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <div style="flex:1;">
                        <p class="section-label">Desde</p>
                        <div class="input-wrapper"><i class="fas fa-moon"></i><input type="time" id="night-start-input" value="23:00"></div>
                    </div>
                    <div style="flex:1;">
                        <p class="section-label">Hasta</p>
                        <div class="input-wrapper"><i class="fas fa-sun"></i><input type="time" id="night-end-input" value="07:00"></div>
                    </div>
                </div>

                <div class="divider-aura"></div>

                <p class="section-label">Horario de encendido del Visor</p>
                <p style="font-size:0.75rem; color:#94a3b8; margin-bottom:8px;">Fuera de este horario el Visor frena la reproducción y muestra una imagen fija — así el hosting no vuelve a enviar nada. Distinto del Modo Noche, que solo atenúa y sigue reproduciendo.</p>
                <div id="visor-schedule-control" class="segmented-control" style="width:fit-content;">
                    <div class="segment-item selected" onclick="setSegmentValue('visor-schedule-val', '0', this)" data-value="0">SIEMPRE</div>
                    <div class="segment-item" onclick="setSegmentValue('visor-schedule-val', '1', this)" data-value="1">POR HORARIO</div>
                </div>
                <input type="hidden" id="visor-schedule-val" value="0">
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <div style="flex:1;">
                        <p class="section-label">Enciende</p>
                        <div class="input-wrapper"><i class="fas fa-sun"></i><input type="time" id="visor-on-input" value="07:00"></div>
                    </div>
                    <div style="flex:1;">
                        <p class="section-label">Se apaga</p>
                        <div class="input-wrapper"><i class="fas fa-moon"></i><input type="time" id="visor-off-input" value="23:00"></div>
                    </div>
                </div>

                <p class="section-label" style="margin-top:15px;">Imagen de reposo</p>
                <p style="font-size:0.75rem; color:#94a3b8; margin-bottom:8px;">Se muestra a pantalla completa mientras el Visor está apagado por horario. Sin imagen, queda en negro.</p>
                <div id="rest-image-preview" style="display:none; margin-bottom:10px;">
                    <img id="rest-image-thumb" src="" style="width:100%; max-height:140px; object-fit:cover; border-radius:12px;">
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="document.getElementById('rest-image-input').click()" class="btn-primary-aura" style="background:#f1f5f9; color:#0f172a; flex:1;">
                        <i class="fas fa-image" style="margin-right:6px;"></i> Cargar imagen
                    </button>
                    <button type="button" id="rest-image-clear-btn" onclick="clearRestImage()" class="btn-primary-aura" style="background:#f1f5f9; color:#ef4444; flex:0 0 auto; display:none;">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </div>
                <input type="file" id="rest-image-input" accept="image/*" hidden>
                <div id="rest-image-progress" style="display:none; margin-top:8px;">
                    <progress id="rest-image-bar" value="0" max="100" style="width:100%; height:8px; accent-color:var(--accent);"></progress>
                </div>

                <button onclick="saveSlideOnly()" class="btn-primary-aura" style="background:var(--accent-gradient); margin-top:20px;">Guardar</button>
            </div>
        </div>
    </div>

    <div id="weather-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal">
            <button class="close-btn" onclick="closeModal('weather-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header"><h2>Ajustes del Clima</h2></div>
            <div class="modal-section">
                <p class="section-label">Ubicación</p>
                <div class="input-wrapper"><i class="fas fa-location-dot"></i><input type="text" id="weather-city-input" placeholder="Zarate, Argentina"></div>

                <p class="section-label" style="margin-top:20px;">Estilo de íconos</p>
                <div class="weather-icon-style-grid">
                    <div class="style-card weather-icon-card" onclick="selectWeatherStyle('aura-glow', this)" data-value="aura-glow">
                        <div class="wis-preview"><img src="https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/fill/all/partly-cloudy-day.svg" style="width:34px;height:34px;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.35));"></div>
                        <span>Glow</span>
                    </div>
                    <div class="style-card weather-icon-card" onclick="selectWeatherStyle('neo-flat', this)" data-value="neo-flat">
                        <div class="wis-preview"><img src="https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/outline/all/partly-cloudy-day.svg" style="width:34px;height:34px;"></div>
                        <span>Flat</span>
                    </div>
                    <div class="style-card weather-icon-card" onclick="selectWeatherStyle('minimal-line', this)" data-value="minimal-line">
                        <div class="wis-preview"><img src="https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/line/all/partly-cloudy-day.svg" style="width:34px;height:34px;filter:brightness(0) invert(1);"></div>
                        <span>Línea</span>
                    </div>
                    <div class="style-card weather-icon-card" onclick="selectWeatherStyle('vibrant-anim', this)" data-value="vibrant-anim">
                        <div class="wis-preview"><img src="https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/monochrome/all/partly-cloudy-day.svg" style="width:34px;height:34px;filter:sepia(1) saturate(3) hue-rotate(320deg) brightness(1.2);"></div>
                        <span>Vibrante</span>
                    </div>
                    <div class="style-card weather-icon-card" onclick="selectWeatherStyle('glassmorphism', this)" data-value="glassmorphism">
                        <div class="wis-preview"><img src="https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/fill/all/partly-cloudy-day.svg" style="width:34px;height:34px;filter:opacity(0.7) drop-shadow(0 0 6px rgba(255,255,255,0.6));"></div>
                        <span>Glass</span>
                    </div>
                </div>
                <input type="hidden" id="weather-icons-style" value="neo-flat">

                <p class="section-label" style="margin-top:20px;">Tamaño de temperatura actual</p>
                <div id="weather-size-control" class="segmented-control">
                    <div class="segment-item" onclick="setWeatherSize('small', this)" data-value="small">PEQUEÑO</div>
                    <div class="segment-item selected" onclick="setWeatherSize('standard', this)" data-value="standard">ESTÁNDAR</div>
                    <div class="segment-item" onclick="setWeatherSize('large', this)" data-value="large">GRANDE</div>
                    <div class="segment-item" onclick="setWeatherSize('extra', this)" data-value="extra">EXTRA</div>
                </div>
                <input type="hidden" id="weather-size-val" value="standard">

                <p class="section-label" style="margin-top:20px;">Tamaño del pronóstico</p>
                <div id="weather-forecast-control" class="segmented-control">
                    <div class="segment-item" onclick="setWeatherForecastSize('small', this)" data-value="small">PEQUEÑO</div>
                    <div class="segment-item selected" onclick="setWeatherForecastSize('standard', this)" data-value="standard">ESTÁNDAR</div>
                    <div class="segment-item" onclick="setWeatherForecastSize('large', this)" data-value="large">GRANDE</div>
                    <div class="segment-item" onclick="setWeatherForecastSize('extra', this)" data-value="extra">EXTRA</div>
                </div>
                <input type="hidden" id="weather-forecast-size-val" value="standard">

                <div class="weather-preview-wrap">
                    <div id="weather-size-preview" class="weather-preview-box weather-size-standard wf-size-standard">
                        <div class="wp-now">
                            <img src="https://cdn.jsdelivr.net/gh/basmilius/weather-icons/production/fill/all/partly-cloudy-day.svg" class="wp-icon" onerror="this.style.display='none'">
                            <div>
                                <div class="wp-temp">22°<small class="wp-minmax">MÁX 25° MÍN 14°</small></div>
                                <small class="wp-city">ZÁRATE</small>
                            </div>
                        </div>
                        <div class="wp-divider"></div>
                        <div class="wp-items">
                            <div class="wp-item"><span>19°</span><small>14:00</small></div>
                            <div class="wp-item"><span>17°</span><small>17:00</small></div>
                            <div class="wp-item"><span>14°</span><small>20:00</small></div>
                        </div>
                    </div>
                </div>

                <button onclick="saveWeatherOnly()" class="btn-primary-aura" style="background:var(--accent-gradient); margin-top:10px;">Guardar Clima</button>
            </div>
        </div>
    </div>

    <div id="album-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal">
            <button class="close-btn" onclick="closeModal('album-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header"><h2>Nuevo Álbum</h2></div>
            <div class="modal-section">
                <div class="input-group-aura">
                    <div class="input-wrapper"><input type="text" id="new-album-name" placeholder="Nombre..."></div>
                    <button onclick="createAlbum()" class="btn-accent-aura"><i class="fas fa-check"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div id="upload-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal">
            <button class="close-btn" onclick="closeModal('upload-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header">
                <h2>Subir</h2>
                <p style="font-size: 0.8rem; color: var(--accent); font-weight: 700; margin-top: 5px;">Álbum: <span id="modal-album-name"></span></p>
            </div>
            <div class="modal-section">
                <div class="upload-area-modern" onclick="document.getElementById('file-input').click()">
                    <i class="fas fa-images"></i>
                    <p>Seleccionar archivos</p>
                    <input type="file" id="file-input" multiple accept="image/*,video/*" hidden>
                </div>
                
                <div id="progress-wrapper" style="display:none; margin-top:20px;">
                    <progress id="upload-progress" value="0" max="100" style="width:100%; height:8px; accent-color: var(--accent);"></progress>
                    <p id="upload-progress-text" style="font-size: 0.7rem; text-align: center; color: var(--accent); margin-top: 5px; font-weight: 700;">SUBIENDO...</p>
                </div>

                <button onclick="uploadFiles()" class="btn-primary-aura" style="margin-top: 20px;">Subir ahora</button>
            </div>
        </div>
    </div>

    <!-- MODAL DURACIÓN POR FOTO -->
    <div id="media-duration-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="max-width:360px;">
            <button class="close-btn" onclick="closeModal('media-duration-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header"><h2>Duración de la foto</h2></div>
            <div class="modal-section">
                <p class="section-label">Segundos (0 = usar duración del álbum o global)</p>
                <div class="input-wrapper">
                    <i class="fas fa-clock"></i>
                    <input type="number" id="media-duration-input" min="0" max="300" placeholder="0">
                </div>
                <input type="hidden" id="media-duration-id">
                <button onclick="saveMediaDuration()" class="btn-primary-aura" style="background:var(--accent-gradient); margin-top:10px;">Guardar</button>
            </div>
        </div>
    </div>

    <!-- MODAL AJUSTES DE ÁLBUM -->
    <div id="album-settings-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="max-width:420px;">
            <button class="close-btn" onclick="closeModal('album-settings-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header"><h2>Ajustes del Álbum</h2></div>
            <div class="modal-section">
                <p class="section-label">Duración por defecto (segundos)</p>
                <p style="font-size:0.75rem; color:#94a3b8; margin-bottom:8px;">0 = usar duración global</p>
                <div class="input-wrapper">
                    <i class="fas fa-clock"></i>
                    <input type="number" id="album-duration-input" min="0" max="300" placeholder="0">
                </div>

                <p class="section-label" style="margin-top:20px;">Animación del Álbum</p>
                <p style="font-size:0.75rem; color:#94a3b8; margin-bottom:8px;">Sin selección = usar animación global</p>
                <div class="input-wrapper">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <select id="album-animation-select" class="aura-select-premium" style="padding-left:45px;">
                        <option value="">— Global —</option>
                        <option value="fade">Desvanecer (Fade)</option>
                        <option value="slide">Deslizar (Slide)</option>
                        <option value="zoom">Zoom</option>
                        <option value="none">Sin animación</option>
                    </select>
                </div>

                <button onclick="saveAlbumSettings()" class="btn-primary-aura" style="background:var(--accent-gradient); margin-top:10px;">Guardar</button>
            </div>
        </div>
    </div>

    <!-- MODAL VISTA PREVIA -->
    <div id="preview-modal" class="modal-overlay" style="display:none; background:rgba(0,0,0,0.88); backdrop-filter:blur(12px); padding:0; align-items:center; justify-content:center;" onclick="closePreview()">
        <div onclick="event.stopPropagation()" style="width:92vw; max-width:900px; border-radius:16px; overflow:hidden; box-shadow:0 40px 80px rgba(0,0,0,0.6); position:relative;">
            <button onclick="closePreview()" style="position:absolute; top:10px; right:10px; z-index:10; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.2); width:36px; height:36px; border-radius:50%; color:white; cursor:pointer; font-size:0.9rem; backdrop-filter:blur(8px);" onmouseover="this.style.background='rgba(255,255,255,0.28)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'"><i class="fas fa-times"></i></button>
            <iframe id="viewer-iframe" src="" style="width:100%; aspect-ratio:16/9; border:none; display:block;"></iframe>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div id="lightbox-modal" class="modal-overlay" style="display:none; background:rgba(0,0,0,0.96); backdrop-filter:blur(24px); padding:0; align-items:center; justify-content:center;" onclick="closeLightbox()">
        <button onclick="closeLightbox()" style="position:fixed; top:20px; right:20px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.15); width:44px; height:44px; border-radius:50%; color:white; cursor:pointer; font-size:1rem; z-index:3001; backdrop-filter:blur(10px); transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times"></i></button>
        <div onclick="event.stopPropagation()" style="display:flex; align-items:center; justify-content:center;">
            <img id="lightbox-img" src="" alt="" style="display:none; max-width:92vw; max-height:92vh; object-fit:contain; border-radius:10px; box-shadow:0 40px 80px rgba(0,0,0,0.7);">
            <video id="lightbox-video" src="" controls style="display:none; max-width:92vw; max-height:92vh; border-radius:10px; box-shadow:0 40px 80px rgba(0,0,0,0.7);"></video>
        </div>
    </div>

    <!-- MODAL PASE RÁPIDO -->
    <div id="quick-show-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="max-width:520px;">
            <button class="close-btn" onclick="closeModal('quick-show-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header">
                <h2><i class="fas fa-bolt" style="color:var(--accent); font-size:1rem; margin-right:6px;"></i> Pase Rápido</h2>
            </div>
            <div class="modal-section" style="max-height:65vh; overflow-y:auto; padding-right:8px;">

                <div style="background:#f8fafc; border-left:3px solid var(--accent); border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:0.78rem; color:#475569; line-height:1.6;">
                    <strong style="color:#0f172a; display:block; margin-bottom:4px;"><i class="fas fa-circle-info" style="margin-right:6px; color:var(--accent);"></i>Cómo funciona</strong>
                    Se activa al hacer <strong>clic izquierdo</strong> en el portarretratos. Cada imagen tiene un <strong>día y un horario</strong> asignado.<br>
                    • Si la hora actual es <em>anterior</em> al horario → muestra las imágenes del día actual.<br>
                    • Si la hora actual <em>ya pasó</em> ese horario → muestra las del día siguiente.<br>
                    Al terminar el pase vuelve al álbum activo automáticamente.
                </div>

                <p class="section-label">Imágenes del pase</p>
                <div class="upload-area-modern" onclick="document.getElementById('qs-file-input').click()" style="margin-bottom:14px;">
                    <i class="fas fa-bolt"></i>
                    <p>Agregar imágenes</p>
                    <input type="file" id="qs-file-input" multiple accept="image/*" hidden>
                </div>

                <div id="qs-progress-wrapper" style="display:none; margin-bottom:14px;">
                    <progress id="qs-upload-progress" value="0" max="100" style="width:100%; height:8px; accent-color:var(--accent);"></progress>
                    <p id="qs-progress-text" style="font-size:0.7rem; text-align:center; color:var(--accent); margin-top:5px; font-weight:700;">SUBIENDO...</p>
                </div>

                <div id="qs-grid" class="media-grid" style="gap:10px;"></div>

                <div id="qs-empty" style="text-align:center; padding:35px 20px; color:#94a3b8; display:none;">
                    <i class="fas fa-bolt" style="font-size:2.5rem; margin-bottom:14px; opacity:0.2; display:block;"></i>
                    <p style="font-size:0.85rem;">No hay imágenes en el pase rápido</p>
                </div>

                <div class="divider-aura"></div>

                <p class="section-label">Duración por foto (segundos)</p>
                <div style="display:flex; gap:10px; align-items:center; margin-bottom:4px;">
                    <div class="input-wrapper" style="flex:1; margin-bottom:0;">
                        <i class="fas fa-clock"></i>
                        <input type="number" id="qs-duration-input" min="1" max="120" placeholder="8">
                    </div>
                    <button onclick="saveQsDuration()" class="btn-primary-aura" style="width:auto; padding:12px 20px; background:var(--accent-gradient); flex-shrink:0;">Guardar</button>
                </div>

                <div id="qs-footer" style="display:none; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9;">
                    <button onclick="clearQuickShow()" class="btn-primary-aura" style="background:#f1f5f9; color:#ef4444; font-size:0.8rem;">
                        <i class="fas fa-trash-can" style="margin-right:6px;"></i> Vaciar pase rápido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL RECORDATORIOS FAMILIARES -->
    <div id="recordatorios-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="max-width:480px;">
            <button class="close-btn" onclick="closeModal('recordatorios-modal')"><i class="fas fa-times"></i></button>
            <div class="modal-header">
                <h2><i class="fas fa-note-sticky" style="color:var(--accent); font-size:1rem; margin-right:6px;"></i> Mensajes familiares</h2>
            </div>
            <div class="modal-section" style="max-height:65vh; overflow-y:auto; padding-right:8px;">
                <p class="section-label">Dejar un mensaje</p>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="recordatorio-autor-input" placeholder="Tu nombre (opcional)">
                </div>
                <div class="input-wrapper" style="align-items:flex-start;">
                    <i class="fas fa-message" style="margin-top:14px;"></i>
                    <textarea id="recordatorio-mensaje-input" rows="3" maxlength="280" placeholder="Ej: Comprar leche, cumple de la abuela el sábado..." style="width:100%; padding:12px 12px 12px 45px; border-radius:12px; border:1px solid #e2e8f0; font-family:inherit; font-weight:600; background:#f8fafc; resize:vertical;"></textarea>
                </div>
                <button onclick="addRecordatorio()" class="btn-primary-aura" style="background:var(--accent-gradient); margin-top:4px;">
                    <i class="fas fa-paper-plane" style="margin-right:8px;"></i> Dejar mensaje
                </button>

                <div class="divider-aura"></div>

                <p class="section-label">Duración del panel en el Visor (segundos)</p>
                <p style="font-size:0.75rem; color:#94a3b8; margin-bottom:8px;">Al abrirse en el portarretrato, se cierra solo después de este tiempo</p>
                <div style="display:flex; gap:10px; align-items:center; margin-bottom:4px;">
                    <div class="input-wrapper" style="flex:1; margin-bottom:0;">
                        <i class="fas fa-clock"></i>
                        <input type="number" id="recordatorios-duration-input" min="5" max="120" placeholder="20">
                    </div>
                    <button onclick="saveRecordatoriosDuration()" class="btn-primary-aura" style="width:auto; padding:12px 20px; background:var(--accent-gradient); flex-shrink:0;">Guardar</button>
                </div>

                <div class="divider-aura"></div>

                <p class="section-label">Mensajes en el portarretrato</p>
                <div id="recordatorios-list"></div>
                <div id="recordatorios-empty" style="text-align:center; padding:30px 20px; color:#94a3b8; display:none;">
                    <i class="fas fa-note-sticky" style="font-size:2.2rem; margin-bottom:12px; opacity:0.2; display:block;"></i>
                    <p style="font-size:0.85rem;">No hay mensajes todavía</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL QR -->
    <div id="qr-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="max-width:360px; text-align:center;">
            <button class="close-btn" onclick="closeModal('qr-modal')"><i class="fas fa-times"></i></button>
            <h2 style="margin:0 0 6px;"><i class="fas fa-qrcode" style="color:var(--accent); font-size:1rem; margin-right:6px;"></i> Compartir acceso</h2>
            <p style="font-size:0.78rem; color:#64748b; margin:0 0 20px;">Escanear desde el celular para abrir la gestión.</p>
            <canvas id="qr-canvas" style="border-radius:14px; display:block; margin:0 auto;"></canvas>
            <p id="qr-url" style="font-size:0.65rem; color:#94a3b8; word-break:break-all; margin:14px 0 0; padding:0 4px;"></p>
            <button onclick="copyQRUrl()" class="btn-primary-aura" style="margin-top:16px; background:var(--accent-gradient); font-size:0.8rem; padding:12px;">
                <i class="fas fa-copy" style="margin-right:8px;"></i> Copiar enlace
            </button>
        </div>
    </div>

    <div id="confirm-modal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-content aura-modal" style="text-align: center;">
            <h2 id="confirm-title">¿Seguro?</h2>
            <p id="confirm-message">Esta acción no se puede deshacer.</p>
            <div style="display: flex; gap: 15px; margin-top:25px;">
                <button onclick="closeModal('confirm-modal')" class="btn-primary-aura" style="background:#f1f5f9; color:#64748b; flex:1;">NO</button>
                <button id="confirm-button" class="btn-primary-aura" style="background:#ef4444; flex:1;">SÍ, ELIMINAR</button>
            </div>
        </div>
    </div>

    <style>
        :root { --glass: rgba(255, 255, 255, 0.75); --glass-border: rgba(255, 255, 255, 0.4); --accent: #8b5cf6; --accent-gradient: linear-gradient(135deg, #8b5cf6 0%, #2dd4bf 100%); }
        .aura-btn-success { color: #2dd4bf !important; }
        .badge-active { background: #2dd4bf; color: white; padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; }
        .notification-area { position: fixed; top: 30px; left: 50%; transform: translateX(-50%); z-index: 9999; width: 95%; max-width: 380px; display: flex; flex-direction: column; gap: 12px; }
        .aura-toast { background: white; border-left: 6px solid var(--accent); border-radius: 15px; padding: 14px; display: flex; align-items: center; gap: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); animation: auraToastIn 0.5s; }
        @keyframes auraToastIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); display: flex; align-items: flex-start; justify-content: center; z-index: 2000; padding-top: 80px; backdrop-filter: blur(8px); }
        .aura-modal { width: 100%; max-width: 480px; padding: 30px !important; border-radius: 28px !important; background: white !important; position: relative; }
        .modal-header h2 { font-weight: 800; color: #0f172a; margin: 0; }
        .close-btn { position: absolute; top: 15px; right: 15px; background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; color: #64748b; cursor: pointer; }
        .btn-action-round { background: white; border: 1px solid #e2e8f0; width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; }
        .btn-primary-aura { width: 100%; padding: 14px; border-radius: 12px; border: none; background: #0f172a; color: white; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-primary-aura:hover { opacity: 0.9; transform: scale(1.02); }
        .input-wrapper { position: relative; flex: 1; display: flex; align-items: center; margin-bottom: 10px; }
        .input-wrapper i { position: absolute; left: 16px; color: #94a3b8; }
        .input-wrapper input, .aura-select-premium { width: 100%; padding: 12px 12px 12px 45px; border-radius: 12px; border: 1px solid #e2e8f0; font-family: inherit; font-weight: 600; background: #f8fafc; }
        .live-dot { width: 8px; height: 8px; background: #2dd4bf; border-radius: 50%; margin-left: 10px; box-shadow: 0 0 10px #2dd4bf; display: inline-block; }
        .icon-style-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(85px, 1fr)); gap: 10px; }
        .style-card { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 5px; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: 0.3s; min-height: 55px; justify-content: center; }
        .style-card.selected { border-color: var(--accent); background: white; box-shadow: 0 5px 15px rgba(139, 92, 246, 0.1); }
        .modal-section::-webkit-scrollbar { width: 5px; }
        .modal-section::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* SEGMENTED CONTROL */
        .segmented-control { display: flex; background: #f1f5f9; padding: 5px; border-radius: 14px; gap: 5px; margin-top: 10px; }
        .segment-item { flex: 1; padding: 10px; text-align: center; font-size: 0.7rem; font-weight: 800; color: #64748b; cursor: pointer; border-radius: 10px; transition: 0.3s; }
        .segment-item:hover { color: var(--accent); }
        .segment-item.selected { background: white; color: var(--accent); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .divider-aura { height: 1px; background: #f1f5f9; margin: 25px 0; }

        /* SELECTOR ÍCONOS CLIMA */
        .weather-icon-style-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 10px; }
        .weather-icon-card { padding: 8px 4px 6px; gap: 6px; flex-direction: column; }
        .wis-preview { background: #0f172a; border-radius: 10px; padding: 8px; display: flex; align-items: center; justify-content: center; width: 100%; }
        .weather-icon-card span { font-size: 0.6rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .weather-icon-card.selected span { color: var(--accent); }

        /* PREVIEW WIDGET CLIMA */
        .weather-preview-wrap { margin: 15px 0 10px; overflow-x: auto; padding: 4px 0; scrollbar-width: none; }
        .weather-preview-wrap::-webkit-scrollbar { display: none; }
        .weather-preview-box { background: rgba(15,23,42,0.92); border: 1px solid rgba(255,255,255,0.12); border-radius: 18px; padding: 13px 18px; display: inline-flex; align-items: center; gap: 14px; color: white; font-family: 'Inter', sans-serif; transition: all 0.25s ease; min-width: min-content; }
        .wp-now { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .wp-icon { width: 44px; height: 40px; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4)); }
        .wp-temp { font-size: 2.2rem; font-weight: 800; line-height: 1; transition: font-size 0.25s; display: flex; flex-direction: column; align-items: flex-start; }
        .wp-minmax { font-size: 0.55rem; opacity: 0.75; margin-top: 3px; font-weight: 600; letter-spacing: 0; }
        .wp-city { display: block; font-size: 0.8rem; opacity: 0.75; font-weight: 600; text-transform: uppercase; transition: font-size 0.25s; }
        .wp-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.18); flex-shrink: 0; }
        .wp-items { display: flex; gap: 12px; flex-shrink: 0; }
        .wp-item { display: flex; flex-direction: column; align-items: center; min-width: 36px; }
        .wp-item span { font-size: 0.9rem; font-weight: 700; transition: font-size 0.25s; }
        .wp-item small { font-size: 0.7rem; opacity: 0.65; transition: font-size 0.25s; }

        /* temp + ciudad */
        .weather-size-small .wp-temp { font-size: 1.4rem; }
        .weather-size-small .wp-city { font-size: 0.6rem; }
        .weather-size-large .wp-temp { font-size: 3.2rem; }
        .weather-size-large .wp-city { font-size: 1.1rem; }
        .weather-size-extra .wp-temp { font-size: 4.4rem; }
        .weather-size-extra .wp-city { font-size: 1.4rem; }

        /* pronóstico */
        .wf-size-small .wp-item span  { font-size: 0.65rem; }
        .wf-size-small .wp-item small { font-size: 0.5rem; }
        .wf-size-large .wp-item span  { font-size: 1.4rem; }
        .wf-size-large .wp-item small { font-size: 1.1rem; }
        .wf-size-extra .wp-item span  { font-size: 1.9rem; }
        .wf-size-extra .wp-item small { font-size: 1.5rem; }

        /* RECORDATORIOS FAMILIARES */
        .recordatorio-item { display: flex; gap: 12px; align-items: flex-start; background: #f8fafc; border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; }
        .recordatorio-item .rec-body { flex: 1; min-width: 0; }
        .recordatorio-item .rec-msg { font-weight: 600; color: #0f172a; font-size: 0.85rem; word-break: break-word; }
        .recordatorio-item .rec-meta { font-size: 0.68rem; color: #94a3b8; margin-top: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .recordatorio-item .rec-del { background: none; border: none; color: #ef4444; cursor: pointer; padding: 6px; flex-shrink: 0; }
    </style>
    <script src="../assets/js/gestion.js?v=20260831"></script>
</body>
</html>
