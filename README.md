# Proyecto Liga de Básquetbol

Práctica Calificada 1: Gestión de una liga de básquetbol desarrollada en PHP vanilla con arquitectura MVC ligera y MySQL/MariaDB.

## Objetivo de aprendizaje

Este proyecto está diseñado para aprender desarrollo web paso a paso:
- Arquitectura MVC sencilla y legible
- PHP + PDO + MySQL
- Control de versiones Git (ramas por funcionalidad, commits atómicos)
- CRUD, relaciones entre tablas, sesiones y autenticación
- Documentación técnica

## Requisitos

- [XAMPP](https://www.apachefriends.org/es/index.html)
- MySQL/MariaDB
- Git

## Instalación y ejecución

1. **Clonar o ubicar el proyecto**

   Colocar la carpeta `ProyectoBasket/` donde se quiera, por ejemplo en `htdocs/`.

2. **Crear la base de datos**

   - Abrir phpMyAdmin (o MySQL CLI)
   - Crear base de datos: `liga_basquet`
   - Importar `database/schema.sql`
   - (Opcional) Importar `database/seeds.sql`

3. **Configurar conexión a BD**

   Editar `app/config/config.php` si es necesario (usuario/contraseña/host/nombre BD). Por defecto para XAMPP: `root`, contraseña vacía, BD `liga_basquet`.

4. **Elegir cómo servir la aplicación**

   La carpeta `public/` contiene el front controller y los recursos públicos.
   Hay dos formas de servirla:

   - **Con un VirtualHost** (lo recomendado, URLs limpias):

     ```apache
     <VirtualHost *:80>
         DocumentRoot "C:/xampp/htdocs/ProyectoBasket/public"
         ServerName ligabasquet.local
     </VirtualHost>
     ```

   - **Sin tocar la configuración**, dejando el proyecto completo dentro de
     `htdocs/` y entrando por `http://localhost/ProyectoBasket/`. El `index.php`
     de la raíz delega en `public/index.php`, y el `.htaccess` de la raíz
     bloquea `app/`, `database/`, `doc/`, `vault/`, `tests/` y `.git/`.

   ⚠️ **Por qué importa:** si el proyecto entero queda bajo el DocumentRoot sin
   el `.htaccess`, cualquiera podría descargar `database/seeds.sql` (con los
   hashes de las contraseñas de prueba) y el código fuente completo desde `.git/`.

5. **Ejecutar**

   Con VirtualHost: `http://ligabasquet.local/`.
   Sin él: `http://localhost/ProyectoBasket/`.
   La ruta con `/public/` también sigue funcionando.

## Licencia/uso

Proyecto educativo para fines de aprendizaje.
