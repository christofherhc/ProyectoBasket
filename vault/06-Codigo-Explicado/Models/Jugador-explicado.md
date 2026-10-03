# Jugador.php - Explicación

JOIN: `SELECT j.*, e.nombre AS nombre_equipo LEFT JOIN equipos e ON e.id=j.equipo_id`

NULL handling para numero/posicion. `obtenerPorId()` con JOIN.
