# Layout y Vistas (explicados)

**Archivos reales:** `app/views/layouts/main.php` + las 13 vistas de los 5 módulos
**Rol:** la capa que genera el HTML. Es donde el usuario ve (o no ve) el resultado.

## 1. La estructura de vistas

```
app/views/
├── layouts/
│   └── main.php          ← el HTML común (el "molde")
├── auth/
│   ├── login.php
│   └── register.php
├── equipos/
│   ├── index.php         ← listado
│   ├── crear.php         ← formulario de alta
│   └── editar.php        ← formulario de edición
├── jugadores/
│   ├── index.php, crear.php, editar.php
├── partidos/
│   ├── index.php, crear.php, editar.php
└── estadisticas/
    └── index.php
```

**14 archivos de vista, 5 controladores, 4 modelos, 4 clases base.**

## 2. `layouts/main.php`: el molde común

Este archivo contiene el HTML que aparece en **todas** las páginas: el `<head>`, el título, el menú y el pie.

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo) ? htmlspecialchars($titulo) . ' - Liga de Básquetbol' : 'Liga de Básquetbol' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/styles.css">
</head>
<body>
    <header><h1>🏀 Liga de Básquetbol</h1></header>
    <nav> ... </nav>
    <main>
        <div class="container">
            ...mensajes...
            <?php
            $vistaArchivo = '../app/views/' . $vista . '.php';
            if (file_exists($vistaArchivo)) {
                require_once $vistaArchivo;
            } else {
                echo '<h2>Error: Vista no encontrada</h2>';
                echo '<p>' . htmlspecialchars($vistaArchivo) . '</p>';
            }
            ?>
        </div>
    </main>
    <footer><p>&copy; 2026 Liga de Básquetbol</p></footer>
</body>
</html>
```

### Las 4 líneas que hacen toda la magia

**a) `BASE_URL` en todos los enlaces y el CSS.**
```php
<link rel="stylesheet" href="<?= BASE_URL ?>css/styles.css">
```
Gracias a `BASE_URL` (definido en `config.php` con detección automática), todo funciona sin importar el nombre de la carpeta en `htdocs`. Está explicado en `../../02-Fundamentos/05-Routing.md` y en el archivo `config.php` mismo.

⚠️ **Si escribieras `href="/css/styles.css"`, funcionaría en la raíz del servidor pero no en `htdocs/ProyectoBasket/public/`.** Es el error más común al mover una aplicación a una subcarpeta.

**b) `htmlspecialchars($titulo)`** — el título viene del controlador (`'Listado de Equipos'`). Se escapa por la misma regla que todo lo demás.

**c) El `isset($titulo) ? ... : ...`** — el `?:` (ternario) con un fallback. Todas las vistas pasan `titulo`, pero el login podría no pasarlo. El ternario evita un aviso de variable indefinida.

**d) El bloque que carga la vista concreta** es el corazón del patrón:

```php
$vistaArchivo = '../app/views/' . $vista . '.php';
if (file_exists($vistaArchivo)) {
    require_once $vistaArchivo;
} else {
    echo '<h2>Error: Vista no encontrada</h2>';
}
```

**¿De dónde sale `$vista`?** De `Controller::view('equipos/index')`. El parámetro `$vista` es visible en el ámbito del layout porque `require` **no crea un ámbito nuevo**: el código se ejecuta en el mismo contexto. Por eso `$titulo`, `$equipos` (los del `extract`) y `$vista` (el parámetro del método) están todos disponibles.

⚠️ **Esa es la "magia" de `extract()` + `require`: comparten el mismo ámbito de variables.** Es potente pero frágil: si el layout y la vista definieran la misma variable con otro nombre, se pisarían.

⚠️ **`$vista` viene del controlador, nunca del usuario**, así que concatenarlo en la ruta **no es inyección de ficheros**. Si algún día `view()` recibiera un parámetro del usuario, esa línea sería una vulnerabilidad grave (podrías hacer `../../etc/passwd`).

## 3. Los mensajes de sesión en el layout

```php
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
```

**Este bloque resuelve tres problemas a la vez:**

### a) El mensaje viaja entre peticiones

Cuando el controlador hace `header('Location: ...')`, la petición anterior **muere**. Sus variables locales desaparecen. La sesión es lo único que sobrevive al salto. Por eso el error se guarda en `$_SESSION` y se muestra en la página de destino.

### b) `unset()` lo consume

Sin el `unset`, el mensaje aparecería **para siempre** en cada página que cargues. Se muestra una vez y desaparece.

### c) El mensaje se escapa

⚠️ **Esto es crítico.** El mensaje de error lo escribe el controlador... pero a veces incluye datos del usuario. Con `htmlspecialchars`, un mensaje como `El email "no-válido@x" ya está registrado` se muestra con las comillas correctas y sin romper nada.

**Verificado:** el mensaje de éxito "Equipo creado correctamente" aparece una sola vez tras crear.

⚠️ **Limitación:** no se muestra el mensaje si el usuario navega a otra página antes de verlo. Es el precio de usar `header('Location')` en lugar de imprimir el mensaje en la misma respuesta. Aceptable: el mensaje se muestra en la redirección inmediata.

## 4. El menú:condicional según la sesión

```php
<?php if (isset($_SESSION['logueado']) && $_SESSION['logueado']): ?>
    <li><a href="<?= BASE_URL ?>?controller=Equipos&action=index">Equipos</a></li>
    <li><a href="<?= BASE_URL ?>?controller=Jugadores&action=index">Jugadores</a></li>
    <li><a href="<?= BASE_URL ?>?controller=Partidos&action=index">Partidos</a></li>
    <li><a href="<?= BASE_URL ?>?controller=Estadisticas&action=index">Estadísticas</a></li>
    <li><a href="<?= BASE_URL ?>?controller=Auth&action=logout">Cerrar Sesión (<?= htmlspecialchars($_SESSION['nombre']) ?>)</a></li>
<?php else: ?>
    <li><a href="<?= BASE_URL ?>?controller=Auth&action=index">Iniciar Sesión</a></li>
    <li><a href="<?= BASE_URL ?>?controller=Auth&action=register">Registrarse</a></li>
<?php endif; ?>
```

**La sintaxis `if: ... else: ... endif;`** es la versión alternativa de PHP, más legible que las llaves cuando hay HTML intercalado. Se cierra con `endif;` (con punto y coma), no con `}`.

⚠️ **Comprobación doble:** `isset($_SESSION['logueado']) && $_SESSION['logueado']`. El `isset` cubre que la clave no exista; el segundo verifica que sea `true`. Belt and braces.

⚠️ **`htmlspecialchars($_SESSION['nombre'])`** — el nombre viene de la base (el usuario se pudo registrar con `<script>alert(1)</script>` como nombre). Sin escapar, el nombre **ejecutaría JavaScript** en el menú de todas las páginas. **Verificado:** el nombre se muestra como texto.

**El menú solo muestra los enlaces de módulos si hay sesión.** Un visitante sin sesión solo ve "Iniciar Sesión" y "Registrarse", aunque los enlaces a Equipos estén "ocultos" en el HTML. La protección real es `Controller::requiereLogin()`, no ocultar el menú: **ocultar no es proteger.**

## 5. Una vista de listado completa: `equipos/index.php`

```php
<h1>Listado de Equipos</h1>

<div class="actions">
    <a href="<?= BASE_URL ?>?controller=Equipos&action=crear" class="btn">+ Nuevo Equipo</a>
</div>

<?php if (empty($equipos)): ?>
    <p class="text-center">No hay equipos registrados aún.</p>
<?php else: ?>
    <table>
        <thead>
            <tr><th>ID</th><th>Nombre</th><th>Ciudad</th><th>Año de Fundación</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$equipo['id']) ?></td>
                    <td><?= htmlspecialchars($equipo['nombre']) ?></td>
                    <td><?= htmlspecialchars($equipo['ciudad']) ?></td>
                    <td><?= htmlspecialchars($equipo['fundacion'] ?? '-') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>?controller=Equipos&action=editar&id=<?= $equipo['id'] ?>" class="btn btn-secondary">Editar</a>
                        <a href="<?= BASE_URL ?>?controller=Equipos&action=eliminar&id=<?= $equipo['id'] ?>" class="btn btn-danger"
                           onclick="return confirm('¿Eliminar este equipo?');">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
```

### Secciones a analizar

### a) El `<table>` con `<thead>` y `<tbody>`

No es puramente cosmético. El CSS usa `thead { background: ... }` y `tbody tr:nth-child(even)` para las filas alternas (efecto "cebra"). Sin esa estructura, el estilo de tablas no funciona.

⚠️ **En todas las vistas, las filas de datos van dentro de `<tbody>`.**

### b) `<?php if (empty($equipos)): ?>` — el caso "no hay datos"

**Una vista debe poder pintar la lista vacía.** Sin este `if`, una base sin equipos daría una tabla con solo la cabecera, que parece un error.

⚠️ **`empty($equipos)` funciona porque `obtenerTodos()` devuelve un array** (vacío si no hay filas), no `false`. Si devolviera `false`, `empty()` también sería `true` y funcionaría por casualidad.

### c) `foreach ... endforeach` — el bucle

```php
<?php foreach ($equipos as $equipo): ?>
    ...una fila por equipo...
<?php endforeach; ?>
```

**`$equipo` es el nombre de la variable que hay que elegir con cuidado.** Dentro del bucle, `$equipo` es **una fila**. Fuera, `$equipos` es **la lista completa**. El singular y el plural lo explican todo.

⚠️ **Si dentro del bucle asignaras `$equipos = $equipo;`**, el bucle se rompería. Nunca reutilices el nombre del array que se está recorriendo.

### d) La regla del escapado: `htmlspecialchars()` en cada dato

```php
<?= htmlspecialchars($equipo['nombre']) ?>
```

⚠️ **PHP no escapa nada por defecto.** Sin esta llamada, un equipo llamado `<script>alert(1)</script>` ejecutaría JavaScript.

**Verificado:** se guardó un equipo con `<script>alert(1)</script>` como nombre y el listado lo mostró **como texto**, sin ejecutar nada.

⚠️ **Y el dato se guarda en la base tal cual** (con los `<script>`). La protección es al **mostrar**, no al guardar. **Enseñar HTML en un listado es válido** (una descripción de un partido puede llevar etiquetas); lo peligroso es ejecutarlo.

### e) `htmlspecialchars((string)$equipo['id'])`

El `(string)` convierte el id a texto antes de escapar. ⚠️ **No es estrictamente necesario** (`htmlspecialchars` acepta int), pero es buena costumbre: uniforma el tipo y evita sorpresas si `id` fuera `null`.

### f) `htmlspecialchars($equipo['fundacion'] ?? '-')` — el `??` por el `NULL`

**La fundación puede ser `NULL` en la base** (es un campo opcional). `htmlspecialchars(null)` es un **`TypeError` en PHP 8.1+**.

⚠️ **Esto no es hipotético: rompe la página de verdad.** Si un equipo se creó sin año de fundación, la vista reventaría con un error fatal en lugar de mostrar un guion.

**El `?? '-'`** es la protección: si es `null`, muestra `-`.

**Verificado:** crear un equipo sin año de fundación y recargar el listado funciona, mostrando `-`.

### g) El enlace de Editar con el id en la URL

```php
href="<?= BASE_URL ?>?controller=Equipos&action=editar&id=<?= $equipo['id'] ?>"
```

**El id viaja en el query string.** No hace falta JavaScript ni formularios ocultos para un enlace.

⚠️ **Y sí, se escapa el id con `htmlspecialchars()` en la columna del `href`.** Parece exagerado para un entero, pero por la misma regla de siempre: escapa todo lo que imprimas, aunque parezca que no puede Romperse.

### h) `onclick="return confirm(...)"`

```html
onclick="return confirm('¿Eliminar este equipo?');"
```

El `return` es lo que importa: hace que el enlace **no se siga** si el usuario cancela.

⚠️ **Esto es usabilidad, no seguridad.** `confirm()` vive en el navegador del usuario: `curl` lo ignora, y cualquiera con JavaScript en la consola lo desactiva. La protección real contra un borrado no autorizado sería un formulario POST con token CSRF. Está documentado como limitación en `../Controllers/EquiposController-explicado.md` sección 8.

## 6. Una vista de formulario: `equipos/crear.php`

```php
<form action="<?= BASE_URL ?>?controller=Equipos&action=guardar" method="POST">
    <div class="form-group">
        <label for="nombre">Nombre del equipo *</label>
        <input type="text" id="nombre" name="nombre"
               value="<?= htmlspecialchars($old['nombre'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="ciudad">Ciudad *</label>
        <input type="text" id="ciudad" name="ciudad"
               value="<?= htmlspecialchars($old['ciudad'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="fundacion">Año de fundación</label>
        <input type="number" id="fundacion" name="fundacion"
               min="1800" max="<?= date('Y') ?>"
               value="<?= htmlspecialchars($old['fundacion'] ?? '') ?>">
    </div>

    <button type="submit" class="btn">Guardar Equipo</button>
</form>
```

### Los atributos HTML5 que hacen la validación

| Atributo | Qué hace |
|---|---|
| `type="number"` | Teclado numérico en móvil, sin letras |
| `min="0"` | No acepta negativos |
| `max="99"` | Límite superior en el número de camiseta |
| `required` | No deja enviar vacío |
| `max="<?= date('Y') ?>"` | El año máximo **es el año actual** |

⚠️ **Los atributos HTML5 son la primera barrera, no la única.** Se pueden esquivar fácil (con `curl`, o deshabilitando JavaScript). El servidor **vuelve a validar todo** en `guardar()`.

**Por qué los dos.** La validación del navegador da una experiencia buena (error al instante, sin recargar), y la del servidor garantiza la integridad. **Las dos hacen falta**, pero **solo la del servidor es seguridad**.

⚠️ **`max="<?= date('Y') ?>"` es dinámico**, igual que en el controlador. Y coincide con la validación `> (int)date('Y')` de PHP: los dos usan el año actual, así que nunca se contradicen.

### `value="<?= htmlspecialchars($old['nombre'] ?? '') ?>"`

**El formulario se rellena con `$old['nombre']`.** ¿De dónde viene `$old`? Del controlador:

```php
$old = $_SESSION['old_equipo'] ?? [];
unset($_SESSION['old_equipo']);
$this->view('equipos/crear', ['titulo' => 'Crear Equipo', 'old' => $old]);
```

**Ese es el mecanismo completo del "formulario se rellena si falla":**

```
1. Usuario envía el formulario con " Lakers " y sin ciudad
2. guardar() guarda $_SESSION['old_equipo'] = ['nombre' => 'Lakers', ...]
3. guardar() falla la validación → redirige a action=crear
4. crear() lee $_SESSION['old_equipo'] y lo pasa como $old
5. La vista pinta value="Lakers" en el campo Nombre
6. unset() consume el valor → no aparecerá otra vez
```

⚠️ **El `unset()` en el paso 6 es lo que evita que el formulario quede prellenado para siempre.**

⚠️ **`htmlspecialchars($old['nombre'] ?? '')`**: si `$old` está vacío (el usuario entró directo a `/crear`), `?? ''` evita un aviso de "Undefined array key".

### `method="POST"` y `<input type="hidden">`

Los formularios de creación y edición **siempre** usan `method="POST"`. Para editar, el `id` va en un campo oculto:

```php
<form action="<?= BASE_URL ?>?controller=Partidos&action=actualizar" method="POST">
    <input type="hidden" name="id" value="<?= htmlspecialchars($partido['id']) ?>">
```

⚠️ **Un campo `hidden` no es secreto.** Cualquiera puede ver el HTML y modificarlo. La seguridad no se basa en ocultar cosas, sino en **validar en el servidor**. Por eso `actualizar()` comprueba `$id <= 0` y debería comprobar que el registro exista.

## 7. El campo `datetime-local` y su bug

En `partidos/editar.php` y `partidos/crear.php`:

```php
<input type="datetime-local" id="fecha" name="fecha"
       value="<?= htmlspecialchars(str_replace(' ', 'T', substr($partido['fecha'], 0, 16))) ?>"
       required>
```

**Este es el archivo donde estaba el error más difícil de detectar del proyecto.** El detalle completo está en `../../08-Errores-y-Soluciones/Registro-de-errores.md` sección 4.

Lo esencial que hay que recordar:

| Fuente | Formato |
|---|---|
| MySQL (`DATETIME`) | `2026-10-01 19:00:00` (espacio) |
| `<input type="datetime-local">` | `2026-10-01T19:00` (**T**) |

⚠️ **Si el `value` no coincide exactamente, el navegador lo descarta en silencio.** No hay error de PHP, ni aviso, ni nada en la consola: el campo simplemente aparece vacío.

**Las dos conversiones hacen falta:** `substr(..., 0, 16)` quita los segundos, y `str_replace(' ', 'T', ...)` cambia el separador.

⚠️ **Y el error dentro del error:** la primera versión usaba solo `substr()`. Parecía correcta leyendo el código, pero el resultado seguía teniendo un espacio, y el campo seguía vacío. **Solo se detectó abriendo el formulario en el navegador.**

## 8. La sintaxis PHP en las vistas: un resumen

Las 4 formas que verás, y cuándo usar cada una:

```php
<?php ... ?>          → etiqueta normal
<?php if (...): ?>    → alternative con endif
<?php foreach (...): ?> → alternative con endforeach
<?= ... ?>            → echo corto (equivale a <?php echo ...; ?>)
```

⚠️ **`<?=` no depende de `short_open_tag`.** Desde PHP 5.4, el eco corto `<?=` está **siempre disponible**, se tenga o no activado `short_open_tag`. Lo que sí depende de `short_open_tag` es `<?` sin `php`: si estuviera desactivado, `<? echo $x; ?>` se mostraría literalmente como texto.

**Lo que sí conviene evitar en producción:** algunos paneles de hosting de prefabricados restringen `short_open_tag` y otras directivas por seguridad. Si el proyecto se despliega en uno de ellos, usar `<?php echo ... ?>` evita sorpresas:

```php
<?= htmlspecialchars($equipo['nombre']) ?>              <!-- proyecto local, XAMPP -->
<?php echo htmlspecialchars($equipo['nombre']); ?>      <!-- máxima compatibilidad -->
```

**La razón de usar `<?=` en todo el proyecto:** es más corto y se lee mejor. En XAMPP, y en cualquier PHP 5.4+, funciona siempre. Es una decisión consciente, no un descuido.

⚠️ **Y `<?php endforeach; ?>` con punto y coma.** Es un detalle que se olvida y da error de sintaxis.

## 9. Lo que una vista NO debe hacer

Las vistas de este proyecto son "listas para mostrar": solo imprimen. **No contienen lógica de negocio ni acceso a datos.**

| ❌ En una vista | ✔️ Dónde va |
|---|---|
| `$db->query(...)` | En el modelo |
| `password_verify(...)` | En el controlador |
| Calcular ganados/perdidos para la tabla | En el controlador |
| Validar si el usuario puede ver la página | En `Controller::requiereLogin()` |

**Hay una excepción en este proyecto:** el cálculo de "Gana Local / Gana Visitante / Empate" **está** en `partidos/index.php`, no en el controlador. Es una decisión discutible, pero aceptable porque es una regla de **presentación** (qué etiqueta mostrar), no una regla de datos. Está explicada en `../Controllers/PartidosController-explicado.md` sección 7.

## 10. Verificación de las vistas

| Qué comprobar | Cómo | Estado |
|---|---|---|
| Los 3 listados muestran datos | Navegar por los 4 módulos | ✅ Verificado |
| El `datetime-local` se rellena al editar | Editar un partido y mirar el campo | ✅ Corregido (bug #4) |
| El escapado de XSS | Nombre `<script>alert(1)</script>` | ✅ Verificado |
| El `NULL` de la fundación no rompe | Crear equipo sin año | ✅ `?? '-'` |
| Los mensajes salen una vez | Crear y navegar | ✅ `unset()` |
| El menú depende de la sesión | Entrar y salir | ✅ Verificado |
| El CSS carga (200) | Ver la pestaña Network | ✅ Verificado |
| Fila vacía se ve bien | Crear sin datos y listar | ✅ `if (empty())` |
| Botón "Nuevo" enlaza | Clic en el botón | ✅ Verificado |

---

**Archivos relacionados:** `../Core/Controller-explicado.md` (el `extract()` que llena estas variables) · `../Controllers/AuthController-explicado.md` (qué hay en `$_SESSION`) · `../../../app/config/config.php` (el `BASE_URL`) · `../../08-Errores-y-Soluciones/Registro-de-errores.md` (secciones 9 y 4) · `../../03-Arquitectura/Estructura-de-carpetas.md`

**Volver al índice →** `../../00-MOC/Proyecto-Liga-Basquet-MOC.md`

---

**Con este archivo queda explicada toda la aplicación**: config → core → modelos → controladores → vistas. Los 15 archivos de `06-Codigo-Explicado` cubren el 100% del código PHP del proyecto.