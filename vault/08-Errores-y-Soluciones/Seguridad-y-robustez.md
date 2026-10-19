# Seguridad y robustez: qué se corrigió en la segunda auditoría

> Este documento recoge los cambios de seguridad aplicados después de la
> revisión completa del código. No es un tutorial de seguridad: es el registro
> de **qué estaba mal, por qué importaba y qué se hizo** en este proyecto.

**Fecha:** octubre de 2026 · **Alcance:** `app/`, `public/`, `database/schema.sql` · **Pruebas:** `tests/regresion.sh` (81 comprobaciones)

---

## 1. Por qué hubo que hacer esto

La primera entrega documentaba las limitaciones del proyecto con mucha honestidad (el vault tiene una sección entera de "⚠️ limitaciones reales"). El problema es que **documentar un fallo no lo arregla**: un borrado que da pantalla en blanco sigue dando pantalla en blanco aunque esté escrito en un documento.

Esta auditoría cambió el enfoque: cada limitación documentada que tenía un arreglo razonable se arregló, y el documento se actualizó para describir el código nuevo.

**Lo que NO se arregló** (y sigue documentado como decisión): el nombre de equipo no es único, `posicion` es `VARCHAR` y no `ENUM`, no hay HTTPS, no hay paginación, no hay copia de seguridad automática.

---

## 2. Lista blanca en el Router

### El problema

El Router construía la ruta del archivo a partir de lo que llegaba en la URL:

```php
$rutaControlador = '../app/controllers/' . $nombreClase . '.php';
if (!file_exists($rutaControlador)) {
    die("Error: No se encontró el controlador: " . $nombreClase);
}
```

Tres problemas en tres líneas:

| Entrada | Qué pasaba |
|---|---|
| `?controller=<script>alert(1)</script>` | El HTML se imprimía sin escapar: **XSS reflejado** |
| `?controller[]=x` | `trim()` recibía un array: `TypeError` → **pantalla en blanco** |
| `?action=view` | `method_exists()` aceptaba métodos protegidos → **error fatal** |

### El arreglo

Una constante con todo lo que existe, comprobada antes de tocar el disco:

```php
private const RUTAS = [
    'auth'         => ['index', 'login', 'register', 'guardarregistro', 'logout'],
    'equipos'      => ['index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar'],
    'jugadores'    => ['index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar'],
    'partidos'     => ['index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar'],
    'estadisticas' => ['index'],
];
```

Y un método `error()` que **no imprime el dato recibido**:

```php
private function error(string $mensaje): void {
    http_response_code(404);
    echo '...<p>' . e($mensaje) . '</p>...';
    exit;
}
```

⚠️ **Escapar funciona; no imprimir es imposible de exploitationar.** Un mensaje de error que dice "no se encontró el controlador *X*" no debería existir: aunque `X` esté escapado, sigue filtrando qué archivos hay en el servidor.

### `is_callable()` en lugar de `method_exists()`

`method_exists()` devuelve `true` para cualquier método declarado, incluidos los **protegidos** y los **privados**. `is_callable()` solo devuelve `true` para lo invocable desde fuera.

| URL | Antes | Ahora |
|---|---|---|
| `?action=view` | 500 (fatal) | 404 |
| `?action=requiereLogin` | 500 (fatal) | 404 |
| `?action=__construct` | 200 (construcción doble) | 404 |
| `?action=volar` (público inexistente) | 500 (fatal) | 404 |

⚠️ **`is_callable()` solo no basta.** Un método público inventado seguiría siendo invocable, y `__construct` es público. Por eso se mantiene la lista blanca: ella dice *qué* se puede llamar, `is_callable()` confirma que *realmente* se puede.

---

## 3. El header `Host` ya no decide dónde redirigir

### El problema

```php
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $esquema . '://' . $host . $carpeta . '/');
```

`HTTP_HOST` es una cabecera que **envía el cliente**. No la usa el servidor para decidir nada: es texto sin verificar.

```bash
$ curl -H 'Host: evil.example.com' 'http://localhost/...?controller=Auth&action=guardarRegistro' -d '...'
Location: http://evil.example.com/ProyectoBasket-Deploy/public/?controller=Auth&action=register
```

Un sitio malo podría hacer que, tras enviar el formulario, el usuario aterrice en una copia de la aplicación donde se introduce su contraseña.

### El arreglo

`BASE_URL` ahora contiene **solo la ruta**, nunca el dominio:

```php
$carpeta = trim($carpeta, '/');
$carpeta = $carpeta === '' ? '' : '/' . $carpeta;
define('BASE_URL', $carpeta . '/');
```

Todas las redirecciones quedan como `Location: /ProyectoBasket-Deploy/public/?controller=...`, que es una URL **relativa**: el navegador la resuelve contra el host real, así que es imposible desviarlo.

⚠️ **Perder la URL absoluta es un precio aceptable.** Antes la app "funcionaba en cualquier carpeta"; ahora también, porque la ruta se detecta igual. Lo que se pierde es poder cambiar de dominio desde el navegador sin editar código, y eso nunca fue una necesidad real.

---

## 4. Validación en el servidor (y por qué el navegador no bastaba)

### El problema

Los controladores confiaban en los atributos HTML5 (`required`, `type="number"`, `maxlength`). Son **ayudas visuales**: `curl` los ignora por completo.

```bash
$ curl -X POST '...?controller=Equipos&action=guardar' -d 'nombre=<200 caracteres>&ciudad=X'
HTTP 500   -- Data too long for column 'nombre' (MySQL 1406)
```

| Dato inválido | Error MySQL | Síntoma |
|---|---|---|
| Texto muy largo | 1406 | Pantalla en blanco |
| `equipo_id` inexistente | 1452 | Pantalla en blanco |
| Fecha `31/02/2026` | 1292 | Pantalla en blanco |
| Puntos `5000` sobre 999 | 1264 | Pantalla en blanco |

### El arreglo

Los límites viven en `config.php`, junto a la base de datos, y se comparan con la definición de las columnas:

```php
define('MAX_NOMBRE', 100);       // equipos.nombre, jugadores.nombre, usuarios.nombre
define('MAX_CIUDAD', 100);       // equipos.ciudad
define('MAX_CAMISETA', 99);      // jugadores.numero
define('MAX_PUNTOS', 999);       // partidos.puntos_local / puntos_visitante
define('POSICIONES', ['Base', 'Escolta', 'Alero', 'Ala-Pívot', 'Pívot']);
```

⚠️ **Un límite en PHP y otro en el esquema es una contradicción esperando a ocurrir.** Si alguien sube el `VARCHAR(100)` a `VARCHAR(200)`, el PHP sigue cortando a 100 y no se nota el error. La regla: cambiar los dos juntos, y por eso están en `config.php` con un comentario que lo dice.

La fecha se valida de verdad, no solo su formato:

```php
function fechaValida(string $valor): ?string {
    $formatos = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formatos as $formato) {
        $fecha = DateTime::createFromFormat($formato, $valor);
        $errores = DateTime::getLastErrors();
        $hayErrores = is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0);
        if ($fecha !== false && !$hayErrores) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }
    return null;
}
```

⚠️ **`createFromFormat()` acepta cosas que no existen.** `DateTime::createFromFormat('Y-m-d', '2026-02-31')` devuelve un objeto: en realidad ajusta el día al 31 de marzo y no se queja. Por eso hay que mirar `getLastErrors()`, que sí detecta el desbordamiento. Ignorarlo es un error sutil y muy fácil de no ver.

---

## 5. Manejador global de excepciones

### El problema

`Database` configura `PDO::ERRMODE_EXCEPTION` (correcto: no esconder errores), pero `public/index.php` no tenía ningún `try/catch`. Cualquier error de la base de datos terminaba en una pantalla vacía.

### El arreglo

```php
function manejarExcepcion(Throwable $e): void {
    error_log('[ProyectoBasket] ' . get_class($e) . ': ' . $e->getMessage());

    if ($e instanceof PDOException) {
        $detalle = errorAmigable($e);
        $codigo  = (int)($e->errorInfo[1] ?? 0);
    } else {
        $detalle = 'Se esperaba un error controlado en la base de datos.';
        $codigo  = 500;
    }

    http_response_code(in_array($codigo, [1451, 1452, 1062], true) ? 409 : 500);
    echo '...<p>' . e($detalle) . '</p>...';
}
```

| Código MySQL | Significado | Mensaje para el usuario |
|---|---|---|
| 1451 | FK `RESTRICT` | "No se puede eliminar: tiene datos asociados" |
| 1452 | FK inexistente | "El registro seleccionado no existe" |
| 1406 | Texto demasiado largo | "Algún texto es más largo de lo permitido" |
| 1292 | Fecha inválida | "La fecha indicada no es válida" |
| 1264 | Fuera de rango | "Un número está fuera de rango" |
| 1062 | Duplicado | "Ya existe un registro con esos datos" |

⚠️ **`getMessage()` nunca se muestra al usuario.** El texto de PDO incluye el SQL y los nombres de tabla y columna. Se registra con `error_log()` (que solo ve quien administra el servidor) y se enseña la traducción.

⚠️ **HTTP 409, no 500.** Un 500 dice "el servidor está roto"; un 409 dice "tu petición choca con el estado actual de los datos". Para un formulario, lo segundo es más útil.

⚠️ **El manejador es la red de seguridad, no el plan.** Con la validación de la sección anterior, no debería ejecutarse nunca. Se mantiene porque el día que alguien añada una consulta nueva sin validar, la diferencia entre un mensaje y una pantalla en blanco es de unos segundos de trabajo.

---

## 6. CSRF: el token y el borrado por POST

### El problema

El listado borraba con un enlace:

```html
<a href="...?controller=Equipos&action=eliminar&id=3" onclick="return confirm('¿Eliminar?')">
```

Un `GET` que borra tiene tres problemas: se ejecuta con un clic accidental, se puede reejecutar desde el historial o el caché del navegador, y **no se puede proteger con CSRF** (los tokens viajan en el cuerpo del POST, no en la URL). Cualquier página cargada en un foro podía borrar filas de la base de datos de quien la visitara.

### El arreglo

Token en la sesión, campo oculto en cada formulario, comprobación en cada POST:

```php
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValido($recibido): bool {
    if (!is_string($recibido) || $recibido === '') { return false; }
    $guardado = $_SESSION['csrf_token'] ?? '';
    return is_string($guardado) && $guardado !== '' && hash_equals($guardado, $recibido);
}
```

```php
function exigirPost(string $destino, bool $csrf = true): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        redir($destino);
    }
    if ($csrf) {
        exigirCsrf();
    }
}
```

Y el borrado pasó a ser un formulario:

```html
<form action="<?= BASE_URL ?>?controller=Equipos&action=eliminar" method="POST" class="inline-form"
      onsubmit="return confirm('¿Eliminar este equipo?');">
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= $equipo['id'] ?>">
    <button type="submit" class="btn btn-danger">Eliminar</button>
</form>
```

⚠️ **`hash_equals()` y no `===`.** Una comparación normal (`$a === $b`) devuelve `true` en cuanto encuentra el primer carácter distinto, así que tarda *menos* cuanto más se parece el atacante. Con 64 caracteres hexadecimales, esa diferencia basta para adivinar el token byte a byte en unos miles de intentos. `hash_equals()` siempre compara entero.

⚠️ **`random_bytes()` y no `rand()`/`uniqid()`.** Un token predecible no protege de nada. `bin2hex(random_bytes(32))` usa el generador criptográfico del sistema operativo.

⚠️ **`exigirCsrf()` responde 403, no 419.** El código 419 (más usado en Laravel) es correcto conceptualmente, pero **Apache no lo conoce y lo convierte en 500**. Se comprobó: `http_response_code(419)` es aceptado por PHP, pero la respuesta que ve el navegador es 500. Un código de estado que no existe en el servidor web es un 500 disfrazado.

⚠️ **El token se genera una vez por sesión y se reutiliza.** No hace falta uno por formulario. Lo que sí importa es que cambie al iniciar sesión: por eso `session_regenerate_id(true)` va **después** de `password_verify()`, y el token del session anterior desaparece con ella.

⚠️ **`confirm()` se dejó.** Es cómodo, pero no protege: se desactiva desde la consola del navegador. Ahora hay una protección real detrás, así que el `confirm()` es solo experiencia de usuario. Antes era la única barrera, y por eso se documentaba como agujero.

---

## 7. Cookies de sesión

### El problema

```
Set-Cookie: PHPSESSID=02i829pt6esq15afpg8e25bso2; path=/
```

Sin `HttpOnly`: cualquier JavaScript de la página podía leer el identificador de sesión y robarlo. Sin `SameSite`: el navegador lo enviaba también en peticiones iniciadas desde otros sitios.

### El arreglo

```php
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $esHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
```

⚠️ **Estos `ini_set()` van ANTES de `session_start()`.** Cambiar los parámetros de la cookie después de iniciar la sesión no tiene efecto: ya se envió la cabecera. Es un detalle que cuesta un rato descubrir.

⚠️ **`secure` depende de HTTPS.** En XAMPP con HTTP plano hay que ponerlo a `false` o el navegador descarta la cookie y no se puede iniciar sesión. El código lo detecta con `$_SERVER['HTTPS']`.

⚠️ **`use_strict_mode` impide el secuestro de sesión fixation por id arbitrario.** Sin él, si alguien envía un `PHPSESSID` inventado, PHP lo acepta y lo crea. Con él, si el identificador no existe en el servidor, PHP genera uno nuevo.

⚠️ **`SameSite=Lax` y no `Strict`.** `Strict` rompería el proyecto: al recargar una página y seguir un enlace, el navegador no enviaría la cookie. `Lax` bloquea los POST desde sitios externos (que es justo el ataque CSRF) y permite la navegación normal.

---

## 8. Bloqueo de intentos en el login

### El problema

Login sin rate limiting: un script podía probar miles de contraseñas por minuto contra `demo@liga.com`.

### El arreglo

El contador está en el **servidor**, en un archivo por IP dentro de la carpeta temporal, y no en la sesión:

```php
// app/core/LimitadorIntentos.php
LimitadorIntentos::estaBloqueado($clave);   // ¿está bloqueada esta IP?
LimitadorIntentos::registrarFallido($clave); // suma un intento
LimitadorIntentos::limpiar($clave);          // login correcto: contador a cero
```

La clave es la dirección IP del visitante:

```php
private static function claveDeBloqueo(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
}
```

Cada intento fallido se guarda como una marca de tiempo en un JSON, y se cuentan solo los que caen dentro de la ventana de 15 minutos. El archivo se bloquea con `flock()` para que dos peticiones simultáneas no se pisen, y el nombre del archivo es el hash SHA-256 de la IP, para no dejar la dirección a la vista en el sistema de archivos.

⚠️ **El contador NO puede vivir en la sesión.** Un contador en `$_SESSION` no protege de nada: el atacante simplemente no envía la cookie y empieza de cero en cada intento. Es la diferencia entre una frenada que no frena y una que sí.

⚠️ **Si la aplicación se pone detrás de un proxy, `REMOTE_ADDR` es la del proxy** y el bloqueo afectaría a todos los usuarios a la vez. En ese caso habría que usar `X-Forwarded-For`, validada contra una lista blanca de proxies de confianza.

⚠️ **Se verifica la contraseña aunque el usuario no exista.** Antes se hacía `if ($usuario === null || !password_verify(...))`, con cortocircuito: si el usuario no existía, `password_verify` no se ejecutaba y la respuesta era más rápida. Esa diferencia de tiempo permite enumerar qué emails están registrados. Ahora se verifica siempre contra un hash falso:

```php
$hash = $usuario['password'] ?? '$2y$10$b5pz5IIYg3leDdmiAxjLmePzvOSB/93Y977NnpATb5/hHY2uexpB2';
if (!password_verify($password, $hash) || $usuario === null) {
```

⚠️ **Ese hash tiene que ser un bcrypt VÁLIDO de 60 caracteres.** La primera versión usaba una cadena de 67 caracteres que parecía un hash pero no lo era. Por casualidad PHP la trataba igual de lento (leía el prefijo `$2y$10$` y hacía el trabajo completo), así que el oráculo de temporización estaba cerrado... pero por accidente. Con un hash mal formado más corto, `password_verify` vuelve en 0,09 ms en vez de 53 ms y el oráculo reaparece. Medido:

| Hash | Longitud | Tiempo de `password_verify` |
|---|---|---|
| bcrypt válido | 60 | 53 ms |
| El que había (malformado) | 67 | 53 ms (por casualidad) |
| Prefijo con cuerpo corto | 12 | **0,09 ms** (oráculo abierto) |
| Texto que no es un hash | 13 | 0,22 ms |

Por eso el hash actual es un bcrypt de verdad, generado sobre una cadena aleatoria que no se guarda en ningún sitio: sirve solo para gastar el mismo tiempo.

---

## 9. Integridad de datos

### `posicion` sin lista blanca

La columna es `VARCHAR(50)` a propósito (para no bloquear valores nuevos), pero eso permite cualquier texto:

```bash
$ curl -X POST '...?controller=Jugadores&action=guardar' -d 'nombre=X&posicion=ADMIN&numero=1&equipo_id=1'
# Guardado: posicion = "ADMIN"
```

Arreglo: `define('POSICIONES', [...])` y comparación con `in_array($posicion, POSICIONES, true)`.

### Dorsales repetidos, nombres de equipo repetidos y `UNIQUE` que no se creaba

Se añadieron dos índices únicos a `schema.sql`: `UNIQUE (equipo_id, numero)` para los dorsales y `UNIQUE (nombre)` para los equipos.

⚠️ **El nombre de equipo repetido no era un detalle menor.** Se podían crear dos equipos llamados "Los Lakers" y en la **tabla de posiciones** aparecían dos filas con el mismo nombre que el usuario no podía distinguir. La ciudad no sirve para desempatar porque esa tabla no la muestra. Ahora el controlador avisa antes de guardar:

```php
} elseif ($this->equipoModel->existeNombre($nombre, $ignorarId ?? 0)) {
    $errores[] = 'Ya existe un equipo con ese nombre';
}
```

Pero `CREATE TABLE IF NOT EXISTS` **no modifica una tabla que ya existe**, así que los índices no aparecían en una base creada antes del cambio. Se resolvió con un bloque idempotente:

```sql
SET @indice_ya_existe = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'jugadores'
    AND INDEX_NAME   = 'uq_jugador_camiseta'
);
SET @sql_indice = IF(
  @indice_ya_existe = 0,
  'ALTER TABLE jugadores ADD UNIQUE KEY uq_jugador_camiseta (equipo_id, numero)',
  'DO 0'
);
-- el mismo patrón, con su propia comprobación de duplicados, para
-- uq_equipo_nombre sobre la tabla equipos
PREPARE stmt_indice FROM @sql_indice;
EXECUTE stmt_indice;
DEALLOCATE PREPARE stmt_indice;
```

⚠️ **`IF NOT EXISTS` significa "no crear si ya existe", no "actualizar si ya existe".** Es la lección de fondo: un esquema que se quiere hacer evolucionar sobre bases existentes necesita `ALTER` con su propia comprobación.

⚠️ **`NULL` no cuenta como duplicado en un `UNIQUE`.** Varios jugadores sin dorsal en el mismo equipo conviven sin conflicto, que es justo lo que se quiere (el número es opcional).

---

## 10. Rutas de archivos: `__DIR__` en todas partes

`public/index.php` usaba `__DIR__`, pero **el resto de la aplicación no**:

```php
// Router.php, antes
$rutaControlador = '../app/controllers/' . $nombreClase . '.php';
// Controller.php, antes
require_once '../app/views/layouts/main.php';
```

Una ruta relativa se resuelve contra el **directorio de trabajo del proceso**, no contra el archivo que la ejecuta. Con Apache y XAMPP ese directorio es `public/`, así que funcionaba. Se comprobó que en cualquier otro contexto se rompía:

```bash
$ php -r 'chdir("/tmp"); require "/ruta/al/proyecto/public/index.php";'
No se encontró el controlador: AuthController
```

Ahora los 13 `require` usan `__DIR__`, y la aplicación funciona desde cualquier directorio de trabajo.

⚠️ **`__DIR__` es la carpeta del archivo, `__FILE__` es el archivo completo.** En un `require` casi siempre se quiere `__DIR__`.

⚠️ **La vista que se carga también está en un `require`.** `layouts/main.php` hacía `'../app/views/' . $vista . '.php'`. Con `__DIR__` ya no depende del directorio de trabajo, y además se dejó de imprimir la ruta del disco cuando la vista no existe.

---

## 11. Lo que se comprobó y NO era un fallo

Una auditoría también sirve para **descartar** sospechas. Estas se investaron, se probaron y resultaron correctas:

| Sospecha | Resultado |
|---|---|
| `bindValue(':f', null, PDO::PARAM_STR)` guarda `''` | **Falso.** Con prepares nativos guarda `NULL` (probado en PHP 8.2 + MariaDB 10.4) |
| MySQL rechaza `2026-10-12T19:00` por la `T` | **Falso.** Lo acepta y lo guarda como `2026-10-12 19:00:00` |
| `?controller=../../etc/passwd` permite leer archivos | **Parcial.** El sufijo `Controller.php` bloqueaba el caso común, pero la lista blanca lo hace imposible |
| Los equipos sin partidos no salen en estadísticas | **Cierto**, y ahora está arreglado (ver `EstadisticasController-explicado.md`) |

⚠️ **La primera se investigó porque el propio vault la daba por dudosa** ("Comportamiento incierto según la versión"). Se probó en el entorno real y se corrigió el documento. Una nota que dice "no estoy seguro" es una nota pendiente de verificar.

---

## 12. Cómo se comprueba todo esto

`tests/regresion.sh` ejecuta **81 comprobaciones** contra la aplicación desplegada, agrupadas en 12 bloques:

```bash
./tests/regresion.sh
# ...
# RESULTADO: 81 correctas, 0 fallidas
```

⚠️ **La suite modifica la base de datos y la reinicia al terminar** desde `database/schema.sql` y `database/seeds.sql`, dejándola en 4/12/4/2. No se ejecuta contra una base con datos reales.

⚠️ **Comprueba respuestas HTTP, no el estado interno.** Si una validación falla pero la aplicación responde 302, la prueba lo detecta mirando el mensaje de error que ve el usuario, no variables de PHP. Es lo que importa.

⚠️ **Antes de cada ejecución hay que importar los seeds**, porque si no la prueba de "el equipo nuevo aparece en el listado" hereda datos de la ejecución anterior y da un falso positivo.

---

## 13. Cómo verificarlo a mano

```bash
# 1. La base en estado limpio
/opt/lampp/bin/mysql -u root liga_basquet < database/schema.sql
/opt/lampp/bin/mysql -u root liga_basquet < database/seeds.sql

# 2. La sintaxis de todos los PHP
find app public -name '*.php' -exec /opt/lampp/bin/php -l {} \;

# 3. La batería completa contra la app desplegada
./tests/regresion.sh

# 4. Comprobaciones directas
curl -i 'http://localhost/ProyectoBasket-Deploy/public/index.php?controller=Auth&action=view'
curl -i 'http://localhost/ProyectoBasket-Deploy/public/index.php?controller[]=x'
curl -i -H 'Host: evil.example.com' 'http://localhost/ProyectoBasket-Deploy/public/index.php?controller=Equipos&action=index'
```

Esperado: `404`, `404` y un `Location:` que menciona `localhost`, nunca `evil.example.com`.

---

## 14. El proyecto entero no puede quedar servido por Apache

### El problema

La instalación por defecto de XAMPP copia el proyecto **entero** dentro de `htdocs/`, y el `DocumentRoot` de Apache apunta a `htdocs/`. Todo lo que cuelgue de ahí es descargable:

| Petición | Qué se descarga |
|---|---|
| `/database/seeds.sql` | Los hashes bcrypt de `demo@liga.com` y `admin@liga.com` (contraseña: `password`) |
| `/.git/` | El código fuente completo y todo el historial de commits |
| `/vault/`, `/doc/` | La documentación interna del proyecto |

El `.zip` de entrega tiene que incluir `.git` por requisito del enunciado, así que el riesgo es real: **los 392 objetos del repositorio se descargaban con HTTP 200**.

### El arreglo: dos capas

**Capa 1 (la principal):** el `DocumentRoot` o un `Alias` apuntan a la carpeta `public/`. Con eso, el resto del proyecto ni siquiera existe para el servidor web.

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/ProyectoBasket/public"
    ServerName ligabasquet.local
</VirtualHost>
```

**Capa 2 (la red de seguridad):** dos `.htaccess` incluidos en el repositorio, por si alguien deja el proyecto dentro de `htdocs/` sin configurar el servidor.

En la **raíz del proyecto**:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^public/ - [L]   # lo que cuelgue de public/ se permite
    RewriteRule ^ - [F,L]        # todo lo demás: prohibido (403)
</IfModule>

<IfModule mod_authz_core.c>
    Require all denied           # red de seguridad si no hay mod_rewrite
</IfModule>
```

Y en **`public/.htaccess`**, la contrapartida que vuelve a permitir el acceso:

```apache
<IfModule mod_authz_core.c>
    Require all granted
</IfModule>
```

⚠️ **Por qué se comparan rutas relativas y no la URL.** Una primera versión usaba `RewriteCond %{REQUEST_URI} !/public(/|$)` y fallaba en dos casos: con un `Alias` la URL ya no contiene `/public/`, y una ruta de instalación que contenga la palabra "public" (por ejemplo `/home/user/public/ProyectoBasket/`) dejaría pasar todo. Con `RewriteRule ^public/` la comparación es contra la ruta **relativa a la carpeta del proyecto**, así que da igual cómo se llame la carpeta de instalación.

⚠️ **`php -S` no lee `.htaccess`.** El servidor embebido de PHP lo ignora: es una herramienta de desarrollo local, no de despliegue.

### Cómo se comprobó

Montando un Apache 2.4 de prueba (con `AllowOverride All`) y pidiendo cada archivo por HTTP:

| Petición | Antes | Después |
|---|---|---|
| `/public/index.php` | 200 | **200** |
| `/public/css/styles.css` | 200 | **200** |
| `/database/seeds.sql` | **200** | **403** |
| `/.git/config` | **200** | **403** |
| `/.git/objects/...` | **200** | **403** |
| `/README.md` | **200** | **403** |

Y se repitió en los tres despliegues habituales: proyecto entero dentro de `htdocs/`, `DocumentRoot` real apuntando a `public/` y ruta de instalación con "public" en el nombre. En los tres, `public/` responde 200 y el resto 403. Cada mecanismo bloquea por sí solo, de modo que la protección aguanta aunque el servidor no tenga `mod_rewrite`.

---

## 15. Lo que queda pendiente

Nada de lo listado bloquea la entrega, pero conviene saberlo:

| Asunto | Por qué no se hizo |
|---|---|
| **HTTPS** | Necesita un certificado. `secure` en la cookie ya se activa solo cuando hay HTTPS |
| **Contraseña de MySQL** | `root` sin contraseña es el valor por defecto de XAMPP. En producción sería un usuario propio con permisos reducidos |
| **Límite de intentos en base de datos** | El contador está en la sesión; un atacante serio abre sesión nueva cada vez |
| **Registro de auditoría** | No hay tabla que registre "quién borró qué". Recomendable en cualquier CRUD real |
| **Paginación** | No hay `LIMIT` en los listados. Con muchos registros la aplicación se ralentiza |

---

## Archivos relacionados

`../../08-Errores-y-Soluciones/Registro-de-errores.md` (errores 16–22, la historia completa de cada fallo) · `../06-Codigo-Explicado/Core/Router-explicado.md` (lista blanca en detalle) · `../06-Codigo-Explicado/Controllers/EquiposController-explicado.md` (el borrado con FK, paso a paso) · `../06-Codigo-Explicado/Controllers/AuthController-explicado.md` (sesión y bloqueo) · `../04-BD/schema-explicado.md` (las restricciones y por qué se eligieron así) · `../../tests/regresion.sh`