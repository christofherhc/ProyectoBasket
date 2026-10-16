# AuthController (explicado)

**Archivo real:** `app/controllers/AuthController.php`
**Rol:** registro, inicio y cierre de sesión. Es el **único controlador con acciones públicas**.

## 1. La diferencia con los otros controladores

Los otros 4 controladores son casi idénticos: un CRUD. Este es distinto porque gestiona la **frontera entre visitante y usuario**, y es el único al que se puede llegar sin haber iniciado sesión.

| Método | ¿Público? | Qué hace |
|---|---|---|
| `index()` | Sí | Muestra el formulario de login |
| `login()` | Sí | Procesa el login |
| `register()` | Sí | Muestra el formulario de registro |
| `guardarRegistro()` | Sí | Procesa el registro |
| `logout()` | Sí | Cierra la sesión |

**Las acciones públicas se protegen con una comprobación inversa:** si **ya** hay sesión, se redirige a la aplicación.

```php
public function index(): void {
    if (isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }
    $this->view('auth/login', ['titulo' => 'Iniciar Sesión']);
}
```

Sin esto, un usuario ya autenticado que entre a `/login` vería el formulario otra vez, lo cual es confuso (aunque no fuera un agujero de seguridad grave: reautenticarse de nuevo no le daría más permisos).

**Fíjate en que aquí NO hay `$this->requiereLogin()`.** Es al revés. Esa omisión es deliberada.

## 2. `login()`: el flujo completo

### Paso 1: rechazar si no es un POST

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```

**Por qué importa.** Sin esta comprobación, entrar a `?controller=Auth&action=login` con un simple clic en la barra de direcciones intentaría procesar un login con datos vacíos y mostraría un error en vez de redirigir al formulario.

Esta es una **buena práctica de diseño de rutas**: las acciones que procesan formularios (`login`, `guardar`, `actualizar`, `eliminar`) solo deben responder a `POST`. Es más seguridad y menos código. En el proyecto esto lo resuelve el helper `exigirPost()`, que además valida el token CSRF:

```php
function exigirPost(string $destino, bool $csrf = true): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        redir($destino);        // un GET redirige, no procesa nada
    }
    if ($csrf) {
        exigirCsrf();           // y sin token válido responde 403
    }
}
```

Verificado en las pruebas: pedir `action=guardar` por GET redirige al formulario **y no crea ningún registro**.

⚠️ Nota: `eliminar()` también usa `exigirPost()`. Todos los borrados son POST con token CSRF; un `?action=eliminar&id=1` por GET redirige sin borrar nada.

### Paso 2: leer y limpiar los datos

```php
$email = trim(strtolower($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
```

Tres decisiones:

**a) `$_POST['x'] ?? ''`** → el operador `??` (coalescente) devuelve el valor si existe y `''` si no. Evita el aviso *"Undefined array key"* de PHP cuando el campo no viene. Es más seguro que `isset($_POST['x']) ? ... : ...`.

**b) `trim()` en el email** → quita espacios al principio y al final. Un usuario que pega `" demo@liga.com "` (con espacio) debería poder entrar.

**c) `strtolower()` en el email** → normaliza a minúsculas. Así `Demo@Liga.com` y `demo@liga.com` son el mismo usuario, y el `UNIQUE` de la base no se rompe por variations de mayúsculas.

**⚠️ Nunca apliques `trim()` a la contraseña.** Las contraseñas son texto exacto: un espacio al final puede ser intencional. Si haces `trim()`, dejas de ser capaz de iniciar sesión con ella. Es un error sutil y muy real.

### Paso 3: validar que no estén vacíos

```php
if (empty($email) || empty($password)) {
    $_SESSION['error'] = 'Email y contraseña son obligatorios';
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```

`empty()` cubre `''`, `'0'`, `0`, `false`, `null` y el array vacío. Suena bien, pero **hay que tener cuidado con el '0'**: `empty('0')` es `true`, así que un email o contraseña que fuera literalmente el texto "0" se rechazaría. Es un caso tan raro que no suele importar, pero para ser precisos se usaría `strlen($email) === 0`.

### Paso 4: buscar y verificar

```php
require_once '../app/models/Usuario.php';
$usuarioModel = new Usuario();

$usuario = $usuarioModel->buscarPorEmail($email);

if ($usuario === null || !password_verify($password, $usuario['password'])) {
    $_SESSION['error'] = 'Credenciales incorrectas';
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```

**⚠️ El mensaje "Credenciales incorrectas" es el correcto y no un descuido.** Muchos sistemas dicen "usuario no encontrado" o "contraseña incorrecta", lo cual permite a un atacante **descubrir qué emails existen** probando hasta que el mensaje cambie.

**La comparación con `||` (o) es intencionada:** si el usuario no existe, no hay nada que verificar, así que falla. Se podría escribir como dos `if` separados, pero con `||` es una línea y no hay forma de saltarse la verificación.

**`password_verify()` y no `===`.** Esta es la línea más importante del archivo:

```php
password_verify($contraseñaEnTextoPlano, $hashGuardadoEnLaBase)
```

Compara **la contraseña que escribió el usuario** contra **el hash almacenado**. Devuelve `true` o `false`. Es la única forma correcta: la base nunca tuvo la contraseña real, solo su huella.

**El orden importa.** Primero se busca por email (una consulta), y **después** se verifica la contraseña (una operación de CPU, la parte lenta). Es lo eficiente: si el email no existe, no gastas CPU verificando.

⚠️ **Lo que NO se hace, y es un error clásico:**

```php
// ❌ NUNCA HAGAS ESTO
$sql = "SELECT * FROM usuarios WHERE email = ? AND password = ?";
$stmt->execute([$email, $password_hash]);
```

No funciona, por dos razones:
1. **Es una inyección SQL esperando a ocurrir** si olvidas el prepared statement.
2. **No encontraría nada**, porque bcrypt usa una sal aleatoria: `password_hash('password')` da un hash **distinto** cada vez. El hash guardado jamás coincidirá con uno recién calculado.

### Paso 5: el login exitoso

```php
session_regenerate_id(true);
$_SESSION['usuario_id']    = $usuario['id'];
$_SESSION['nombre']        = $usuario['nombre'];
$_SESSION['email']         = $usuario['email'];
$_SESSION['logueado']      = true;

$_SESSION['exito'] = 'Sesión iniciada correctamente';
header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
exit;
```

**`session_regenerate_id(true)` va PRIMERO.** Es la defensa contra *session fixation* (robo de sesión). Ver `../../02-Fundamentos/06-Sesiones-y-Auth.md`.

**Lo que se guarda en la sesión:** solo lo mínimo. El `id`, el `nombre` (para mostrarlo en el menú), el `email` y un marcador `logueado`. **No** el hash de la contraseña (nunca se necesita después de verificar), ni datos de otros módulos.

**Por qué `$_SESSION['usuario_id']` y no solo un booleano.** `Controller::requiereLogin()` comprueba `isset($_SESSION['usuario_id'])`. Ese es el contrato entre la autenticación y la protección de páginas. Si guardaras solo `logueado`, habría que cambiar `requiereLogin()`.

**El mensaje "Sesión iniciada correctamente" viaja en la sesión** y lo muestra el layout en la página de destino. Se guarda **antes** del `header()`, porque la sesión sobrevive a la redirección.

## 3. `guardarRegistro()`: las cinco validaciones

El registro es el punto donde se decide qué datos entran en la base, así que es donde más validaciones hay.

```php
$nombre    = trim($_POST['nombre'] ?? '');
$email     = trim(strtolower($_POST['email'] ?? ''));
$password  = $_POST['password'] ?? '';
$confirmar = $_POST['confirmar'] ?? '';
```

### Guarda lo escrito ANTES de validar

```php
$_SESSION['old'] = ['nombre' => $nombre, 'email' => $email];
```

Esto parece estar fuera de lugar (está antes de los `if`), pero es una decisión deliberada: **si el registro falla, el usuario no pierde lo que escribió**. La vista rellena el formulario con estos valores.

⚠️ **Nunca se guardan las contraseñas en la sesión.** Solo `nombre` y `email`. Si guardaras la contraseña "para convenience", acabarías con contraseñas en texto plano en el disco del servidor.

### Las cinco reglas, en orden de salida

| # | Comprobación | Código | Mensaje |
|---|---|---|---|
| 1 | Campos obligatorios | `empty($nombre) \|\| empty($email) \|\| ...` | "Todos los campos son obligatorios" |
| 2 | Formato de email | `!filter_var($email, FILTER_VALIDATE_EMAIL)` | "Email no válido" |
| 3 | Contraseñas iguales | `$password !== $confirmar` | "Las contraseñas no coinciden" |
| 4 | Longitud mínima | `strlen($password) < 6` | "La contraseña debe tener al menos 6 caracteres" |
| 5 | Email no repetido | `$usuarioModel->existeEmail($email)` | "El email ya está registrado" |

**`filter_var()` con `FILTER_VALIDATE_EMAIL`** valida el formato en PHP: exige algo@algo.algo. Es una primera criba, no una garantía (acepta `a@b.co`, que existe técnicamente).

**El orden importa por experiencia de usuario.** Los errores se comprueban de arriba abajo y cada uno muestra **un** mensaje y `exit`. El usuario ve el primer problema que encuentra, lo corrige, lo reenvía, y entonces ve el siguiente. Si mostraras los cinco a la vez, sería más rápido para el usuario pero más complejo de implementar y de leer.

⚠️ **La regla 5 es redundante, y está bien que lo sea.** El campo `email` es `UNIQUE` en la base, así que MySQL rechazaría el duplicado (error 1062) aunque esta comprobación no existiera. La comprobación sirve para **dar un mensaje bonito** en lugar de una pantalla de error de SQL. Son dos capas: la validación para las personas, la restricción para la integridad.

### El orden de las validaciones, en detalle

Fíjate en que la regla 5 (email duplicado) va **última**, después de validar el formato. Si se comprobara primero, el mensaje sería "El email ya está registrado" para un email mal escrito, lo cual sería confuso.

### Guardar

```php
$hash = password_hash($password, PASSWORD_DEFAULT);
$usuarioModel->crear($nombre, $email, $hash);

unset($_SESSION['old']);
$_SESSION['exito'] = 'Registro exitoso. Ahora puedes iniciar sesión';
header('Location: ' . BASE_URL . '?controller=Auth&action=index');
exit;
```

**`password_hash($password, PASSWORD_DEFAULT)`** es el otro lado de `password_verify()`. PHP genera la sal, aplica el algoritmo y devuelve el hash completo. `PASSWORD_DEFAULT` significa "el mejor algoritmo disponible ahora" (hoy bcrypt; mañana podría ser argon2 y seguiría funcionando).

**El modelo nunca ve la contraseña en claro.** `crear()` recibe `$hash`, no `$password`. La transformación ocurre en el controlador y el modelo solo guarda lo que le dan.

**No se inicia sesión automáticamente.** Tras registrarse, el usuario vuelve al login. Podrías iniciar sesión directamente, pero volver al login es más simple y deja claro que ahora hay que autenticarse.

**`unset($_SESSION['old'])` antes de redirigir**, porque si no los datos escritos se quedarían ahí y rellenarían el siguiente formulario de registro.

## 4. `logout()`: cerrar la sesión de verdad

```php
public function logout(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```

Son cuatro pasos, y **los cuatro importan**:

| Paso | Qué hace | Por qué |
|---|---|---|
| `$_SESSION = []` | Vacía los datos en memoria | Borra `usuario_id`, y con él el acceso |
| `setcookie(...)` con tiempo pasado | Elimina la cookie `PHPSESSID` | El navegador deja de mandar el identificador |
| `session_destroy()` | Borra el archivo de sesión en el servidor | El identificador ya no apunta a nada |
| `header + exit` | Redirige al login | El usuario ve que cerró sesión |

**¿Por qué el tiempo `-42000`?** Es simplemente "más de una hora en el pasado" (42000 segundos ≈ 11.6 horas). PHP espera un timestamp de expiración; un valor en el pasado le dice al navegador "borra esta cookie ahora".

**¿Por qué `time() - 42000` y no un valor fijo?** Porque `time()` es el instante actual en cualquier máquina. Es una expresión que siempre está en el pasado, sin importar cuándo se ejecute.

**⚠️ La cookie no se borra con `setcookie` normal.** Solo se puede *sobrescribir* una cookie. Para eliminarla hay que mandarla con el mismo nombre, ruta y dominio, pero con fecha de expiración pasada.

**¿Por qué los tres pasos y no solo uno?**

- Si solo haces `$_SESSION = []`: el archivo de sesión sigue en el servidor. Sigue siendo un riesgo si alguien lo encuentra.
- Si solo haces `session_destroy()`: el navegador **sigue enviando** la cookie, y PHP crea una sesión vacía nueva con el mismo nombre. En la práctica "funciona", pero dejas cookies basura.
- Con los tres: la sesión desaparece de verdad.

**Verificado:** tras el logout, la petición a un módulo protegido devuelve el login.

⚠️ **El logout es por POST con token CSRF.** `logout()` empieza con `exigirPost(...)`, así que un enlace `GET` no cierra la sesión: redirige sin más. Antes era un `<a href>` y cualquiera podía cerrarte la sesión con un `<img src="...logout">` en una página ajena. Ahora el botón del layout es un formulario POST con `csrfCampo()`, y una petición GET a `?action=logout` responde 302 sin destruir nada.

⚠️ **El token CSRF no hay que "invalidarlo" al salir.** Al hacer `session_destroy()` el token guardado en la sesión desaparece con ella. Como el token vive en `$_SESSION`, destruir la sesión ya lo invalida.

## 5. Cómo se relaciona con el layout

El layout (`app/views/layouts/main.php`) muestra el nombre del usuario y el botón de salir usando lo que guardó este controlador:

```php
<?php if (isset($_SESSION['logueado']) && $_SESSION['logueado']): ?>
    <span>Hola, <?= e($_SESSION['nombre']) ?></span>
    <form action="<?= BASE_URL ?>?controller=Auth&action=logout" method="POST" class="inline-form">
        <?= csrfCampo() ?>
        <button type="submit" class="btn-link">Cerrar Sesión</button>
    </form>
<?php endif; ?>
```

Y los mensajes de sesión:

```php
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
```

Ese `unset` es lo que hace que el mensaje aparezca **una sola vez**.

## 6. Resumen de las decisiones de diseño

| Decisión | Motivo |
|---|---|
| Único controlador con acciones públicas | La autenticación es la frontera; el resto son módulos privados |
| `strtolower()` en el email | El `UNIQUE` no debe romperse por mayúsculas |
| Sin `trim()` en la contraseña | El texto exacto es parte de la contraseña |
| `password_verify()`, nunca `===` | Es la única comparación correcta con hashes |
| "Credenciales incorrectas" | No revela qué emails existen |
| `session_regenerate_id(true)` antes de autenticar | Evita el session fixation |
| `password_hash()` en el controlador | El modelo nunca ve la contraseña en claro |
| `filter_var()` para el email | Filtro rápido de formato |
| Validaciones en orden | Se muestra el primer problema, no todos |
| Logout en 4 pasos | Sesión realmente destruida |
| Logout por GET | ✅ **Resuelto:** ahora es un formulario POST con token CSRF |

---

**Archivos relacionados:** `../../../app/views/auth/login.php` · `../../../app/views/auth/register.php` · `../Core/Controller-explicado.md` · `../Models/Usuario-explicado.md` · `../../02-Fundamentos/06-Sesiones-y-Auth.md`

**Siguiente paso →** `EquiposController-explicado.md` (el CRUD más simple, el mejor para empezar).