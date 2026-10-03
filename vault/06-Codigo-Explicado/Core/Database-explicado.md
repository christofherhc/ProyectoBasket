# Explicación detallada: Database.php

**Archivo:** `app/core/Database.php`  
**Tipo:** Core (Clase base de infraestructura)  
**Responsabilidad:** Gestionar conexión única PDO (Singleton) a MySQL/MariaDB con prepared statements seguros.

## 1. Propósito General

Centraliza conexión BD. Garantiza **una única instancia PDO** por petición HTTP (Singleton), aplica opciones seguras (ERRMODE_EXCEPTION, FETCH_ASSOC, emulate_prepares=false, charset utf8mb4).

## 2. Estructura

```php
<?php

class Database {
    private static ?PDO $instancia = null;
    private function __construct() {}
    public static function getConexion(): PDO { ... }
    private function __clone() {}
    public function __wakeup(): void { throw new Exception(...); }
}
```

## 3. Explicación línea por línea

### Propiedad estática
```php
private static ?PDO $instancia = null;
```
- `static`: compartida entre todas instancias
- `?PDO`: puede ser null o PDO (PHP 7.4+)
- `null`: aún no creada

### Constructor privado
```php
private function __construct() {}
```
Impide `new Database()` → obliga usar `getConexion()` (Singleton).

### getConexion()
```php
public static function getConexion(): PDO {
    if (self::$instancia === null) { ... crea ... }
    return self::$instancia;
}
```
Verifica si existe. Si no, crea y guarda. Retorna siempre misma instancia.

### DSN
```php
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
```
Define host, BD, charset utf8mb4 (soporta Unicode).

### Opciones
```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
```
Lanza PDOException → permite try/catch, debug claro.

```php
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
```
fetch() devuelve array asociativo legible.

```php
PDO::ATTR_EMULATE_PREPARES => false
```
Usa prepared statements nativos motor → más seguro.

```php
PDO::ATTR_PERSISTENT => false
```
Conexiones no persistentes (evita efectos inesperados).

### Creación
```php
self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
```
Instancia única guardada estáticamente.

### try/catch
```php
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
```
Muestra mensaje claro (desarrollo). En producción no exponer detalles.

### Prevención clon/deserialización
```php
private function __clone() {}
public function __wakeup(): void {
    throw new Exception("No se puede deserializar una instancia Singleton");
}
```
Garantiza unicidad instancia.

## 4. Por qué Singleton
- Una conexión por petición (eficiente)
- Centraliza acceso
- Facilita mantenimiento/test

## 5. Notas aprendizaje
Prepared statements + opciones seguras = base sólida.
