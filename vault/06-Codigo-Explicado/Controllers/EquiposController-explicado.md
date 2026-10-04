# EquiposController (explicado)

**Archivo real:** `app/controllers/EquiposController.php`
**Rol:** CRUD de equipos. **Empieza por este controlador**: es el patrón que repiten los otros tres.

## 1. El esqueleto de un CRUD

Los 4 módulos de la aplicación tienen exactamente la misma estructura:

| Método | Qué hace | Recibe datos por |
|---|---|---|
| `index()` | Listado | — |
| `crear()` | Formulario de alta | — |
| `guardar()` | Procesa el alta | POST |
| `editar()` | Formulario de edición | GET (`id`) |
| `actualizar()` | Procesa la edición | POST |
| `eliminar()` | Borra | GET (`id`) |

Y todos empiezan igual:

```php
public function index(): void {
    $this->requiereLogin();     // ← siempre la primera línea
    ...
}
```

**Si entiendes este controlador, entiendes los cuatro.**

## 2. El constructor

```php
class EquiposController extends Controller {
    private Equipo $equipoModel;

    public function __construct() {
        require_once '../app/models/Equipo.php';
        $this->equipoModel = new Equipo();
    }
```

**`private` en la propiedad** significa "solo este controlador la usa". Si el modelo fuera `public`, otras clases podrían cambiarlo.

**¿Por qué `require_once` aquí y no en `index.php`?** Porque cada controlador carga **solo** los modelos que necesita. Si `index.php` cargara los 4 modelos siempre, la página de login también abriría la base de datos para nada.

- `AuthController` carga `Usuario`.
- `EquiposController` carga `Equipo`.
- `JugadoresController` carga `Jugador` **y** `Equipo` (para el `<select>` del formulario).
- `PartidosController` carga `Partido` **y** `Equipo`.
- `EstadisticasController` carga solo `Partido`.

**Nota sobre `extends Controller` sin llaves:** PHP 8 permite `extends Controller { ... }` sin la palabra `class` repetida. La forma clásica es:

```php
class EquiposController extends Controller {
```

El constructor se llama una sola vez por el `Router`, así que la conexión se abre **una vez** al empezar cada petición.

## 3. `index()`: el listado

```php
public function index(): void {
    $this->requiereLogin();
    $equipos = $this->equipoModel->obtenerTodos();
    $this->view('equipos/index', [
        'titulo' => 'Listado de Equipos',
        'equipos' => $equipos
    ]);
}
```

Cuatro pasos: comprobar sesión → pedir datos → pasar datos a la vista.

**`$this->view('equipos/index', [...])`** carga el layout, que a su vez carga `views/equipos/index.php`. Dentro de esa vista, `extract()` ha creado las variables `$titulo` y `$equipos`.

**El array se llama así** porque `extract()` convierte las claves en variables. `'equipos' => $equipos` se convierte en `$equipos`. Detalle: la clave y el valor a menudo tienen el mismo nombre, y eso es por comodidad al leer.

## 4. `crear()`: guardar lo escrito por si falla

```php
public function crear(): void {
    $this->requiereLogin();
    $old = $_SESSION['old_equipo'] ?? [];
    unset($_SESSION['old_equipo']);
    $this->view('equipos/crear', [
        'titulo' => 'Crear Equipo',
        'old' => $old
    ]);
}
```

**Las tres líneas de `$old` siempre van juntas.** Este patrón aparece en los 4 controladores y es importante entenderlo:

| Línea | Para qué |
|---|---|
| `$old = $_SESSION['old_equipo'] ?? []` | Recoge lo que el usuario escribió antes |
| `unset($_SESSION['old_equipo'])` | Lo consume: no volverá a mostrarse |
| `'old' => $old` | Lo pasa a la vista para rellenar el formulario |

**El problema que resuelve.** Cuando el formulario falla la validación, el controlador **redirige** a la página de creación (PRG, ver `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 10). Como la redirección es una petición nueva, las variables locales se pierden: el usuario vería el formulario **vacío**.

Guardando los datos en la sesión antes de validar y leyéndolos en `crear()`, los datos sobreviven al salto.

**Sin el `unset`, el formulario quedaría prellenado para siempre**, incluso después de guardar con éxito. Está verificado: los formularios vacíos después de crear bien.

**El nombre de la clave incluye el módulo** (`old_equipo`, `old_jugador`, `old_partido`) para que no se mezclen si el usuario tuviera dos formularios abiertos en pestañas distintas.

## 5. `guardar()`: el patrón completo

```php
public function guardar(): void {
    $this->requiereLogin();

    // 1. Solo por POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
        exit;
    }

    // 2. Leer y limpiar
    $nombre    = trim($_POST['nombre'] ?? '');
    $ciudad    = trim($_POST['ciudad'] ?? '');
    $fundacion = $_POST['fundacion'] ?? '';

    // 3. Guardar por si hay que volver
    $_SESSION['old_equipo'] = [
        'nombre' => $nombre,
        'ciudad' => $ciudad,
        'fundacion' => $fundacion
    ];

    // 4. Validar
    if (empty($nombre) || empty($ciudad)) {
        $_SESSION['error'] = 'Nombre y ciudad son obligatorios';
        header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
        exit;
    }

    $fundacionInt = null;
    if ($fundacion !== '') {
        $fundacionInt = (int)$fundacion;
        if ($fundacionInt < 1800 || $fundacionInt > (int)date('Y')) {
            $_SESSION['error'] = 'Año de fundación no válido';
            header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
            exit;
        }
    }

    // 5. Guardar
    $this->equipoModel->crear($nombre, $ciudad, $fundacionInt);
    unset($_SESSION['old_equipo']);
    $_SESSION['exito'] = 'Equipo creado correctamente';
    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}
```

### ⚠️ `$fundacion` se deja como cadena a propósito

Fíjate en que `fundacion` **no** lleva `trim()` ni `(int)`. Se deja como cadena para poder distinguir tres casos que PHP confunde:

| Entrada del usuario | `$fundacion` | `$fundacionInt` | Se guarda |
|---|---|---|---|
| `""` (campo vacío) | `''` | `null` | `NULL` |
| `"1947"` | `'1947'` | `1947` | `1947` |
| `"abc"` | `'abc'` | `0` → falla el rango | error |

Si hicieras `(int)` antes, `""` y `"abc"` darían ambos `0`, y no podrías distinguirlos. Está documentado en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 7.

**La validación del rango: `< 1800` y `> date('Y')`.**

- El mínimo (1800) es arbitrario pero razonable: ninguna liga de básquetbol existía antes.
- El máximo usa el **año actual**. Es dinámico: en 2027 seguirá siendo válido, sin tocar el código.

`(int)date('Y')` convierte "2026" a `2026`, para comparar enteros con enteros. Sin el `int`, se compararía string con número y PHP haría una comparación textual.

⚠️ **Lo que NO se valida:** que la fundación sea múltiplo de 4, ni que el nombre no esté repetido. En un proyecto real se añadirían reglas de negocio así. La validación aquí es suficiente para la práctica.

⚠️ **Y lo que no se puede validar desde aquí:** si el nombre tiene 4 millones de caracteres, MySQL lo rechazará por `VARCHAR(100)`. Eso produce un error de SQL. Lo ideal sería validar la longitud en PHP:

```php
if (strlen($nombre) > 100) {
    $_SESSION['error'] = 'El nombre no puede superar 100 caracteres';
    ...
}
```

No está implementado: es una mejora conocida.

## 6. `editar()`: comprobar que existe

```php
public function editar(array $params): void {
    $this->requiereLogin();
    $id = (int)($params['id'] ?? 0);
    $equipo = $this->equipoModel->obtenerPorId($id);

    if ($equipo === null) {
        $_SESSION['error'] = 'Equipo no encontrado';
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }

    $old = $_SESSION['old_equipo'] ?? [];
    unset($_SESSION['old_equipo']);
    $this->view('equipos/editar', [
        'titulo' => 'Editar Equipo',
        'equipo' => $equipo,
        'old' => $old
    ]);
}
```

**`$id = (int)($params['id'] ?? 0)`** es una línea de defensa triple:
1. `?? 0` → si no hay `id`, usa 0.
2. `(int)` → convierte a número. Así `?id=abc` se vuelve `0`, no una cadena.
3. Con `0`, `obtenerPorId(0)` no encuentra nada y cae en el mensaje de error.

**Verificado:** pedir `?id=abc` o `?id=99999` muestra "Equipo no encontrado" y no se rompe. Un principiante que hiciera `SELECT * FROM equipos WHERE id = '$id'` tendría un error de SQL con `?id=abc` (y una inyección).

**`$params['id'] ?? 0` y NO `$params['id']`.** El `??` evita el aviso "Undefined array key" si alguien llama a `editar()` sin parámetros.

**Note que `editar()` NO necesita `$_POST`.** Recibe el `id` por query string: `?controller=Equipos&action=editar&id=3`. El `Router` lo mete en `$params`.

## 7. `actualizar()`: casi idéntico a `guardar()`

Las diferencias, y son tres:

**a) El `id` viene por POST** (`$_POST['id']`), no por query string. Porque actualizar es un envío de formulario. El campo `id` va escondido: `<input type="hidden" name="id" value="...">`.

**b) La validación incluye el id:**
```php
if (empty($nombre) || empty($ciudad) || $id <= 0) {
```
Un UPDATE sin id no tiene sentido.

**c) ⚠️ Los errores redirigen a distinto sitio.** Compara:

| Error en... | Redirige a |
|---|---|
| `guardar()` | `action=crear` |
| `actualizar()` | `action=index` o `action=editar&id=$id` |

En este proyecto, `actualizar()` redirige al **listado** cuando faltan nombre o ciudad, pero al **formulario de edición** cuando el año no es válido. Es una inconsistencia menor, pero conviene saber que está ahí.

⚠️ **Y lo más importante que no se hace:** `actualizar()` **no comprueba que el equipo exista**. Si mandas `id=99999`, el `UPDATE` no afecta a ninguna fila, no da error, y muestra "Equipo actualizado correctamente". La comprobación de existencia solo está en `editar()`.

La forma correcta sería:

```php
if ($this->equipoModel->obtenerPorId($id) === null) {
    $_SESSION['error'] = 'Equipo no encontrado';
    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}
```

Verificado: se probó `actualizar` con `id=99999` y **no se creó nada** (0 filas afectadas), pero el mensaje es de éxito. **Limitación conocida**, no un fallo grave.

## 8. `eliminar()`

```php
public function eliminar(array $params): void {
    $this->requiereLogin();
    $id = (int)($params['id'] ?? 0);

    if ($id > 0) {
        $this->equipoModel->eliminar($id);
        $_SESSION['exito'] = 'Equipo eliminado correctamente';
    } else {
        $_SESSION['error'] = 'ID de equipo inválido';
    }

    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}
```

**`$id > 0` en lugar de validar cada caso.** Es una forma compacta de decir "si el id es un entero positivo, borra; si no, avisa".

### ✅ Cómo quedó este método tras la auditoría

Estas tres limitaciones **estaban** y ya no están. Se conservan aquí porque el arreglo es la parte importante.

**a) El borrado ya es por POST con token CSRF.** El enlace del listado se sustituyó por un formulario:

```html
<form action="<?= BASE_URL ?>?controller=Equipos&action=eliminar" method="POST" class="inline-form"
      onsubmit="return confirm('¿Eliminar este equipo?');">
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= $equipo['id'] ?>">
    <button type="submit" class="btn btn-danger">Eliminar</button>
</form>
```

Y el controlador empieza con `exigirPost()`, que corta la petición si no es POST o si el token no cuadra:

```php
public function eliminar(array $params): void {
    $this->requiereLogin();
    exigirPost('controller=Equipos&action=index');
    $id = (int)post('id');
```

Un `?controller=Equipos&action=eliminar&id=1` a pelo ahora **redirige sin borrar nada**, y un POST sin token se rechaza con HTTP 403.

⚠️ **`confirm()` sigue siendo solo comodidad.** Sigue estando en el `onsubmit`, pero ya no es lo único que protege: aunque alguien lo desactive, la petición es un POST con un token válido que el servidor exige. Antes el borrado ocurría igual; hoy depende de protecciones reales.

**b) El borrado con clave foránea ya no da pantalla en blanco.** Este era el punto más delicado del proyecto. `equipos` tiene `ON DELETE RESTRICT` en sus dos referencias, así que:

```sql
DELETE FROM equipos WHERE id = 1;   -- si tiene 3 jugadores
-- ERROR 1451: Cannot delete or update a parent row
```

Con `ERRMODE_EXCEPTION` la excepción subía hasta `index.php`, que no tenía ningún `try/catch`, y el usuario veía una página vacía. **Era el error más probable al probar el proyecto**: los 4 equipos de `seeds.sql` tienen jugadores, así que el primer "Eliminar" que se pulsaba fallaba.

Se resolvió por dos capas:

**Capa 1 — avisar antes de intentarlo** (la que el usuario ve):

```php
$dependientes = $this->contarDependientes($id);
if ($dependientes['jugadores'] > 0 || $dependientes['partidos'] > 0) {
    flashError(
        'No se puede eliminar "' . $this->nombreEquipo($id) . '": tiene '
        . $dependientes['jugadores'] . ' jugador(es) y '
        . $dependientes['partidos'] . ' partido(s) registrados.'
    );
    redir('controller=Equipos&action=index');
}
```

```php
private function contarDependientes(int $id): array {
    $db = Database::getConexion();
    $stmt = $db->prepare(
        'SELECT
            (SELECT COUNT(*) FROM jugadores WHERE equipo_id = :a) AS jugadores,
            (SELECT COUNT(*) FROM partidos
              WHERE equipo_local_id = :b OR equipo_visitante_id = :c) AS partidos'
    );
    // ... bindValue de los tres parámetros
}
```

⚠️ **Una consulta con dos `SELECT COUNT(*)` dentro y `FOREIGN KEY` intacta.** Comprobar antes tiene una limitación real: entre la comprobación y el borrado otro proceso podría insertar. Por eso hace falta la segunda capa.

**Capa 2 — un manejador global de excepciones.** `public/index.php` registra `set_exception_handler('manejarExcepcion')`, que traduce los códigos de MySQL a un mensaje entendible:

| Código | Significado | Mensaje que ve el usuario |
|---|---|---|
| 1451 | FK `RESTRICT` | "No se puede eliminar: tiene datos asociados" |
| 1452 | FK inexistente | "El registro seleccionado no existe" |
| 1406 | Texto demasiado largo | "Algún texto es más largo de lo permitido" |
| 1292 | Fecha inválida | "La fecha indicada no es válida" |
| 1264 | Número fuera de rango | "Un número está fuera de rango" |
| 1062 | Duplicado | "Ya existe un registro con esos datos" |

⚠️ **El manejador nunca imprime el mensaje de MySQL.** Ese texto incluye nombres de tablas y columnas; mostrárselo a un visitante es filtrar información interna. Se registra con `error_log()` y al usuario se le enseña la traducción.

⚠️ **Con la validación en sitio, el manejador no debería aparecer nunca.** Es la red de seguridad: si mañana alguien añade una consulta que no valida, el usuario recibe un 409 con un mensaje razonable en vez de una pantalla en blanco.


## 9. El patrón a memorizar

```php
public function guardar(): void {
    $this->requiereLogin();                                  // 1. seguridad
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ...exit; }    // 2. solo POST
    $campo = trim($_POST['campo'] ?? '');                    // 3. leer
    $_SESSION['old_x'] = ['campo' => $campo];                // 4. guardar por si falla
    if (empty($campo)) {                                      // 5. validar
        $_SESSION['error'] = '...';
        header('Location: ' . BASE_URL . '?controller=X&action=crear');
        exit;
    }
    $this->xModel->crear($campo);                             // 6. persistir
    unset($_SESSION['old_x']);                                // 7. limpiar
    $_SESSION['exito'] = '...';                               // 8. mensaje
    header('Location: ' . BASE_URL . '?controller=X&action=index');
    exit;                                                     // 9. PRG
}
```

**Ocho pasos, siempre en el mismo orden.** Cuando escribas un controlador nuevo, copia este esqueleto y cambia los nombres. Esa consistencia es lo que hace el proyecto fácil de leer.

## 10. Limitaciones conocidas de este controlador

| # | Limitación (ya resueltas) | Gravedad | Arreglo aplicado |
|---|---|---|---|
| 1 | Borrar un equipo con jugadores/partidos daba error de FK sin capturar | **Alta** | `contarDependientes()` avisa antes; además `manejarExcepcion()` como red de seguridad |
| 2 | Eliminar por GET, sin token CSRF | Media | Formulario POST + `exigirPost()` |
| 3 | `actualizar()` no comprobaba que el equipo exista | Baja | `validarId()` en el controlador + comprobación de existencia en el modelo |
| 4 | No se validaba la longitud de los textos | Baja | `cabeEnColumna()` contra `MAX_NOMBRE` / `MAX_CIUDAD` |
| 5 | `fundacion=abc` se convertía en un año basura | Baja | `ctype_digit()` antes de convertir |
| 6 | `die()` sin escapar en el Router | Baja | El Router ya no imprime el dato; usa `error()` con 404 |
| 7 | Sin rate limit en el login | Media | Bloqueo tras 5 intentos en 15 minutos |

⚠️ **Lo que sigue abierto a propósito:** el nombre de equipo **no** es único. Dos equipos pueden llamarse "Real Madrid". No se añade `UNIQUE` porque el enunciado no lo pide y en una liga real puede haber equipos con el mismo nombre en ciudades distintas. Si algún día lo necesitas, es un `ALTER TABLE equipos ADD UNIQUE (nombre)`.

---

**Archivos relacionados:** `../../../app/models/Equipo.php` · `../../../app/views/equipos/index.php` · `../Core/Controller-explicado.md` · `PartidosController-explicado.md` (el más complejo) · `../../08-Errores-y-Soluciones/Registro-de-errores.md`

**Siguiente paso →** `JugadoresController-explicado.md` (añade una relación con `Equipo`).