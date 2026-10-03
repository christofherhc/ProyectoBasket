# 6. Sesiones y Autenticación (Login/Registro)

## 1. ¿Qué son las Sesiones PHP?

Las **sesiones** permiten mantener información del usuario entre distintas peticiones HTTP. HTTP es **stateless** (sin estado): cada petición es independiente, servidor "olvida" al usuario.

**Solución:** PHP usa `$_SESSION` para guardar datos en servidor, identificados por cookie de sesión en navegador.

**Cómo funciona:**
1. `session_start()` → Crea o reanuda sesión
2. Guardamos datos: `$_SESSION['usuario_id'] = 1; $_SESSION['nombre'] = 'Ana';`
3. En siguientes peticiones, `session_start()` recupera sesión
4. Cerramos con `session_destroy()` + `unset()`

**Importante:** `session_start()` debe llamarse **antes** de cualquier salida HTML.

## 2. ¿Qué es Autenticación?

**Autenticación** = verificar identidad ("¿Quién eres?"). En esta app: email + contraseña.

**Autorización** = verificar permisos ("¿Qué puedes hacer?"). Aquí básico (logueado vs no logueado).

Módulo **Autenticación** requiere: **Registro e Inicio de sesión**.

## 3. Flujo de Autenticación

### Registro (crear cuenta)
1. Usuario accede `?controller=Auth&action=register` (formulario)
2. Envía POST con datos (nombre, email, contraseña, confirmar)
3. Validar servidor: campos obligatorios, email válido, contraseñas coinciden
4. Verificar email no existe
5. **Hashear contraseña** con `password_hash()`
6. Guardar en BD (`usuarios`)
7. Redirigir a login con mensaje éxito

### Login (iniciar sesión)
1. `?controller=Auth&action=index` (form login)
2. POST email + contraseña
3. Buscar usuario por email
4. Verificar con `password_verify()`
5. Si OK → guardar en `$_SESSION` (`usuario_id`, `nombre`, `email`)
6. Redirigir a `?controller=Equipos&action=index`
7. Si error → volver a login con mensaje error

### Logout (cerrar sesión)
1. `?controller=Auth&action=logout`
2. Limpiar sesión: `unset($_SESSION)`, `session_destroy()`
3. Redirigir a login

## 4. Seguridad de Contraseñas: Hashing

**Nunca guardar contraseñas en texto plano.** Jamás.

### ¿Por qué hashear?

Si BD se filtra, contraseñas hasheadas son inútiles (con buen algoritmo).

### password_hash() y password_verify()

```php
// Registro
$passwordPlano = 'miClave123';
$hash = password_hash($passwordPlano, PASSWORD_DEFAULT);
// $hash ejemplo: $2y$10$... largo, único por ejecución

// Login
if (password_verify($passwordPlano, $hash)) {
    // OK, contraseñas coinciden
} else {
    // Incorrecta
}
```

**PASSWORD_DEFAULT** usa algoritmo recomendado (actualmente bcrypt). PHP actualiza algoritmo automáticamente.

**Otras opciones:** `PASSWORD_BCRYPT`. `PASSWORD_DEFAULT` es seguro y flexible.

## 5. Tabla `usuarios` (Esquema)

```sql
CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL COMMENT 'Hash con password_hash',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Razones:**
- `password VARCHAR(255)` → Hash bcrypt ocupa ~60 chars, usar 255 por seguridad/futuro
- `email UNIQUE` → Evita cuentas duplicadas
- `NOT NULL` → Campos obligatorios

## 6. Modelo Usuario (`app/models/Usuario.php`)

Responsabilidades: buscar por email, crear usuario, validar existencia.

```php
<?php

require_once '../app/core/Model.php';

class Usuario extends Model {
    protected string $tabla = 'usuarios';
    
    public function buscarPorEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->tabla} WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado === false ? null : $resultado;
    }
    
    public function crear(string $nombre, string $email, string $passwordHash): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, email, password) VALUES (:nombre, :email, :password)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
        return $stmt->execute();
    }
    
    public function existeEmail(string $email): bool {
        $usuario = $this->buscarPorEmail($email);
        return $usuario !== null;
    }
}
```

**Notas:**
- Hereda de `Model` (tiene `$this->db` = conexión PDO)
- Usa prepared statements
- Retorna `null` si no encontrado (más claro que false)
- `LIMIT 1` optimiza búsqueda por email único

## 7. AuthController (`app/controllers/AuthController.php`)

Coordina login/register/logout.

### 1. index() → Mostrar formulario login

```php
<?php
class AuthController extends Controller {
    public function index(): void {
        // Si ya logueado, redirigir a equipos
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
            exit;
        }
        $this->view('auth/login', [
            'titulo' => 'Iniciar Sesión',
            'error'  => $_SESSION['error'] ?? null
        ]);
        unset($_SESSION['error']); // limpiar mensaje flash
    }
    // ...
}
```

**Mensaje flash:** Mostrar error una sola vez. Tras mostrar, lo eliminamos con `unset`.

### 2. login() → Procesar login (POST)

```php
public function login(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validaciones básicas
    if ($email === '' || $password === '') {
        $_SESSION['error'] = 'Email y contraseña son obligatorios';
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }
    
    $usuarioModel = new Usuario();
    $usuario = $usuarioModel->buscarPorEmail($email);
    
    if ($usuario === null) {
        $_SESSION['error'] = 'Credenciales incorrectas';
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }
    
    // Verificar contraseña
    if (!password_verify($password, $usuario['password'])) {
        $_SESSION['error'] = 'Credenciales incorrectas';
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }
    
    // Login exitoso → crear sesión
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['email'] = $usuario['email'];
    $_SESSION['logueado'] = true;
    
    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}
```

**Seguridad:** Mensaje genérico "Credenciales incorrectas" (no revelar si email existe). Mejor UX+seguridad básica.

**Verificación:** `password_verify()` compara texto plano con hash.

### 3. register() → Formulario registro

```php
public function register(): void {
    if (isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }
    $this->view('auth/register', [
        'titulo' => 'Registro de Usuario',
        'error'  => $_SESSION['error'] ?? null,
        'old'    => $_SESSION['old'] ?? [] // mantener datos válidos
    ]);
    unset($_SESSION['error'], $_SESSION['old']);
}
```

`old` mantiene valores ingresados si hay error (mejora UX).

### 4. guardarRegistro() → Procesar registro

```php
public function guardarRegistro(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . '?controller=Auth&action=register');
        exit;
    }
    
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirmar'] ?? '';
    
    // Validaciones
    if ($nombre === '' || $email === '' || $password === '' || $confirm === '') {
        $_SESSION['error'] = 'Todos los campos son obligatorios';
        $_SESSION['old'] = ['nombre'=>$nombre, 'email'=>$email];
        header('Location: ' . BASE_URL . '?controller=Auth&action=register');
        exit;
    }
    
    // Validar email básico
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Email no válido';
        $_SESSION['old'] = ['nombre'=>$nombre, 'email'=>$email];
        header('Location: ' . BASE_URL . '?controller=Auth&action=register');
        exit;
    }
    
    if ($password !== $confirm) {
        $_SESSION['error'] = 'Las contraseñas no coinciden';
        $_SESSION['old'] = ['nombre'=>$nombre, 'email'=>$email];
        header('Location: ' . BASE_URL . '?controller=Auth&action=register');
        exit;
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres';
        $_SESSION['old'] = ['nombre'=>$nombre, 'email'=>$email];
        header('Location: ' . BASE_URL . '?controller=Auth&action=register');
        exit;
    }
    
    $usuarioModel = new Usuario();
    if ($usuarioModel->existeEmail($email)) {
        $_SESSION['error'] = 'El email ya está registrado';
        $_SESSION['old'] = ['nombre'=>$nombre, 'email'=>$email];
        header('Location: ' . BASE_URL . '?controller=Auth&action=register');
        exit;
    }
    
    // Hashear y crear
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $usuarioModel->crear($nombre, $email, $hash);
    
    $_SESSION['exito'] = 'Registro exitoso. Ahora puedes iniciar sesión';
    header('Location: ' . BASE_URL . '?controller=Auth&action=index');
    exit;
}
```

**Validaciones servidor-side:** Siempre obligatorias. Nunca confiar solo en HTML `required`.

**filter_var EMAIL:** Validación básica correcta.

**strtolower email:** Normalizar para evitar duplicados por mayúsculas.

### 5. logout() → Cerrar sesión

```php
public function logout(): void {
    // Limpiar todas las variables de sesión
    $_SESSION = [];
    // Destruir cookie de sesión si existe
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

Limpieza completa: evita que datos persistan.

## 8. Proteger Rutas (Auth Middleware Simple)

Queremos evitar que usuarios NO logueados accedan a módulos (Equipos, Jugadores, Partidos, Estadísticas).

**Solución simple:** Función/helper o chequeo directo en controladores.

### Opción A: Método privado en Controller base

Añadir a `Controller.php`:

```php
<?php
class Controller {
    protected function view(string $vista, array $datos = []): void {
        extract($datos);
        require_once '../app/views/layouts/main.php';
    }
    
    protected function requiereLogin(): void {
        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['error'] = 'Debes iniciar sesión para acceder';
            header('Location: ' . BASE_URL . '?controller=Auth&action=index');
            exit;
        }
    }
}
```

### Usar en controladores protegidos

```php
<?php
class EquiposController extends Controller {
    public function index(): void {
        $this->requiereLogin();
        // ... resto
    }
    
    public function crear(): void {
        $this->requiereLogin();
        // ...
    }
    
    public function guardar(): void {
        $this->requiereLogin();
        // ...
    }
    // aplicar a todas acciones que requieran login
}
```

**¿Qué NO proteger?**
- `AuthController`: index, login, register, guardarRegistro, logout (públicos con lógica interna)

**¿Qué SÍ proteger?**
- Equipos, Jugadores, Partidos, Estadísticas → **todos sus métodos** (excepto quizás ninguno)

## 9. Vistas de Autenticación

### login.php (`views/auth/login.php`)

```php
<?php
// Mensajes
if (isset($_SESSION['exito'])): ?>
    <div class="alert success"><?= htmlspecialchars($_SESSION['exito']) ?></div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form action="<?= BASE_URL ?>?controller=Auth&action=login" method="POST">
    <div>
        <label>Email:</label>
        <input type="email" name="email" required autocomplete="email">
    </div>
    <div>
        <label>Contraseña:</label>
        <input type="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit">Ingresar</button>
    <p><a href="<?= BASE_URL ?>?controller=Auth&action=register">¿No tienes cuenta? Regístrate</a></p>
</form>
```

### register.php (`views/auth/register.php`)

```php
<?php if (isset($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form action="<?= BASE_URL ?>?controller=Auth&action=guardarRegistro" method="POST">
    <div>
        <label>Nombre completo:</label>
        <input type="text" name="nombre" value="<?= isset($old['nombre']) ? htmlspecialchars($old['nombre']) : '' ?>" required>
    </div>
    <div>
        <label>Email:</label>
        <input type="email" name="email" value="<?= isset($old['email']) ? htmlspecialchars($old['email']) : '' ?>" required>
    </div>
    <div>
        <label>Contraseña (mín. 6):</label>
        <input type="password" name="password" required minlength="6">
    </div>
    <div>
        <label>Confirmar contraseña:</label>
        <input type="password" name="confirmar" required minlength="6">
    </div>
    <button type="submit">Registrarse</button>
    <p><a href="<?= BASE_URL ?>?controller=Auth&action=index">¿Ya tienes cuenta? Inicia sesión</a></p>
</form>
```

**Atributos HTML5:** `required`, `minlength`, `type=email` dan validación básica cliente-side (complementario, NO sustituye servidor).

## 10. Mensajes Flash (éxito/error)

Usamos `$_SESSION` temporal:
- `$_SESSION['error']` → Rojo, mostrar una vez
- `$_SESSION['exito']` → Verde, mostrar una vez
- `$_SESSION['old']` → Reponer formulario

**Patrón:** Setear → Redirigir → Mostrar en vista → Unset

## 11. Seguridad Adicional (Buena Práctica)

| Medida | Aplicado | Explicación |
|---|---|---|
| **password_hash** | Sí | Nunca texto plano |
| **password_verify** | Sí | Comparación segura |
| **Prepared statements** | Sí | SQL Injection |
| **Validación servidor** | Sí | Todos campos validados |
| **Mensajes genéricos login** | Sí | No filtra existencia usuario |
| **trim()** | Sí | Limpia espacios |
| **strtolower(email)** | Sí | Normaliza |
| **filter_var email** | Sí | Valida formato |
| **CSRF** | Opcional (no requerido) | Podría añadirse, pero práctica no lo pide. Nos centramos en requerido. |
| **session_regenerate_id** | Recomendado tras login | Previene session fixation. Fácil añadir: `session_regenerate_id(true);` tras login OK. |

**Recomendado añadir:** tras login exitoso:
```php
session_regenerate_id(true); // genera nuevo ID sesión
```
Mayor seguridad, mínimo cambio.

## 12. Checklist Auth

- [ ] `session_start()` en index.php
- [ ] Tabla `usuarios` con email UNIQUE
- [ ] Modelo Usuario con buscarPorEmail, crear, existeEmail
- [ ] AuthController con index/login/register/guardarRegistro/logout
- [ ] password_hash en registro
- [ ] password_verify en login
- [ ] Validaciones servidor completas
- [ ] Mensajes flash (error/exito/old)
- [ ] Protección rutas con `requiereLogin()`
- [ ] Redirecciones correctas
- [ ] Vistas limpias con htmlspecialchars()

## 13. Resumen

**Sesiones** mantienen estado autenticado. **Hashing** protege contraseñas. **Validación** garantiza integridad. **Protección rutas** evita acceso no autorizado.

Flujo claro: **Registro → Hash → Login → Sesión → Acceso protegido → Logout**

Todo implementado con PHP vanilla, claro y didáctico. Ideal para entender fundamentos de auth.