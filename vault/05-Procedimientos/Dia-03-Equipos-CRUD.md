# Día 03 - Equipos: el primer CRUD completo

**Fecha:** 2026-10-03
**Rama:** `main`
**Objetivo:** Construir el primer módulo de principio a fin, con el patrón Modelo → Controlador → Vista.
**Temas a comprender:** CRUD, validación en servidor, mensajes flash, patrón Post/Redirect/Get y `NULL` vs `''`.

## 1. Contexto

El día 02 dio la arquitectura pero ninguna entidad. Hoy se añade la primera, y con ella se ve **el patrón completo**: una tabla, un modelo, un controlador con seis acciones, y tres vistas.

**La pregunta del día:** ¿qué pasa entre que el usuario pulsa "Guardar" y el registro aparece en la base?

## 2. Preparación

- Core MVC funcionando (día 02).
- Tabla `equipos` creada en `schema.sql`.
- Decidir los campos: `nombre` (obligatorio), `ciudad` (obligatoria), `fundacion` (opcional, entero entre 1800 y el año actual, admite `NULL`).

## 3. Pasos realizados

### Paso 3.1: El modelo

```php
class Equipo extends Model {
    protected string $tabla = 'equipos';

    public function crear(string $nombre, string $ciudad, ?int $fundacion = null): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, ciudad, fundacion)
                VALUES (:nombre, :ciudad, :fundacion)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
        if ($fundacion === null || $fundacion === 0) {
            $stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
        }
        return $stmt->execute();
    }

    public function actualizar(int $id, string $nombre, string $ciudad, ?int $fundacion = null): bool {
        // mismo patrón, con WHERE id = :id
    }

    public function obtenerTodos(): array {
        $sql = "SELECT * FROM {$this->tabla} ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

⚠️ **`$fundacion === null || $fundacion === 0` antes de decidir el tipo.** Es el detalle que hace que "sin fundación" se guarde como `NULL` y no como `0`.

**Por qué hace falta el `if`:** `execute([':fundacion' => $fundacion])` manda todo como cadena. En una columna `INT`, MySQL convierte `''` en **`0`**, no en `NULL`. Consecuencia: todos los equipos sin fundación tendrían `fundacion = 0`, y en el listado aparecerían "Fundado en 0", que no es lo mismo que "no se sabe".

⚠️ **`bindValue()` con `PDO::PARAM_NULL` es lo que guarda un `NULL` de verdad.** Es la única forma de enviar un NULL con PDO: pasar la cadena `''` no funciona, y pasar `null` con `PARAM_STR` tampoco.

⚠️ **`ORDER BY nombre` va en el modelo, no en la vista.** El orden es parte de la consulta. La vista solo recorre lo que le llega.

⚠️ **Sin `ORDER BY`, el orden es el que devuelva MySQL**, que no está garantizado y puede cambiar entre ejecuciones o entre servidores. El listado "salía ordenado" durante días y un día dejó de estarlo.

⚠️ **`Equipo` no sobrescribe `obtenerPorId()`,** así que lo hereda de `Model`. Hereda la consulta **sin** `ORDER BY`, lo cual es lo correcto: para un solo registro el orden da igual.

### Paso 3.2: Las seis acciones del controlador

```php
// Las seis acciones públicas, tal como quedaron:
public function index(): void          { /* listar: equipos/index   */ }
public function crear(): void          { /* formulario vacío: equipos/crear */ }
public function guardar(): void        { /* valida el POST e inserta */ }
public function editar(array $params): void { /* carga por id: equipos/editar */ }
public function actualizar(): void     { /* valida el POST y actualiza */ }
public function eliminar(array $params): void { /* borra por id de $params */ }
```

⚠️ **El modelo se llama `$this->equipoModel`, no `$this->modelo`.** En `__construct()` el controlador hace `require_once` del modelo y le asigna la instancia a esa propiedad. El nombre de la propiedad es lo que distingue un controlador de otro: `$this->usuarioModel`, `$this->equipoModel`, `$this->jugadorModel`, `$this->partidoModel`.

⚠️ **`crear()` NO pasa `'errores' => []`.** El proyecto no usa arrays de errores por campo: usa un único `$_SESSION['error']` con una cadena. Y sí pasa `'old'`, que es el array de lo que el usuario escribió (vacío la primera vez).

⚠️ **`index`, `crear`, `editar` son lectura (GET). `guardar`, `actualizar`, `eliminar` son escritura.** Las dos que leen `$_POST` (`guardar` y `actualizar`) comprueban antes que el método de la petición sea `POST`. Todas empiezan con `$this->requiereLogin()`.

⚠️ **`editar()` y `eliminar()` reciben `array $params`, no un `int $id`.** El Router siempre pasa un array (aunque esté vacío), y la firma lo declara así. Por eso dentro se hace `$id = (int)($params['id'] ?? 0)`.

⚠️ **`eliminar()` también pasa por GET**, con un enlace `?controller=Equipos&action=eliminar&id=5`. No hay formulario `POST` ni token: el `confirm()` del JavaScript es una comodidad, no una protección (ver el punto 11 de las lecciones).

⚠️ **Los nombres de las acciones son verbos del usuario, no de la base.** `guardar` llama a un `INSERT`, `actualizar` a un `UPDATE`. Que coincidan es casualidad, no regla.

### Paso 3.3: El flujo de guardado (lo importante)

```php
public function guardar(): void {
    $this->requiereLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
        exit;
    }

    $nombre    = trim($_POST['nombre'] ?? '');
    $ciudad    = trim($_POST['ciudad'] ?? '');
    $fundacion = $_POST['fundacion'] ?? '';

    $_SESSION['old_equipo'] = [
        'nombre' => $nombre,
        'ciudad' => $ciudad,
        'fundacion' => $fundacion
    ];

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

    $this->equipoModel->crear($nombre, $ciudad, $fundacionInt);
    unset($_SESSION['old_equipo']);
    $_SESSION['exito'] = 'Equipo creado correctamente';
    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}
```

**Y en `crear()`, para recuperar los datos si hubo error:**
```php
$old = $_SESSION['old_equipo'] ?? [];
unset($_SESSION['old_equipo']);
```

⚠️ **`ciudad` es OBLIGATORIA.** El `if (empty($nombre) || empty($ciudad))` lo dice claramente, y por eso la firma del modelo es `string $ciudad` (no `?string`). Fue una decisión: un equipo sin ciudad no se puede situar en ningún mapa.

⚠️ **`trim()` antes de validar es lo que hace funcionar la validación.** Sin él, un nombre de cinco espacios (`"   "`) pasaría el `empty()`, porque `"   "` no es una cadena vacía. Con `trim()` la cadena se vuelve `""` y `empty("")` da `true`. El `trim()` no es solo limpieza: es parte de la regla.

⚠️ **El rango usa `date('Y')`,** así que la validación se ajusta sola con el tiempo. Y el `if ($fundacion !== '')` evita que un campo vacío se convierta en `0`, que fallaría el rango con un mensaje confuso ("Año de fundación no válido") cuando lo que pasó es que el usuario no rellenó el campo.

⚠️ **`$_SESSION['old_equipo']` se guarda ANTES de validar,** no después. Así, aunque la validación falle, el usuario no pierde lo que escribió. Si se guardara después, el formulario recargaría vacío.

⚠️ **El patrón Post/Redirect/Get (PRG) es obligatorio aquí.** Sin el `header('Location: ...')`, al recargar la página (F5) se vuelve a enviar el `POST` y **se crea el equipo dos veces**. Con la redirección, la recarga repite un `GET` inocuo.

⚠️ **Los datos del formulario se guardan en sesión, no se pasan por la URL.** Meterlos en la query string los expondría en el historial del navegador y en el `Referer` de los enlaces salientes.

⚠️ **Un solo mensaje, no una lista de errores.** El proyecto usa `$_SESSION['error']` con una cadena, no un array de errores. Es más simple de mostrar y suficiente para formularios de tres campos. Con quince campos, un array por campo sería mejor.

⚠️ **`unset($_SESSION['old_equipo'])` al terminar bien,** antes del mensaje de éxito. Si no se borrara, el siguiente formulario de creación heredaría los datos del anterior.

### Paso 3.4: El mensaje flash

```php
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
```

⚠️ **El `unset()` va DENTRO del `if`.** Si estuviera fuera, borraría el mensaje aunque no hubiera ninguno, y el día que se añadiera un segundo mensaje en la misma vista, el primero desaparecería.

⚠️ **`isset()` y no `empty()`.** `isset()` devuelve `false` si la variable no existe **o** si vale `null`, que es justo lo que se necesita: un mensaje que vale `null` no es un mensaje.

⚠️ **¿Por qué sesión y no un parámetro?** Un mensaje en la URL sobrevive al F5 y aparecería cada vez que se recarga. En sesión se consume al mostrarlo: sale una vez y desaparece.

### Paso 3.5: La vista del listado

```php
<a href="<?= BASE_URL ?>?controller=Equipos&action=eliminar&id=<?= $eq['id'] ?>"
   onclick="return confirm('¿Eliminar <?= htmlspecialchars($eq['nombre']) ?>?')">
```

⚠️ **`confirm()` es JavaScript del navegador.** Si el usuario tiene JavaScript desactivado, **el borrado ocurre igual**. No es una protección real: es una comodidad. La protección de verdad sería exigir POST + token CSRF.

⚠️ **`htmlspecialchars()` dentro del `onclick`**, y con comillas HTMLentities. El nombre del equipo viene de la base y puede contener comillas o `<`. Sin escapar, un equipo llamado `O'Brien` rompe el atributo `onclick` y el JavaScript entero deja de funcionar.

### Paso 3.6: `htmlspecialchars()` en todas las salidas

```php
<?= htmlspecialchars($eq['ciudad'] ?? '') ?>
```

⚠️ **Escapar SIEMPRE, aunque el dato sea "fiable".** Una cosa es que el usuario valide el nombre y otra que el nombre no lleve `<script>`. Esa validación se puede saltar por el formulario, por phpMyAdmin, por un import...

## 4. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/models/Equipo.php` | crear | `obtenerTodos()` con `ORDER BY`, `crear()`, `actualizar()` |
| `app/controllers/EquiposController.php` | crear | 6 acciones con `requiereLogin()` |
| `app/views/equipos/index.php` | crear | Tabla de listados + enlace a crear |
| `app/views/equipos/crear.php` | crear | Formulario de alta |
| `app/views/equipos/editar.php` | crear | Formulario de edición |
| `app/views/layouts/main.php` | editar | Enlaces de navegación a Equipos |

## 5. Explicación técnica: el ciclo completo de un `INSERT`

```
1. Usuario rellena el formulario y pulsa "Guardar"        (navegador)
2. POST a index.php?controller=Equipos&action=guardar     (navegador)
3. session_start() ya corre; el Router resuelve la acción (public/index.php)
4. requiereLogin() comprueba $_SESSION['usuario_id']     (controlador)
5. Se validan los campos; si falla → mensaje + redirect  (controlador)
6. Model::crear() → $db->prepare() → execute()           (modelo)
7. lastInsertId() para saber qué se creó                  (modelo)
8. Mensaje flash + redirect a index                       (controlador)
9. El navegador pide index.php?...action=index            (navegador)
10. index() carga los equipos y los pasa a la vista       (controlador)
11. La vista imprime la tabla                             (vista)
```

⚠️ **El paso 5 y el 6 están en archivos distintos a propósito.** Si la validación viviera en el modelo, el modelo tendría que saber qué es un nombre válido, cuándo redirigir y cómo mostrar un error. **El modelo habla con la base; el controlador habla con el usuario.**

## 6. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| El listado muestra los 4 equipos de `seeds.sql` | ✅ |
| El listado está ordenado alfabéticamente | ✅ |
| Crear equipo con nombre, ciudad y fundación | ✅ |
| Crear con nombre y ciudad, sin fundación → `fundacion = NULL` | ✅ |
| Nombre vacío → error y vuelve al formulario | ✅ |
| Nombre de solo espacios → error | ✅ |
| Fundación vacía → se guarda como `NULL`, no como `0` | ✅ |
| Fundación = 1850 → aceptada | ✅ |
| Fundación = 1500 → rechazada con "Año de fundación no válido" | ✅ |
| Fundación = 3000 → rechazada (mayor que el año actual) | ✅ |
| Ciudad vacía → rechazada ("Nombre y ciudad son obligatorios") | ✅ |
| Nombre de cinco espacios → rechazado (gracias al `trim`) | ✅ |
| `action=guardar` por GET → redirige al formulario, no inserta | ✅ |
| Editar cambia los datos y se ven en el listado | ✅ |
| Eliminar quita la fila de la tabla | ✅ |
| El mensaje flash sale **una sola vez** | ✅ |
| Recargar con F5 tras guardar **no** duplica el equipo | ✅ |
| Un nombre con `<script>` se muestra como texto | ✅ |
| Un nombre con comilla simple no rompe el HTML | ✅ |
| El listado sigue ordenado tras crear y editar | ✅ |
| `php -l` sin errores en los 5 archivos nuevos | ✅ |
| Tras un error, el formulario conserva lo que el usuario escribió | ✅ |
| Tras un éxito, el formulario siguiente viene vacío | ✅ |

**La prueba de "recargar con F5 no duplica" es la que validates el PRG.** Sin la redirección, esa prueba falla y aparece un equipo repetido.

## 7. Commits realizados

```
feat: añade módulo de Equipos (modelo, controlador, vistas) con CRUD completo
```

## 8. Aprendizajes del día

1. **El CRUD son 6 acciones, no 4.** Listar y ver el formulario vacío son distintas de ver el formulario con datos.
2. **Validar en el servidor, siempre.** El `required` del HTML es una comodidad, no una garantía.
3. **El patrón PRG evita el doble envío.** Sin redirección tras el `POST`, un F5 crea el registro otra vez.
4. **`trim()` antes de validar.** "   " no está vacío, pero tampoco es un nombre.
5. **`''` y `NULL` son cosas distintas.** `intval('')` es `0`, y `0` no es "desconocido": hace falta `PDO::PARAM_NULL`.
6. **`trim()` es parte de la regla, no solo limpieza.** Sin él, cinco espacios pasan el `empty()`.
7. **Un rango con `date('Y')` se ajusta solo.** Una validación con el año actual no hay que tocarla nunca.
8. **Los mensajes flash se consumen al mostrarlos.** Por eso el `unset()` va dentro del `if`.
9. **`htmlspecialchars()` en todas las salidas, incluidas las "internas".** Un nombre con `<script>` viene de la base y la base no lo valida.
10. **El orden va en el modelo.** Sin `ORDER BY`, MySQL no garantiza ningún orden.
11. **`confirm()` no es seguridad.** Es comodidad. Con JavaScript desactivado, el borrado ocurre igual.

## 10. Dudas pendientes

⚠️ **Problemas detectados que se documentan pero no se arreglan:**

| # | Problema | Gravedad | Dónde se anota |
|---|---|---|---|
| 1 | `eliminar()` da error de FK si el equipo tiene jugadores o partidos | **Alta** | Día 08, tabla de limitaciones |
| 2 | `eliminar()` por GET (borrado con F5 o con el historial) | Baja | Día 08 |
| 3 | `actualizar()` no comprueba que el registro exista: responde "actualizado" aunque no haya nada | Baja | Día 08 |
| 4 | Sin protección CSRF en ningún formulario | Media | Día 08 |

⚠️ **El 1 es el más visible de todos:** los 4 equipos de `seeds.sql` tienen jugadores y partidos, así que **intentar borrar cualquiera de ellos siempre falla**. Se detectó en el día 08, al probar el borrado de verdad.

## 11. Próximos pasos

**Día 04 - Jugadores:** el mismo CRUD pero con una **relación**. Cada jugador pertenece a un equipo, y eso obliga a aprender `JOIN`, a construir un desplegable y a manejar `NULL`.