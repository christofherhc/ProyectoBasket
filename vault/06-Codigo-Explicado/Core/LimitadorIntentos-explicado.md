# LimitadorIntentos (explicado)

**Archivo real:** `app/core/LimitadorIntentos.php`
**Rol en la arquitectura:** cuenta los intentos de login fallidos y bloquea la cuenta cuando se repiten.

## 1. Qué problema resuelve

Sin ningún límite, un script puede probar miles de contraseñas por minuto contra `demo@liga.com`:

```bash
while true; do
  curl -X POST '...?controller=Auth&action=login' \
    -d 'email=demo@liga.com&password=una-contrasena-mas'
done
```

La contraseña de la base está hasheada con bcrypt, y bcrypt es lento a propósito. Pero "lento" no es "imposible": si el atacante tiene tiempo, la encuentra.

La respuesta es poner un límite. Pero **dónde** se guarda ese límite es lo importante, y en este proyecto se probaron dos sitios:

| Dónde | Por qué no sirve |
|---|---|
| En la sesión (`$_SESSION`) | El atacante deja de mandar la cookie y empieza de cero en cada intento. No frena nada. |
| En el servidor, por IP | Cinco fallos de una cuenta bloqueaban a **todas** las cuentas de esa IP. En un aula o una oficina (muchas máquinas con la misma IP pública) eso echa a gente que no ha hecho nada malo. |

La solución combine las dos cosas: **IP + cuenta**.

## 2. La clave del contador

```php
private static function claveDeBloqueo(string $email): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
    return $ip . '|' . strtolower($email);
}
```

- El atacante que prueba mil contraseñas contra `demo@liga.com` genera siempre la misma clave, así que el bloqueo lo alcanza.
- Un usuario que se equivoca cinco veces bloquea **su** cuenta desde esa IP, no las de los demás.
- `strtolower()` en el email hace que `Demo@Liga.com` y `demo@liga.com` cuenten como la misma cuenta (el login ya normaliza el email a minúsculas).

⚠️ **Si la aplicación se pone detrás de un proxy**, `REMOTE_ADDR` es la del proxy y el bloqueo alcanzaría a todo el mundo. En ese caso habría que usar la cabecera `X-Forwarded-For`, validada contra una lista blanca de proxies de confianza.

## 3. La API: tres métodos estáticos

```php
LimitadorIntentos::estaBloqueado($clave);   // ¿está bloqueada esta clave?
LimitadorIntentos::registrarFallido($clave); // suma un intento
LimitadorIntentos::limpiar($clave);          // login correcto: contador a cero
```

Son estáticos porque no hay estado en el objeto: el estado está en el disco, y un objeto por petición no aportaría nada.

## 4. Dónde vive el estado

En un archivo por clave, dentro de la carpeta temporal del sistema:

```php
private static function rutaDe(string $clave): string {
    $carpeta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'liga_basquet_login';
    if (!is_dir($carpeta)) {
        @mkdir($carpeta, 0700, true);
    }
    return $carpeta . DIRECTORY_SEPARATOR . hash('sha256', $clave) . '.json';
}
```

⚠️ **El nombre del archivo es el hash de la clave**, no la clave. Así ni la IP ni el email quedan a la vista en el sistema de archivos, y una clave con caracteres raros no puede escapar de la carpeta.

⚠️ **La carpeta se crea con permisos `0700`**, que solo puede leer y escribir su propietario. El archivo guarda un JSON con las marcas de tiempo de los intentos:

```json
[1789432101, 1789432134, 1789432202]
```

Cada marca es un `time()`. Al leer, se descartan las que ya han pasado de la ventana (15 minutos):

```php
$vivos = array_values(array_filter(
    $intentos,
    static fn($t) => ($ahora - (int)$t) < self::VENTANA
));
```

## 5. Lo importante: el bloqueo de archivos

Toda la clase existe, en el fondo, para resolver **una** cosa: que dos peticiones a la vez no se pisen.

### El error que había

La primera versión leía el archivo **sin** bloquear y lo escribía **con** bloqueo:

```php
public static function registrarFallido(string $clave): void {
    $intentos = self::intentosDe($clave);   // ← lectura sin bloqueo
    $intentos[] = time();
    self::escribir($clave, $intentos);      // ← escritura con bloqueo
}
```

Entre la lectura y la escritura cabía otra petición, y su registro se perdía. Medido con 20 procesos en paralelo registrando 50 intentos cada uno:

| Versión | Intentos registrados de 1000 |
|---|---|
| Leer sin bloqueo, escribir con bloqueo | **9** (se perdían 991) |
| Leer y escribir bajo el mismo bloqueo | **1000** |

⚠️ **Eso rompía la protección entera.** El bloqueo solo se activa a partir del quinto intento, y con las peticiones en paralelo (que es justo lo que hace un script de fuerza bruta) el contador nunca llegaba a cinco. El atacante podía probar tantas contraseñas como quisiera.

### El arreglo

El ciclo leer-modificar-escribir entero ocurre con el archivo bloqueado en exclusiva:

```php
if (!flock($manejador, LOCK_EX)) {
    self::avisar('No se pudo bloquear el archivo de intentos: ' . $archivo);
    return;
}

$contenido = stream_get_contents($manejador);
$intentos  = $operacion(self::decodificar((string)$contenido));

ftruncate($manejador, 0);
rewind($manejador);
fwrite($manejador, (string)json_encode($vivos));
fflush($manejador);
```

Y la lectura usa un bloqueo compartido (`LOCK_SH`), porque leer un archivo que se está escribiendo a mitad puede devolver un JSON incompleto: `json_decode` devolvería `null` y **el contador se reiniciaría a cero**, desarmando el bloqueo solo.

### Por qué `limpiar()` no borra el archivo

```php
public static function limpiar(string $clave): void {
    self::modificar($clave, static fn(array $intentos): array => []);
}
```

Un `unlink()` suelto compite con una escritura simultánea y puede dejar el contador "resucitado" justo después de un login correcto. Vaciar el archivo con el bloqueo puesto no tiene esa carrera.

## 6. Si algo falla

```php
private static function avisar(string $mensaje): void {
    error_log('[ProyectoBasket] ' . $mensaje);
}
```

⚠️ **El limitador falla "abierto"**: si el archivo no se puede leer o escribir, el contador no cuenta y el bloqueo no se activa. Es una decisión consciente (que la aplicación no se quede sin poder entrar a nadie por un problema de disco), pero **no es silencioso**: queda un `error_log` para el administrador, que es donde se puede ver que está pasando.

## 7. Verificación

La suite de regresión lo comprueba en su bloque 13:

| Comprobación | Qué demuestra |
|---|---|
| 5 fallos **en paralelo** bloquean la cuenta | El contador no pierde registros con peticiones simultáneas |
| Otra cuenta de la misma IP entra | El bloqueo es por IP **y** cuenta, no solo por IP |
| La cuenta atacada sigue bloqueada | El atacante no esquiva el bloqueo cambiando de cuenta |
| Tras limpiar el estado, se entra | El contador se limpia al hacer login bien |

---

**Archivos relacionados:** `../Controllers/AuthController-explicado.md` (quién llama a esta clase) · `../../08-Errores-y-Soluciones/Seguridad-y-robustez.md` (sección 8) · `../../08-Errores-y-Soluciones/Registro-de-errores.md` (errores 23 y 25) · `../../../tests/regresion.sh` (bloque 13)

**Siguiente paso →** `../Controllers/AuthController-explicado.md` (el login completo, paso a paso).
