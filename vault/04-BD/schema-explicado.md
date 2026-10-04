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
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB ...;
```

`fundacion YEAR NULL` es opcional a propósito: hay equipos sin año de fundación conocido. `NULL` significa "no lo sé", que es distinto de `0` (que significaría "fundado en el año 0"). Esta distinción se respeta también en PHP (ver `Equipo-explicado.md`).

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

⚠️ **Pero "falla" aquí significa HTTP 500, no un mensaje amable.** `Database` configura `PDO::ERRMODE_EXCEPTION`, así que el error 1451 se lanza como `PDOException` y **ningún controlador la captura**: el usuario ve una pantalla en blanco. Es la limitación más visible del proyecto, y la reasona estar documentada en `06-Codigo-Explicado/Controllers/EquiposController-explicado.md` sección 8, con las dos formas de arreglarla.

⚠️ **Los datos sí están a salvo** (de eso se trata `RESTRICT`), pero la experiencia de usuario es un error 500 en vez de "No se puede eliminar: el equipo tiene jugadores registrados".

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