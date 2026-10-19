<?php

/**
 * Clase LimitadorIntentos - Bloquea los intentos de login fallidos
 *
 * Un contador guardado en la sesión no protege de nada: el atacante
 * simplemente no envía la cookie y empieza de cero en cada intento.
 * Por eso el contador vive en el servidor.
 *
 * La clave combina la dirección IP con la cuenta que se está atacando:
 *
 *   - Por IP sola, cinco fallos de una cuenta bloquearían a TODAS las
 *     cuentas de esa IP durante 15 minutos. En un aula, una oficina o
 *     cualquier red con NAT, eso deja fuera a gente que no ha hecho nada
 *     malo. Con la cuenta en la clave, el bloqueo solo afecta a la
 *     cuenta que se está atacando desde esa IP.
 *
 *   - El atacante que prueba mil contraseñas contra una cuenta sigue
 *     bloqueado, porque su clave es siempre la misma.
 *
 * El estado se guarda en un archivo por clave dentro de la carpeta
 * temporal del sistema. Todas las operaciones se hacen con el archivo
 * bloqueado (flock), incluida la lectura: leer sin bloquear puede
 * coincidir con una escritura a medio hacer y devolver un contador
 * vacío, que es justo lo que se quiere evitar.
 */
class LimitadorIntentos {
    /** Número de intentos fallidos que bloquean el acceso. */
    public const MAX_INTENTOS = 5;

    /** Ventana de tiempo, en segundos, en la que se cuentan los intentos. */
    public const VENTANA = 900; // 15 minutos

    /**
     * Indica si una clave está bloqueada por intentos fallidos.
     *
     * @param string $clave
     * @return bool
     */
    public static function estaBloqueado(string $clave): bool {
        $intentos = self::leer($clave);
        return count($intentos) >= self::MAX_INTENTOS;
    }

    /**
     * Suma un intento fallido para esa clave.
     *
     * @param string $clave
     * @return void
     */
    public static function registrarFallido(string $clave): void {
        self::modificar($clave, static function (array $intentos): array {
            $intentos[] = time();
            return $intentos;
        });
    }

    /**
     * Deja el contador a cero (el login ha sido correcto).
     *
     * No se borra el archivo: se vacía con el archivo bloqueado. Un
     * unlink suelto puede competir con una escritura simultánea y dejar
     * el contador resurrected justo después de un login correcto.
     *
     * @param string $clave
     * @return void
     */
    public static function limpiar(string $clave): void {
        self::modificar($clave, static fn(array $intentos): array => []);
    }

    /**
     * Lee los intentos que siguen dentro de la ventana de tiempo.
     *
     * @param string $clave
     * @return array Lista de marcas de tiempo
     */
    private static function leer(string $clave): array {
        $archivo = self::rutaDe($clave);
        $manejador = @fopen($archivo, 'c+');
        if ($manejador === false) {
            self::avisar('No se pudo abrir el archivo de intentos: ' . $archivo);
            return [];
        }

        try {
            if (!flock($manejador, LOCK_SH)) {
                self::avisar('No se pudo bloquear el archivo de intentos: ' . $archivo);
                return [];
            }

            $contenido = stream_get_contents($manejador);
            $intentos  = self::decodificar((string)$contenido);
        } finally {
            flock($manejador, LOCK_UN);
            fclose($manejador);
        }

        return $intentos;
    }

    /**
     * Modifica la lista de intentos con el archivo bloqueado en exclusiva.
     *
     * Leer y escribir dentro del mismo bloqueo es lo que evita perder
     * un intento cuando dos peticiones llegan a la vez.
     *
     * @param string   $clave
     * @param callable $operacion Recibe los intentos y devuelve los nuevos
     * @return void
     */
    private static function modificar(string $clave, callable $operacion): void {
        $archivo = self::rutaDe($clave);
        $manejador = @fopen($archivo, 'c+');
        if ($manejador === false) {
            self::avisar('No se pudo abrir el archivo de intentos: ' . $archivo);
            return;
        }

        try {
            if (!flock($manejador, LOCK_EX)) {
                self::avisar('No se pudo bloquear el archivo de intentos: ' . $archivo);
                return;
            }

            $contenido = stream_get_contents($manejador);
            $intentos  = $operacion(self::decodificar((string)$contenido));

            // Se descartan los intentos que ya han salido de la ventana.
            $ahora = time();
            $vivos = array_values(array_filter(
                $intentos,
                static fn($t) => ($ahora - (int)$t) < self::VENTANA
            ));

            ftruncate($manejador, 0);
            rewind($manejador);
            fwrite($manejador, (string)json_encode($vivos));
            fflush($manejador);
        } finally {
            flock($manejador, LOCK_UN);
            fclose($manejador);
        }
    }

    /**
     * Convierte el contenido del archivo en una lista de marcas de tiempo.
     *
     * @param string $contenido
     * @return array
     */
    private static function decodificar(string $contenido): array {
        if ($contenido === '') {
            return [];
        }

        $intentos = json_decode($contenido, true);
        if (!is_array($intentos)) {
            return [];
        }

        return array_values(array_map('intval', $intentos));
    }

    /**
     * Deja constancia de un problema con el archivo de intentos.
     *
     * ⚠️ Si el estado no se puede guardar, el limitador deja de contar:
     * es un fallo "abierto" (fail open). Se avisa al administrador por
     * el log del servidor, que es donde se puede ver que pasa.
     *
     * @param string $mensaje
     * @return void
     */
    private static function avisar(string $mensaje): void {
        error_log('[ProyectoBasket] ' . $mensaje);
    }

    /**
     * Ruta del archivo donde se guardan los intentos de una clave.
     *
     * La clave se pasa por hash para que el nombre del archivo no deje
     * la dirección IP ni el email a la vista en el sistema de archivos.
     *
     * @param string $clave
     * @return string
     */
    private static function rutaDe(string $clave): string {
        $carpeta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'liga_basquet_login';
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0700, true);
        }

        return $carpeta . DIRECTORY_SEPARATOR . hash('sha256', $clave) . '.json';
    }
}
