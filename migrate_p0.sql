USE portaretrato;

-- Agregar duracion_default si no existe
SET @q = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE albums ADD COLUMN duracion_default INT DEFAULT 10',
    'SELECT "duracion_default ya existe"')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'portaretrato' AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'duracion_default');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Agregar animacion_tipo si no existe
SET @q = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE albums ADD COLUMN animacion_tipo VARCHAR(20) DEFAULT ''fade''',
    'SELECT "animacion_tipo ya existe"')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'portaretrato' AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'animacion_tipo');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Crear tabla settings si no existe
CREATE TABLE IF NOT EXISTS settings (
    clave VARCHAR(50) NOT NULL PRIMARY KEY,
    valor TEXT
);

-- Insertar valores por defecto solo si no existen
INSERT IGNORE INTO settings (clave, valor) VALUES
    ('clock_style', 'v-1'),
    ('clock_size', 'standard'),
    ('date_format', 'full'),
    ('weather_city', ''),
    ('weather_days', '4'),
    ('weather_hours', '4'),
    ('weather_icons', 'neo-flat'),
    ('weather_lat', ''),
    ('weather_lon', '');

-- Corregir clock_style 'blueprint-blue' que no tiene CSS asociado
UPDATE settings SET valor = 'v-1' WHERE clave = 'clock_style' AND valor = 'blueprint-blue';
