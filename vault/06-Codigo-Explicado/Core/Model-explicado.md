# Model (explicado)

**Archivo real:** `app/core/Model.php`
**Rol en la arquitectura:** clase base que da a los 4 modelos el CRUD que todos repiten.

## 1. Qué problema resuelve

`Equipo`, `Jugador`, `Partido` y `Usuario` necesitan todos lo mismo:

- Obtener todos los registros
- Obtener uno por su id
- Guardar (INSERT)
- Actualizar (UPDATE)
- Eliminar (DELETE)

Son 4 tablas distintas pero **las operaciones son idénticas**: solo cambia el nombre de la tabla. Es justo lo que resuelve la herencia: `Model` contiene la lógica común, y cada modelo solo declara su tabla y sus particularidades.

## 2. Las dos propiedades que heredan los modelos

```php
class Model {
    protected PDO $db;          // la conexión, compartida por todos
    protected string $tabla;    // cada modelo dice cuál es la suya
```

### `protected string $tabla` — el corazón del diseño

En `Equipo.php`:
```php
class Equipo extends Model {
    protected string $tabla = 'equipos';
}
```

Y en el `Model`:
```php
$sql = "SELECT * FROM {$this->tabla}";
```

`{$this->tabla}` se sustituye por `equipos`. El método `obtenerTodos()` de la clase base **no sabe** qué tabla está usando: en tiempo de ejecución, `$this` es un objeto `Equipo`, y PHP lee su propiedad `tabla`, que vale `equipos`.

**⚠️ Por qué `protected` y no `private`.** Una propiedad `private` en la clase padre **no sería accesible** desde la clase hija: cada clase tiene sus propiedades privadas separadas. Con `protected` (o `public`), el hijo sí puede leer y escribir la del padre. Este detalle es la diferencia entre que la herencia funcione o no.

**⚠️ Y por qué aquí NO es inyección SQL.** En general, escribir el nombre de la tabla en una consulta es un problema: un usuario podría manipularla. Aquí es seguro porque:

1. El valor **nunca viene del usuario**. Está escrito a mano en el archivo del modelo.
2. **MySQL no permite placeholders para nombres de tabla ni de columna**, solo para valores. No hay forma de "parametrizar" un identificador.

Es una excepción controlada y consciente, no un descuido. En los valores de los datos (`$nombre`, `$id`, `$fecha`) sí se usa siempre `?` o placeholders con nombre.

**Una señal de alarma a recordar:** si algún día vieras `protected string $tabla = $_GET['tabla'];`, eso sí sería una vulnerabilidad.

### `protected PDO $db` — la conexión heredada

```php
public function __construct() {
    $this->db = Database::getConexion();
}
```

Los modelos **no** escriben nada de configuración: piden la conexión al Singleton. El constructor de la clase base lo hace por ellos, así que `new Equipo()` ya deja `$this->db` listo.

**PHP no tiene constructores "encadenados"** (como en Java o C#): si el hijo define su propio constructor, el del padre **no se ejecuta automáticamente**. Por eso `PartidosController` y otros modelos que necesitan más cosas crean sus modelos **sin** constructor propio, y si lo tuvieran, tendrían que llamar a `parent::__construct()`.

## 3. Los cuatro métodos heredados

### `obtenerTodos()`

```php
public function obtenerTodos(): array {
    $sql = "SELECT * FROM {$this->tabla}";
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}
```

Tres pasos que se repiten **siempre** en PDO:

```php
$stmt = $pdo->prepare($sql);   // 1. preparar: enviar la consulta con huecos
$stmt->execute($valores);     // 2. ejecutar: enviar los valores
$resultado = $stmt->fetchAll(); // 3. recoger: convertir la respuesta en PHP
```

`fetchAll()` devuelve **todos** los registros como un array de arrays. `fetch()` devuelve **solo el primero**.

**`prepare()` sobre una consulta sin variables parece inútil.** ¿Por qué se usa? Porque mantiene el estilo uniforme y, sobre todo, porque `prepare()` + `execute()` es **más rápido** si la consulta se repite muchas veces (el servidor la analiza una vez). Y evita que alguien escriba `query()` por inercia en una línea que sí recibe datos del usuario.

### `obtenerPorId()`

```php
public function obtenerPorId(int $id): ?array {
    $sql = "SELECT * FROM {$this->tabla} WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch();
    return $resultado === false ? null : $resultado;
}
```

Cuatro detalles importantes:

**a) El tipo `:array` con `?` en el retorno** → `obtenerPorId()` devuelve un array **o `null`** si no encuentra nada. Es lo que permite:

```php
if ($partido === null) {
    $_SESSION['error'] = 'Partido no encontrado';
    header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
    exit;
}
```

**b) `LIMIT 1`** → le dice al servidor que solo traiga la primera fila. Como `id` es clave primaria, el resultado es único; el `LIMIT 1` es una red de seguridad.

**c) `PDO::PARAM_INT`** → le dice a PDO que lo que va es un entero. Sin esto, PDO envía todo como cadena, y MySQL acaba haciendo una conversión implícita que **impide que use el índice**: la diferencia puede ser de 3 milisegundos a 300 en una tabla grande.

**d) `fetch()` devuelve `false` cuando no hay filas.** Un controller principiante escribe `return $stmt->fetch();` y cree que devuelve `null`. No: devuelve `false`. Por eso la línea es `$resultado === false ? null : $resultado`. También aquí `===` en vez de `!`, para no confundir un array vacío con `false`.

### `eliminar()`

```php
public function eliminar(int $id): bool {
    $sql = "DELETE FROM {$this->tabla} WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}
```

`execute()` devuelve `true` si la sentencia se ejecutó. **Ojo con lo que eso significa:** `true` quiere decir "la orden se envió correctamente", **no** "se borró algo". Si el `id` no existía, `execute()` sigue devolviendo `true` y no se borra ninguna fila. Para saber si había algo que borrar hay que usar `rowCount()`, y el proyecto sí lo hace:

```php
// execute() devuelve true aunque no se borre ninguna fila, así que
// rowCount() es lo que dice si el registro existía de verdad.
// Aquí no hay la ambigüedad del UPDATE: un DELETE recuenta las filas
// que borra de verdad, así que 0 significa "no existía".
return $stmt->execute() && $stmt->rowCount() > 0;
```

#### ⚠️ `rowCount()` no sirve para un UPDATE

Esta es una trampa real, y el proyecto la encontró al escribir los tests de regresión. En un `DELETE`, `rowCount()` cuenta filas eliminadas. En un `UPDATE`, MySQL cuenta filas **cambiadas**, no filas **encontradas**:

| `UPDATE` sobre | Filas encontradas | `rowCount()` |
|---|---|---|
| Un id inexistente | 0 | **0** |
| Un id existente al que se le cambia algo | 1 | **1** |
| Un id existente al que se le guarda lo mismo | 1 | **0** ⚠️ |

Las dos últimas filas son el problema. Si el usuario abre el formulario de edición, no cambia nada y pulsa guardar, MySQL devuelve `0` y el código conclude "ese equipo no existe":

```
Actualización fallida: No se encontró el equipo que se quiso modificar
```

...para un equipo que existe perfectamente y que el usuario acaba de ver en pantalla.

⚠️ **Un falso negativo es peor que un falso positivo aquí.** Un falso positivo (decir "guardado" sin haber guardado) es una molestia. Un falso negativo (decir "no existe" sobre algo que el usuario tiene delante) hace pensar que la aplicación está rota.

La solución en `Equipo`, `Jugador` y `Partido` es no fiarse de `rowCount()` para decidir si el id existe:

```php
if (!$stmt->execute()) {
    return false;
}
if ($stmt->rowCount() > 0) {
    return true;                     // se cambió algo: todo bien
}
return $this->obtenerPorId($id) !== null;   // 0 filas: ¿existe o no?
```

⚠️ **La consulta de descarte es un `SELECT` extra por guardado.** Solo se ejecuta en el caso raro de "no se cambió nada", así que en el uso normal no se nota. La alternativa sería `PDO::MYSQL_ATTR_FOUND_ROWS`, que hace que `rowCount()` cuente filas encontradas, pero es un ajuste de conexión que cambia el significado de `rowCount()` en toda la aplicación; para este proyecto el `SELECT` explícito es más fácil de entender.

⚠️ **Los controladores también validan antes de llamar.** `validarId()` ya comprueba la existencia con `obtenerPorId()`, así que el mensaje correcto ya se ha mostrado antes. La comprobación del modelo es la red de seguridad para un `actualizar()` que se llame desde otro sitio.

### `obtenerUltimoId()`

```php
public function obtenerUltimoId(): string {
    return $this->db->lastInsertId();
}
```

**¿Para qué sirve?** MySQL genera el `id` al insertar (`AUTO_INCREMENT`). Si necesitas saberlo, no puedes adivinarlo: se le pregunta al servidor.

En este proyecto **no se usa**, porque tras crear se redirige al listado y no hace falta conocer el id. Pero es imprescindible en el caso típico de "guardar un post y luego redirigir a la edición de ese post".

**Advertencia:** `lastInsertId()` es un método **de la conexión**, no de la sentencia. Si haces dos `INSERT` y consultas entre medias, devuelve el id del **último INSERT**, no el tuyo. El patrón correcto es leerlo inmediatamente después de tu `execute()`.

## 4. Lo que cada modelo añade encima

La herencia da lo común; cada modelo añade solo lo suyo.

### `Equipo`

```php
public function obtenerTodos(): array {
    $sql = "SELECT * FROM {$this->tabla} ORDER BY nombre ASC";
    ...
}
```

**Sobrescribe** el método del padre para añadir `ORDER BY`. Es legal: el método del hijo gana. Los controladores que usan `Equipo` obtienen la lista **ordenada alfabéticamente**, y quien llame al `obtenerTodos()` genérico no tiene que saber que aquí está ordenado.

Y añade los métodos `crear()` y `actualizar()`, que resuelven el tratamiento del `NULL` en `fundacion` (ver `../Models/Equipo-explicado.md`).

### `Usuario`

Añade `buscarPorEmail()` y `existeEmail()`. Son consultas **de una tabla concreta**, no un CRUD genérico: el email no es un id.

### `Partido`

Sobrescribe `obtenerTodos()` **y** `obtenerPorId()` para añadir dos `JOIN` a `equipos`:

```php
SELECT p.*, el.nombre AS nombre_local, ev.nombre AS nombre_visitante
FROM partidos p
LEFT JOIN equipos el ON el.id = p.equipo_local_id
LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
ORDER BY p.fecha DESC
```

Es el mejor ejemplo de por qué el patrón funciona: el método genérico no sabe nada de partidos, y este modelo necesita una consulta totalmente distinta. Puede reescribirla sin tocar el `Model` ni a los otros tres modelos.

**Usa `LEFT JOIN` y no `JOIN`.** Con `LEFT JOIN`, si un partido tuviera un `equipo_id` inexistente, la fila **seguiría apareciendo** (con el nombre en `null`) en lugar de desaparecer de la lista. Con `JOIN` interno, ese partido se ocultaría sin aviso. En una aplicación real, `LEFT JOIN` es la opción más segura para no perder datos.

**`ORDER BY p.fecha DESC`** → los partidos más recientes primero, que es lo que espera el usuario de un módulo de partidos.

## 5. Los dos estilos de placeholders, y cuál usa este proyecto

| Estilo | Ejemplo | Estado |
|---|---|---|
| Posicional `?` | `$stmt->execute([$id])` | Funciona siempre |
| Con nombre `:id` | `$stmt->bindValue(':id', $id, PDO::PARAM_INT)` | Funciona siempre en MySQL/MariaDB |

El proyecto usa los dos estilos según el archivo, porque ambos son válidos. Si mezclas los dos en el **mismo `execute()`**, cuidado: PDO **no** admite named y posicional a la vez en una misma llamada.

⚠️ **Mitos sobre este tema (importante):**

| Se cree que... | Realidad |
|---|---|
| Los placeholders con nombre exigen `EMULATE_PREPARES => true` | **Falso.** Con `false` (prepares nativos) los placeholders con nombre funcionan igual en MySQL/MariaDB. De hecho, es al revés: con emulación activada **no** se puede reutilizar un mismo placeholder con nombre dos veces en la misma consulta, y con prepares nativos sí. |
| Con `EMULATE_PREPARES => false` hay que usar `?` | **Falso.** Los 4 modelos de este proyecto usan `:id` con la emulación desactivada y funcionan. |

**La razón real por la que `?` es más portable:** algunas extensiones de PDO que no son de MySQL (por ejemplo SQL Server u Oracle) no soportan placeholders con nombre de la misma forma. Como este proyecto solo habla con MySQL/MariaDB, esa limitación no aplica y `:id` es preferible porque se lee mejor.

**Por qué `bindValue()` cuando basta `execute([$datos])`:** `bindValue()` permite declarar el **tipo** (`PDO::PARAM_INT`, `PDO::PARAM_NULL`, `PDO::PARAM_STR`). Eso es justo lo que se necesita para guardar un `NULL` correctamente en `equipos.fundacion`. `execute([...])` no permite especificar el tipo.

## 6. Errores frecuentes con los modelos

| Error | Síntoma | Por qué |
|---|---|---|
| `protected` → `private` en `$tabla` | `SQLSTATE[42S02]: Base table or view not found: ''` | La tabla queda vacía: el hijo no ve la propiedad del padre |
| Olvidar `extends Model` | `Call to undefined method Equipo::obtenerTodos()` | Sin herencia no hay CRUD |
| `fetch()` esperando `null` | Comparar `=== null` y no entrar nunca | `fetch()` devuelve `false` si no hay filas |
| Olvidar `PDO::PARAM_INT` | Consultas lentas | MySQL no puede usar el índice con conversiones |
| No sobrescribir el método del padre | Listados sin `ORDER BY` | El método heredado gana si el hijo no lo define |
| Poner `$tabla` desde `$_GET` | Inyección SQL | El nombre de tabla nunca debe venir del usuario |
| Reutilizar `:id` con dos tipos distintos | `Invalid parameter number` | Con prepares nativos un mismo nombre debe enviarse siempre con el mismo tipo |
| Usar `?` y `:` en la misma llamada | `SQLSTATE[HY093]: Invalid parameter number` | No se pueden mezclar posicionales y con nombre en un mismo `execute()` |

## 7. El resumen en una imagen mental

```
Model  (tabla = ?  ,  db = la conexión compartida)
  ├─ obtenerTodos()      SELECT * FROM tabla
  ├─ obtenerPorId($id)   SELECT * FROM tabla WHERE id = ?
  ├─ eliminar($id)       DELETE FROM tabla WHERE id = ?
  └─ obtenerUltimoId()   la conexión

Equipo   extends Model   tabla = 'equipos'    + crear() actualizar() + ORDER BY nombre
Jugador  extends Model   tabla = 'jugadores'  + crear() actualizar() + JOIN con equipos
Partido  extends Model   tabla = 'partidos'   + crear() actualizar() + doble JOIN + ORDER BY fecha
Usuario  extends Model   tabla = 'usuarios'   + buscarPorEmail() existeEmail() crear()
```

**El modelo no valida nada.** Solo guarda y lee. Las reglas ("el equipo local y el visitante no pueden ser iguales") viven en los controladores. Mantener esa separación es lo que hace el código legible.

---

**Archivos relacionados:** `Database-explicado.md` · `../Models/Partido-explicado.md` · `../Models/Equipo-explicado.md`

**Siguiente paso →** `../Models/Equipo-explicado.md` (el manejo del `NULL` en un campo opcional).