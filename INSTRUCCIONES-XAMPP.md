# Instrucciones para ejecutar en XAMPP

## 1. Copiar a htdocs

Copiar la carpeta `ProyectoBasket-Deploy` completa a:
```text
C:\xampp\htdocs\            (Windows)
/opt/lampp/htdocs/          (Linux)
/Applications/XAMPP/htdocs/ (macOS)
```

Puedes renombrarla (ej. `ProyectoBasket`). **La URL base se detecta sola**, no hay que cambiar nada.

## 2. Iniciar servicios XAMPP

Iniciar **Apache** y **MySQL** desde XAMPP Control Panel.

## 3. Crear la base de datos e importar

El archivo `database/schema.sql` **ya crea la base de datos** `liga_basquet` y todas las tablas.

1. Ir a `http://localhost/phpmyadmin`
2. Pestaña **Importar** (sin seleccionar ninguna BD)
3. Seleccionar `database/schema.sql` → **Ejecutar**
   - (Opcional) Luego importar `database/seeds.sql` para datos de prueba

> Seguro de reimportar: no da error si ya existe.

## 4. Acceder a la aplicación

```
http://localhost/ProyectoBasket-Deploy/public/
```

Reemplaza `ProyectoBasket-Deploy` por el nombre real de la carpeta si la renombraste.

## 5. Usuarios de prueba

Si importaste `seeds.sql`:
- Email: `demo@liga.com` | Contraseña: `password`
- Email: `admin@liga.com` | Contraseña: `password`

## 6. Requisitos

- **PHP 8.x** (XAMPP moderno ya lo incluye)
- **MySQL/MariaDB** (XAMPP usa MariaDB, compatible con el driver `mysql:` de PDO)

## 7. Si el CSS no carga

Verifica que entras por `.../public/` y que Apache está activo.
La URL base es dinámica: se calcula con el nombre real de la carpeta.
