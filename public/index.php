<?php

// =============================================
// Hardening de la sesión
// Estas opciones deben fijarse ANTES de session_start().
// =============================================

// No aceptar un ID de sesión enviado por el cliente que no exista en el servidor.
ini_set('session.use_strict_mode', '1');
// La cookie de sesión no puede leerla JavaScript.
ini_set('session.cookie_httponly', '1');
// La cookie no se envía en peticiones que vienen de otros sitios (CSRF).
ini_set('session.cookie_samesite', 'Lax');

$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,          // cookie de sesión (se borra al cerrar el navegador)
    'path'     => '/',
    'domain'   => '',
    'secure'   => $esHttps,   // solo viaja por HTTPS si la web lo usa
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Cargar configuración
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/helpers.php';

// Cargar clases core
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Router.php';

/**
 * Traduce cualquier excepción no controlada en una respuesta comprensible.
 *
 * Antes de este bloque, un DELETE que violaba una clave foránea terminaba en
 * una pantalla en blanco (error 1451 sin capturar). Ver registro de errores #16.
 *
 * @param Throwable $e
 * @return void
 */
function manejarExcepcion(Throwable $e): void {
    error_log('[ProyectoBasket] ' . get_class($e) . ': ' . $e->getMessage());

    if ($e instanceof PDOException) {
        $detalle = errorAmigable($e);
        $codigo  = (int)($e->errorInfo[1] ?? 0);
    } else {
        $detalle = 'Se produjo un error inesperado en la aplicación.';
        $codigo  = 500;
    }

    if (headers_sent()) {
        return;
    }

    // 409: conflicto con los datos existentes. 500: error del servidor.
    http_response_code(in_array($codigo, [1451, 1452, 1062], true) ? 409 : 500);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<title>No se pudo completar</title></head><body>'
        . '<h1>No se pudo completar la operación</h1><p>' . e($detalle) . '</p>'
        . '<p><a href="' . e(BASE_URL) . '">Volver al inicio</a></p>'
        . '</body></html>';
    exit;
}

set_exception_handler('manejarExcepcion');

// Inicializar enrutador (maneja toda la petición)
new Router();