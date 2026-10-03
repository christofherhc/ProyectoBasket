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
            header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
            exit;
        }
        
        $this->view('auth/login', [
            'titulo' => 'Iniciar Sesión'
        ]);
    }

    /**
     * Procesa login
     */
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Auth&action=index');
            exit;
        }
        
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email y contraseña son obligatorios';
            header('Location: ' . BASE_URL . '?controller=Auth&action=index');
            exit;
        }
        
        // Cargar modelo usuario
        require_once '../app/models/Usuario.php';
        $usuarioModel = new Usuario();
        
        $usuario = $usuarioModel->buscarPorEmail($email);
        
        if ($usuario === null || !password_verify($password, $usuario['password'])) {
            $_SESSION['error'] = 'Credenciales incorrectas';
            header('Location: ' . BASE_URL . '?controller=Auth&action=index');
            exit;
        }
        
        // Login exitoso
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['logueado'] = true;
        
        $_SESSION['exito'] = 'Sesión iniciada correctamente';
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }

    /**
     * Muestra formulario de registro
     */
    public function register(): void {
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
            exit;
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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Auth&action=register');
            exit;
        }
        
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmar = $_POST['confirmar'] ?? '';
        
        $_SESSION['old'] = [
            'nombre' => $nombre,
            'email' => $email
        ];
        
        if (empty($nombre) || empty($email) || empty($password) || empty($confirmar)) {
            $_SESSION['error'] = 'Todos los campos son obligatorios';
            header('Location: ' . BASE_URL . '?controller=Auth&action=register');
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Email no válido';
            header('Location: ' . BASE_URL . '?controller=Auth&action=register');
            exit;
        }
        
        if ($password !== $confirmar) {
            $_SESSION['error'] = 'Las contraseñas no coinciden';
            header('Location: ' . BASE_URL . '?controller=Auth&action=register');
            exit;
        }
        
        if (strlen($password) < 6) {
            $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres';
            header('Location: ' . BASE_URL . '?controller=Auth&action=register');
            exit;
        }
        
        require_once '../app/models/Usuario.php';
        $usuarioModel = new Usuario();
        
        if ($usuarioModel->existeEmail($email)) {
            $_SESSION['error'] = 'El email ya está registrado';
            header('Location: ' . BASE_URL . '?controller=Auth&action=register');
            exit;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $usuarioModel->crear($nombre, $email, $hash);
        
        unset($_SESSION['old']);
        $_SESSION['exito'] = 'Registro exitoso. Ahora puedes iniciar sesión';
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }

    /**
     * Cierra sesión
     */
    public function logout(): void {
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
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }
}
