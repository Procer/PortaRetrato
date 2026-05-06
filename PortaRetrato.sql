-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.4.3 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Volcando estructura para tabla portaretrato.albums
CREATE TABLE IF NOT EXISTS `albums` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `duracion_default` int DEFAULT '10',
  `animacion_tipo` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'fade',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla portaretrato.albums: ~2 rows (aproximadamente)
DELETE FROM `albums`;
INSERT INTO `albums` (`id`, `nombre`, `activo`, `fecha_creacion`, `duracion_default`, `animacion_tipo`) VALUES
	(5, 'PUPI', 1, '2026-04-17 19:57:07', 10, 'blur'),
	(7, 'sadf', 0, '2026-04-18 03:10:28', 10, 'fade');

-- Volcando estructura para tabla portaretrato.media
CREATE TABLE IF NOT EXISTS `media` (
  `id` int NOT NULL AUTO_INCREMENT,
  `album_id` int NOT NULL,
  `ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('imagen','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `duracion_img` int DEFAULT '10',
  `orden` int DEFAULT '0',
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `album_id` (`album_id`),
  CONSTRAINT `media_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla portaretrato.media: ~5 rows (aproximadamente)
DELETE FROM `media`;
INSERT INTO `media` (`id`, `album_id`, `ruta`, `tipo`, `duracion_img`, `orden`, `fecha_subida`) VALUES
	(6, 5, 'uploads/69e292cebd577.jpg', 'imagen', 10, 0, '2026-04-17 20:06:38'),
	(7, 5, 'uploads/69e292cebeda2.jpg', 'imagen', 10, 0, '2026-04-17 20:06:38'),
	(8, 5, 'uploads/69e292cec0e03.jpg', 'imagen', 10, 0, '2026-04-17 20:06:38'),
	(10, 5, 'uploads/69e294297c1fa.jpg', 'imagen', 10, 0, '2026-04-17 20:12:25'),
	(11, 5, 'uploads/69e294297d749.jpg', 'imagen', 10, 0, '2026-04-17 20:12:25');

-- Volcando estructura para tabla portaretrato.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `clave` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla portaretrato.settings: ~9 rows (aproximadamente)
DELETE FROM `settings`;
INSERT INTO `settings` (`clave`, `valor`) VALUES
	('clock_size', 'standard'),
	('clock_style', 'blueprint-blue'),
	('date_format', 'full'),
	('weather_city', 'Zárate, Buenos Aires'),
	('weather_days', '4'),
	('weather_hours', '4'),
	('weather_icons', 'neo-flat'),
	('weather_lat', '-34.09584'),
	('weather_lon', '-59.02423');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
