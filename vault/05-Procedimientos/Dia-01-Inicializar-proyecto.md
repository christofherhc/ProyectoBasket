# Día 01 - Inicialización del proyecto y estructura base

**Fecha sugerida:** Día 1 (Semana 1)
**Rama:** `main` (inicial)
**Objetivo:** Preparar el terreno: repositorio Git, estructura de carpetas, archivos base y orden del proyecto.
**Temas a comprender:** `git init`, `git branch -M`, `git commit`, `.gitignore`, organización de carpetas en MVC, y por qué `public/` debe ser el único DocumentRoot.

## 1. Contexto

Antes de escribir una línea de PHP hay que decidir **dónde vive cada cosa**. Es la decisión más barata de tomar y la más cara de corregir después.

**El criterio que se aplicó:** todo lo que el navegador podría pedir directamente (`.php`, `.css`, imágenes) va en `public/`. Todo lo que contiene lógica (controladores, modelos, vistas, configuración) va fuera, en `app/`.

⚠️ **Por qué importa tanto.** Si los controladores estuvieran dentro de `public/`, cualquiera podría pedir `/app/controllers/AuthController.php` por el navegador y ver el **código fuente completo**, incluida la contraseña de la base de datos en `config.php`. Es una de las vulnerabilidades más graves y más comunes en proyectos PHP. Con `DocumentRoot` en `public/`, esos archivos están fuera del alcance del servidor web.

## 2. Preparación

- XAMPP instalado (Apache + PHP + MariaDB).
- Un editor de texto (VS Code, Sublime, Notepad++, lo que sea).
- Git instalado: `git --version`.

## 3. Pasos realizados

### Paso 1.1: Verificar el entorno

```bash
php -v          # versión de PHP
mysql --version # versión de MySQL/MariaDB
git --version   # versión de Git
```

⚠️ **Anotar las versiones tiene una razón concreta:** cuando algo no funcione, la primera pregunta es "¿es mi código o es la versión?". Tener las versiones anotadas ahorra media hora de depuración.

### Paso 1.2: Crear la carpeta e inicializar Git

```bash
mkdir -p ~/Descargas/ProyectoBasket
cd ~/Descargas/ProyectoBasket
git init
git branch -M main
```

⚠️ **`git branch -M main` renombra la rama inicial.** Por defecto Git crea una rama llamada `master`. El enunciado pide `main`, y por eso el `-M` (mayúscula) la renombra desde el principio. **Renombrar después obliga a reconfigurar los remotos; hacerlo en el día 1 es gratis.**

### Paso 1.3: Crear el `.gitignore`

```gitignore
# Credenciales y datos sensibles
.env

# Sistema operativo
.DS_Store
Thumbs.db

# Editores
.vscode/
.idea/
*.swp

# Logs y temporales
*.log
*.tmp

# Sistema de archivos
/vendor/
/node_modules/

# ⚠️ NO ignorar .git: es la carpeta que hace que esto sea un repositorio
```

⚠️ **Lo que NO debe ir en `.gitignore`:** la carpeta `.git` misma (si la ignoras, el proyecto deja de ser un repositorio), los archivos `.php` del proyecto (son el código fuente), ni los `.md` de documentación.

⚠️ **Y lo que sí debe ignorarse siempre:** cualquier archivo con contraseñas o claves. En este proyecto la contraseña de MySQL está en `app/config/config.php` porque es un proyecto local (`root` sin clave en XAMPP). En un proyecto real, eso iría en un `.env` que está en `.gitignore` y que se crea a partir de un `.env.example`.

### Paso 1.4: Crear la estructura de carpetas

```bash
mkdir -p app/{config,core,controllers,models,views/{layouts,auth,equipos,jugadores,partidos,estadisticas}}
mkdir -p public/css
mkdir -p database
mkdir -p doc
mkdir -p vault/{00-MOC,01-Introduccion,02-Fundamentos,03-Arquitectura,04-BD,05-Procedimientos,06-Codigo-Explicado/{Core,Controllers,Models,Vistas},07-Diario-de-Aprendizaje,08-Errores-y-Soluciones,09-Glosario,10-Recursos,_Templates}
```

**Una decisión: el vault (mi carpeta de conocimiento) va dentro del proyecto.** Podría estar fuera, en una carpeta personal. Se metió dentro por dos razones:
1. Viaja con el proyecto (si lo compartes o lo entregas, el conocimiento va con él).
2. Git lo versiona, así que tienes historial de lo que aprendiste.

⚠️ **El orden de las carpetas dentro de `views/` es el mismo que los controladores:** un directorio por módulo. Si mañana se añade `categorias`, se crea `views/categorias/` y `CategoriasController.php`. La convención consistente es lo que hace predecible un proyecto.

### Paso 1.5: El primer commit

```bash
git add .
git status                    # revisar QUÉ se va a añadir, antes de añadir
git commit -m "chore: inicializa estructura base del proyecto Liga de Básquetbol"
```

⚠️ **`git status` ANTES de `git commit`.** Siempre. Un `git add .` a ciegas puede meter un archivo con contraseñas. Mirar qué se va a guardar es un hábito que evita accidentes.

⚠️ **El prefijo del mensaje (`chore:`) no es decoración.** Es una convención muy extendida:

| Prefijo | Significado |
|---|---|
| `chore:` | Tareas de mantenimiento (estructura, configuración) |
| `feat:` | Funcionalidad nueva |
| `fix:` | Corrección de un bug |
| `docs:` | Documentación |
| `refactor:` | Reestructurar sin cambiar comportamiento |

Un historial donde se lee la intención de cada cambio es **mucho** más útil que veinte commits de "cambios varios".

## 4. Archivos creados

| Archivo | Tipo | Descripción |
|---|---|---|
| `.gitignore` | crear | Exclusiones de Git (`.env`, editores, logs) |
| `README.md` | crear | Descripción del proyecto, requisitos, cómo ejecutar |
| `app/`, `public/`, `database/`, `doc/`, `vault/` | crear | Estructura de carpetas |

## 5. Explicación técnica: `DocumentRoot` y por qué `public/`

**DocumentRoot** es la carpeta desde la que el servidor web sirve archivos. En XAMPP es `C:\xampp\htdocs` (o `/opt/lampp/htdocs` en Linux).

**Diseño correcto:**
```
ProyectoBasket/                  ← FUERA del alcance del navegador
├── app/
│   ├── config/config.php        ← contiene la contraseña de MySQL
│   ├── controllers/             ← lógica de negocio
│   └── views/                   ← HTML
└── public/                      ← ← ESTE es el DocumentRoot
    └── index.php                ← el único punto de entrada
```

El navegador solo puede pedir cosas dentro de `public/`. `/app/config/config.php` no existe para el servidor web.

**Diseño INCORRECTO (el error clásico):**
```
htdocs/proyecto/
├── index.php
├── config.php     ← accesible: /proyecto/config.php
└── controllers/   ← accesible: /proyecto/controllers/AuthController.php
```

⚠️ **Un `require` no es una barrera de seguridad.** Aunque `config.php` esté "protegido" porque lo incluye `index.php`, el navegador puede pedir el archivo directamente y ver su código en texto plano. Solo la ubicación física fuera del DocumentRoot lo protege.

**Configurar XAMPP** para que apunte a `public/`:
- Windows: Panel de XAMPP → Apache → `Config` → `httpd.conf` → `DocumentRoot "C:/xampp/htdocs/proyecto/public"`
- O, más simple en este proyecto: copiar la carpeta a `htdocs` y acceder a `/proyecto/public/index.php`.

## 6. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| `git init` crea el repositorio | ✅ |
| `git branch` muestra `main` | ✅ |
| `.gitignore` no ignora `.git` ni los `.php` | ✅ |
| La estructura de carpetas está completa | ✅ |
| El primer commit se creó con mensaje descriptivo | ✅ |
| `public/` contiene lo único accesible | ✅ |

## 7. Commits realizados

```
chore: inicializa estructura base del proyecto Liga de Básquetbol
```

## 8. Aprendizajes del día

1. **`public/` es el único DocumentRoot.** Todo lo demás queda fuera del alcance del navegador. No es una cuestión de estética: es seguridad.
2. **Renombrar `master` a `main` en el día 1 es gratis.** Hacerlo después obliga a tocar la configuración de los remotos.
3. **`git status` antes de `git commit`.** Siempre. Evita subir un archivo con contraseñas por accidente.
4. **Los prefijos (`chore:`, `feat:`, `fix:`) hacen legible el historial.** Con veinte commits, se puede ver qué cambió sin abrir los archivos.
5. **La estructura de carpetas es una decisión cara de cambiar.** Diez minutos decidiendo ahora evitan reorganizar 20 archivos después.
6. **Un proyecto de aprendizaje también debe documentarse desde el día 1.** El vault se crea antes que el código para que no se quede sin escribir.

## 9. Dudas pendientes

| # | Duda | Estado |
|---|---|---|
| 1 | ¿Cómo configurar el DocumentRoot de XAMPP a `public/`? | Pendiente documentar en el README |
| 2 | ¿Las credenciales de MySQL pueden quedar en `config.php`? | Aceptable en local; en real, `.env` |
| 3 | ¿La contraseña de MySQL debería estar en el `.gitignore`? | Se resolvió con `.env` para producción |

⚠️ **La 2 es un compromiso consciente, no un descuido.** En XAMPP, MySQL corre con `root` y **sin contraseña** por defecto, así que no hay ningún secreto real que proteger. En un servidor real, iría en un `.env`.

## 10. Próximos pasos

**Día 02 - Core MVC + Front Controller:**
- `app/config/config.php` (conexión y `BASE_URL` dinámico).
- Las 4 clases base: `Database` (Singleton), `Model` (CRUD), `Controller` (view + auth), `Router` (enrutado).
- `public/index.php` como front controller.
- Layout, CSS y autenticación básica.