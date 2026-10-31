# Proyecto Liga de Básquetbol

Práctica Calificada 1: Gestión de una liga de básquetbol desarrollada en PHP vanilla con arquitectura MVC ligera y MySQL/MariaDB.

## Objetivo de aprendizaje

Este proyecto está diseñado para aprender desarrollo web paso a paso:
- Arquitectura MVC sencilla y legible
- PHP + PDO + MySQL
- Control de versiones Git (ramas por funcionalidad, commits atómicos)
- CRUD, relaciones entre tablas, sesiones y autenticación
- Documentación técnica y aprendizaje estructurado (Vault)

## Requisitos

- [XAMPP](https://www.apachefriends.org/es/index.html), [Laragon](https://laragon.org/es) o [WAMP](https://www.wampserver.com/en/) (PHP 8.x recomendado)
- MySQL/MariaDB
- Git

## Estructura del proyecto

```text
ProyectoBasket/
├── .htaccess                    # Enruta a index.php y bloquea las carpetas internas
├── index.php                    # Entrada desde la raíz (delega en public/index.php)
├── app/
│   ├── config/config.php        # Configuración de BD y rutas
│   ├── core/                    # Clases base (Database, Model, Controller, Router, LimitadorIntentos) y helpers
│   ├── models/                  # Modelos (Usuario, Equipo, Jugador, Partido)
│   ├── controllers/             # Controladores (Auth, Equipos, Jugadores, Partidos, Estadisticas)
│   └── views/                   # Vistas con layout base
├── public/
│   ├── .htaccess                # Autoriza el acceso (única carpeta pública)
│   ├── index.php                # Front controller (punto de entrada)
│   └── css/styles.css           # Estilos propios
├── database/
│   ├── schema.sql               # Esquema de base de datos
│   └── seeds.sql                # Datos de prueba opcionales
├── doc/
│   └── practica_liga.md         # Documento técnico obligatorio (500–800 palabras)
├── vault/                       # Material de estudio Obsidian (amplio y explicativo)
└── README.md
```

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

## Flujo de desarrollo (Git)

- Rama principal: `main`
- Ramas por funcionalidad, una por bloque de trabajo, que se integran en `main` con un commit de fusión (`--no-ff`) para que quede constancia de qué entró en cada momento:
  - `feature/integridad-equipos` — el nombre de equipo no puede repetirse
  - `fix/seguridad-auth` — el bloqueo de intentos de login pasa a ser por IP
  - `fix/robustez-datos` — ids estrictos, claves foráneas recuperables, escapado unificado
- Commits frecuentes, atómicos y con mensajes descriptivos (tipo convencional: `feat:`, `fix:`, `docs:`, `test:`)
- Cada bloque de trabajo lleva sus propias pruebas en `tests/regresion.sh` (97 comprobaciones)

### Publicar en GitHub

El repositorio todavía no tiene remoto. Para publicarlo:

```bash
git remote add origin https://github.com/TU-USUARIO/ProyectoBasket.git
git push -u origin main
git push origin feature/integridad-equipos
git push origin fix/seguridad-auth
git push origin fix/robustez-datos
```

A partir de ahí, cada cambio nuevo sigue el mismo ciclo: rama → commits → pull request → fusión en `main`.

## Vault de estudio

La carpeta `vault/` contiene explicaciones **amplias y paso a paso** de cada parte del proyecto (fundamentos, arquitectura, código explicado línea por línea, procedimientos día a día, diario de aprendizaje, glosario). Está pensada para estudiar primero, entender el código y luego replicarlo por cuenta propia.

## Licencia/uso

Proyecto educativo para fines de aprendizaje.