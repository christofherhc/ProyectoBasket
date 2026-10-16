# JugadoresController (explicado)

**Archivo real:** `app/controllers/JugadoresController.php`
**Rol:** CRUD de jugadores. Es un copia de `EquiposController` **con una diferencia**: hay que elegir un equipo de una lista.

## 1. Qué lo distingue de EquiposController

Los dos hacen el mismo CRUD, pero `JugadoresController` tiene que:

1. Cargar **dos** modelos en vez de uno.
2. Cargar la lista de equipos **también en `editar()`**, para pintar el `<select>` con el equipo actual marcado.
3. Validar un campo más (`numero`), con su propio rango.

**El aprendizaje clave:** cuando un formulario tiene una **relación con otra tabla**, el controlador necesita los datos de las dos tablas: una para guardar el registro, otra para las opciones del desplegable.

## 2. El constructor con dos modelos

```php
class JugadoresController extends Controller {
    private Jugador $jugadorModel;
    private Equipo $equipoModel;

    public function __construct() {
        require_once '../app/models/Jugador.php';
        require_once '../app/models/Equipo.php';
        $this->jugadorModel = new Jugador();
        $this->equipoModel  = new Equipo();
    }
```

**Dos propiedades `private`, dos `require`, dos `new`.** Cada uno hace su papel:

| Modelo | Para qué |
|---|---|
| `Jugador` | Leer y guardar jugadores |
| `Equipo` | Llenar el `<select>` de equipos en crear() y editar() |

⚠️ **No los carga en el constructor.** Se usan solo en `crear()` y `editar()`, y no en `index()` ni en `eliminar()`. Podría crearlos "en el momento" (`$this->equipoModel = new Equipo();` dentro del método) para no abrirlos si no hacen falta, pero la conexión es Singleton: ya está abierta igualmente. Por eso se dejan en el constructor, que es más limpio.

## 3. `crear()`: el desplegable de equipos

```php
public function crear(): void {
    $this->requiereLogin();
    $equipos = $this->equipoModel->obtenerTodos();
    $old = $_SESSION['old_jugador'] ?? [];
    unset($_SESSION['old_jugador']);
    $this->view('jugadores/crear', [
        'titulo'   => 'Crear Jugador',
        'equipos'  => $equipos,
        'old'      => $old
    ]);
}
```

La única diferencia con `EquiposController::crear()` es la línea `$equipos = $this->equipoModel->obtenerTodos();` y su `'equipos' => $equipos`.

**En la vista** (`app/views/jugadores/crear.php`) eso se convierte en:

```php
<select name="equipo_id" id="equipo_id" required>
    <option value="">Selecciona un equipo</option>
    <?php foreach ($equipos as $equipo): ?>
        <option value="<?= (int)$equipo['id'] ?>"
                <?= (isset($old['equipo_id']) && (int)$old['equipo_id'] === (int)$equipo['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($equipo['nombre']) ?>
        </option>
    <?php endforeach; ?>
</select>
```

**⚠️ Tres detalles importantes en ese HTML:**

**a) `<option value="">` como primera opción** para forzar una elección consciente. Sin ella, el navegador seleccionaría el primer equipo por defecto y el usuario podría guardar el jugador en el equipo equivocado sin darse cuenta. La validación del servidor (`$equipoId <= 0`) cubre el caso de que se envíe vacío.

**b) El `(int)` en los `id`.** El `id` viene de la base como string (`'1'`), y el `value` del `<option>` también. Si comparas `'1' === '1'` funciona, pero si comparas con `==` y uno es string y el otro int, PHP hace una conversión silenciosa. Con `(int)` en ambos lados, la comparación es siempre fiable:

```php
(int)$old['equipo_id'] === (int)$equipo['id']     // ✔️ siempre correcto
$old['equipo_id'] == $equipo['id']                 // ⚠️ depende de la conversión
```

**c) `htmlspecialchars($equipo['nombre'])`** siempre, aunque el nombre venga de la base. Es la regla que evita XSS.

**d) El primer `<option>` tiene `value=""`**, y `(int)''` da `0`, que no coincide con ningún id de equipo. Así el equipo seleccionado del `$old` nunca marca accidentalmente la primera opción.

## 4. `guardar()`: las tres validaciones

```php
$nombre    = trim($_POST['nombre'] ?? '');
$numero    = $_POST['numero'] ?? '';       // ← sin (int), a propósito
$posicion  = trim($_POST['posicion'] ?? '');
$equipoId  = (int)($_POST['equipo_id'] ?? 0);

if (empty($nombre) || $equipoId <= 0) {
    $_SESSION['error'] = 'Nombre y equipo son obligatorios';
    header('Location: ' . BASE_URL . '?controller=Jugadores&action=crear');
    exit;
}

$numeroInt = null;
if ($numero !== '') {
    $numeroInt = (int)$numero;
    if ($numeroInt < 0 || $numeroInt > 99) {
        $_SESSION['error'] = 'Número de camiseta inválido (0-99)';
        header('Location: ' . BASE_URL . '?controller=Jugadores&action=crear');
        exit;
    }
}

$this->jugadorModel->crear($nombre, $numeroInt, $posicion, $equipoId);
```

### La diferencia clave: `$equipoId <= 0` en vez de `empty()`

En `EquiposController`, la validación es `empty($nombre) || empty($ciudad)`. Aquí el equipo **no** puede validar con `empty()`, porque es un número:

| Valor de `equipo_id` | `empty()` | `$id <= 0` | Correcto |
|---|---|---|---|
| `''` (nada seleccionado) | `true` → error | `true` → error | Ambos |
| `'0'` (opción vacía) | **`true` → error** | `true` → error | Ambos |
| `'3'` | `false` → OK | `false` → OK | Ambos |
| `'abc'` | `false` → **pasa el filtro** | `true` → error | Solo `<= 0` |

**El caso `'abc'` es el que importa.** `(int)'abc'` es `0`, así que `$equipoId <= 0` lo rechaza. Con `empty()` pasaría el filtro y llegaría un `0` a la base, que fallaría por clave foránea con un error de SQL.

**Regla general:** para campos numéricos valida con **comparaciones de rango** (`<= 0`, `< 0`, `> 99`), no con `empty()`. Para campos de texto, `empty()` sí sirve.

**`$numero !== ''` antes de convertir.** El campo se deja como cadena para distinguir "vacío" de "cero". Vacío significa **sin dorsal** y se guarda como `NULL`; `'0'` es un dorsal real y se guarda como `0`. El modelo solo convierte a `NULL` cuando recibe `null`, nunca cuando recibe `0`:

```php
if ($numero === null) {
    $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
}
```

### La validación del `numero`: `> 99`

Un rango **razonable**, no arbitrario: en básquetbol las camisetas van de 0 a 99. El `0` es válido (Coby White y Jayson Tatum lo llevan en los datos de prueba), así que la única cota superior es `MAX_CAMISETA`:

```php
$numeroInt = (int)$numero;
if ($numeroInt > MAX_CAMISETA) {
    $errores[] = 'El número de camiseta debe estar entre 0 y ' . MAX_CAMISETA;
}
```

⚠️ **`ctype_digit()` ya descarta los negativos.** Antes de convertir se comprueba que el texto sean solo dígitos, así que `-5` se rechaza como "no es un número entero" sin necesidad de comprobar el signo.

### Unicidad del dorsal por equipo: sí se valida

Dos jugadores del mismo equipo no pueden llevar la misma camiseta. El índice `UNIQUE (equipo_id, numero)` de la base ya lo impide, pero **comprobarlo antes permite un mensaje claro** en vez del error 1062 genérico. El modelo tiene:

```php
public function existeDorsalEnEquipo(int $equipoId, int $numero, int $ignorarId = 0): bool {
    $sql = "SELECT COUNT(*) FROM {$this->tabla}
            WHERE equipo_id = :equipo AND numero = :numero AND id <> :ignorar";
    // ...
    return (int)$stmt->fetchColumn() > 0;
}
```

Y el controlador lo consulta dentro de `validarCampos()`:

```php
if ($numeroInt !== null && $equipoExiste
    && $this->jugadorModel->existeDorsalEnEquipo($equipoId, $numeroInt, $ignorarId ?? 0)) {
    $errores[] = 'Ese número de camiseta ya está en uso en el equipo seleccionado';
}
```

⚠️ **`$ignorarId` es la clave.** Al **editar**, el jugador que estás modificando ya tiene ese número; si no se excluye, la comprobación lo cuenta como duplicado de sí mismo y no podrías guardar sin cambiarlo. Por eso `actualizar()` llama a `validarCampos($id)` y `guardar()` a `validarCampos(null)`.

### `posicion`: se valida en PHP contra una lista blanca

La columna es `VARCHAR(50)` a propósito (no `ENUM`), así que el valor se contrasta contra la constante `POSICIONES` definida en `config.php`:

```php
if ($posicion !== '' && !in_array($posicion, POSICIONES, true)) {
    $errores[] = 'Posición no válida. Opciones: ' . implode(', ', POSICIONES);
}
```

El formulario ofrece esas mismas opciones en un `<select>`, pero **la validación no depende de eso**: un envío directo con `curl` podría mandar `posicion=Administrador`, y la lista blanca lo rechaza.

⚠️ **El `true` del tercer parámetro de `in_array()` no es opcional.** Con `in_array($posicion, POSICIONES)` y `$posicion = '0'`, compararía `'0' == 'Base'` en PHP 7 y daría un falso positivo. Con `true` la comparación es estricta.

**Lección general:** nunca confíes en que un `<select>` limite los valores. El HTML se puede manipulation; solo el servidor manda.

## 5. `editar()`: el detalle que se olvida

```php
public function editar(array $params): void {
    $this->requiereLogin();
    $id = (int)($params['id'] ?? 0);
    $jugador = $this->jugadorModel->obtenerPorId($id);

    if ($jugador === null) {
        $_SESSION['error'] = 'Jugador no encontrado';
        header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
        exit;
    }

    $equipos = $this->equipoModel->obtenerTodos();   // ← no te olvides de esto
    ...
}
```

⚠️ **El error más común al escribir este controlador:** olvidar `$equipos = $this->equipoModel->obtenerTodos();`.

Si falta, el `$select` del formulario se pinta sin opciones y el jugador aparece **sin equipo**, aunque tenga uno asignado. Al guardar, se manda `equipo_id=""`, la validación `$equipoId <= 0` lo rechaza y el usuario pierde el equipo.

El síntoma es desconcertante: "abro editar, el campo Equipo está vacío, y aunque elija otro, me dice que el equipo es obligatorio". En realidad el `<select>` no tenía ninguna opción.

**Verificado:** el `<select>` de equipos se puebla correctamente en `crear()` y en `editar()`.

## 6. `actualizar()` y `eliminar()`

`actualizar()` es idéntico a `guardar()` más el `id`:

```php
$id = (int)($_POST['id'] ?? 0);
...
if (empty($nombre) || $equipoId <= 0 || $id <= 0) { ... }
```

**Las mismas protecciones que en EquiposController:** el borrado es por POST con token CSRF, `validarId()` comprueba que el jugador exista y la posición se contrasta contra la lista blanca `POSICIONES`. Este CRUD **no** suffered el error 1451 porque `jugadores` no es la tabla padre de ninguna FK, así que nunca hubo que gestionar el borrado bloqueado (aunque `jugadores` **no tiene** referencias hacia otras tablas, así que borrar un jugador nunca falla por FK; es el único de los tres módulos donde `eliminar()` es seguro).

## 7. Cómo se ve la relación en el listado

El modelo `Jugador` sobrescribe `obtenerTodos()` para traer el nombre del equipo:

```sql
SELECT j.*, e.nombre AS nombre_equipo
FROM jugadores j
LEFT JOIN equipos e ON e.id = j.equipo_id
ORDER BY j.nombre ASC
```

Y en la vista:

```php
<?php foreach ($jugadores as $jugador): ?>
    <td><?= htmlspecialchars($jugador['nombre']) ?></td>
    <td><?= htmlspecialchars($jugador['numero'] ?? '—') ?></td>
    <td><?= htmlspecialchars($jugador['posicion'] ?? '—') ?></td>
    <td><?= htmlspecialchars($jugador['nombre_equipo'] ?? 'Sin equipo') ?></td>
<?php endforeach; ?>
```

**El `?? '—'` es importante.** `numero` y `posicion` pueden ser `NULL` en la base. Sin el `??`, `htmlspecialchars(null)` genera un aviso de *"Deprecated: htmlspecialchars(): Passing null"* en PHP 8.1+.

⚠️ **`htmlspecialchars()` en PHP 8.1+ ya no acepta `null`.** Antes devolvía `''` con un aviso. Ahora es un `TypeError`. Por eso el `?? '—'` no es solo por estética: **evita un error fatal** cuando el campo es nulo.

Y en el layout, `$_SESSION['nombre']` también va escapado:

```php
Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>
```

porque un nombre con `<` rompería el HTML. Está verificado con un usuario llamado `<script>alert(1)</script>`: se muestra como texto.

## 8. Limitaciones conocidas de este controlador

Todas las que se listaban aquí **ya están resueltas**; se conservan como referencia del punto de partida:

| # | Limitación (ya resuelta) | Gravedad | Arreglo aplicado |
|---|---|---|---|
| 1 | No se validaba que la `posicion` fuera una de las 5 válidas | Media | `in_array($posicion, POSICIONES, true)` en el controlador |
| 2 | No se validaba la unicidad del número por equipo | Media | `existeDorsalEnEquipo()` con `$ignorarId` al editar |
| 3 | `actualizar()` no comprobaba que existiera el jugador | Baja | `validarId()` antes de guardar |
| 4 | No se validaba `strlen($nombre) > 100` | Baja | `cabeEnColumna()` contra `MAX_NOMBRE` |
| 5 | ⚠️ `confirm()` del cliente no protegía nada en servidor | Baja | Formulario POST con token CSRF |
| 6 | El dorsal 0 se confundía con "sin dorsal" | Media | `0` es válido; solo `null` significa "sin dorsal" |

⚠️ **La 2 era un bug de datos real:** se podían crear dos jugadores con la camiseta 23 en el mismo equipo. Ahora lo bloquean a la vez la validación del controlador (mensaje claro) y el índice `UNIQUE` de la base (garantía final).

---

**Archivos relacionados:** `../../../app/models/Jugador.php` · `../../../app/views/jugadores/index.php` · `../Models/Jugador-explicado.md` · `EquiposController-explicado.md` (el esqueleto base) · `../../04-BD/Modelo-ER.md`

**Siguiente paso →** `PartidosController-explicado.md` (el controlador con más lógica de negocio).