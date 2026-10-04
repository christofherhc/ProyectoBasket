<?php

// Configuración de Base de Datos (MySQL/MariaDB - XAMPP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'liga_basquet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL base de la aplicación (detectada automáticamente)
// Funciona sin importar el nombre de la carpeta en htdocs.
if (PHP_SAPI === 'cli') {
    // Valor por defecto al ejecutar por consola
    define('BASE_URL', 'http://localhost/ProyectoBasket/public/');
} else {
    $esHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $esquema  = $esHttps ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $carpeta  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $esquema . '://' . $host . $carpeta . '/');
}

// Zona horaria para fechas
date_default_timezone_set('America/Lima');
