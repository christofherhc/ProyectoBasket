<?php

// Iniciar sesión (debe ir antes de cualquier salida)
session_start();

// Cargar configuración
require_once __DIR__ . '/../app/config/config.php';

// Cargar clases core
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Router.php';

// Inicializar enrutador (maneja toda la petición)
new Router();
