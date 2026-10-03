# Día 02 - Core MVC + Front Controller

**Fecha:** 2026-10-03  
**Objetivo:** Crear configuración, clases core (Database/Model/Controller/Router), punto de entrada public/index.php, layout, estilos CSS, auth base y documentación ampliada.  
**Temas:** PDO singleton, MVC base, Front Controller, routing GET, sesiones, layout.  
**Rama:** main (progresivo)

## 1. Contexto
Construimos la base arquitectónica: todo lo necesario para levantar app con routing funcional.

## 2. Archivos creados
- app/config/config.php
- app/core/Database.php (singleton PDO)
- app/core/Model.php (CRUD base)
- app/core/Controller.php (view + requiereLogin)
- app/core/Router.php (parse URL, carga controlador, ejecuta acción)
- public/index.php (front controller + session_start)
- public/css/styles.css (estilos base legibles)
- app/views/layouts/main.php (layout con nav, mensajes flash)
- app/controllers/AuthController.php
- app/models/Usuario.php
- app/views/auth/login.php, register.php
- vault/05-Procedimientos/Dia-02-Core-MVC.md, vault/06-Codigo-Explicado/* ampliados, vault/07-Diario-de-Aprendizaje/Diario-Dia-02.md

## 3. Conceptos clave
- Singleton evita múltiples conexiones
- extract() pasa datos a vistas
- call_user_func_array pasa params
- password_hash/verify
- session_regenerate_id tras login
- htmlspecialchars en vistas

## 4. Pruebas
Estructura cargable. Rutas Auth funcionan conceptualmente.

## 5. Commit
feat: añade core MVC, config, front controller y auth base
