# Objetivo del Proyecto

## 1. Objetivo General

Desarrollar una aplicación web sencilla y entendible para la **gestión de una liga de básquetbol**, aplicando buenas prácticas de desarrollo de software y control de versiones con Git. El enfoque principal no es solo cumplir con la práctica, sino **aprender paso a paso, leyendo y entendiendo el código generado** para poder replicarlo por cuenta propia.

## 2. Objetivos Específicos

Al finalizar este proyecto, serás capaz de:

1. **Comprender y aplicar MVC ligero.** Entender la separación entre modelos, vistas, controladores y por qué esta organización facilita la lectura y mantenibilidad del código.
2. **Desarrollar con PHP vanilla.** Trabajar con PHP estructurado, sin frameworks, para comprender los conceptos fundamentales sin capas de abstracción ocultas.
3. **Gestionar base de datos con PDO.** Crear conexiones seguras, utilizar prepared statements, trabajar con relaciones 1:N y prevenir inyecciones SQL.
4. **Implementar autenticación básica.** Desarrollar registro, inicio de sesión y cierre de sesión usando sesiones PHP, entendiendo el flujo completo.
5. **Realizar CRUD completo.** Aplicar operaciones Crear, Leer, Actualizar y Eliminar en Equipos, Jugadores y Partidos, entendiendo cada paso.
6. **Trabajar con relaciones entre tablas.** Gestionar la asignación de jugadores a equipos y partidos entre equipos (con dos claves foráneas al mismo modelo).
7. **Generar estadísticas básicas.** Realizar consultas agregadas (JOIN, COUNT, CASE) para obtener métricas de la liga.
8. **Aplicar control de versiones correctamente.** Utilizar ramas por funcionalidad (`feature/*`), commits atómicos, descriptivos y con historial progresivo a lo largo del tiempo.
9. **Documentar técnicamente.** Redactar el documento obligatorio `/doc/practica_liga.md` explicando arquitectura, componentes, funcionalidades, fragmentos de código, dificultades y decisiones de diseño.
10. **Aprender leyendo código ajeno.** Utilizar el Vault para estudiar, explicar y comprender el "qué", "cómo" y "porqué" de cada línea de código.

## 3. Alcance del Proyecto (Requerimientos)

Según la especificación de la práctica calificada, la aplicación debe contar con los siguientes módulos funcionales:

| Módulo | Funcionalidades requeridas | Explicación conceptual |
|---|---|---|
| **Autenticación** | Registro e inicio de sesión de usuarios | Permite identificar usuarios y mantener sesiones activas. |
| **Equipos** | Crear, editar, listar y eliminar | Gestión completa de equipos de la liga. |
| **Jugadores** | Gestión y asignación de jugadores por equipo | Cada jugador pertenece a un equipo (relación 1:N). |
| **Partidos** | Registro y control de encuentros deportivos | Registrar marcador, fecha, equipos local/visitante. |
| **Estadísticas** | Visualización de resultados y métricas básicas | Mostrar partidos jugados, victorias/derrotas/empates por equipo. |

## 4. Requerimientos Técnicos

| Componente | Especificación | Justificación para aprendizaje |
|---|---|---|
| **Lenguaje** | PHP | Ampliamente usado, fácil de entender para principiantes y permite ver conceptos sin ocultarlos. |
| **Base de Datos** | MySQL o MariaDB | Relacional, estándar, ideal para aprender PK, FK y JOINs. |
| **Control de versiones** | Git | Industria obligatoria. Practicar buen flujo es clave. |
| **Repositorio remoto** | GitHub | Trabajo colaborativo y exposición del historial. |
| **Documentación** | `/doc/practica_liga.md` (500–800 palabras) | Obligatoriao para reflexionar y explicar lo desarrollado. |

## 5. Enfoque Pedagógico

Este proyecto está pensado con un enfoque de **aprendizaje progresivo y explicativo**:

- **Sencillez sobre complejidad.** Código legible, mínimo necesario, nombres descriptivos. Ideal para estudiar y entender.
- **Explicación amplia.** Cada concepto se explica con detalle suficiente para no tener que buscar información complementaria en Internet.
- **Aprendizaje por reconstrucción.** Seguir procedimientos día a día, crear, leer y repasar explicaciones línea por línea.
- **Leer para aprender.** Cumple el principio: aprender programación leyendo código ajeno, entendiendo su lógica y replicándola.
- **Práctica deliberada.** Pequeños pasos diarios, commits atómicos y consolidación mediante diario de aprendizaje.

## 6. Resultado Esperado

Al término del proyecto tendrás:
1. **Aplicación funcional completa** que cumple todos los módulos requeridos.
2. **Historial Git limpio y progresivo** que evidencie avance continuo (requisito indispensable).
3. **Documento técnico** redactado correctamente en `/doc/practica_liga.md`.
4. **Vault completo** con fundamentos, procedimientos, código explicado, diario y glosario (material de estudio reutilizable para futuros proyectos).
5. **Comprensión sólida** de los temas trabajados, con capacidad para explicar y replicar por cuenta propia.