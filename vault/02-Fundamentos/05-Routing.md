# 5. Routing (Enrutamiento) - Explicación Amplia

## 1. ¿Qué es el Routing (Enrutamiento)?

El **Routing** (enrutamiento) es el proceso de **convertir una URL (petición del usuario) en la acción que debe ejecutar la aplicación**.

Es decir: cuando el usuario visita una URL, el sistema decide:
- **Qué controlador** debe instanciarse
- **Qué método (acción)** debe ejecutarse
- **Qué parámetros** recibir

En nuestro caso, queremos URLs legibles. Usaremos **enrutamiento simple por parámetros GET** con patrón Front Controller.

## 2. Front Controller - ¿Qué es?

**Front Controller** es un patrón de diseño donde **todas las peticiones pasan por un único punto de entrada**.

En lugar de tener muchos archivos `.php` públicos (`equipos.php`, `jugadores.php`...), **toda la lógica pasa por `public/index.php`**.

**Ventajas:**
- Centraliza control (autenticación, errores, configuración)
- URLs más limpias y consistentes
- Más fácil de mantener y entender
- Arquitectura MVC clara

**Nuestro punto de entrada:** `ProyectoBasket/public/index.php`

## 3. Enrutamiento con Parámetros GET (Opción Elegida)

Usaremos esta estructura:

```text
http://localhost/ProyectoBasket/public/?controller=Equipos&action=index
http://localhost/ProyectoBasket/public/?controller=Jugadores&action=crear
http://localhost/ProyectoBasket/public/?controller=Partidos&action=editar&id=3
http://localhost/ProyectoBasket/public/?controller=Auth&action=logout
```

### Descomposición

| Parámetro | Significado | Ejemplo |
|---|---|---|
| `controller` | Nombre del controlador (sin sufijo Controller) | `Equipos` → `EquiposController.php` |
| `action` | Método/acción a ejecutar | `index`, `crear`, `guardar`, `editar` |
| `id` (opcional) | Parámetro para editar/eliminar/ver detalle | `id=1` |

### ¿Por qué GET params (no URLs amigables con .htaccess)?

Para **aprendizaje**:
- **Transparente y explícito**: Ves claramente qué controlador/acción se ejecuta
- **No requiere mod_rewrite**: Funciona en cualquier Apache/XAMPP sin configurar `.htaccess`
- **Más fácil de entender para principiantes**: No hay "magia" de reescritura
- **Debug más sencillo**

Cumple perfectamente objetivos (sencillo, entendible). Podemos añadir rutas amigables después, pero **no necesario**.

## 4. Valores por Defecto (Fallbacks)

Si usuario no pasa parámetros, definimos seguros:

```php
$controllerNombre = $_GET['controller'] ?? 'Auth';
$actionNombre = $_GET['action'] ?? 'index';
```

**¿Por qué Auth/index por defecto?** Al entrar a la app, lo lógico es mostrar login (o dashboard si logueado). Más adelante controlamos con sesiones.

## 5. Clase Router (`app/core/Router.php`)

Router se encarga de:
1. Leer `controller` y `action` de URL
2. Validar que existen
3. Cargar archivo del controlador
4. Instanciar controlador
5. Llamar al método (acción)

### Implementación explicada paso a paso

```php
<?php

class Router {
    private string $controller = 'Auth';
    private string $action = 'index';
    private array $params = [];

    public function __construct() {
        // 1. Obtener parámetros de URL
        $this->parseUrl();

        // 2. Cargar controlador
        $this->loadController();

        // 3. Ejecutar acción
        $this->executeAction();
    }

    private function parseUrl(): void {
        // Controller
        if (isset($_GET['controller']) && !empty($_GET['controller'])) {
            $this->controller = ucfirst(strtolower($_GET['controller']));
        }

        // Action
        if (isset($_GET['action']) && !empty($_GET['action'])) {
            $this->action = strtolower($_GET['action']);
        }

        // Parámetros extra (id, etc.)
        $this->params = $_GET;
        // Eliminamos controller y action para dejar solo params útiles
        unset($this->params['controller'], $this->params['action']);
    }

    private function loadController(): void {
        $nombreClase = $this->controller . 'Controller';
        $rutaControlador = '../app/controllers/' . $nombreClase . '.php';

        if (file_exists($rutaControlador)) {
            require_once $rutaControlador;
            // Verificar que clase existe
            if (class_exists($nombreClase)) {
                // Instanciamos
                $this->controllerObjeto = new $nombreClase();
            } else {
                // Clase no definida
                die("Error: No existe la clase $nombreClase en $rutaControlador");
            }
        } else {
            // Archivo no existe
            die("Error: No se encontró el controlador $rutaControlador");
        }
    }

    private function executeAction(): void {
        if (method_exists($this->controllerObjeto, $this->action)) {
            // Llamamos método con parámetros
            call_user_func_array([$this->controllerObjeto, $this->action], [$this->params]);
        } else {
            die("Error: El método '{$this->action}' no existe en el controlador {$this->controller}Controller");
        }
    }
}
```

### Explicación de cada parte

#### 1. `parseUrl()` - Parsear URL

```php
$this->controller = ucfirst(strtolower($_GET['controller']));
```
- `strtolower()` → Normaliza (Equipos/equipos/EQUIPOS → 'equipos')
- `ucfirst()` → Primera mayúscula ('equipos' → 'Equipos') → Coincide con nombre clase

```php
$this->action = strtolower($_GET['action']);
```
Acciones en minúsculas (convención: métodos camelCase pero URL lowercase).

```php
unset($this->params['controller'], $this->params['action']);
```
Limpiamos para pasar solo parámetros extra (`id`, filtros...) al método.

#### 2. `loadController()` - Cargar e instanciar

```php
$nombreClase = $this->controller . 'Controller'; // 'Equipos' + 'Controller' = 'EquiposController'
$rutaControlador = '../app/controllers/' . $nombreClase . '.php';
```

**Nota ruta:** Desde `public/index.php` subimos un nivel (`../`) para llegar a `app/`.

```php
if (class_exists($nombreClase)) {
    $this->controllerObjeto = new $nombreClase();
}
```
Instanciación dinámica (PHP permite new con string). Útil y claro aquí.

#### 3. `executeAction()` - Ejecutar método

```php
if (method_exists($this->controllerObjeto, $this->action)) {
    call_user_func_array([$this->controllerObjeto, $this->action], [$this->params]);
}
```

**`call_user_func_array()`**: Llama al método pasando array de parámetros. Así el método recibe `$params` (array con `id` u otros).

**Alternativa más simple (sin params array):**
```php
$this->controllerObjeto->{$this->action}();
```
Pero para pasar `id` necesitamos array. Con `call_user_func_array` pasamos todos params limpios.

Otra forma: pasar `$id` directo. Pero array flexible para futuro.

## 6. Front Controller - `public/index.php`

Punto de entrada único. Orden correcto:

```php
<?php

// 1. Iniciar sesión SIEMPRE antes de cualquier salida
session_start();

// 2. Cargar configuración
require_once __DIR__ . '/../app/config/config.php';

// 3. Cargar clases core (autocarga opcional, pero require_once claro)
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Router.php';

// 4. Inicializar Router (maneja toda la petición)
new Router();
```

### Explicación orden

1. **`session_start()` primero** → Necesario para auth, mensajes flash. Debe ir antes de `echo`/HTML.
2. **Config** → Define constantes (DB_*, BASE_URL) usadas por core/clases.
3. **Core** → Cargamos en orden: Database → Model (depende DB conceptualmente) → Controller → Router.
4. **Router** → Dispara flujo MVC completo.

`__DIR__` garantiza rutas absolutas relativas al archivo (robusto).

## 7. Mapear URLs a Controladores/Acciones

| URL (GET) | Controller | Action | Descripción |
|---|---|---|---|
| `?controller=Auth&action=index` | AuthController | index() | Login (por defecto) |
| `?controller=Auth&action=login` | AuthController | login() | Procesar login (POST) |
| `?controller=Auth&action=register` | AuthController | register() | Formulario registro |
| `?controller=Auth&action=guardarRegistro` | AuthController | guardarRegistro() | Procesar registro |
| `?controller=Auth&action=logout` | AuthController | logout() | Cerrar sesión |
| `?controller=Equipos&action=index` | EquiposController | index() | Listar equipos |
| `?controller=Equipos&action=crear` | EquiposController | crear() | Form crear |
| `?controller=Equipos&action=guardar` | EquiposController | guardar() | Procesar crear (POST) |
| `?controller=Equipos&action=editar&id=1` | EquiposController | editar($params) | Form editar |
| `?controller=Equipos&action=actualizar` | EquiposController | actualizar() | Procesar editar (POST) |
| `?controller=Equipos&action=eliminar&id=1` | EquiposController | eliminar($params) | Eliminar |
| `?controller=Jugadores&action=index` | JugadoresController | index() | Listar |
| `...` | ... | ... | Patrón coherente |
| `?controller=Estadisticas&action=index` | EstadisticasController | index() | Estadísticas |

## 8. Cómo Recibe Parámetros el Controlador

Router pasa `$params` (array). Método los recibe:

```php
<?php
class EquiposController extends Controller {
    public function editar(array $params): void {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        // o
        $id = (int)($params['id'] ?? 0);

        $equipo = (new Equipo())->obtenerPorId($id);
        if (!$equipo) {
            // Redirigir o mostrar error
        }
        $this->view('equipos/editar', ['equipo' => $equipo]);
    }

    public function eliminar(array $params): void {
        $id = (int)($params['id'] ?? 0);
        (new Equipo())->eliminar($id);
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }
}
```

**Cast a (int)** para seguridad (validar ID numérico).

## 9. Validación de Rutas (Seguridad Básica)

Router actual hace `die()` con errores. En desarrollo OK (explicativo). En producción ideal manejar 404.

Para aprendizaje, mensajes `die()` son **claros** (ves qué falla: controlador o método no existe).

Podemos mejorar después con manejo de errores, pero no necesario para práctica.

## 10. Caso Práctico: Formulario POST

Formularios usan `method="POST"`. Acción POST va a controlador:

```html
<form action="<?= BASE_URL ?>?controller=Equipos&action=guardar" method="POST">
    <input name="nombre" required>
    <input name="ciudad" required>
    <button>Guardar</button>
</form>
```

Controlador lee `$_POST`:
```php
public function guardar(): void {
    $nombre = trim($_POST['nombre'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    // validar...
    (new Equipo())->crear($nombre, $ciudad);
    // redirigir
}
```

**Nota:** Acción guardar/actualizar suelen recibir POST (no params URL).

## 11. Redirecciones

Tras guardar/eliminar, conviene **redireccionar** (PRG: Post-Redirect-Get). Evita reenvío duplicado al refrescar.

```php
header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
exit; // Siempre exit tras header
```

Usamos `BASE_URL` (de config.php) para URLs consistentes.

## 12. Ventajas de Este Routing

| Ventaja | Explicación |
|---|---|
| **Sencillo** | Fácil de entender, seguir y explicar |
| **Explícito** | URL muestra claramente intención |
| **Sin configuración extra** | Funciona out-of-box en XAMPP |
| **Perfecto para aprender MVC** | Ves mapeo URL→Controller→Action claramente |
| **Debuggeable** | Errores claros si controlador/método no existe |

## 13. Alternativa (URLs Amigables) - Concepto

Con `.htaccess` en `public/` podríamos tener:
```text
/equipos/index
/equipos/crear
/equipos/editar/1
```

Requiere `mod_rewrite`. Para este proyecto **NO necesario**. La versión GET params cumple 100% requisitos y es **excelente para aprendizaje**.

## 14. Resumen

- **Front Controller**: `public/index.php` único punto de entrada.
- **Router**: Traduce `?controller=X&action=Y&id=Z` → Instancia XController → Ejecuta Y($params)
- **Flujo completo**: URL → index.php → Router → Controller → Model → View → HTML
- **Seguridad básica**: Validación con `isset/empty`, cast (int), strtolower/ucfirst.
- **Claro y didáctico**: Expone arquitectura MVC sin ocultarla.

Este routing es **sencillo, entendible y pedagógicamente ideal** para tu objetivo de aprendizaje.