-- ============================================================
--  PortaRetrato — Script de instalación / actualización
--  Ejecutar UNA SOLA VEZ en phpMyAdmin del hosting.
--  Es seguro correrlo aunque la BD ya tenga datos: no borra nada.
-- ============================================================

-- Si WNPower ya creó la BD, estas dos líneas no hacen falta
-- pero tampoco rompen nada si las dejás.
CREATE DATABASE IF NOT EXISTS portaretrato
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portaretrato;

-- ------------------------------------------------------------
-- Tablas base
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS albums (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    activo          TINYINT(1) DEFAULT 0,
    fecha_creacion  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duracion_default INT DEFAULT 10,
    animacion_tipo  VARCHAR(20) DEFAULT 'fade'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    album_id     INT NOT NULL,
    ruta         VARCHAR(255) NOT NULL,
    tipo         ENUM('imagen','video') NOT NULL,
    duracion_img INT DEFAULT 10,
    orden        INT DEFAULT 0,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    clave VARCHAR(50) NOT NULL PRIMARY KEY,
    valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quick_show_media (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ruta         VARCHAR(255) NOT NULL,
    tipo         ENUM('imagen','video') DEFAULT 'imagen',
    orden        INT DEFAULT 0,
    dia_semana   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    horario      TIME NOT NULL DEFAULT '08:00:00',
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- dia_semana: 0=dom, 1=lun, 2=mar, 3=mié, 4=jue, 5=vie, 6=sáb (igual que JS Date.getDay())

-- ------------------------------------------------------------
-- Columnas nuevas en albums (seguro si ya existen)
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
-- Settings por defecto (no sobreescribe valores existentes)
-- ------------------------------------------------------------

INSERT IGNORE INTO settings (clave, valor) VALUES
    -- Reloj
    ('clock_style',           'v-1'),
    ('clock_size',            'standard'),
    ('date_format',           'full'),
    -- Clima
    ('weather_city',          ''),
    ('weather_lat',           ''),
    ('weather_lon',           ''),
    ('weather_days',          '4'),
    ('weather_hours',         '4'),
    ('weather_icons',         'neo-flat'),
    ('weather_size',          'standard'),
    ('weather_forecast_size', 'large'),
    -- Modo noche
    ('night_mode_enabled',    '0'),
    ('night_start',           '23:00'),
    ('night_end',             '07:00'),
    -- Pase rápido
    ('quick_show_duration',   '8');

-- Corrige valor inválido 'blueprint-blue' si todavía está en la BD
UPDATE settings SET valor = 'v-1' WHERE clave = 'clock_style' AND valor = 'blueprint-blue';

-- ============================================================
--  Listo. Este es el único script que necesitás ejecutar.
--  Los archivos migrate_*.sql son solo para migraciones locales.
-- ============================================================
