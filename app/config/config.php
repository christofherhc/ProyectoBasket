<?php

// Configuración de Base de Datos (MySQL/MariaDB - XAMPP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'liga_basquet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Zona horaria para fechas
date_default_timezone_set('America/Lima');

// =============================================
// LÍMITES DE VALIDACIÓN
// Estos valores DEBEN coincidir con database/schema.sql.
// Si los cambias aquí, cámbialos también en el esquema.
// =============================================
define('MAX_NOMBRE', 100);       // equipos.nombre, jugadores.nombre, usuarios.nombre
define('MAX_CIUDAD', 100);       // equipos.ciudad
define('MAX_CAMISETA', 99);      // jugadores.numero
define('MAX_PUNTOS', 999);       // partidos.puntos_local / puntos_visitante

// Posiciones admitidas. La columna es VARCHAR (no ENUM) a propósito,
// así que la lista blanca vive aquí y se valida en el controlador.
define('POSICIONES', ['Base', 'Escolta', 'Alero', 'Ala-Pívot', 'Pívot']);

// =============================================
// URL BASE DE LA APLICACIÓN
//
// IMPORTANTE: aquí solo se calcula la RUTA, nunca el dominio.
// Si se usara $_SERVER['HTTP_HOST'] (que es el header Host que envía el
// cliente), un atacante podría mandar "Host: evil.com" y provocar que las
// redirecciones apunten a un sitio ajeno. Ver registro de errores #18.
// =============================================
if (PHP_SAPI === 'cli') {
    define('BASE_URL', '/ProyectoBasket/public/');
} else {
    $script   = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $carpeta  = str_replace('\\', '/', dirname($script));
    // Se eliminan los caracteres que no pueden aparecer en una ruta segura.
    $carpeta  = preg_replace('~[^A-Za-z0-9_\-/]~', '', $carpeta) ?? '';
    $carpeta  = trim($carpeta, '/');
    $carpeta  = $carpeta === '' ? '' : '/' . $carpeta;
    define('BASE_URL', $carpeta . '/');
}