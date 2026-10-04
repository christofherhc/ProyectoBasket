# Día 04 - Módulo Jugadores (CRUD con relación)

**Fecha:** 2026-10-03
**Rama:** `main`
**Objetivo:** Construir el CRUD de jugadores, el primer módulo con una **relación con otra tabla**.
**Temas a comprender:** `LEFT JOIN` con alias, `<select>` populate desde PHP, campos opcionales y `NULL`, formularios que se rellenan tras un error.

## 1. Contexto

El módulo de Equipos (Día 03) era "plano": una tabla, un CRUD, ningún dato de otra tabla. Los jugadores son el primer paso a algo más realista: **cada jugador pertenece a un equipo**.

Eso obliga a resolver dos cosas nuevas:

1. Cómo traer el nombre del equipo junto con el jugador (JOIN).
2. Cómo mostrar una lista de equipos en un formulario (`<select>`) sin escribir los equipos a mano en el HTML.

## 2. Preparación

- Base de datos importada (schema + seeds).
- XAMPP con Apache y MySQL arrancados.
- Módulo de Equipos funcionando (para tener datos que relacionar).

## 3. Pasos realizados

### Paso 4.1: Decidir qué hace el modelo

Decisión clave: **¿el modelo trae solo jugadores, o jugadores + equipo?**

Se eligió traer ambos, con un JOIN. La alternativa (dos consultas y buscar en un `foreach` dentro de la vista) obligaría a escribir lógica de búsqueda en la capa de presentación, que es exactamente lo que se quiere evitar.

### Paso 4.2: Crear `app/models/Jugador.php`

```php
class Jugador extends Model {
    protected string $tabla = 'jugadores';

    public function obtenerTodos(): array {
        $sql = "SELECT j.*, e.nombre AS nombre_equipo
                FROM {$this->tabla} j
                LEFT JOIN equipos e ON e.id = j.equipo_id
                ORDER BY j.nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

Decisiones dentro de este método:

| Decisión | Motivo |
|---|---|
| `SELECT j.*` y no `SELECT *` | Evita colisión de columnas entre las dos tablas |
| `AS nombre_equipo` | Nombre claro para el dato que viene de `equipos` |
| `LEFT JOIN` y no `JOIN` | Que un jugador no desaparezca de la lista si su equipo falta |
| `ORDER BY j.nombre ASC` | Orden alfabético decided en el modelo |

**Y hay que sobrescribir `obtenerPorId()` también**, no solo `obtenerTodos()`. Si solo se sobrescribe el primero, el formulario de editar recibiría los datos sin `nombre_equipo` y el desplegable no marcaría el equipo actual.

### Paso 4.3: El `NULL` en `numero` y `posicion`

Ambos campos son opcionales. El `Modelo` aplica el patrón aprendido en Equipos:

```php
if ($numero === null || $numero === 0) {
    $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
}
$stmt->bindValue(':posicion', $posicion === '' ? null : $posicion, PDO::PARAM_STR);
```

⚠️ Sin `PDO::PARAM_NULL`, un número vacío se guardaría como `0` y el listado mostraría "0" en vez de "-". Ese bug ya se había resuelto en Equipos, así que aquí se aplicó el mismo patrón desde el principio.

### Paso 4.4: Crear `app/controllers/JugadoresController.php`

Se copia el esqueleto de `EquiposController` y se añade **un modelo más**:

```php
require_once '../app/models/Jugador.php';
require_once '../app/models/Equipo.php';
$this->jugadorModel = new Jugador();
$this->equipoModel  = new Equipo();
```

`Equipo` se necesita en `crear()` y `editar()` para llenar el `<select>`.

**El bug del que hubo que acordarse:** en `editar()`, es fácil olvidar `$equipos = $this->equipoModel->obtenerTodos();`. El síntoma es desconcertante: el `<select>` sale vacío, el jugador aparece sin equipo, y al guardar el servidor responde "el equipo es obligatorio".

### Paso 4.5: Validaciones

```php
$nombre   = trim($_POST['nombre'] ?? '');
$numero   = $_POST['numero'] ?? '';          // sin (int), a propósito
$equipoId = (int)($_POST['equipo_id'] ?? 0);

if (empty($nombre) || $equipoId <= 0) { ... }

$numeroInt = null;
if ($numero !== '') {
    $numeroInt = (int)$numero;
    if ($numeroInt < 0 || $numeroInt > 99) { ... }
}
```

**Lección del día:** para campos numéricos se valida con **comparaciones de rango**, no con `empty()`. Con `empty()`, un `equipo_id=abc` pasaría el filtro ((int)'abc' es 0, y `empty('abc')` es `false`), y llegaría un 0 a la base.

El rango `0-99` se debe a que en básquetbol las camisetas van de 0 a 99. El `0` es válido (hay jugadores con esa camiseta en los datos de prueba), así que el mínimo es `< 0` y no `<= 0`.

### Paso 4.6: Las tres vistas

**`index.php`** — listado con el nombre del equipo (viene del JOIN):
```php
<td><?= htmlspecialchars($jugador['nombre']) ?></td>
<td><?= htmlspecialchars($jugador['numero'] ?? '—') ?></td>
<td><?= htmlspecialchars($jugador['posicion'] ?? '—') ?></td>
<td><?= htmlspecialchars($jugador['nombre_equipo'] ?? 'Sin equipo') ?></td>
```

⚠️ Los `??` no son opcionales: `numero`, `posicion` y `nombre_equipo` pueden ser `NULL`, y `htmlspecialchars(null)` es un `TypeError` en PHP 8.1+.

**`crear.php` y `editar.php`** — el `<select>` con los equipos:
```php
<select name="equipo_id" id="equipo_id" required>
    <option value="">Selecciona un equipo</option>
    <?php foreach ($equipos as $equipo): ?>
        <option value="<?= (int)$equipo['id'] ?>"
            <?= (isset($old['equipo_id']) && (int)$old['equipo_id'] === (int)$equipo['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($equipo['nombre']) ?>
        </option>
    <?php endforeach; ?>
</select>
```

Tres detalles que hay que explicar:

1. **La primera opción es `value=""`.** Fuerza a elegir conscientemente. Sin ella, el navegador seleccionaría el primer equipo por defecto y el usuario podría guardar el jugador en el equipo equivocado sin darse cuenta.
2. **Los `(int)` en la comparación del `selected`.** El id viene de la base como string. Comparar `'1' == 1` depende de conversiones implícitas; con `(int)` en ambos lados la comparación es siempre fiable.
3. **`htmlspecialchars` en el nombre.** Aunque venga de la base, se escapa siempre.

## 4. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/models/Jugador.php` | crear | Modelo con `LEFT JOIN` a equipos para traer `nombre_equipo` |
| `app/controllers/JugadoresController.php` | crear | CRUD con 2 modelos y validación del número (0-99) |
| `app/views/jugadores/index.php` | crear | Listado con JOIN y `onclick="return confirm(...)"` en eliminar |
| `app/views/jugadores/crear.php` | crear | Formulario con `<select>` de equipos |
| `app/views/jugadores/editar.php` | crear | Igual que crear, con `selected` en el equipo actual |

## 5. Explicación técnica: `LEFT JOIN` y por qué `AS`

```sql
SELECT j.*, e.nombre AS nombre_equipo
FROM jugadores j
LEFT JOIN equipos e ON e.id = j.equipo_id
```

**Un `JOIN` une dos tablas por una columna común.** `ON e.id = j.equipo_id` dice: "busca en `equipos` la fila cuyo `id` sea el `equipo_id` del jugador".

**Dos decisiones clave:**

**a) `SELECT j.*` en vez de `SELECT *`.** Si se pusiera `SELECT *`, MySQL devolvería el `nombre` de jugadores y el `nombre` de equipos, ambos con la misma clave. En PHP quedaría **un solo** `nombre` (el último gana), y `id` también se pisaría. Con `j.*` solo vienen las columnas de jugadores, y la de equipos se pide **explícitamente con alias**: `e.nombre AS nombre_equipo`.

**b) `LEFT JOIN` en vez de `JOIN`.** Con `JOIN` (interno), si un jugador tuviera un `equipo_id` que no existe, la fila **desaparecería** del listado. Con `LEFT JOIN` la fila se conserva y `nombre_equipo` llega como `NULL`.

En este proyecto el caso no se da (`ON DELETE RESTRICT` impide dejar jugadores sin equipo), pero `LEFT JOIN` no cuesta nada y protege de un descuido futuro. **Regla: cuando el dato de la derecha es accesorio, usa `LEFT JOIN`.**

## 6. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| El listado muestra los 12 jugadores con su equipo | ✅ |
| JOIN muestra "Los Lakers" en LeBron James | ✅ |
| El `<select>` se puebla con 4 opciones + la vacía | ✅ |
| Crear jugador → aparece en el listado | ✅ |
| Editar jugador → el desplegable marca el equipo actual | ✅ |
| Número vacío → se guarda como `NULL`, se muestra "—" | ✅ |
| Número = 0 → se guarda como `NULL` | ✅ (comportamiento discutido) |
| Número = 150 → rechazado con mensaje | ✅ |
| `equipo_id` vacío → rechazado con mensaje | ✅ |
| `equipo_id=abc` → rechazado (con `(int)`) | ✅ |
| Eliminar jugador → desaparece | ✅ |
| Nombre con `<script>` → se muestra como texto | ✅ |

## 7. Commits realizados

```
feat: añade CRUD completo de jugadores con asignación a equipo
```

## 8. Aprendizajes del día

1. **Un JOIN necesita alias en cuanto hay dos tablas**, aunque solo haya una relación. Obligarse a calificar cada columna (`j.nombre`) evita sorpresas al añadir la segunda.
2. **`SELECT *` con JOIN es una trampa**: las columnas con el mismo nombre se pisan. Usa `tabla.*` y alias para lo que venga de la otra tabla.
3. **`LEFT JOIN` para datos accesorios**: prefiero ver un `NULL` que una fila desaparecida.
4. **Los campos numéricos se validan con rangos, no con `empty()`**: `empty('abc')` es `false`, y `(int)'abc'` es `0`.
5. **El `NULL` de un campo opcional se pierde en la vista**: `htmlspecialchars(null)` es un error fatal en PHP 8.1+. El `?? '-'` no es decoración.
6. **Un `<select>` no es validación**: solo ofrece las opciones. La comprobación real está en el servidor.

## 9. Dudas pendientes

⚠️ **Limitaciones que se detectaron al hacer este módulo y siguen abiertas:**

| # | Limitación | Gravedad |
|---|---|---|
| 1 | No se valida que la `posicion` sea una de las 5 válidas (solo el `<select>` lo limita) | Media |
| 2 | No se valida la unicidad del número por equipo (pueden existir dos jugadores con la camiseta 23 en el mismo equipo) | Media |
| 3 | `actualizar()` no comprueba que el jugador exista | Baja |
| 4 | No se valida `strlen($nombre) > 100` | Baja |

**La 2 es un bug de datos real.** Se resolvería con una consulta que excluya el propio id al editar (el "truco" del parámetro `ignorarId`), o con un `UNIQUE (equipo_id, numero)` en el esquema.

## 10. Próximos pasos

**Día 05 - Módulo Partidos:**
- El módulo más complejo: **dos** JOIN a la misma tabla (`equipos` como local y como visitante), lo que obliga a usar alias dobles.
- Regla de negocio nueva: el local y el visitante **no pueden ser el mismo equipo**.
- Validación de puntos no negativos (y `INT UNSIGNED` en la base).
- El campo `datetime-local`, con el problema de formato entre MySQL (espacio) y el navegador (letra `T`).