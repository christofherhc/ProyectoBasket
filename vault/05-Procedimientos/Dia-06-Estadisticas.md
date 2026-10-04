# Día 06 - Estadísticas (cálculo en memoria)

**Fecha:** 2026-10-03
**Rama:** `main`
**Objetivo:** Crear la tabla de posiciones de la liga, calculada a partir de los partidos.
**Temas a comprender:** arrays asociativos anidados, bucles acumuladores, `usort()` con `<=>`, y por qué a veces lo correcto es calcular y no almacenar.

## 1. Contexto

Los 5 módulos anteriores seguían el mismo patrón: **leer de la base y mostrar**. Este es el primero que hace algo más: **calcular un dato que no existe en la base**.

No hay una tabla `estadisticas`. Las columnas PJ (jugados), PG (ganados), PE (empatados), PP (perdidos), GF (a favor), GC (en contra) y DIF (diferencia) **se calculan en cada visita a la página**, recorriendo los partidos.

**La pregunta del día:** ¿calcular en PHP o en SQL?

## 2. La decisión de diseño

| Enfoque | Cómo | Ventaja | Inconveniente |
|---|---|---|---|
| **En PHP** (elegido) | Bucles sobre los partidos | Legible, fácil de depurar | Recalcula en cada visita |
| En SQL | `GROUP BY` con `SUM`/`COUNT` | El motor lo resuelve rápido | Más difícil de leer y depurar |

**Se eligió PHP** por tres razones concretas:

1. **Escala pequeña:** 4 equipos y 4 partidos. El rendimiento no es un problema.
2. **Legibilidad:** las reglas se ven línea a línea, sin anidar agregaciones en SQL.
3. **Depuración:** se puede insertar un `var_dump($stats)` y ver exactamente qué pasa.

⚠️ **El límite de esta decisión:** con 50.000 partidos, este bucle se volvería lento y habría que moverlo a SQL o cachearlo. Elegir la opción simple es correcto **mientras la escala lo permita**.

## 3. Preparación

- Módulo de Partidos funcionando (los datos de origen).
- La decisión de no crear tabla `estadisticas` tomada y justificada.

## 4. Los tres pasos del cálculo

### Paso 6.1: Fase 1 — recopilar

```php
$partidos = $this->partidoModel->obtenerTodos();

$equiposMap = [];
foreach ($partidos as $p) {
    $equiposMap[$p['equipo_local_id']]     = $p['nombre_local'];
    $equiposMap[$p['equipo_visitante_id']] = $p['nombre_visitante'];
}
```

**El objetivo:** averiguar qué equipos han jugado, y de paso conseguir su nombre.

**El resultado es un array asociativo id → nombre:**
```php
$equiposMap = [
    1 => 'Los Lakers',
    2 => 'Boston Celtics',
    3 => 'Chicago Bulls',
    4 => 'Golden State Warriors',
];
```

**¿Por qué no usar el modelo `Equipo`?** Porque los nombres **ya vienen** en los partidos: el modelo `Partido` hace `LEFT JOIN equipos` y trae `nombre_local` y `nombre_visitante`. Pedirlos otra vez sería una segunda consulta para el mismo dato.

⚠️ **Consecuencia de esta decisión: solo aparecen los equipos que han jugado.** Un equipo recién creado, sin partidos, **no sale en la tabla de posiciones**. Es discutible si es lo correcto (en una liga real, un equipo sin partidos suele aparecer con 0-0). Está en la lista de limitaciones.

### Paso 6.2: Fase 2 — inicializar los contadores

```php
$stats = [];
foreach (array_unique(array_keys($equiposMap)) as $eid) {
    $eidInt = (int)$eid;
    $stats[$eidInt] = [
        'equipo_id' => $eidInt,
        'nombre' => $equiposMap[$eidInt] ?? '',
        'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0,
        'gf' => 0, 'gc' => 0, 'dif' => 0
    ];
}
```

**Cada equipo empieza con todos los contadores en 0.** Esto es lo que hace posible el patrón "acumulador":

```php
$stats[$id]['gf'] += $puntos;    // suma directa dentro del array anidado
```

⚠️ **PHP permite `+=` sobre una posición de un array anidado** sin variables intermedias. Es idiomático en PHP y evita ensuciar el código con variables temporales.

### Paso 6.3: Fase 3 — recorrer y acumular

```php
foreach ($partidos as $p) {
    $el = (int)$p['equipo_local_id'];
    $ev = (int)$p['equipo_visitante_id'];
    $pl = (int)$p['puntos_local'];
    $pv = (int)$p['puntos_visitante'];

    if (!isset($stats[$el]) || !isset($stats[$ev])) {
        continue;   // guarda: si no conozco ese equipo, salto el partido
    }

    $stats[$el]['pj']++;
    $stats[$ev]['pj']++;

    $stats[$el]['gf'] += $pl;   // el local anota
    $stats[$el]['gc'] += $pv;   // el local recibe
    $stats[$ev]['gf'] += $pv;   // el visitante anota
    $stats[$ev]['gc'] += $pl;   // el visitante recibe

    if ($pl > $pv) {
        $stats[$el]['pg']++;
        $stats[$ev]['pp']++;
    } elseif ($pv > $pl) {
        $stats[$ev]['pg']++;
        $stats[$el]['pp']++;
    } else {
        $stats[$el]['pe']++;
        $stats[$ev]['pe']++;
    }
}
```

**Lo más importante: cada partido afecta a DOS equipos.** Si el local gana, el local suma una victoria **y** el visitante suma una derrota. Por eso los contadores van siempre en pareja.

⚠️ **La guarda `if (!isset($stats[$el]) || !isset($stats[$ev])) continue;`** evita un fallo real. Sin ella, si un partido tuviera un `equipo_id` no registrado, `$stats[$el]['pj']++` daría un aviso de *"Undefined array key"* y `null++` no funcionaría. `continue` salta a la siguiente vuelta.

**Los contadores, explicados:**

| Abrev. | Significado | Cómo se calcula |
|---|---|---|
| PJ | Partidos Jugados | +1 por partido |
| PG | Partidos Ganados | +1 si ganó |
| PE | Partidos Empatados | +1 si empató |
| PP | Partidos Perdidos | +1 si perdió |
| GF | Puntos a Favor | Suma de los puntos que hizo |
| GC | Puntos en Contra | Suma de los que le hicieron |
| DIF | Diferencia | GF − GC |

⚠️ **"Goles" aquí son "puntos",** porque es básquetbol. Los nombres vienen del fútbol, pero PJ/PG/DIF son el estándar en cualquier deporte.

### Paso 6.4: Fase 4 — lo que depende de lo acumulado

```php
foreach ($stats as $k => $v) {
    $stats[$k]['dif'] = $v['gf'] - $v['gc'];
}
```

⚠️ **¿Por qué está fuera del bucle de partidos?** Porque la diferencia **depende de los valores ya sumados**. Calcularla dentro del bucle de partidos daría un valor parcial (solo el del partido actual).

**Este es el patrón clave de los algoritmos de agregación:**
```
primero acumula  →  después calcula lo que depende de lo acumulado
```

⚠️ **Modificar `$stats` mientras se recorre `$stats` parece peligroso.** Aquí es seguro: solo se asigna a claves **que ya existen** (`$k` viene del propio recorrido). No se añaden ni se borran elementos. Si la línea fuera `$stats['nuevo'] = ...`, sí podría comportarse mal.

### Paso 6.5: Fase 5 — ordenar por clasificación

```php
usort($stats, function ($a, $b) {
    if ($a['pg'] !== $b['pg']) {
        return $b['pg'] <=> $a['pg'];
    }
    return $b['dif'] <=> $a['dif'];
});
```

**`usort()` reordena con una función de comparación** que dice cuál va primero (negativo = primero, positivo = segundo, 0 = iguales).

⚠️ **El error clásico de estas funciones: el orden invertido.** Poner `$b['pg'] <=> $a['pg']` es lo que hace que quede **descendente** (el que más gana, primero). Si se escribiera `$a['pg'] <=> $b['pg']`, saldría ascendente: el último en la tabla sería el líder.

**El operador `<=>`** (spaceship, "nave espacial") es la forma corta de comparar:
```php
$a <=> $b     // equivale a: $a > $b ? 1 : ($a < $b ? -1 : 0)
$b <=> $a     // equivale a: $a < $b ? 1 : ($a > $b ? -1 : 0)
```

**El desempate por `dif` es una decisión de negocio real:** si dos equipos ganan lo mismo, va delante el que tiene mejor diferencia de puntos. Podría ser por puntos a favor, o por victories como local, según el reglamento. **Por eso no está en la base de datos: depende de las reglas que decidas aplicar.**

⚠️ **`usort()` pierde las claves** (reindexa el array). Si necesitaras conservar la clave, usa `uasort()`. Aquí da igual porque la vista solo recorre en orden.

### Paso 6.6: Fase 6 — pasar a la vista

```php
$this->view('estadisticas/index', [
    'titulo'   => 'Estadísticas de la Liga',
    'stats'    => $stats,
    'partidos' => $partidos
]);
```

**Se pasan los dos arrays:** `stats` (la tabla ordenada) y `partidos` (para la sección "Últimos Partidos", que ya viene ordenada por `fecha DESC` del modelo).

## 5. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/controllers/EstadisticasController.php` | crear | Único controlador sin CRUD: 6 fases de cálculo |
| `app/views/estadisticas/index.php` | crear | Tabla de posiciones + últimos partidos |
| `app/views/layouts/main.php` | editar | Añadir enlace "Estadísticas" al menú |
| `app/views/partidos/index.php` | — | Sin cambios, pero ahora sus datos alimentan las estadísticas |

## 6. Explicación técnica: por qué NO hay tabla `estadisticas`

Esta es la decisión más interesante del día.

**Si se guardaran las estadísticas en una tabla**, pasarían dos cosas:

1. Cada vez que se creara, editara o eliminara un partido, habría que **recalcular y actualizar** esa tabla.
2. Cualquier forgeto (un partido editado a mano en phpMyAdmin, un restored de un backup) dejaría la tabla **desincronizada** de los partidos. Y ese error es muy difícil de detectar.

**Al calcular en cada visita, ese problema no existe:** la tabla de posiciones es una **vista**, no una fuente de datos. Es siempre coherente con los partidos, porque se deriva de ellos en el momento.

⚠️ **El coste:** se recalcula en cada visita. Con muchos partidos sería lento, y ahí la solución es cachear (guardar el resultado en un archivo o en la sesión durante X minutos) en vez de duplicar datos.

**La regla general:** duplicar datos siempre es un coste. Antes de hacerlo, pregunta si puedes derivarlo en el momento.

## 7. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| La página carga y muestra la tabla | ✅ |
| Muestra la tabla de posiciones con 4 equipos | ✅ |
| Muestra los últimos partidos | ✅ |
| Columnas PJ/PG/PE/PP/GF/GC/DIF presentes | ✅ |
| Celtics: 2 partidos, 1 ganado, 1 perdido (1 local + 1 visitante) | ✅ |
| El orden respeta ganados y luego diferencia | ✅ |
| Los marcadores coinciden con los partidos reales | ✅ |
| Coherencia: total de PG = total de PP = nº de partidos | ✅ |

**Verificación de la coherencia (importante):** con 4 partidos, el total de victorias de toda la tabla debe ser 4, y el de derrotas también. Está comprobado que se cumple. Es el tipo de comprobación que detecta errores de cálculo sin necesidad de revisar cada celda.

## 8. Commits realizados

```
feat: añade módulo de estadísticas con cálculo en memoria y tabla de posiciones
```

## 9. Aprendizajes del día

1. **Un bucle acumulador tiene tres fases:** inicializar a cero → acumular → calcular lo derivado. **Inicializar a cero no es opcional**: `+=` sobre un índice inexistente da error.
2. **Lo que depende de lo acumulado va en un bucle aparte.** Calcularlo en medio del accumulation da valores parciales.
3. **`usort()` ordena según el signo que devuelve la función de comparación**, y el orden se invierte al cambiar los operandos. El error más común aquí es ponerlo al revés.
4. **`<=>` es la forma corta y legible de comparar**, y siempre hay que decidir si se quiere ascendente o descendente.
5. **No dupliques datos si puedes derivarlos.** Una tabla de estadísticas guardada puede desincronizarse; una calculada, no.
6. **Calcular en PHP es válido mientras la escala sea pequeña.** No es "menos profesional": es elegir la solución que el problema necesita.
7. **Desempates y criterios de orden son reglas de negocio**, no detalles técnicos. Van en el código de aplicación, no en la base.

## 10. Dudas pendientes

⚠️ **Limitaciones de este módulo:**

| # | Limitación | Gravedad |
|---|---|---|
| 1 | Los equipos **sin partidos no aparecen** en la tabla | Media |
| 2 | Rendimiento lineal con muchos partidos | Media (a escala) |
| 3 | Solo 7 estadísticas (sin % de aciertos, rachas ni partidos como local/visitante) | Baja |
| 4 | El criterio de desempate no es el reglamentario real | Info |

**La 1 se nota al probar:** crea un equipo nuevo en Equipos, ve a Estadísticas y **no aparecerá**. Aparecerá cuando le crees un partido.

**Arreglo:** empezar la tabla desde `$equipoModel->obtenerTodos()` en vez de desde los partidos:
```php
foreach ($this->equipoModel->obtenerTodos() as $e) {
    $stats[$e['id']] = ['nombre' => $e['nombre'], 'pj' => 0, 'pg' => 0, ...];
}
```
⚠️ **El tradeoff:** con este cambio aparecen todos los equipos, pero la vista necesita proteger contra `nombre` nulo (por si un partido tiene un equipo inexistente).

## 11. Próximos pasos

**Día 07 - Documento técnico:**
- Escribir el documento que explica las decisiones, los problemas encontrados y cómo se resolvieron.

**Día 08 - Verificación:**
- Batería de pruebas funcionales de todo el sistema.
- Corrección de bugs reales detectados por las pruebas (entre ellos el del `datetime-local`).