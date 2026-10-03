# Documento Técnico: Gestión de Liga de Básquetbol (PHP MVC)

**Extensión:** 565 palabras (aprox.)  
**Autor:** Proyecto de Aprendizaje  
**Fecha:** 2026-10-03

## 1. Arquitectura

El sistema sigue una arquitectura **MVC ligero** (Modelo-Vista-Controlador) implementada en PHP vanilla sin frameworks. Se emplea el patrón **Front Controller** mediante `public/index.php`, que centraliza todas las peticiones. El enrutador (`app/core/Router.php`) interpreta parámetros GET (`controller`, `action`, `id`) y delega al controlador correspondiente.

Capas:
- **Modelo**: Acceso a datos y lógica de negocio (extends Model)
- **Vista**: Presentación HTML con layout compartido
- **Controlador**: Coordinación entre modelo y vista
- **Core**: Clases base reutilizables (Database, Model, Controller, Router)
- **Config**: Constantes de BD/URL

## 2. Componentes Clave

**Core/Database.php**: Conexión PDO singleton. Usa prepared statements, `PDO::ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `utf8mb4`.

**Core/Model.php**: Métodos base `obtenerTodos()`, `obtenerPorId()`, `eliminar()`, `obtenerUltimoId()`. Modelos heredan conexión `$db` y definen `$tabla`.

**Core/Controller.php**: `view()` (extract + layout), `requiereLogin()` para proteger rutas.

**Core/Router.php**: Parseo URL, validación, carga e instanciación dinámica de controladores, `call_user_func_array`.

**Modelos**: `Usuario`, `Equipo`, `Jugador`, `Partido` con consultas parametrizadas.

**Controladores**: `AuthController`, `EquiposController`, `JugadoresController`, `PartidosController`, `EstadisticasController`.

**Vistas**: Layout `main.php` + carpetas por módulo. Uso de `htmlspecialchars()` para escapar salida.

## 3. Funcionalidades Desarrolladas

**Autenticación**: Registro con validaciones (campos, email válido, contraseñas coinciden, mínimo 6, email único). Login con `password_verify()`, `session_regenerate_id()`, logout completo. Mensajes flash.

**Equipos (CRUD)**: Crear/editar/listar/eliminar con validación año fundación.

**Jugadores (CRUD + asignación)**: Asignación a equipo (select), JOIN para mostrar nombre equipo, validación número 0-99.

**Partidos (CRUD + lógica)**: Dos FKs a equipos, validación distinto equipo, marcador, cálculo resultado (Gana Local/Visitante/Empate), fecha datetime-local.

**Estadísticas**: Tabla posiciones (PJ/PG/PE/PP/GF/GC/DIF) ordenada por PG desc, DIF desc. Últimos 10 partidos. Cálculo por iteración sobre partidos (métricas básicas).

## 4. Código Fuente Relevante

**Auth hash** (`AuthController::guardarRegistro`): `$hash = password_hash($password, PASSWORD_DEFAULT);`  
**Login verify** (`AuthController::login`): `password_verify($password, $usuario['password'])`  
**JOIN jugadores** (`Jugador::obtenerTodos`): `LEFT JOIN equipos e ON e.id = j.equipo_id`  
**JOIN partidos** (`Partido::obtenerTodos`): JOIN doble a equipos (local/visitante)  
**Router dinámico** (`Router::executeAction`): `call_user_func_array([$obj, $action], [$params])`

## 5. Resolución de Problemas

**Formato datetime-local**: Convertir `DATETIME` (YYYY-MM-DD HH:MM:SS) a `YYYY-MM-DDTHH:MM` con `substr(...,0,16)`.  
**NULL en BD**: Diferenciar vacío vs NULL (fundación/número/posición).  
**JOINs dobles**: Alias el/ev para evitar ambigüedad.  
**Mensajes flash**: Usar `$_SESSION` + `unset()` tras mostrar (una sola vez).  
**Rutas relativas**: Usar `__DIR__` y `../` coherentes desde `public/`.

## 6. Decisiones de Diseño

- **Vanilla PHP**: Aprendizaje fundamentos sin magia.
- **MVC ligero**: Claridad, legible, fácil replicar.
- **GET routing**: Transparente, sin .htaccess.
- **PDO singleton**: Una conexión, prepared statements.
- **Sesiones auth**: Simple, correcto.
- **CSS puro**: Sin frameworks, entendible.
- **LEFT JOIN**: Mostrar aunque dato relacionado falte (robusto).
- **Validación servidor**: Obligatoria (nunca solo cliente).
- **Feature branches + commits atómicos**: Historial progresivo.

Todo cumple requerimientos: módulos, Git, doc en `/doc/practica_liga.md`, `.git` intacto.
