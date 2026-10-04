# Equipo (explicado)

**Archivo real:** `app/models/Equipo.php`
**Rol:** acceso a la tabla `equipos`. **El mejor ejemplo para entender el tratamiento de un `NULL`.**

## 1. El código completo

```php
class Equipo extends Model {
    protected string $tabla = 'equipos';

    public function crear(string $nombre, string $ciudad, ?int $fundacion = null): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, ciudad, fundacion)
                VALUES (:nombre, :ciudad, :fundacion)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
        if ($fundacion === null || $fundacion === 0) {
            $stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
        }
        return $stmt->execute();
    }

    public function actualizar(int $id, string $nombre, string $ciudad, ?int $fundacion = null): bool {
        $sql = "UPDATE {$this->tabla}
                SET nombre = :nombre, ciudad = :ciudad, fundacion = :fundacion
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($fundacion === null || $fundacion === 0) {
            $stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
        }
        return $stmt->execute();
    }

    public function obtenerTodos(): array {
        $sql = "SELECT * FROM {$this->tabla} ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

## 2. `?int $fundacion = null`: el parámetro anulable

**`?int` significa "un entero o `null`".** Es lo que permite diferenciar tres cosas que en PHP se parecen mucho:

| Lo que el usuario escribió | `$fundacion` recibido | En la base |
|---|---|---|
| `""` (no lo rellenó) | `null` | `NULL` |
| `"1947"` | `1947` | `1947` |
| No llega el campo | `null` (por el `= null`) | `NULL` |

⚠️ **Sin el `?`, no se podría pasar `null`.** Si el parámetro fuera `int $fundacion`, PHP convertiría `null` en `0` (con un aviso en PHP 8). El `?` es lo que hace posible "no informado".

**El `= null` es el valor por defecto.** Permite llamar así:

```php
$equipoModel->crear($nombre, $ciudad);          // fundacion = null
$equipoModel->crear($nombre, $ciudad, 1947);     // fundacion = 1947
```

## 3. El problema central: por qué `bindValue()` con tipo

```php
if ($fundacion === null || $fundacion === 0) {
    $stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
}
```

### ⚠️ Lo que pasa si no se declara el tipo

Con `execute([':fundacion' => null])`, PDO **adivina** el tipo. Y aquí está el problema: en algunas versiones y configuraciones, PDO envía `null` como un entero vacío, y MySQL lo recibe como **`0`**, no como `NULL`.

El resultado: si no rellenas el año de fundación, se guarda **`0`**, y el listado muestra "0". **Ese fue el bug real de este proyecto.** Está documentado en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 7.

**`PDO::PARAM_NULL` obliga a PDO a enviar un `NULL` de verdad**, con el tipo correcto en el protocolo.

### Por qué `bindValue()` y no `execute([...])`

```php
// Con execute() — no se puede declarar el tipo:
$stmt->execute([':nombre' => $nombre, ':ciudad' => $ciudad, ':fundacion' => $fundacion]);

// Con bindValue() — se declara el tipo de cada hueco:
$stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
```

`execute([...])` es más corto y cómodo para datos simples. `bindValue()` es más largo pero **permite especificar el tipo**, y sin el tipo no hay forma de garantizar que un `NULL` llegue intacto.

**Regla práctica:** usa `bindValue()` cuando tengas `null`, enteros que no quieres que se guarden como cadenas, o fechas. Usa `execute([...])` para el resto.

### Por qué `|| $fundacion === 0`

Porque el controlador ya convierte lo que viene:

```php
$fundacionInt = null;
if ($fundacion !== '') {
    $fundacionInt = (int)$fundacion;   // "" no entra; "abc" → 0
}
```

Entonces `$fundacionInt` puede ser `null` (vacío) **o** `0` (el usuario escribió "abc", que pasó a ser 0... aunque en este caso el rango 1800-2026 lo rechaza antes de llegar aquí).

⚠️ **La comprobación `$fundacion === 0` es una defensa extra.** Si por lo que sea llegara un `0`, se trata como "sin especificar", no como "fundado en el año 0". Es la misma idea que en `Jugador::crear()` con el número de camiseta.

**Y en `actualizar()` es aún más necesaria:** si editas un equipo que **no tenía** año de fundación (guardado como `NULL`) y no lo tocas, el formulario manda `fundacion=""`, el controlador lo convierte en `null`, y el modelo lo vuelve a guardar como `NULL`. Sin ese tratamiento, se convertiría en `0` al editar, y ese sería un error muy difícil de detectar.

## 4. `obtenerTodos()`: sobrescribir el método heredado

```php
public function obtenerTodos(): array {
    $sql = "SELECT * FROM {$this->tabla} ORDER BY nombre ASC";
    ...
}
```

**El `Model` ya tiene un `obtenerTodos()`.** Este lo sustituye. En PHP, cuando un método hijo tiene el mismo nombre que el del padre, **gana el del hijo**.

**¿Por qué no se modifico el `Model`?** Porque el `ORDER BY nombre` es específico de equipos. `Jugador` ordena por `nombre`, `Partido` por `fecha DESC`, y el `Model` genérico **no debería decidir** el orden de una tabla que no conoce. Si el `Model` metiera un orden, los tres modelos lo sobrescribirían igualmente.

**Consecuencia práctica:** cualquier parte del código que llame a `$equipoModel->obtenerTodos()` recibe la lista **ya ordenada alfabéticamente**, sin tener que pedirlo. Así lo usa `JugadoresController::crear()` para el desplegable (los equipos salen en orden alfabético).

⚠️ **El `ORDER BY` sin `LIMIT` ordena toda la tabla.** Con 4 equipos da igual. Con 100.000 equipos habría que paginar. Está bien decidido para esta escala.

## 5. La diferencia entre `crear()` y `actualizar()`

Son casi idénticos. La única diferencia estructural es el `WHERE id = :id`:

```sql
-- crear
INSERT INTO equipos (nombre, ciudad, fundacion) VALUES (:nombre, :ciudad, :fundacion)

-- actualizar
UPDATE equipos SET nombre = :nombre, ciudad = :ciudad, fundacion = :fundacion WHERE id = :id
```

**⚠️ Los placeholders del `SET` llevan `:` y los del `WHERE` también**, pero PDO los distingue por nombre, no por posición. Con named placeholders no puede haber confusión entre un valor del `SET` y uno del `WHERE`.

⚠️ **`actualizar()` no comprueba que exista la fila.** Si el `id` no existe, el `UPDATE` afecta 0 filas y devuelve `true`. El controlador no lo comprueba y muestra "actualizado correctamente". Documentado en `../Controllers/EquiposController-explicado.md` sección 7.

## 6. Esquema de la tabla

```sql
CREATE TABLE IF NOT EXISTS equipos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre del equipo',
  ciudad VARCHAR(100) NOT NULL COMMENT 'Ciudad de origen',
  fundacion YEAR NULL COMMENT 'Año de fundación (opcional)',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### `YEAR` en lugar de `INT` o `SMALLINT`

**`YEAR` es un tipo de fecha de MySQL**, no un número cualquiera. Tiene una consecuencia útil: si se le pasa `1947`, lo almacena como `1947`; si se le pasa `20250`, lo recorta o rechaza según el modo. MySQL lo controla.

⚠️ **Y una trampa:** `YEAR` puede devolver `0000` si se le pasa un valor inválido en algunos modos. Por eso el controlador valida el rango **antes** de guardar (1800 → año actual).

**`YEAR` vs `SMALLINT`:** `SMALLINT` es más flexible (permite 20250, o valores negativos). `YEAR` documenta la intención: "esto es un año". Para un campo de fundación, `YEAR` es más claro.

⚠️ **Desventaja de `YEAR`:** no admite años antes del rango de MySQL (1000-9999). Un equipo "fundado en 990" no se puede guardar. Para este dominio no importa.

### `fundacion YEAR NULL` — el `NULL` que da nombre al capítulo

`NULL` significa "no lo sé". No es 0, no es `''`, no es `0`. Es **la ausencia de información**.

⚠️ **En MySQL, `NULL` tiene una trampa histórica: `NULL = NULL` no es `TRUE`.** Ninguna comparación con `NULL` da `TRUE`, ni siquiera consigo mismo. Se usa `IS NULL`:

```php
// ❌ siempre falso
if ($equipo['fundacion'] == null) { ... }

// ✔️ correcto
if ($equipo['fundacion'] === null) { ... }
```

⚠️ **En PHP es al revés:** ahí `== null` funciona, porque `$var === null` es exactamente lo que comprueba. Esa diferencia entre PHP y SQL es una fuente clásica de confusiones.

**Y por eso en la vista hay que tratar el `null`:**

```php
<?= $equipo['fundacion'] !== null ? htmlspecialchars($equipo['fundacion']) : '—' ?>
```

El `—` (guion largo) es un convenio visual para "no hay dato". Es mejor que mostrar una celda vacía, porque el usuario distingue "vacío" de "que alguien olvidó rellenarlo".

⚠️ **Y el `!== null` es obligatorio en PHP 8.1+:** `htmlspecialchars(null)` ya no devuelve `''`, lanza un `TypeError`. Sin el `??`, una fila con `fundacion = NULL` **rompe la página**.

## 7. `equipos` es el centro del esquema

Es la tabla de la que dependen las otras dos:

```
equipos (esta)
    ▲
    ├── jugadores.equipo_id          FK, ON DELETE RESTRICT
    ├── partidos.equipo_local_id     FK, ON DELETE RESTRICT
    └── partidos.equipo_visitante_id FK, ON DELETE RESTRICT
```

⚠️ **Consecuencia práctica importante:** **no se puede borrar un equipo que tenga jugadores o partidos.** MySQL lanza el error 1451, y como `Database` configura `ERRMODE_EXCEPTION`, la excepción sube hasta la pantalla del usuario.

⚠️ **Este proyecto NO captura ese error.** Es la funcionalidad pendiente más visible del módulo: intentas borrar "Los Lakers" (que tiene 3 jugadores y 2 partidos) y ves un error de SQL.

Se documenta el arreglo en `../Controllers/EquiposController-explicado.md` sección 8. Lo resumido:

```php
// Comprobar antes de borrar
if ($this->equipoModel->tieneRegistros($id)) {
    $_SESSION['error'] = 'No se puede eliminar: el equipo tiene jugadores o partidos';
    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}
```

**Y la alternativa más simple:** en lugar de `ON DELETE RESTRICT`, usar `ON DELETE CASCADE`, que borra también los registros dependientes. ⚠️ **No es la mejor opción**: perderías 3 jugadores y 2 partidos sin preguntar. Para una app real, avisar es mejor que destruir en silencio.

## 8. Verificación

```bash
# Ver los equipos con su fundación, distinguiendo NULL de 0
mysql -u root liga_basquet --batch -e "
SELECT id, nombre, fundacion,
       IFNULL(fundacion, 'NULL') AS valor_real
FROM equipos;"

# Confirmar que ningún equipo tiene fundacion = 0
mysql -u root liga_basquet --batch -e "
SELECT COUNT(*) AS con_cero FROM equipos WHERE fundacion = 0;"
```

**Si `con_cero` es mayor que 0, hay equipos con la fundación guardada como `0` en vez de `NULL`.** Es la forma de comprobar si el bug del `NULL` está presente.

**Verificado en las pruebas:** tras crear un equipo sin año de fundación y recargar, el campo sigue vacío (no aparece un 0).

## 9. Errores frecuentes con este modelo

| Error | Síntoma | Solución |
|---|---|---|
| Sin `PDO::PARAM_NULL` | El año vacío se guarda como `0` | Declarar el tipo en `bindValue()` |
| `bindValue(':fundacion', null)` sin el tercer parámetro | El `NULL` llega como `''` o `0` | Añadir `PDO::PARAM_NULL` |
| Usar `execute([':fundacion' => null])` | Comportamiento incierto según la versión | `bindValue()` con tipo explícito |
| `(int)` en el controlador sobre `""` | Se pierde la diferencia vacío/0 | Comprobar `!== ''` antes de convertir |
| `htmlspecialchars($valor)` sin comprobar `null` | `TypeError` en PHP 8.1+ | `$valor ?? '—'` o `!== null` |
| `WHERE fundacion = null` en SQL | Nunca encuentra nada | `WHERE fundacion IS NULL` |
| No comprobar la existencia antes de `DELETE` | Error 1451 sin capturar | Comprobar dependientes primero |

---

**Archivos relacionados:** `../Controllers/EquiposController-explicado.md` · `../Core/Model-explicado.md` · `../../04-BD/schema-explicado.md` (secciones 4 y 5) · `../../08-Errores-y-Soluciones/Registro-de-errores.md` (sección 7) · `Jugador-explicado.md` (el mismo patrón, con `numero`)

**Siguiente paso →** `Jugador-explicado.md` (el `LEFT JOIN` y cómo se muestra el nombre del equipo).