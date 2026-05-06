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
    <link rel="stylesheet" href="../assets/css/gestion.css">
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
                </div>
            </div>
            
            <div id="album-quick-actions" class="header-right" style="display:none; gap:12px;">
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
            <div class="modal-section">
                <p class="section-label">Duración (segundos)</p>
                <div class="input-wrapper"><i class="fas fa-clock"></i><input type="number" id="slide-duration" min="1" max="60"></div>
                
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

                <button onclick="saveSlideOnly()" class="btn-primary-aura" style="background:var(--accent-gradient); margin-top:10px;">Guardar Slide</button>
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
                <button onclick="saveWeatherOnly()" class="btn-primary-aura" style="background:var(--accent-gradient);">Guardar Clima</button>
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
            <div class="modal-header"><h2>Subir</h2></div>
            <div class="modal-section">
                <div class="upload-area-modern" onclick="document.getElementById('file-input').click()">
                    <i class="fas fa-images"></i>
                    <p>Seleccionar archivos</p>
                    <input type="file" id="file-input" multiple accept="image/*,video/*" hidden>
                </div>
                <button onclick="uploadFiles()" class="btn-primary-aura">Subir ahora</button>
            </div>
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
    </style>
    <script src="../assets/js/gestion.js"></script>
</body>
</html>
