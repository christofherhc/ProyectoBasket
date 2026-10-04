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
    $rutaControlador = '../app/controllers/' . $nombreClase . '.php';

    if (!file_exists($rutaControlador)) {
        die("Error: No se encontró el controlador: " . $nombreClase);
    }

    require_once $rutaControlador;

    if (!class_exists($nombreClase)) {
        die("Error: No existe la clase: " . $nombreClase);
    }

    $this->controllerObjeto = new $nombreClase();
}
```

**Las dos comprobaciones no son redundantes.** Son dos fallos distintos:
- `file_exists` falla → no hay archivo PHP en esa ruta.
- `class_exists` falla → el archivo existe pero dentro no hay ninguna clase con ese nombre.

Sin el segundo `if`, si el archivo se llamara `PartidosController.php` pero la clase fuera `PartidoController`, `new $nombreClase()` daría un error críptico de PHP en vez de un mensaje claro.

**`new $nombreClase()` con variable.** PHP permite instanciar una clase cuyo nombre está en una variable. Aquí `$nombreClase` es `"PartidosController"`, así que es como si pusieras `new PartidosController()`. Esto es lo que permite el enrutado dinámico.

**⚠️ `die()` con un mensaje del `Router` sin escapar.** El nombre viene de `$_GET`, y aquí se imprime directamente en la respuesta HTML. En este proyecto está controlado porque solo se alcanza con rutas inválidas, pero es una práctica que conviene corregir en cuanto sepas hacerlo:

```php
die("Error: Controlador no encontrado: " . htmlspecialchars($nombreClase));
```

**Lección:** cuando imprimas algo que venga del usuario, escápalo. También en los mensajes de error.

### `executeAction()`: llamar al método

```php
private function executeAction(): void {
    if ($this->controllerObjeto === null) {
        die("Error: Controlador no instanciado");
    }

    if (!method_exists($this->controllerObjeto, $this->actionNombre)) {
        die("Error: El método '{$this->actionNombre}' no existe en " . get_class($this->controllerObjeto));
    }

    call_user_func_array(
        [$this->controllerObjeto, $this->actionNombre],
        [$this->params]
    );
}
```

**`method_exists()` antes de llamar.** Es la comprobación que evita el error fatal "Call to undefined method". Es la versión de *función* del `file_exists` de antes: primero compruebas que existe, luego llamas.

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

## 5. Qué NO hace este router

- **No usa `$_SERVER['PATH_INFO']`** ni reescritura de URLs. Todo va por query string. Para un proyecto escolar es más que suficiente.
- **No hay middleware.** La comprobación de sesión vive en `Controller::requiereLogin()`, no en el router.
- **No valida los parámetros.** `?id=abc` llega al controlador como `'abc'`, y es el controlador quien hace `(int)` y comprueba `> 0`.
- **No hay 404.** Si pides una acción inexistente, muestra `die("Error: El método 'xyz' no existe...")`. Es feo, pero es honesto y fácil de depurar.

## 6. Posibles mejoras (para cuando quieras ir más allá)

Esto **no está implementado** en el proyecto; es solo por si algún día quieres evolucionarlo:

```php
// 1. 404 y 500 con página propia, en vez de die()
if (!method_exists($this->controllerObjeto, $this->actionNombre)) {
    http_response_code(404);
    $this->controllerObjeto->noEncontrado('Acción no disponible');
    return;
}

// 2. Lista blanca de acciones públicas, para no olvidarse de requiereLogin()
// (protección contra olvidar la comprobación en un método nuevo)
private array $accionesPublicas = ['login', 'index', 'register', 'guardarRegistro', 'logout'];

// 3. URLs limpias en vez de query string:  /partidos/editar/7
// Requiere .htaccess con mod_rewrite, que en XAMPP ya está disponible.
```

## 7. Errores que Cometí y cómo evitarlos

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