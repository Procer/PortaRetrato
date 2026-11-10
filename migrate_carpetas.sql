USE portaretrato;

-- Álbum = carpeta sincronizada (Syncthing). albums.carpeta es el nombre de la carpeta.
SET @q = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE albums ADD COLUMN carpeta VARCHAR(150) NULL',
    'SELECT "albums.carpeta ya existe"')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'portaretrato' AND TABLE_NAME = 'albums' AND COLUMN_NAME = 'carpeta');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- media.ruta pasa a ser la copia de visualización; origen es el archivo real en la carpeta.
SET @q = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE media ADD COLUMN origen VARCHAR(400) NULL, ADD COLUMN origen_mtime BIGINT NULL, ADD COLUMN origen_size BIGINT NULL, ADD INDEX idx_media_origen (origen(191))',
    'SELECT "media.origen ya existe"')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'portaretrato' AND TABLE_NAME = 'media' AND COLUMN_NAME = 'origen');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
