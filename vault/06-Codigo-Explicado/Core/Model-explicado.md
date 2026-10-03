# Explicación detallada: Model.php

**Archivo:** `app/core/Model.php`  
**Tipo:** Core Base  
**Responsabilidad:** CRUD genérico reutilizable.

## Propósito
Herencia para modelos. Provee `$this->db` (PDO) y métodos comunes.

## Constructor
```php
public function __construct() {
    $this->db = Database::getConexion();
}
```
Obtiene conexión al instanciar modelo.

## Métodos
- `obtenerTodos()`: SELECT * tabla → fetchAll()
- `obtenerPorId(int $id)`: WHERE id :id LIMIT 1 → fetch() null si false
- `eliminar(int $id)`: DELETE WHERE id :id → bool
- `obtenerUltimoId()`: lastInsertId()

## Notas
`protected string $tabla` definido en hijo. SQL con `{$this->tabla}`. Prepared statements coherentes.
