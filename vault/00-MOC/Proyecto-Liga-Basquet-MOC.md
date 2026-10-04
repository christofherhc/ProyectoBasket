# Mapa General - Proyecto Liga de Básquetbol (MOC)

## 1. Propósito del MOC

Este Mapa de Contenido (MOC) sirve como punto de partida para navegar todo el material de estudio del proyecto. Está diseñado para facilitar el aprendizaje progresivo: primero entender los fundamentos, luego la arquitectura, seguir los procedimientos día a día y, finalmente, repasar el código explicado con profundidad.

El objetivo es que puedas **leer, entender y replicar** este proyecto por tu cuenta, sin necesidad de asumir conceptos ni tener que buscar información básica constantemente.

## 2. Ruta de Aprendizaje Recomendada (Secuencial)

Para aprovechar al máximo el material, se recomienda recorrerlo en el siguiente orden. Cada sección construye sobre la anterior.

| Orden | Sección | Propósito | Tiempo estimado |
|---|---|---|---|
| 1 | [[01-Introduccion/Objetivo-del-proyecto]] | Comprender qué se va a desarrollar y por qué | 5–10 min |
| 2 | [[01-Introduccion/Que-aprendere]] | Ver listado completo de habilidades a adquirir | 5 min |
| 3 | [[02-Fundamentos/01-Git-y-GitHub]] | Control de versiones, ramas, commits, PRs | 1–2 h |
| 4 | [[02-Fundamentos/02-PHP-estructurado]] | PHP necesario para este proyecto (amplio) | 2–3 h |
| 5 | [[02-Fundamentos/03-PDO-y-MySQL]] | Base de datos, conexiones seguras, relaciones | 1–2 h |
| 6 | [[02-Fundamentos/04-MVC-sencillo]] | Arquitectura MVC explicada de forma muy clara | 30–60 min |
| 7 | [[02-Fundamentos/05-Routing]] | Front Controller y rutas limpias | 20–40 min |
| 8 | [[02-Fundamentos/06-Sesiones-y-Auth]] | Autenticación, sesiones y protección de rutas | 30–60 min |
| 9 | [[03-Arquitectura/Estructura-de-carpetas]] | Por qué cada carpeta y archivo | 15 min |
| 10 | [[03-Arquitectura/Diagrama-conceptual]] | Flujo completo de la aplicación | 15 min |
| 11 | [[03-Arquitectura/Decisiones-de-diseno]] | Justificaciones técnicas (importante para doc) | 20 min |
| 12 | [[04-BD/Modelo-ER]] | Entidades, relaciones 1:N, campos | 20–40 min |
| 13 | [[04-BD/schema-explicado]] | `schema.sql` línea por línea (muy detallado) | 30–60 min |
| 14 | [[04-BD/Datos-prueba]] | Seeds y utilidad para pruebas/estudio | 10 min |
| 15 | **Procedimientos** → [[05-Procedimientos/Dia-01-Inicializar-proyecto]] | Seguir día a día los 8 días reales de trabajo | 2 semanas |
| 16 | **Código Explicado** → [[06-Codigo-Explicado/Core/Database-explicado]] | Repasar cada archivo con explicación amplia (tras crearlo) | Paralelo a procedimientos |
| 17 | [[07-Diario-de-Aprendizaje/Diario-Dia-02]] | Registrar lo aprendido, dudas y comprensión | Diario diario (5–10 min) |
| 18 | [[08-Errores-y-Soluciones/Registro-de-errores]] | Consolidar soluciones encontradas | Según necesidad |
| 19 | [[09-Glosario/Terminos-clave]] | Consultar términos cuando surjan dudas | Permanente |
| 20 | [[10-Recursos/Enlaces-de-estudio]] | Ampliación si algún concepto necesita reforzarse | Opcional |

## 3. Navegación por Áreas

### 01. Introducción
- [[01-Introduccion/Objetivo-del-proyecto]] – Alcance, requisitos de la práctica, enfoque de aprendizaje.
- [[01-Introduccion/Que-aprendere]] – Competencias técnicas y blandas que desarrollarás.

### 02. Fundamentos (Explicaciones Amplias)
- [[02-Fundamentos/01-Git-y-GitHub]] – Conceptos, flujo feature branches, commits atómicos, PRs, buenas prácticas.
- [[02-Fundamentos/02-PHP-estructurado]] – Sintaxis, includes, funciones, tipos, flujo, seguridad básica. Explicado para principiantes con ejemplos claros.
- [[02-Fundamentos/03-PDO-y-MySQL]] – Prepared statements, fetch, relaciones, integridad referencial, prevención de SQLi.
- [[02-Fundamentos/04-MVC-sencillo]] – Separación de responsabilidades, ventajas para aprender/leer código ajeno.
- [[02-Fundamentos/05-Routing]] – Front Controller, parámetros GET, URLs limpias, mapeo controlador/acción.
- [[02-Fundamentos/06-Sesiones-y-Auth]] – $_SESSION, login/registro, hash de contraseñas, protección de rutas.

### 03. Arquitectura
- [[03-Arquitectura/Estructura-de-carpetas]] – Explicación del porqué de cada carpeta, responsabilidad y flujo de dependencias.
- [[03-Arquitectura/Diagrama-conceptual]] – Flujo de petición (navegador → public/index.php → Router → Controller → Model → DB → View → HTML).
- [[03-Arquitectura/Decisiones-de-diseno]] – Decisiones técnicas justificadas (vanilla PHP, MVC ligero, CSS puro, rutas simples). Útil para el punto 6 del documento técnico.

### 04. Base de Datos
- [[04-BD/Modelo-ER]] – Entidades (usuarios, equipos, jugadores, partidos), atributos, cardinalidades, claves foráneas.
- [[04-BD/schema-explicado]] – Explicación línea por línea de `database/schema.sql`: tipos, constraints, índices, ON DELETE/UPDATE, coherencia.
- [[04-BD/Datos-prueba]] – `seeds.sql` explicado, casos útiles para pruebas y comprensión de datos.

### 05. Procedimientos (Día a Día)
Pasos detallados para construir el proyecto progresivamente. Son los **8 días reales** en los que se construyó (no una planificación ideal): cada uno incluye contexto, pasos minuciosos, archivos, pruebas, commits, aprendizajes y las dudas que quedaron pendientes.

| Día | Tema | Lo que se aprende |
|---|---|---|
| 01 | [[05-Procedimientos/Dia-01-Inicializar-proyecto]] | Git, `.gitignore`, estructura de carpetas, `DocumentRoot` |
| 02 | [[05-Procedimientos/Dia-02-Core-MVC]] | Front Controller, Router, Singleton PDO, sesiones, layout |
| 03 | [[05-Procedimientos/Dia-03-Equipos-CRUD]] | El patrón CRUD completo: Modelo → Controlador → Vista |
| 04 | [[05-Procedimientos/Dia-04-Jugadores-CRUD]] | Relaciones 1:N, `LEFT JOIN`, desplegable dependiente, `NULL` |
| 05 | [[05-Procedimientos/Dia-05-Partidos]] | Doble JOIN a la misma tabla, reglas de negocio, `datetime-local` |
| 06 | [[05-Procedimientos/Dia-06-Estadisticas]] | Acumuladores, `usort()`, `<=>`, por qué no duplicar datos |
| 07 | [[05-Procedimientos/Dia-07-Doc-Tecnico]] | Documento técnico, MOC, plantillas, documentar limitaciones |
| 08 | [[05-Procedimientos/Dia-08-Verificacion]] | 81 pruebas con `curl`, 3 bugs reales, suite idempotente |

### 06. Código Explicado (Ampliamente Detallado)
Explicaciones línea por línea, por bloques, con propósito, conceptos y ejemplos prácticos. Pensado para entender **no memorizar**.

**Core (Base):**
- [[06-Codigo-Explicado/Core/Database-explicado]] – Conexión PDO singleton, prepared statements, manejo de errores, por qué cada línea.
- [[06-Codigo-Explicado/Core/Router-explicado]] – Parseo de URLs, mapeo controller/action, parámetros, valores por defecto, validación.
- [[06-Codigo-Explicado/Core/Controller-explicado]] – Clase base, método `view()`, paso de datos a vistas, helpers para renderizado.
- [[06-Codigo-Explicado/Core/Model-explicado]] – Clase base reutilizable, CRUD genérico, consultas comunes, prepared statements.

**Models:**
- [[06-Codigo-Explicado/Models/Usuario-explicado]] – Modelo de usuarios, validaciones, hash, login, métodos de búsqueda.
- [[06-Codigo-Explicado/Models/Equipo-explicado]] – CRUD equipos, validaciones, listado.
- [[06-Codigo-Explicado/Models/Jugador-explicado]] – Jugadores con relación a equipo, JOINs, asignación.
- [[06-Codigo-Explicado/Models/Partido-explicado]] – Partidos con 2 FKs (local/visitante), cálculo de ganador, validaciones de negocio.

**Controllers:**
- [[06-Codigo-Explicado/Controllers/AuthController-explicado]] – Registro, login, logout, sesiones, redirecciones, validaciones servidor.
- [[06-Codigo-Explicado/Controllers/EquiposController-explicado]] – CRUD completo, flujo MVC, protección (concepto), mensajes.
- [[06-Codigo-Explicado/Controllers/JugadoresController-explicado]] – Listados con JOIN, creación con select de equipos.
- [[06-Codigo-Explicado/Controllers/PartidosController-explicado]] – Registro con validaciones (mismo equipo), cálculo resultado, listado con nombres.
- [[06-Codigo-Explicado/Controllers/EstadisticasController-explicado]] – Consultas agregadas (COUNT, CASE, JOINs), lógica de estadísticas.

**Vistas:**
- [[06-Codigo-Explicado/Vistas/Layout-y-vistas-explicados]] – Plantilla `main.php`, paso de datos, includes, estructura HTML+PHP, separación lógica/presentación.

### 07. Diario de Aprendizaje
Registro reflexivo por día: qué entendí, qué no entendí (dudas), errores, soluciones, conceptos reforzados y próximos pasos. Fundamental para tu método de estudio.

⚠️ **Solo hay 7 diarios (del 02 al 08).** El día 01 no lleva diario: aún no había código (solo carpeta, `.gitignore` y `git init`), y su contenido está en el procedimiento del día 01.

- [[07-Diario-de-Aprendizaje/Diario-Dia-02]]
- [[07-Diario-de-Aprendizaje/Diario-Dia-03]]
- [[07-Diario-de-Aprendizaje/Diario-Dia-04]]
- [[07-Diario-de-Aprendizaje/Diario-Dia-05]]
- [[07-Diario-de-Aprendizaje/Diario-Dia-06]]
- [[07-Diario-de-Aprendizaje/Diario-Dia-07]]
- [[07-Diario-de-Aprendizaje/Diario-Dia-08]]

### 08. Errores y Soluciones
- [[08-Errores-y-Soluciones/Registro-de-errores]] – Bitácora de problemas + causa + solución aplicada + lección aprendida.

### 09. Glosario
- [[09-Glosario/Terminos-clave]] – Definiciones amplias y sencillas de términos: MVC, PDO, CRUD, FK, PK, prepared statement, front controller, routing, commit atómico, feature branch, PR, etc.

### 10. Recursos
- [[10-Recursos/Enlaces-de-estudio]] – Enlaces organizados por tema para reforzar conceptos si necesitas profundizar aún más.

## 4. Plantillas
- [[_Templates/Plantilla-Dia-Procedimiento]] – Usada para cada día (estructura uniforme, detallada).
- [[_Templates/Plantilla-Codigo-Explicado]] – Usada para cada archivo (explicación amplia y sistemática).

## 5. Principio de Aprendizaje

> *"Aprender a programar no es solo escribir código, sino leer código ajeno y entender cómo funciona".*

Este Vault sigue ese principio: **explica el qué, el cómo y, sobre todo, el porqué**. Con ese nivel de profundidad evitarás tener que buscar información complementaria constantemente y podrás centrarte en entender, asimilar y replicar con confianza.
