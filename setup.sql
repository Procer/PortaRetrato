CREATE DATABASE IF NOT EXISTS portaretrato;
USE portaretrato;

CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

-- Insertar un álbum de ejemplo
INSERT INTO albums (nombre, activo) VALUES ('Álbum Inicial', 1);
