CREATE DATABASE IF NOT EXISTS portaretrato;
USE portaretrato;

CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duracion_default INT DEFAULT 10,
    animacion_tipo VARCHAR(20) DEFAULT 'fade'
);

CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    tipo ENUM('imagen', 'video') NOT NULL,
    duracion_img INT DEFAULT 10,
    orden INT DEFAULT 0,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    clave VARCHAR(50) NOT NULL PRIMARY KEY,
    valor TEXT
);

INSERT INTO settings (clave, valor) VALUES
    ('clock_style', 'v-1'),
    ('clock_size', 'standard'),
    ('date_format', 'full'),
    ('weather_city', ''),
    ('weather_days', '4'),
    ('weather_hours', '4'),
    ('weather_icons', 'neo-flat'),
    ('weather_lat', ''),
    ('weather_lon', '')
ON DUPLICATE KEY UPDATE clave = clave;

-- Álbum de ejemplo
INSERT INTO albums (nombre, activo) VALUES ('Álbum Inicial', 1);
