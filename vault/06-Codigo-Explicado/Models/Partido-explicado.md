# Partido (explicado)

**Archivo real:** `app/models/Partido.php`
**Rol:** acceso a la tabla `partidos`. **El modelo con la consulta más compleja del proyecto.**

## 1. El código completo

```php
class Partido extends Model {
    protected string $tabla = 'partidos';

    public function crear(int $equipoLocalId, int $equipoVisitanteId,
                          int $puntosLocal, int $puntosVisitante, string $fecha): bool {
        $sql = "INSERT INTO {$this->tabla} (equipo_local_id, equipo_visitante_id, puntos_local, puntos_visitante, fecha)
                VALUES (:local, :visitante, :pl, :pv, :fecha)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':local', $equipoLocalId, PDO::PARAM_INT);
        $stmt->bindValue(':visitante', $equipoVisitanteId, PDO::PARAM_INT);
        $stmt->bindValue(':pl', $puntosLocal, PDO::PARAM_INT);
        $stmt->bindValue(':pv', $puntosVisitante, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function actualizar(int $id, int $equipoLocalId, int $equipoVisitanteId,
                               int $puntosLocal, int $puntosVisitante, string $fecha): bool {
        $sql = "UPDATE {$this->tabla}
                SET equipo_local_id = :local, ...
                WHERE id = :id";
        ...
    }

    public function obtenerTodos(): array {
        $sql = "SELECT p.*,
                       el.nombre AS nombre_local,
                       ev.nombre AS nombre_visitante
                FROM {$this->tabla} p
                LEFT JOIN equipos el ON el.id = p.equipo_local_id
                LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
                ORDER BY p.fecha DESC";
        ...
    }

    public function obtenerPorId(int $id): ?array {
        // misma consulta + WHERE p.id = :id LIMIT 1
    }
}
```

## 2. El doble JOIN: la parte más importante

```sql
SELECT p.*,
       el.nombre AS nombre_local,
       ev.nombre AS nombre_visitante
FROM partidos p
LEFT JOIN equipos el ON el.id = p.equipo_local_id
LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
ORDER BY p.fecha DESC
```

### El problema que resuelve

Un partido tiene **dos** referencias a equipos: `equipo_local_id` y `equipo_visitante_id`. Ambas apuntan a `equipos`. Para mostrar "Lakers 112 vs Celtics 108", hacen falta los dos nombres de esa misma tabla.

### ⚠️ La misma tabla, dos veces: los alias son obligatorios

Como `equipos` aparece **dos veces**, cada aparición necesita un nombre propio:

| Alias | Qué representa |
|---|---|
| `p` | `partidos` (la tabla base) |
| `el` | `equipos` **como** equipo local (**e**quipo **l**ocal) |
| `ev` | `equipos` **como** equipo visitante (**e**quipo **v**isitante) |

Los alias son으로는 mnemotécnicos a propósito: `el` de "equipo local", `ev` de "equipo visitante".

**Sin alias, esto no compila:**
```sql
SELECT nombre FROM partidos JOIN equipos ON equipos.id = partidos.equipo_local_id  -- ERROR
-- ERROR 1052 (23000): Column 'nombre' in field list is ambiguous
```

MySQL ve `nombre` en las dos copias de `equipos` y no sabe cuál quieres. Está documentado en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 6.

### `AS nombre_local` y `AS nombre_visitante` — el resultado final

Los alias de tabla (`el`, `ev`) son para escribir la consulta. Los **alias de columna** (`nombre_local`, `nombre_visitante`) son para lo que devuelve:

```php
$partido['nombre_local']       // 'Los Lakers'
$partido['nombre_visitante']   // 'Boston Celtics'
```

Sin ellos, ambas columnas se llamarían `nombre` y PHP solo vería una (la última).

**⚠️ Regla:** con dos JOIN a la misma tabla, **ambos** tipos de alias son necesarios. Los de tabla para desambiguar la consulta, los de columna para distinguir el resultado.

### `LEFT JOIN` en los dos casos

Igual que en `Jugador`: `LEFT JOIN` garantiza que el partido **aparece** aunque un equipo no exista, en lugar de desaparecer de la lista.

**Verificado:** con datos correctos no hay diferencia. Pero el `LEFT JOIN` es la elección segura.

### `ORDER BY p.fecha DESC`

Los partidos **más recientes primero**. Es lo que espera el usuario de un módulo de partidos y lo que hace que la sección "Últimos Partidos" de las estadísticas no necesite ordenar nada más.

⚠️ **El `DESC` es lo que invierte el orden natural de MySQL** (que ordena ascendentemente por defecto). Sin él, el partido más antiguo saldría primero.

⚠️ **Y `fecha` necesita índice**, que sí tiene:
```sql
KEY idx_partido_fecha (fecha)
```
Sin ese índice, MySQL tendría que leer toda la tabla y ordenarla en memoria. Con 4 partidos da igual; con 100.000, la diferencia es enorme.

## 3. Los nombres de placeholder: abreviados

```php
VALUES (:local, :visitante, :pl, :pv, :fecha)
```

⚠️ **Aquí los placeholders se abrevian (`:pl`, `:pv`) mientras que en `Jugador` se usan completos.** No es un error, pero **es una inconsistencia de estilo** dentro del mismo proyecto.

**Por qué se abreviaron:** `:puntos_local` y `:puntos_local` aparecen dos veces (en el `SET` del UPDATE y en el `VALUES` del INSERT), y son largos. Abreviar hace la consulta más legible de un vistazo.

**⚠️ El riesgo de abreviar:** `:pl` es menos claro que `:puntos_local`. Y un error tipográfico (`bindValue(':p'` en vez de `':pl'`) da un error de PDO críptico:

```
SQLSTATE[HY093]: Invalid parameter number: parameter was not defined
```

**Regla práctica:** si el nombre del placeholder no es obvio, escribe el nombre completo. La abreviatura solo se justifica si la palabra completa es muy larga o si se repite en la misma consulta.

## 4. `$fecha` como `PDO::PARAM_STR` y el formato `T`

```php
$stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
```

**La fecha llega como texto**, no como objeto `DateTime`. Este es un detalle importante:

### Lo que envía el navegador

El `<input type="datetime-local">` envía el valor en formato **ISO 8601 con `T`**:
```
2026-10-12T19:00
```

### Lo que guarda MySQL

La columna es `DATETIME`, y MySQL almacena en formato `YYYY-MM-DD HH:MM:SS`:
```
2026-10-12 19:00:00
```

### ¿Quién hace la conversión?

**MySQL, automáticamente.** Está verificado: insertar `'2026-10-12T19:00'` en una columna `DATETIME` funciona y guarda `2026-10-12 19:00:00`.

**Por eso el modelo no transforma nada.** Solo pasa la cadena tal cual.

⚠️ **Pero depende del modo de MySQL.** En `NO_ZERO_DATE` y otros modos estrictos, formatos inesperados pueden dar error o advertencias. Por eso el modelo **no debería** depender de esa tolerancia. Lo correcto sería normalizar en el controlador:

```php
// En PartidosController::guardar()
$fecha = str_replace('T', ' ', trim($_POST['fecha'] ?? ''));
$dt = DateTime::createFromFormat('Y-m-d H:i', $fecha);
if ($dt === false) {
    $_SESSION['error'] = 'La fecha no tiene un formato válido';
    header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
    exit;
}
$fechaParaGuardar = $dt->format('Y-m-d H:i:s');
```

⚠️ **No está implementado.** Funciona por la tolerancia de MySQL, y está verificado que funciona. Pero es una dependencia implícita que conviene hacer explícita.

### ⚠️ El problema inverso SÍ estaba: la conversión al mostrar

Este es el bug real del proyecto. Al **leer** de la base, la fecha viene con **espacio**, y el `<input type="datetime-local">` exige **`T`**. La conversión va en la **vista**, no en el modelo:

```php
// app/views/partidos/editar.php — ✔️ versión corregida
value="<?= htmlspecialchars(str_replace(' ', 'T', substr($partido['fecha'], 0, 16))) ?>"
```

Está explicado en detalle en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 4.

**El resumen del problema de fechas en este proyecto:**

| Momento | Formato | Quién convierte |
|---|---|---|
| El navegador envía | `2026-10-12T19:00` | — |
| Se guarda en MySQL | `2026-10-12 19:00:00` | MySQL (automático) |
| Se lee de MySQL | `2026-10-01 19:00:00` | — |
| Se muestra en el input | `2026-10-01T19:00` | **PHP, en la vista** ⚠️ |

**Las dos últimas filas necesitan conversión, y ahí estaba el bug.**

## 5. `crear()` y `actualizar()`: los cinco parámetros

```php
public function crear(int $equipoLocalId, int $equipoVisitanteId,
                      int $puntosLocal, int $puntosVisitante, string $fecha): bool
```

**Cinco parámetros posicionales.** ⚠️ **Es un error fácil de cometer:** `crear(1, 2, 112, 108, '2026-10-01 19:00')` — si se intercambian dos `int` seguidos, el compilador no avisa. PHP no tiene tipos nominales.

```php
// ❌ Positional: ¿qué es 112 y qué es 108?
$model->crear(1, 2, 112, 108, '2026-10-01 19:00');

// ✔️ Con array nombrado (PHP 8): las claves se autocompletan
$model->crear(
    equipoLocalId: 1,
    equipoVisitanteId: 2,
    puntosLocal: 112,
    puntosVisitante: 108,
    fecha: '2026-10-01 19:00'
);
```

⚠️ **No está implementado en el proyecto.** En PHP 8 se puede usar la sintaxis de parámetros nombrados sin cambiar nada más. Con cinco parámetros del mismo tipo, es una mejora que evita errores difíciles de detectar.

**`actualizar()` añade un sexto parámetro, `$id`, al principio:**
```php
public function actualizar(int $id, int $equipoLocalId, ...)
```
Es un detalle de diseño: el `id` que identifica **qué** fila actualizar va primero, y luego los datos nuevos.

## 6. Esquema de la tabla

```sql
CREATE TABLE IF NOT EXISTS partidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATETIME NOT NULL COMMENT 'Fecha y hora del encuentro',
  equipo_local_id INT UNSIGNED NOT NULL COMMENT 'Equipo local (FK)',
  equipo_visitante_id INT UNSIGNED NOT NULL COMMENT 'Equipo visitante (FK)',
  puntos_local INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Puntos equipo local',
  puntos_visitante INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Puntos equipo visitante',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_partido_local (equipo_local_id),
  KEY idx_partido_visitante (equipo_visitante_id),
  KEY idx_partido_fecha (fecha),
  CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id)
    REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_partido_visitante FOREIGN KEY (equipo_visitante_id)
    REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
```

### Tres claves foráneas... no, dos (y dos índices más)

⚠️ **Dos FK a la misma tabla**, igual que los dos JOIN del modelo. Cada FK necesita su índice, y hay un **tercer índice** para `fecha`.

⚠️ **`puntos_local INT UNSIGNED NOT NULL DEFAULT 0`** — el `UNSIGNED` impide negativos a nivel de base, y el `DEFAULT 0` significa que un partido scheduled puede existir sin marcador informado (empate provisional 0-0).

### ⚠️ Lo que NO está en el esquema: los equipos no pueden ser iguales

Nada impide que un partido tenga `equipo_local_id = equipo_visitante_id`. Esa regla solo existe en `PartidosController::guardar()`:

```php
if ($equipoLocalId === $equipoVisitanteId) {
    $_SESSION['error'] = 'El equipo local y visitante no pueden ser iguales';
    ...
}
```

⚠️ **Debería estar también en la base**, porque una restricción en el esquema protege incluso de INSERT hechos a mano en phpMyAdmin:

```sql
CONSTRAINT chk_equipos_distintos CHECK (equipo_local_id <> equipo_visitante_id)
```

**Verificado en las pruebas:** 0 partidos con el mismo equipo en los dos lados, pero eso es gracias al código PHP, no al esquema.

### `ON DELETE RESTRICT` en los dos

Un partido bloquea el borrado de cualquiera de sus dos equipos. Igual que en `Jugador`. Está en `../../04-BD/schema-explicado.md` sección 6.

## 7. Verificación

```bash
# El listado con los dos JOIN, como lo hace la aplicación
mysql -u root liga_basquet --batch -e "
SELECT p.id, p.fecha,
       el.nombre AS local, p.puntos_local,
       ev.nombre AS visitante, p.puntos_visitante,
       CASE
         WHEN p.puntos_local > p.puntos_visitante THEN 'Gana Local'
         WHEN p.puntos_local < p.puntos_visitante THEN 'Gana Visitante'
         ELSE 'Empate'
       END AS resultado
FROM partidos p
LEFT JOIN equipos el ON el.id = p.equipo_local_id
LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
ORDER BY p.fecha DESC;"

# Partidos con el mismo equipo en ambos lados (debe devolver 0)
mysql -u root liga_basquet --batch -e "
SELECT COUNT(*) AS invalidos
FROM partidos WHERE equipo_local_id = equipo_visitante_id;"
```

⚠️ **El `CASE` del SQL es la versión "en la base" del cálculo que hace la vista en PHP.** Los dos dan el mismo resultado. Poder calcularlo de las dos maneras es buena forma de **verificar que el cálculo en PHP es correcto**: si alguna vez no coinciden, sabes que hay un bug.

**Verificado:** el listado muestra los 4 partidos con sus nombres, marcadores y resultados (Gana Local / Gana Visitante / Empate).

## 8. Errores frecuentes con este modelo

| Error | Síntoma | Solución |
|---|---|---|
| JOIN sin alias | `Column 'id' is ambiguous` | `el` y `ev` |
| Alias de columna duplicado | Solo se ve un nombre en PHP | `AS nombre_local` / `AS nombre_visitante` |
| `WHERE id = :id` sin calificar | `Ambiguous` en el WHERE | `WHERE p.id = :id` |
| Falta `ORDER BY fecha DESC` | El más reciente al final | Añadirlo |
| Olvidar `LEFT JOIN` en `obtenerPorId()` | El formulario de editar sin nombres | Copiar la consulta completa |
| Cinco parámetros `int` posicionales | Errores de argumentos intercambiados | Parámetros nombrados (PHP 8) |
| Asignar `0` a `puntos` por defecto | Partidos 0-0 falsos | Usar `?? ''` y comprobar vacío |
| Confiar en que MySQL convierte la `T` | Puede fallar según el modo SQL | Convertir en el controlador |
| Mostrar la fecha sin convertir a `T` | El campo del formulario sale vacío | `str_replace(' ', 'T', ...)` en la vista |

---

**Archivos relacionados:** `../Controllers/PartidosController-explicado.md` · `../../../app/views/partidos/editar.php` (el fix del `datetime-local`) · `Jugador-explicado.md` (un JOIN en lugar de dos) · `Equipo-explicado.md` · `../../08-Errores-y-Soluciones/Registro-de-errores.md` (secciones 4 y 6)

**Este es el último modelo.** Con los cuatro tienes explicado el acceso a datos completo de la aplicación.

**Siguiente paso →** `../Controllers/EstadisticasController-explicado.md` (qué se puede construir con los partidos que este modelo trae).