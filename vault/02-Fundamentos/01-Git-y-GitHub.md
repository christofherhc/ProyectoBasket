# 1. Git y GitHub - Explicación Amplia para Principiantes

## 1. ¿Qué es Git y por qué usarlo?

**Git** es un sistema de control de versiones distribuido (DVCS). Su función principal es registrar y controlar todos los cambios que realizas sobre tus archivos a lo largo del tiempo. En lugar de tener múltiples copias como `proyecto_v1`, `proyecto_v2_final`, `proyecto_v2_FINAL_CORREGIDO`, Git guarda un historial cronológico, estructurado y recuperable.

**GitHub** es una plataforma en la nube que aloja repositorios Git, permitiendo compartir código, trabajar de forma organizada y mostrar evidencia del progreso.

### ¿Por qué es obligatorio aquí?

La práctica calificada exige:
- Uso de Git con estrategia de ramas (feature branches)
- Commits frecuentes, atómicos y descriptivos
- Pull Requests para fusionar cambios
- Avance progresivo (no todos los commits en una sola fecha)
- Entrega con `.git` incluido en el `.zip`

## 2. Conceptos Clave (Explicados con detalle)

| Concepto | Explicación sencilla | Ejemplo práctico |
|---|---|---|
| **Repositorio (repo)** | Carpeta que Git vigila. Guarda historial completo. | `ProyectoBasket/` con carpeta `.git` oculta. |
| **Working Directory (Área de trabajo)** | Archivos actuales que editas. | Carpeta donde escribes código. |
| **Staging Area (Área de preparación)** | Archivos seleccionados para incluir en próximo commit. | `git add archivo.php` los pasa a staging. |
| **Commit** | Fotografía (snapshot) de los archivos en staging + mensaje. | "Añade modelo Equipo" guarda ese estado. |
| **Rama (Branch)** | Línea de desarrollo independiente. | `main` estable, `feature/equipos` desarrollo. |
| **Merge (Fusionar)** | Une cambios de una rama a otra. | Fusionar `feature/auth` a `main`. |
| **Pull Request (PR)** | Solicitud de revisión/fusión en GitHub. | Útil para practicar flujo profesional. |
| **Remote (Remoto)** | Repositorio en GitHub. | `origin` apunta a URL de GitHub. |
| **HEAD** | Apunta al commit/rama actual. | Donde estás trabajando ahora. |

## 3. Estados de un Archivo en Git

Un archivo puede estar en 3 estados principales:

1. **Untracked (no rastreado)**: Git no lo sigue (nuevo archivo).
2. **Modified (modificado)**: Cambió desde último commit, no está en staging.
3. **Staged (preparado)**: Marcado para ir en próximo commit (`git add`).
4. **Unmodified (sin cambios)**: Igual al último commit.

Flujo: Modificado → `git add` → Staged → `git commit` → Unmodified.

## 4. Configuración Inicial (Git)

Configurar identidad (importante para historial):

```bash
git config --global user.name "Tu Nombre"
git config --global user.email "tu@email.com"
```

Verificar:
```bash
git config --global --list
```

## 5. Inicializar Repositorio (Día 1)

Cuando creamos proyecto desde cero:

```bash
cd /home/chris/Downloads/ProyectoBasket
git init
```
- Crea carpeta `.git/` oculta (contiene todo el historial). **Nunca borrar**.
- Estado pasa a repositorio Git.

Añadir archivos:
```bash
git add README.md
git add .
```
`git add .` añade todos archivos nuevos/modificados (excepto ignorados por `.gitignore`).

Primer commit:
```bash
git commit -m "chore: inicializa proyecto con estructura base y README"
```

## 6. Ramas por Funcionalidad (Feature Branches)

Estrategia requerida: **una rama por funcionalidad**. Esto mantiene `main` estable y muestra avance progresivo.

Convención de nombres sugerida:
- `feature/auth` – Autenticación
- `feature/equipos` – CRUD equipos
- `feature/jugadores` – CRUD jugadores
- `feature/partidos` – Gestión partidos
- `feature/estadisticas` – Estadísticas
- `feature/docs` – Documentación (Vault/doc)

### Crear y cambiar de rama

```bash
git checkout -b feature/auth
# o (más moderno)
git switch -b feature/auth
```
Crea rama y nos mueve a ella.

### Ver ramas
```bash
git branch        # locales
git branch -a     # locales + remotas
```

### Volver a main
```bash
git switch main
```

### Fusionar (merge) cambios
Desde `main`:
```bash
git switch main
git merge feature/auth
```
Une historial de `feature/auth` en `main`.

## 7. Commits Atómicos y Descriptivos

**Commit atómico** = un commit hace **UNA sola cosa lógica** (pequeño, enfocado). Esto facilita leer historial y revertir si falla.

**Mal ejemplo (grande):**
```text
"añade todo el proyecto"
```
**Buenos ejemplos (atómicos):**
```text
feat: añade clase Database con conexión PDO
feat: crea modelo Usuario con métodos de autenticación
fix: corrige validación de equipo_local != equipo_visitante
docs: añade explicación de Router en vault
chore: actualiza .gitignore
style: añade estilos base para formularios
```

### Convención de tipos (recomendada)

| Tipo | Significado | Cuándo usar |
|---|---|---|
| `feat` | Nueva funcionalidad | Añade módulo/característica |
| `fix` | Corrección de bug | Arregla error |
| `docs` | Documentación | Cambios en README, vault, doc |
| `style` | Estilos/formato | CSS, espacios, formato (sin lógica) |
| `refactor` | Refactorización | Mejora código sin cambiar comportamiento |
| `chore` | Mantenimiento | Config, .gitignore, estructura |
| `test` | Pruebas | Añade/modifica pruebas (no aplica aquí) |

## 8. Comandos Esenciales (Diarios)

| Comando | Explicación |
|---|---|
| `git status` | Muestra estado: modificados, staged, untracked. Usar **siempre antes de commit**. |
| `git add <archivo>` | Añade archivo a staging. |
| `git add .` | Añade todos cambios del directorio actual. |
| `git commit -m "msg"` | Crea commit con mensaje. |
| `git log --oneline -5` | Ver últimos 5 commits (resumido, legible). |
| `git log --oneline --graph -10` | Ver ramas gráficamente. |
| `git diff` | Ver cambios no staged. |
| `git diff --staged` | Ver cambios staged (lo que irá en commit). |
| `git restore <archivo>` | Descarta cambios no staged (cuidado). |
| `git rm <archivo>` | Elimina archivo y lo registra en Git. |
| `git push -u origin <rama>` | Sube rama a GitHub (primer push). |
| `git push` | Sube commits a rama remota. |
| `git pull` | Trae cambios del remoto. |

## 9. .gitignore (Ignorar archivos innecesarios)

`.gitignore` evita subir archivos temporales, configs locales, caché.

Archivo base sugerido (`ProyectoBasket/.gitignore`):

```gitignore
# IDEs y editores
.vscode/
.idea/
*.sublime-project
*.sublime-workspace

# Sistema operativo
.DS_Store
Thumbs.db
desktop.ini

# PHP / logs temporales
*.log
error_log
php_errors.log

# Archivos de entorno (no commitear nunca)
.env
.env.local

# Dependencias (si hubiera composer/vendor)
vendor/

# Caché
tmp/
cache/
*.cache
```

**Importante:** Nunca commitear secretos, contraseñas, `.env`.

## 10. GitHub: Remoto, Push y PRs

### Conectar repo local a GitHub

Crear repo vacío en GitHub (sin README, .gitignore, LICENSE). Copiar URL (HTTPS).

```bash
git remote add origin https://github.com/tuusuario/ProyectoBasket.git
git branch -M main
git push -u origin main
```

- `remote add origin` vincula remoto.
- `branch -M main` renombra a main (si no lo era).
- `push -u origin main` sube y establece upstream.

### Subir rama feature
```bash
git switch feature/auth
git push -u origin feature/auth
```

### Pull Request (Recomendado para práctica)

En GitHub: `Compare & pull request` → Título descriptivo → Descripción breve (qué cambios, por qué) → Crear PR → (opcional merge a main).

Esto demuestra **flujo profesional** y cumple "uso de pull requests".

## 11. Avance Progresivo (Requisito Crítico)

La práctica indica: **No se aceptarán trabajos con todos los commits en una sola fecha o bloque**.

**Cómo cumplirlo:**
- **Commits frecuentes**: 1–3 commits por día mínimo (ideal 2–5 atómicos).
- **Día a día**: Trabajar cada día según roadmap (Semana 1–2). Registrar en `vault/05-Procedimientos/` y `vault/07-Diario-de-Aprendizaje/`.
- **Ramas por funcionalidad**: Muestra desarrollo organizado y progresivo.
- **Historial visible**: `git log --oneline --graph` debe mostrar evolución temporal clara.

## 12. Recomendaciones Prácticas para Aprender

1. **Siempre `git status` antes de commit.** Evitas commitear lo equivocado.
2. **Commits pequeños > grandes.** Más fácil entender y revertir.
3. **Mensajes en presente/imperativo.** "Añade modelo Equipo" vs "Añadido modelo Equipo". Estilo común: "feat: añade modelo Equipo".
4. **Un commit = un cambio lógico.** Atómico.
5. **No commitear archivos innecesarios.** Usar `.gitignore`.
6. **Trabajar en ramas feature.** Nunca desarrollar todo directo en `main` (buenas prácticas).
7. **Push frecuente.** Respaldas trabajo en GitHub.
8. **Leer historial.** `git log --oneline -10` para entender evolución.

## 13. Verificación de Historial Limpio

Antes de entrega (Día 14):
```bash
git log --oneline --all --graph -15
git branch -a
git status  # debe estar limpio (working tree clean)
```
`working tree clean` significa no hay cambios sin commitear. Ideal para empaquetar `.zip` con `.git` intacto.