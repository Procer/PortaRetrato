-- Migración: Métricas v2 — 2026-09-02
-- Agrega el cupo de almacenamiento que el navegador le da al sitio en cada
-- equipo (navigator.storage.estimate().quota). Sirve para detectar cuando el
-- álbum NO entra en el disco del portarretrato y Chrome está expulsando el
-- cache (síntoma: "archivos en caché" se queda trabado y el consumo no para).
-- Correr después de migrate_metricas.sql.

ALTER TABLE dispositivos
    ADD COLUMN quota_bytes BIGINT UNSIGNED DEFAULT 0 AFTER cache_bytes,
    ADD COLUMN persistente TINYINT(1) DEFAULT 0 AFTER quota_bytes;
