<?php

/**
 * Clase Database - Singleton para conexión PDO a MySQL/MariaDB
 * 
 * Proporciona una única instancia de conexión a la base de datos
 * utilizando PDO con prepared statements para mayor seguridad.
 */
class Database {
    private static ?PDO $instancia = null;

    /**
     * Constructor privado para evitar instanciación directa (Singleton)
     */
    private function __construct() {
    }

    /**
     * Obtiene la conexión PDO a la base de datos.
     * Si no existe, crea una nueva instancia (patrón Singleton).
     * 
     * @return PDO Instancia de conexión PDO
     */
    public static function getConexion(): PDO {
        if (self::$instancia === null) {
            try {
                // DSN (Data Source Name) para MySQL
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                
                // Opciones de configuración PDO
                $opciones = [
                    // Modo de errores: lanzar excepciones
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    // Modo de fetch por defecto: array asociativo
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Desactivar emulación de prepared statements (más seguro)
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // No usar conexiones persistentes
                    PDO::ATTR_PERSISTENT         => false,
                ];
                
                // Crear nueva conexión PDO
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
                
            } catch (PDOException $e) {
                // En caso de error de conexión
                die('Error de conexión a la base de datos: ' . $e->getMessage());
            }
        }
        
        return self::$instancia;
    }

    /**
     * Evitar clonación de la instancia (Singleton)
     */
    private function __clone() {
    }

    /**
     * Evitar deserialización de la instancia (Singleton)
     * @throws Exception
     */
    public function __wakeup(): void {
        throw new Exception("No se puede deserializar una instancia Singleton");
    }
}
