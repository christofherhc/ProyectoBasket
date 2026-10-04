# Día 02 - Core MVC + Front Controller

**Fecha:** 2026-10-03
**Rama:** `main`
**Objetivo:** Crear la configuración, las 4 clases base, el punto de entrada, el layout y la autenticación mínima.
**Temas a comprender:** Front Controller, patrón Singleton, enrutado por `$_GET`, sesiones, y el orden en que PHP carga los archivos.

## 1. Contexto

El día 01 dejó la estructura de carpetas vacía. Hoy se rellena **todo el esqueleto**: lo mínimo para que un navegador entre por una URL y reciba HTML.

**La pregunta del día:** ¿cómo conviertes `/index.php?controller=Equipos&action=index` en "carga la clase `EquiposController` y llama a su método `index()`"?

## 2. Preparación

- Estructura de carpetas del día 01.
- Decidir dos cosas: dónde va la configuración (dentro de `app/`, fuera de `public/`) y cómo se mapea una URL a un controlador.

## 3. Pasos realizados

### Paso 2.1: `app/config/config.php`

```php
<?php
// Configuración de Base de Datos (MySQL/MariaDB - XAMPP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'liga_basquet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL base de la aplicación (detectada automáticamente)
if (PHP_SAPI === 'cli') {
    define('BASE_URL', 'http://localhost/ProyectoBasket/public/');
} else {
    $esHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $esquema  = $esHttps ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $carpeta  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $esquema . '://' . $host . $carpeta . '/');
}

// Zona horaria para fechas
date_default_timezone_set('America/Lima');
```

⚠️ **`BASE_URL` es una URL COMPLETA, no una ruta.** Se construye con tres partes: esquema (`http` o `https`) + host + carpeta. Todo el proyecto la usa igual:

```php
header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
```
```php
<a href="<?= BASE_URL ?>css/styles.css">
```

⚠️ **Se calcula, no se escribe a mano.** `dirname($_SERVER['SCRIPT_NAME'])` da la carpeta desde la que se sirvió el script: `/ProyectoBasket/public`. Si mañana la app se mueve a la raíz del servidor o a otra carpeta, el mismo código sigue funcionando. Escribir `define('BASE_URL', 'http://localhost/ProyectoBasket/public/')` a mano funcionaría **hasta el día que se mueve la carpeta**, y entonces los 30 `header('Location:')` del proyecto darían error a la vez.

⚠️ **El caso `PHP_SAPI === 'cli'`.** Cuando el script se ejecuta por consola (o con el servidor embebido de PHP), `$_SERVER['HTTP_HOST']` no existe. El `if` da un valor por defecto en lugar de producir un error. Es una guarda pequeña que evita que la app se rompa al usarla desde la terminal.

⚠️ **La detección de HTTPS.** `$_SERVER['HTTPS']` no es `false` sino `'off'` cuando hay HTTPS pero el servidor lo publica así. Por eso el código comprueba **las dos** cosas: que exista y que no sea `'off'`.

⚠️ **`date_default_timezone_set('America/Lima')`.** Sin esto, PHP usa la zona horaria del servidor. Con PHP 8 el aviso es explícito, y las fechas de los partidos se mostrarían desplazadas. **Esta línea va en la configuración, no en cada vista que imprima una fecha.**

⚠️ **`DB_CHARSET = 'utf8mb4'`, no `utf8`.** `utf8` en MySQL son 3 bytes y no soporta emojis ni algunos caracteres. `utf8mb4` son 4 bytes y es el estándar real. El síntoma del error es ver `PÃ-vot` en vez de `Pívot`.

### Paso 2.2: `app/core/Database.php` — Singleton

```php
class Database {
    private static ?PDO $instancia = null;

    private function __construct() {}

    public static function getConexion(): PDO {
        if (self::$instancia === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ]);
        }
        return self::$instancia;
    }
}
```

**Las tres puertas del Singleton:**
| Puerta | Cómo se cierra |
|---|---|
| `new Database()` | Constructor **privado** |
| `clone $db` | `__clone()` **privado** |
| `unserialize()` | `__wakeup()` **público** que lanza excepción |

⚠️ **El nombre del método es `getConexion` (con "x") y no `getConnection`.** Es una convención del proyecto, no un error. Si un día importas código de otra persona que usa `getConnection`, llama a `Database::getConexion()` y te vas a acordar.

⚠️ **`self::$instancia` es `static`**, así que se llama con `Database::` y no con `$this->`. Y como es Singleton, **los 4 modelos de una misma petición comparten la misma conexión**, aunque se instancien cuatro veces.

### Paso 2.3: `app/core/Model.php` — CRUD genérico

```php
class Model {
    protected PDO $db;
    protected string $tabla;

    public function __construct() {
        $this->db = Database::getConexion();
    }

    public function obtenerTodos(): array {
        $sql = "SELECT * FROM {$this->tabla}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array { ... }
    public function eliminar(int $id): bool { ... }
    public function obtenerUltimoId(): string { return $this->db->lastInsertId(); }
}
```

⚠️ **`$tabla` es `protected`, no `private`.** Es la pieza clave de la herencia: `Equipo extends Model` asigna `protected $tabla = 'equipos'`, y los métodos del padre la ven. Si fuera `private`, los métodos del padre verían `$this->tabla` **vacía**, y el error sería `Base table or view not found: ''`.

⚠️ **`{$this->tabla}` con llaves.** Sin las llaves, PHP interpretaría `$tabla` como una constante. Interpolación de propiedades **exige** las llaves.

⚠️ **`obtenerPorId()` devuelve `null`, no `false`.**
```php
$resultado = $stmt->fetch();          // false si no hay filas
return $resultado === false ? null : $resultado;
```
`fetch()` devuelve `false` cuando no encuentra nada. Traducirlo a `null` en el modelo evita que cada vista tenga que comprobar `=== false`.

### Paso 2.4: `app/core/Controller.php` — vista + protección

```php
class Controller {
    protected function view(string $vista, array $datos = []): void {
        $ruta = __DIR__ . '/../views/' . $vista . '.php';
        if (!file_exists($ruta)) { die('Vista no encontrada'); }
        extract($datos);
        require $ruta;
    }

    protected function requiereLogin(): void {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . BASE_URL . '?controller=Auth&action=login');
            exit;
        }
    }
}
```

⚠️ **`extract($datos)` convierte el array en variables.** Si pasas `['titulo' => 'Equipos']`, dentro de la vista existe `$titulo`. Es cómodo para aprender, pero tiene un riesgo: si el array contiene una clave llamada `ruta` o `datos`, **pisa** la variable local. Por eso el proyecto pasa siempre arrays associative con nombres propios.

⚠️ **`__DIR__` en vez de una ruta escrita a mano.** `__DIR__` es la carpeta del archivo actual, así que `__DIR__ . '/../views/'` funciona sin importar desde dónde se llame al método. Las rutas relativas dependen del archivo que las ejecuta, y eso se rompe en cuanto un `require` se mueve de sitio.

⚠️ **La protección va en el controlador, no en el Router.** `Router` no sabe qué acciones son públicas (login, registro) y cuáles privadas. Si la metiera en el Router, tendría que mantener una lista de excepciones, y cada acción nueva que se olvide de añadir a esa lista quedaría desprotegida.

### Paso 2.5: `app/core/Router.php`

```php
class Router {
    private string $controllerNombre = 'Auth';
    private string $actionNombre = 'index';
    private array $params = [];
    private ?object $controllerObjeto = null;

    public function __construct() {
        $this->parseUrl();
        $this->loadController();
        $this->executeAction();
    }

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
}
```

⚠️ **Todo el trabajo ocurre en el constructor.** En `index.php` no hay ni una línea más: `new Router();`. Lo habitual sería `new Router(); $router->run();`, pero para un front controller de este tamaño es práctico.

⚠️ **`ucfirst(strtolower($controller))` resuelve el problema de las mayúsculas.** El usuario puede escribir `?controller=PARTIDOS`, `partidos` o `Partidos`, y siempre se busca `PartidosController.php`. Sin esa normalización, en Linux (donde los nombres de archivo distinguen mayúsculas) el archivo no se encontraría.

⚠️ **El sufijo `Controller` se añade en `loadController()`, no en `parseUrl()`.** `parseUrl()` solo normaliza; `loadController()` construye el nombre de clase completo. Son dos responsabilidades separadas.

⚠️ **`$this->params = $_GET` seguido de los dos `unset()`.** Así los parámetros sobrantes (`id`, etc.) se separan de los dos nombres de ruta, y `editar()` recibe solo lo que le interesa:

```php
// URL: ?controller=Partidos&action=editar&id=7
$this->params = ['id' => '7'];   // sin 'controller' ni 'action'
```

⚠️ **`call_user_func_array()` es lo que hace que unas acciones reciban parámetros y otras no.** Todas las acciones se invocan con `[$this->params]`, así que `editar(array $params)` lo recibe y `index()` lo ignora. **PHP permite que un método definido por el usuario reciba argumentos de más**: no da error y simplemente no los usa. (Con funciones internas de PHP sí daría error, de ahí la precaución.)

⚠️ **Las tres comprobaciones de `die()`** (controlador no encontrado, clase no encontrada, método inexistente) son lo que evita la pantalla en blanco. Sin ellas, un error de nombre en la URL produciría un "Class not found" de PHP en crudo.

⚠️ **`die()` sin `htmlspecialchars()` en el nombre.** `$this->actionNombre` viene de `$_GET` y se imprime en el mensaje de error. Con `?action=<script>alert(1)</script>` eso sería XSS reflejado. Está anotado como limitación pendiente del proyecto.

### Paso 2.6: `public/index.php` — el front controller

```php
<?php

// Iniciar sesión (debe ir antes de cualquier salida)
session_start();

// Cargar configuración
require_once __DIR__ . '/../app/config/config.php';

// Cargar clases core
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Router.php';

// Inicializar enrutador (maneja toda la petición)
new Router();
```

⚠️ **`session_start()` va PRIMERO, antes de cualquier salida.** Si el código lee o escribe `$_SESSION` antes de `session_start()`, la sesión no existe y el dato se pierde. Además, si se imprime algo (un `echo`, un espacio antes de `<?php`), PHP avisa con *"headers already sent"* y las redirecciones dejan de funcionar.

⚠️ **Los `require_once` son explícitos, no hay autoloader.** Es una decisión consciente para un proyecto de 22 archivos PHP: la lista se lee de un vistazo y no hay misterio. En un proyecto con cientos de clases se usaría `spl_autoload_register()`; aquí solo añadiría líneas.

⚠️ **`__DIR__` en los `require`.** `__DIR__` es la ruta absoluta del archivo que lo contiene, así que el `require` funciona **desde cualquier carpeta**. Con una ruta relativa (`require 'app/core/Router.php'`) el día que la app se ejecute desde otro directorio, todos los includes fallan.

⚠️ **Los modelos NO se cargan aquí.** Cada controlador carga los suyos en su constructor:
```php
public function __construct() {
    require_once '../app/models/Equipo.php';
    $this->equipoModel = new Equipo();
}
```
⚠️ **Fíjate en que esa ruta es RELATIVA** (`'../app/models/...'`), no con `__DIR__`. Funciona porque el directorio de trabajo de PHP es siempre `public/` (donde está `index.php`). Es frágil: si mañana se añade un script CLI en otra carpeta, los modelos no se encontrarán. Está anotado como fragilidad conocida.

⚠️ **El orden de los `require_once` importa.** `Model.php` usa `Database::getConexion()` en su constructor, y `Router` instancia controladores que heredan de `Controller`. Si `Controller.php` se cargara después de `Router.php`, el `class XController extends Controller` fallaría con "Class not found".

### Paso 2.7: Autenticación mínima

`AuthController` con **cinco** acciones, más `app/models/Usuario.php`.

| Acción | Qué hace |
|---|---|
| `index()` | Muestra el formulario de login |
| `login()` | Procesa el login (POST) |
| `register()` | Muestra el formulario de registro |
| `guardarRegistro()` | Procesa el registro (POST) |
| `logout()` | Cierra la sesión |

⚠️ **Los nombres vienen en pares: "mostrar el formulario" y "procesar los datos".** `index()` muestra, `login()` procesa. Es la convención que se mantiene en los 4 CRUD (`crear`/`guardar`, `editar`/`actualizar`).

⚠️ **`index()` y `register()` redirigen si ya hay sesión.** Si un usuario autenticado entra a `/login`, ve el formulario otra vez, lo cual es confuso. La comprobación es `isset($_SESSION['usuario_id'])`.

⚠️ **`login()` normaliza el email:** `trim(strtolower($_POST['email']))`. Así `  Ana@Liga.COM ` y `ana@liga.com` son **el mismo usuario**, y no se pueden crear dos cuentas que se diferencien solo en mayúsculas.

⚠️ **Todas las acciones que procesan datos comprueban el método HTTP:**
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```
⚠️ **Esto es lo que hace que la batería de pruebas del día 08 encontrara un fallo real.** Una prueba que pedía `action=guardar` por GET veía un 302, no un error. **La aplicación estaba bien; el test estaba mal.** Aun así, la comprobación es buena: si alguien teclea la URL de `guardar()` en el navegador, se redirige al formulario en vez de intentar insertar con datos vacíos.

**El login, en 4 pasos:**
```php
$usuario = $usuarioModel->buscarPorEmail($email);

if ($usuario === null || !password_verify($password, $usuario['password'])) {
    $_SESSION['error'] = 'Credenciales incorrectas';
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['nombre'] = $usuario['nombre'];
$_SESSION['email'] = $usuario['email'];
$_SESSION['logueado'] = true;
```

⚠️ **`session_regenerate_id(true)` tras el login es obligatorio.** Sin él, el identificador de sesión **no cambia** al autenticarse. Si alguien consigue un `PHPSESSID` de otra persona (por ejemplo, por un enlace), podría reutilizar su sesión ya autenticada. El `true` además borra la sesión antigua.

⚠️ **Un solo mensaje de error para "email inexistente" y "contraseña incorrecta".** Deliberado: si fueran distintos, el formulario permitiría enumerar qué emails están registrados. Con un mensaje único, no se puede saber.

⚠️ **`$_SESSION['logueado']` es lo que usa el layout** para decidir si muestra el menú de módulos o el de "Iniciar Sesión / Registrarse". Y `requiereLogin()` usa `usuario_id`. Son dos banderas para lo mismo, pero cada una tiene su consumidor.

⚠️ **`password_hash()` genera el hash con su propia sal.** Dos usuarios con la contraseña `1234` producen hashes **distintos**. Por eso el login no puede comparar contraseñas: tiene que usar `password_verify($input, $hashAlmacenado)`, que sabe qué sal se usó.

**El registro valida 5 cosas, en este orden:**
```php
if (empty($nombre) || empty($email) || empty($password) || empty($confirmar))  → 'Todos los campos son obligatorios'
if (!filter_var($email, FILTER_VALIDATE_EMAIL))                               → 'Email no válido'
if ($password !== $confirmar)                                                  → 'Las contraseñas no coinciden'
if (strlen($password) < 6)                                                     → 'mínimo 6 caracteres'
if ($usuarioModel->existeEmail($email))                                         → 'El email ya está registrado'
```
⚠️ **`filter_var($email, FILTER_VALIDATE_EMAIL)`** es la validación de email en PHP. Devuelve el email si es válido y `false` si no, así que se usa con `!`. **No es una prueba de que el email exista**: solo de que tiene forma de email.

⚠️ **`$_SESSION['old']` guarda nombre y email para el formulario** (nunca la contraseña), y se hace `unset()` al terminar bien. El patrón se repite en los 4 módulos con claves distintas: `old`, `old_equipo`, `old_jugador`, `old_partido`.

⚠️ **`logout()` hace el cierre completo, y ese detalle se olvida a menudo:**
```php
$_SESSION = [];                              // vacía los datos
if (ini_get("session.use_cookies")) {         // borra la cookie
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();                           // destruye el archivo de sesión
header('Location: ' . BASE_URL . '?controller=Auth&action=index');
```
Con solo `$_SESSION = []`, la cookie `PHPSESSID` sigue viva y el archivo de sesión en el servidor no se borra. **Los tres pasos son necesarios.**

## 4. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/config/config.php` | crear | Constantes de BD y `BASE_URL` calculado |
| `app/core/Database.php` | crear | Singleton PDO con las 4 opciones de configuración |
| `app/core/Model.php` | crear | CRUD genérico (`obtenerTodos`, `obtenerPorId`, `eliminar`, `obtenerUltimoId`) |
| `app/core/Controller.php` | crear | `view()` con `extract()` y `requiereLogin()` |
| `app/core/Router.php` | crear | Mapeo `?controller=X&action=Y` a clase y método |
| `public/index.php` | crear | Front controller: sesión, config, `require_once` del core, `new Router()` |
| `public/css/styles.css` | crear | Estilos base legibles |
| `app/views/layouts/main.php` | crear | Nav, contenido, mensajes flash |
| `app/controllers/AuthController.php` | crear | `index`, `login`, `register`, `guardarRegistro`, `logout` |
| `app/models/Usuario.php` | crear | `buscarPorEmail`, `existeEmail`, `crear` |
| `app/views/auth/login.php`, `register.php` | crear | Formularios de acceso |

## 5. Explicación técnica: por qué el front controller y no un archivo por vista

**Sin front controller** (PHP clásico): cada página es un archivo público.
```
htdocs/proyecto/login.php     → accesible por el navegador
htdocs/proyecto/registro.php  → accesible por el navegador
htdocs/proyecto/equipos.php   → accesible por el navegador
```

⚠️ **Tres problemas:** (1) cada archivo repite la sesión, los `require` y la configuración; (2) con 20 páginas hay 20 archivos expuestos; (3) la lógica de negocio se mezcla con el HTML.

**Con front controller:**
```
htdocs/proyecto/public/index.php   → el ÚNICO archivo accesible
```
El navegador pide `index.php?controller=Equipos&action=index`. El Router decide qué controlador y qué método ejecutar. **`public/` queda con un solo archivo**, y todo lo demás está fuera del alcance del servidor web.

⚠️ **El coste:** las URLs quedan como `?controller=Equipos&action=index` en lugar de `/equipos`. Se puede ocultar con `.htaccess` (URLs limpias), pero para este proyecto la query string es más transparente: **se ve exactamente qué está pasando**, que es lo que hace falta al aprender.

## 6. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| `/public/index.php` carga el login sin errores | ✅ |
| `?controller=Auth&action=index` muestra el formulario de login | ✅ |
| `?controller=Auth&action=register` muestra el registro | ✅ |
| `?controller=NoExiste` avisa sin pantalla en blanco | ✅ |
| `?controller=Auth&action=noExiste` avisa | ✅ |
| Login con datos correctos entra y muestra el nombre | ✅ |
| Login con password incorrecto muestra error | ✅ |
| `$_SESSION['usuario_id']` existe tras login | ✅ |
| El `PHPSESSID` **cambia** al iniciar sesión | ✅ |
| El hash en la base empieza por `$2y$` y mide 60 caracteres | ✅ |
| La contraseña **no** aparece en texto plano en la base | ✅ |
| Los 4 `require_once` del core cargan sin error | ✅ |
| `BASE_URL` se resuelve bien con `htdocs/…/public` | ✅ |
| `BASE_URL` cambia a `https://` si el servidor sirve por HTTPS | ✅ |
| `guardarRegistro()` con GET redirige al formulario (no inserta) | ✅ |
| `logout()` vacía sesión, borra cookie y destruye la sesión | ✅ |

**El orden de los `require` se comprobó:** si `Router` se carga antes que `Controller`, aparece "Class not found" al instanciar.

## 7. Commits realizados

```
feat: añade core MVC, config, front controller y auth base
```

## 8. Aprendizajes del día

1. **El front controller convierte N archivos públicos en 1.** Es una decisión de seguridad tanto como de arquitectura.
2. **Los `require_once` explícitos son legibles cuando hay pocas clases.** Con 22 archivos PHP se lee la lista de un vistazo; con cientos, el autoload gana.
3. **El Singleton no es rendimiento: es un único punto de configuración.** Un solo lugar decide charset, modo de error y emulación.
4. **`$tabla` en el padre tiene que ser `protected`.** Con `private`, los métodos heredados verían la tabla vacía.
5. **`session_start()` va primero, siempre.** Cualquier `$_SESSION` antes de tiempo es un bug silencioso.
6. **`session_regenerate_id(true)` tras el login no es opcional.** Sin él, el robo de sesión es trivial.
7. **`password_hash()` mete su propia sal**, así que las contraseñas nunca se comparan, se verifican.
8. **El valor por defecto del Router (`Auth` / `index`) es una decisión de UX.** Que entrar a `/` muestre el login en vez de un error.
9. **Los datos de envío se validan por tipo de petición.** `guardar()` y `actualizar()` rechazan el GET; `guardarRegistro()` y `login()` hacen lo propio.
10. **`logout()` completo son tres pasos**, no uno: vaciar, borrar cookie y destruir.
11. **Un mismo mensaje de error para credenciales inválidas**, para no filtrar qué emails existen.

## 10. Dudas pendientes

⚠️ **De este día quedaron vivos dos problemas que no se resolverían hasta el día 08:**

| # | Problema | Se resolvió |
|---|---|---|
| 1 | `Router` hace `die()` con datos de `$_GET` sin escapar → XSS reflected | No (anotado como limitación baja) |
| 2 | `Database` muestra `getMessage()` en producción → filtra nombre de BD y rutas | No (anotado; aceptable en local) |

## 11. Próximos pasos

**Día 03 - Equipos:** el primer CRUD completo, para ver el patrón Modelo → Controlador → Vista de extremo a extremo con una entidad real.