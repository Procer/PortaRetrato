<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor - Porta Retrato v1.1</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
    <link rel="stylesheet" href="assets/css/viewer.css">
</head>
<body>
    <div id="entry-overlay"></div>
    <div id="night-overlay"></div>
    <div id="offline-indicator" class="connection-online"><i class="fas fa-wifi"></i><span>En línea</span></div>
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
    </div>

    <script src="assets/js/viewer.js"></script>
</body>
</html>
