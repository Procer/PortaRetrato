-- Migración: Recordatorios familiares
-- Ejecutar en la BD antes de usar la funcionalidad

CREATE TABLE IF NOT EXISTS recordatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensaje VARCHAR(280) NOT NULL,
    autor VARCHAR(60) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    visto TINYINT(1) DEFAULT 0
);
