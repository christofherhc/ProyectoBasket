-- =============================================
-- Datos de prueba para Liga de Básquetbol
-- Seguro de reimportar (usa INSERT IGNORE con IDs fijos)
-- =============================================

USE liga_basquet;

-- Usuarios de prueba (contraseña: password)
INSERT IGNORE INTO usuarios (id, nombre, email, password) VALUES
(1, 'Usuario Demo', 'demo@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Administrador', 'admin@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Equipos de prueba
INSERT IGNORE INTO equipos (id, nombre, ciudad, fundacion) VALUES
(1, 'Los Lakers', 'Los Angeles', 1947),
(2, 'Boston Celtics', 'Boston', 1946),
(3, 'Chicago Bulls', 'Chicago', 1966),
(4, 'Golden State Warriors', 'San Francisco', 1946);

-- Jugadores de prueba
INSERT IGNORE INTO jugadores (id, nombre, numero, posicion, equipo_id) VALUES
(1, 'LeBron James', 23, 'Alero', 1),
(2, 'Anthony Davis', 3, 'Pívot', 1),
(3, 'Austin Reaves', 15, 'Escolta', 1),
(4, 'Jayson Tatum', 0, 'Alero', 2),
(5, 'Jaylen Brown', 7, 'Alero', 2),
(6, 'Kristaps Porzingis', 8, 'Pívot', 2),
(7, 'DeMar DeRozan', 11, 'Escolta', 3),
(8, 'Nikola Vucevic', 9, 'Pívot', 3),
(9, 'Coby White', 0, 'Base', 3),
(10, 'Stephen Curry', 30, 'Base', 4),
(11, 'Klay Thompson', 11, 'Escolta', 4),
(12, 'Draymond Green', 23, 'Ala-Pívot', 4);

-- Partidos de prueba
INSERT IGNORE INTO partidos (id, fecha, equipo_local_id, equipo_visitante_id, puntos_local, puntos_visitante) VALUES
(1, '2026-10-01 19:00:00', 1, 2, 112, 108),
(2, '2026-10-02 20:00:00', 3, 4, 98, 105),
(3, '2026-10-05 19:30:00', 2, 3, 110, 95),
(4, '2026-10-06 21:00:00', 4, 1, 120, 118);
