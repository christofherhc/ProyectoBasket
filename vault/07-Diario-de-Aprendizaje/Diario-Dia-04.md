# Diario de Aprendizaje - Día 04

**Fecha:** 2026-10-03
**Módulo:** Jugadores (CRUD con JOIN)

## Aprendido hoy

**Relaciones entre tablas**
- Un jugador tiene un `equipo_id` que apunta a `equipos.id`. Esa relación es una **clave foránea**, y en SQL se resuelve con un `JOIN`.
- `LEFT JOIN` en lugar de `JOIN` interno: si un jugador tuviera un `equipo_id` inexistente, la fila **seguiría apareciendo** (con el nombre en `null`) en vez de desaparecer de la lista sin aviso.

**El desplegable dependiente**
```html
<select name="equipo_id">
    <?php foreach ($equipos as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $selected ?>>
            <?= htmlspecialchars($e['nombre']) ?>
        </option>
    <?php endforeach; ?>
</select>
```
El controlador carga la lista de equipos y la pasa a la vista. Así el usuario **elige de una lista** en vez de escribir un id a mano, y no puede inventarse uno.

**NULL y su opcode**
- `fundacion` puede ser `NULL`. Al insertar, `execute([$fundacion])` manda la cadena `''`, y en una columna `INT` eso se convierte en `0`, no en `NULL`. Para guardar `NULL` de verdad hay que usar `bindValue(..., PDO::PARAM_NULL)`.

**Numeración y colores**
- El número de camiseta se guarda como `INT UNSIGNED NULL` (el `UNSIGNED` impide negativos en la propia base) y la posición como `VARCHAR(50) NULL`, es decir, texto libre.

## Lo que más me costó

⚠️ **Entender por qué `LEFT JOIN` y no `JOIN`.** Con `JOIN` interno, un dato huérfano desaparece en silencio: el listado se ve "limpio" pero incompleto, y no hay ningún error que lo delate. Con `LEFT JOIN` el problema se hace visible (`nombre` en `null`) y se puede diagnosticar.

⚠️ **El `NULL` vs `''`.** Son cosas distintas en PHP y en SQL: `'' == null` es `true` en PHP, pero en la base `''` y `NULL` son valores diferentes. Fue el punto más fácil de equivocar del día.

⚠️ **Los `<option>` seleccionados.** Al recargar el formulario tras un error hay que marcar el `<option>` que el usuario había elegido, o se pierde la selección:
```php
<option value="<?= $e['id'] ?>" <?= $old['equipo_id'] == $e['id'] ? 'selected' : '' ?>>
```

## Dudas aclaradas
- ¿Un `SELECT` con `JOIN` necesita `GROUP BY`? No, solo si se agrupan o se cuentan columnas.
- ¿El orden de las tablas en el `FROM` importa en un `JOIN`? No: `LEFT JOIN equipos e ON e.id = j.equipo_id` funciona igual sin importar el orden de las tablas en el `FROM`.

## Errores de hoy
- `Undefined array key "equipo"` en la vista → el JOIN no tenía `e.nombre AS equipo` y la vista pedía una clave que no existía.
- SELECT con `ORDER BY` que no respetaba el orden → el `ORDER BY j.nombre` debe referirse a la columna de `jugadores`, no a la del JOIN.

## Limitaciones detectadas (anotadas, no resueltas)
- No se valida la `posicion` contra la lista de 5 válidas (se acepta cualquier texto).
- No se valida la unicidad del número de camiseta por equipo.
- El select de equipos no remembería al usuario cuál era (se corregió con `selected`).

## Decisiones tomadas
- La posición se guarda como `VARCHAR(50) NULL`, **no** como `ENUM`. La validación de que sea una de las cinco posiciones válidas no existe: solo la limita el `<select>` del formulario, que se puede saltar. Podría haberse usado `ENUM('Base','Escolta','Alero','Ala-Pívot','Pívot')` para que la base rechazara lo inválido.
- Los listados ordenan por `nombre`, que es lo que espera un usuario, no por `id`.

## Próximo
**Día 05:** Partidos, el módulo más complejo: dos JOIN a la misma tabla y un `datetime-local`.