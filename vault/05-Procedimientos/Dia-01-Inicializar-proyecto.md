# Día 01 - Inicializar Proyecto + Estructura Base

**Fecha sugerida:** Día 1 (Semana 1)  
**Objetivo:** Inicializar repositorio Git, crear estructura de carpetas, crear README, .gitignore y subir primer commit.  
**Temas a comprender:** Git init/add/commit, .gitignore, organización de carpetas MVC, DocumentRoot.  
**Rama Git:** `main` (inicial)

## 1. Contexto

Primer paso: preparar base ordenada. Creamos estructura, inicializamos Git con commits atómicos desde inicio (avance progresivo).

## 2. Pasos a realizar

### Paso 1.1: Verificar ubicación
```bash
cd /home/chris/Downloads/ProyectoBasket
ls -la
```

### Paso 1.2: Inicializar Git
```bash
git init
git branch -M main
```

### Paso 1.3: Crear .gitignore (ya creado, revisar)
Verificar contenido.

### Paso 1.4: Estado Git
```bash
git status
git add .
git commit -m "chore: inicializa estructura base del proyecto Liga de Básquetbol"
```

### Paso 1.5: Crear estructura (ya creada) - Verificar
```bash
tree -L 3 2>/dev/null || find . -type d | sort | head -30
```

## 3. Archivos creados
README.md, .gitignore, estructura carpetas, database/*, vault/*

## 4. Aprendizajes
Git init, commits atómicos, importancia estructura ordenada.

## 5. Próximos pasos
Día 02: Core MVC (Database, Model, Controller, Router) + public/index.php
