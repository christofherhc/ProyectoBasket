# Diario de Aprendizaje - Día 05

**Fecha:** 2026-10-03
**Módulo:** Partidos (doble JOIN + `datetime-local`)

## Aprendido hoy

**Doble JOIN a la misma tabla**
Un partido tiene **dos** equipos. La tabla `equipos` aparece dos veces en la consulta, así que necesita dos alias:

```sql
LEFT JOIN equipos el ON el.id = p.equipo_local_id
LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
```

Y para distinguirlos en la vista: `el.nombre AS nombre_local`, `ev.nombre AS nombre_visitante`.

⚠️ **Esto no es opcional:** sin alias, MySQL dice `Not unique table/alias: 'e'`.

**El resultado del partido se calcula, no se guarda**
La tabla `partidos` solo tiene `puntos_local` y `puntos_visitante`. El texto "Gana Local" / "Gana Visitante" / "Empate" se decide en PHP:

```php
if ($pl > $pv)      { $resultado = 'Gana Local'; }
elseif ($pv > $pl) { $resultado = 'Gana Visitante'; }
else               { $resultado = 'Empate'; }
```

**Regla de negocio en el controlador**
```php
if ($equipoLocalId === $equipoVisitanteId) {
    $_SESSION['error'] = 'El equipo local y visitante no pueden ser iguales';
}
```
⚠️ **Esta regla NO está en la base de datos.** Solo existe en PHP, así que un `INSERT` hecho a mano en phpMyAdmin saltaría la restricción. Aparece como limitación pendiente.

**El `datetime-local`**
MySQL guarda `2026-10-01 19:00:00` (espacio). El `<input type="datetime-local">` quiere `2026-10-01T19:00` (letra T). Esa diferencia hizo que el campo apareciera vacío el día 8.

## Lo que más me costó

⚠️ **El doble JOIN.** Al principio probé `JOIN equipos e ON e.id = p.equipo_local_id OR e.id = p.equipo_visitante_id` y "funcionaba", pero devolvía una fila por equipo y el resultado se duplicaba. La solución son dos alias.

⚠️ **Entender que un `LEFT JOIN` a la misma tabla no es un problema.** El motor no se confunde: son dos "puertas" independientes al mismo conjunto de datos.

⚠️ **Comparar ids como cadenas.** `$_POST['equipo_local_id']` llega como `'1'` (cadena). `===` entre `'1'` y `1` da `false`, así que la comparación de "son el mismo equipo" fallaba. Se resolvió con `intval()` en ambos lados **antes** de comparar. Este bug pasó desapercibido hasta las pruebas del día 8.

## Dudas aclaradas
- ¿Se puede validar "equipos distintos" en SQL? Sí, con `CHECK (equipo_local_id <> equipo_visitante_id)`. Está anotado como mejora pendiente.
- ¿Por qué se guarda la fecha como `DATETIME` y no como texto? Porque permite ordenar por fecha con `ORDER BY` de forma correcta y comparar rangos.

## Errores de hoy
- `Not unique table/alias: 'e'` → faltaba un alias para el segundo JOIN.
- Partido duplicado en el listado → un solo JOIN con `OR` en vez de dos.
- Fechas ordenadas como texto (`2026-10-9` antes que `2026-10-12`) → al usar `VARCHAR` en vez de `DATETIME`.

## Limitaciones detectadas (anotadas, no resueltas)
- No se comprueba que los equipos existan al crear (error FK sin capturar).
- No se valida el formato de la fecha con `DateTime`.
- `$puntosLocal = (int)($_POST[...] ?? 0)` confunde "campo vacío" con "0".

## Decisiones tomadas
- El resultado ("Gana Local") se calcula en PHP, no se guarda en una columna: es derivable.
- La fecha se guarda como `DATETIME` completo, aunque el formulario solo permita minutos.

## Próximo
**Día 06:** Estadísticas, donde por primera vez el cálculo no es un CRUD sino una agregación sobre todos los partidos.