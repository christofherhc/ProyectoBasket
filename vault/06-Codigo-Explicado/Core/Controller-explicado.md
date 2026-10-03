# Explicación detallada: Controller.php

**Archivo:** `app/core/Controller.php`

## view()
```php
protected function view(string $vista, array $datos = []): void {
    if (!empty($datos)) extract($datos);
    require_once '../app/views/layouts/main.php';
}
```
Convierte claves array a variables PHP. Carga layout principal.

## requiereLogin()
Verifica `$_SESSION['usuario_id']`. Redirige a Auth con error flash y `exit`.

## Filosofía
Controladores delgados, heredan helpers.
