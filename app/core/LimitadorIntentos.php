<?php

/**
 * Clase LimitadorIntentos - Bloquea los intentos de login fallidos
 *
 * Un contador guardado en la sesión no protege de nada: el atacante
 * simplemente no envía la cookie y empieza de cero en cada intento.
 * Por eso el contador vive en el servidor y se identifica por la
 * dirección IP de quien intenta entrar.
 *
 * El estado se guarda en un archivo por IP dentro de la carpeta temporal
 * del sistema, con bloqueo de escritura (flock) para que dos peticiones
 * simultáneas no se pisen.
 */
class LimitadorIntentos {
    /** Número de intentos fallidos que bloquean la entrada. */
    public const MAX_INTENTOS = 5;

    /** Ventana de tiempo, en segundos, en la que se cuentan los intentos. */
    public const VENTANA = 900; // 15 minutos

    /**
     * Indica si una clave (la IP) está bloqueada por intentos fallidos.
     *
     * @param string $clave
     * @return bool
     */
    public static function estaBloqueado(string $clave): bool {
        return count(self::intentosDe($clave)) >= self::MAX_INTENTOS;
    }

    /**
     * Suma un intento fallido para esa clave.
     *
     * @param string $clave
     * @return void
     */
    public static function registrarFallido(string $clave): void {
        $intentos = self::intentosDe($clave);
        $intentos[] = time();

        self::escribir($clave, $intentos);
    }

    /**
     * Borra el contador (el login ha sido correcto).
     *
     * @param string $clave
     * @return void
     */
    public static function limpiar(string $clave): void {
        $archivo = self::rutaDe($clave);
        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }

    /**
     * Devuelve los intentos fallidos que siguen dentro de la ventana.
     *
     * @param string $clave
     * @return array Lista de marcas de tiempo
     */
    private static function intentosDe(string $clave): array {
        $archivo = self::rutaDe($clave);
        if (!is_file($archivo)) {
            return [];
        }

        $contenido = @file_get_contents($archivo);
        if ($contenido === false || $contenido === '') {
            return [];
        }

        $intentos = json_decode($contenido, true);
        if (!is_array($intentos)) {
            return [];
        }

        $ahora = time();
        $vivos = array_filter(
            $intentos,
            static fn($t) => ($ahora - (int)$t) < self::VENTANA
        );

        return array_values($vivos);
    }

    /**
     * Guarda la lista de intentos en disco.
     *
     * @param string $clave
     * @param array  $intentos
     * @return void
     */
    private static function escribir(string $clave, array $intentos): void {
        $archivo = self::rutaDe($clave);
        $datos = json_encode(array_values($intentos));

        // 'c+' crea el archivo si no existe y no lo borra si ya está.
        $manejador = @fopen($archivo, 'c+');
        if ($manejador === false) {
            return;
        }

        // El bloqueo evita que dos peticiones simultáneas se pisen.
        if (flock($manejador, LOCK_EX)) {
            ftruncate($manejador, 0);
            rewind($manejador);
            fwrite($manejador, (string)$datos);
            fflush($manejador);
            flock($manejador, LOCK_UN);
        }

        fclose($manejador);
    }

    /**
     * Ruta del archivo donde se guardan los intentos de una clave.
     *
     * La clave se pasa por hash para que el nombre del archivo no deje
     * la dirección IP a la vista en el sistema de archivos.
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
