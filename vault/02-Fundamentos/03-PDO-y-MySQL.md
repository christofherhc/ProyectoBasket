# 3. PDO y MySQL - Conexión Segura a Base de Datos

## 1. ¿Qué es MySQL/MariaDB?

**MySQL** y **MariaDB** son sistemas de gestión de bases de datos relacionales (RDBMS). Usan SQL (Structured Query Language) para crear, leer, actualizar y eliminar datos (CRUD).

**Diferencia práctica:** MariaDB es fork compatible con MySQL. Ambos funcionan igual para lo que haremos.

## 2. ¿Qué es PDO?

**PDO** (PHP Data Objects) es una extensión de PHP que provee una interfaz uniforme para acceder a bases de datos. Soporta múltiples drivers (MySQL, PostgreSQL, SQLite...).

**Ventajas clave:**
- **Prepared Statements** → Previene SQL Injection
- **Manejo de errores robusto** → Excepciones claras
- **Orientado a objetos** → Se integra bien con clases
- **Portabilidad** → Fácil cambiar motor si fuera necesario
- **Seguridad** → Parametrización obligatoria con prepared statements

## 3. ¿Por qué usar PDO y no mysqli?

| Aspecto | PDO | mysqli |
|---|---|---|
| Soporte BD | Múltiples (12+) | Solo MySQL/MariaDB |
| Estilo | OOP + Procedural | Procedural + OOP |
| Prepared Statements | Soporta named params `:nombre` (más legible) | Soporta `?` principalmente |
| Fetch styles | Muchos (`FETCH_ASSOC`, `FETCH_OBJ`, `FETCH_CLASS`) | Más limitado |
| Ideal para aprender | Mejor para entender patrón genérico | Más específico |

Para este proyecto **PDO con named parameters** es más claro y legible.

## 4. Modelo de Datos de la Liga

Necesitamos 4 entidades principales:

| Tabla | Propósito | Relaciones |
|---|---|---|
| **usuarios** | Autenticación (login/registro) | Independiente |
| **equipos** | Equipos participantes | 1:N con jugadores |
| **jugadores** | Jugadores por equipo | N:1 con equipos |
| **partidos** | Encuentros entre equipos | N:1 con equipos (local y visitante) → 2 FKs |

**Relaciones clave:**
- `jugadores.equipo_id` → FK a `equipos.id` (UN jugador pertenece a UN equipo)
- `partidos.equipo_local_id` → FK a `equipos.id`
- `partidos.equipo_visitante_id` → FK a `equipos.id`

**NOTA:** Un partido NO puede tener mismo equipo como local y visitante (validación lógica).

## 5. DSN (Data Source Name)

DSN indica a PDO cómo conectar. Para MySQL:

```text
mysql:host=HOST;dbname=NOMBRE_DB;charset=utf8mb4
```

Parámetros importantes:
- `charset=utf8mb4` → Soporta caracteres Unicode (acentos, ñ)
- `host` → `localhost` en XAMPP/Laragon

## 6. Conexión PDO Paso a Paso

### Configuración (`app/config/config.php`)

```php
<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'liga_basquet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL base (para enlaces)
define('BASE_URL', 'http://localhost/ProyectoBasket/public/');
```

**Notas:**
- En XAMPP contraseña suele ser vacía. En otros entornos puede variar.
- `BASE_URL` útil para construir enlaces coherentes (opcional pero práctico).

### Clase Database (`app/core/Database.php`)

Creamos clase singleton para **una sola conexión** durante la petición.

**¿Por qué singleton?** Evita abrir múltiples conexiones innecesarias.

```php
<?php

class Database {
    private static ?PDO $instancia = null;

    private function __construct() {}

    public static function getConexion(): PDO {
        if (self::$instancia === null) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $opciones = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanzar excepciones
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Array asociativo por defecto
                    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usar prepared reales
                    PDO::ATTR_PERSISTENT         => false,                  // No conexión persistente
                ];
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            } catch (PDOException $e) {
                // No se muestra el mensaje de PDO: incluye el DSN (host, nombre
                // de la base) y detalles internos. Se registra y se responde
                // con una página 500 genérica.
                error_log('[ProyectoBasket] Error de conexión: ' . $e->getMessage());
                http_response_code(500);
                echo '<h1>Servicio no disponible</h1>'
                    . '<p>No se pudo conectar con la base de datos. Inténtalo más tarde.</p>';
                exit;
            }
        }
        return self::$instancia;
    }

    // Evitar clonación
    private function __clone() {}

    // Evitar deserialización
    public function __wakeup() {
        throw new Exception("No se puede deserializar singleton");
    }
}
```

### Explicación de opciones PDO

| Opción | Valor | Explicación detallada |
|---|---|---|
| `PDO::ATTR_ERRMODE` | `PDO::ERRMODE_EXCEPTION` | Lanza `PDOException` en error. Permite `try/catch` y debug claro. |
| `PDO::ATTR_DEFAULT_FETCH_MODE` | `PDO::FETCH_ASSOC` | `fetch()` devuelve array asociativo `['id'=>1, 'nombre'=>'Lakers']` (legible). |
| `PDO::ATTR_EMULATE_PREPARES` | `false` | Fuerza prepared statements nativos del motor (más seguro). |
| `PDO::ATTR_PERSISTENT` | `false` | Conexiones nuevas por petición (prevenir efectos inesperados). |

## 7. Prepared Statements (Clave para Seguridad)

### ¿Qué es SQL Injection?

Si concatenamos:
```php
$email = $_POST['email']; // "admin' OR '1'='1"
$sql = "SELECT * FROM usuarios WHERE email = '$email' AND password = '$pass'";
// Se convierte en: ... WHERE email = 'admin' OR '1'='1' ...
// Esto podría burlar login
```

**Solución:** Parametrizar valores.

### Prepared Statement con named parameters (`:nombre`)

Más legible y claro.

**SELECT con WHERE**
```php
<?php
require_once 'app/core/Database.php';

$pdo = Database::getConexion();
$email = 'test@ejemplo.com';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = :activo");
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':activo', 1, PDO::PARAM_INT);
$stmt->execute();

$usuario = $stmt->fetch(); // Un solo registro
```

**INSERT**
```php
$stmt = $pdo->prepare("INSERT INTO equipos (nombre, ciudad, fundacion) VALUES (:nombre, :ciudad, :fundacion)");
$stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
$stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
$stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
$stmt->execute();
$idInsertado = $pdo->lastInsertId();
```

**UPDATE**
```php
$stmt = $pdo->prepare("UPDATE equipos SET nombre = :nombre, ciudad = :ciudad WHERE id = :id");
$stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
$stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
```

**DELETE**
```php
$stmt = $pdo->prepare("DELETE FROM equipos WHERE id = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
```

### bindValue vs bindParam

| Diferencia | `bindValue` | `bindParam` |
|---|---|---|
| Vincula | **Valor** en ese momento | **Referencia** a variable |
| Útil para | Valores fijos, constantes | Cuando variable puede cambiar antes de execute |
| Común en CRUD | **Sí, más directo** | Menos usado aquí |

Para CRUD básico, `bindValue` es claro y seguro.

### Tipos PDO para bind

| Constante | Cuándo usar |
|---|---|
| `PDO::PARAM_STR` | Strings (nombres, email, ciudad) |
| `PDO::PARAM_INT` | Enteros (id, años, puntos) |
| `PDO::PARAM_BOOL` | Booleanos |
| `PDO::PARAM_NULL` | NULL |
| `PDO::PARAM_LOB` | Archivos binarios (no aplica) |

## 8. Fetch Modes (Recuperar Datos)

Tras `execute()`, obtenemos resultados con `fetch()` o `fetchAll()`.

| Método | Devuelve | Uso |
|---|---|---|
| `$stmt->fetch()` | Primer registro (array assoc) o `false` si vacío | Buscar un solo registro (login, ver detalle) |
| `$stmt->fetchAll()` | Array con todos registros | Listados (index) |
| `$stmt->rowCount()` | Nº filas afectadas por UPDATE/DELETE/INSERT? | Ver si afectó filas. Atención: con SELECT no siempre confiable según driver. |
| `$pdo->lastInsertId()` | ID autoincremental generado | Útil tras INSERT |

**Ejemplos:**

```php
// Un registro
$stmt->execute();
$usuario = $stmt->fetch();
if ($usuario === false) {
    // No encontrado
}

// Varios registros
$stmt->execute();
$equipos = $stmt->fetchAll();
foreach ($equipos as $e) {
    echo $e['nombre'];
}

// Con objeto
$stmt->setFetchMode(PDO::FETCH_OBJ);
$equipo = $stmt->fetch();
echo $equipo->nombre;
```

Usaremos **FETCH_ASSOC** por defecto (coherente con todo el proyecto).

## 9. Transacciones (Opcional pero útil concepto)

Transacción garantiza "todo o nada". Útil si múltiples INSERT/UPDATE deben ser atómicos.

```php
<?php
$pdo->beginTransaction();
try {
    // varias operaciones
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

Para este proyecto **NO es estrictamente necesario**, pero es buen concepto a conocer.

## 10. Schema SQL - Explicado Conceptualmente

Veremos `database/schema.sql` completo. Conceptos clave:

### Claves
- `PRIMARY KEY (id)` → Identificador único, auto_increment
- `FOREIGN KEY (equipo_id) REFERENCES equipos(id)` → Integridad referencial
- `UNIQUE(email)` → Evita duplicados

### Tipos elegidos (razonables)
- `INT UNSIGNED` para IDs
- `VARCHAR(100/150/255)` para textos cortos
- `DATE/DATETIME` para fechas
- `TINYINT` para booleanos (0/1)
- `INT` para puntos (pueden ser >=0)

### Integridad referencial
Al eliminar equipo con jugadores, ¿qué pasa? Podemos usar `ON DELETE RESTRICT` o `ON DELETE SET NULL`. Para aprendizaje, **RESTRICT** evita borrar equipo si tiene dependencias (más seguro).

## 11. Errores Comunes con PDO (Evitar)

| Error | Causa | Solución |
|---|---|---|
| "SQLSTATE[HY000] [1049] Unknown database" | BD no existe o nombre mal | Crear BD `liga_basquet` |
| "Access denied for user" | Credenciales erróneas | Revisar DB_USER/DB_PASS |
| "SQLSTATE[HY093]: Invalid parameter number" | Nombre param mal (`:email` vs `:mail`) | Revisar nombres |
| "Call to undefined method" | Error tipográfico | Revisar método PDO |
| Notices "Undefined index" | Acceder $_GET sin isset | Usar `??` o validar |
| Datos vacíos tras fetch | No encontró registro | Comprobar condición `=== false` |

## 12. Debugging Seguro (Solo Desarrollo)

**NO mostrar stack trace completo en producción.** Aquí es **desarrollo/aprendizaje**.

Para entender errores:
```php
try {
    // ...
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    // echo $e->getTraceAsString(); // solo si necesario
}
```

En `Database.php` **no** mostramos `$e->getMessage()` al visitante: se registra con `error_log()` y se responde una página 500 genérica. El detalle de PDO incluye el DSN (host y nombre de la base), que no debe filtrarse.

## 13. Buenas Prácticas PDO (Aplicadas)

1. **Siempre prepared statements** → Nunca concatenar SQL
2. **Named parameters** → Más legibles (`:email`)
3. **FETCH_ASSOC por defecto** → Array limpio
4. **ERRMODE_EXCEPTION** → Errores detectables con try/catch
5. **emulate_prepares = false** → Más seguridad
6. **charset=utf8mb4** → Unicode correcto
7. **Singleton para conexión** → Evitar múltiples conexiones
8. **Validar IDs/valores** → Antes de bind
9. **Escapar salida en vistas** → `htmlspecialchars()` (XSS)
10. **Nunca exponer credenciales fuera de config** → Mantener config seguro

## 14. Checklist Rápido PDO

- [ ] `config.php` con constantes BD
- [ ] `Database.php` con `getConexion()` estático
- [ ] Usar `prepare()` + `bindValue()` + `execute()`
- [ ] `fetch()` para 1 registro, `fetchAll()` para lista
- [ ] `lastInsertId()` tras INSERT
- [ ] Usar `PDO::PARAM_INT`/`STR` correctos
- [ ] Probar conexión antes de seguir

## 15. Resumen

PDO + prepared statements es **estándar profesional** y **esencial para seguridad**. En este proyecto lo usaremos sistemáticamente en **todos** los modelos (`Usuario`, `Equipo`, `Jugador`, `Partido`). Esto garantiza:
- Código seguro ante SQL Injection
- Patrones reutilizables
- Fácil lectura y comprensión
- Base sólida para proyectos futuros