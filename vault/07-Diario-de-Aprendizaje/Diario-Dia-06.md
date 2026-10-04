# Diario de Aprendizaje - Día 06

**Fecha:** 2026-10-03
**Módulo:** Estadísticas (cálculo en memoria)

## Aprendido hoy

**Un cálculo agregado**
Por primera vez no hubo CRUD: los datos (PJ, PG, PE, PP, GF, GC, DIF) **no existen en la base**, se calculan en cada visita a partir de los partidos.

**El patrón de tres fases**
```php
// 1. Inicializar a cero
$stats[$id] = ['pj' => 0, 'pg' => 0, ...];

// 2. Acumular (recorriendo los partidos)
$stats[$el]['gf'] += $pl;
$stats[$el]['pj']++;

// 3. Calcular lo que depende de lo acumulado
$stats[$k]['dif'] = $stats[$k]['gf'] - $stats[$k]['gc'];
```
⚠️ **El paso 3 tiene que ir aparte.** Calcular la diferencia dentro del bucle de partidos daría un valor parcial.

**Cada partido afecta a dos equipos**
Si el local gana, el local suma una victoria y el visitante suma una derrota. Los contadores van siempre en pareja.

**Ordenar con `usort()`**
```php
usort($stats, fn($a, $b) => $b['pg'] <=> $a['pg'] ?: $b['dif'] <=> $a['dif']);
```
⚠️ **El signo de la comparación decide el orden.** `$b <=> $a` = descendente (el líder primero). `$a <=> $b` = ascendente (el líder al final). Este fue el error más fácil de cometer.

**El criterio de desempate es una regla de negocio**
Si dos equipos ganan lo mismo, va delante el que tiene mejor diferencia. Podría ser por puntos a favor o por victorias como local, según el reglamento. **Va en el código de aplicación, no en la base de datos.**

## Lo que más me costó

⚠️ **El `continue` de seguridad.** Sin `if (!isset($stats[$el])) continue;`, un partido con un `equipo_id` desconocido daría `Undefined array key` y `null++` no incrementaría nada. Se debugueó leyendo el array con una fila inválida.

⚠️ **La decisión de no crear la tabla `estadisticas`.** Los dos argumentos que convencieron:
1. Si se guardara, habría que **recalcular y actualizar** en cada alta, edición o borrado de partido.
2. Cualquier forgeto (un partido editado a mano, un restore de backup) dejaría la tabla **desincronizada**.

**Regla general:** duplicar datos es un coste. Antes de hacerlo, preguntar si se puede derivar en el momento.

⚠️ **Que solo aparezcan los equipos que han jugado.** La tabla se construye desde los partidos, así que un equipo nuevo y sin partidos **no sale**. Se anotó como limitación (media) con el arreglo propuesto: empezar desde `EquipoModel::obtenerTodos()`.

## Dudas aclaradas
- ¿`+=` sobre una posición de un array anidado? Sí, es idiomático en PHP y evita variables intermedias.
- ¿Es "menos profesional" calcular en PHP? No. Es la opción correcta mientras la escala sea pequeña (4 equipos, 4 partidos). A 50.000 partidos habría que moverlo a SQL o cachearlo.
- ¿Modificar `$stats` mientras se recorre `$stats` es peligroso? Aquí no: solo se asignan claves que ya existen. Si se añadiera una clave nueva, sí podría comporta mal.

## Errores de hoy
- Tabla ordenada al revés (`$a['pg'] <=> $b['pg']` en vez de `$b <=> $a`).
- `Undefined array key` al procesar un partido con equipo inexistente → faltaba la guarda `isset`.
- Diferencia calculada dentro del bucle de partidos → valores parciales en vez de la diferencia total.

## Decisiones tomadas
- Calcular en PHP, no en SQL, por legibilidad y depuración a esta escala.
- Desempate por victories y después por diferencia.
- Los equipos salen de los partidos (no de la tabla `equipos`), lo que implica la limitación de los equipos sin partidos.

## Próximo
**Día 07:** escribir el documento técnico y montar el vault.