# Estructura de Carpetas - Explicación Detallada

## 1. Visión General

La estructura está pensada para **MVC ligero + organización clara**. Cada carpeta tiene una **responsabilidad única**. El objetivo: que al abrir cualquier archivo sepas **inmediatamente** dónde estás y qué función cumple.

Todo orientado a **facilitar lectura, estudio y comprensión** (ideal para "leer código ajeno").

## 2. Árbol Completo con Explicación

```text
ProyectoBasket/
│
├── .htaccess                   # Bloquea el acceso a todo el proyecto salvo public/
│
├── app/                        # Núcleo de la aplicación (lógica MVC)
│   ├── config/                 # Configuración global
│   │   └── config.php          # Constantes BD, BASE_URL, rutas
│   │
│   ├── core/                   # Clases base reutilizables
│   │   ├── Database.php        # Conexión PDO singleton
│   │   ├── Model.php           # Clase base para modelos (CRUD común)
│   │   ├── Controller.php      # Clase base para controladores (método view)
│   │   ├── Router.php          # Enrutamiento + Front Controller logic
│   │   └── helpers.php         # Funciones compartidas: CSRF, flash, validación
│   │
│   ├── models/                 # Modelos (acceso a datos + lógica negocio)
│   │   ├── Usuario.php         # Autenticación/usuarios
│   │   ├── Equipo.php          # CRUD equipos
│   │   ├── Jugador.php         # Jugadores + relación equipo
│   │   └── Partido.php         # Partidos + lógica resultados
│   │
│   ├── controllers/            # Controladores (coordinadores)
│   │   ├── AuthController.php
│   │   ├── EquiposController.php
│   │   ├── JugadoresController.php
│   │   ├── PartidosController.php
│   │   └── EstadisticasController.php
│   │
│   └── views/                  # Vistas (HTML + PHP mínimo)
│       ├── layouts/
│       │   └── main.php        # Plantilla base (header/nav/footer)
│       ├── auth/
│       │   ├── login.php
│       │   └── register.php
│       ├── equipos/
│       │   ├── index.php       # Listar
│       │   ├── crear.php       # Form crear
│       │   └── editar.php      # Form editar
│       ├── jugadores/
│       │   ├── index.php
│       │   ├── crear.php
│       │   └── editar.php
│       ├── partidos/
│       │   ├── index.php
│       │   ├── crear.php
│       │   └── editar.php
│       └── estadisticas/
│           └── index.php
│
├── public/                     # Carpeta pública (DocumentRoot)
│   ├── .htaccess               # Autoriza el acceso (contrapartida del de la raíz)
│   ├── index.php               # ÚNICO punto de entrada (Front Controller)
│   └── css/
│       └── styles.css          # Estilos CSS puros (legibles)
│
├── database/                   # SQL de base de datos
│   ├── schema.sql              # Estructura: tablas, PK, FK, constraints
│   └── seeds.sql               # Datos de prueba para estudiar/probar
│
├── doc/                        # Documentación obligatoria
│   └── practica_liga.md        # 500–800 palabras (requisito)
│
├── vault/                      # Material de estudio Obsidian (amplio)
│   ├── 00-MOC/                 # Mapa navegación
│   ├── 01-Introduccion/
│   ├── 02-Fundamentos/         # Explicaciones amplias
│   ├── 03-Arquitectura/
│   ├── 04-BD/
│   ├── 05-Procedimientos/      # Día a día
│   ├── 06-Codigo-Explicado/    # Línea por línea
│   ├── 07-Diario-de-Aprendizaje/
│   ├── 08-Errores-y-Soluciones/
│   ├── 09-Glosario/
│   ├── 10-Recursos/
│   └── _Templates/
│
├── .gitignore                  # Archivos a ignorar por Git
└── README.md                   # Guía rápida ejecución
```

## 3. Explicación Por Carpeta

### `app/` - Corazón de la aplicación

Contiene **TODO** el código PHP de negocio. Fuera de `public/` por seguridad (no accesible directo vía URL si DocumentRoot bien configurado).

### `app/config/`

**Propósito:** Centralizar configuración.

- `config.php` → Define constantes globales: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`, `BASE_URL`. Evita valores fijos dispersos.

### `app/core/`

**Propósito:** Clases base reutilizables. Aquí reside la "arquitectura" del MVC ligero.

| Archivo | Responsabilidad detallada | ¿Por qué? |
|---|---|---|
| `Database.php` | Conexión PDO singleton. Retorna instancia única `$pdo`. Configura opciones seguras. | Reutilizable por todos modelos. Evita múltiples conexiones. Centraliza config BD. |
| `Model.php` | Clase padre con lógica común (obtenerTodos, obtenerPorId, crear, actualizar, eliminar...). Usa `$this->db`. Define `$tabla`. | DRY: evita duplicar CRUD en cada modelo. Modelos solo extienden y especializan. |
| `Controller.php` | Clase padre con `view($vista, $datos)`. Hace `extract()` y carga layout `main.php`. | DRY: todos controladores comparten renderizado. Centraliza paso datos→vista. |
| `Router.php` | Lee `$_GET[controller/action/params]`, contrasta contra la lista blanca `RUTAS`, carga el controlador y ejecuta la acción. | Separa lógica de enrutamiento. La lista blanca impide cargar archivos ajenos y llamar métodos protegidos. |
| `helpers.php` | Funciones puras sin clase: `post()`, `get()`, `e()`, `csrfToken()`, `csrfCampo()`, `exigirCsrf()`, `exigirPost()`, `redir()`, `flashError()`, `flashExito()`, `cabeEnColumna()`, `fechaValida()`, `errorAmigable()`. | Se cargan una vez en `index.php`. Al ser funciones y no métodos, cualquier vista puede llamarlas sin tener un objeto. |

### `app/models/`

**Propósito:** Capa de datos y lógica de negocio relacionada a entidades.

Cada modelo representa **una entidad** del dominio (Usuario, Equipo, Jugador, Partido).

**Reglas:**
- Solo acceso a BD + reglas de entidad
- **NO** imprime HTML
- **NO** maneja sesiones/redirecciones (eso va a Controller)
- Retorna arrays/valores (datos puros)

| Modelo | Explicación |
|---|---|
| `Usuario.php` | CRUD usuarios + `buscarPorEmail`, `existeEmail`. Lógica auth. |
| `Equipo.php` | CRUD equipos. Validaciones mínimas a nivel modelo si aplica. |
| `Jugador.php` | CRUD jugadores + listar con JOIN (`equipo_id` → nombre equipo). |
| `Partido.php` | CRUD partidos + 2 FKs (local/visitante). Lógica para calcular ganador/empate. Consultas con JOINs para mostrar nombres. |

### `app/controllers/`

**Propósito:** Capa de coordinación. "Director de orquesta".

Recibe peticiones, valida, llama modelos, pasa datos a vistas, redirige.

**Reglas:**
- Delgados (mínima lógica)
- **NO** escribe SQL directo (usa modelos)
- **NO** consulta BD
- Maneja `$_GET/$_POST/$_SESSION`, redirecciones, mensajes flash

| Controlador | Funciones |
|---|---|
| `AuthController.php` | Login, registro, logout, protección lógica básica |
| `EquiposController.php` | CRUD completo equipos |
| `JugadoresController.php` | CRUD completo jugadores (con selector equipos) |
| `PartidosController.php` | Registrar partidos, validación (mismo equipo), mostrar listados |
| `EstadisticasController.php` | Calcular métricas: PJ, PG, PE, PP, etc. por equipo |

### `app/views/`

**Propósito:** Capa de presentación (HTML). Lo único visible al usuario.

**Filosofía:** Vistas **simples**. Máximo bucles/condicionales `if/foreach`. Sin consultas SQL.

#### `layouts/main.php`

Plantilla única. Contiene:
- `<head>` con meta, título dinámico, CSS
- `<header>` con logo/título
- `<nav>` con enlaces (Login/Equipos/Jugadores/Partidos/Estadísticas/Logout)
- `<main>` → aquí se inserta vista específica
- `<footer>`
- Mensajes flash globales si desea

**Ventaja:** Cambiar estructura una sola vez.

#### Carpetas por módulo

Cada módulo tiene su carpeta con vistas coherentes:
- `index.php` → Listado
- `crear.php` → Formulario creación
- `editar.php` → Formulario edición

Patrón consistente → fácil aprender.

### La raíz del proyecto y el `.htaccess`

**Propósito:** aquí viven el código, los scripts SQL, la documentación y el historial de Git. **Nada de esto debe ser descargable desde el navegador.**

**El problema real:** la instalación por defecto de XAMPP copia el proyecto entero dentro de `htdocs/`, así que Apache sirve *todas* sus carpetas. Sin protección, cualquiera podría descargar:

| Archivo | Qué se obtiene |
|---|---|
| `database/seeds.sql` | Los hashes bcrypt de las contraseñas de los usuarios de prueba |
| `.git/` | El código fuente completo y todo el historial de commits |
| `vault/`, `doc/` | La documentación interna del proyecto |

**La solución en dos niveles:**

1. **La principal:** apuntar el `DocumentRoot` (o un `Alias`) a la carpeta `public/`. Con eso, el resto del proyecto ni siquiera existe para el servidor web.
2. **La red de seguridad:** el archivo `.htaccess` de esta raíz, que devuelve **403** a cualquier petición que no sea de `public/`.

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^public/ - [L]   # lo que cuelgue de public/ se permite
    RewriteRule ^ - [F,L]        # todo lo demás: prohibido (403)
</IfModule>

<IfModule mod_authz_core.c>
    Require all denied           # y la red de seguridad, por si mod_rewrite no está
</IfModule>
```

Y en `public/.htaccess` está la contrapartida, que vuelve a permitir el acceso:

```apache
<IfModule mod_authz_core.c>
    Require all granted
</IfModule>
```

⚠️ **Las dos reglas son independientes y cada una basta por sí sola**, así que la protección se mantiene aunque el servidor no tenga `mod_rewrite`. Se comparan rutas **relativas** a esta carpeta, de modo que da igual cómo se llame la carpeta de instalación.

**Verificado con Apache 2.4** en los tres escenarios habituales: proyecto entero dentro de `htdocs/`, `DocumentRoot` apuntando a `public/` y ruta de instalación que contiene la palabra "public". En los tres, `public/` responde 200 y todo lo demás 403.

### `public/`

**Propósito:** Única carpeta accesible desde web (DocumentRoot).

**Seguridad:** Código sensible (`app/`, `database/`, `vault/`, `doc/`) **NO** debe estar bajo `public/`.

- `index.php` → Front Controller. Carga todo core + router. Inicia sesión.
- `css/styles.css` → Estilos propios, legibles, comentados mínimos.
- `.htaccess` → Permite el acceso dentro de esta carpeta (la única pública).

**Acceso:** `http://localhost/ProyectoBasket/public/`

### `database/`

**Propósito:** Scripts SQL versionados con código.

- `schema.sql` → Define estructura BD: CREATE TABLE, FOREIGN KEY, INDEX, ENGINE, CHARSET. Importar primero.
- `seeds.sql` → Datos de prueba (equipos, jugadores). Útil para ver app funcionando rápido.

Ambos comentados en `schema-explicado.md` del vault.

### `doc/`

**Propósito:** Cumplir requisito obligatorio.

- `practica_liga.md` → Documento técnico 500–800 palabras: arquitectura, componentes, funcionalidades, fragmentos, dificultades, decisiones.

### `vault/`

**Propósito:** Material didáctico completo (Obsidian). NO va a producción, solo estudio.

Explicaciones amplias: fundamentos, arquitectura, código línea por línea, procedimientos día a día, diario, errores, glosario.

### Archivos raíz

| Archivo | Propósito |
|---|---|
| `.gitignore` | Excluye IDE, logs, tmp, etc. Mantiene repo limpio. |
| `README.md` | Guía instalación/ejecución rápida para cualquiera (incluyéndote futuro). |

## 4. Flujo de Dependencias (Quién depende de Quién)

```
petición navegador
        ↓
public/index.php (front controller)
        ↓ carga core + inicia sesión
Router (core)
        ↓ decide controller/action
Controller (hereda Controller base)
        ↓ usa Model
Model (hereda Model base) → Database (PDO)
        ↓ consulta BD
Controller → pasa datos → View (dentro layout)
        ↓ genera HTML
respuesta navegador
```

**Dependencias claras:** Unidireccionales. Evita acoplamiento innecesario.

## 5. ¿Por qué esta Estructura?

| Razón | Explicación |
|---|---|
| **Separación responsabilidades** | MVC puro y claro. Fácil identificar capa. |
| **Seguridad** | `app/` fuera `public/` → archivos PHP no accesibles directo. Solo `index.php`. |
| **Escalabilidad ordenada** | Añadir módulo = nueva carpeta/vista + modelo+controlador. Sin caos. |
| **Legibilidad** | Nombres intuitivos. Cualquier principiante entiende. |
| **Didáctica** | Muestra arquitectura real pero simple. Perfecto para aprender/replicar. |
| **Cumple requisitos** | `doc/`, `.git`, estructura clara. |
| **Reutilización** | Clases base `core/` evitan repetición. |

## 6. Convenciones de Archivos

- **Clases:** PascalCase (`Equipo.php`, `EquiposController.php`)
- **Vistas:** minúsculas (`index.php`, `crear.php`)
- **CSS/JS:** minúsculas (`styles.css`)
- **SQL:** minúsculas (`schema.sql`)
- **Carpetas:** minúsculas/plurales coherentes

## 7. Configuración DocumentRoot

**Recomendado:** Apuntar Apache a `ProyectoBasket/public/`

**En XAMPP (httpd-vhosts.conf o panel):**
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/ProyectoBasket/public"
    ServerName ligabasquet.local
</VirtualHost>
```

O simplemente usar: `http://localhost/ProyectoBasket/public/`

Ambos funcionan. Con VirtualHost URLs más limpias.

⚠️ **El `DocumentRoot` es la solución principal, pero no la única.** Si el proyecto se deja dentro de `htdocs/` y se entra por `.../public/`, el `.htaccess` de la raíz se encarga de bloquear el resto (ver la sección de la raíz del proyecto más arriba). La combinación de las dos cosas es la más segura: el `.htaccess` protege aunque alguien cambie la configuración del servidor por error.

⚠️ **`php -S` no lee `.htaccess`.** El servidor embebido de PHP ignora ese archivo, así que con él no hay protección: úsalo solo para desarrollo local y nunca expongas esa carpeta a Internet.

## 8. Verificación Rápida

Tras crear estructura, comprobar:
- [ ] `public/index.php` existe y carga core
- [ ] `app/config/config.php` creado
- [ ] `app/core/*` creados
- [ ] Carpetas vacías coherentes
- [ ] `database/schema.sql` creado (más adelante)
- [ ] `doc/practica_liga.md` se creará Día 12
- [ ] `vault/` completo con índices

## 9. Resumen

Estructura **minimalista pero completa**. Cada archivo en su sitio lógico. Sigue principios MVC clásicos sin sobreingeniería. Diseñada explícitamente para **entender, no adivinar**.