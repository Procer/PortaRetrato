-- Migración: Pase Rápido
-- Ejecutar en la BD antes de usar la funcionalidad

CREATE TABLE IF NOT EXISTS quick_show_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta VARCHAR(255) NOT NULL,
    tipo ENUM('imagen','video') DEFAULT 'imagen',
    orden INT DEFAULT 0,
    dia_semana TINYINT UNSIGNED NOT NULL DEFAULT 1,
    horario TIME NOT NULL DEFAULT '08:00:00',
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- dia_semana usa la convención de JS Date.getDay(): 0=dom, 1=lun, 2=mar, 3=mié, 4=jue, 5=vie, 6=sáb

INSERT IGNORE INTO settings (clave, valor) VALUES ('quick_show_duration', '8');
