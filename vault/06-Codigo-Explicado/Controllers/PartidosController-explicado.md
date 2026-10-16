# PartidosController (explicado)

**Archivo real:** `app/controllers/PartidosController.php`
**Rol:** CRUD de partidos. **Es el controlador con más lógica de negocio del proyecto.**

## 1. Por qué este es el más complejo

Un equipo es un nombre y una ciudad: campos independientes. Un partido es una **relación** entre dos entidades y tiene reglas:

- Los dos equipos deben existir.
- **El local y el visitante no pueden ser el mismo equipo.**
- Los puntos no pueden ser negativos.
- La fecha es obligatoria.
- Y hay que saber who's gana.

Cada una de esas reglas es un `if` con su mensaje. El controlador entero son validaciones.

## 2. El constructor

```php
public function __construct() {
    require_once '../app/models/Partido.php';
    require_once '../app/models/Equipo.php';
    $this->partidoModel = new Partido();
    $this->equipoModel  = new Equipo();
}
```

Igual que `JugadoresController`: dos modelos, uno para los registros y otro para las opciones del desplegable.

⚠️ **Ojo con el singular y el plural.** La propiedad es `$partidoModel` (en singular) y se llama `$this->partidoModel->obtenerTodos()`. El método está en plural porque devuelve todos. Es una convención: **la propiedad en singular, el método con su cardinalidad.**

## 3. `guardar()`: las cinco validaciones

```php
$equipoLocalId     = (int)($_POST['equipo_local_id'] ?? 0);
$equipoVisitanteId = (int)($_POST['equipo_visitante_id'] ?? 0);
$puntosLocal       = (int)($_POST['puntos_local'] ?? 0);
$puntosVisitante   = (int)($_POST['puntos_visitante'] ?? 0);
$fecha             = trim($_POST['fecha'] ?? '');
```

**Todos con `(int)` desde el principio**, incluido `$fecha` que no lleva `int` porque es texto.

⚠️ **`$puntosLocal = (int)($_POST['puntos_local'] ?? 0)` y el `?? 0`.** El `0` por defecto es peligroso: si el campo no llega, los puntos valen `0` en vez de "falta el dato", y un partido 0-0 se guardaría como si fuera válido. Aquí `0` es un marcador legítimo, así que no se puede distinguir "no enviado" de "enviado 0". Es una limitación del enfoque.

### La regla más importante: los equipos deben ser distintos

```php
if ($equipoLocalId === $equipoVisitanteId) {
    $_SESSION['error'] = 'El equipo local y visitante no pueden ser iguales';
    header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
    exit;
}
```

⚠️ **Se usa `===`, no `==`.** Es esencial: las dos variables ya son `int` thanks al `(int)` del principio, así que `==` también funcionaría. Pero si alguien quita un `(int)` y queda `('1', '1')` (strings), `==` compararía string con string y funcionaría, mientras que con `('1', 1)` una comparación con `==` en PHP 7 diría `true` (conversión de tipos) y en PHP 8 diría `false`. **Con `===` no hay sorpresas**: solo son iguales si son el mismo tipo y el mismo valor.

**Verificado:** enviar `equipo_local_id=1&equipo_visitante_id=1` muestra el error y **no guarda nada**.

**⚠️ Esta regla no está en la base de datos.** Es solo validación de PHP. Podría haberse puesto como `CHECK (equipo_local_id <> equipo_visitante_id)` en el esquema, y sería más robusta. Está en `../../04-BD/schema-explicado.md` como mejora posible.

### Los puntos negativos

```php
if ($puntosLocal < 0 || $puntosVisitante < 0) {
    $_SESSION['error'] = 'Los puntos no pueden ser negativos';
    ...
}
```

⚠️ **Esto parece redundante con el tipo `INT UNSIGNED` de la base**, que ya rechaza los negativos. Pero no lo es, y la razón es que sin esta validación el usuario vería un error de SQL.

Comparte la misma idea que el email duplicado: **la validación existe para dar un mensaje comprensible, la restricción para garantizar la integridad.**

Además, el formulario ya lleva `min="0"` en el `<input type="number">`, así que el navegador no deja escribir el `-`. La validación en PHP cubre el caso de alguien que manipule la petición.

**Verificado:** enviar `puntos_local=-5` muestra el error y no guarda.

### La fecha

```php
$fecha = trim($_POST['fecha'] ?? '');
...
if ($fecha === '') {
    $_SESSION['error'] = 'La fecha es obligatoria';
    ...
}
```

**No basta con comprobar que no esté vacía.** El proyecto valida además que la fecha **exista de verdad** con `fechaValida()`:

```php
$fecha = fechaValida($fechaBruta);
if ($fechaBruta === '') {
    $errores[] = 'La fecha es obligatoria';
} elseif ($fecha === null) {
    $errores[] = 'La fecha indicada no es válida';
}
```

`fechaValida()` (en `app/core/helpers.php`) prueba varios formatos con `DateTime::createFromFormat()` y **mira `getLastErrors()`**, porque `createFromFormat()` acepta en silencio fechas imposibles como el 31 de febrero (las "arregla" moviendo el día). Con esto, `fecha=31/02/2026` se rechaza con un mensaje en vez de llegar a MySQL.

### ⚠️ Un bug real (ya resuelto): no se comprobaba que los equipos existieran

Las validaciones comprueban que los ids sean `> 0` y **distintos**, pero no que un equipo con ese id exista de verdad:

```php
if ($equipoLocalId <= 0 || $equipoVisitanteId <= 0) { ... }   // ambos existen
if ($equipoLocalId === $equipoVisitanteId) { ... }           // son distintos
// ❌ NO se comprueba: ¿existe el equipo 99?
```

Si se envía `equipo_local_id=99&equipo_visitante_id=2`, la base **rechaza** el INSERT por clave foránea (error 1452). Antes el usuario veía un error de SQL; ahora se comprueba antes:

```php
if ($this->equipoModel->obtenerPorId($local) === null) {
    $errores[] = 'El equipo local seleccionado no existe';
}
if ($this->equipoModel->obtenerPorId($visitante) === null) {
    $errores[] = 'El equipo visitante seleccionado no existe';
}
```

La base sigue siendo la garantía final (el FK no se puede saltar), pero el mensaje ya es el correcto. Y como red de seguridad, el manejador global de `index.php` traduce el 1452 a "El registro seleccionado no existe" si alguna vez se escapa.

## 4. `editar()`: el `datetime-local`

Este método tiene un detalle que costó un error real del proyecto.

```php
public function editar(array $params): void {
    $this->requiereLogin();
    $id = (int)($params['id'] ?? 0);
    $partido = $this->partidoModel->obtenerPorId($id);
    if ($partido === null) {
        $_SESSION['error'] = 'Partido no encontrado';
        header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
        exit;
    }
    $equipos = $this->equipoModel->obtenerTodos();
    $old = $_SESSION['old_partido'] ?? [];
    unset($_SESSION['old_partido']);
    $this->view('partidos/editar', [
        'titulo' => 'Editar Partido',
        'partido' => $partido,
        'equipos' => $equipos,
        'old' => $old
    ]);
}
```

### El bug: `value="2026-10-01 19:00"` en vez de `2026-10-01T19:00`

En `app/views/partidos/editar.php`:

```php
<?php /* ❌ VERSIÓN CON EL BUG */ ?>
<input type="datetime-local" name="fecha"
       value="<?= htmlspecialchars(substr($partido['fecha'], 0, 16)) ?>">

<?php /* ✔️ VERSIÓN CORRECTA */ ?>
<input type="datetime-local" name="fecha"
       value="<?= htmlspecialchars(str_replace(' ', 'T', substr($partido['fecha'], 0, 16))) ?>"
       required>
```

| Dónde | Formato de la fecha |
|---|---|
| La base de datos | `2026-10-01 19:00:00` (espacio) |
| `<input type="datetime-local">` | `2026-10-01T19:00` (letra `T`) |

**Cuando el `value` no coincide exactamente, el navegador lo descarta en silencio.** No hay error de PHP, ni aviso en la consola: el HTML es válido, el navegador simplemente ignora el valor y el campo aparece vacío.

Por eso hacía falta **dos** conversiones:
- `substr(..., 0, 16)` quita los segundos.
- `str_replace(' ', 'T', ...)` cambia el espacio por la `T`.

**Lo importante del error dentro del error:** la primera solución aplicada fue **solo** `substr()`. Parecía correcta leyendo el código, pero el resultado seguía teniendo un espacio y el campo seguía vacío. **Solo se detectó abriendo el formulario en el navegador y mirando el campo real.** Está documentado en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 4.

### El `str_replace` es "defensivo" a propósito

```php
str_replace(' ', 'T', substr($old['fecha'] ?? $partido['fecha'], 0, 16))
```

**Fíjate en `$old['fecha'] ?? $partido['fecha']`:** si el usuario ya envió el formulario con un error (y `$old['fecha']` viene del POST), se usa ese; si no, el de la base.

Como el navegador **ya** manda el formato con `T`, `$old['fecha']` viene con `T` y el `str_replace` no hace nada. Pero si por lo que sea llegara con espacio (un `curl`, otra versión del formulario), el `str_replace` lo arregla. **Funciona en los dos casos sin comprobar nada.**

La misma protección se aplicó en `partidos/crear.php`.

### ⚠️ El `<select>` de equipos tiene un detalle extra

Los dos desplegables (local y visitante) se llenan con los mismos 4 equipos, así que el usuario **puede** elegir el mismo dos veces. La interfaz no lo impide: solo lo detecta el servidor al enviar.

Se podría mejorar con JavaScript (que al elegir local, el visitante no pueda ser el mismo), pero lo correcto sigue siendo validarlo en el servidor. Un `disabled` en el `<option>` elegido es un extra de usabilidad, no de seguridad.

**Verificado:** el servidor rechaza el mismo equipo con el mensaje correcto.

## 5. `actualizar()`: las mismas reglas, más el id

```php
if ($id <= 0 || $equipoLocalId <= 0 || $equipoVisitanteId <= 0) {
    $_SESSION['error'] = 'Datos inválidos';
    header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
    exit;
}
if ($equipoLocalId === $equipoVisitanteId) {
    $_SESSION['error'] = 'Equipos no pueden ser iguales';
    header('Location: ' . BASE_URL . '?controller=Partidos&action=editar&id=' . $id);
    exit;
}
if ($fecha === '' || $puntosLocal < 0 || $puntosVisitante < 0) {
    $_SESSION['error'] = 'Datos inválidos (fecha/puntos)';
    ...
}
```

**Diferencias con `guardar()`:**

| Aspecto | `guardar()` | `actualizar()` |
|---|---|---|
| Valida el id | No | Sí (`$id <= 0`) |
| Comprueba que exista | No | **No** (misma limitación) |
| Ubicación del error | `action=crear` | `action=index` o `action=editar&id=$id` |

⚠️ **La tercera columna es inconsistente:** si falta el nombre de un equipo, redirige al listado; si los equipos son iguales, redirige al formulario. Lo coherente sería redirigir siempre al formulario de edición, para que el usuario no pierda los datos escritos.

**⚠️ El mensaje "Datos inválidos" no dice qué campo falla.** Es un mensaje perezoso. Lo ideal sería algo como "La fecha y los puntos no pueden estar vacíos ni ser negativos". Limitación del código actual.

## 6. `eliminar()`

```php
public function eliminar(array $params): void {
    $this->requiereLogin();
    $id = (int)($params['id'] ?? 0);
    if ($id > 0) {
        $this->partidoModel->eliminar($id);
        $_SESSION['exito'] = 'Partido eliminado correctamente';
    } else {
        $_SESSION['error'] = 'ID inválido';
    }
    header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
    exit;
}
```

Es el más simple de los tres CRUD porque **los partidos no son referenciados por ninguna otra tabla**: `partidos` es una hoja (solo `equipos` está por encima). Por eso borrar un partido nunca falla por clave foránea.

⚠️ **Y sí hay confirmación, pero del lado del cliente.** El listado usa `onclick="return confirm('¿Eliminar este partido?')"`. Evita el borrado accidental, pero `curl` lo ignora: el servidor borra sin preguntar. Igual que en los otros módulos.

## 7. El cálculo del resultado (está en la vista)

La lógica de "quién ganó" **no está en el controlador ni en el modelo**: está en la vista. Es una decisión de diseño, y aquí es aceptable porque es una regla de presentación (mostrar una etiqueta), no una regla de datos.

```php
<?php if ($partido['puntos_local'] > $partido['puntos_visitante']): ?>
    <span class="badge badge-gana">Gana Local</span>
<?php elseif ($partido['puntos_local'] < $partido['puntos_visitante']): ?>
    <span class="badge badge-gana">Gana Visitante</span>
<?php else: ?>
    <span class="badge badge-empate">Empate</span>
<?php endif; ?>
```

**Tres ramas porque el empate es un caso real.** Un error típico sería usar solo dos (`> y <`), y entonces los partidos empatados no mostrarían nada.

**⚠️ `elseif` y no dos `if`.** Si se escribieran dos `if` separados, con empate se entrarían en los dos (porque `112 > 108` es `true` y `112 < 108` también... bueno, no en este caso, pero sí con dos condiciones mal escritas). `elseif` garantiza que solo se cumple una rama.

**Verificado:** los partidos de prueba generan "Gana Local", "Gana Visitante" y, al crear uno 105-105, "Empate".

**Si la lógica se usara en dos sitios** (por ejemplo, en el listado y en las estadísticas), habría que extraerla a un método. Como solo aparece en el listado, se queda ahí.

## 8. Limitaciones conocidas de este controlador

| # | Limitación (ya resuelta) | Gravedad | Arreglo aplicado |
|---|---|---|---|
| 1 | No se comprobaba que los equipos existieran (error de FK sin capturar) | **Alta** | `obtenerPorId()` de ambos antes de guardar |
| 2 | No se validaba el formato de la fecha | Media | `fechaValida()` con `DateTime::createFromFormat()` |
| 3 | `$puntosLocal = (int)(...)` no distinguía "vacío" de "0" | Baja | Se valida el texto con `ctype_digit()` antes de convertir |
| 4 | `actualizar()` redirigía a sitios distintos según el error | Baja | Redirige siempre al formulario de edición |
| 5 | Mensajes genéricos ("Datos inválidos") | Baja | Cada error indica el campo |
| 6 | ⚠️ `confirm()` del cliente no protegía nada en servidor | Baja | Formulario POST con token CSRF |
| 7 | No se comprobaba que existiera el partido en `actualizar()` | Baja | `validarId()` antes |

⚠️ **La 1 era la que más se notaba.** Al registrar un partido con un equipo inexistente salía un error de SQL. Ahora sale "El equipo local seleccionado no existe".

---

**Archivos relacionados:** `../../../app/models/Partido.php` (el doble JOIN) · `../../../app/views/partidos/editar.php` (el fix del `datetime-local`) · `EquiposController-explicado.md` · `../../08-Errores-y-Soluciones/Registro-de-errores.md` (sección 4) · `../../04-BD/schema-explicado.md`

**Siguiente paso →** `EstadisticasController-explicado.md` (el único controlador sin CRUD: solo calcula).