<?php

/**
 * Funciones de apoyo compartidas por controladores y vistas.
 *
 * Se separan en un archivo aparte (en vez de un método estático) porque son
 * funciones puras que no dependen de ninguna clase: así cualquier vista puede
 * llamarlas sin necesitar un objeto.
 */

/**
 * Lee un valor de $_POST garantindo que sea texto.
 *
 * Sin esta comprobación, un formulario manipulado que envíe "nombre[]=x"
 * haría que trim() recibiera un array y PHP 8 lanzaría un TypeError
 * (pantalla en blanco). Ver registro de errores #19.
 *
 * @param string $clave
 * @param string $defecto
 * @return string
 */
function post(string $clave, string $defecto = ''): string {
    if (!isset($_POST[$clave]) || !is_string($_POST[$clave])) {
        return $defecto;
    }
    return $_POST[$clave];
}

/**
 * Lee un valor de $_GET garantizando que sea texto.
 * Misma razón que post(): evita TypeError con "controller[]=x".
 *
 * @param string $clave
 * @param string $defecto
 * @return string
 */
function get(string $clave, string $defecto = ''): string {
    if (!isset($_GET[$clave]) || !is_string($_GET[$clave])) {
        return $defecto;
    }
    return $_GET[$clave];
}

/**
 * Escapa texto para insertarlo dentro de un atributo o texto HTML.
 *
 * @param mixed $valor
 * @return string
 */
function e($valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// =============================================
// CSRF (Cross-Site Request Forgery)
// =============================================

/**
 * Devuelve el token CSRF de la sesión, creándolo si no existe.
 *
 * @return string
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto con el token, para insertarlo dentro de un <form>.
 *
 * @return string
 */
function csrfCampo(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Comprueba el token recibido usando hash_equals (evita ataques de tiempo).
 *
 * @param mixed $recibido
 * @return bool
 */
function csrfValido($recibido): bool {
    if (!is_string($recibido) || $recibido === '') {
        return false;
    }
    $guardado = $_SESSION['csrf_token'] ?? '';
    return is_string($guardado) && $guardado !== '' && hash_equals($guardado, $recibido);
}

/**
 * Corta la petición si el token CSRF no es válido.
 *
 * @return void
 */
function exigirCsrf(): void {
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        // 403 y no 419: Apache no conoce 419 y lo convierte en 500.
        http_response_code(403);
        exit('Error de seguridad: la sesión expiró o el formulario no es válido. Recarga la página.');
    }
}

// =============================================
// Peticiones y redirecciones
// =============================================

/**
 * Exige que la petición sea POST y, opcionalmente, un token CSRF válido.
 *
 * @param string $destino Consulta a la que redirigir si no es POST.
 * @param bool   $csrf   Validar también el token CSRF.
 * @return void
 */
function exigirPost(string $destino, bool $csrf = true): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        redir($destino);
    }
    if ($csrf) {
        exigirCsrf();
    }
}

/**
 * Redirige y termina la ejecución.
 *
 * $destino se escribe como "Controlador&action=accion" y esta función
 * construye la URL completa usando BASE_URL.
 *
 * @param string $destino
 * @return void
 */
function redir(string $destino): void {
    header('Location: ' . BASE_URL . '?' . ltrim($destino, '?&'));
    exit;
}

// =============================================
// Mensajes flash
// =============================================

/**
 * Guarda un mensaje de error para mostrarlo en la siguiente pantalla.
 *
 * @param string $mensaje
 * @return void
 */
function flashError(string $mensaje): void {
    unset($_SESSION['exito']);
    $_SESSION['error'] = $mensaje;
}

/**
 * Guarda un mensaje de éxito para mostrarlo en la siguiente pantalla.
 *
 * @param string $mensaje
 * @return void
 */
function flashExito(string $mensaje): void {
    unset($_SESSION['error']);
    $_SESSION['exito'] = $mensaje;
}

// =============================================
// Validación
// =============================================

/**
 * Indica si un texto cabe en la columna VARCHAR de la base de datos.
 *
 * Sin esta comprobación, un texto demasiado largo dispara el error 1406 de
 * MySQL y el usuario ve una pantalla en blanco. Ver registro de errores #20.
 *
 * @param string $valor
 * @param int    $maximo
 * @return bool
 */
function cabeEnColumna(string $valor, int $maximo): bool {
    return mb_strlen($valor, 'UTF-8') <= $maximo;
}

/**
 * Normaliza una fecha de `datetime-local` al formato que espera MySQL.
 *
 * Acepta "AAAA-MM-DDTHH:MM" y "AAAA-MM-DD HH:MM:SS".
 * Devuelve null si la fecha no existe de verdad (por ejemplo, 31/02/2026).
 *
 * @param string $valor
 * @return string|null
 */
function fechaValida(string $valor): ?string {
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    $formatos = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formatos as $formato) {
        $fecha = DateTime::createFromFormat($formato, $valor);
        $errores = DateTime::getLastErrors();
        // PHP 8.2 devuelve false en getLastErrors() cuando no hay errores.
        $hayErrores = is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0);
        if ($fecha !== false && !$hayErrores) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }
    return null;
}

/**
 * Traduce un error de PDO a un mensaje que el usuario pueda entender.
 *
 * @param PDOException $e
 * @return string
 */
function errorAmigable(PDOException $e): string {
    $codigo = (int)($e->errorInfo[1] ?? 0);

    $mensajes = [
        1451 => 'No se puede eliminar: el registro tiene datos asociados (jugadores o partidos).',
        1452 => 'El registro seleccionado no existe.',
        1406 => 'Algún texto es más largo de lo permitido.',
        1292 => 'La fecha indicada no es válida.',
        1264 => 'Un número está fuera de rango.',
        1062 => 'Ya existe un registro con esos datos.',
        1048 => 'Falta un campo obligatorio.',
    ];

    return $mensajes[$codigo] ?? 'No se pudo completar la operación en la base de datos.';
}