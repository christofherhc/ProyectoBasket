-- Datos de prueba para Liga de Básquetbol
-- Útiles para probar la aplicación rápidamente

-- Usuarios de prueba
INSERT INTO usuarios (nombre, email, password) VALUES
('Usuario Demo', 'demo@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Administrador', 'admin@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- NOTA: Contraseña para ambos es 'password'
-- Hash generado con password_hash('password', PASSWORD_DEFAULT)

-- Equipos de prueba
INSERT INTO equipos (nombre, ciudad, fundacion) VALUES
('Los Lakers', 'Los Angeles', 1947),
('Boston Celtics', 'Boston', 1946),
('Chicago Bulls', 'Chicago', 1966),
('Golden State Warriors', 'San Francisco', 1946);

-- Jugadores de prueba
INSERT INTO jugadores (nombre, numero, posicion, equipo_id) VALUES
('LeBron James', 23, 'Alero', 1),
('Anthony Davis', 3, 'Pívot', 1),
('Austin Reaves', 15, 'Escolta', 1),
('Jayson Tatum', 0, 'Alero', 2),
('Jaylen Brown', 7, 'Alero', 2),
('Kristaps Porzingis', 8, 'Pívot', 2),
('DeMar DeRozan', 11, 'Escolta', 3),
('Nikola Vucevic', 9, 'Pívot', 3),
('Coby White', 0, 'Base', 3),
('Stephen Curry', 30, 'Base', 4),
('Klay Thompson', 11, 'Escolta', 4),
('Draymond Green', 23, 'Ala-Pívot', 4);

-- Partidos de prueba
INSERT INTO partidos (fecha, equipo_local_id, equipo_visitante_id, puntos_local, puntos_visitante) VALUES
('2026-10-01 19:00:00', 1, 2, 112, 108),
('2026-10-02 20:00:00', 3, 4, 98, 105),
('2026-10-05 19:30:00', 2, 3, 110, 95),
('2026-10-06 21:00:00', 4, 1, 120, 118);
