# Router (explicado)

**Archivo real:** `app/core/Router.php`
**Rol en la arquitectura:** traduce la URL en "qué controlador y qué método se ejecutan".

## 1. El problema que resuelve

Una URL tiene que acabar pidiendo una clase y un método:

```
?controller=Partidos&action=editar&id=7
      ↓
PartidosController → editar(['id' => '7'])
```

El `Router` es el que hace esa traducción. El nombre "front controller" (controlador frontal) viene de que **todas** las peticiones pasan por este único punto de entrada, `public/index.php`.

## 2. Las tres URL que maneja el proyecto

| URL | Controlador | Método |
|---|---|---|
| `?controller=Auth&action=index` | `AuthController` | `index()` |
| `?controller=Equipos&action=crear` | `EquiposController` | `crear()` |
| `?controller=Partidos&action=editar&id=7` | `PartidosController` | `editar(['id' => '7'])` |

Es una URL "declarativa": **los parámetros son palabras, no segmentos de ruta**. No es el estilo `/partidos/editar/7` de frameworks como Laravel, sino el de query string, que es más sencillo de entender y perfectly adequate para un proyecto escolar.

## 3. El código, paso a paso

### El constructor: tres llamadas en orden

```php
public function __construct() {
    $this->parseUrl();      // 1. leer la URL
    $this->loadController(); // 2. cargar e instanciar la clase
    $this->executeAction();  // 3. ejecutar el método
}
```

**El orden importa.** No puedes ejecutar el método sin haber cargado el controlador, ni puedes cargar el controlador sin saber qué cargar. Todo el enrutado ocurre en el constructor, así que en `index.php` basta con:

```php
new Router();   // eso es todo
```

Un constructor que hace el trabajo completo es un poco raro (lo ideal sería `new Router(); $router->run();`), pero para un front controller de este tamaño resulta práctico.

### `parseUrl()`: leer y normalizar

```php
private string $controllerNombre = 'Auth';   // valor por defecto
private string $actionNombre = 'index';      // valor por defecto

private function parseUrl(): void {
    if (isset($_GET['controller']) && !empty(trim($_GET['controller']))) {
        $controller = trim($_GET['controller']);
        $this->controllerNombre = ucfirst(strtolower($controller));
    }

    if (isset($_GET['action']) && !empty(trim($_GET['action']))) {
        $action = trim($_GET['action']);
        $this->actionNombre = strtolower($action);
    }

    $this->params = $_GET;
    unset($this->params['controller']);
    unset($this->params['action']);
}
```

**Los valores por defecto importan mucho.** Si abres `public/index.php` sin parámetros, el `Router` carga `AuthController` y llama a `index()`, que muestra el login. Por eso entrar a `http://localhost/ProyectoBasket/public/` ya te lleva a la pantalla de acceso sin escribir nada.

**`ucfirst(strtolower($controller))` normaliza el nombre.** Convierte `PARTIDOS`, `partidos` o `Partidos` en `Partidos`, siempre. Sin esto, `?controller=partidos` buscaría `partidosController.php`, un archivo que no existe (en Linux el nombre de archivo es sensible a mayúsculas: `Archivo` y `archivo` son distintos). Con esto, da igual cómo lo escriba el usuario.

Ojo con el detalle: el archivo se busca como `$nombreClase . 'Controller'`, es decir, `PartidosController.php`. El sufijo `Controller` se añade **en `loadController()`**, no en `parseUrl()`.

**`isset()` + `!empty(trim(...))` es una defensa triple.** Rechaza que no exista el parámetro, que llegue vacío y que llegue solo con espacios. Sin el `isset`, un `trim()` sobre `null` generaría un aviso de PHP.

**`$this->params = $_GET` seguido de los dos `unset()`.** Los parámetros sobrantes (`id`, etc.) se separan de los dos nombres de ruta, de forma que `editar()` recibe solo lo que le interesa:

```php
// URL: ?controller=Partidos&action=editar&id=7
$this->params = ['id' => '7'];   // sin 'controller' ni 'action'
```

### `loadController()`: de nombre a archivo

```php
private function loadController(): void {
    $nombreClase = $this->controllerNombre . 'Controller';
    $rutaControlador = __DIR__ . '/../controllers/' . $nombreClase . '.php';

    if (!is_file($rutaControlador)) {
        $this->error('No se encontró el archivo del controlador.');
    }

    require_once $rutaControlador;

    if (!class_exists($nombreClase)) {
        $this->error('No existe la clase del controlador.');
    }

    $this->controllerObjeto = new $nombreClase();
}
```

**Las dos comprobaciones no son redundantes.** Son dos fallos distintos:
- `is_file` falla → no hay archivo PHP en esa ruta.
- `class_exists` falla → el archivo existe pero dentro no hay ninguna clase con ese nombre.

Sin el segundo `if`, si el archivo se llamara `PartidosController.php` pero la clase fuera `PartidoController`, `new $nombreClase()` daría un error críptico de PHP en vez de un mensaje claro.

**`new $nombreClase()` con variable.** PHP permite instanciar una clase cuyo nombre está en una variable. Aquí `$nombreClase` es `"PartidosController"`, así que es como si pusieras `new PartidosController()`. Esto es lo que permite el enrutado dinámico.

**✅ Ni un solo `die()` con datos dentro.** El nombre del controlador ya se validó contra la lista blanca `RUTAS` en `parseUrl()`, así que no puede contener nada escrito por un atacante. Aun así, el `Router` no imprime el nombre del archivo ni ningún valor de la URL: todo pasa por el método `error()`, que devuelve **404 con una página propia** y solo muestra un mensaje fijo.

```php
private function error(string $mensaje): void {
    if (!headers_sent()) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<title>Petición no válida</title></head><body>'
        . '<h1>Petición no válida</h1><p>' . e($mensaje) . '</p>'
        . '<p><a href="' . e(BASE_URL) . '">Volver al inicio</a></p>'
        . '</body></html>';
    exit;
}
```

**Lección:** lo ideal es *no imprimir* el dato, no solo escaparlo. Escapar funciona; no imprimir es imposible de exploitationar.

### `executeAction()`: llamar al método

```php
private function executeAction(): void {
    if ($this->controllerObjeto === null) {
        $this->error('Controlador no instanciado.');
    }

    // is_callable() y no method_exists(): ver la nota de abajo.
    if (!is_callable([$this->controllerObjeto, $this->actionNombre])) {
        $this->error('La acción solicitada no está disponible.');
    }

    call_user_func_array(
        [$this->controllerObjeto, $this->actionNombre],
        [$this->params]
    );
}
}
```

**`is_callable()` antes de llamar.** Es la comprobación que evita el error fatal "Call to undefined method".

⚠️ **Aquí se usa `is_callable()` y no `method_exists()`, y hay un motivo concreto:** `method_exists()` devuelve `true` también para métodos **protegidos** y **privados**, no solo para los públicos. Con `method_exists()` se podía pedir `?action=view` (que es `protected` en `Controller`) y PHP respondía con un error fatal al intentar invocarlo. `is_callable()` solo devuelve `true` para lo que se puede llamar desde fuera.

Probado antes del arreglo:

| URL | Con `method_exists()` | Con `is_callable()` |
|---|---|---|
| `?action=view` | HTTP 500 (fatal) | HTTP 404 |
| `?action=requiereLogin` | HTTP 500 (fatal) | HTTP 404 |
| `?action=__construct` | HTTP 200 (sin hacer nada) | HTTP 404 |

`__construct` es el caso más curioso: `method_exists()` lo encontraba y `call_user_func_array()` lo invocaba, construyendo el controlador una segunda vez sin ejecutar ninguna acción.

⚠️ **`is_callable()` por sí solo no basta.** Un método público inventado (`?action=volar`) seguiría siendo invocable, y `__construct` es público. Por eso el `Router` además contrasta el controlador y la acción contra la constante `RUTAS` antes de cargar nada. Las dos comprobaciones juntas: la lista blanca dice *qué* se puede llamar, `is_callable()` confirma que *realmente* se puede.

**`call_user_func_array()`** es el equivalente en tiempo de ejecución de `metodo($args)`. Es lo que hace posible pasar argumentos dinámicos:

```php
// Lo que realmente ejecuta, expandido:
$this->controllerObjeto->editar(['id' => '7']);
```

Fíjate en que `$this->params` se pasa como **un único argumento** que es un array. Por eso los métodos que reciben parámetros declaran `array $params`:

```php
public function editar(array $params): void {
    $id = (int)($params['id'] ?? 0);
}
```

Y por eso `index()` no acepta parámetros: el `Router` siempre pasa el array, y un método que no lo declara... simplemente lo ignora (PHP no da error por argumentos sobrantes).

**⚠️ Ese comportamiento es una trampa.** Si declaras `index()` sin parámetro y luego escribes `$params['id']` dentro, suelta un aviso de variable no definida. Si declaras `editar()` **sin** `array $params` y haces `$params['id']`, PHP da error fatal. **Regla:** todo método público de un controlador recibe `array $params`, y accedes siempre con `$params['x'] ?? valorPorDefecto`.

## 4. Un ejemplo completo, de la URL al resultado

```
Navegador pide: ?controller=Partidos&action=editar&id=7
        │
        ▼
public/index.php
  session_start()
  require config.php, Database, Model, Controller, Router
        │
        ▼
new Router()
        │
        ├─ parseUrl()
        │    controllerNombre = 'Partidos'
        │    actionNombre    = 'editar'
        │    params          = ['id' => '7']
        │
        ├─ loadController()
        │    archivo: ../app/controllers/PartidosController.php
        │    objeto : PartidosController
        │
        └─ executeAction()
             PartidosController::editar(['id' => '7'])
                 ├─ requiereLogin()          → si no hay sesión, va al login
                 ├─ obtenerPorId(7)          → busca en la base
                 ├─ si no existe → mensaje "Partido no encontrado"
                 └─ view('partidos/editar', ['partido' => ..., 'equipos' => ...])
                      └─ layouts/main.php
                           └─ partidos/editar.php  ← el HTML que ve el usuario
```

## 5. La lista blanca `RUTAS` (añadido en la auditoría)

Esta es la pieza de seguridad más importante del router. Antes de cargar nada, se contrasta la URL contra una constante:

```php
private const RUTAS = [
    'auth'         => ['index', 'login', 'register', 'guardarregistro', 'logout'],
    'equipos'      => ['index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar'],
    'jugadores'    => ['index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar'],
    'partidos'     => ['index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar'],
    'estadisticas' => ['index'],
];
```

Un controlador que no esté en el array no se carga. Una acción que no esté en la fila de su controlador no se ejecuta.

⚠️ **Las acciones van en minúsculas** porque la comparación es `strtolower()` en los dos lados. El PHP real se llama `guardarRegistro`, con R mayúscula, y sigue funcionando: PHP resuelve los nombres de método sin distinguir mayúsculas, y la lista blanca solo normaliza para poder compararla de forma fiable.

⚠️ **Sin lista blanca, `?controller=../../etc/passwd` construía una ruta de archivo con lo que mandara el visitante.** Ahora es imposible por construcción: si el nombre no está en el array, se corta la petición antes de tocar el disco.

### Los helpers `get()` y `post()`

`parseUrl()` ya no llama a `trim($_GET['controller'])` directamente. Usa los helpers de `app/core/helpers.php`:

```php
$controlador = strtolower(trim(get('controller', '')));
$accion      = strtolower(trim(get('action', 'index')));
```

```php
function get(string $clave, string $defecto = ''): string {
    if (!isset($_GET[$clave]) || !is_string($_GET[$clave])) {
        return $defecto;
    }
    return $_GET[$clave];
}
```

**Por qué el `is_string()`.** Con `?controller[]=x`, PHP construye un array, no un texto. `trim()` sobre un array lanza un `TypeError` en PHP 8 y el visitante ve una pantalla en blanco. Con el `is_string()` el array se descarta y la petición sigue su curso (en este caso, el controlador por defecto). Ver el registro de errores #19.

## 6. Qué NO hace este router

- **No usa `$_SERVER['PATH_INFO']`** ni reescritura de URLs. Todo va por query string. Para un proyecto escolar es más que suficiente.
- **No hay middleware.** La comprobación de sesión vive en `Controller::requiereLogin()`, no en el router.
- **No valida los parámetros de negocio.** `?id=abc` llega al controlador como `'abc'`, y es el controlador quien hace `(int)` y comprueba `> 0`.
- **No sabe qué acciones son públicas.** La lista blanca `RUTAS` dice qué acciones *existen*, no cuáles exigen sesión. Esa segunda decisión sigue en cada controlador, llamando a `requiereLogin()` al principio.

## 7. Posibles mejoras (para cuando quieras ir más allá)

Estas son ideas para evolucionar el router. El punto 1 ya se implementó en la auditoría; los otros dos **no** están en el proyecto y son solo sugerencias:

```php
// 1. ✅ Ya implementado: 404 con página propia en vez de die()
//    (método error() más arriba). Antes se usaba die() con el nombre del
//    archivo, que filtraba rutas y no daba una página decente.

// 2. Lista blanca de acciones públicas, para no olvidarse de requiereLogin()
// (protección contra olvidar la comprobación en un método nuevo)
private array $accionesPublicas = ['login', 'index', 'register', 'guardarRegistro', 'logout'];

// 3. URLs limpias en vez de query string:  /partidos/editar/7
// Requiere .htaccess con mod_rewrite, que en XAMPP ya está disponible.
```

## 8. Errores que Cometí y cómo evitarlos

| Error | Síntoma | Solución |
|---|---|---|
| Olvidar `Controller` en el nombre de archivo | "No se encontró el controlador" | Los archivos se llaman `XController.php` |
| Nombre de clase distinto al del archivo | "No existe la clase" | `class_exists()` te avisa |
| No normalizar con `ucfirst` | Falla si el usuario escribe minúsculas | `ucfirst(strtolower(...))` |
| Olvidar `unset` de `controller`/`action` en `$params` | `editar()` recibe parámetros de más | Los dos `unset()` |
| Declarar `editar()` sin `array $params` | Error fatal al acceder a `$params['id']` | Siempre `array $params` |
| Olvidar `exit` en el controlador | Se imprime HTML tras el `header()` | `exit` siempre |

---

**Archivos relacionados:** `Controller-explicado.md` · `../../../public/index.php` · `../../02-Fundamentos/05-Routing.md`

**Siguiente paso →** `../Controllers/PartidosController-explicado.md` (un controlador completo, con sus validaciones).