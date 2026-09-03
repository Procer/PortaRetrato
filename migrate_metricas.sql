-- Migración: Métricas de dispositivos (modo sync) — 2026-09-02
-- Ejecutar en la BD antes de usar la pantalla "Diagnóstico" de Gestión.
-- Solo crea la tabla si no existe: no toca datos.

CREATE TABLE IF NOT EXISTS dispositivos (
    device_id         VARCHAR(64) PRIMARY KEY,
    nombre            VARCHAR(80)  DEFAULT NULL,
    sw_activo         TINYINT(1)   DEFAULT 0,
    cache_bytes       BIGINT UNSIGNED DEFAULT 0,   -- espacio total del origen en el equipo (storage.estimate)
    cache_archivos    INT          DEFAULT 0,      -- entradas de /uploads/ en el cache del Service Worker
    descarga_bytes    BIGINT UNSIGNED DEFAULT 0,   -- bytes realmente bajados del hosting (cache-miss) desde descarga_desde
    descarga_archivos INT          DEFAULT 0,
    descarga_desde    BIGINT UNSIGNED DEFAULT 0,   -- epoch ms en que el SW empezó a contar (se reinicia si se borra el cache)
    media_total       INT          DEFAULT 0,      -- cantidad de archivos del álbum activo según el manifest
    version_hash      VARCHAR(40)  DEFAULT NULL,
    online            TINYINT(1)   DEFAULT 0,
    user_agent        VARCHAR(255) DEFAULT NULL,
    ip                VARCHAR(45)  DEFAULT NULL,
    ultimo_reporte    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
