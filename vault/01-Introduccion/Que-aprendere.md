# ¿Qué Aprenderé con Este Proyecto?

## 1. Visión General

Este proyecto está estructurado para aprender desarrollo web desde cero, pero de forma ordenada, práctica y con explicaciones amplias. El objetivo es que adquieras una base sólida que te permita entender código ajeno y generar el tuyo propio con criterio.

## 2. Aprendizajes Técnicos

| Tema | Nivel | Descripción detallada |
|---|---|---|
| **Git y GitHub** | Fundamental | Inicializar repositorio, trabajar con ramas (`main`, `feature/*`), commits atómicos y descriptivos, `add`, `commit`, `push`, `pull`, flujo con Pull Requests, `.gitignore`, mantener historial limpio y progresivo. |
| **Entorno de desarrollo PHP** | Fundamental | Configurar XAMPP/Laragon/WAMP, DocumentRoot apuntando a `public/`, estructura de carpetas, ejecución local. |
| **PHP Estructurado** | Fundamental | Sintaxis, variables, tipos, operadores, condicionales, bucles, arrays, includes/requires, funciones, scope, validación básica, legibilidad. |
| **HTML5 + CSS3 (puro)** | Fundamental | Estructura semántica básica, formularios, tablas, clases CSS, organización de estilos, maquetado simple. Se prioriza CSS propio para entender sin abstracciones. |
| **MySQL/MariaDB** | Fundamental | Diseño relacional, tablas, tipos de datos, PRIMARY KEY, FOREIGN KEY, UNIQUE, NOT NULL, relaciones 1:N, integridad referencial, consultas básicas (SELECT, INSERT, UPDATE, DELETE). |
| **PDO (PHP Data Objects)** | Crítico | Conexión con PDO, DSN, prepared statements, vinculación de parámetros (`bindValue/bindParam`), `fetch`, `fetchAll`, `rowCount`, manejo de errores con `PDO::ERRMODE_EXCEPTION`, prevención de SQL Injection. |
| **Arquitectura MVC Ligero** | Crítico | ¿Qué es MVC?, responsabilidades (Model–View–Controller), por qué separar lógica, datos y presentación. Diferencia con código spaghetti. Ventajas para leer código ajeno. |
| **Front Controller + Routing** | Intermedio | Patrón Front Controller (`public/index.php`), capturar `$_GET['controller']` y `$_GET['action']`, valores por defecto, validación, inclusión dinámica controladores. URLs limpias. |
| **Sesiones PHP ($_SESSION)** | Intermedio | Iniciar/cerrar sesión, guardar datos usuario, proteger rutas, login/logout, mensajes flash (éxito/error). |
| **Autenticación** | Intermedio | Registro con validación, hash de contraseñas (`password_hash`), verificación (`password_verify`), evitar exponer credenciales. |
| **CRUD** | Crítico | Crear, Leer, Actualizar, Eliminar registros. Validaciones servidor, redirecciones, mensajes, consistencia. |
| **Relaciones entre entidades** | Intermedio | Jugador pertenece a Equipo (FK `equipo_id`). Partido entre dos equipos (dos FKs `equipo_local_id`, `equipo_visitante_id`). JOINs para mostrar nombres en lugar de IDs. |
| **Consultas Agregadas + Estadísticas** | Intermedio | `COUNT()`, `SUM()`, `GROUP BY`, `CASE WHEN` para calcular victorias/empates/derrotas, partidos jugados. |
| **Validaciones servidor-side** | Intermedio | Validar campos obligatorios, evitar datos inválidos, lógica de negocio (no permitir partido entre mismo equipo), coherencia. |
| **Seguridad básica aplicada** | Intermedio | Uso de prepared statements, escape consciente en vistas (cuando corresponda), validación, sesiones seguras por diseño. |
| **Documentación técnica** | Intermedio | Redactar documento claro (arquitectura, componentes, funcionalidades, fragmentos, dificultades, decisiones) con lenguaje formal dirigido a desarrolladores. |

## 3. Aprendizajes sobre Proceso y Mentalidad

| Habilidad | Descripción |
|---|---|
| **Pensamiento estructurado** | Descomponer funcionalidades en pasos pequeños, pensar en flujo antes de escribir código. |
| **Leer código ajeno con intención** | Analizar archivos, seguir dependencias, entender "porqué" no solo "qué". Esto es el núcleo buscado. |
| **Explicación técnica** | Articular conceptos con tus propias palabras en diario, glosario y código-explicado. Consolida aprendizaje. |
| **Resolución de problemas** | Identificar errores, leer mensajes, probar hipótesis, documentar solución. |
| **Atención al detalle** | Nombres claros, consistencia, commits atómicos, evitar ambigüedad. |
| **Aprendizaje progresivo** | Construir sobre lo anterior. No saltar temas. Cada día suma comprensión. |
| **Documentar para aprender** | El Vault te obliga a explicar. Explicar es la mejor forma de entender realmente. |

## 4. Ruta de Dominio por Fases

| Fase | Enfoque | Meta |
|---|---|---|
| **Fase 1: Fundamentos** | Git, PHP básico, SQL, entorno | Sentir confianza con herramientas base. |
| **Fase 2: Arquitectura** | MVC, Routing, PDO | Entender cómo se organiza un proyecto legible. |
| **Fase 3: Funcionalidades** | Auth, CRUD, Relaciones | Ver aplicación cobrar forma funcionalmente. |
| **Fase 4: Lógica + Stats** | Validaciones, estadísticas | Pensamiento de negocio aplicado. |
| **Fase 5: Documentación + Consolidación** | Vault, doc técnico, diario | Consolidar, explicar y poder replicar. |

## 5. Nivel de Profundidad Esperado

El material está diseñado para ser **amplio y detallado por intención**. No se asumen conocimientos previos. Se explicará:
- **Qué** se está haciendo (acción)
- **Cómo** se hace (sintaxis, lógica paso a paso)
- **Por qué** se hace así (justificación: claridad, seguridad, mantenibilidad, aprendizaje)
- **Conceptos subyacentes** cuando aparezcan (PDO, prepared statements, sesiones, etc.) con ejemplos sencillos
- **Relaciones entre archivos** para seguir el flujo completo

## 6. Criterio de Éxito

Considerarás logrado el objetivo cuando puedas:
1. **Explicar con tus palabras** qué hace cada clase, controlador, modelo o vista sin mirar la explicación.
2. **Reconstruir mentalmente** el flujo: petición → router → controlador → modelo → vista.
3. **Crear un módulo similar** (CRUD + relación) por tu cuenta sin copiar línea por línea, entendiendo la lógica.
4. **Escribir commits claros** y entender el porqué de ramas por funcionalidad.
5. **Leer cualquier archivo de este proyecto** y entenderlo con autonomía razonable.