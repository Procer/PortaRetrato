-- Migración: Pase Rápido v2 — columnas día y horario
-- Ejecutar SOLO en BDs que ya tienen la tabla quick_show_media (BD local)
-- Para instalaciones nuevas (WNPower), usar migrate_quick_show.sql directamente

ALTER TABLE quick_show_media
    ADD COLUMN dia_semana TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER orden,
    ADD COLUMN horario TIME NOT NULL DEFAULT '08:00:00' AFTER dia_semana;

-- dia_semana usa la convención de JS Date.getDay(): 0=dom, 1=lun, 2=mar, 3=mié, 4=jue, 5=vie, 6=sáb
