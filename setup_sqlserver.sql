-- Script de configuración para SQL Server

-- Crear la base de datos (Ejecutar por separado si es necesario)
-- CREATE DATABASE portaretrato;
-- GO
-- USE portaretrato;
-- GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'albums')
BEGIN
    CREATE TABLE albums (
        id INT IDENTITY(1,1) PRIMARY KEY,
        nombre NVARCHAR(100) NOT NULL,
        activo TINYINT DEFAULT 0,
        fecha_creacion DATETIME DEFAULT GETDATE()
    );
END

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'media')
BEGIN
    CREATE TABLE media (
        id INT IDENTITY(1,1) PRIMARY KEY,
        album_id INT NOT NULL,
        ruta NVARCHAR(255) NOT NULL,
        tipo NVARCHAR(10) CHECK (tipo IN ('imagen', 'video')) NOT NULL,
        duracion_img INT DEFAULT 10,
        orden INT DEFAULT 0,
        fecha_subida DATETIME DEFAULT GETDATE(),
        FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
    );
END

-- Insertar un álbum de ejemplo si no hay ninguno
IF NOT EXISTS (SELECT 1 FROM albums)
BEGIN
    INSERT INTO albums (nombre, activo) VALUES ('Álbum Inicial', 1);
END
