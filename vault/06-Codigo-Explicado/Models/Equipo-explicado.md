# Equipo.php - Explicación

Métodos:
- `crear($nombre,$ciudad,?$fundacion)` → bind NULL si vacío
- `actualizar($id,...)` → UPDATE con NULL handling
- `obtenerTodos()` → ORDER BY nombre ASC (override con orden)

Null-safe para YEAR opcional.
