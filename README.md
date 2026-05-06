# Instrucciones para iniciar Porta Retrato

## 1. Base de Datos
1. Abre tu gestor de base de datos (phpMyAdmin o HeidiSQL en Laragon).
2. Ejecuta el archivo `setup.sql` que se encuentra en la raíz del proyecto. Esto creará la base de datos `portaretrato` y las tablas necesarias.

## 2. Configuración PHP (Para Videos Grandes)
Si vas a subir videos pesados, asegúrate de que Laragon tenga configurados límites altos.
En el archivo `php.ini` de Laragon, busca y ajusta estos valores:
* `upload_max_filesize = 100M`
* `post_max_size = 110M`
* `max_execution_time = 300`

## 3. Primeros Pasos
1. Entra a `http://localhost/PortaRetrato/gestion/`.
2. Crea un nuevo álbum.
3. Haz clic en el álbum creado y sube tus fotos y videos.
4. Presiona el botón **"Marcar como Activo (Visor)"**.
5. Abre `http://localhost/PortaRetrato/` (el visor) y ponlo en pantalla completa (F11).

¡Listo! El sistema rotará tus archivos de forma infinita.
