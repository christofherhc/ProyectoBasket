# Datos de Prueba (seeds.sql)

> Describe el archivo `database/seeds.sql` **en su versión final verificada**. La diferencia con la versión antigua está en la sección 8: importa, porque explica por qué los datos se pueden reimportar sin duplicarse.

## 1. Para qué sirve

Un schema vacío no sirve para probar nada: no hay nada que listar ni que relacionar. `seeds.sql` llena la base con datos de ejemplo realistas para que, apenas importes, la aplicación se vea terminada.

Es un "seed" = semilla = datos iniciales que hacen crecer el proyecto.

## 2. Lo primero: `USE liga_basquet`

```sql
USE liga_basquet;
```

Igual que en `schema.sql`: selecciona la base de trabajo. Si lo importas desde phpMyAdmin con la base ya seleccionada no hace falta, pero si lo haces por consola evita el error "No database selected".

## 3. Usuarios de prueba

```sql
INSERT IGNORE INTO usuarios (id, nombre, email, password) VALUES
(1, 'Usuario Demo', 'demo@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Administrador', 'admin@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
```

### Credenciales

| Email | Contraseña | Rol |
|---|---|---|
| `demo@liga.com` | `password` | Usuario normal |
| `admin@liga.com` | `password` | Administrador |

> Estos dos usuarios tienen exactamente los mismos permisos. El campo "rol" no existe en la base: en esta versión toda persona que se registra ve la liga completa.

### Sobre el hash

`$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi` es un **hash bcrypt** de la palabra `password`. Nótese que no dice `password`: dice `$2y$10$...`.

- `$2y$` → algoritmo bcrypt.
- `10` → coste (2^10 = 1024 rondas). Más alto = más seguro y más lento.
- El resto → sal + resultado, todo en una sola cadena.

**Nunca escribas contraseñas en claro en un archivo de seeds.** El hash se genera una vez y se copia. Para crear un hash nuevo:

```php
echo password_hash('miClave', PASSWORD_DEFAULT);
```

Y para comprobar una contraseña contra el hash guardado (esto es lo que hace `password_verify()` en el login):

```php
password_verify('password', $hashDeLaBase); // true
```

**Nunca compares contraseñas con `===` ni con `==`.** Un hash no es una contraseña: es una huella. La comparación correcta es `password_verify()`.

## 4. Equipos (4)

```sql
INSERT IGNORE INTO equipos (id, nombre, ciudad, fundacion) VALUES
(1, 'Los Lakers', 'Los Angeles', 1947),
(2, 'Boston Celtics', 'Boston', 1946),
(3, 'Chicago Bulls', 'Chicago', 1966),
(4, 'Golden State Warriors', 'San Francisco', 1946);
```

Con 4 equipos puedes comprobar las relaciones de los otros módulos:
- 2 equipos → 1 partido
- 4 equipos → 12 jugadores (3 cada uno)

## 5. Jugadores (12, 3 por equipo)

```sql
INSERT IGNORE INTO jugadores (id, nombre, numero, posicion, equipo_id) VALUES
(1, 'LeBron James', 23, 'Alero', 1),
(2, 'Anthony Davis', 3, 'Pívot', 1),
...
(12, 'Draymond Green', 23, 'Ala-Pívot', 4);
```

Reparto:

| Equipo | Jugadores |
|---|---|
| 1. Los Lakers | LeBron James (23), Anthony Davis (3), Austin Reaves (15) |
| 2. Boston Celtics | Jayson Tatum (0), Jaylen Brown (7), Kristaps Porzingis (8) |
| 3. Chicago Bulls | DeMar DeRozan (11), Nikola Vucevic (9), Coby White (0) |
| 4. Golden State Warriors | Stephen Curry (30), Klay Thompson (11), Draymond Green (23) |

Las **posiciones** cubren los cinco valores que usa el formulario: `Base`, `Escolta`, `Alero`, `Ala-Pívot`, `Pívot`. También hay un número `0` a propósito (Coby White, Tatum), para que se vea que el formulario acepta `min="0"`.

## 6. Partidos (4)

```sql
INSERT IGNORE INTO partidos (id, fecha, equipo_local_id, equipo_visitante_id, puntos_local, puntos_visitante) VALUES
(1, '2026-10-01 19:00:00', 1, 2, 112, 108),
(2, '2026-10-02 20:00:00', 3, 4, 98, 105),
(3, '2026-10-05 19:30:00', 2, 3, 110, 95),
(4, '2026-10-06 21:00:00', 4, 1, 120, 118);
```

Los marcadores están **alternados a propósito** para que el módulo Estadísticas tenga de todo:

| Partido | Local | Visitante | Resultado | Para las estadísticas |
|---|---|---|---|---|
| 1 | Lakers 112 | Celtics 108 | Gana Local | Celtics pierde de local |
| 2 | Bulls 98 | Warriors 105 | Gana Visitante | Bulls pierde de local, Warriors gana visitante |
| 3 | Celtics 110 | Bulls 95 | Gana Local | Celtics gana de local |
| 4 | Warriors 120 | Lakers 118 | Gana Local | Warriors gana de local |

Con esto la tabla de posiciones muestra **1-2** para Celtics (1 local, 1 visitante) y **1-1** para el resto: si el cálculo de ganados es incorrecto, se nota enseguida.

**Sobre la fecha:** aquí se escribe con espacio (`2026-10-01 19:00:00`), que es el formato de MySQL. El `input type="datetime-local"` del navegador usa `T` en vez de espacio. Esa diferencia es la causa de un error real del proyecto, documentado en `schema-explicado.md` sección 9 y en `08-Errores-y-Soluciones/Registro-de-errores.md`.

## 7. Cómo importarlos

**Consola (desde la raíz del proyecto):**

```bash
mysql -u root < database/schema.sql
mysql -u root < database/seeds.sql
```

**phpMyAdmin:** Importa primero `schema.sql` y después `seeds.sql` (selecciona la base `liga_basquet` en la pestaña de la izquierda antes de importar el segundo).

## 8. ⚠️ `INSERT IGNORE` e IDs fijos: la diferencia con la versión antigua

Este es el punto clave de este archivo.

**La primera versión usaba esto:**

```sql
-- ❌ VERSIÓN ANTIGUA (NO LA USES)
INSERT INTO usuarios (nombre, email, password) VALUES
('Usuario Demo', 'demo@liga.com', '...');
```

Sin `id` y sin `IGNORE`. Al importarlo dos veces fallaba:

```
ERROR 1062 (23000): Duplicate entry 'demo@liga.com' for key 'email'
```

Es decir, los datos de prueba **solo se podían cargar una vez**. Y como los `INSERT` de la versión antigua no fijaban el `id`, cada importación generaba ids distintos, lo que rompía las referencias `equipo_id = 1` de los jugadores.

**La versión final hace dos cosas:**

```sql
INSERT IGNORE INTO jugadores (id, nombre, numero, posicion, equipo_id) VALUES
(1, 'LeBron James', 23, 'Alero', 1),
```

1. **Escribe el `id` a mano** (1, 2, 3...) → los jugadores siempre apuntan al equipo 1, 2, 3, 4 aunque el AUTO_INCREMENT siga avanzando. Si borras los datos e importas otra vez, siguen siendo coherentes.
2. **Usa `INSERT IGNORE`** → si el registro ya existe (misma clave primaria), se salta esa fila sin error y continúa con las demás.

**Qué significa `IGNORE` exactamente:** ignora *solo* los errores de clave duplicada. Si te equivocas al escribir (una columna que no existe, un valor inválido), **el error sigue apareciendo**. No silencia todos los problemas.

**Limitación que debes conocer:** los datos de prueba nunca se actualizan. Si cambias `fundacion` de 'Los Lakers' a 1949 en el archivo y reimportas, MySQL no lo actualiza: mantiene 1947, porque la fila ya existe y `IGNORE` la salta. Para actualizarlos tienes dos opciones:

```sql
-- Opción A: borrar primero y reimportar
mysql -u root liga_basquet -e "DELETE FROM partidos; DELETE FROM jugadores; DELETE FROM equipos;"
mysql -u root < database/seeds.sql

-- Opción B: UPSERT (reemplaza si existe, inserta si no)
INSERT INTO equipos (id, nombre, ciudad, fundacion) VALUES
(1, 'Los Lakers', 'Los Angeles', 1949)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), ciudad=VALUES(ciudad), fundacion=VALUES(fundacion);
```

## 9. Cómo comprobar que los datos quedaron bien

```bash
mysql -u root liga_basquet --batch -e "
SELECT (SELECT COUNT(*) FROM usuarios)   AS usuarios,
       (SELECT COUNT(*) FROM equipos)    AS equipos,
       (SELECT COUNT(*) FROM jugadores)  AS jugadores,
       (SELECT COUNT(*) FROM partidos)   AS partidos;"
```

Salida esperada:

```
usuarios	equipos	jugadores	partidos
2	4	12	4
```

Si.importas los scripts y después ejecutas esa consulta una segunda vez, los números **deben seguir siendo 2, 4, 12, 4**. Si cambian, tu `seeds.sql` es la versión antigua.

Y para verificar que los JOIN funcionan:

```bash
mysql -u root liga_basquet --batch -e "
SELECT j.nombre AS jugador, e.nombre AS equipo
FROM jugadores j
JOIN equipos e ON e.id = j.equipo_id
LIMIT 5;"
```

## 10. Advertencia

Estos datos son **solo para desarrollo y pruebas**. Las contraseñas son públicas (`password`). Nunca subes un seeds.sql con claves reales a un repositorio: usa un `.gitignore` o un archivo aparte para producción.

⚠️ **El hash de `password` es uno de los más conocidos que hay.** Aparece en miles de repositorios y en todas las listas de contraseñas descartadas, así que no hace falta ni descifrarlo: se reconoce al momento. Cualquiera que tenga el repositorio puede entrar como `demo@liga.com` o `admin@liga.com`.

Por eso la aplicación **no debe exponerse a Internet** con estos datos de prueba. Si algún día se publica, lo primero es cambiar las contraseñas de los usuarios de prueba y poner una contraseña propia, larga, a los que se registran.

---

**Archivos relacionados:** `../../database/seeds.sql` · `schema-explicado.md` · `Modelo-ER.md` · `../05-Procedimientos/Dia-01-Inicializar-proyecto.md`

**Siguiente paso →** `06-Codigo-Explicado/Core/Database-explicado.md` (cómo se conecta PHP con esta base).