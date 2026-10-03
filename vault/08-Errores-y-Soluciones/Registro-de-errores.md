# Registro de Errores y Soluciones

| Fecha | Error | Causa | Solución |
|---|---|---|---|
| 2026-10-03 | datetime-local no mostraba valor | DATETIME DB formato `YYYY-MM-DD HH:MM:SS` distinto a `YYYY-MM-DDTHH:MM` | `substr($fecha,0,16)` en editar |
| 2026-10-03 | ambigüedad columnas en JOIN partidos | ambos equipos de misma tabla sin alias | usar `el.nombre AS nombre_local`, `ev.nombre AS nombre_visitante` |
| 2026-10-03 | fundación/número guardaba vacío como 0 | comparación estricta | tratar '' como NULL, bind PDO::PARAM_NULL |
| 2026-10-03 | redirect tras POST | posible reenvío | usar header + exit (PRG) |
| 2026-10-03 | mensajes duplicados | no unset tras mostrar | unset $_SESSION['error']/['exito'] |
| 2026-10-03 | session fixation | no regen ID | `session_regenerate_id(true)` tras login OK |
| 2026-10-03 | rutas relativas | require desde subcarpeta | usar `__DIR__` + `../` coherentes |

Lecciones: escapar siempre, prepared statements, validación servidor, NULL handling.
