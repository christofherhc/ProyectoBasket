# Jugador (explicado)

**Archivo real:** `app/models/Jugador.php`
**Rol:** acceso a la tabla `jugadores`. El ejemplo más claro de un `LEFT JOIN` para traer un dato de otra tabla.

## 1. El código completo

```php
class Jugador extends Model {
    protected string $tabla = 'jugadores';

    public function crear(string $nombre, ?int $numero, ?string $posicion, int $equipoId): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, numero, posicion, equipo_id)
                VALUES (:nombre, :numero, :posicion, :equipo_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        // null significa "sin dorsal". El 0 SÍ es un dorsal válido.
        if ($numero === null) {
            $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
        }
        $stmt->bindValue(':posicion', $posicion === '' ? null : $posicion, PDO::PARAM_STR);
        $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function actualizar(int $id, string $nombre, ?int $numero, ?string $posicion, int $equipoId): bool { ... }

    public function obtenerTodos(): array {
        $sql = "SELECT j.*, e.nombre AS nombre_equipo
                FROM {$this->tabla} j
                LEFT JOIN equipos e ON e.id = j.equipo_id
                ORDER BY j.nombre ASC";
        ...
    }

    public function obtenerPorId(int $id): ?array {
        // misma consulta + WHERE j.id = :id LIMIT 1
    }
}
```

## 2. `LEFT JOIN equipos`: un solo JOIN, un solo alias

```sql
SELECT j.*, e.nombre AS nombre_equipo
FROM jugadores j
LEFT JOIN equipos e ON e.id = j.equipo_id
ORDER BY j.nombre ASC
```

**Comparado con `Partido`**, aquí solo hay un JOIN, así que:

| | `Jugador` | `Partido` |
|---|---|---|
| JOINs | 1 (a `equipos`) | **2** (a `equipos`, dos veces) |
| Alias de tabla | `j`, `e` | `p`, `el`, `ev` |
| Alias obligatorios | No estrictamente | **Sí**, o error "ambiguous" |
| Alias de columna | 1 (`nombre_equipo`) | 2 (`nombre_local`, `nombre_visitante`) |

**Aunque aquí solo haya un JOIN, se usan alias igual.** Es buena costumbre: obliga a calificar cada columna (`j.nombre` en vez de `nombre`) y evita tener que añadir alias más adelante.

### `j.*` y `e.nombre AS nombre_equipo`

- `j.*` = todas las columnas de `jugadores` (`id`, `nombre`, `numero`, `posicion`, `equipo_id`, `creado_en`).
- `e.nombre AS nombre_equipo` = **una columna extra** con el nombre del equipo.

⚠️ **El resultado en PHP:**
```php
$jugador['nombre']         // 'LeBron James'    ← de jugadores
$jugador['numero']         // 23                ← de jugadores
$jugador['nombre_equipo']  // 'Los Lakers'      ← de equipos (el JOIN)
```

⚠️ **Y `SELECT e.*` no es buena idea.** Traer todas las columnas de `equipos` haría que `$jugador['nombre']` **chocara** con `$jugador['id']` de ambas tablas, y MySQL devolvería solo una (la última). De ahí el `e.nombre AS nombre_equipo`: un nombre claro que no colisiona.

**Este es el error clásico de los JOIN: usar `SELECT *` con más de una tabla.** Se llame `SELECT *` o `SELECT j.*`, siempre que haya dos tablas, hay que tener cuidado con las columnas que se repiten.

### Por qué el JOIN y no dos consultas

❌ **Lo que NO se hizo** (y sería peor):
```php
$jugadores = $this->obtenerTodos();                    // 1 consulta
$equipos = $equipoModel->obtenerTodos();                // 2ª consulta
// y luego en la vista: buscar en un foreach el equipo de cada jugador
```

❌ **Eso obliga a escribir un JOIN en el modelo, o peor, a escribir lógica de búsqueda en la VISTA.** Con `foreach` anidados para 12 jugadores y 4 equipos son 48 iteraciones; con 100.000 jugadores serían 100 millones.

✔️ **El JOIN resuelve todo en la base de datos**, que es donde están los datos relacionados. El modelo devuelve cada jugador **ya con su equipo**, y la vista solo imprime.

⚠️ **Coste del JOIN:** si se une una tabla muy grande sin índices, MySQL puede acabar haciendo un producto cartesiano lento. Por eso `esquema.sql` declara `KEY idx_jugador_equipo (equipo_id)`: MySQL puede encontrar rápido los jugadores de un equipo.

## 3. `LEFT JOIN` en vez de `JOIN`

**`JOIN` (interno)**: si no hay coincidencia, **la fila desaparece**.

```sql
-- Si un jugador tuviera equipo_id = 99 (equipo borrado),
-- con JOIN interno NO aparecería en el listado
FROM jugadores j JOIN equipos e ON e.id = j.equipo_id
```

**`LEFT JOIN` (externo por la izquierda)**: si no hay coincidencia, **la fila se conserva** y las columnas de la tabla derecha vienen `NULL`.

```sql
-- Con LEFT JOIN el jugador aparece, con nombre_equipo = NULL
FROM jugadores j LEFT JOIN equipos e ON e.id = j.equipo_id
```

**Por qué es la opción correcta.** Un jugador sin equipo es un problema de datos que hay que **ver**, no esconder. Con `LEFT JOIN` el listado lo muestra como "Sin equipo" y el error es visible. Con `JOIN` desaparece, y el usuario cree que el jugador no existe.

⚠️ **En este proyecto `ON DELETE RESTRICT` impide que un jugador se quede sin equipo**, así que la situación no se da. Pero `LEFT JOIN` sigue siendo la elección correcta: **no hay coste extra y te protege de un futuro descuido.**

⚠️ **Y por eso la vista necesita el `??`:**
```php
<td><?= htmlspecialchars($jugador['nombre_equipo'] ?? 'Sin equipo') ?></td>
```
Con `LEFT JOIN`, `nombre_equipo` **puede ser `null`**, y `htmlspecialchars(null)` es un `TypeError` en PHP 8.1+.

## 4. El patrón del `NULL`, igual que en `Equipo`

```php
if ($numero === null) {
    $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
}
$stmt->bindValue(':posicion', $posicion === '' ? null : $posicion, PDO::PARAM_STR);
```

**Es exactamente el mismo problema y la misma solución que en `Equipo::crear()`** con `fundacion`. Está explicado en detalle en `Equipo-explicado.md` sección 3.

Dos detalles propios de este modelo:

**a) Solo `null` se trata como "sin dorsal".** El `0` **sí es un dorsal válido** (Coby White y Jayson Tatum lo llevan en los datos de prueba), así que no se puede confundir con "vacío". El controlador es quien decide: si el campo llega vacío pasa `null`; si llega `'0'` pasa `0`. El modelo nunca convierte un `0` en `NULL`. Antes sí lo hacía y era un bug: escribir `0` guardaba `NULL` y se mostraba `—`.

**b) `$posicion === '' ? null : $posicion` usa el ternario.** Aquí no hay `if` porque la transformación es una línea. El operador `?:` sería más corto (`$posicion ?: null`), ⚠️ **pero tiene un problema**: devuelve `null` para cualquier valor "falsy", incluidos `'0'` y `0`. Con el ternario explícito, solo la cadena vacía se convierte en `null`. **Regla: el ternario completo es más seguro que el abreviado.**

⚠️ **Fíjate en que `$posicion` se vincula con `PDO::PARAM_STR` incluso cuando es `null`.** Funciona (PDO envía el `NULL`), pero lo más consistente sería:
```php
if ($posicion === '') {
    $stmt->bindValue(':posicion', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':posicion', $posicion, PDO::PARAM_STR);
}
```
**La diferencia real** aparece cuando el campo es un entero, como `numero`: ahí sí es crítico declarar el tipo. Con una cadena, `PARAM_STR` y `PARAM_NULL` producen el mismo resultado.

## 5. `ORDER BY j.nombre ASC`

Alfabéticamente. Es una decisión de presentación, pero vive en el modelo (como en `Equipo`).

⚠️ **Como en `Equipo`, el modelo no debería decidir el orden.** (Esta firma **no existe** en el código real: `obtenerTodos()` no recibe parámetros.) Si algún día quieres dos listados distintos (uno alfabético y otro por número de camiseta), el orden debería ser un **parámetro** del método:

```php
public function obtenerTodos(string $ordenarPor = 'nombre'): array {
    // ⚠️ ojo: esto SÍ sería inyección SQL si no se valida
    $columnasValidas = ['nombre', 'numero', 'posicion', 'id'];
    if (!in_array($ordenarPor, $columnasValidas, true)) {
        $ordenarPor = 'nombre';
    }
    $sql = "SELECT ... ORDER BY j.{$ordenarPor} ASC";
    ...
}
```

⚠️ **La validación con `in_array(..., true)` no es opcional aquí.** Un ORDER BY dinámico sin lista blanca es una inyección SQL clásica:
```
?ordenarPor=id; DROP TABLE jugadores; --
```
Es uno de los pocos casos donde un valor de la URL **sí** se mete en la consulta sin placeholders (porque MySQL no permite parametrizar nombres de columna). Por eso necesita una lista blanca.

## 6. `obtenerPorId()` reescrito: el detalle que se olvida

```php
public function obtenerPorId(int $id): ?array {
    $sql = "SELECT j.*, e.nombre AS nombre_equipo
            FROM {$this->tabla} j
            LEFT JOIN equipos e ON e.id = j.equipo_id
            WHERE j.id = :id
            LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch();
    return $resultado === false ? null : $resultado;
}
```

⚠️ **Si este método no se sobrescribiera, devolvería los datos SIN el nombre del equipo.** El `Model` devolvería solo columnas de `jugadores`, y `nombre_equipo` no existiría.

**El síntoma sería muy confuso:** el formulario de editar mostraría el desplegable de equipos con la opción correcta sin marcar, porque usaría `$jugador['equipo_id']`... en realidad ese sí estaría. Pero cualquier vista que usara `nombre_equipo` mostraría un error de variable indefinida.

**Regla clara: si sobrescribes `obtenerTodos()`, sobrescribe `obtenerPorId()`.** Deben devolver la misma forma de datos.

⚠️ **Y `WHERE j.id = :id` debe llevar el alias.** Aquí solo hay un JOIN, así que `WHERE id = :id` funcionaría. Pero si mañana se añade un JOIN a otra tabla con `id`, `p.id` es obligatorio. En `Partido` está escrito `WHERE p.id = :id` por esa razón. **Calificar siempre, desde el principio.**

## 7. Esquema de la tabla

```sql
CREATE TABLE IF NOT EXISTS jugadores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  numero INT UNSIGNED NULL COMMENT 'Número de camiseta',
  posicion VARCHAR(50) NULL COMMENT 'Posición: Base/Escolta/Alero/Ala-Pívot/Pívot',
  equipo_id INT UNSIGNED NOT NULL COMMENT 'Equipo al que pertenece (FK)',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_jugador_equipo (equipo_id),
  UNIQUE KEY uq_numero_por_equipo (equipo_id, numero),
  CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_id)
    REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
```

**`numero INT UNSIGNED NULL`** → puede ser `NULL` (sin camiseta) pero nunca negativo, porque `UNSIGNED`.

**`UNIQUE KEY uq_numero_por_equipo (equipo_id, numero)`** → en básquetbol, dos jugadores del mismo equipo no pueden llevar la misma camiseta, y el índice lo garantiza a nivel de base de datos. Es la última red: aunque la validación de PHP fallara, la base rechaza el duplicado (error 1062).

⚠️ **`UNIQUE` con `NULL` tiene un matiz:** en MySQL dos filas con `numero = NULL` **no** se consideran duplicadas, así que un equipo puede tener varios jugadores sin dorsal. Es justo lo que se quiere: "sin número" no choca consigo mismo.

La validación amable vive en el controlador (`existeDorsalEnEquipo()`, explicada en `../Controllers/JugadoresController-explicado.md`), que da un mensaje claro antes de llegar al error de SQL.

**`posicion VARCHAR(50) NULL`** → texto libre, no un `ENUM`. La validación de que sea una de las cinco posiciones válidas la hace el controlador contra la constante `POSICIONES` de `config.php` (lista blanca con `in_array(..., true)`), y el formulario la ofrece en un `<select>`.

⚠️ **Podría ser `ENUM('Base','Escolta','Alero','Ala-Pívot','Pívot')`**, que haría que **la base** rechazara valores inválidos. Es una alternativa al `VARCHAR` + validación en PHP, aunque menos flexible si luego quieres añadir posiciones.

## 8. Verificación

```bash
# El JOIN, como lo hace la aplicación
mysql -u root liga_basquet --batch -e "
SELECT j.id, j.nombre, j.numero, j.posicion, e.nombre AS equipo
FROM jugadores j
LEFT JOIN equipos e ON e.id = j.equipo_id
ORDER BY j.nombre;"

# Jugadores sin número (NULL, no 0)
mysql -u root liga_basquet --batch -e "
SELECT nombre, IFNULL(numero, 'NULL') AS numero
FROM jugadores WHERE numero IS NULL;"

# Duplicados de camiseta por equipo (debe dar 0 siempre)
mysql -u root liga_basquet --batch -e "
SELECT equipo_id, numero, COUNT(*) AS veces
FROM jugadores
WHERE numero IS NOT NULL
GROUP BY equipo_id, numero
HAVING COUNT(*) > 1;"
```

⚠️ **La tercera consulta es una comprobación de integridad.** Si alguna vez devuelve filas, es que el índice `UNIQUE` no se aplicó (por ejemplo, si la tabla se creó antes de añadirlo). Con el esquema actual debe salir **siempre vacía**: la base no permite dos camisetas iguales en el mismo equipo.

**Verificado:** el listado muestra los 12 jugadores con el nombre de su equipo correcto y la consulta de duplicados devuelve 0 filas.

## 9. Errores frecuentes con este modelo

| Error | Síntoma | Solución |
|---|---|---|
| `SELECT e.*` | Columnas repetidas, datos pisados | Solo las columnas necesarias con alias |
| `JOIN` sin `AS nombre_equipo` | Solo se ve un `nombre` | Alias de columna |
| `JOIN` interno en vez de `LEFT JOIN` | Filas desaparecen sin aviso | `LEFT JOIN` |
| No sobrescribir `obtenerPorId()` | El formulario sin datos del equipo | Sobrescribirlo también |
| `WHERE id` sin calificar | `Ambiguous` al añadir más JOINs | `WHERE j.id` |
| `ORDER BY` dinámico sin lista blanca | **Inyección SQL** | `in_array($col, $validas, true)` |
| Sin `PDO::PARAM_NULL` en `numero` | El vacío se guarda como `0` | Declarar el tipo |
| `?:` en vez de ternario en `posicion` | `'0'` se convierte en `null` | Ternario explícito |
| `htmlspecialchars($nombre_equipo)` sin `??` | `TypeError` en PHP 8.1+ | `?? 'Sin equipo'` |

---

**Archivos relacionados:** `../Controllers/JugadoresController-explicado.md` · `Equipo-explicado.md` (el mismo patrón del `NULL`) · `Partido-explicado.md` (el JOIN doble) · `../Core/Model-explicado.md` · `../../04-BD/schema-explicado.md` (sección 5)

---

**Con este archivo quedan los 4 modelos explicados.** El siguiente paso natural es ver cómo se traduce todo esto en HTML: `../Vistas/Layout-y-vistas-explicados.md`.