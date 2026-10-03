-- Esquema de Base de Datos: Liga de Básquetbol
-- Motor: InnoDB | Charset: utf8mb4
-- Fecha: 2026-10-03

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
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
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
  CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_partido_visitante FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ÍNDICES RECOMENDADOS PARA CONSULTAS
-- =============================================
CREATE INDEX idx_jugador_equipo ON jugadores(equipo_id);
CREATE INDEX idx_partido_local ON partidos(equipo_local_id);
CREATE INDEX idx_partido_visitante ON partidos(equipo_visitante_id);
CREATE INDEX idx_partido_fecha ON partidos(fecha);
