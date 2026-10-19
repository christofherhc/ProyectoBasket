# Instrucciones para ejecutar en XAMPP

## 1. Copiar el proyecto

Copia la carpeta `ProyectoBasket` completa a tu carpeta de servidores:

```text
C:\xampp\htdocs\            (Windows)
/opt/lampp/htdocs/          (Linux)
/Applications/XAMPP/htdocs/ (macOS)
```

El nombre de la carpeta es libre: **la URL base se detecta sola**, no hay que cambiar nada en el código.

## 2. Iniciar servicios XAMPP

Inicia **Apache** y **MySQL** desde el XAMPP Control Panel.

## 3. Crear la base de datos e importar

El archivo `database/schema.sql` **ya crea la base de datos** `liga_basquet` y todas las tablas.

**Opción A — phpMyAdmin (gráfica):**

1. Ir a `http://localhost/phpmyadmin`
2. Pestaña **Importar** (sin seleccionar ninguna BD)
3. Seleccionar `database/schema.sql` → **Ejecutar**
   - (Opcional) Luego importar `database/seeds.sql` para datos de prueba

**Opción B — consola (más rápida):**

```bash
mysql -u root < database/schema.sql
mysql -u root < database/seeds.sql   # datos de prueba (opcional)
```

⚠️ **El comando va SIN el nombre de la base de datos.** `schema.sql` ya hace
el `CREATE DATABASE` y el `USE` por dentro. Si se escribe
`mysql -u root liga_basquet < database/schema.sql` y la base todavía no
existe, el cliente falla con `ERROR 1049: Unknown database 'liga_basquet'`
**antes de leer el script**, y no se crea nada.

> Seguro de reimportar: no da error si ya existe.

## 4. Acceder a la aplicación

```text
http://localhost/ProyectoBasket/public/
```

Sustituye `ProyectoBasket` por el nombre real de la carpeta si la renombraste.

### Alternativa recomendada: VirtualHost (URLs limpias)

Si quieres servir el proyecto desde la raíz del dominio en lugar de desde una
subcarpeta, edita `httpd-vhosts.conf` y añade:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/ProyectoBasket/public"
    ServerName ligabasquet.local
</VirtualHost>
```

Reinicia Apache y entra en `http://ligabasquet.local/`.

## 5. ⚠️ Por qué solo se sirve `public/`

El proyecto contiene, además del código, los scripts de la base de datos, la
documentación interna y el historial de Git. **Solo `public/` debe ser accesible
desde el navegador.**

Para que no dependas de la configuración del servidor, el proyecto incluye dos
archivos `.htaccess` que ya lo garantizan:

| Archivo | Qué hace |
|---|---|
| `.htaccess` (raíz) | Devuelve **403** a cualquier petición que no sea de `public/` |
| `public/.htaccess` | Vuelve a permitir el acceso dentro de `public/` |

Comprobado con Apache 2.4 en los tres casos habituales:

| Petición | Resultado |
|---|---|
| `/ProyectoBasket/public/index.php` | 200 (la aplicación) |
| `/ProyectoBasket/public/css/styles.css` | 200 (los estilos) |
| `/ProyectoBasket/database/seeds.sql` | **403** (hashes de las contraseñas de prueba) |
| `/ProyectoBasket/.git/config` | **403** (código fuente e historial) |
| `/ProyectoBasket/README.md` | **403** (documentación interna) |

⚠️ **Requisito del servidor:** Apache solo lee un `.htaccess` si el directorio
tiene `FollowSymLinks` activo. En la configuración por defecto de XAMPP ya lo
está. Si usas `php -S` (servidor embebido) el `.htaccess` **no** se aplica: en
ese caso, copia solo la carpeta `public/` a una carpeta servida por Apache.

## 6. Usuarios de prueba

Si importaste `seeds.sql`:
- Email: `demo@liga.com` | Contraseña: `password`
- Email: `admin@liga.com` | Contraseña: `password`

## 7. Requisitos

- **PHP 8.x** (XAMPP moderno ya lo incluye)
- **MySQL/MariaDB** (XAMPP usa MariaDB, compatible con el driver `mysql:` de PDO)

## 8. Si el CSS no carga

Verifica que entras por `.../public/` y que Apache está activo.
La URL base es dinámica: se calcula con el nombre real de la carpeta.
