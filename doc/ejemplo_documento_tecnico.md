# Documento Técnico: Aplicación Web para la Gestión de una Liga de Básquetbol

> **Modalidad:** Individual  
> **Proyecto:** Práctica Calificada 1  
> **Repositorio:** GitHub  
> **Autor:** Christofher HUrtado Castillo

## Índice

1. [Introducción](#1-introducción)
2. [Arquitectura del Sistema](#2-arquitectura-del-sistema)
3. [Componentes Clave](#3-componentes-clave)
4. [Funcionalidades Desarrolladas](#4-funcionalidades-desarrolladas)
5. [Código Fuente: Explicación y Fragmentos Relevantes](#5-código-fuente-explicación-y-fragmentos-relevantes)
6. [Resolución de Problemas](#6-resolución-de-problemas)
7. [Decisiones de Diseño](#7-decisiones-de-diseño)

## 1. Introducción

El presente documento describe el desarrollo de una aplicación web para la gestión integral de una liga de básquetbol. El objetivo principal fue implementar los módulos solicitados (autenticación, equipos, jugadores, partidos y estadísticas), aplicando buenas prácticas de desarrollo de software y control de versiones con Git.

## 2. Arquitectura del Sistema

La aplicación fue desarrollada siguiendo el patrón de arquitectura **Modelo-Vista-Controlador (MVC)**, implementado en **PHP vanilla** (sin frameworks). Para centralizar todas las peticiones HTTP, se utilizó el patrón **Front Controller** a través del archivo `public/index.php`.

**Capas de la arquitectura:**

- **Modelo (app/models/):** Encargado del acceso a datos, lógica de negocio y relaciones entre entidades. Hereda de la clase base `Model`.
- **Vista (app/views/):** Responsable de la presentación (HTML, CSS). Utiliza una plantilla base (`layouts/main.php`) para reutilizar estructura común.
- **Controlador (app/controllers/):** Coordina el flujo entre Modelo y Vista, gestiona peticiones ($_GET/$_POST) y sesiones.
- **Core (app/core/):** Contiene clases base reutilizables: `Database.php`, `Model.php`, `Controller.php` y `Router.php`.
- **Configuración (app/config/):** Define constantes globales (BD, URL base).
- **Punto de entrada (public/):** Única carpeta accesible vía web (`index.php`).

**Flujo de peticiones:** El navegador realiza una solicitud a `public/?controller=...&action=...`, `index.php` inicia sesión y carga `Router`, el cual parsea la URL, instancia el controlador correspondiente y ejecuta la acción solicitada. El controlador solicita datos al modelo, prepara la información y la envía a la vista para su renderizado.

## 3. Componentes Clave

| Componente | Descripción Técnica |
|---|---|
| **Database.php** | Clase Singleton para conexión PDO. Configura `PDO::ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `ATTR_EMULATE_PREPARES=false`, charset `utf8mb4`. Garantiza una única conexión por petición. |
| **Model.php** | Clase base con CRUD genérico: `obtenerTodos()`, `obtenerPorId()`, `eliminar()`, `obtenerUltimoId()`. Provee `$db` (PDO) y define `$tabla` en cada modelo hijo. |
| **Controller.php** | Clase base con método `view($vista, $datos)` (usa `extract()` para pasar variables al layout). Incluye `requiereLogin()` para proteger rutas. |
| **Router.php** | Enrutamiento por parámetros GET. Normaliza nombres (controller/action), carga dinámicamente controladores, verifica existencia y ejecuta método vía `call_user_func_array()`. |
| **Usuario.php** | Gestiona autenticación: `buscarPorEmail()`, `existeEmail()`, `crear()` (almacena hash de contraseña). |
| **Equipo.php** | CRUD de equipos. Maneja `fundacion` (YEAR) permitiendo NULL cuando no se especifica. Ordena listados por nombre. |
| **Jugador.php** | CRUD con relación 1:N a equipos. Usa `LEFT JOIN equipos` para mostrar `nombre_equipo` en listados/detalles. |
| **Partido.php** | Gestiona partidos entre equipos (2 FKs: local/visitante). `LEFT JOIN` doble con alias (`el`, `ev`) para obtener nombres de ambos equipos. Ordena por fecha descendente. |
| **AuthController.php** | Registro/login/logout. Valida datos servidor-side, usa `password_hash()`/`password_verify()`, `session_regenerate_id(true)`, mensajes flash y recuperación de datos (`old`). |
| **Equipos/Jugadores/Partidos/EstadisticasController.php** | Implementan CRUD completo, validaciones de negocio y coordinación entre modelos/vistas. |

## 4. Funcionalidades Desarrolladas

### 4.1 Autenticación
- **Registro:** Validación obligatoria, formato email, coincidencia contraseñas, mínimo 6 caracteres, unicidad de email. Contraseñas se almacenan con `password_hash()` (PASSWORD_DEFAULT).
- **Login:** Verificación con `password_verify()`, regeneración de ID de sesión, variables de sesión (`usuario_id`, `nombre`, `logueado`).
- **Logout:** Limpieza completa de `$_SESSION` y destrucción de cookie de sesión.

### 4.2 Equipos
CRUD completo (Crear, Leer, Actualizar, Eliminar). Validaciones: nombre/ciudad obligatorios, año de fundación entre 1800 y año actual.

### 4.3 Jugadores
CRUD con **asignación a equipo** (select dinámico). Validaciones: nombre/equipo obligatorios, número camiseta 0–99. Listado muestra equipo mediante JOIN.

### 4.4 Partidos
Registro y gestión de encuentros. Requisitos: equipos distintos (validación negocio), marcador no negativo, fecha/hora obligatoria. Muestra **resultado** (Gana Local / Gana Visitante / Empate).

### 4.5 Estadísticas
Tabla de posiciones con: PJ, PG, PE, PP, GF, GC, DIF, ordenada por PG descendente y DIF descendente. Muestra últimos 10 partidos registrados. Cálculo por procesamiento de partidos (sin tabla auxiliar).

## 5. Código Fuente: Explicación y Fragmentos Relevantes

### 5.1 Conexión segura con PDO (Database.php)
```php
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
];
self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
```
**Explicación:** Se utiliza `emulate_prepares=false` para aprovechar prepared statements nativos del motor (mayor seguridad). `FETCH_ASSOC` simplifica arrays en vistas.

### 5.2 Hash y verificación de contraseñas (AuthController)
```php
// Registro
$hash = password_hash($password, PASSWORD_DEFAULT);
$usuarioModel->crear($nombre, $email, $hash);

// Login
if ($usuario === null || !password_verify($password, $usuario['password'])) {
    // credenciales incorrectas (mensaje genérico)
}
session_regenerate_id(true);
```
**Explicación:** Nunca se almacenan contraseñas en texto plano. Mensaje genérico protege contra enumeración de usuarios.

### 5.3 JOIN para mostrar nombres (Partido.php)
```php
$sql = "SELECT p.*, 
               el.nombre AS nombre_local, 
               ev.nombre AS nombre_visitante
        FROM partidos p
        LEFT JOIN equipos el ON el.id = p.equipo_local_id
        LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
        ORDER BY p.fecha DESC";
```
**Explicación:** Se emplean alias `el`/`ev` (equipos local/visitante) para evitar ambigüedad de columnas. `LEFT JOIN` asegura integridad al mostrar datos.

### 5.4 Routing dinámico (Router.php)
```php
call_user_func_array(
    [$this->controllerObjeto, $this->actionNombre],
    [$this->params]
);
```
**Explicación:** Permite pasar parámetros (`id`) al método del controlador de forma flexible y segura.

### 5.5 Protección de rutas (Controller.php)
```php
protected function requiereLogin(): void {
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['error'] = 'Debes iniciar sesión para acceder';
        header('Location: ' . BASE_URL . '?controller=Auth&action=index');
        exit;
    }
}
```
**Explicación:** Todas las acciones de módulos protegidos verifican autenticación antes de ejecutar lógica.

### 5.6 Escape de salida en vistas
```php
<?= htmlspecialchars($equipo['nombre']) ?>
```
**Explicación:** Previene ataques XSS mostrando datos escapados.

## 6. Resolución de Problemas

| Dificultad | Causa | Solución Aplicada |
|---|---|---|
| **Formato datetime-local en formularios** | Campo HTML espera `YYYY-MM-DDTHH:MM` y MySQL guarda `YYYY-MM-DD HH:MM:SS` | En edición se usó `substr($fecha, 0, 16)` para convertir el formato correctamente. |
| **Ambigüedad en JOIN doble (partidos–equipos)** | Misma tabla referenciada 2 veces sin alias | Se asignaron alias `el` (local) y `ev` (visitante) con sus respectivos nombres. |
| **Diferenciación vacío vs NULL** | Campos opcionales (fundación, número, posición) podían guardarse como `0` o cadena vacía | Se validó cadenas vacías (`''`) y se vinculó `PDO::PARAM_NULL` cuando correspondía. |
| **Reenvío de formulario al refrescar (POST)** | Envío duplicado tras refresh | Tras operaciones POST exitosas se aplicó **redirección (header + exit)** siguiendo patrón PRG (Post-Redirect-Get). |
| **Mensajes flash persistentes** | `$_SESSION['error/exito']` permanecían tras mostrar | Se eliminaron con `unset()` inmediatamente después de mostrarlos en el layout. |
| **Session fixation** | Posible reutilización de ID de sesión | Tras login exitoso se ejecutó `session_regenerate_id(true)`. |

## 7. Decisiones de Diseño

| Decisión | Justificación |
|---|---|
| **PHP Vanilla (sin frameworks)** | Permite comprender fundamentos (routing, PDO, sesiones) sin abstracciones ocultas. Ideal para aprendizaje y lectura de código. |
| **MVC Ligero** | Separación clara de responsabilidades, código legible, facilita mantenimiento y comprensión. |
| **Enrutamiento por GET (`?controller=&action=`)** | Transparente, explícito, no requiere `.htaccess` ni configuración extra (funciona en XAMPP/Laragon). Excelente para didáctica. |
| **PDO + Prepared Statements** | Protege frente a SQL Injection. Uso de `named parameters` y `emulate_prepares=false` para mayor seguridad. |
| **Singleton (Database)** | Garantiza una única conexión por petición, evita sobrecarga y centraliza acceso a BD. |
| **Layout base único** | Reutilización de header/nav/footer/mensajes flash (DRY). Cambios globales centralizados. |
| **Validación servidor-side obligatoria** | Nunca se confía en cliente. HTML5 complementa, PHP valida y protege lógica de negocio. |
| **Mensajes genéricos en login** | No revela si email existe o no ("Credenciales incorrectas"), aporta seguridad básica. |
| **LEFT JOIN en listados** | Más robusto ante posibles datos inconsistentes, evita pérdida de información en visualización. |
| **Controladores delgados / Modelos con lógica BD** | Respeta separación MVC: SQL y relaciones en Modelo, coordinación en Controlador, presentación en Vista. |
| **Git: commits atómicos + ramas por funcionalidad** | Cumple requisito de avance progresivo. Historial claro, trazable y profesional. |
