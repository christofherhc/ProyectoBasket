# schema.sql Explicado Línea por Línea

## 1. Objetivo

Explicación amplia y detallada de cada línea de `database/schema.sql`. Entender **qué, cómo y porqué** de cada definición.

## 2. Cabecera

```sql
-- Esquema de Base de Datos: Liga de Básquetbol
-- Motor: InnoDB | Charset: utf8mb4
-- Fecha: 2026-10-03
```

Comentarios SQL claros. Fecha para trazabilidad.

## 3. Tabla `usuarios`

```sql
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del usuario',
  email VARCHAR(150) NOT NULL UNIQUE COMMENT 'Email único para login',
  password VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada con password_hash()',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Campo | Explicación |
|---|---|
| `id INT UNSIGNED` | Entero sin signo. IDs >= 1. Más eficiente. |
| `AUTO_INCREMENT` | Generado automáticamente. |
| `PRIMARY KEY` | Identificador único. Indexado. |
| `nombre VARCHAR(100) NOT NULL` | 100 caracteres suficiente para nombres. Obligatorio. |
| `email VARCHAR(150) NOT NULL UNIQUE` | UNIQUE evita duplicados. Índice implícito. |
| `password VARCHAR(255)` | bcrypt ~60 chars pero usar 255 (futuro-proof). |
| `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP` | Registra cuándo se creó. Automático. |
| `ENGINE=InnoDB` | Soporta FK, transacciones. Recomendado. |
| `utf8mb4` | Unicode completo (emojis/acentos). Mejor que utf8. |
| `COLLATE=utf8mb4_unicode_ci` | Comparación insensible mayúsculas/minúsculas. |

## 4. Tabla `equipos`

```sql
CREATE TABLE IF NOT EXISTS equipos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  ciudad VARCHAR(100) NOT NULL,
  fundacion YEAR NULL,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`YEAR` tipo específico para años. NULL permitido (opcional).

## 5. Tabla `jugadores`

```sql
CREATE TABLE IF NOT EXISTS jugadores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  numero INT UNSIGNED NULL,
  posicion VARCHAR(50) NULL,
  equipo_id INT UNSIGNED NOT NULL,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_jugador_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**FK:** `equipo_id` referencia `equipos.id`. `ON DELETE RESTRICT` impide borrar equipo con jugadores. `ON UPDATE CASCADE` mantiene integridad si cambia ID.

## 6. Tabla `partidos`

```sql
CREATE TABLE IF NOT EXISTS partidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATETIME NOT NULL,
  equipo_local_id INT UNSIGNED NOT NULL,
  equipo_visitante_id INT UNSIGNED NOT NULL,
  puntos_local INT UNSIGNED NOT NULL DEFAULT 0,
  puntos_visitante INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_partido_visitante FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Dos FKs al mismo padre (`equipos`). Necesario para distinguir local/visitante. Validación "distintos" se hace en aplicación.

## 7. Índices

```sql
CREATE INDEX idx_jugador_equipo ON jugadores(equipo_id);
CREATE INDEX idx_partido_local ON partidos(equipo_local_id);
CREATE INDEX idx_partido_visitante ON partidos(equipo_visitante_id);
CREATE INDEX idx_partido_fecha ON partidos(fecha);
```

**¿Por qué índices?**
- Aceleran JOINs y búsquedas frecuentes
- `equipo_id` usado en JOINs/listados
- Búsquedas por fecha
- Mínimos pero útiles para aprendizaje

FK ya crea índice implícito en MySQL/InnoDB, pero explícitos clarifican intención.

## 8. Recomendaciones al Importar

1. Crear BD: `CREATE DATABASE liga_basquet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Importar `schema.sql` primero
3. Importar `seeds.sql` opcional
4. Verificar tablas: `SHOW TABLES;` / `DESCRIBE tabla;`

## 9. Notas

- `IF NOT EXISTS` evita errores si se importa varias veces
- `COMMENT` documenta campos (buena práctica)
- Diseño cumple 3ra forma normal (3NF) básica
- Sin datos sensibles en schema (password solo hash en seeds/demo)
