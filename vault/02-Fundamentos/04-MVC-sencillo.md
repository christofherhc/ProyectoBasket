# 4. MVC Sencillo - Explicación Amplia

## 1. ¿Qué es MVC?

**MVC** significa **Model - View - Controller** (Modelo - Vista - Controlador). Es un patrón de arquitectura de software que separa una aplicación en **tres capas** con responsabilidades bien definidas.

El objetivo principal: **separar la lógica, los datos y la presentación**. Esto hace el código:
- Más legible
- Más fácil de entender (ideal para leer código ajeno)
- Más mantenible
- Más fácil de organizar y probar

En este proyecto usaremos **MVC muy ligero y sencillo** (vanilla PHP). Sin frameworks, para que **veas todo claramente, sin magia**.

## 2. Las Tres Capas Explicadas

### 1. Model (Modelo) → Datos + Lógica de Negocio

El **Modelo** representa los datos y la lógica relacionada con ellos. Se comunica con la base de datos.

**Responsabilidades:**
- Obtener, guardar, actualizar, eliminar datos (CRUD)
- Validaciones a nivel de datos/lógica de negocio
- Relaciones entre entidades
- Consultas SQL (usando PDO)

**Ejemplos en proyecto:** `Usuario.php`, `Equipo.php`, `Jugador.php`, `Partido.php`

**Idea clave:** El Modelo responde "¿Qué datos necesito y cómo los obtengo/guardo?"

### 2. View (Vista) → Presentación (HTML)

La **Vista** es lo que ve el usuario: HTML, CSS. **Solo muestra datos**, no debe tener lógica compleja de BD.

**Responsabilidades:**
- Renderizar HTML
- Recibir datos del Controlador y mostrarlos
- Formularios, tablas, enlaces
- Presentación/UX básica

**Regla de oro:** Las Vistas **NO hacen consultas a BD**. Reciben datos preparados.

**Ejemplos:** `views/equipos/index.php`, `views/auth/login.php`, `views/layouts/main.php`

**Idea clave:** La Vista responde "¿Cómo muestro esto al usuario?"

### 3. Controller (Controlador) → Coordinador

El **Controlador** actúa como intermediario. Recibe peticiones, pide datos al Modelo, prepara datos y los pasa a la Vista.

**Responsabilidades:**
- Recibir petición del usuario (`$_GET`, `$_POST`)
- Validar entrada básica
- Llamar a Modelos para obtener/guardar datos
- Preparar datos para la Vista
- Decidir qué Vista mostrar
- Redirecciones, mensajes de éxito/error

**Ejemplos:** `EquiposController.php`, `AuthController.php`, `PartidosController.php`

**Idea clave:** El Controlador responde "¿Qué debe pasar cuando el usuario hace esto?"

## 3. Flujo MVC Paso a Paso (Concreto)

Vamos a seguir un ejemplo real: **Listar equipos** (`/equipos/index`)

1. **Usuario hace petición**: Navega a `http://localhost/ProyectoBasket/public/?controller=Equipos&action=index`
2. **Front Controller** (`public/index.php`): Recibe URL, instancia Router
3. **Router** (`app/core/Router.php`): Extrae `controller = Equipos`, `action = index`. Carga `EquiposController.php`
4. **EquiposController → index()**: Método `index()` se ejecuta
5. **Controller pide datos**: Llama a `Equipo::obtenerTodos()` (Modelo)
6. **Modelo Equipo**: Ejecuta consulta SQL `SELECT * FROM equipos ORDER BY nombre`. Devuelve array `$equipos`
7. **Controller pasa datos a Vista**: `$this->view('equipos/index', ['equipos' => $equipos])`
8. **Vista equipos/index.php**: Recibe `$equipos`, recorre con `foreach`, genera tabla HTML
9. **Layout main.php**: Envuelve vista (header, nav, footer)
10. **Respuesta al navegador**: HTML completo generado por PHP → Navegador lo muestra

**Este flujo es lineal, claro y fácil de seguir.**

## 4. Ventajas de MVC para Aprendizaje

| Ventaja | Explicación |
|---|---|
| **Separación clara** | Sabrás exactamente dónde buscar (BD→Modelos, lógica→Controllers, HTML→Views) |
| **Código legible** | Cada archivo tiene una única responsabilidad. Ideal para leer código ajeno. |
| **Reutilizable** | Modelos reutilizables, layout compartido entre vistas. |
| **Escalable ordenado** | Añadir módulos sin desordenar. |
| **Fácil de depurar** | Flujos predecibles. Siguientes capas paso a paso. |
| **Enfoque "leer para aprender"** | Sigues petición completa de principio a fin → entiendes arquitectura global. |

## 5. MVC vs Código Spaghetti (Ejemplo Conceptual)

**Sin MVC (confuso):**
```php
<?php
// todo mezclado: SQL + lógica + HTML
include 'conexion.php';
$sql = "SELECT * FROM equipos";
$result = mysqli_query($conn, $sql);
?>
<html><body><table>
<?php while($row = mysqli_fetch_assoc($result)): ?>
  <tr><td><?= $row['nombre'] ?></td></tr>
<?php endwhile; ?>
</table></body></html>
```

Funciona, pero **mezcla responsabilidades** (difícil mantener, leer a gran escala).

**Con MVC (organizado):**
- Modelo: solo SQL/BD
- Controller: solo coordinación
- Vista: solo HTML

Separación clara → **muchísimo más fácil de entender y estudiar**.

## 6. Nuestra Implementación MVC (Ligera)

Usaremos **variación simple** (no puro estricto, sino práctico para aprender):

### Core base

**`app/core/Model.php`** → Clase base con métodos comunes (CRUD reutilizable)
```php
<?php
class Model {
    protected PDO $db;
    protected string $tabla;
    // métodos findAll, findById, create, update, delete...
}
```
Todos modelos heredan: `class Equipo extends Model { ... }`

**`app/core/Controller.php`** → Clase base con método `view()`
```php
<?php
class Controller {
    protected function view(string $vista, array $datos = []): void {
        extract($datos); // convierte claves a variables
        require_once '../app/views/layouts/main.php';
    }
}
```
Controladores heredan: `class EquiposController extends Controller { ... }`

**`app/core/Router.php`** → Enrutamiento simple (GET params)
**`app/core/Database.php`** → Conexión PDO (singleton)

### Rutas (enrutamiento simple)

Usamos **parámetros GET** (sencillo, sin mod_rewrite complejo):

```
?controller=Equipos&action=index  → Listar
?controller=Equipos&action=crear  → Formulario crear
?controller=Equipos&action=guardar → Procesar POST
?controller=Equipos&action=editar&id=1
?controller=Equipos&action=eliminar&id=1
```

¿Por qué GET params? **Transparentes, fáciles de entender para principiantes.** No requiere `.htaccess` complejo. Más legible para aprendizaje.

## 7. Convenciones de Nombres (MVC)

| Elemento | Convención | Ejemplo |
|---|---|---|
| **Controlador** | PascalCase + `Controller` | `EquiposController`, `AuthController` |
| **Modelo** | Singular PascalCase | `Equipo`, `Jugador`, `Partido`, `Usuario` |
| **Vista carpeta** | plural (minúsculas) | `equipos/`, `jugadores/`, `partidos/` |
| **Archivo vista** | minúsculas con guión o nombre acción | `index.php`, `crear.php`, `editar.php` |
| **Acción (método)** | camelCase | `index()`, `crear()`, `guardar()`, `editar()`, `actualizar()`, `eliminar()` |
| **Tabla BD** | plural minúsculas | `equipos`, `jugadores`, `partidos`, `usuarios` |

Coherencia facilita mucho la lectura.

## 8. Principios MVC que Seguiremos (Prácticos)

1. **Thin Controllers (Controladores delgados)** → Controlador coordina, NO hace SQL. SQL va a Modelo.
2. **Fat Models (Modelos con lógica)** → Lógica de datos/negocio en Modelos cuando corresponde.
3. **Dumb Views (Vistas simples)** → Solo presentación. Ciclos, condicionales simples OK. Lógica compleja NO.
4. **Un método por acción** → Cada acción HTTP/lógica clara tiene su método.
5. **Nunca mezclar capas** → Vista no llama BD. Modelo no imprime HTML. Controlador no escribe SQL directamente (lo delega).
6. **DRY (Don't Repeat Yourself)** → Clases base evitan duplicación.

## 9. Paso de Datos a Vistas (Detalle)

Controlador usa `extract($datos)`. Esto convierte array asociativo en variables.

**Controlador:**
```php
public function index(): void {
    $equipos = (new Equipo())->obtenerTodos();
    $this->view('equipos/index', [
        'equipos' => $equipos,
        'titulo'  => 'Lista de Equipos'
    ]);
}
```

**Vista (recibe variables):**
```php
<h1><?= htmlspecialchars($titulo) ?></h1>
<?php foreach ($equipos as $e): ?>
    <p><?= htmlspecialchars($e['nombre']) ?></p>
<?php endforeach; ?>
```

`extract()` crea `$equipos` y `$titulo` localmente en vista. Sencillo, claro para aprender.

## 10. Layout Base (Template)

Compartimos estructura común (header, nav, footer). Evita repetir HTML.

**`views/layouts/main.php`:**
```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= isset($titulo) ? htmlspecialchars($titulo) : 'Liga de Básquet' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/styles.css">
</head>
<body>
    <header>...</header>
    <nav>...</nav>
    <main>
        <?php require_once "../app/views/$vista.php"; ?>
    </main>
    <footer>...</footer>
</body>
</html>
```

Controlador pasa `$vista = 'equipos/index'` y layout hace `require` interno.

## 11. Errores Comunes MVC (Evitar)

| Error | Explicación | Solución |
|---|---|---|
| **Controlador hace SQL** | Rompe separación capas | SQL va a Modelo. Controller llama método Modelo. |
| **Vista hace consultas BD** | Mezcla presentación+datos | Pasar datos desde Controller. |
| **Modelo imprime HTML** | Rompe separación | Retornar datos (arrays), nunca `echo`. |
| **Lógica compleja en vista** | Vista difícil de leer | Preparar datos en Controller/Modelo. |
| **Nombres inconsistentes** | Confuso seguir flujo | Seguir convenciones definidas. |
| **No usar clases base** | Duplica código | Heredar de Model/Controller. |

## 12. ¿Por qué MVC Ligero (Vanilla)?

- **Sin capas ocultas** → Ves cada línea, entiendes flujo completo.
- **Sin dependencias** → Solo PHP puro. Más fácil aprender.
- **Menos abstracción** → Ideal para leer código ajeno y entender fundamentos.
- **Cumple requisitos** → Sencillo, entendible, profesional.

## 13. Resumen Mental

**MVC es un mapa de responsabilidades:**
- **M** = ¿Dónde viven los datos? (BD + reglas)
- **V** = ¿Qué ve el usuario? (HTML)
- **C** = ¿Quién organiza el tráfico? (decisiones)

Flujo unidireccional claro: **Petición → C → M → C → V → Respuesta**

Este patrón es **clave** para tu objetivo: "aprender a programar leyendo código ajeno y entendiendo cómo funciona". MVC hace ese objetivo muchísimo más fácil.