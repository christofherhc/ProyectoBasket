# Schema Explicado (schema.sql)

> **Este documento describe el archivo `database/schema.sql` tal como quedó en la versión final y verificada del proyecto.** Si tu copia del `schema.sql` tiene los índices al final con `CREATE INDEX` aparte, está desactualizada: lee la sección 9.

## 1. Propósito del archivo

`schema.sql` es el "plano de la obra": define la base de datos y las 4 tablas de la aplicación. Si lo ejecutas sobre una base vacía, obtienes exactamente la estructura que espera el código PHP.

Motor: **InnoDB** · Charset: **utf8mb4** · Compatible con **MySQL y MariaDB** (XAMPP).

## 2. Las dos primeras instrucciones: crear y seleccionar la base

```sql
CREATE DATABASE IF NOT EXISTS liga_basquet
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE liga_basquet;
```

Estas dos líneas resuelven uno de los errores más comunes al empezar: **"No database selected"**.

- `CREATE DATABASE IF NOT EXISTS` → si la base ya existe no da error, sigue adelante.
- `USE liga_basquet` → le dice al servidor "todas las órdenes siguientes aplícalas en esta base". Sin esto, un `CREATE TABLE usuarios` falla si no hay ninguna base seleccionada.

**Por qué `utf8mb4`:** es el charset que soporta emoji, acentos y todo Unicode. Por eso en la interfaz verás 🏀 y textos como "Pívot" sin romperse.

## 3. Tabla `usuarios` — autenticación

```sql
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del usuario',
  email VARCHAR(150) NOT NULL UNIQUE COMMENT 'Email único para login',
  password VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada con password_hash()',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Puntos clave:

| Campo | Por qué así |
|---|---|
| `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | Identificador único que se genera solo al insertar. Nunca lo escribes tú. |
| `email ... UNIQUE` | La base **rechaza** dos usuarios con el mismo email. Es la primera barrera contra registros duplicados, antes incluso de validar en PHP. |
| `password VARCHAR(255)` | 255 porque bcrypt genera cadenas de 60 caracteres, pero si más adelante cambias a argon2 ocupa más. No guardamos la contraseña real, solo su hash. |
| `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP` | MySQL rellena la fecha si no la envías. |

## 4. Tabla `equipos`

```sql
CREATE TABLE IF NOT EXISTS equipos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre del equipo',
  ciudad VARCHAR(100) NOT NULL COMMENT 'Ciudad de origen',
  fundacion YEAR NULL COMMENT 'Año de fundación (opcional)',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_equipo_nombre (nombre)
) ENGINE=InnoDB ...;
```

`fundacion YEAR NULL` es opcional a propósito: hay equipos sin año de fundación conocido. `NULL` significa "no lo sé", que es distinto de `0` (que significaría "fundado en el año 0"). Esta distinción se respeta también en PHP (ver `Equipo-explicado.md`).

`UNIQUE KEY uq_equipo_nombre (nombre)` impide que dos equipos se llamen igual. Antes no lo había y el resultado era visible: en la tabla de posiciones aparecían dos filas con el mismo nombre que el usuario no podía distinguir. La ciudad no sirve para desempatar porque la tabla de posiciones no la muestra.

## 5. Tabla `jugadores` — donde aparece la primera clave foránea

```sql
CREATE TABLE IF NOT EXISTS jugadores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del jugador',
  numero INT UNSIGNED NULL COMMENT 'Número de camiseta',
  posicion VARCHAR(50) NULL COMMENT 'Posición: Base/Escolta/Alero/Ala-Pívot/Pívot',
  equipo_id INT UNSIGNED NOT NULL COMMENT 'Equipo al que pertenece (FK)',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_jugador_equipo (equipo_id),
  CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_id)
    REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB ...;
```

### La clave foránea

`equipo_id` **no es un número suelto**: es una promesa de que ese equipo existe en `equipos`. Gracias a esto la base de datos te protege de errores que PHP no siempre detecta:

- No puedes crear un jugador con `equipo_id = 999` (error 1452).
- No puedes borrar un equipo que tenga jugadores (error 1451, por `ON DELETE RESTRICT`).

### `ON DELETE RESTRICT` vs `ON DELETE CASCADE`

| Opción | Qué pasa al borrar el equipo |
|---|---|
| `CASCADE` | Borra también sus jugadores automáticamente. **Peligroso**: pierdes datos sin avisar. |
| `RESTRICT` | **No te deja borrar** el equipo si tiene jugadores. **Elegido aquí.** |

Elegimos `RESTRICT` a propósito: es más seguro que la aplicación decida qué hacer. Si un usuario intenta borrar un equipo con jugadores, el `DELETE` falla y **no se destruye nada**.

✅ **Y ahora el usuario ve un mensaje, no una pantalla en blanco.** `EquiposController::eliminar()` consulta cuántos jugadores y partidos tiene el equipo antes de intentar el borrado, así que casi nunca se llega a la excepción. Y si se llega (por una carrera entre dos peticiones, o por una consulta nueva que no valida), el manejador global de `public/index.php` traduce el error 1451 a un mensaje entendible. Ver `06-Codigo-Explicado/Controllers/EquiposController-explicado.md` y el registro de errores #16.

⚠️ **Los datos siguen a salvo** (de eso se trata `RESTRICT`) y ahora el mensaje es "No se puede eliminar: tiene 3 jugador(es) y 2 partido(s) registrados".

`ON UPDATE CASCADE` significa que si someday cambiaras el `id` de un equipo, las referencias se actualizan solas.

### El índice

`KEY idx_jugador_equipo (equipo_id)` es como el índice de un libro: no guarda datos extra, solo permite encontrar filas rápido. Lo necesitas porque el listado de jugadores siempre busca `WHERE equipo_id = ?`.

**Importante:** el índice y la clave foránea se escriben **dentro** del paréntesis de `CREATE TABLE` (separados por comas), no como sentencias sueltas al final. Ver sección 9.

## 6. Tabla `partidos` — dos claves foráneas a la misma tabla

```sql
CREATE TABLE IF NOT EXISTS partidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATETIME NOT NULL COMMENT 'Fecha y hora del encuentro',
  equipo_local_id INT UNSIGNED NOT NULL COMMENT 'Equipo local (FK)',
  equipo_visitante_id INT UNSIGNED NOT NULL COMMENT 'Equipo visitante (FK)',
  puntos_local INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Puntos equipo local',
  puntos_visitante INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Puntos equipo visitante',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_partido_local (equipo_local_id),
  KEY idx_partido_visitante (equipo_visitante_id),
  KEY idx_partido_fecha (fecha),
  CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id)
    REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_partido_visitante FOREIGN KEY (equipo_visitante_id)
    REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB ...;
```

Tres cosas que Aquí se ven por primera vez:

1. **Dos FK a la misma tabla.** En los JOIN hay que usar alias obligatorios (`el` y `ev`), o MySQL se quejará con "ambiguous column name".
2. **`INT UNSIGNED NOT NULL` en los puntos.** Firma de que en esta base **no existen los números negativos**. Por eso el controlador valida los puntos antes de guardar y por qué el formulario no admite el símbolo menos.
3. **Tres índices.** Uno por cada FK y otro por `fecha`, porque el módulo Partidos lista los partidos del más reciente al más antiguo (`ORDER BY p.fecha DESC`).

`DEFAULT 0` en los puntos significa que un partido programado empieza 0-0 aunque no se informen los puntos.

## 7. `IF NOT EXISTS` en todas las tablas

Permite reejecutar el script sin romper nada. Pero **ojo**: `IF NOT EXISTS` solo evita el error de "ya existe"; no modifica una tabla que ya tiene columnas distintas. Si cambias una columna, tienes que borrar la tabla a mano (o la base entera) y reimportar. Para empezar de cero en clase:

```bash
mysql -u root -e "DROP DATABASE liga_basquet;"
mysql -u root < database/schema.sql
```

## 8. Cómo se comprueba que quedó bien

```bash
mysql -u root liga_basquet -e "SHOW TABLES;"
# +--------------------+
# | Tables_in_liga_basquet |
# +--------------------+
# | equipos   |
# | jugadores |
# | partidos  |
# | usuarios  |
# +--------------------+
```

Y para ver que las claves foráneas están realmente creadas:

```bash
mysql -u root liga_basquet -e "SHOW CREATE TABLE partidos\G"
```

En `partidos` debes ver **2** líneas `CONSTRAINT` (`fk_partido_local` y `fk_partido_visitante`). Si no aparecen, tu script está desactualizado. En `jugadores` hay **1** más (`fk_jugador_equipo`), así que el schema completo tiene **3** claves foráneas.

## 9. ⚠️ La diferencia entre la versión antigua y la final

La **primera versión** de este proyecto tenía los índices como sentencias sueltas al final del archivo:

```sql
-- ❌ VERSIÓN ANTIGUA (NO LA USES)
CREATE INDEX idx_jugador_equipo ON jugadores(equipo_id);
CREATE INDEX idx_partido_local ON partidos(equipo_local_id);
CREATE INDEX idx_partido_visitante ON partidos(equipo_visitante_id);
CREATE INDEX idx_partido_fecha ON partidos(fecha);
```

**Por qué se cambió:** con esa forma, la **segunda** vez que importas el script MySQL responde `Duplicate key name 'idx_jugador_equipo'` y se detiene. Los índices solo admiten crearse **dentro** del `CREATE TABLE`. Además la versión antigua no tenía `CREATE DATABASE` ni `USE`, así que había que crear la base a mano primero.

La versión final (secciones 2, 5 y 6) usa `KEY` dentro del `CREATE TABLE`, `CREATE DATABASE IF NOT EXISTS` y `USE`, por lo que **se puede importar tantas veces como quieras sin errores**.

**Lección general:** cuando un script de base de datos no es idempotente, la causa casi siempre es un elemento declarado fuera de su bloque (índices, constraints, triggers).

## 9-bis. ⚠️ Los índices únicos y las bases que ya existían

Después de la auditoría se añadieron dos índices únicos: `uq_jugador_camiseta (equipo_id, numero)`, para que un dorsal no se repita dentro del mismo equipo, y `uq_equipo_nombre (nombre)`, para que dos equipos no se llamen igual. Aquí está el problema que dejaba la solución a medias.

⚠️ **`CREATE TABLE IF NOT EXISTS` no actualiza una tabla que ya existe.** Su nombre lo dice: "crea la tabla si no hay ninguna". Si el usuario ya tenía la base creada con una versión anterior del script, la tabla `jugadores` ya estaba y **el `CREATE TABLE` se saltaba entero**: el índice inline nunca se creaba, y el usuario que creyera que la restricción estaba activa seguía pudiendo meter dorsales repetidos sin enterarse.

Por eso el final de `schema.sql` tiene un bloque aparte que consulta `information_schema` y añade cada índice con `ALTER TABLE` si falta. Los dos índices siguen el mismo patrón: dorsal por equipo y nombre de equipo.

### ⚠️ Y el `ALTER` también puede fallar

Añadir el índice a una base antigua con datos ya guardados es exactamente el caso donde el `ALTER` revienta:

```
ERROR 1062 (23000): Duplicate entry '1-23' for key 'uq_jugador_camiseta'
```

⚠️ **Un `ALTER` que falla aborta todo el `import`.** MySQL detiene el script en el primer error si el cliente está en modo estricto (XAMPP lo está). El usuario se queda **sin las tablas de la sección 5**, es decir, con la base a medias, y el mensaje de error habla de un índice que ni siquiera estaba usando.

⚠️ **Esto no es hipotético: es lo que pasa con cualquier base real.** Los datos de prueba están limpios, pero una base usada un tiempo puede tener dorsales repetidos sin problema, porque hasta ahora nada lo impedía.

La solución es comprobar los duplicados **antes** de intentar el `ALTER`, y si los hay, saltar el índice y explicar cuál es el problema:

```sql
SET @hay_duplicados = (
  SELECT COUNT(*) FROM (
    SELECT equipo_id, numero FROM jugadores
     WHERE numero IS NOT NULL
     GROUP BY equipo_id, numero
    HAVING COUNT(*) > 1
  ) AS repetidos
);
```

Y al final, en lugar de fallar en silencio:

```sql
SELECT equipo_id, numero, COUNT(*) AS repetidos,
       GROUP_CONCAT(id ORDER BY id) AS ids_afectados
  FROM jugadores
 WHERE numero IS NOT NULL
 GROUP BY equipo_id, numero
HAVING COUNT(*) > 1;
```

que en la base de prueba produce exactamente esto:

```
+----------+--------+-----------+---------------+
| equipo_id | numero | repetidos | ids_afectados |
+----------+--------+-----------+---------------+
|        1 |     23 |         2 | 1,13          |
+----------+--------+-----------+---------------+
```

⚠️ **Se omite la restricción en lugar de inventarse una solución.** La alternativa sería borrar los repetidos automáticamente, pero un script de esquema **no debe decidir qué filas son las correctas**. Decirle al usuario "el dorsal 23 del equipo 1 está en los jugadores 1 y 13" es información útil; borrar uno de los dos sin que nadie lo haya pedido es destruir datos.

⚠️ **`WHERE numero IS NOT NULL` es obligatorio.** El dorsal es opcional, así que hay jugadores con `NULL`. En un `UNIQUE`, varios `NULL` en la misma columna **no** se consideran duplicados, así que se pueden insertar sin problema (se comprobó: dos jugadores sin dorsal en el mismo equipo conviven). Si el filtro no estuviera, el `GROUP BY` contaría los `NULL` como repetidos y saltaría el índice sin motivo.

⚠️ **La migración se probó en los tres escenarios,** no solo en el fácil (y con los dos índices a la vez):

| Base de partida | Índice creado | Import |
|---|---|---|
| Vacía (esquema nuevo) | Inline en el `CREATE TABLE` | Sin errores |
| Antigua **sin** duplicados | Sí, con el `ALTER` | Sin errores |
| Antigua **con** duplicados | No, se omite y avisa | **Sin errores**, exit 0 |

⚠️ **Con duplicados en las dos tablas a la vez**, la importación sigue terminando bien y avisa de cada problema por separado:

```
AVISO: no se creó el índice único uq_jugador_camiseta porque hay dorsales repetidos dentro de un mismo equipo.
equipo_id  numero  repetidos  ids_afectados
1          5       2          1,2

AVISO: no se creó el índice único uq_equipo_nombre porque hay equipos con el mismo nombre.
nombre       repetidos  ids_afectados
Los Lakers  2          1,2
```

⚠️ **El mensaje de cada tabla dice qué fila sobra.** Con el nombre del equipo duplicado, el usuario sabe exactamente qué dos equipos elegir entre ellos; con el dorsal, qué dos jugadores.

⚠️ **El último caso es el importante.** Un esquema que se importa bien es mejor que uno que se importa "hasta donde da error".

## 10. Resumen de las 4 tablas

```
usuarios   (id, nombre, email UNIQUE, password, creado_en)

equipos    (id, nombre, ciudad, fundacion NULL, creado_en)
    ▲            ▲
    │            └── partidos.equipo_local_id      (RESTRICT)
    │            └── partidos.equipo_visitante_id  (RESTRICT)
    └── jugadores.equipo_id                        (RESTRICT)
```

`usuarios` no tiene relaciones: es independiente del resto del dominio. Cualquiera puede ver los datos de la liga.

---

**Archivos relacionados:** `../../database/schema.sql` · `Datos-prueba.md` · `Modelo-ER.md` · `../08-Errores-y-Soluciones/Registro-de-errores.md`

**Siguiente paso →** `Datos-prueba.md` (los datos de ejemplo que se importan después de este script).