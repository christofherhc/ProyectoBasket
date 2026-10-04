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

**`$numero !== ''` antes de convertir.** Como en `Equipo`, el campo se deja como cadena para distinguir "vacío" de "cero". Con `$numero === '0'`, el `if` **sí** entra, `$numeroInt` vale `0`, y el modelo lo convierte en `NULL` (porque trata `0` como "sin número"). Está bien: se guardó como `NULL`, que es lo correcto para "no informado".

### La validación del `numero`: `< 0 || > 99`

Un rango **razonable**, no arbitrario: en básquetbol las camisetas van de 0 a 99. El `0` es válido (Coby White y Jayson Tatum lo llevan en los datos de prueba), así que el mínimo correcto es `< 0` y no `<= 0`.

⚠️ **No se valida la unicidad del número por equipo.** En la realidad, dos jugadores del mismo equipo no pueden llevar la misma camiseta. Aquí se permitiría. Para añadirlo:

```php
// En el modelo
public function numeroExisteEnEquipo(int $equipoId, int $numero, ?int $ignorarId = null): bool {
    $sql = "SELECT COUNT(*) FROM jugadores WHERE equipo_id = :equipo_id AND numero = :numero";
    $params = [':equipo_id' => $equipoId, ':numero' => $numero];
    if ($ignorarId !== null) {
        $sql .= " AND id != :id";
        $params[':id'] = $ignorarId;
    }
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}
```

Fíjate en `$ignorarId`: al **editar**, el jugador que estás editando ya tiene ese número, así que hay que excluirlo de la comprobación. Es un detalle que se olvida y produce el clásico error de "no puedo editar, dice que el número está duplicado".

### `posicion`: se valida en el `<select>`, no en PHP

El campo `posicion` se pasa tal cual, sin comprobar que sea uno de los cinco valores válidos:

```php
$this->jugadorModel->crear($nombre, $numeroInt, $posicion, $equipoId);
```

**¿Por qué no se valida?** Porque el `<select>` solo ofrece cinco opciones, y un usuario normal no puede enviar otra cosa. Pero se puede manipular la petición (con `curl`, por ejemplo). Un envío directo podría guardar `"Administrador"` como posición.

**Qué haría falta** (no implementado):

```php
$posicionesValidas = ['Base', 'Escolta', 'Alero', 'Ala-Pívot', 'Pívot'];
if ($posicion !== '' && !in_array($posicion, $posicionesValidas, true)) {
    $_SESSION['error'] = 'Posición no válida';
    header('Location: ' . BASE_URL . '?controller=Jugadores&action=crear');
    exit;
}
```

⚠️ **El `true` del tercer parámetro de `in_array()` no es opcional.** Con `in_array($posicion, $posicionesValidas)` y `$posicion = '0'`, compararía `'0' == 'Base'` en PHP 7 y daría un falso positivo. Con `true` la comparación es estricta.

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

**Las mismas limitaciones que en EquiposController:** no comprueba que el jugador exista, y `eliminar()` no maneja el error de clave foránea (aunque `jugadores` **no tiene** referencias hacia otras tablas, así que borrar un jugador nunca falla por FK; es el único de los tres módulos donde `eliminar()` es seguro).

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

| # | Limitación | Gravedad | Arreglo |
|---|---|---|---|
| 1 | No se valida que la `posicion` sea una de las 5 válidas | Media | `in_array($posicion, $validas, true)` |
| 2 | No se valida la unicidad del número por equipo | Media | Consulta con `ignorarId` al editar |
| 3 | `actualizar()` no comprueba que exista el jugador | Baja | `obtenerPorId()` antes |
| 4 | No se valida `strlen($nombre) > 100` | Baja | Validar en PHP |
| 5 | ⚠️ `confirm()` del cliente no protege nada en servidor | Baja | Formulario POST real |

⚠️ **La 2 es un bug de datos real:** puedes crear dos jugadores con la camiseta 23 en el mismo equipo, y el listado los mostrará sin avisar de nada.

---

**Archivos relacionados:** `../../../app/models/Jugador.php` · `../../../app/views/jugadores/index.php` · `../Models/Jugador-explicado.md` · `EquiposController-explicado.md` (el esqueleto base) · `../../04-BD/Modelo-ER.md`

**Siguiente paso →** `PartidosController-explicado.md` (el controlador con más lógica de negocio).