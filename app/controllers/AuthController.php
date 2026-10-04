<?php

/**
 * AuthController - Controlador de Autenticación
 *
 * Gestiona registro, inicio de sesión y cierre de sesión
 */
class AuthController extends Controller {
    /**
     * Muestra formulario de login
     */
    public function index(): void {
        // Si ya está logueado, redirigir
        if (isset($_SESSION['usuario_id'])) {
            redir('controller=Equipos&action=index');
        }

        $this->view('auth/login', [
            'titulo' => 'Iniciar Sesión'
        ]);
    }

    /**
     * Procesa login
     */
    public function login(): void {
        exigirPost('controller=Auth&action=index');

        $email    = strtolower(trim(post('email')));
        $password = post('password');

        // Se compara con === '' en vez de usar empty() porque empty('0') es true
        // y la contraseña "0" sería rechazada sin motivo.
        if ($email === '' || $password === '') {
            flashError('Email y contraseña son obligatorios');
            redir('controller=Auth&action=index');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashError('El email no tiene un formato válido');
            redir('controller=Auth&action=index');
        }

        // Bloqueo temporal tras 5 intentos fallidos en 15 minutos.
        if (loginBloqueado()) {
            flashError('Demasiados intentos fallidos. Espera 15 minutos antes de volver a intentarlo.');
            redir('controller=Auth&action=index');
        }

        // Cargar modelo usuario
        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario();

        $usuario = $usuarioModel->buscarPorEmail($email);

        // Se verifica siempre la contraseña, aunque el usuario no exista.
        // Si no, el tiempo de respuesta revelaría qué emails existen.
        $hash = $usuario['password'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
        if (!password_verify($password, $hash) || $usuario === null) {
            registrarIntentoFallido();
            flashError('Credenciales incorrectas');
            redir('controller=Auth&action=index');
        }

        limpiarIntentosFallidos();

        // Login exitoso
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['logueado'] = true;

        flashExito('Sesión iniciada correctamente');
        redir('controller=Equipos&action=index');
    }

    /**
     * Muestra formulario de registro
     */
    public function register(): void {
        if (isset($_SESSION['usuario_id'])) {
            redir('controller=Equipos&action=index');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $this->view('auth/register', [
            'titulo' => 'Registrarse',
            'old' => $old
        ]);
    }

    /**
     * Procesa registro
     */
    public function guardarRegistro(): void {
        exigirPost('controller=Auth&action=register');

        $nombre   = trim(post('nombre'));
        $email    = strtolower(trim(post('email')));
        $password = post('password');
        $confirmar = post('confirmar');

        $_SESSION['old'] = [
            'nombre' => $nombre,
            'email'  => $email,
        ];

        $volver = 'controller=Auth&action=register';

        if ($nombre === '' || $email === '' || $password === '' || $confirmar === '') {
            flashError('Todos los campos son obligatorios');
            redir($volver);
        }

        if (!cabeEnColumna($nombre, MAX_NOMBRE)) {
            flashError('El nombre no puede superar los ' . MAX_NOMBRE . ' caracteres');
            redir($volver);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashError('Email no válido');
            redir($volver);
        }

        if ($password !== $confirmar) {
            flashError('Las contraseñas no coinciden');
            redir($volver);
        }

        if (strlen($password) < 6) {
            flashError('La contraseña debe tener al menos 6 caracteres');
            redir($volver);
        }

        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario();

        if ($usuarioModel->existeEmail($email)) {
            flashError('El email ya está registrado');
            redir($volver);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $usuarioModel->crear($nombre, $email, $hash);

        unset($_SESSION['old']);
        flashExito('Registro exitoso. Ahora puedes iniciar sesión');
        redir('controller=Auth&action=index');
    }

    /**
     * Cierra sesión
     */
    public function logout(): void {
        // Antes se podía cerrar la sesión con un simple enlace GET, lo que
        // permitía que un sitio ajeno cerrara la sesión del usuario.
        exigirPost('controller=Auth&action=index');

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        redir('controller=Auth&action=index');
    }
}

/**
 * Suma un intento fallido y bloquea la cuenta temporalmente si son muchos.
 *
 * Sin esto, un atacante podía probar miles de contraseñas por segundo.
 *
 * @return void
 */
function registrarIntentoFallido(): void {
    $ahora = time();
    $intentos = $_SESSION['login_fallidos'] ?? [];

    // Se descartan los intentos de hace más de 15 minutos.
    $intentos = array_filter($intentos, static fn($t) => ($ahora - (int)$t) < 900);

    $intentos[] = $ahora;
    $_SESSION['login_fallidos'] = $intentos;
}

/**
 * Indica si la cuenta está bloqueada por intentos fallidos.
 *
 * @return bool
 */
function loginBloqueado(): bool {
    $ahora = time();
    $intentos = array_filter(
        $_SESSION['login_fallidos'] ?? [],
        static fn($t) => ($ahora - (int)$t) < 900
    );
    return count($intentos) >= 5;
}

/**
 * Limpia el contador tras un login correcto.
 *
 * @return void
 */
function limpiarIntentosFallidos(): void {
    unset($_SESSION['login_fallidos']);
}