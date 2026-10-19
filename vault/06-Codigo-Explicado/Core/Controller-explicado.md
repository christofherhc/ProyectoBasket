# Controller (explicado)

**Archivo real:** `app/core/Controller.php`
**Rol en la arquitectura:** clase base de la que heredan los 5 controladores de la aplicación.

## 1. Qué problema resuelve

Un controlador se ocupa de una cosa: **traducir una petición HTTP en una respuesta**. Hay tres pasos que se repiten en todas las páginas:

1. Comprobar que el usuario está autenticado.
2. Cargar los datos de la base.
3. Mostrar una página.

Los tres son idénticos en los 5 controladores. Si los escribieras en cada uno, tendrías 5 copias que mantener. Esta clase los escribe **una vez**.

## 2. El código completo

```php
class Controller {

    protected function view(string $vista, array $datos = []): void {
        if (!empty($datos)) {
            extract($datos);
        }
        require_once '../app/views/layouts/main.php';
    }

    protected function requiereLogin(): void {
        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['error'] = 'Debes iniciar sesión para acceder a esta página';
            header('Location: ' . BASE_URL . '?controller=Auth&action=index');
            exit;
        }
    }
}
```

## 3. Cómo se usa: la herencia

Que los controladores **hereden** de esta clase es lo que hace posible el código corto del proyecto. En `PartidosController.php`:

```php
class PartidosController extends Controller {
    public function index(): void {
        $this->requiereLogin();                       // heredado
        $partidos = $this->partidoModel->obtenerTodos();
        $this->view('partidos/index', [               // heredado
            'titulo' => 'Listado de Partidos',
            'partidos' => $partidos
        ]);
    }
}
```

`$this->requiereLogin()` y `$this->view()` **no están escritos** en `PartidosController`. Vienen heredados. Por eso los controladores reales son cortos.

### `protected`: por qué no `public`

`protected` significa "accesible desde esta clase y sus hijas, pero no desde fuera". Es exactamente la visibilidad que quieres: los controladores (hijas) pueden usarlos; el `Router` u otra clase externa no deberían.

Si fueran `public`, cualquiera podría llamar `$controlador->view(...)` desde fuera. No es peligroso aquí, pero `protected` documenta la intención: estos métodos son internos de la jerarquía.

## 4. `view()` y la ayuda `extract()`

### Qué hace

Carga el layout principal y, dentro de él, la vista concreta.

### El truco de `extract()`

`extract(['partidos' => $partidos, 'titulo' => 'Listado'])` crea variables PHP a partir de las claves del array. A partir de ese momento, dentro del método existe `$partidos` y `$titulo`.

Esto permite que en las vistas escribas directamente:

```php
<?php foreach ($partidos as $partido): ?>
    <td><?= htmlspecialchars($partido['nombre']) ?></td>
<?php endforeach; ?>
```

en lugar de `<?php foreach ($datos['partidos'] as $partido): ?>`. Es más corto y más legible, y por eso casi todas las vistas de frameworks usan `extract()` (o su equivalente).

### ⚠️ Por qué `extract()` es peligroso

`extract()` crea variables **con el nombre que tú quieras**. Si un array tiene una clave llamada `db`, `vista` o incluso `this`, romperías el método. Es un vector de colisión de nombres reconocido.

**Cómo se mitiga en este proyecto:**
- Las claves las escribe el programador en el controlador (`'titulo' => ...`), no vienen del usuario.
- Nunca se hace `extract($_GET)` ni `extract($_POST)`. Los datos del usuario se usan siempre como `$params['id']`, nunca se convierten en variables.

**Alternativa más segura** si algún día el proyecto crece:

```php
// En vez de extract(), pasar el array y usarlo tal cual en la vista
$this->view('partidos/index', $datos);   // y en la vista: $datos['partidos']
```

Es un poco más verboso pero imposible de romper. **Regla práctica:** `extract()` está bien con arrays literales que tú controlas; nunca con entrada del usuario.

### `require_once` y por qué el layout, no la vista directamente

Fíjate en que se carga `layouts/main.php`, **no** `partidos/index.php`. El layout es el que carga la vista, y además comprueba que exista:

```php
<?php
// dentro de layouts/main.php, dentro de <main>
$vistaArchivo = '../app/views/' . $vista . '.php';
if (file_exists($vistaArchivo)) {
    require_once $vistaArchivo;
} else {
    echo '<h2>Error: Vista no encontrada</h2>';
    echo '<p>' . htmlspecialchars($vistaArchivo) . '</p>';
}
?>
```

⚠️ **La variable `$vista` llega aquí por `extract()`.** `Controller::view('partidos/index', [...])` no carga `partidos/index.php`: extrae los datos, carga el layout, y el layout usa `$vista` para saber qué vista concreta.include. Por eso `$vista` tiene que viajar en el array de datos... o ser un parámetro del método, que es lo que hace:

```php
$this->view('partidos/index', ['titulo' => '...', 'partidos' => $partidos]);
//                     ↑ este string es el $vista que usa el layout
```

⚠️ **`htmlspecialchars($vistaArchivo)` en el mensaje de error**, porque `$vista` viene del controlador y, en teoría, podría venir de fuera. En este proyecto los nombres de vista están escritos a mano en el código, así que el riesgo es bajo, pero el escapado no cuesta nada.

Así el HTML (menú, estilos, pie) se escribe **una vez** y todas las páginas lo reutilizan. Es el patrón de plantilla (*layout* o *master page*).

`require_once` en vez de `require`: si el layout ya se cargó en esta petición, PHP lo ignora. Sin el `_once`, la segunda llamada lo cargaría otra vez y tendrías el menú duplicado.

⚠️ **Aquí se cambió a `require`.** Con `require_once`, una segunda llamada a `view()` en la misma petición **no renderizaría nada**: PHP vería que el layout ya está cargado y se lo saltaría, con lo que la pantalla saldría vacía. Ahora cada llamada a `view()` renderiza, que es lo que se espera de un método que "dibuja una vista".

⚠️ **Lo que sí debe seguir siendo `require_once` es la vista concreta**, dentro del layout: ahí el objetivo es que la vista no se cargue dos veces, y la comprobación `is_file()` protege de que la ruta exista.

### Nota sobre la ruta

El código usa `require_once '../app/views/layouts/main.php'` (ruta relativa). Funciona porque **todos** los archivos se incluyen desde `public/index.php`, así que el directorio de trabajo siempre es `public/`. La alternativa robusta es anclar la ruta con `__DIR__`:

```php
require_once __DIR__ . '/../views/layouts/main.php';
```

Esta segunda forma es la recomendada en general, porque no depende del directorio desde el que se ejecute. Se documenta aquí porque es la práctica correcta y evita sorpresas si mañana se añade un subdirectorio.

## 5. `requiereLogin()`: la barrera de las páginas privadas

### Qué hace

```php
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['error'] = 'Debes iniciar sesión para acceder a esta página';
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```

Cuatro pasos, y **los cuatro importan**:

1. **`isset($_SESSION['usuario_id'])`** comprueba si existe la clave. `isset` devuelve `false` tanto si la clave no existe como si su valor es `null`. Es la forma correcta de preguntar "¿hay sesión iniciada?".
2. **Guarda el motivo** en la sesión. Ojo: si solo pones el mensaje en la URL o en una variable local, se pierde al redirigir. La sesión es lo único que sobrevive al salto.
3. **Redirige** al login con `header('Location: ...')`.
4. **`exit`**: sin esto, PHP **seguiría ejecutando** el resto del método e imprimiría la página protegida detrás de la redirección. `exit` corta la ejecución aquí.

### Por qué es fundamental el `exit`

Un error clásico: si quitas el `exit`, el navegador recibe la orden de redirección *y* el HTML de la página privada. Dependiendo del navegador puedes ver un error o contenido parpadeando. Y en `PartidosController::index()` se verían los datos **después** del `header()`.

`header()` solo funciona si **no se ha enviado nada todavía**. Cualquier `echo`, cualquier espacio en blanco antes del `<?php` y un mensaje de warning, y el `header()` falla silenciosamente con el aviso *"Cannot modify header information - headers already sent"*.

### La dónde hay que llamarla

**Primera línea de cada método público.** En `PartidosController`:
```php
public function index(): void          { $this->requiereLogin(); ... }
public function crear(): void          { $this->requiereLogin(); ... }
public function guardar(): void        { $this->requiereLogin(); ... }
public function editar(array $params): void { $this->requiereLogin(); ... }
public function actualizar(): void     { $this->requiereLogin(); ... }
public function eliminar(array $params): void { $this->requiereLogin(); ... }
```

⚠️ **Fíjate en la diferencia de firma:** `editar()` y `eliminar()` reciben `array $params`, y las demás no reciben nada. Es el `Router` el que decide: siempre llama con `[$this->params]` como argumento (el array con lo que sobra de `$_GET`), y PHP permite que un método **ignore** argumentos de más. Por eso `index()` no declara parámetros y funciona igual.

Si se te olvida `requiereLogin()` en uno, esa acción queda accesible sin autenticación. Está verificado que los 4 módulos principales están protegidos.

### Por qué comprobar la sesión en cada método y no una sola vez

Podrías poner la comprobación en el `Router` y ahorrar líneas. Pero entonces las acciones públicas (el login, el registro) también quedarían bloqueadas: no se puede proteger "todo menos estas tres". Comprobar método por método es más verboso, pero permite tener páginas públicas y privadas mezcladas.

## 6. Lo que esta clase NO hace

Detalle importante para entender los límites del diseño:

- **No sabe nada de la base de datos.** No hay `require` de modelos aquí. Cada controlador carga los suyos.
- **No sabe nada de HTML.** Solo decide qué vista se muestra; el contenido lo pone la vista.
- **No valida datos.** Eso lo hacen los controladores, con las reglas de cada módulo.
- **No tiene control de roles.** Cualquier usuario registrado entra a todo. No existe el concepto de administrador.

## 7. Errónea común que conviene evitar

**Escribir la ruta de la vista con `.php` incluido o sin `./`:**
```php
$this->view('partidos/index.php');   // ❌ el layout ya añade .php
$this->view('./partidos/index');    // ❌ sobrante
$this->view('partidos/index');      // ✔️ correcto
```

**Colocar `echo` o HTML antes de `header()`**, que rompe la redirección. En un archivo PHP que empieza con un bloque `<?php` no hay problema, pero si el archivo empieza con una línea en blanco fuera de las etiquetas, ya has enviado cabeceras.

## 8. Resumen

| Elemento | Para qué sirve |
|---|---|
| `extends Controller` | Hereda `view()` y `requiereLogin()` |
| `protected` | Los hijos los usan; el resto del mundo no |
| `view($vista, $datos)` | Carga el layout, que a su vez carga la vista |
| `extract($datos)` | Convierte claves del array en variables |
| `require_once` | Carga el layout una sola vez |
| `requiereLogin()` | Corta la petición si no hay sesión |
| `$_SESSION['error']` | El mensaje viaja entre las dos peticiones |
| `exit` | Imposible que se imprima la página tras redirigir |

---

**Archivos relacionados:** `Router-explicado.md` · `../Controllers/PartidosController-explicado.md` · `../Vistas/Layout-y-vistas-explicados.md`

**Siguiente paso →** `Database-explicado.md` (cómo se abre la conexión que usa `Model`).