# Database (explicado)

**Archivo real:** `app/core/Database.php`
**Rol en la arquitectura:** abre (una sola vez) la conexión PDO que usan todos los modelos.

## 1. El problema que resuelve

Cada modelo necesita una conexión a la base. Si cada uno abriera la suya:

- MySQL tendría muchas conexiones simultáneas abiertas (y hay un límite).
- Abrir y cerrar conexiones repetidamente es lento.

Además, la configuración (host, usuario, contraseña) no debería estar escrita dentro de cada modelo.

Esta clase resuelve las dos cosas: **una única conexión compartida** (patrón *Singleton*) y **la configuración centralizada** en `config.php`.

## 2. El patrón Singleton, en tres piezas

Un Singleton es una clase de la que **solo existe una instancia**, y se pide siempre de la misma forma: `Database::getConexion()`.

### Pieza 1: la propiedad estática privada

```php
private static ?PDO $instancia = null;
```

- `static` → la variable pertenece a la **clase**, no a un objeto. Es una sola para todos.
- `private` → nadie más que la propia clase la toca.
- `?PDO` → puede contener un objeto PDO **o** `null` (el símbolo `?` es "anulable", igual que `?array`).

### Pieza 2: la comprobación

```php
public static function getConexion(): PDO {
    if (self::$instancia === null) {
        // ... crear la conexión y guardarla ...
    }
    return self::$instancia;
}
```

Es un "lazy initialization" (inicialización perezosa): la conexión **no se crea al cargar el archivo**, sino la primera vez que alguien la pide. La primera llamada entra en el `if` y conecta; todas las siguientes encuentran `$instancia` ya llena y devuelven la misma.

`self::$instancia === null` se compara con `===` (identidad estricta) para no confundirse con un valor "falsy".

### Pieza 3: los tres bloqueos

```php
private function __construct() { }          // nadie puede crear: new Database()

private function __clone() { }              // nadie puede clonar: $d->__clone()

public function __wakeup(): void {          // nadie puede deserializar
    throw new Exception("No se puede deserializar una instancia Singleton");
}
```

Una puerta trasera basta para romper un Singleton. Estas tres la cierran:

| Puerta | Cómo se cerraba sin el bloqueo |
|---|---|
| `new Database()` | Crear un segundo objeto con su propia conexión |
| `clone $db` | Copiar el objeto, y con él la referencia a la conexión |
| `unserialize()` | Reconstruir el objeto saltándose el constructor |

`__construct` privado es el cierre principal; los otros dos son defensa completa.

**Nota:** `__wakeup` está declarado `public` a propósito. PHP exige que los métodos mágicos sean **públicos**, salvo `__construct`, `__destruct` y `__clone`; si se declaran `protected` o `private`, PHP emite un aviso. Un método público que lanza una excepción es la forma canónica de bloquear la deserialización.

## 3. El DSN: la dirección de la base

```php
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
```

DSN = *Data Source Name*. Es una cadena con formato fijo que le dice a PDO **a qué motor** conectarse, **dónde** y **con qué codificación**.

```
mysql:host=localhost;dbname=liga_basquet;charset=utf8mb4
 └─┬─┘ └─┬──────┘ └────────┬─────────┘ └───┬───┘
  │  │    │                 │              └─ utf8mb4 (acentos y emoji)
  │  │    │                 └─ nombre de la BASE DE DATOS
  │  │    └─ host del servidor MySQL
  │  └─ motor: mysql (funciona igual con MariaDB)
  └─ protocolo
```

Dos cosas que se olvidan a menudo:

- **`dbname` es obligatorio.** Sin él obtienes `No database selected` aunque la base exista. Este error está documentado en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 1.
- **`charset=utf8mb4` debe ir en el DSN**, no solo en el `SET NAMES`. Ahí es donde realmente se negocia la codificación con el servidor. Sin esto, los acentos ("Pívot") y los emoji (🏀) se rompen.

Los valores vienen de `config.php` como constantes:

```php
// app/config/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'liga_basquet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

**Por qué constantes y no variables.** Una constante no se puede reasignar por accidente, y se puede usar en cualquier ámbito sin `global`. Para configuración que no cambia durante la ejecución, es lo adecuado.

## 4. Las cuatro opciones del PDO

```php
$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
```

### `ERRMODE_EXCEPTION`: que los errores se lansan, no se silencian

PDO tiene tres modos de error. El valor por defecto de PHP es `SILENT`, y es una trampa:

```php
// Con ERRMODE_SILENT (por defecto), esto NO da error:
$stmt = $db->prepare("SELECT * FROM tabla_que_no_existe");
$stmt->execute();
$filas = $stmt->fetchAll();
// $filas = []   y no sabrías por qué
```

Con `ERRMODE_EXCEPTION`, la consulta que falla lanza una `PDOException` con el mensaje real, y se detiene el código en esa línea. En vez de un síntoma lejos de la causa, tienes el error exacto donde pasa.

### `FETCH_ASSOC`: filas como arrays asociativos

Sin esta opción, `fetch()` devuelve arrays con **números**:

```php
// Sin FETCH_ASSOC (modo por defecto FETCH_BOTH):
$fila[0] => 1, $fila['id'] => 1, $fila[1] => 'Los Lakers', $fila['nombre'] => 'Los Lakers'

// Con FETCH_ASSOC:
$fila['id'] => 1, $fila['nombre'] => 'Los Lakers'
```

En las vistas se usa `$equipo['nombre']`, que es mucho más legible que `$equipo[2]`. Se fija **una vez** en la conexión para que todos los modelos lo hereden.

### `EMULATE_PREPARES => false`: seguridad real

Este es el más importante de los cuatro. Por defecto, PDO **simula** los prepared statements: reemplaza los `?` por el valor **en el cliente**, con comillas y escapes, y envía una consulta ya "cosida" a MySQL.

Con la emulación activada, PDO tiene que decidir cómo entrecomillar el valor. Esa decisión es la que ha dado lugar a casos reales de inyección en el mundo real.

Con `false`, PDO usa el protocolo nativo: envía la consulta con los placeholders intactos y los valores **separados**. MySQL recibe la estructura y los datos por canales distintos, y nunca interpreta el contenido como SQL.

**Consecuencia práctica:** los valores se pasan en `execute([...])` o con `bindValue()`, y **ambos estilos de placeholder siguen funcionando**: los posicionales `?` y los con nombre `:id`. Los 4 modelos de este proyecto usan `:id` con la emulación desactivada, y funcionan.

⚠️ **Ventaja poco conocida de los prepares nativos:** un mismo placeholder con nombre **se puede repetir** dentro de la misma consulta. Con la emulación activada, no:

```php
// Con EMULATE_PREPARES = false  →  OK (prepares nativos)
$sql = "SELECT * FROM jugadores WHERE equipo_id = :id OR equipo_id = :id";

// Con EMULATE_PREPARES = true   →  Invalid parameter number
```

⚠️ **Y un límite real de los prepares nativos:** el mismo nombre debe enviarse **siempre con el mismo tipo**. No puedes enlazar `:id` como entero en un sitio y como cadena en otro.

### `PERSISTENT => false`

Una conexión persistente se guarda en un "pool" y se reutiliza entre peticiones. Suena eficiente, pero en un entorno como XAMPP puede causar problemas de base de datos bloqueada o estados sucios. Con cuatro usuarios de clase, `false` es la opción honesta.

## 5. El manejo del error

```php
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
```

**Qué está bien aquí:** la conexión se intenta dentro de un `try`, así que un fallo de MySQL no produce un error críptico de PHP, sino un mensaje que dice qué pasó.

**⚠️ Y lo que habría que mejorar en producción:**

1. **Muestra el mensaje real** (`getMessage()`). En un entorno real eso puede filtrar el nombre de la base, el usuario o la ruta interna. En producción se registra en el log del servidor y se muestra algo genérico: "Error de conexión. Inténtalo más tarde."
2. **`die()` mata el proceso.** Para una web sería mejor registrar el error y mostrar una página amigable.

**Qué lo hace aceptable en este proyecto:** es un trabajo local, con datos de prueba y sin información sensible. Y para aprender, ver el mensaje exacto del error es una ventaja enorme.

## 6. Cómo lo usa el resto del código

La cadena completa es:

```
Equipo extends Model
    ↓  (hereda el constructor)
Model::__construct()
    ↓
$this->db = Database::getConexion();   ← el Singleton, una sola conexión
    ↓
$this->db->prepare(...)  /  $this->db->lastInsertId()
```

En `Model.php`:
```php
protected PDO $db;

public function __construct() {
    $this->db = Database::getConexion();
}
```

Como `getConexion()` es `static`, se llama con `Database::` y no con `$this->`. Y como es Singleton, los 4 modelos que se instancian en una misma petición **comparten la misma conexión**: en el listado de equipos, el constructor se ejecuta una vez, pero cualquier modelo que se cree después reutiliza la conexión ya abierta.

## 7. Cómo probarlo por tu cuenta

Abre `public/index.php`, añade esto **al principio** (después de los `require`) y ábrelo en el navegador:

```php
$db = Database::getConexion();
echo "Conexión OK: " . $db->getAttribute(PDO::ATTR_SERVER_VERSION);
```

Y para comprobar que es Singleton de verdad:

```php
$a = Database::getConexion();
$b = Database::getConexion();
var_dump($a === $b);   // true  ← son el mismo objeto
```

Si devuelve `false`, el Singleton está roto y estás abriendo una conexión por modelo.

## 8. Errores frecuentes con esta clase

| Síntoma | Causa | Solución |
|---|---|---|
| `No database selected` | Falta `dbname` en el DSN | Añadirlo |
| `SQLSTATE[HY000] [1045]` | Usuario o contraseña incorrectos | Revisar `config.php` |
| `Connection refused` | MySQL apagado | Iniciar en el panel de XAMPP |
| Acentos como `PÃ-vot` | Charset mal configurado | `charset=utf8mb4` en el DSN |
| No se ve ningún error SQL | `ERRMODE_SILENT` | `ERRMODE_EXCEPTION` |
| `Invalid parameter number` | Placeholders con nombre maltipados, o reutilizar un `:id` con dos tipos distintos | Revisar los `bindValue()` y no mezclar `?` con `:` en la misma llamada |
| Error "Too many connections" | Sin Singleton, un connection por modelo | Este Singleton lo evita |

---

**Archivos relacionados:** `Model-explicado.md` · `../../../app/config/config.php` · `../../02-Fundamentos/03-PDO-y-MySQL.md`

**Siguiente paso →** `Model-explicado.md` (el CRUD genérico que heredan los 4 modelos).