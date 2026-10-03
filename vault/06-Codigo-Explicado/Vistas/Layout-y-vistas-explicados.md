# Layout y Vistas - Explicación

**main.php**: HTML base, nav condicional (logueado/no), muestra mensajes flash (error/exito), requiere vista con `../app/views/$vista.php`, BASE_URL CSS.

**Vistas auth**: forms POST, old/email, enlaces.

**Vistas equipos/jugadores**: tablas, enlaces crear/editar/eliminar con confirm, selects.

**Vistas partidos**: muestra marcador + resultado calculado, datetime-local.

**Vistas estadísticas**: tabla posiciones con #, signos DIF, slice 10.

**Seguridad**: `htmlspecialchars()` en todo dato dinámico.
