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
├── app/
│   ├── config/config.php        # Configuración de BD y rutas
│   ├── core/                    # Clases base (Database, Model, Controller, Router)
│   ├── models/                  # Modelos (Usuario, Equipo, Jugador, Partido)
│   ├── controllers/             # Controladores (Auth, Equipos, Jugadores, Partidos, Estadisticas)
│   └── views/                   # Vistas con layout base
├── public/
│   ├── index.php                # Front controller (punto de entrada)
│   ├── css/styles.css           # Estilos propios
│   └── js/app.js                # JS mínimo
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

   Colocar la carpeta `ProyectoBasket/` en el directorio de tu servidor (ej. `htdocs/` en XAMPP).

2. **Crear la base de datos**

   - Abrir phpMyAdmin (o MySQL CLI)
   - Crear base de datos: `liga_basquet`
   - Importar `database/schema.sql`
   - (Opcional) Importar `database/seeds.sql`

3. **Configurar conexión a BD**

   Editar `app/config/config.php` si es necesario (usuario/contraseña/host/nombre BD). Por defecto para XAMPP: `root`, contraseña vacía, BD `liga_basquet`.

4. **Configurar DocumentRoot (recomendado)**

   Apuntar el servidor a `ProyectoBasket/public/` para tener URLs limpias. En XAMPP puedes usar un VirtualHost o acceder como `http://localhost/ProyectoBasket/public/`.

5. **Ejecutar**

   Abrir `http://localhost/ProyectoBasket/public/` en el navegador.

## Flujo de desarrollo (Git)

- Rama principal: `main`
- Ramas por funcionalidad: `feature/auth`, `feature/equipos`, `feature/jugadores`, `feature/partidos`, `feature/estadisticas`, `feature/docs`
- Commits frecuentes, atómicos y descriptivos
- Avance progresivo (requisito de la práctica)

## Vault de estudio

La carpeta `vault/` contiene explicaciones **amplias y paso a paso** de cada parte del proyecto (fundamentos, arquitectura, código explicado línea por línea, procedimientos día a día, diario de aprendizaje, glosario). Está pensada para estudiar primero, entender el código y luego replicarlo por cuenta propia.

## Licencia/uso

Proyecto educativo para fines de aprendizaje.