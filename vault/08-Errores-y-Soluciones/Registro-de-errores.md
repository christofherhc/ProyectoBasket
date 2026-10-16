# Registro de Errores y Soluciones

Todos los errores reales que aparecieron al construir y verificar este proyecto. El objetivo es que, cuando te metas un error parecido dentro de dos meses, lo reconozcas aquí en lugar de perder una tarde.

**Cómo leerlo:** "Error" es lo que ves en pantalla o en el navegador · "Causa" es lo que realmente pasó · "Solución" es el arreglo definitivo.

## Índice rápido

| # | Error | Gravedad |
|---|---|---|
| [1](#1-no-database-selected) | `No database selected` | Bloqueante |
| [2](#2-duplicate-key-name-al-reimportar-el-schema) | `Duplicate key name` al reimportar el schema | Bloqueante |
| [3](#3-duplicate-entry-al-importar-los-datos-de-prueba) | `Duplicate entry` al importar seeds | Alto |
| [4](#4-el-formulario-de-editar-partido-salía-vacío) | Campo `datetime-local` vacío | Alto |
| [5](#5-la-vista-de-partidos-mostraba-la-página-en-blanco) | Vista vacía, pantalla en blanco | Alto |
| [6](#6-ambigüedad-en-el-join-de-partidos) | `Column 'id' is ambiguous` | Medio |
| [7](#7-la-fundación-y-el-número-guardaban-0-en-vez-de-vacío) | Opcionales se guardaban como 0 | Medio |
| [8](#8-intentos-de-inyección-sql) | Login vulnerable a SQL injection | Crítico |
| [9](#9-xss-en-el-listado) | `<script>` sin escapar en la vista | Crítico |
| [10](#10-el-formulario-reenviaba-datos-al-recargar) | Doble envío al pulsar F5 | Medio |
| [11](#11-los-mensajes-de-éxito-salían-dos-veces) | Mensaje duplicado en pantalla | Bajo |
| [12](#12-fallo-de-session-fixation) | Reutilización del ID de sesión | Alto |
| [13](#13-rutas-relativas-al-cargar-el-layout) | `Failed to open stream` | Medio |
| [14](#14-las-pruebas-daban-fallos-pero-la-aplicación-sí-funcionaba) | Falsos negativos en las pruebas | Interesante |
| [15](#15-la-base-de-datos-de-la-última-verificación-quedó-con-datos-de-prueba) | Datos sucios entre ejecuciones | Interesante |
| [16](#16-el-borrado-de-un-equipo-con-jugadores-daba-pantalla-en-blanco) | Borrar equipo con jugadores o partidos | ✅ Resuelto |
| [17](#17-el-router-reflejaba-html-en-el-mensaje-de-error) | XSS reflejado en `?controller=` | ✅ Resuelto |
| [18](#18-la-redirección-usaba-el-header-host) | `Location:` con host ajeno | ✅ Resuelto |
| [19](#19-los-campos-enviados-como-array-provocaban-500) | `nombre[]=x` -> HTTP 500 | ✅ Resuelto |
| [20](#20-los-datos-inválidos-provocaban-500) | Exceso de longitud, fecha o FK inválida | ✅ Resuelto |
| [21](#21-la-base-no-restringía-posiciones-ni-dorsales) | `posicion=ADMIN` y dorsales duplicados | ✅ Resuelto |

---

## 1. No database selected

**Síntoma.** Al importar `schema.sql`, o al usar la aplicación:

```
ERROR 1046 (3D000): No database selected
```

y la página en blanco.

**Causa.** MySQL no sabe en qué base trabajar si no se lo indicas, y `schema.sql` no lo hacía. La aplicación tampoco: el DSN no llevaba `dbname`.

**Solución.** Las dos primeras instrucciones del script:

```sql
CREATE DATABASE IF NOT EXISTS liga_basquet
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE liga_basquet;
```

Y en PHP, el nombre de la base va en el DSN:

```php
'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4'
```

**Aprendizaje.** "Conectarse al servidor" y "conectarse a una base" son dos pasos distintos. El segundo es el que se olvida.

---

## 2. Duplicate key name al reimportar el schema

**Síntoma.** La primera importación funciona. La segunda:

```
ERROR 1061 (42000): Duplicate key name 'idx_jugador_equipo'
```

**Causa.** La versión antigua declaraba los índices **fuera** del `CREATE TABLE`:

```sql
-- ❌ MAL
CREATE INDEX idx_jugador_equipo ON jugadores(equipo_id);
```

Un índice solo puede crearse una vez, y el `IF NOT EXISTS` que sí tienen las tablas no protege a los índices sueltos.

**Solución.** Declararlos dentro de la tabla, separados por comas:

```sql
-- ✔️ BIEN
CREATE TABLE IF NOT EXISTS jugadores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id INT UNSIGNED NOT NULL,
  KEY idx_jugador_equipo (equipo_id),
  CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id)
);
```

**Aprendizaje.** Todo lo que es *parte* de un objeto (columnas, índices, claves foráneas) va dentro de su bloque. Solo puede ir suelto lo que se crea de forma independiente.

---

## 3. Duplicate entry al importar los datos de prueba

**Síntoma.**

```
ERROR 1062 (23000): Duplicate entry 'demo@liga.com' for key 'email'
```

**Causa.** `seeds.sql` usaba `INSERT` normal y no fijaba los `id`, así que la segunda importación colisionaba.

**Solución.**

```sql
INSERT IGNORE INTO usuarios (id, nombre, email, password) VALUES
(1, 'Usuario Demo', 'demo@liga.com', '$2y$10$92IX...');
```

Dos cambios: `INSERT IGNORE` salta las filas que ya existen, y los `id` fijos mantienen coherentes las referencias `equipo_id`.

**Cuidado con `IGNORE`.** Solo silencia los errores de clave duplicada. Si escribes mal una columna, el error sigue apareciendo. No es un "cállate todo".

**Aprendizaje.** Un archivo de datos de prueba que se va a importar más de una vez tiene que ser **idempotente**: ejecutarlo N veces produce el mismo resultado.

---

## 4. El formulario de editar partido salía vacío

**Síntoma.** El campo "Fecha y hora" del formulario de edición estaba **vacío**, aunque el partido existía y se listaba bien en el módulo.

**Causa.** Formato incompatible:

| Dónde | Formato |
|---|---|
| La base de datos | `2026-10-01 19:00:00` (espacio) |
| `<input type="datetime-local">` | `2026-10-01T19:00` (letra `T`) |

Cuando el `value` no coincide **exactamente** con el formato esperado, el navegador **lo descarta en silencio** y el campo aparece vacío.

Este error es traicionero porque **no produce ningún aviso**: ni error de PHP, ni mensaje en la consola. El HTML es válido; simplemente el navegador lo ignora.

**Solución.** Convertir el espacio en `T` al generar el `value`:

```php
<input type="datetime-local" name="fecha"
       value="<?= htmlspecialchars(str_replace(' ', 'T', substr($partido['fecha'], 0, 16))) ?>"
       required>
```

Dos cosas hace ese código, y las dos son necesarias:
- `substr(..., 0, 16)` → recorta los segundos: `2026-10-01 19:00:00` → `2026-10-01 19:00`.
- `str_replace(' ', 'T', ...)` → cambia el separador: → `2026-10-01T19:00`.

Se aplicó lo mismo en el formulario de creación, por si `$old['fecha']` llegara con espacio al recuperar un formulario con errores.

**El error dentro del error.** La primera solución que se probó fue **solo** `substr($partido['fecha'], 0, 16)`. Parecía correcta leyendo el código, pero el resultado seguía siendo `2026-10-01 19:00` **con espacio**, y el campo seguía vacío. Se detectó únicamente abriendo el formulario en el navegador y mirando el campo real. Documentarlo aquí porque es la clase de error que no se ve leyendo código.

**Verificación adicional.** MySQL y MariaDB **sí** aceptan el formato con `T` al *guardar*: inserting `'2026-10-12T19:00'` lo almacena como `2026-10-12 19:00:00`. Así que no hace falta transformar el `POST` antes de insertar. El problema era solo de visualización.

**Aprendizaje.** Un campo de formulario invisible es casi siempre un problema de **formato**, no de datos. Y HTML5 no perdona: si el valor no coincide exactamente, lo ignora sin avisar.

---

## 5. La vista de partidos mostraba la página en blanco

**Síntoma.** `/partidos` devolvía una pantalla completamente vacía. Los otros módulos funcionaban con normalidad.

**Causa.** El archivo `app/views/partidos/index.php` **estaba vacío (0 bytes)**. Un `require` de un archivo vacío no genera error: simplemente no imprime nada.

**Solución.** Reescribir la vista completa: tabla con fecha, ambos equipos, marcador, resultado calculado y botones de editar/eliminar.

**Cómo diagnosticarlo rápido.** Antes de culpar al código o la base:

```bash
wc -l app/views/partidos/index.php    # → 0 líneas = encontrado
php -l app/views/partidos/index.php   # "No syntax errors" (¡pasa igual!)
```

Un archivo vacío **aprueba** la revisión de sintaxis, porque no tiene nada que analisar. Por eso `wc -l` va antes que `php -l`.

**Aprendizaje.** "Página en blanco" tiene tres causas principales: archivo vacío, error PHP silenciado por la configuración, o un `require` con ruta equivocada. Empieza siempre por el tamaño del archivo.

---

## 6. Ambigüedad en el JOIN de partidos

**Síntoma.**

```
ERROR 1052 (23000): Column 'id' in field list is ambiguous
```

**Causa.** `partidos` se une con `equipos` **dos veces** (el local y el visitante). Al escribir `SELECT nombre FROM partidos JOIN equipos ...`, MySQL no sabe de qué tabla sale `nombre`: las dos tienen esa columna.

**Solución.** Dar un alias a cada aparición de la tabla:

```php
SELECT p.*,
       el.nombre AS nombre_local,
       ev.nombre AS nombre_visitante
FROM partidos p
JOIN equipos el ON el.id = p.equipo_local_id
JOIN equipos ev ON ev.id = p.equipo_visitante_id
```

**Aprendizaje.** En cuanto una tabla aparece **más de una vez** en una consulta, los alias dejan de ser opcionales. Usa alias siempre, aunque solo haya una tabla: te obliga a escribir de dónde sale cada columna.

---

## 7. La fundación y el número guardaban 0 en vez de vacío

**Síntoma.** Al crear un equipo sin año de fundación, el listado mostraba **0** en la columna "Fundación", y el número de camiseta del jugador aparecía como 0.

**Causa.** PHP convierte la cadena vacía de un formulario (`''`) en el entero `0` al pasarlo a un campo `INT`. El `''` no es `NULL`: es un cero disfrazado.

**Solución.** Decidir explícitamente que vacío significa `NULL`:

```php
$fundacion = ($datos['fundacion'] ?? '') !== '' ? (int)$datos['fundacion'] : null;
```

y al guardar, vincularlo con el tipo correcto:

```php
$stmt->bindValue(':fundacion', $datos['fundacion'], $datos['fundacion'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
```

Y al mostrar, tratar `null` como celda vacía:

```php
<?= $equipo['fundacion'] !== null ? htmlspecialchars($equipo['fundacion']) : '—' ?>
```

**Por qué `bindValue` con `PDO::PARAM_NULL`.** Si no dices el tipo, PDO lo adivina, y al adivinar puede convertir `null` en `0` otra vez. Ser explícito con `PDO::PARAM_NULL` es lo que hace que el `NULL` llegue intacto a la base.

**Aprendizaje.** `''`, `0`, `'0'` y `NULL` son cuatro valores distintos. Decide cuál de los cuatro representa "el usuario no llenó este campo", y tradúcelo de forma explícita en cada capa.

---

## 8. Intentos de inyección SQL

**Síntoma (si fuera vulnerable).** Un usuario podría saltarse el login escribiendo en el email:
```
demo@liga.com' OR '1'='1
```

**Causa.** Construir la consulta concatenando lo que viene del usuario:
```php
// ❌ NUNCA
$sql = "SELECT * FROM usuarios WHERE email = '$email' AND password = '$hash'";
```

**Solución (la que se usa en el proyecto).** Prepared statements: el usuario manda los datos, nunca la estructura de la consulta.

```php
$sql = "SELECT id, nombre, email, password FROM usuarios WHERE email = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && password_verify($password, $usuario['password'])) {
    // acceso concedido
}
```

El `?` es un **hueco literal**. El driver envía a MySQL la consulta con los signos de interrogación *y* los valores por un canal distinto (protocolo "prepared statement"), de modo que MySQL nunca interpreta el contenido del email como código SQL.

**Verificación real.** Se probó con `demo@liga.com' OR '1'='1` tanto en el email como en la contraseña: ambos intentos devolvieron "Credenciales incorrectas" y no '__crearon sesión.

**El password hash no va en la consulta.** Un error común es poner `password_hash($password)` en el `WHERE`. No funciona: como la sal es aleatoria, el hash cambia cada vez y nunca coincide con el almacenado. La contraseña **nunca** se busca en la base: se trae la fila por email y luego se compara con `password_verify()`.

**Aprendizaje.** Regla de oro: **datos con `?` y `execute()`, nunca con concatenación.** Si ves `$sql .= $variable`, es un fallo de seguridad aunque "funcione".

---

## 9. XSS en el listado

**Síntoma.** Al guardar un equipo llamado `<script>alert(1)</script>`, el navegador ejecutaba el script en el listado.

**Causa.** PHP no escapa la salida. Si el dato viene de la base y lo imprimes tal cual, el HTML lo interpreta.

**Solución. Tres capas, todas necesarias:**

**Capa 1 — escapar al imprimir (obligatoria en cada vista):**
```php
<?= htmlspecialchars($equipo['nombre']) ?>
```

**Capa 2 — escapar también los atributos:**
```php
value="<?= htmlspecialchars($equipo['ciudad']) ?>"
```
Sin comillas en el atributo, un valor con `"` rompe el HTML.

**Capa 3 — el router y el layout no deben imprimir datos crudos.** Los mensajes de sesión van escapados también en el layout.

**Verificación real.** Se guardó el equipo con `<script>alert(1)</script>` como nombre y el listado lo mostró como texto, sin ejecutar nada. La base guarda el texto tal cual (no "neutralizado"): la protección es al **mostrar**, no al guardar. Enseñar HTML en un listado es perfectamente válido; ejecutarlo es el agujero.

**Lección sobre el orden.** No intentes limpiar los datos al guardarlos (quitar `<script>` del texto). Eso corrompe la información y deja pasar otras variantes. Escapa al imprimir, siempre.

**Aprendizaje.** PHP **no escapa nada** por defecto. Cada `<?= ?>` que muestre datos de la base necesita su `htmlspecialchars()`. No es opcional, es la regla.

---

## 10. El formulario reenviaba datos al recargar

**Síntoma.** Después de crear un jugador, si pulsabas F5 (o volvías atrás), el formulario se enviaba otra vez y **creaba un jugador duplicado**.

**Causa.** Después de procesar el POST, el controlador redireccionaba con `header('Location: ...')` pero **sin `exit`**. PHP seguía ejecutando el archivo, y el `include` de la vista devolvía HTML que se acumulaba con los headers de redirección.

**Solución. Patrón PRG (Post/Redirect/Get):**
```php
$_SESSION['exito'] = 'Jugador creado correctamente';
header('Location: ' . BASE_URL . '/?controller=Jugadores&action=index');
exit;   // ← esto es lo que faltaba
```

**Por qué `exit` es imprescindible.** La redirección solo funciona si **no** se envía nada más. Con `exit`, el navegador recibe la orden de ir a la URL nueva y llega a la vista limpia. Sin `exit`, se mezclan la respuesta del POST (formulario) y la del GET (listado), y al recargar se repite el envío.

**Por qué redireccionar y no mostrar el mensaje en la misma respuesta.** Si.showas el "creado correctamente" directamente, la URL sigue siendo `?action=guardar`. Al recargar, el navegador reenvía el POST y duplica el registro. Con PRG, la URL es siempre la del listado, y recargar es inofensivo.

**Aprendizaje.** `header('Location: ...')` sin `exit` es un error clásico de PHP. Y para formularios, la combinación correcta es redirección + `exit`.

---

## 11. Los mensajes de éxito salían dos veces

**Síntoma.** Tras crear algo, el mensaje "Equipo creado correctamente" aparecía dos veces, y se quedaba ahí al navegar a otra página.

**Causa.** Los mensajes se guardan en la sesión y no se limpian al mostrarlos:

```php
if (isset($_SESSION['exito'])) {
    echo $_SESSION['exito'];
}
```

**Solución. Mostrar y borrar en el mismo paso:**
```php
if (isset($_SESSION['exito'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['exito']) . '</div>';
    unset($_SESSION['exito']);   // ← se consume
}
```

Lo mismo con `$_SESSION['error']`.

**Por qué la sesión y no una variable normal.** Con `header('Location: ...')` la petición anterior **muere**: sus variables locales desaparecen. La sesión es lo único que sobrevive al salto entre las dos peticiones. Por eso el mensaje "viaja" en la sesión y se limpia al mostrarse.

**Aprendizaje.** La sesión sirve para transportar datos entre dos peticiones HTTP. Todo lo que guardes ahí debe consumirse (`unset`) en el mismo paso, o se quedará ahí para siempre.

---

## 12. Fallo de session fixation

**Síntoma.** El `PHPSESSID` no cambiaba al iniciar sesión, así que si alguien conocía el ID de sesión de otra persona podía reutilizarlo.

**Causa.** El identificador de sesión se creaba al empezar a navegar, antes de autenticarse. Login y "sesión anónima" comparten el mismo ID.

**Solución. Regenerar el ID tras autenticar:**
```php
session_regenerate_id(true);
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['usuario_nombre'] = $usuario['nombre'];
```

El `true` es importante: **borra el archivo de sesión antiguo**. Sin ese parámetro, la sesión preautenticación sigue existiendo en el servidor y el atacante podría usarla.

**Por qué funciona.** El objetivo es que la sesión *anterior* (la de visitante, posiblemente conocida) deje de ser válida en cuanto la persona se autentica. La nueva sesión pertenece a un usuario ya verificado.

**Aprendizaje.** `session_regenerate_id()` **siempre** después de un cambio de privilegios (login, logout, cambio de contraseña).

---

## 13. Rutas relativas al cargar el layout

**Síntoma.**
```
Warning: include(../app/views/layouts/main.php): Failed to open stream
```

**Causa.** `require` con ruta relativa (`../`) depende del directorio de trabajo actual, que en servidor web no es necesariamente el archivo que lo llama.

**Solución.** Rutas ancladas al archivo, no al lugar desde donde se ejecuta:
```php
require_once __DIR__ . '/../views/layouts/main.php';
```

**Qué significa `__DIR__`.** Es una constante mágica de PHP que contiene **la ruta de la carpeta donde está el archivo actual**. Como `Controller.php` siempre vive en `app/core/`, `__DIR__` siempre vale `.../app/core`, sin importar desde dónde se llame.

**Solución alternativa, más simple.** Como solo hay un nivel de profundidad y los archivos se incluyen siempre desde el mismo `front controller`, la versión final del proyecto usa rutas relativas normales (`../app/views/...`) y **funciona**, verificado con el servidor de XAMPP. Se documenta `__DIR__` aquí porque es la práctica correcta y evita sorpresas si mañana añades un subdirectorio.

**Aprendizaje.** Rutas relativas en `include`/`require` son frágiles. `__DIR__` es la forma de hacerlas robustas.

---

## 14. Las pruebas daban fallos pero la aplicación sí funcionaba

**Síntoma.** La suite de pruebas informaba **13 fallos**, entre ellos "el usuario puede registrarse con email duplicado" o "no se puede iniciar sesión con contraseña incorrecta", cuando abriendo la aplicación a mano todo funcionaba correctamente.

**Causa. Errores en el propio script de pruebas, no en la aplicación. Tres casos distintos:**

**a) `curl` no seguía las redirecciones.** Después de un `POST` el controlador responde `302 Location: ...`. Sin `-L`, `curl` se quedaba en la respuesta de redirección y nunca veía el mensaje de la página siguiente:

```bash
# ❌ nunca ve el mensaje de éxito/error
curl -s -d "email=demo@liga.com&password=mal" "$B/?controller=Auth&action=login"

# ✔️ sí lo ve
curl -sL -d "email=demo@liga.com&password=mal" "$B/?controller=Auth&action=login"
```

**b) Faltaba el archivo de cookies.** El mensaje "Credenciales incorrectas" viaja en la sesión. Al redirigir, `curl` tenía que reenviar la cookie `PHPSESSID`; sin `-c`/`-b` abría una sesión nueva en cada salto y la sesión "se perdía":

```bash
# ❌ cookie no persiste entre peticiones
curl -sL -d "..." "$B/?..."

# ✔️ cookie jar: se guarda y se reenvía
curl -sL -c /tmp/ck.txt -b /tmp/ck.txt -d "..." "$B/?..."
```

**c) Aserciones equivocadas.** Una comprobación asumía mal: esperaba ver el login tras pedir `action=guardar` por GET, cuando lo correcto (y lo que hace el código) es redirigir al formulario de creación. El test asumía mal, no la aplicación.

**Solución.** Reescribir la suite con las tres correcciones, y **verificar cada "fallo" contra el comportamiento real** antes de tocar el código de la aplicación.

**Resultado.** **81 de 81 pruebas superadas.** Y por el camino apareció el único bug de verdad que quedaba (el del `datetime-local`, error 4).

**Lección importante.** Cuando una prueba falla, hay que decidir **qué** está mal: el código o la prueba. Cambiar el código para satisfacer un test mal escrito destruye funcionalidad correcta y esconde bugs reales.

---

## 15. La base de datos de la última verificación quedó con datos de prueba

**Síntoma.** Al repetir la suite, aparecieron fallos como "el listado muestra 5 equipos en vez de 4" o "el usuario `uno@liga.com` ya existe".

**Causa.** La suite había creado datos ("Test Team", "Test Player", un usuario registrado, un partido de prueba) y **no los borraba al terminar**. Como los scripts usan `INSERT IGNORE`, los datos de `seeds.sql` tampoco restauraban el estado original: se saltaban por ya existir.

**Solución. Que la suite empiece desde cero, siempre:**
```bash
mysql -u root -e "DROP DATABASE IF EXISTS liga_basquet;"
mysql -u root < database/schema.sql
mysql -u root < database/seeds.sql
```
y ejecutar la suite completa después. Así el resultado es idéntico en la segunda, tercera o décima ejecución.

**Por qué `INSERT IGNORE` no sirve aquí.** `IGNORE` es lo correcto para *seeds* (no quiero perder mis datos si reimporto). Pero para *preparar un entorno de pruebas limpio* lo que se necesita es lo contrario: **borrar**. Son dos intenciones distintas y requieren dos herramientas distintas.

**Aprendizaje.** Antes de confiar en una batería de pruebas, pregúntate: **¿el resultado depende del orden?** Si al repetirla salen números distintos, la batería está mal diseñada, no la aplicación.

---

## 16. ✅ El borrado de un equipo con jugadores daba pantalla en blanco

**Error:** al pulsar "Eliminar" en un equipo con jugadores o partidos, el navegador muestra una página vacía (HTTP 500).

**Causa:** `equipos.id` tiene `ON DELETE RESTRICT`. El `DELETE` viola la clave foránea y MariaDB responde con el error 1451. `Database` configura `PDO::ERRMODE_EXCEPTION`, así que la excepción sube hasta `index.php`, que **no tiene ningún `try/catch`**. Resultado: los datos están a salvo, pero el usuario no ve ningún mensaje.

```text
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row
```

**✅ Solución aplicada:** envolver la llamada en `try/catch` y comprobar el código 1451, o avisar antes:

```php
try {
    $ok = $this->equipoModel->eliminar($id);
} catch (PDOException $e) {
    $ok = ($e->errorInfo[1] === 1451);
}
```

Alternativa más limpia: contar los dependientes antes de borrar y mostrar un aviso legible ("no se puede eliminar: tiene N jugadores y M partidos").

⚠️ **Los datos nunca se pierden.** `RESTRICT` funciona; lo que falla es el mensaje.

---

## 17. ✅ El Router reflejaba HTML en el mensaje de error

**Error:** al pedir `index.php?controller=<script>alert(1)</script>`, el navegador ejecuta JavaScript.

**Causa:** `Router::cargarController()` compone el mensaje de error con `$nombreClase`, que viene de la URL sin escapar, y lo imprime con `die()`.

**✅ Solución aplicada:** escapar en la salida **o**, mejor, no imprimir el nombre del archivo en absoluto. La versión final va más allá: el Router **no imprime ningún dato de la URL** y responde con un método `error()` que devuelve 404 y un mensaje fijo.

```php
// Opción mínima: no imprimir el dato
die('Error: controlador no encontrado.');

// Lo que quedó implementado: 404 con página propia y mensaje fijo
private function error(string $mensaje): void {
    http_response_code(404);
    echo '<h1>Petición no válida</h1><p>' . e($mensaje) . '</p>';
    exit;
}
```

⚠️ **Escapar al imprimir, no al entrar:** el valor se usa además para construir la ruta del archivo, así que la validación correcta sigue siendo una lista blanca de nombres permitidos (la constante `RUTAS`).

---

## 18. ✅ La redirección usaba el header Host

**Error:** con `Host: evil.example.com`, la respuesta devuelve `Location: http://evil.example.com/...`.

**Causa:** `BASE_URL` se construye en `config.php` con `$_SERVER['HTTP_HOST']`, que el cliente controla por completo.

```text
$ curl -H 'Host: evil.example.com' 'http://localhost/.../?controller=Auth&action=guardarRegistro' -d '...'
Location: http://evil.example.com/ProyectoBasket-Deploy/public/?controller=Auth&action=register
```

**✅ Solución aplicada:** no derivar la URL pública del request. `config.php` calcula solo la **ruta** desde `SCRIPT_NAME` (y la sanea con `preg_replace`), nunca el dominio:

```php
$script  = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$carpeta = str_replace('\\', '/', dirname($script));
$carpeta = preg_replace('~[^A-Za-z0-9_\-/]~', '', $carpeta) ?? '';
define('BASE_URL', rtrim($carpeta, '/') . '/');   // ruta, no dominio
```

Si en algún momento hiciera falta el dominio, habría que validar `HTTP_HOST` contra una lista blanca. Mientras tanto, **no abras el proyecto a Internet.**

---

## 19. ✅ Los campos enviados como array provocaban 500

**Error:** `POST` con `nombre[]=x` o `email[]=x` devuelve HTTP 500.

**Causa:** `trim()` y `strtolower()` reciben un array. En PHP 8 eso es un `TypeError`: la función espera `string`. La misma causa está en el Router con `?controller[]=x`.

**✅ Solución aplicada:** comprobar el tipo antes de usar el valor.

```php
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
    ? trim($_POST['nombre']) : '';
```

En el Router, la lista blanca quedó como constante `RUTAS` (controlador → acciones permitidas), y el rechazo usa `error()` con 404 en vez de `die()`:

```php
if (!array_key_exists($controlador, self::RUTAS)) {
    $this->error('Controlador no permitido.');
}
```

⚠️ **`method_exists()` también permitía llamar a métodos protegidos** (`?action=view`, `?action=requiereLogin`). Se sustituyó por `is_callable([$controlador, $accion])`, que solo acepta lo invocable desde fuera.

---

## 20. ✅ Los datos inválidos provocaban 500

**Error:** introducir datos demasiado largos, una fecha no válida o un `equipo_id` inexistente devuelve HTTP 500.

**Causa:** los controladores confían en el formulario HTML5 y no validan en el servidor. Lo que llega a PDO dispara una excepción que nadie captura.

| Caso | Error MySQL | Código |
|---|---|---|
| Texto más largo que el `VARCHAR` | `Data too long for column` | 1406 |
| `equipo_id` que no existe | `Cannot add or update a child row` | 1452 |
| Fecha como `31/12/2026` | `Incorrect datetime value` | 1292 |
| Puntos fuera de `INT UNSIGNED` | `Out of range value` | 1264 |

**✅ Solución aplicada:** validar en el servidor y, además, un `try/catch` que muestre un mensaje útil.

```php
if (mb_strlen($nombre) > 100) {
    $errores[] = 'El nombre no puede superar los 100 caracteres.';
}
$fecha = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['fecha'] ?? '');
if (!$fecha || DateTime::getLastErrors()['warning_count']) {
    $errores[] = 'La fecha no es válida.';
}
```

⚠️ `htmlspecialchars()` y `type="datetime-local"` son ayudas del navegador, **no** validación. Se pueden esquivar con `curl`.

---

## 21. ✅ La base no restringía posiciones ni dorsales

**Error:** se puede guardar `posicion = "ADMIN"` y dos jugadores con el mismo dorsal en el mismo equipo.

**Causa:** `jugadores.posicion` es `VARCHAR(50)` y `numero` es `INT UNSIGNED` sin más restricciones. No hay `UNIQUE (equipo_id, numero)` ni lista blanca de posiciones. `equipos.nombre` tampoco es único.

**✅ Solución aplicada:** validar la posición contra la lista del enunciado (Base, Escolta, Alero, Ala-Pívot, Pívot) y añadir la restricción en el esquema:

```sql
ALTER TABLE jugadores ADD UNIQUE KEY uq_jugador_numero (equipo_id, numero);
```

⚠️ `VARCHAR` se eligió a propósito (ver `schema-explicado.md`, sección 3) para no bloquear valores nuevos; la consecuencia es que **la validación tiene que vivir en el controlador**, no en la base.

---

## Resumen de lecciones

| Tema | Lección |
|---|---|
| Base de datos | Crear la base y hacer `USE` son pasos distintos |
| Idempotencia | Índices dentro del `CREATE TABLE`; `INSERT IGNORE` + ids fijos en seeds |
| HTML5 | `datetime-local` exige `T`; si el formato no coincide, el campo queda vacío sin avisar |
| Depuración | `wc -l` antes que `php -l` (un archivo vacío pasa la sintaxis) |
| JOIN | Una tabla repetida obliga a usar alias |
| Tipos | `''`, `0`, `'0'` y `NULL` son cuatro valores distintos |
| Seguridad | Prepared statements con `?` para SQL; `htmlspecialchars()` para HTML |
| Contraseñas | Se comparan con `password_verify()`, nunca con `===` |
| Flujo web | `header('Location:')` siempre con `exit`; PRG para formularios |
| Sesión | `session_regenerate_id(true)` tras login; los mensajes se consumen con `unset` |
| Rutas | `__DIR__` para includes |
| Salida | Escapar al imprimir, no al recibir; mejor aún, no imprimir el dato |
| URLs | `BASE_URL` no se deriva del header `Host`: lo controla el cliente |
| PHP 8 | `trim()` sobre un array es `TypeError`: comprobar `is_string()` |
| Confianza | El `required` del navegador es una ayuda visual, no una validación |
| Integridad | Lo que no restringe la base hay que validarlo en el controlador |
| Pruebas | Antes de "arreglar" el código, confirmar que el fallo no sea del test |