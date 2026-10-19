# Diario de Aprendizaje - Día 08

**Fecha:** 2026-10-04
**Módulo:** Verificación final, corrección de bugs y documentación

## Aprendido hoy

**Probar de verdad**
Hasta el día 7 se había navegado por las páginas, pero no se había comprobado cada validación. Hoy se montó una batería de **81 pruebas funcionales** con `curl`: 12 bloques que cubren autenticación, los 3 CRUD, la lógica de partidos, estadísticas, seguridad (SQL injection, XSS, contraseñas hasheadas) e integridad referencial.

**Antes de arreglar el código, confirmar que el fallo no sea del test**
La primera corrida dio **40 pasadas / 13 fallidas**. La reacción natural era mirar los 13 fallos y "arreglar" el código. **12 de los 13 eran del propio script de pruebas:**

| Fallo aparente | Causa real |
|---|---|
| No se ve el mensaje tras un POST | Faltaba `-L` (seguir redirecciones) |
| La sesión "se pierde" al redirigir | Faltaba el cookie jar (`-c` / `-b`) |
| El listado no coincide al repetir | Faltaba reiniciar la base de datos |
| "Debería redirigir al listado" | El test asumía mal, la app hacía bien |

⚠️ **Cambiar código para satisfacer un test mal escrito destruye funcionalidad correcta y esconde bugs reales.**

**Entorno de pruebas aislado**
MySQL de XAMPP no arrancaba (`Can't create/write to file '/opt/lampp/var/...': Permission denied`). Se montó un entorno propio en `/tmp` (MariaDB con `--datadir` y socket propios, `php -S` en otro puerto, copia de la app con el puerto cambiado). **Construir un entorno limpio es más rápido y más seguro que pelearte con permisos del sistema.**

**Tres bugs reales encontrados**
1. **El `datetime-local` vacío al editar** (el importante).
2. **`app/views/partidos/index.php` vacío** (0 bytes) → pantalla en blanco.
3. **Fallos por datos sucios** entre ejecuciones.

## Lo que más me costó

⚠️ **El bug del `datetime-local`.** Es el que más lessons dio, porque **solo se detectó abriendo el formulario en el navegador**. Leyendo el código, la solución parecía correcta:
```php
value="<?= htmlspecialchars(substr($partido['fecha'], 0, 16)) ?>"   // ❌ no funcionaba
```
Quita los segundos, sí, pero el resultado sigue siendo `2026-10-01 19:00` **con espacio**, y el navegador lo descarta igual. La solución correcta necesita las dos conversiones:
```php
value="<?= htmlspecialchars(str_replace(' ', 'T', substr($partido['fecha'], 0, 16))) ?>"
```
⚠️ **Un bug puede estar en la solución anterior.** Nunca hay que dar por buena una solución solo porque parece razonable en el papel.

⚠️ **Un archivo vacío pasa `php -l`.** `partidos/index.php` estaba en 0 bytes y `php -l` decía "No syntax errors", porque no había nada que analizar. Por eso `wc -l` va **antes** de `php -l`.

⚠️ **La diferencia entre HTML y base de datos.** MySQL y MariaDB **sí aceptan** `2026-10-12T19:00` en un `INSERT` y lo guardan como `2026-10-12 19:00:00`. SeDocumentó porque la conclusión contraintuitiva cambia la solución: el problema era **solo de visualización**, no hacía falta transformar el POST.

## Dudas aclaradas
- ¿Por qué fallaban 13 pruebas? Porque un test depende del estado: `INSERT IGNORE` no restaura la base, hay que hacer `DROP DATABASE` + reimportar.
- ¿La integridad referencial se comprueba en el navegador? No. Hay que preguntar a la base: `SELECT COUNT(*) FROM jugadores j LEFT JOIN equipos e ON e.id = j.equipo_id WHERE e.id IS NULL` debe dar 0.
- ¿Las contraseñas están realmente hasheadas? `SELECT LEFT(password,7), LENGTH(password) FROM usuarios` → `$2y$10$` y `60`. Si el largo fuera 8, habría texto plano.

## Errores de hoy (los del entorno, no del código)
- `Can't create/write to file '/opt/lampp/var/Chris-PC.err': Permission denied` → arranque de MariaDB con `--datadir` y `--socket` propios.
- `Not unique table/alias` en una prueba de integridad → alias duplicados en el `SELECT` de comprobación.
- Datos persistiendo entre corridas → `DROP DATABASE IF EXISTS` + import de `schema.sql` y `seeds.sql` antes de cada corrida.

## Decisiones tomadas
- La suite se queda en `/tmp` y no en el repositorio: es una herramienta de trabajo, no parte de la entrega.
- Las 12 limitaciones pendientes se **documentan con su arreglo propuesto** en vez de ocultarlas.
- La documentación desactualizada se considera **un bug**: enseñar el schema viejo haría que el lector importara un script que falla.

## Cierre del proyecto

| Elemento | Estado |
|---|---|
| Módulos | 5 |
| Archivos PHP | 22 |
| Tablas | 4, con 3 claves foráneas |
| Pruebas | **81 / 81** (tras la auditoría, la suite subió a **96 / 96**) |
| Git | Historial limpio, commits atómicos |

**Pendiente fuera del alcance:** conectar con un remoto de GitHub, demostrar ramas y Pull Requests. *(El ZIP de entrega con `.git` incluida se generó después.)*

---

**Volver al índice →** `../00-MOC/Proyecto-Liga-Basquet-MOC.md`