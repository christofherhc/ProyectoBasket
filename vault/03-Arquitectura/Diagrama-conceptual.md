# Diagrama Conceptual - Flujo de la Aplicación

## 1. Objetivo

Explicar **visualmente con texto** cómo fluye una petición HTTP a través de toda la arquitectura MVC + Routing. El objetivo es que puedas seguir el camino **paso a paso** y entender qué hace cada capa.

## 2. Visión General del Sistema

```
Navegador (Cliente)
    │
    │ Petición HTTP (GET/POST)
    ↓
┌─────────────────────────────────────────────────────────────┐
│                    public/index.php                          │
│                  (FRONT CONTROLLER - ÚNICO PUNTO)            │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓ carga config + core + inicia sesión
┌─────────────────────────────────────────────────────────────┐
│                        app/core/Router.php                   │
│                Parsea URL → controller + action + params     │
└─────────────────────────────────────────────────────────────┘
                            │
                            ↓ valida y carga
┌─────────────────────────────────────────────────────────────┐
│                    app/controllers/*Controller.php           │
│                (COORDINADOR) - Recibe POST/GET/SESSION      │
└─────────────────────────────────────────────────────────────┘
                │                        │
                │ (solicita datos)      │ (prepara/redirecciona)
                ↓                        ↓
        ┌─────────────────┐      ┌─────────────────────────┐
        │  app/models/*.php│      │  app/core/Controller.php│
        │  (MODELO)       │      │  método view()          │
        └─────────────────┘      └─────────────────────────┘
                │                        │
                │ consulta               │ carga vista + layout
                ↓                        ↓
        ┌─────────────────┐      ┌─────────────────────────┐
        │  app/core/       │      │  app/views/**/*.php     │
        │  Database.php    │      │  (VISTA - HTML)         │
        │  (PDO)           │      └─────────────────────────┘
                │                        │
                ↓                        ↓
            ┌─────────────┐      ┌─────────────────────────┐
            │  MySQL/MariaDB│      │  HTML generado → vuelve  │
            └─────────────┘      │  al navegador            │
                                └─────────────────────────┘
```

## 3. Flujo Detallado Paso a Paso (Ejemplo: Listar Equipos)

Usaremos ejemplo real: `?controller=Equipos&action=index`

### PASO 1: Petición del Navegador
- URL: `http://localhost/ProyectoBasket/public/?controller=Equipos&action=index`
- Método: `GET`
- Navegador envía petición al servidor Apache

### PASO 2: Front Controller (`public/index.php`)
- Apache apunta a `public/` → ejecuta `index.php`
- `session_start()` → Reanuda/inicia sesión
- `require_once` config.php → carga constantes
- `require_once` core: Database, Model, Controller, Router
- `new Router()` → Lanza enrutamiento

### PASO 3: Router (`app/core/Router.php`)
- `parseUrl()`: Lee `$_GET`
  - controller = "Equipos" → `ucfirst(strtolower) = "Equipos"`
  - action = "index" → `"index"`
  - params = `[]` (sin id)
- `loadController()`:
  - nombreClase = `EquiposController`
  - ruta = `../../app/controllers/EquiposController.php`
  - `require_once` + `new EquiposController()`
- `executeAction()`:
  - Verifica `method_exists(EquiposController, 'index')`
  - Llama `call_user_func_array([$obj, 'index'], [[]])`

### PASO 4: Controlador (`app/controllers/EquiposController.php`)

```php
public function index(): void {
    $this->requiereLogin(); // protección (si logueado)
    $equipoModel = new Equipo();
    $equipos = $equipoModel->obtenerTodos(); // pide datos
    $this->view('equipos/index', [
        'equipos' => $equipos,
        'titulo'  => 'Listado de Equipos'
    ]);
}
```

- Ejecuta `requiereLogin()` (chequea `$_SESSION['usuario_id']`)
- Crea instancia `Equipo` (modelo)
- Llama `$equipoModel->obtenerTodos()`
- Recibe array `$equipos`
- Llama `$this->view()` pasando datos

### PASO 5: Modelo (`app/models/Equipo.php`)

Extiende `Model`. Hereda `$this->db` (PDO).

```php
public function obtenerTodos(): array {
    $sql = "SELECT * FROM {$this->tabla} ORDER BY nombre ASC";
    $stmt = $this->db->prepare($sql); // aunque sin params, prepare() OK
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

- Usa `$this->tabla = 'equipos'`
- Prepara consulta SQL con PDO
- `execute()` → ejecuta
- `fetchAll()` → retorna array asociativo con todos equipos
- Retorna a controlador

### PASO 6: Base de Datos (`app/core/Database.php`)

- `getConexion()` retorna instancia PDO única
- Conexión con `mysql:host=...;dbname=...;charset=utf8mb4`
- Opciones: ERRMODE_EXCEPTION, FETCH_ASSOC, emulate_prepares=false
- Ejecuta consulta vía PDO prepared statement

### PASO 7: Volver a Controlador

Recibe `$equipos` (array). Llama `$this->view('equipos/index', [...])`.

### PASO 8: Controller Base (`app/core/Controller.php`)

```php
protected function view(string $vista, array $datos = []): void {
    extract($datos); // crea $equipos, $titulo
    require_once '../app/views/layouts/main.php';
}
```

- `extract($datos)` convierte claves a variables PHP
- Requiere `layouts/main.php` (plantilla base)

### PASO 9: Layout + Vista (`app/views/layouts/main.php` + `equipos/index.php`)

**main.php** estructura HTML:
```php
<!DOCTYPE html>
<html lang="es">
<head>...</head>
<body>
  <header>...</header>
  <nav>... enlaces con BASE_URL ...</nav>
  <main>
    <?php require_once "../app/views/$vista.php"; ?>
  </main>
  <footer>...</footer>
</body>
</html>
```

**equipos/index.php** se incluye dentro `<main>`:
```php
<h1><?= htmlspecialchars($titulo) ?></h1>
<table>
<?php foreach ($equipos as $e): ?>
  <tr><td><?= htmlspecialchars($e['nombre']) ?></td></tr>
<?php endforeach; ?>
</table>
```

PHP genera HTML completo.

### PASO 10: Respuesta al Navegador

Servidor envía **HTML puro** al navegador. Navegador renderiza tabla de equipos.

**Fin del ciclo petición→respuesta MVC.**

## 4. Flujo para Formulario POST (Crear Equipo)

Ejemplo: Enviar formulario crear equipo.

### PASO 1: Usuario envía formulario
`method="POST" action="?controller=Equipos&action=guardar"`

Datos: `nombre=Lakers&ciudad=Los Angeles`

### PASO 2: Router → EquiposController@guardar
Mismo front controller + router. Action `guardar()`.

### PASO 3: Controlador procesa POST

```php
public function guardar(): void {
    $this->requiereLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: '.BASE_URL.'?controller=Equipos&action=crear');
        exit;
    }
    $nombre = trim($_POST['nombre'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    // validaciones...
    $equipoModel = new Equipo();
    $equipoModel->crear($nombre, $ciudad);
    header('Location: '.BASE_URL.'?controller=Equipos&action=index');
    exit;
}
```

**Importante:** Tras POST exitoso → **REDIRECCIÓN** (PRG pattern) para evitar reenvío al refrescar.

### PASO 4: Modelo crea registro

```php
public function crear(string $nombre, string $ciudad, ?int $fundacion = null): bool {
    $sql = "INSERT INTO equipos (nombre, ciudad) VALUES (:nombre, :ciudad)";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
    return $stmt->execute();
}
```

INSERT con prepared statement. Retorna true/false.

### PASO 5: Redirección a index

Header Location → navegador hace **nueva petición GET** a `index`. Muestra listado actualizado.

## 5. Flujo con Parámetros (Editar/Eliminar)

URL: `?controller=Equipos&action=editar&id=5`

Router parsea → params = `['id'=>5]`
`executeAction()` llama `editar(['id'=>5])`

Controlador:
```php
public function editar(array $params): void {
    $id = (int)($params['id'] ?? 0);
    $equipo = (new Equipo())->obtenerPorId($id);
    $this->view('equipos/editar', ['equipo'=>$equipo]);
}
```

Modelo:
```php
public function obtenerPorId(int $id): ?array {
    $sql = "SELECT * FROM equipos WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $res = $stmt->fetch();
    return $res === false ? null : $res;
}
```

## 6. Mapa de Archivos por Capa (Referencia Rápida)

| Capa | Archivos | Función |
|---|---|---|
| **Entrada** | `public/index.php` | Front Controller |
| **Enrutamiento** | `app/core/Router.php` | Mapeo URL→Controller/Action |
| **Base MVC** | `app/core/Controller.php`, `app/core/Model.php` | Reutilización, DRY |
| **Datos** | `app/core/Database.php` | Conexión PDO |
| **Coordinación** | `app/controllers/*.php` | Lógica petición/respuesta |
| **Negocio/Datos** | `app/models/*.php` | Acceso BD + reglas |
| **Presentación** | `app/views/layouts/main.php`, `app/views/*/*.php` | HTML |
| **Config** | `app/config/config.php` | Constantes |
| **BD Scripts** | `database/*.sql` | Esquema + datos |

## 7. Conceptos Clave Visualizables

- **Front Controller**: Un solo archivo recibe TODO
- **Separación estricta**: Cada capa solo habla con capa adyacente (Controller→Model, Controller→View)
- **Model NO conoce View**: Correcto (desacoplado)
- **View NO conoce Model**: Correcto (sólo recibe datos)
- **Controller centraliza**: Orquesta flujo
- **Router desacopla URL**: URLs flexibles
- **Layout reutilizable**: Evita duplicación HTML

## 8. Diagrama Simplificado "Mental"

```
URL GET/POST → [index.php] → [Router]
  → instancia [Controller] → llama [Model]
    → [Database] → [MySQL]
  ← devuelve datos ←
[Controller] → carga [View]+[Layout] → genera HTML → Navegador
```

## 9. ¿Por qué este Flujo es Ideal para Aprender?

1. **Lineal y predecible** → Fácil seguir con depuración mental
2. **Sin magia** → Ves cada salto entre archivos
3. **Responsabilidades claras** → Sabes dónde mirar al buscar bug
4. **Patrón real** → Base para entender frameworks después
5. **Reforza lectura código ajeno** → Sigues ruta completa petición→respuesta

## 10. Resumen

**Todo empieza en `public/index.php`. Todo termina con HTML en navegador.**

El Router traduce URL. El Controller decide. El Model trae datos. La View muestra. El Layout unifica.

Este flujo es **transparente, didáctico y sólido**. Justo lo que buscamos para aprender entendiendo, no memorizando.