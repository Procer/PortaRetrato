-- ============================================================
--  PortaRetrato — Script de actualización para WNPower
--  Ejecutar UNA SOLA VEZ en phpMyAdmin.
--  Solo agrega lo que no existe: NO borra ni modifica datos.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Nueva tabla para el Pase Rápido
--    (si ya existe, no hace nada)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS quick_show_media (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ruta         VARCHAR(255) NOT NULL,
    tipo         ENUM('imagen','video') DEFAULT 'imagen',
    orden        INT DEFAULT 0,
    dia_semana   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    horario      TIME NOT NULL DEFAULT '08:00:00',
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- dia_semana: 0=dom, 1=lun, 2=mar, 3=mié, 4=jue, 5=vie, 6=sáb

-- ------------------------------------------------------------
-- 2. Columnas nuevas en albums
--    (solo se agregan si no existen)
-- ------------------------------------------------------------

SET @q = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE albums ADD COLUMN duracion_default INT DEFAULT 10',
    'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'duracion_default');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE albums ADD COLUMN animacion_tipo VARCHAR(20) DEFAULT ''fade''',
    'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'animacion_tipo');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3. Settings nuevos
--    INSERT IGNORE = solo inserta si la clave no existe todavía
-- ------------------------------------------------------------

INSERT IGNORE INTO settings (clave, valor) VALUES
    -- Modo noche (nuevo)
    ('night_mode_enabled',    '0'),
    ('night_start',           '23:00'),
    ('night_end',             '07:00'),
    -- Pase rápido (nuevo)
    ('quick_show_duration',   '8'),
    -- Clima — por si falta alguno
    ('weather_forecast_size', 'large'),
    -- Recordatorios familiares (nuevo 2026-07-15): segundos que dura el panel
    -- abierto en el Visor antes de cerrarse solo
    ('recordatorios_duration', '20'),
    -- Horario de encendido del Visor + imagen de reposo (modo sync, 2026-08-27)
    ('visor_schedule_enabled', '0'),
    ('visor_on',               '07:00'),
    ('visor_off',              '23:00'),
    ('visor_off_image',        '');

-- ------------------------------------------------------------
-- 4. Nueva tabla para Recordatorios familiares (2026-07-15)
--    (si ya existe, no hace nada)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS recordatorios (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    mensaje        VARCHAR(280) NOT NULL,
    autor          VARCHAR(60) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    visto          TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Nueva tabla para Métricas de dispositivos (2026-09-02)
--    Alimenta la pantalla "Diagnóstico" de Gestión: estado del
--    Service Worker, archivos en caché y bytes bajados del hosting
--    por cada portarretrato. (si ya existe, no hace nada)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dispositivos (
    device_id         VARCHAR(64) PRIMARY KEY,
    nombre            VARCHAR(80)  DEFAULT NULL,
    sw_activo         TINYINT(1)   DEFAULT 0,
    cache_bytes       BIGINT UNSIGNED DEFAULT 0,
    cache_archivos    INT          DEFAULT 0,
    descarga_bytes    BIGINT UNSIGNED DEFAULT 0,
    descarga_archivos INT          DEFAULT 0,
    descarga_desde    BIGINT UNSIGNED DEFAULT 0,
    media_total       INT          DEFAULT 0,
    version_hash      VARCHAR(40)  DEFAULT NULL,
    online            TINYINT(1)   DEFAULT 0,
    user_agent        VARCHAR(255) DEFAULT NULL,
    ip                VARCHAR(45)  DEFAULT NULL,
    ultimo_reporte    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Listo. Tus datos existentes no se tocaron.
-- ============================================================
