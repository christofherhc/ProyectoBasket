# 2. PHP Estructurado - Explicación Amplia (Desde Cero)

## 1. ¿Qué es PHP?

**PHP** (Hypertext Preprocessor) es un lenguaje de programación del lado del servidor. Significa que el código PHP se ejecuta en el servidor web (Apache/Nginx), genera HTML y ese HTML es lo que recibe el navegador. Esto nos permite conectar con base de datos, gestionar sesiones, autenticar usuarios, etc.

En este proyecto usaremos **PHP estructurado** (procedural con organización modular) + programación orientada a objetos muy ligera (clases base). Esto es perfecto para **entender conceptos fundamentales** sin abstracciones complejas.

## 2. Sintaxis Básica de PHP

PHP se escribe entre etiquetas `<?php ... ?>`. En archivos que solo contienen PHP (recomendado), se omite `?>` al final para evitar espacios en blanco accidentales.

```php
<?php
// Esto es un comentario de una línea
/*
  Comentario multilínea
*/
echo "Hola Mundo";
```

**echo vs print:** Ambos imprimen. `echo` acepta múltiples argumentos y es ligeramente más rápido. Usaremos `echo` para HTML.

## 3. Variables, Tipos de Datos y Constantes

### Variables
Empiezan con `$`, sensibles a mayúsculas/minúsculas.

```php
<?php
$nombre = "Ana";
$edad = 20;
$precio = 19.99;
$activo = true;
$equipos = array("A", "B"); // o []
$nulo = null;
```

### Tipos de datos (PHP dinámico)
PHP es de tipado débil/dinámico (tipo se infiere). Tipos: `string`, `int`, `float`, `bool`, `array`, `object`, `null`, `resource`.

### Constantes
No cambian su valor. Definidas con `define()` o `const`.

```php
<?php
define('DB_NAME', 'liga_basquet'); // global
const PI = 3.14159; // dentro de clase/ámbito
```
**Buena práctica:** Usar mayúsculas para constantes. Las usaremos en `config.php`.

## 4. Operadores

### Aritméticos
`+ - * / % **` (potencia PHP 5.6+)

```php
$a = 10; $b = 3;
$suma = $a + $b; // 13
$mod = $a % $b;  // 1
```

### Comparación
`==` igual valor, `===` igual valor y tipo, `!=`, `!==`, `< > <= >=`

```php
5 == "5";  // true (valor)
5 === "5"; // false (tipo diferente)
```
**Recomendación:** Preferir `===` para evitar errores sutiles.

### Lógicos
`&&` AND, `||` OR, `!` NOT, `and/or` (menor precedencia)

```php
if ($activo && $edad >= 18) { ... }
```

### Concatenación
`.` concatena strings. `.=` concatena y asigna.

```php
$mensaje = "Hola " . $nombre . ", bienvenido";
$mensaje .= " a la liga";
```

## 5. Estructuras de Control

### Condicionales

**if/elseif/else**
```php
<?php
if ($edad < 12) {
    echo "Infantil";
} elseif ($edad < 18) {
    echo "Juvenil";
} else {
    echo "Adulto";
}
```

**if corto (ternario)**
```php
$estado = ($activo) ? 'Activo' : 'Inactivo';
```

**match (PHP 8+)** útil y legible:
```php
$resultado = match ($puntosLocal <=> $puntosVisit) {
    -1 => 'Gana Visitante',
     0 => 'Empate',
     1 => 'Gana Local',
};
```

### Bucles

**for**
```php
for ($i = 0; $i < 5; $i++) {
    echo $i;
}
```

**while / do-while**
```php
$i = 0;
while ($i < 3) {
    echo $i++; // 0,1,2
}
```

**foreach** (ideal arrays)
```php
$equipos = ["Lakers", "Bulls", "Celtics"];
foreach ($equipos as $equipo) {
    echo $equipo . "<br>";
}
// Con clave
foreach ($equipos as $id => $equipo) {
    echo "$id: $equipo<br>";
}
```

## 6. Arrays

PHP tiene arrays asociativos muy flexibles.

**Array indexado**
```php
$numeros = [1, 2, 3, 4];
echo $numeros[0]; // 1
```

**Array asociativo (clave => valor)**
```php
$equipo = [
    'id' => 1,
    'nombre' => 'Lakers',
    'ciudad' => 'Los Angeles'
];
echo $equipo['nombre']; // Lakers
```

**Funciones útiles**
```php
count($array);        // tamaño
array_push($arr, $v); // añadir
isset($arr['key']);   // existe clave
empty($var);          // vacío/null/0/false
in_array($v, $arr);   // buscar valor
array_key_exists($k,$arr);
```

## 7. Funciones

Funciones reutilizables, mejoran legibilidad.

```php
<?php
function saludar($nombre) {
    return "Hola, " . htmlspecialchars($nombre);
}
echo saludar("María");
```

**Parámetros por defecto**
```php
function obtenerSaludo($nombre = "Invitado") {
    return "Bienvenido, $nombre";
}
```

**Tipo de retorno (PHP 7+)**
```php
function sumar(int $a, int $b): int {
    return $a + $b;
}
```

**Ámbito (scope):** Variables dentro función locales. Usar `global` o pasar parámetros.

## 8. Includes y Require (Muy Importante)

Para organizar código y reutilizar archivos (plantillas, config, clases).

| Instrucción | Comportamiento | Uso recomendado |
|---|---|---|
| `include` | Incluye archivo. Si no existe, **emite advertencia** y continúa ejecución. | Vistas, partes opcionales. |
| `require` | Incluye archivo. Si no existe, **fatal error** (para ejecución). | Archivos críticos (config, core). |
| `include_once` / `require_once` | Incluye solo una vez (evita duplicados). | Ideal para includes anidados. |

**Ejemplo (nuestro caso):**
En `public/index.php` cargamos `app/config/config.php` y core. En vistas incluimos layout.

```php
<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Router.php';
```

**`__DIR__`**: Directorio actual del archivo (ruta absoluta relativa al archivo). Muy útil para evitar rutas erróneas.

## 9. Superglobales PHP (Usadas en Proyecto)

Variables globales predefinidas, accesibles desde cualquier ámbito.

| Superglobal | Uso | Ejemplo |
|---|---|---|
| `$_GET` | Parámetros URL | `?controller=Equipos&action=index` → `$_GET['controller']` |
| `$_POST` | Datos formularios (method POST) | Formularios crear/editar/login/register |
| `$_SESSION` | Datos de sesión usuario | Guardar `$_SESSION['usuario_id']`, `$_SESSION['nombre']` |
| `$_SERVER` | Info servidor/solicitud | `$_SERVER['REQUEST_URI']`, `$_SERVER['PHP_SELF']` |
| `$_FILES` | Archivos subidos | (no usaremos aquí) |
| `$_COOKIE` | Cookies | Opcional |
| `$_REQUEST` | GET+POST+COOKIE | Evitar usar (menos explícito) |

**Importante:** Siempre validar/sanitizar datos de `$_GET`/`$_POST` antes de usarlos.

## 10. Seguridad Básica en PHP (Crítica)

### Sanitización y escape de salida

Nunca mostrar datos crudos del usuario en HTML (XSS). Usar `htmlspecialchars()`.

```php
<?php
// INCORRECTO (riesgo XSS)
echo $nombreEquipo;

// CORRECTO
echo htmlspecialchars($nombreEquipo, ENT_QUOTES, 'UTF-8');
```

Usaremos esto en **vistas** siempre que mostremos datos de BD/formularios.

### Prepared Statements (PDO) - SQL Injection

Nunca concatenar variables en SQL:
```php
// PELIGROSO
$sql = "SELECT * FROM usuarios WHERE email = '$email'";
```

**Siempre prepared statements:**
```php
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email");
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->execute();
```
Esto se explica en detalle en 02-Fundamentos/03-PDO-y-MySQL.md.

### Hash de contraseñas
Nunca guardar contraseñas en texto plano. Usar `password_hash()` y `password_verify()`.

```php
$hash = password_hash($password, PASSWORD_DEFAULT); // registro
if (password_verify($password, $hash)) { ... }      // login
```

## 11. PHP Orientado a Objetos (Ligero, Solo lo Necesario)

Usaremos POO muy básica para Core (Database, Model, Controller). Clases + propiedades + métodos.

**Clase básica**
```php
<?php
class Equipo {
    private ?int $id;
    private string $nombre;

    public function getId(): ?int {
        return $this->id;
    }

    public function setNombre(string $nombre): void {
        $this->nombre = $nombre;
    }

    public function getNombre(): string {
        return $this->nombre;
    }
}
```

**Visibilidad:** `public`, `protected`, `private`. Usaremos `protected`/`private` para encapsular.

**Herencia (`extends`)**: Modelos heredarán de `Model` base, Controladores de `Controller` base. Esto evita duplicar código (DRY).

```php
<?php
require_once 'app/core/Model.php';

class Equipo extends Model {
    protected string $tabla = 'equipos';
    // ...
}
```

## 12. Buenas Prácticas PHP (Para Aprendizaje y Legibilidad)

1. **Un archivo = una responsabilidad.** Clase por archivo (Equipo.php para clase Equipo).
2. **Nombres claros.** Clases PascalCase (`EquipoController`), variables camelCase (`$nombreEquipo`), constantes UPPER_SNAKE_CASE (`DB_HOST`).
3. **Archivos PHP solo PHP.** Si solo PHP, omitir `?>` final.
4. **Usar `__DIR__` para rutas.** Evita problemas de rutas relativas.
5. **Preferir `require_once` para archivos core/config.** Garantiza carga única.
6. **Escapar siempre salida HTML.** `htmlspecialchars()` en vistas.
7. **Prepared statements SIEMPRE en BD.** No excepciones.
8. **Validar servidor-side.** Nunca confiar solo en frontend.
9. **Código legible > código "inteligente".** Prioritario para aprender/leer.
10. **Comentarios mínimos.** Código autoexplicativo (nombres). Solo comentar lógica no obvia.

## 13. PHP 8.x Recomendado

Usaremos características disponibles (match, typed returns, null coalescing `??`, etc.). Laragon/XAMPP con PHP 8.x funcionan bien.

**Null Coalescing (??)**
```php
$controller = $_GET['controller'] ?? 'Auth'; // valor por defecto si no existe
$action = $_GET['action'] ?? 'index';
```
Muy útil para routing (evita notices de undefined index).

**Null Coalescing Assignment (??=)**
```php
$data['mensaje'] ??= '';
```

## 14. Resumen Conceptual

PHP estructurado + organización modular es el punto intermedio ideal. Nos permite:
- Entender qué pasa bajo el capó (sin framework)
- Ver flujo completo petición→respuesta
- Aprender fundamentos sólidos
- Leer código ajeno fácilmente (sin magia oculta)

Estos conceptos son suficientes para desarrollar **completamente** todos los módulos de esta práctica, de forma clara y entendible.