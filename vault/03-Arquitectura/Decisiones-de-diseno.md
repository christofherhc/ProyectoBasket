# Decisiones de Diseño - Justificaciones Técnicas

## 1. Propósito

Este documento justifica las decisiones técnicas tomadas para este proyecto. Está pensado para:
- **Entender el porqué** de cada elección (no "porque sí")
- **Servir como base** para el punto 6 del documento técnico obligatorio (`doc/practica_liga.md`)
- **Desarrollar criterio** al tomar decisiones en futuros proyectos

## 2. Principios Rectores

Al diseñar este proyecto priorizamos:

1. **Sencillez > Complejidad** → Código legible, mínimo necesario
2. **Claridad pedagógica** → Debe poder entenderse sin experiencia previa
3. **Aprendizaje por lectura** → Facilitar "leer código ajeno y entender"
4. **Sin magia** → Todo visible, nada oculto tras abstracciones excesivas
5. **Cumplir requisitos** → Ajustado estrictamente a lo pedido (PHP+MySQL+Git)
6. **Progresivo** → Se construye paso a paso según roadmap

## 3. Lenguaje: PHP Vanilla (Sin Framework)

**Decisión:** Usar PHP 8.x estructurado + POO ligero, **sin frameworks** (Laravel, Symfony, CodeIgniter).

**Justificación:**
- **Sin capas ocultas** → Ves todo: routing, conexión BD, renderizado vistas
- **Fundamentos sólidos** → Entiendes HTTP, sesiones, PDO desde cero
- **Menor curva de aprendizaje** → Enfocado a conceptos, no a CLI/framework
- **Ideal para leer código ajeno** → Sin "facades", sin "Eloquent mágico". SQL explícito
- **Cumple requisito** → PHP puro es suficiente
- **Sin dependencias externas** → Solo PHP + MySQL

**Contra-argumentado:** Frameworks ahorran tiempo, pero **ocultan conceptos**. Para **aprender fundamentos**, vanilla PHP es superior aquí.

## 4. Arquitectura: MVC Ligero

**Decisión:** Implementar **MVC muy ligero** (propio, minimalista).

**Justificación:**
- **Separación de responsabilidades** → Model/View/Controller claros
- **Organización predecible** → Fácil encontrar código
- **Reutilizable** → Clases base evitan duplicación
- **Pedagógico** → Muestra patrón usado en muchos frameworks
- **Escalable ordenado** → Añadir módulos sin desordenar
- **Fomenta lectura** → Flujo petición→respuesta trazable

**Detalles:**
- `Model.php` base con CRUD genérico
- `Controller.php` base con `view()`
- `Router.php` propio simple

**Alternativa descartada:** Código procedural todo junto (spaghetti) → menos didáctico.

## 5. Enrutamiento: Parámetros GET + Front Controller

**Decisión:** Usar `?controller=...&action=...&id=...` con `public/index.php` como Front Controller.

**Justificación:**
- **Transparente** → URL muestra explícitamente qué se ejecuta
- **Sin mod_rewrite** → Funciona en XAMPP/Laragon sin .htaccess
- **Fácil debuggear** → Clarísimo para principiantes
- **Sin configuración extra** → Menor fricción
- **Didáctico** → Enseña Front Controller claramente
- **Suficiente** → Cumple funcionalidad 100%

**Alternativa descartada:** Rutas amigables (`/equipos/index/1`) con .htaccess
- Requiere mod_rewrite activo
- Añade "magia" de reescritura
- Más abstracción para objetivo aprendizaje

**Conclusión:** GET params **mejor para aprender arquitectura**. Podemos migrar después si se desea, pero innecesario aquí.

## 6. Base de Datos: MySQL/MariaDB + PDO

**Decisión:** Usar **PDO** con prepared statements, **no mysqli**.

**Justificación PDO:**
- **Prepared statements nativos** → Protección SQL Injection
- **Named parameters (`:nombre`)** → Más legibles que `?`
- **Interfaz uniforme** → Si cambia motor, menor impacto
- **Fetch modes flexibles** → FETCH_ASSOC, FETCH_OBJ...
- **Orientado a objetos** → Coherente con clases
- **Soporta múltiples drivers** → Conceptualmente más sólido
- **Más didáctico** → Patrón claro reutilizable

**Fetch mode elegido:** `FETCH_ASSOC` por defecto
- Array asociativo limpio: `$equipo['nombre']`
- Sin propiedades mágicas
- Muy legible en vistas
- Coherente con código estructurado

**Singleton en Database.php:** Una sola conexión por petición → eficiente y simple.

## 7. Autenticación: Sesiones PHP + password_hash

**Decisión:** Autenticación propia con `$_SESSION`, `password_hash()`, `password_verify()`.

**Justificación:**
- **Estándar PHP** → Sin librerías externas
- **Seguro suficiente** → password_hash usa bcrypt (recomendado)
- **Entendible** → Ves flujo completo login/registro
- **Mínimo** → Cumple "registro e inicio de sesión"
- **session_regenerate_id** tras login → previene session fixation (buenísima práctica)

**No usar:** JWT/cookies tokens → innecesario, añade complejidad. Sesiones PHP son perfectas para app web monolítica.

## 8. Vistas: Layout Base + HTML + CSS Puro

**Decisión:** Un solo layout `views/layouts/main.php`. **CSS puro**, sin Bootstrap/Tailwind.

**Justificación:**
- **DRY** → Evita repetir header/nav/footer
- **Fácil modificar** → Un archivo
- **CSS puro = aprendizaje real** → Entiendes selectores, propiedades
- **Sin dependencias CDN** → Funciona offline
- **Más ligero** → Mínimo
- **Legible** → Código claro para estudiar
- **Enfoque contenido** → Priorizamos lógica PHP sobre diseño

**Vistas minimalistas:** Solo presentación + bucles/condicionales simples.

## 9. Modelos: Herencia + CRUD Base

**Decisión:** `Model.php` con métodos comunes (`obtenerTodos`, `obtenerPorId`, `crear`, `actualizar`, `eliminar`) heredados.

**Justificación:**
- **DRY** → Elimina repetición
- **Consistencia** → Todos modelos usan mismo patrón
- **Didáctico** → Muestra herencia POO
- **Flexible** → Cada modelo puede añadir métodos propios (`buscarPorEmail`, consultas con JOIN)
- **Claro** → Separación genérico vs específico

**JOINs en modelos específicos:** Para mostrar nombres (jugador con equipo, partido con equipos). Lógica correcta en capa modelo.

## 10. Controladores: Delgados, Coordinadores

**Decisión:** Controladores **thin**. Lógica de BD va a modelos. Lógica presentación va a vistas.

**Justificación:**
- **Separación capas** → Respeta MVC
- **Testeable mentalmente** → Flujo claro
- **Reutilización modelos** → Varios controladores podrían usar mismo modelo
- **Legible** → Acción = pocos pasos

**Método `requiereLogin()`** en `Controller.php` base (método protegido). Simple, reutilizable.

## 11. Validaciones: Servidor-Side Obligatorias

**Decisión:** **Toda validación crítica en PHP** (servidor). HTML5 `required/minlength` solo complementario.

**Justificación:**
- **Segura** → Puede saltarse HTML5 fácilmente
- **Fiable** → Siempre se ejecuta
- **Correcta** → Datos llegan limpios a BD
- **Buena práctica** → Nunca confiar en cliente

**Validaciones aplicadas:**
- Login: campos obligatorios + password_verify
- Registro: obligatorios + email válido + contraseñas coinciden + min 6 + email único
- Partidos: equipos distintos, obligatorios
- Trim + strtolower(email) → normalización

⚠️ **Un `(int)` no es una validación.** `(int)'1abc'` vale `1`, así que un id con basura al final se aceptaría en silencio y se abriría el registro equivocado. Para los ids se usa el helper `enteroPositivo()`, que exige que el texto sea **solo dígitos** o no es un id válido:

```php
$id = enteroPositivo($params['id'] ?? '') ?? 0;
```

⚠️ **Un campo que no llega no es un valor por defecto.** Si el marcador no viene en el POST, `post('puntos_local')` devuelve `''` y se avisa de que es obligatorio. Antes el valor por defecto era `'0'` y se guardaba un 0-0 sin decir nada.

## 12. Git: Feature Branches + Commits Atómicos

**Decisión:** Flujo con ramas por funcionalidad: `feature/auth`, `feature/equipos`, etc. + commits atómicos descriptivos.

**Justificación:**
- **Requisito explícito** → Pide feature branches, PRs, avance progresivo
- **Historial claro** → Se ve evolución lógica
- **Revertible** → Commit atómico fácil revertir
- **Profesional** → Flujo usado industria
- **Didáctico** → Aprende Git correctamente
- **Evidencia progreso** → Evita "todo un bloque"

**Convención:** `feat:`, `fix:`, `docs:`, `style:`, `refactor:`, `chore:` (tipo convencional claro)

## 13. Vault Obsidian: Explicativo y Amplio

**Decisión:** Crear `vault/` con explicaciones **muy detalladas** (línea por línea) + procedimientos + diario.

**Justificación:**
- **Soporta objetivo** → "aprender leyendo código ajeno + entender"
- **Evita buscar externo** → Explicado todo lo necesario
- **Reforzamiento** → Explicar con palabras consolida
- **Replicable futuro** → Reutilizable
- **Obsidian-friendly** → Markdown + enlaces internos
- **Estructurado** → MOC guía ruta secuencial
- **Diario reflexivo** → Autoevaluación aprendizaje

**Niveles de detalle:** Ampliados a propósito (no minimalistas).

## 14. Documentación Técnica (`doc/practica_liga.md`)

**Decisión:** Redactar 500–800 palabras con secciones obligatorias.

**Secciones:** Arquitectura, componentes clave (clases/controladores/modelos), funcionalidades, fragmentos relevantes, dificultades+soluciones, decisiones diseño.

**Justificación:** Obligatoriao. Además obliga a **reflexionar** sobre lo hecho → refuerza comprensión.

## 15. Seguridad: Medidas Aplicadas (Adecuadas al Nivel)

**Aplicado:**
- PDO prepared statements → SQLi
- `password_hash`/`password_verify` → contraseñas
- `htmlspecialchars()` en vistas → XSS
- Validación servidor → integridad
- Protección rutas `requiereLogin()` → authz básica
- `session_regenerate_id(true)` tras login → session fixation
- `__DIR__` para rutas → evita path traversal confuso
- `trim()` inputs → limpieza
- Mensajes genéricos login → info disclosure mínimo

**NO aplicado (deliberado, no requerido):**
- CSRF tokens → práctica no lo pide explícitamente. Suficiente nivel actual
- Rate limiting → sobreingeniería
- Roles (admin/user) → no requerido (solo auth básica)
- ReCAPTCHA → no necesario
- HTTPS forzado → desarrollo local

**Criterio:** Aplicar **seguridad mínima pero correcta** acorde a alcance aprendizaje.

## 16. Diseño Minimalista UI

**Decisión:** Interfaz HTML muy simple + CSS propio mínimo.

**Justificación:**
- **Enfoque código** → Aprendemos PHP/MVC, no diseño
- **Rápido** → Menos tiempo UI
- **Legible** → Claro para estudiar
- **Sin frameworks CSS** → Entiende fundamentos CSS
- **Funcional** → Cumple mostrar datos/CRUD

## 17. ¿Por qué NO usar Composer/Autoload?

**Decisión:** `require_once` manual en `index.php` + controladores/modelos.

**Justificación:**
- **Transparente** → Ves qué se carga y cuándo
- **Sin tooling extra** → Solo PHP
- **Menor complejidad** → Aprendes includes básicos
- **Didáctico** → Evita abstracción autoload
- **Suficiente** → Pocos archivos

**PSR-4 autoload** sería mejor a futuro, pero para **este proyecto educativo** `require_once` explícito es **más claro para aprender**.

## 18. Decisiones sobre Estructura BD

**Tablas:** `usuarios`, `equipos`, `jugadores`, `partidos`

**Relaciones:**
- `jugadores.equipo_id` FK → `equipos(id)` ON DELETE RESTRICT (evita borrar equipo con jugadores)
- `partidos.equipo_local_id` FK → `equipos(id)` RESTRICT
- `partidos.equipo_visitante_id` FK → `equipos(id)` RESTRICT

**RESTRICT vs CASCADE:**
- **RESTRICT** → Más seguro, previene pérdida accidental
- **CASCADE** → Eliminaría dependencias. Para integridad didáctica, RESTRICT mejor.

**ENGINE=InnoDB** → Soporta FK, transacciones
**utf8mb4** → Soporta acentos/ñ Unicode completo

## 19. Balance entre "Perfecto" y "Aprendible"

**Filosofía:** **"Suficientemente bueno para aprender, suficientemente claro para leer"**

Priorizamos:
- **Claridad > Elegancia**
- **Legibilidad > Optimización**
- **Explicativo > Conciso excesivo**
- **Didáctico > "Demasiado avanzado"**

## 20. Resumen de Decisiones Clave

| Decisión | Razón Principal |
|---|---|
| PHP Vanilla | Aprender fundamentos sin magia |
| MVC Ligero | Separación clara + pedagógico |
| GET Routing | Transparente, sin .htaccess |
| PDO | Seguro + legible + estándar |
| Sesiones Auth | Simple, correcto, entendible |
| CSS Puro | Aprender CSS real |
| Feature Branches | Cumple requisito + profesional |
| Vault Amplio | Soporta "leer para aprender" |
| require_once manual | Transparente para principiantes |

## 21. Conclusión

Todas las decisiones buscan **maximizar comprensión**. No buscamos "producción enterprise", buscamos **proyecto didáctico eficaz**: que puedas leer cada línea, entender flujo completo, y replicarlo con confianza.

Estas justificaciones también servirán directamente para redactar la sección **"Decisiones de diseño"** del documento técnico.