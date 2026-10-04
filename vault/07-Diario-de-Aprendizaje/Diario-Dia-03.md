# Diario de Aprendizaje - Día 03

**Fecha:** 2026-10-03
**Módulo:** Equipos (primer CRUD completo)

## Aprendido hoy

**El patrón CRUD completo**
Por primera vez se vio el ciclo entero de un módulo: **Modelo → Controlador → Vista**. Hasta el día 2 solo había arquitectura; hoy se añadió una entidad real.

- **Modelo (`Equipo`)**: `obtenerTodos()` con `ORDER BY nombre`, `obtenerPorId()`, `crear()`, `actualizar()`, `eliminar()`. Todos heredan la conexión de `Model`.
- **Controlador (`EquiposController`)**: una acción por operación (`index`, `crear`, `guardar`, `editar`, `actualizar`, `eliminar`), cada una protegida con `requiereLogin()`.
- **Vistas**: `index.php` (tabla), `crear.php` y `editar.php` (formularios).

**Validación y UX**
- Validar **siempre en el servidor**. El HTML5 (`required`, `type="email"`) ayuda, pero se puede esquivar.
- Cuando la validación falla, se recarga el formulario **con los datos que el usuario ya escribió** (`$old`), para no obligarle a teclear todo de nuevo. Ese detalle se nota mucho.
- Los mensajes van a `$_SESSION['error']` / `$_SESSION['exito']` y el layout los muestra una sola vez.

**Extras**
- `confirm()` en JavaScript antes de eliminar.
- `obtenerUltimoId()` heredado de `Model` para saber qué equipo se acaba de crear.

## Lo que más me costó

⚠️ **`actualizar()` no comprobaba que el registro existiera.** Si el id no existe, el `UPDATE`affected 0 filas y la appRespondía "equipo actualizado" igualmente. Ahora está anotado como limitación pendiente (gravidad baja).

⚠️ **La diferencia entre "campo vacío" y "valor 0".** `$_POST['fundacion']` vacío llega como `''`, y `'' == 0` es `true` en PHP. Con `if (!$fundacion)` seuba a 0 una fundación vacía. La solución fue `=== ''` para comprobar vacío y `intval()` para el resto.

⚠️ **Ver que `htmlspecialchars()` no es opcional.** Con el nombre `<b>Los Angeles</b>`, sin escapar la tabla muestra negrita; con `htmlspecialchars()` muestra el texto literal. Se probó también con `<script>`.

## Dudas aclaradas
- ¿Por qué `eliminar()` acepta GET? Porque el enlace `<a href="...?action=eliminar&id=3">` es lo más simple. En producción debería ser POST + token CSRF.
- ¿Por qué `ORDER BY nombre` en el modelo y no en la vista? Porque el orden es parte de la consulta, y la vista solo muestra.

## Errores de hoy
- `Base table or view not found: ''` → `$tabla` no se asignó en el constructor del modelo.
- Doble `htmlspecialchars()` en un campo ya escapado → mostraba `&amp;lt;`.
- Mensaje de éxito que no aparecía tras crear → faltaba el `header('Location: ...')` para hacer la redirección PRG.

## Decisiones tomadas
- `fundacion` acepta vacío y se guarda como `NULL` (con `PDO::PARAM_NULL`), no como 0: "fundación desconocida" no es lo mismo que "fundada en el año 0".
- Los formularios de crear y editar se separan en dos archivos, aunque el HTML sea casi idéntico.

## Próximo
**Día 04:** Jugadores, con la complicación de que cada jugador pertenece a un equipo → JOIN y desplegable dependiente.