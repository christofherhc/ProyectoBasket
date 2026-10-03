# Datos de Prueba (seeds.sql)

## 1. Objetivo

Explicación de `database/seeds.sql`. Datos para probar aplicación rápidamente y entender relaciones.

## 2. Usuarios de Prueba

```sql
INSERT INTO usuarios (nombre, email, password) VALUES
('Usuario Demo', 'demo@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Administrador', 'admin@liga.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
```

**Credenciales:**
- Email: `demo@liga.com` / Password: `password`
- Email: `admin@liga.com` / Password: `password`

**Hash:** Usado hash estándar bcrypt de ejemplo (`laravel/hashing` común). Generado con `password_hash('password', PASSWORD_DEFAULT)`. Válido para pruebas locales.

## 3. Equipos

4 equipos clásicos para ejemplos claros:
1. Los Lakers (LA, 1947)
2. Boston Celtics (Boston, 1946)
3. Chicago Bulls (Chicago, 1966)
4. Golden State Warriors (SF, 1946)

## 4. Jugadores (12 total)

Distribuidos 3 por equipo. Datos coherentes para mostrar JOINs y listados.

## 5. Partidos (4)

Muestran resultados (local vs visitante, marcador). Útiles para módulo Estadísticas (calcular W/L/D).

## 6. Uso

Importar después de `schema.sql`:
```bash
mysql -u root -p liga_basquet < database/seeds.sql
```

O phpMyAdmin → Importar seeds.sql.

## 7. Notas

Solo para desarrollo/pruebas. No usar en producción. Credenciales demo explícitas para facilitar aprendizaje.
