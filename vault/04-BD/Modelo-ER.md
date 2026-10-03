# Modelo Entidad-Relación (ER)

## 1. Entidades

| Entidad | Descripción |
|---|---|
| **Usuario** | Representa usuarios del sistema (para autenticación) |
| **Equipo** | Representa equipos participantes en la liga |
| **Jugador** | Representa jugadores que pertenecen a un equipo |
| **Partido** | Representa encuentros entre dos equipos |

## 2. Atributos

### usuarios
- id (PK, INT UNSIGNED, AUTO_INCREMENT)
- nombre (VARCHAR(100), NOT NULL)
- email (VARCHAR(150), NOT NULL, UNIQUE)
- password (VARCHAR(255), NOT NULL)
- creado_en (DATETIME, DEFAULT CURRENT_TIMESTAMP)

### equipos
- id (PK, INT UNSIGNED, AUTO_INCREMENT)
- nombre (VARCHAR(100), NOT NULL)
- ciudad (VARCHAR(100), NOT NULL)
- fundacion (YEAR, NULL)
- creado_en (DATETIME, DEFAULT CURRENT_TIMESTAMP)

### jugadores
- id (PK, INT UNSIGNED, AUTO_INCREMENT)
- nombre (VARCHAR(100), NOT NULL)
- numero (INT UNSIGNED, NULL)
- posicion (VARCHAR(50), NULL)
- equipo_id (FK, INT UNSIGNED, NOT NULL)
- creado_en (DATETIME, DEFAULT CURRENT_TIMESTAMP)

### partidos
- id (PK, INT UNSIGNED, AUTO_INCREMENT)
- fecha (DATETIME, NOT NULL)
- equipo_local_id (FK, INT UNSIGNED, NOT NULL)
- equipo_visitante_id (FK, INT UNSIGNED, NOT NULL)
- puntos_local (INT UNSIGNED, NOT NULL, DEFAULT 0)
- puntos_visitante (INT UNSIGNED, NOT NULL, DEFAULT 0)
- creado_en (DATETIME, DEFAULT CURRENT_TIMESTAMP)

## 3. Relaciones (Cardinalidad)

| Relación | Tipo | Explicación |
|---|---|---|
| **Equipo - Jugador** | 1:N (Uno a Muchos) | Un equipo tiene muchos jugadores. Un jugador pertenece a un equipo. |
| **Equipo - Partido (Local)** | 1:N | Un equipo puede ser local en muchos partidos. Un partido tiene un equipo local. |
| **Equipo - Partido (Visitante)** | 1:N | Un equipo puede ser visitante en muchos partidos. Un partido tiene un equipo visitante. |

**Detalle:** Partido tiene **DOS relaciones** con Equipo (auto-relación desde perspectiva equipo). Esto es normal y correcto.

## 4. Claves

- **PK (Primary Key):** `id` en todas
- **FK (Foreign Key):** `jugadores.equipo_id`, `partidos.equipo_local_id`, `partidos.equipo_visitante_id`
- **UK (Unique):** `usuarios.email`

## 5. Reglas de Integridad

- ON DELETE RESTRICT: No permite eliminar equipo con jugadores/partidos asociados
- ON UPDATE CASCADE: Si cambia ID equipo, actualiza referencias

## 6. Diagrama ER (Texto)

```text
usuarios (1) ––––– (independiente)
          id PK
          nombre
          email UK
          password
          creado_en

equipos (1) ───< jugadores (N)
          id PK        id PK
          nombre       nombre
          ciudad       numero
          fundacion    posicion
          creado_en    equipo_id FK -> equipos.id
                       creado_en

equipos (1) ───< partidos_local (N)  (via equipo_local_id)
equipos (1) ───< partidos_visit (N)  (via equipo_visitante_id)

partidos (N)
          id PK
          fecha
          equipo_local_id FK -> equipos.id
          equipo_visitante_id FK -> equipos.id
          puntos_local
          puntos_visitante
          creado_en
```

## 7. Notas de Diseño

- Partido **no puede** tener local == visitante (validación lógica en Controller/negocio)
- Estadísticas calculadas por consultas (no tabla separada) → cumple "métricas básicas"
- Relación ternaria no necesaria. Diseño normalizado 3NF
