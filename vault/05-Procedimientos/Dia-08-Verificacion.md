# Día 08 - Verificación, corrección de bugs y documentación real

**Fecha:** 2026-10-04
**Rama:** `main`
**Objetivo:** Comprobar que **todo** el sistema funciona, corregir los bugs reales encontrados y dejar la documentación coherente con el código final.
**Temas a comprender:** pruebas funcionales con `curl`, diferencia entre bug y error del test, entorno de pruebas aislado, e idempotencia.

## 1. Contexto

Con los 6 módulos implementados, la pregunta ya no es "¿funciona?" sino **"¿funciona *todo*?"**. Nunca se había probado el sistema de forma sistemática: se había navegado por las páginas, sí, pero sin comprobar cada validación ni cada regla de negocio.

Este día tiene tres partes:
1. Montar un entorno de pruebas.
2. Detectar y corregir bugs.
3. Corregir la documentación que había quedado desactualizada.

## 2. Preparación: el entorno de pruebas aislado

⚠️ **Problema:** no se podía depender de MySQL de XAMPP. Intentando arrancar el panel apareció:

```
Can't create/write to file '/opt/lampp/var/Chris-PC.err' (Errcode: 13 "Permission denied")
```

MySQL necesita escribir en `/opt/lampp/var`, y el usuario actual no tenía permiso.

**Solución: un entorno aislado en `/tmp`.**

```bash
# 1. Base de datos temporal en un directorio propio
mysql_install_db --datadir=/tmp/opencode/mysqldata
mysqld --datadir=/tmp/opencode/mysqldata \
        --socket=/tmp/opencode/mysql.sock \
        --port=3307 --skip-networking=0 &

# 2. Copia de la aplicación con el puerto de BD cambiado
cp -r /opt/lampp/htdocs/ProyectoBasket-Deploy /tmp/opencode/testapp

# 3. Servidor PHP propio
php -S 127.0.0.1:8899 -t /tmp/opencode/testapp/public &
```

**Resultado:** tres procesos aislados que **no tocan** la base de datos real ni el Apache de XAMPP. Se pueden romper y tirar sin consecuencias.

⚠️ **El coste:** hay que cambiar el `DB_PORT` en la copia temporal (`Database.php` no leía el puerto). Se hizo solo en la copia, nunca en el proyecto real.

⚠️ **Lección general:** cuando el entorno de desarrollo no está disponible, **construye uno limpio** en vez de pelearte con el que tienes. Es más rápido y más seguro.

## 3. La batería de pruebas

### Paso 8.1: Por qué `curl` y no clicar

Probar a mano funciona para 2-3 páginas. Con 6 módulos, 6 métodos por módulo y 5 validaciones cada uno, el clic manual no escala: hay que automatizar.

```bash
# Login correcto
curl -sL -c ck.txt -b ck.txt \
     -d "email=demo@liga.com&password=password" \
     "http://127.0.0.1:8899/?controller=Auth&action=login" | grep -c "Listado de Equipos"
```

### Paso 8.2: La primera corrida (y la lección más importante)

**Resultado de la primera corrida: 40 pruebas pasadas, 13 fallidas.**

La reacción natural es mirar los 13 fallos y empezar a "arreglar" el código. **Eso habría sido un error.**

⚠️ **Antes de tocar el código, hay que determinar si el fallo es del código o del test.** Y en este caso, **12 de los 13 fallos eran del propio script de pruebas.**

### Paso 8.3: Los tres errores del script de pruebas

**a) `curl` no seguía las redirecciones.** Después de un POST, el controlador responde `302 Location: ...`. Sin `-L`, `curl` se quedaba en la redirección y nunca veía la página de destino:

```bash
# ❌ nunca ve el mensaje de éxito/error
curl -s -d "email=...&password=mal" "$B/?controller=Auth&action=login"

# ✔️ sí lo ve
curl -sL -d "email=...&password=mal" "$B/?controller=Auth&action=login"
```

**b) Faltaba el cookie jar.** El mensaje "Credenciales incorrectas" viaja en la sesión. Al redirigir, `curl` tenía que reenviar la cookie `PHPSESSID`; sin `-c`/`-b` abría una sesión nueva en cada salto y **la sesión "se perdía"**:

```bash
# ❌ la cookie no persiste entre peticiones
curl -sL -d "..." "$B/?..."

# ✔️ cookie jar: se guarda y se reenvía
curl -sL -c /tmp/ck.txt -b /tmp/ck.txt -d "..." "$B/?..."
```

**c) Aserciones equivocadas.** Una comprobación esperaba ver el login tras pedir `action=guardar` por GET, cuando lo correcto es redirigir al formulario de creación. **El test asumía mal, no la aplicación.**

### Paso 8.4: La suite reescrita

Se reescribió el script con las tres correcciones, **más un paso que faltaba por completo**: reiniciar la base de datos antes de cada ejecución.

```bash
$MYSQL -e "DROP DATABASE IF EXISTS liga_basquet;"
$MYSQL < database/schema.sql
$MYSQL < database/seeds.sql
```

⚠️ **Por qué era imprescindible.** La primera corrida había creado "Test Team", "Test Player", un usuario y un partido. Sin el reseteo:
- "El listado muestra 5 equipos en vez de 4"
- "El usuario `uno@liga.com` ya existe"

`INSERT IGNORE` no restauraba el estado original: se saltaba las filas porque ya existían. **Los datos de prueba y la preparación de un entorno de prueba son dos cosas distintas y necesitan herramientas distintas.**

**Resultado: 81 de 81 pruebas superadas.**

## 4. Bugs reales encontrados

### Bug 1: el `datetime-local` no se rellenaba al editar ⚠️ EL IMPORTANTE

**Síntoma:** el campo "Fecha y hora" del formulario de edición **vacío**, aunque el partido existía y se listaba bien.

**Causa:** incompatibilidad de formatos.

| Dónde | Formato |
|---|---|
| MySQL (`DATETIME`) | `2026-10-01 19:00:00` (espacio) |
| `<input type="datetime-local">` | `2026-10-01T19:00` (**letra T**) |

Cuando el `value` no coincide **exactamente**, el navegador **lo descarta en silencio**. No hay error de PHP, ni aviso en la consola: el HTML es válido, el navegador simplemente lo ignora.

**La solución que falló primero:**
```php
value="<?= htmlspecialchars(substr($partido['fecha'], 0, 16)) ?>"
```
Parecía correcta leyendo el código: quita los segundos. Pero el resultado seguía siendo `2026-10-01 19:00` **con espacio**, y el campo seguía vacío.

⚠️ **Este es el punto más importante del día.** El error **solo se detectó abriendo el formulario en el navegador y mirando el campo real**. Leyendo el código, `substr()` parecía resolver el problema. Es un recordatorio de que algunas fallas solo existen en el mundo real, no en el texto.

**La solución correcta:**
```php
value="<?= htmlspecialchars(str_replace(' ', 'T', substr($partido['fecha'], 0, 16))) ?>"
```

Dos conversiones, y las dos hacen falta:
- `substr(..., 0, 16)` → quita los segundos
- `str_replace(' ', 'T', ...)` → cambia el separador

Se aplicó también en `partidos/crear.php`, para cuando `$old['fecha']` llegara con espacio.

**Verificación adicional (importante):** se comprobó que MySQL y MariaDB **sí aceptan** el formato con `T` al *guardar*. Insertar `'2026-10-12T19:00'` en una columna `DATETIME` funciona y guarda `2026-10-12 19:00:00`. Así que **no hace falta transformar el POST**: el problema era solo de visualización.

### Bug 2: la vista de partidos estaba vacía

**Síntoma:** `/partidos` devolvía una pantalla completamente en blanco. Los otros módulos funcionaban.

**Causa:** `app/views/partidos/index.php` **estaba vacío (0 bytes)**. Un `require` de un archivo vacío no da error: simplemente no imprime nada.

**Diagnóstico rápido:**
```bash
wc -l app/views/partidos/index.php    # → 0 líneas = encontrado
php -l app/views/partidos/index.php   # "No syntax errors" (¡pasa igual!)
```

⚠️ **Un archivo vacío aprueba la revisión de sintaxis**, porque no hay nada que analizar. Por eso `wc -l` va antes que `php -l`.

**Solución:** reescribir la vista completa.

### Bug 3: fallos por datos sucios entre ejecuciones

**Síntoma:** al repetir la suite, fallos como "el listado muestra 5 equipos".

**Causa:** la suite creaba datos y no los borraba.

**Solución:** el `DROP DATABASE` + reimportación antes de cada corrida (ver paso 8.4).

## 5. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/views/partidos/editar.php` | editar | **Fix del `datetime-local`**: `str_replace(' ', 'T', substr(...))` |
| `app/views/partidos/crear.php` | editar | Misma protección en el formulario de creación |
| `app/views/partidos/index.php` | crear | Vista completa del listado (estaba vacía) |
| `vault/04-BD/schema-explicado.md` | editar | Reescrito: documentaba la versión antigua del schema |
| `vault/04-BD/Datos-prueba.md` | editar | Reescrito: documentaba `INSERT` sin `IGNORE` ni ids fijos |
| `vault/08-Errores-y-Soluciones/Registro-de-errores.md` | editar | 15 errores con causa y solución (antes 7, y una solución era incorrecta) |
| `vault/06-Codigo-Explicado/**` | editar | 15 archivos reescritos a fondo (antes: 3-18 líneas) |
| `vault/05-Procedimientos/Dia-04..08` | editar | 5 procedimientos completos (antes: 2-7 líneas) |
| `vault/07-Diario-de-Aprendizaje/Dia-04..08` | editar | 5 diarios completos |
| `doc/practica_liga.md` | editar | Corregida la explicación del `datetime-local` |
| `doc/ejemplo_documento_tecnico.md` | editar | Corregida la explicación del `datetime-local` |

## 6. Explicación técnica: la suite final (12 bloques)

| # | Bloque | Pruebas | Cubre |
|---|---|---|---|
| 1 | Acceso y estáticos | 6 | Login carga, campos, CSS 200, rutas inválidas |
| 2 | Protección de rutas | 5 | Los 4 módulos redirigen sin sesión |
| 3 | Autenticación | 5 | Password/email incorrectos, login OK, redirecciones |
| 4 | Equipos (CRUD) | 11 | Listado, crear, leer en BD, editar, eliminar, volver a 4 |
| 5 | Jugadores (CRUD) | 9 | JOIN, desplegable, crear, editar, eliminar, volver a 12 |
| 6 | Partidos (lógica) | 13 | Marcador, Gana Local/Visitante/Empate, **datetime con T**, 4 validaciones |
| 7 | Estadísticas | 6 | Tabla de posiciones, PJ/PG, datos coherentes con los partidos |
| 8 | Registro | 11 | Crear usuario, **hash bcrypt**, no texto plano, 5 validaciones |
| 9 | Logout | 1 | Sesión cerrada no accede |
| 10 | Seguridad | 10 | **2 SQL injections**, **XSS**, IDs inválidos, GET en guardar |
| 11 | Aislamiento de sesiones | 2 | Una sesión nueva no ve datos de otra |
| 12 | Integridad de datos | 4 | Sin huérfanos, ningún partido con el mismo equipo |

**Total: 81 pruebas.**

⚠️ **La prueba 12 no está en el navegador.** Comprueba cosas que solo se ven por consola SQL:
```bash
mysql -u root liga_basquet --batch -e "
SELECT COUNT(*) FROM jugadores j
LEFT JOIN equipos e ON e.id = j.equipo_id
WHERE e.id IS NULL;"                                    # debe dar 0

mysql -u root liga_basquet --batch -e "
SELECT COUNT(*) FROM partidos
WHERE equipo_local_id = equipo_visitante_id;"          # debe dar 0
```

**Comprobar la integridad referencial requiere hablar con la base**, no con el HTML. Un listado puede verse perfecto y aun así la base estar inconsistente.

### Las pruebas de seguridad, en detalle

**SQL injection:**
```bash
curl -sL -d "email=demo@liga.com' OR '1'='1&password=x" "$B/?controller=Auth&action=login"
# esperado: "Credenciales incorrectas"
```
Se probó en el email **y** en la contraseña. Ambas bloqueadas. Funciona porque se usan prepared statements: el valor viaja por un canal separado de la consulta.

**XSS:**
```bash
curl -sL -d "nombre=<script>alert(1)</script>&ciudad=XSSCity&fundacion=2000" ...
# luego: el listado debe mostrarlo como TEXTO, sin ejecutar
```
⚠️ **Y el dato se guarda en la base tal cual**, con los `<script>`. La protección está al **mostrar** (`htmlspecialchars`), no al guardar. Enseñar HTML en un listado es válido; ejecutarlo es el agujero.

**Contraseñas hasheadas:**
```bash
mysql -u root liga_basquet --batch -e "
SELECT email, LEFT(password,7), LENGTH(password) FROM usuarios;"
# esperado: $2y$10$  y  60
```
⚠️ **Si el largo fuera 8, o el prefijo no fuera `$2y$`, habría contraseñas en texto plano.** Es la comprobación de seguridad más importante de todas.

## 7. Commits realizados

```
fix: restaura vista de listado de partidos que quedó vacía
fix: convierte fecha a formato datetime-local (AAAA-MM-DDTHH:MM) en formularios de partido
docs: reescribe explicaciones del vault a fondo y corrige documentación desactualizada
docs: documenta los errores encontrados en la verificación final
```

## 8. Aprendizajes del día

1. **Antes de "arreglar" el código, confirma que el fallo no sea del test.** Cambiar código para satisfacer un test mal escrito destruye funcionalidad correcta y esconde bugs reales. De los 13 fallos iniciales, **12 eran del script** y solo 1 era del código.
2. **`curl` necesita `-L` y cookie jar** para probar una aplicación con PRG. Sin los dos, todo parece roto.
3. **Un test que depende del estado anterior no es un test.** La suite tiene que reiniciar la base antes de ejecutarse.
4. **Los archivos vacíos pasan la revisión de sintaxis.** `wc -l` antes que `php -l`.
5. **HTML5 no perdona los formatos.** Si el `value` de un `datetime-local` no coincide exactamente, el navegador lo descarta sin avisar.
6. **Un bug puede estar en la solución anterior.** La primera "solución" (`substr` sin `str_replace`) parecía correcta leyendo el código. Solo la prueba en navegador lo reveló.
7. **Construye un entorno limpio cuando el de desarrollo no esté disponible**, en vez de pelearte con permisos del sistema.
8. **Verificar solo la interfaz no basta.** La integridad referencial se comprueba con SQL, no mirando el HTML.
9. **La documentación desactualizada es un bug documentado.** Enseñar la versión antigua del schema habría hecho que el lector importara un script que da error.

## 9. Limitaciones pendientes (al cerrar el proyecto)

Todo esto se detectó durante la verificación y **no se implementó**:

| # | Limitación | Gravedad | Módulo |
|---|---|---|---|
| 1 | **Borrar un equipo con jugadores/partidos da error de FK sin capturar** | **Alta** | Equipos |
| 2 | No se comprueba que los equipos existan al crear un partido (error FK) | Alta | Partidos |
| 3 | No se valida la `posicion` contra la lista de 5 válidas | Media | Jugadores |
| 4 | No se valida la unicidad del número de camiseta por equipo | Media | Jugadores |
| 5 | Los equipos sin partidos no aparecen en las estadísticas | Media | Estadísticas |
| 6 | No se valida el formato de la fecha con `DateTime` | Media | Partidos |
| 7 | `actualizar()` no comprueba que el registro exista | Baja | Los 3 CRUD |
| 8 | `eliminar()` por GET (la confirmación es solo de `confirm()` en JS) | Baja | Los 3 CRUD |
| 9 | No hay protección CSRF | Media | Todos |
| 10 | No hay control de roles (todo usuario ve todo) | Baja | Auth |
| 11 | `Router` usa `die()` sin escapar el nombre del `$_GET` | Baja | Core |
| 12 | `$puntosLocal = (int)($_POST[...] ?? 0)` confunde "vacío" con "0" | Baja | Partidos |

⚠️ **La 1 es la más visible al usar la aplicación.** Los 4 equipos de `seeds.sql` tienen jugadores y partidos, así que intentar borrar cualquiera de ellos **siempre** produce un error de FK.

**El arreglo (documentado pero no implementado):**
```php
// Opción A — comprobar antes de borrar
if ($this->equipoModel->tieneRegistros($id)) {
    $_SESSION['error'] = 'No se puede eliminar: el equipo tiene jugadores o partidos';
    header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
    exit;
}

// Opción B — capturar la excepción
try {
    $this->equipoModel->eliminar($id);
    $_SESSION['exito'] = 'Equipo eliminado correctamente';
} catch (PDOException $e) {
    $_SESSION['error'] = 'No se puede eliminar: tiene registros relacionados';
}
```

## 10. Estado final del proyecto

| Elemento | Estado |
|---|---|
| Módulos | 5 (auth, equipos, jugadores, partidos, estadísticas) |
| Archivos PHP | 22 |
| Tablas | 4, con 3 claves foráneas |
| Pruebas funcionales | **81 / 81 superadas** |
| Seguridad | Prepared statements, bcrypt, escapado de salida, sesiones regeneradas |
| Documentación | Vault de 10 secciones, 15 archivos de código explicado, 8 procedimientos |
| Git | Historial limpio con commits atómicos y mensajes descriptivos |

**Pendiente fuera del alcance del código:**
- Conectar con un remoto de GitHub.
- Demostrar el trabajo con ramas y Pull Requests.
- Generar el ZIP de entrega con la carpeta `.git` incluida.

---

**Volver al índice →** `../00-MOC/Proyecto-Liga-Basquet-MOC.md`