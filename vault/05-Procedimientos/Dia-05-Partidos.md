# Día 05 - Módulo Partidos (doble JOIN + reglas de negocio)

**Fecha:** 2026-10-03
**Rama:** `main`
**Objetivo:** Construir el CRUD de partidos, el módulo con más lógica de negocio y la consulta más compleja del proyecto.
**Temas a comprender:** doble JOIN a la misma tabla, reglas de negocio con `===`, `INT UNSIGNED`, `datetime-local` y el conflicto de formatos con MySQL.

## 1. Contexto

Los jugadores introdujeron **una** relación. Los partidos introducen **dos referencias a la misma tabla** (`equipo_local_id` y `equipo_visitante_id`, ambos a `equipos`), y además tienen reglas de negocio que los otros módulos no tenían:

- El local y el visitante **no pueden ser el mismo equipo**.
- Los puntos **no pueden ser negativos**.
- La fecha es obligatoria.
- Hay que saber quién ganó.

Además aparece el `input type="datetime-local"`, que tiene una incompatibilidad de formato con MySQL que más tarde costó el error más difícil de detectar del proyecto.

## 2. Preparación

- Módulos de Equipos y Jugadores funcionando (hay 4 equipos con los que hacer partidos).
- Decidir el modelo de datos antes de escribir el código: dos FK, no una tabla puente.

## 3. Pasos realizados

### Paso 5.1: La consulta con doble JOIN

```sql
SELECT p.*,
       el.nombre AS nombre_local,
       ev.nombre AS nombre_visitante
FROM partidos p
LEFT JOIN equipos el ON el.id = p.equipo_local_id
LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
ORDER BY p.fecha DESC
```

**Aquí los alias no son opcionales.** `equipos` aparece dos veces en la consulta, así que MySQL no puede saber de qué tabla sale un `nombre`:

```
ERROR 1052 (23000): Column 'nombre' in field list is ambiguous
```

Se usaron alias **mnemotécnicos** a propósito:
- `p` = partidos
- `el` = **e**quipo **l**ocal
- `ev` = **e**quipo **v**isitante

Y **dos tipos de alias**, que es el punto clave:

| Tipo | Dónde | Para qué |
|---|---|---|
| De tabla | `p`, `el`, `ev` | Desambiguar la consulta |
| De columna | `AS nombre_local`, `AS nombre_visitante` | Distinguir el resultado en PHP |

⚠️ **Se necesitan los dos.** Los alias de tabla no bastan: sin `AS nombre_local`, ambas columnas se llamarían `nombre` en el array de PHP y solo se vería una.

⚠️ **En el `WHERE` también hay que calificar:** `WHERE p.id = :id`, no `WHERE id = :id`. Aquí solo hay un JOIN y funcionaría, pero calificar desde el principio evita el error en cuanto se añada otra tabla.

### Paso 5.2: `ORDER BY p.fecha DESC`

Los partidos **más recientes primero**. `DESC` invierte el orden natural de MySQL (ascendente por defecto).

⚠️ **Esto obliga a que `fecha` tenga índice** en la base, y lo tiene:
```sql
KEY idx_partido_fecha (fecha)
```
Sin índice, MySQL tendría que leer toda la tabla y ordenarla en memoria.

### Paso 5.3: El constructor del controlador

```php
public function __construct() {
    require_once '../app/models/Partido.php';
    require_once '../app/models/Equipo.php';
    $this->partidoModel = new Partido();
    $this->equipoModel  = new Equipo();
}
```

Igual que en Jugadores: dos modelos, uno para los registros y otro para los desplegables.

**Convención de nombres:** la propiedad es `$partidoModel` (singular) y el método `$this->partidoModel->obtenerTodos()` (plural en el método). La propiedad nombra el modelo, el método indica qué devuelve.

### Paso 5.4: La regla "el local y el visitante no pueden ser iguales"

```php
if ($equipoLocalId === $equipoVisitanteId) {
    $_SESSION['error'] = 'El equipo local y visitante no pueden ser iguales';
    header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
    exit;
}
```

⚠️ **Se usa `===` (identidad estricta), no `==`.** Con `===` solo son iguales si son del mismo tipo **y** el mismo valor. Como ambas variables se convergen a `int` al principio (`(int)($_POST[...] ?? 0)`), la comparación es 100% predecible.

Si alguien escribiera `==`, funcionaría en este caso concreto (ambos son int), pero es frágil: si mañana un valor llegara como string y otro como int, `==` haría una conversión implícita y el resultado cambiaría entre versiones de PHP.

⚠️ **Esta regla NO está en la base de datos.** Solo existe en PHP. Podría haberse puesto como restricción en el esquema:
```sql
CONSTRAINT chk_equipos_distintos CHECK (equipo_local_id <> equipo_visitante_id)
```
Lo haría más robusto (protegería también de inserts manuales desde phpMyAdmin). Está pendiente.

### Paso 5.5: Los puntos negativos

```php
if ($puntosLocal < 0 || $puntosVisitante < 0) {
    $_SESSION['error'] = 'Los puntos no pueden ser negativos';
    ...
}
```

Parece redundante porque la columna es `INT UNSIGNED`, que ya rechaza negativos. **Pero no lo es**, y por dos razones:

1. **El `UNSIGNED` da un error de SQL**, no un mensaje entendible. La validación convierte un error técnico en algo que el usuario puede corregir.
2. Es **defensa en profundidad**: si alguien cambiara la columna a `INT` (o usara otra base), la regla seguiría funcionando.

Es el mismo criterio que el email duplicado: **la validación da un mensaje comprensible; la restricción garantiza la integridad.**

⚠️ Además, el formulario lleva `min="0"` en el `<input type="number">`, así que el navegador tampoco deja escribir el `-`. Las tres barreras (HTML, PHP, base) apuntan al mismo objetivo.

### Paso 5.6: El campo `datetime-local`

```html
<input type="datetime-local" id="fecha" name="fecha" value="<?= ... ?>" required>
```

**El problema:** el navegador usa formato ISO con `T`, y MySQL usa espacio.

| Dónde | Formato |
|---|---|
| El navegador envía | `2026-10-12T19:00` |
| MySQL guarda | `2026-10-12 19:00:00` |
| MySQL devuelve | `2026-10-01 19:00:00` |
| El navegador **exige** para el campo | `2026-10-01T19:00` |

**Verificación importante:** se comprobó que MySQL y MariaDB **sí aceptan** el formato con `T` al insertar (`'2026-10-12T19:00'` se guarda como `2026-10-12 19:00:00`). Así que **no hace falta transformar el POST antes de guardar**.

El problema era **solo al mostrar** el valor en el formulario de edición. Se detalla en el Día 08 porque fue el error más difícil de detectar.

### Paso 5.7: Las validaciones en orden

El orden importa para la experiencia del usuario:

| # | Validación | Mensaje |
|---|---|---|
| 1 | Ambos equipos seleccionados | "Debe seleccionar ambos equipos" |
| 2 | Equipos distintos | "El equipo local y visitante no pueden ser iguales" |
| 3 | Fecha no vacía | "La fecha es obligatoria" |
| 4 | Puntos no negativos | "Los puntos no pueden ser negativos" |

Cada una muestra **un** mensaje y `exit`. El usuario ve el primer problema, lo corrige, reenvía, y entonces ve el siguiente. Mostrar los cuatro a la vez sería más rápido para el usuario pero mucho más complejo de implementar.

## 4. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/models/Partido.php` | crear | Doble JOIN a `equipos` con alias `el`/`ev`, `ORDER BY fecha DESC` |
| `app/controllers/PartidosController.php` | crear | CRUD con 4 validaciones de negocio |
| `app/views/partidos/index.php` | crear | Listado con marcador y resultado calculado (Gana Local / Visitante / Empate) |
| `app/views/partidos/crear.php` | crear | Formulario con 2 desplegables de equipos, fecha y 2 campos de puntos |
| `app/views/partidos/editar.php` | crear | Igual que crear, con los valores del partido precargados |
| `database/schema.sql` | editar | Tabla `partidos` con 2 FK, `INT UNSIGNED` en puntos, 3 índices |

## 5. Explicación técnica: por qué el resultado se calcula en la vista

En `partidos/index.php`:

```php
<?php if ($partido['puntos_local'] > $partido['puntos_visitante']): ?>
    <span class="badge badge-gana">Gana Local</span>
<?php elseif ($partido['puntos_local'] < $partido['puntos_visitante']): ?>
    <span class="badge badge-gana">Gana Visitante</span>
<?php else: ?>
    <span class="badge badge-empate">Empate</span>
<?php endif; ?>
```

**Decisión discutida.** ¿Debería esto estar en el controlador? Argumentos:

| Ubicación | A favor | En contra |
|---|---|---|
| En la vista (elegido) | Es una regla de **presentación** (qué etiqueta mostrar), y solo se usa en un sitio | Mezcla lógica en la capa de HTML |
| En el controlador | Más limpio, se podría reutilizar | Habría que pasar el resultado a la vista; parece innecesario |

**El criterio que se aplicó:** si la lógica aparece en **dos sitios**, se extrae a un método del modelo. Si aparece en uno solo y es de presentación, se queda en la vista.

⚠️ **Tres ramas, no dos.** El empate es un caso real: con solo `>` y `<`, los partidos empatados no mostrarían nada. La tercera rama (`else`) lo cubre. Está verificado que un partido 105-105 muestra "Empate".

⚠️ **`elseif` y no un segundo `if`.** `elseif` garantiza que solo se cumple una rama. Dos `if` independientes permitirían que las dos entradas se ejecuten.

## 6. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| Listado muestra los 4 partidos con nombres | ✅ |
| Muestra marcador (112 - 108) | ✅ |
| Detecta "Gana Local" | ✅ |
| Detecta "Gana Visitante" | ✅ |
| Crear partido 105-105 → muestra "Empate" | ✅ |
| Mismo equipo local y visitante → rechazado | ✅ |
| Puntos negativos → rechazado | ✅ |
| Equipos vacíos → rechazado | ✅ |
| Fecha vacía → rechazado | ✅ |
| Crear partido → se guarda en la base | ✅ |
| Editar partido → se actualiza | ✅ |
| Eliminar partido → desaparece | ✅ |
| `?id=abc` → "Partido no encontrado" | ✅ |

## 7. Commits realizados

```
feat: añade módulo de partidos con doble JOIN y reglas de negocio
```

## 8. Aprendizajes del día

1. **Una tabla repetida en un JOIN obliga a alias**, y hacen falta **dos tipos**: de tabla (para escribir la consulta) y de columna (para distinguir el resultado).
2. **`WHERE id` sin calificar es un error esperando:** funciona hasta que añades la segunda tabla.
3. **`===` en comparaciones de negocio.** Con `int` funciona igual, pero `===` no surprises.
4. **Una regla de negocio puede vivir en dos sitios.** En PHP da buenos mensajes; en la base garantiza la integridad. Lo ideal es tener las dos.
5. **El `UNSIGNED` en la base no sustituye la validación.** Uno da un error de SQL, el otro un mensaje útil.
6. **El orden de las validaciones es una decisión de UX.** El primer error que ve el usuario debería ser el más fácil de corregir.
7. **Los dropdowns de dos campos relacionados necesitan JS para ir cómodo** (elegir local y que el visitante no pueda ser el mismo), pero la validación real siempre va en el servidor.

## 9. Dudas pendientes

⚠️ **Limitaciones detectadas en este módulo:**

| # | Limitación | Gravedad |
|---|---|---|
| 1 | **No se comprueba que los equipos existan** → error de FK (1452) sin capturar, el usuario ve un error de SQL | **Alta** |
| 2 | No se valida el formato de la fecha (solo que no esté vacía) | Media |
| 3 | `$puntosLocal = (int)($_POST[...] ?? 0)` no distingue "no enviado" de "enviado 0" | Baja |
| 4 | `actualizar()` redirige a sitios distintos según el error (inconsistente) | Baja |
| 5 | Mensajes genéricos ("Datos inválidos") que no dicen qué campo falla | Baja |
| 6 | La regla "equipos distintos" no está en el esquema | Media |

**La 1 es la más grave.** Consecuencia: si un equipo se borra (cosa que el `RESTRICT` impide si tiene partidos, pero que podría ocurrir con datos manipulados) o si se manipula la URL, aparece un error de SQL crudo.

**La 3 tiene un matiz importante:** en un partido, `0` es un marcador legítimo. El `?? 0` impide distinguir "el usuario dejó el campo vacío" de "el usuario puso 0". La solución sería:
```php
$puntosLocal = $_POST['puntos_local'] ?? '';   // cadena
if ($puntosLocal === '') { /* error: obligatorio */ }
```

## 10. Próximos pasos

**Día 06 - Estadísticas:**
- El primer controlador que **no** es un CRUD: calcula la tabla de posiciones.
- Los datos (PJ, PG, PE, PP, GF, GC, DIF) **no existen en la base**: se derivan en PHP.
- Encadenar bucles: recopilar → acumular → calcular lo que depende de lo acumulado → ordenar con `usort()`.

**Día 07 - Documento técnico:**
- Cómo escribir un documento técnico que describa decisiones, no solo lo que hiciste.

**Día 08 - Verificación y corrección de errores:**
- Batería de pruebas funcionales.
- Corrección de los bugs detectados (incluido el del `datetime-local`).