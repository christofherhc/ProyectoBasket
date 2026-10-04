# EstadisticasController (explicado)

**Archivo real:** `app/controllers/EstadisticasController.php`
**Rol:** calcula la tabla de posiciones a partir de los partidos. **Es el único controlador sin CRUD.**

## 1. Qué lo hace distinto

Los otros 4 hacen el mismo ciclo: leer datos → pasarlos a la vista. Este **calcula** algo que no está en la base.

**No existe una tabla `estadisticas`.** Las columnas "PJ", "PG", "PE", "PP", "GF", "GC" y "DIF" no están guardadas en ningún sitio: se calculan en cada visita a la página, en PHP, recorriendo los partidos.

**Esa es una decisión de diseño muy relevante.** Hay dos enfoques posibles:

| Enfoque | Cómo | Ventaja | Inconveniente |
|---|---|---|---|
| **En PHP** (este proyecto) | Calcular al vuelo con bucles | Simple, sin nada que sincronizar | Recalcular en cada visita |
| En SQL | `GROUP BY` con `SUM`/`COUNT` | El motor lo hace rápido | Más difícil de entender |

Se eligió PHP porque: (1) las reglas son fáciles de ver línea a línea, (2) con una liga de 4 equipos y 4 partidos no hay problema de rendimiento, y (3) es mucho más fácil depurar con `var_dump()`.

⚠️ **El límite de este enfoque:** con 50.000 partidos, este bucle se volvería lento. En ese momento habría que moverlo a SQL o cachearlo. Está bien elegir la opción simple cuando la escala es pequeña.

## 2. El flujo completo, paso a paso

El método son tres fases: **recopilar → calcular → ordenar**.

### Fase 1: construir un mapa de equipos

```php
$partidos = $this->partidoModel->obtenerTodos();

$equiposMap = [];
foreach ($partidos as $p) {
    $equiposMap[$p['equipo_local_id']]     = $p['nombre_local'];
    $equiposMap[$p['equipo_visitante_id']] = $p['nombre_visitante'];
}
```

**El objetivo de este paso:** averiguar qué equipos han jugado algún partido. Con los 4 partidos de prueba aparecen los 4 equipos.

**`$equiposMap` es un array asociativo** que mapea id → nombre:

```php
$equiposMap = [
    1 => 'Los Lakers',
    2 => 'Boston Celtics',
    3 => 'Chicago Bulls',
    4 => 'Golden State Warriors',
];
```

⚠️ **¿Por qué no usar el modelo `Equipo`?** Porque aquí los nombres **ya vienen** en los partidos (el modelo `Partido` hace `LEFT JOIN equipos` y trae `nombre_local` y `nombre_visitante`). Si los trajera otra vez, sería una segunda consulta para el mismo dato.

**La consecuencia de diseño:** solo aparecen en la tabla los equipos **que han jugado al menos un partido**. Un equipo recién creado, sin partidos, **no sale en la tabla de posiciones**.

Es discutible si es lo correcto: en una liga real, un equipo sin partidos suele tener 0-0 y sí debería aparecer. Para arreglarlo habría que usar el modelo `Equipo` y empezar la tabla con los 4, aunque no hayan jugado:

```php
foreach ($this->equipoModel->obtenerTodos() as $e) {
    $stats[$e['id']] = ['nombre' => $e['nombre'], 'pj' => 0, 'pg' => 0, ...];
}
```

⚠️ **Limitación conocida.** En los datos de prueba los 4 equipos han jugado, así que no se nota. Crea un equipo nuevo y comprobarás que no aparece en las estadísticas.

⚠️ **Y un detalle sutil:** en la línea 23 se usa `$p['equipo_local_id']` (el id **crudo** de la base) y en la 24 `$p['equipo_visitante_id']`. Como la clave del array PHP se convierte a string automáticamente, `['1' => ...]` y `[1 => ...]` son **la misma clave**. Por eso el `$eidInt = (int)$eid` de después funciona sin sorpresas. Pero es una mezcla de tipos que confunde al leer.

### Fase 2: inicializar los contadores

```php
$stats = [];
foreach (array_unique(array_keys($equiposMap)) as $eid) {
    $eidInt = (int)$eid;
    $stats[$eidInt] = [
        'equipo_id' => $eidInt,
        'nombre'    => $equiposMap[$eidInt] ?? '',
        'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0,
        'gf' => 0, 'gc' => 0, 'dif' => 0
    ];
}
```

**Cada equipo empieza con todos los contadores en 0.** Esto es fundamental para el patrón "acumulador":

```
$stats[$id]['gf'] += $puntos;      // suma dentro del array anidado
```

PHP permite `+=` sobre una posición de un array anidado sin intermediates. Es idiomático en PHP y evita las variables temporales.

**`array_keys()` + `array_unique()`** → `array_keys($equiposMap)` da `[1, 2, 3, 4]`, y `array_unique()` quita duplicados (aunque aquí no hacen falta, porque un equipo aparece en muchas filas y la clave del mapa ya lo unifica).

⚠️ `$equiposMap[$eidInt] ?? ''` — el `??` evita un aviso si el nombre no estuviera. Es una prefectura que aquí no se da.

### Fase 3: recorrer los partidos y acumular

```php
foreach ($partidos as $p) {
    $el = (int)$p['equipo_local_id'];
    $ev = (int)$p['equipo_visitante_id'];
    $pl = (int)$p['puntos_local'];
    $pv = (int)$p['puntos_visitante'];

    if (!isset($stats[$el]) || !isset($stats[$ev])) {
        continue;   // ← la guarda que evita avisos
    }

    $stats[$el]['pj']++;        // ambos jugado
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

**Lo importante: cada partido afecta a DOS equipos.** Si el local gana, el local suma una victoria **y** el visitante suma una derrota. Por eso los contadores van en pareja.

⚠️ **`if (!isset($stats[$el]) || !isset($stats[$ev])) { continue; }`** es una guarda importante. Sin ella, si un partido tuviera un `equipo_id` que no está en el mapa, `$stats[$el]['pj']++` daría un aviso de *"Undefined array key"* y, peor, PHP crearía el elemento con valor `null` y `null++` no funcionaría como se espera.

`continue` salta a la siguiente vuelta del `foreach`. Es el patrón correcto para "si esto no me sirve, sáltatelo".

**Los contadores, explicados:**

| Abreviatura | Significado | Cómo se calcula |
|---|---|---|
| PJ | Partidos Jugados | +1 por cada partido |
| PG | Partidos Ganados | +1 si ganó |
| PP | Partidos Perdidos | +1 si perdió |
| PE | Partidos Empatados | +1 si empató |
| GF | Goles/puntos a favor | Suma de los puntos que hizo |
| GC | Goles/puntos en contra | Suma de los puntos que le hicieron |
| DIF | Diferencia | GF − GC |

⚠️ **Aquí "goles" son "puntos"**, porque es básquetbol. Los nombres vienen del fútbol, pero en este proyecto significan puntos anotados. Está bien: usar PJ/PG/PE/PP/DIF es el estándar en deportes de cualquier tipo.

**Verificado con los datos de prueba:**

| Equipo | PJ | PG | PP | PE | GF | GC | DIF |
|---|---|---|---|---|---|---|---|
| Boston Celtics | 2 | 1 | 1 | 0 | 218 | 207 | +11 |
| Los Lakers | 2 | 1 | 1 | 0 | 230 | 226 | +4 |
| Chicago Bulls | 2 | 0 | 2 | 0 | 193 | 208 | −15 |
| Golden State | 2 | 1 | 1 | 0 | 223 | 216 | +7 |

Los Celtics juegan los partidos 1 (visitante, pierden) y 3 (local, ganan) → 1-1. Correcto.

### Fase 4: calcular la diferencia

```php
foreach ($stats as $k => $v) {
    $stats[$k]['dif'] = $v['gf'] - $v['gc'];
}
```

**¿Por qué está fuera del bucle anterior?** Porque la diferencia **depende de los valores ya acumulados**. Calcularla dentro del bucle de partidos daría un valor parcial. Esta separación "primero sumo, después calculo lo que depende de lo sumado" es la clave de estos algoritmos de agregación.

**`foreach ($stats as $k => $v)` mientras se modifica `$stats`.** Parece peligroso, y en general lo es (modificar un array mientras lo recorres puede saltarse elementos). Aquí es seguro: solo se asigna a **claves que ya existen** (`$k` viene del propio recorrido), no se añaden ni se borran.

⚠️ **No es un error, pero sí una zona a tener en cuenta.** Si en esa línea se hiciera `$stats['nuevo'] = ...`, sí que podría comportarse mal.

### Fase 5: ordenar por clasificación

```php
usort($stats, function ($a, $b) {
    if ($a['pg'] !== $b['pg']) {
        return $b['pg'] <=> $a['pg'];
    }
    return $b['dif'] <=> $a['dif'];
});
```

**`usort()` reordena el array con una función de comparación.** La función recibe dos elementos y dice cuál va primero (negativo = primero, positivo = segundo, 0 = iguales).

**El operador `<=>` ( spaceship, "nave espacial")** es la forma corta de comparar:

| Expresión larga | Equivale a |
|---|---|
| `$a <=> $b` | `$a > $b ? 1 : ($a < $b ? -1 : 0)` |
| `$b <=> $a` | `$a < $b ? 1 : ($a > $b ? -1 : 0)` |

**⚠️ `$b['pg'] <=> $a['pg']` y no `$a['pg'] <=> $b['pg']`: es lo que hace que quede en orden descendente.** Es el error clásico de estas funciones. Ponerlo al revés ordenaría ascendentemente y la tabla saldría con el último en primero.

**El desempate por `dif`** es una decisión de criterio deportivo real: si dos equipos ganan lo mismo, va delante el que tiene mejor diferencia de puntos. Es una decisión de negocio, y por eso **no está en la base de datos**: depende de las reglas que quieras aplicar (podrías desempatar por puntos a favor, o por local/visitante ganados).

⚠️ **`usort()` pierde las claves.** `usort` reindexa el array, así que después `$stats` es `[0 => ..., 1 => ...]`. Si necesitas conservar la clave (por ejemplo, para buscar por id después), usa `uasort()`. Aquí da igual porque la vista solo recorre en orden.

⚠️ **`!==` en vez de `!=`.** Es una comparación estricta de enteros. En la práctica da igual, pero con `!=` PHP haría una conversión de tipos antes de comparar.

### Fase 6: pasar a la vista

```php
$this->view('estadisticas/index', [
    'titulo'  => 'Estadísticas de la Liga',
    'stats'   => $stats,
    'partidos' => $partidos
]);
```

**Se pasan los dos arrays:** `stats` (la tabla ya ordenada) y `partidos` (para la sección "Últimos Partidos").

⚠️ `$partidos` viene de `obtenerTodos()`, que ya trae `ORDER BY fecha DESC`. Así que está ordenado del más reciente al más antiguo. Por eso esa sección "no necesita ordenar nada" en la vista.

## 3. Qué muestra la vista

Dos secciones:

**Tabla de posiciones:**
```php
<?php foreach ($stats as $s): ?>
    <tr>
        <td><?= htmlspecialchars($s['nombre']) ?></td>
        <td><?= (int)$s['pj'] ?></td>
        <td><?= (int)$s['pg'] ?></td>
        ...
        <td><?= $s['dif'] > 0 ? '+' . $s['dif'] : $s['dif'] ?></td>
    </tr>
<?php endforeach; ?>
```

⚠️ **Los números también se escapan (con `(int)`).** Parece que los enteros no necesitan `htmlspecialchars`, y es cierto que no. Pero aplicar la conversión al imprimir un número es buena costumbre: si un día un cálculo estuviera mal y `$s['pj']` fuera una cadena con HTML, el `(int)` la neutraliza igual. **"Escapa al imprimir, sin excepción, aunque parezca innecesario."**

⚠️ **El `+` manual de la diferencia.** `$s['dif']` es `11`, y se muestra como `+11`. Los números positivos en tablas de posiciones llevan `+` por convenio. Se hace con `'+' . $s['dif']`.

**Últimos partidos:**
```php
<?php foreach (array_slice($partidos, 0, 5) as $p): ?>
    ...
<?php endforeach; ?>
```

`array_slice($partidos, 0, 5)` toma los **5 primeros** en vez de todos. Como ya vienen ordenados por fecha descendente, son los 5 últimos partidos jugados. Lo de "últimos" es por el orden, no por el nombre del método.

## 4. Por qué no hay `crear()` ni `editar()`

Porque no hay nada que crear ni editar: las estadísticas no se guardan, se derivan.

⚠️ **Eso significa que "modificar" una estadística es imposible.** Si el marcador de un partido está mal, hay que corregir el partido. Es una buena propiedad: **no hay datos que puedan desincronizarse**, porque no están duplicados.

Ese es el argumento a favor de calcular en lugar de almacenar: **la tabla de posiciones es una vista, no una fuente de datos.**

## 5. Limitaciones conocidas

| # | Limitación | Gravedad | Arreglo |
|---|---|---|---|
| 1 | Los equipos sin partidos no aparecen | Media | Empezar desde `$equipoModel->obtenerTodos()` |
| 2 | Rendimiento lineal con muchos partidos | Media (a escala) | Mover a `GROUP BY` en SQL o cachear |
| 3 | Solo 7 estadísticas (sin % de aciertos ni rachas) | Baja | Añadir más acumuladores |
| 4 | No se muestra quién fue local o visitante en la tabla | Baja | Añadir columnas |
| 5 | El desempate no es el reglamentario real | Info | Decidir el criterio con el profesor |

**⚠️ La 1 es la que vas a notar al probar.** Crea un equipo nuevo en Equipos y ve a Estadísticas: no aparecerá en la tabla hasta que le crees un partido.

## 6. Verificación con datos reales

Para comprobar que los cálculos son correctos, hay una propiedad que siempre se cumple: **el total de victorias debe ser igual al total de derrotas**, y las dos deben sumar el número de partidos.

```
Total de PG en la tabla = número de partidos
Total de PP en la tabla = número de partidos
Total de PJ en la tabla = número de partidos × 2
```

Con 4 partidos: PG totales = 4, PP totales = 4, PJ totales = 8. Está comprobado que se cumple.

Es el tipo de comprobación que puedes hacer a mano con `SELECT SUM(...)` si quieres más seguridad, o con una hoja de cálculo.

---

**Archivos relacionados:** `../../../app/models/Partido.php` (los JOIN que hacen posible esto) · `../../../app/views/estadisticas/index.php` · `PartidosController-explicado.md` · `../../03-Arquitectura/Diagrama-conceptual.md`

**Volver al índice →** `../../00-MOC/Proyecto-Liga-Basquet-MOC.md`

---

**Este es el último controlador.** Con estos cinco archivos tienes explicado todo el backend de la aplicación. Lo que falta es la capa de presentación: `../Vistas/Layout-y-vistas-explicados.md`.