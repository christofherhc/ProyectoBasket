-- =============================================
-- Esquema de Base de Datos: Liga de Básquetbol
-- Motor: InnoDB | Charset: utf8mb4
-- Compatible con MySQL y MariaDB (XAMPP)
-- =============================================
-- Este script crea la base de datos si no existe
-- y luego todas las tablas. Es seguro reimportarlo.
-- =============================================

CREATE DATABASE IF NOT EXISTS liga_basquet
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE liga_basquet;

-- =============================================
-- 1. TABLA USUARIOS (AUTENTICACIÓN)
-- =============================================
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del usuario',
  email VARCHAR(150) NOT NULL UNIQUE COMMENT 'Email único para login',
  password VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada con password_hash()',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 2. TABLA EQUIPOS
-- =============================================
CREATE TABLE IF NOT EXISTS equipos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre del equipo',
  ciudad VARCHAR(100) NOT NULL COMMENT 'Ciudad de origen',
  fundacion YEAR NULL COMMENT 'Año de fundación (opcional)',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Dos equipos no pueden llamarse igual: en la tabla de posiciones dos filas
  -- con el mismo nombre son indistinguibles para el usuario.
  UNIQUE KEY uq_equipo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 3. TABLA JUGADORES
-- =============================================
CREATE TABLE IF NOT EXISTS jugadores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del jugador',
  numero INT UNSIGNED NULL COMMENT 'Número de camiseta',
  posicion VARCHAR(50) NULL COMMENT 'Posición: Base/Escolta/Alero/Ala-Pívot/Pívot',
  equipo_id INT UNSIGNED NOT NULL COMMENT 'Equipo al que pertenece (FK)',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_jugador_equipo (equipo_id),
  -- Un dorsal no puede repetirse dentro del mismo equipo.
  -- NULL no cuenta como duplicado, así que los jugadores sin dorsal conviven.
  UNIQUE KEY uq_jugador_camiseta (equipo_id, numero),
  CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 4. TABLA PARTIDOS
-- =============================================
CREATE TABLE IF NOT EXISTS partidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATETIME NOT NULL COMMENT 'Fecha y hora del encuentro',
  equipo_local_id INT UNSIGNED NOT NULL COMMENT 'Equipo local (FK)',
  equipo_visitante_id INT UNSIGNED NOT NULL COMMENT 'Equipo visitante (FK)',
  puntos_local INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Puntos equipo local',
  puntos_visitante INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Puntos equipo visitante',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_partido_local (equipo_local_id),
  KEY idx_partido_visitante (equipo_visitante_id),
  KEY idx_partido_fecha (fecha),
  CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_partido_visitante FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 5. AJUSTES QUE NO PUEDEN IR EN EL CREATE TABLE
--
-- CREATE TABLE IF NOT EXISTS no modifica una tabla que ya existe, así que
-- los índices UNIQUE solo se crean con una base nueva. Este bloque los añade
-- también sobre una base importada antes de estos cambios.
--
-- Se consulta information_schema en lugar de "DROP TABLE" para no perder
-- datos: reimportar el esquema nunca debe ser una operación destructiva.
--
-- Además se comprueban los duplicados ANTES de añadir cada clave. Si una
-- base antigua los tiene, el ALTER falla con el error 1062 y aborta toda la
-- importación del esquema, dejando al usuario sin esquema. En ese caso el
-- índice se omite y se avisa, que es mejor que un import que se corta a mitad.
-- =============================================

-- --- Índice único de dorsales por equipo ---
SET @indice_ya_existe = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'jugadores'
    AND INDEX_NAME   = 'uq_jugador_camiseta'
);

SET @hay_duplicados = (
  SELECT COUNT(*) FROM (
    SELECT equipo_id, numero
      FROM jugadores
     WHERE numero IS NOT NULL
     GROUP BY equipo_id, numero
    HAVING COUNT(*) > 1
  ) AS repetidos
);

SET @sql_indice = IF(
  @indice_ya_existe > 0,
  'DO 0',
  IF(
    @hay_duplicados > 0,
    'DO 0',
    'ALTER TABLE jugadores ADD UNIQUE KEY uq_jugador_camiseta (equipo_id, numero)'
  )
);

PREPARE stmt_indice FROM @sql_indice;
EXECUTE stmt_indice;
DEALLOCATE PREPARE stmt_indice;

-- Si el índice no se pudo crear por duplicados, se dice exactamente cuál sobra.
SELECT IF(
  @hay_duplicados > 0,
  'AVISO: no se creó el índice único uq_jugador_camiseta porque hay dorsales repetidos dentro de un mismo equipo.',
  'uq_jugador_camiseta verificado.'
) AS estado_migracion;

SELECT equipo_id, numero, COUNT(*) AS repetidos, GROUP_CONCAT(id ORDER BY id) AS ids_afectados
  FROM jugadores
 WHERE numero IS NOT NULL
 GROUP BY equipo_id, numero
HAVING COUNT(*) > 1;

-- --- Índice único del nombre de equipo ---
SET @indice_ya_existe = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'equipos'
    AND INDEX_NAME   = 'uq_equipo_nombre'
);

SET @hay_duplicados = (
  SELECT COUNT(*) FROM (
    SELECT nombre
      FROM equipos
     GROUP BY nombre
    HAVING COUNT(*) > 1
  ) AS repetidos
);

SET @sql_indice = IF(
  @indice_ya_existe > 0,
  'DO 0',
  IF(
    @hay_duplicados > 0,
    'DO 0',
    'ALTER TABLE equipos ADD UNIQUE KEY uq_equipo_nombre (nombre)'
  )
);

PREPARE stmt_indice FROM @sql_indice;
EXECUTE stmt_indice;
DEALLOCATE PREPARE stmt_indice;

SELECT IF(
  @hay_duplicados > 0,
  'AVISO: no se creó el índice único uq_equipo_nombre porque hay equipos con el mismo nombre.',
  'uq_equipo_nombre verificado.'
) AS estado_migracion;

SELECT nombre, COUNT(*) AS repetidos, GROUP_CONCAT(id ORDER BY id) AS ids_afectados
  FROM equipos
 GROUP BY nombre
HAVING COUNT(*) > 1;
