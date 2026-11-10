<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor - Porta Retrato v1.1</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="assets/vendor/fa/css/all.min.css">
    <link rel="stylesheet" href="assets/css/viewer.css?v=20261111">
</head>
<body>
    <div id="entry-overlay"></div>
    <div id="night-overlay"></div>
    <div id="rest-overlay"></div>
    <div id="offline-indicator" class="connection-online"><i class="fas fa-wifi"></i><span>En línea</span></div>
    <div id="messages-btn" style="display:none;" onclick="event.stopPropagation(); openRecordatoriosOverlay();">
        <i class="fas fa-note-sticky"></i>
        <span id="messages-badge" style="display:none;"></span>
    </div>
    <div id="recordatorios-overlay" style="display:none;" onclick="closeRecordatoriosOverlay()">
        <div id="recordatorios-overlay-content" onclick="event.stopPropagation()">
            <button id="recordatorios-overlay-close" onclick="closeRecordatoriosOverlay()"><i class="fas fa-times"></i></button>
            <h2><i class="fas fa-note-sticky"></i> Mensajes familiares</h2>
            <div id="recordatorios-overlay-list"></div>
        </div>
    </div>
    <div id="viewer-container">
        <!-- Barra inferior: clima + reloj en un solo renglón -->
        <div id="bottom-right-widgets">
            <div id="unified-widget" class="unified-glass">
                <div id="weather-widget" class="weather-section" style="display:none;">
                    <div class="weather-now">
                        <div id="now-icon"></div>
                        <div class="now-info">
                            <span id="now-temp">--°</span>
                            <small id="now-city">Ciudad</small>
                            <small id="weather-stale" style="display:none;"></small>
                            <small id="weather-alert" style="display:none;"></small>
                        </div>
                    </div>
                    <div class="weather-divider"></div>
                    <div id="weather-dynamic-section" class="weather-carousel"></div>
                    <div class="weather-divider"></div>
                </div>
                <div id="clock-widget" class="clock-glass">
                    <div id="clock-time">00:00</div>
                    <div id="clock-date">Cargando fecha...</div>
                </div>
            </div>
        </div>

        <!-- El contenido (img/video) se cargará aquí dinámicamente -->
        <div id="media-display"></div>

        <div id="video-sound-btn" class="video-sound-btn" style="display:none;" onclick="event.stopPropagation(); toggleVideoSound();">
            <i class="fas fa-volume-xmark"></i>
        </div>
    </div>

    <script src="assets/vendor/qrcode.min.js"></script>
    <script src="assets/js/viewer.js?v=20261111"></script>
</body>
</html>
