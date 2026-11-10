-- Esquema completo de PortaRetrato (instalación limpia en el portarretrato).
-- Reemplaza a setup.sql, PortaRetrato.sql y todos los migrate_*.sql.

CREATE TABLE IF NOT EXISTS albums (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL,
    carpeta          VARCHAR(150) NULL,           -- nombre de la carpeta sincronizada (Syncthing)
    activo           TINYINT(1) DEFAULT 0,
    fecha_creacion   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duracion_default INT NULL,            -- NULL = usar la duración global (Pantalla)
    animacion_tipo   VARCHAR(20) NULL            -- NULL = usar la animación global
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    album_id      INT NOT NULL,
    ruta          VARCHAR(255) NOT NULL,          -- copia de visualización que muestra el Visor
    tipo          ENUM('imagen','video') NOT NULL,
    duracion_img  INT NULL,                       -- NULL = la del álbum o la global
    orden         INT DEFAULT 0,
    fecha_subida  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    origen        VARCHAR(400) NULL,              -- "Álbum/archivo" dentro de la carpeta sincronizada
    origen_mtime  BIGINT NULL,
    origen_size   BIGINT NULL,
    KEY album_id (album_id),
    KEY idx_media_origen (origen(191)),
    CONSTRAINT media_album_fk FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    clave VARCHAR(50) NOT NULL PRIMARY KEY,
    valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pase Rápido. dia_semana usa la convención de JS Date.getDay(): 0=dom … 6=sáb
CREATE TABLE IF NOT EXISTS quick_show_media (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ruta         VARCHAR(255) NOT NULL,
    tipo         ENUM('imagen','video') DEFAULT 'imagen',
    orden        INT DEFAULT 0,
    dia_semana   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    horario      TIME NOT NULL DEFAULT '08:00:00',
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recordatorios (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    mensaje        VARCHAR(280) NOT NULL,
    autor          VARCHAR(60) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    visto          TINYINT(1) DEFAULT 0,
    de_hija        TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE recordatorios ADD COLUMN IF NOT EXISTS de_hija TINYINT(1) NOT NULL DEFAULT 0;

INSERT IGNORE INTO settings (clave, valor) VALUES
    ('clock_style', 'v-1'),
    ('clock_size', 'standard'),
    ('date_format', 'full'),
    ('weather_city', ''),
    ('weather_days', '4'),
    ('weather_hours', '4'),
    ('weather_icons', 'neo-flat'),
    ('weather_lat', ''),
    ('weather_lon', ''),
    ('quick_show_duration', '8');
