# Diario de Aprendizaje - Día 02

**Fecha:** 2026-10-03
**Módulo:** Core MVC + Front Controller

## Aprendido hoy

**Arquitectura**
- **Front Controller:** un solo archivo (`public/index.php`) recibe todas las peticiones. El navegador nunca habla directamente con un controlador.
- **Router:** traduce `?controller=X&action=Y` en "carga la clase `XController` y llama a `Y()`". Si el método no existe, avisa en lugar de fallar en blanco.
- **Patrón Singleton:** una única conexión PDO para toda la petición, aunque se instancien 4 modelos.
- **Herencia:** `Model` aporta el CRUD genérico; `Controller` aporta `view()` y `requiereLogin()`. Los hijos solo escriben lo que cambia.

**PHP**
- `extract()` pasa un associative array a variables dentro de la vista. Es cómodo para aprender, aunque en proyectos grandes se prefiere el array explícito.
- `call_user_func_array()` llama a un método con un array de parámetros.
- `password_hash()` / `password_verify()` para contraseñas.
- `session_regenerate_id(true)` tras iniciar sesión.
- `htmlspecialchars()` en cada salida.

## Lo que más me costó

⚠️ **Entender por qué `Database` es Singleton y no simplemente "una conexión".** No es por rendimiento (cuatro usuarios no lo notan), es porque *todos* los modelos comparten el mismo objeto PDO, y así solo hay un punto donde se configura charset, modo de error y emulación de prepared statements.

⚠️ **El orden de los `require`.** Si `public/index.php` carga `Router` antes que `Controller`, y `Router` intenta instanciar un controlador que extiende `Controller`, PHP da "Class not found". El orden de los requires en un proyecto sin autoloader **es** parte de la arquitectura.

## Dudas aclaradas
- `extract()` vs pasar el array: para aprender, `extract()`; en código profesional, array explícito.
- `call_user_func_array` vs llamada directa: la versión con array es flexible y permite pasar 0, 1 o N parámetros sin cambiar la firma.
- ¿Por qué `session_start()` va en el front controller y no en cada vista? Porque la sesión debe existir **antes** de que ningún código lea o escriba `$_SESSION`.

## Errores de hoy
- Clase `Controller` no encontrada → `require` en el orden incorrecto.
- Sesión que no se cerraba al hacer logout → faltaba `session_destroy()`, no solo vaciar `$_SESSION`.
- Mensaje flash que salía dos veces → faltaba el `unset()` después de imprimirlo.

## Decisiones tomadas
- `BASE_URL` calculado con `$_SERVER['SCRIPT_NAME']`, no escrito a mano, para que la app funcione desde cualquier carpeta.
- Los errores de PDO se muestran con `die()` (solo aceptable en local).

## Próximo
**Día 03:** el primer CRUD completo (Equipos) para ver el patrón MVC de extremo a extremo.