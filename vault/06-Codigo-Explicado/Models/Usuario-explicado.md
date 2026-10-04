# Usuario (explicado)

**Archivo real:** `app/models/Usuario.php`
**Rol:** acceso a la tabla `usuarios`. Es el modelo con menos código, pero el más importante para la seguridad.

## 1. El código completo

```php
class Usuario extends Model {
    protected string $tabla = 'usuarios';

    public function buscarPorEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->tabla} WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado === false ? null : $resultado;
    }

    public function existeEmail(string $email): bool {
        $usuario = $this->buscarPorEmail($email);
        return $usuario !== null;
    }

    public function crear(string $nombre, string $email, string $passwordHash): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, email, password)
                VALUES (:nombre, :email, :password)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
        return $stmt->execute();
    }
}
```

**Solo 3 métodos propios.** Los otros (`obtenerTodos`, `obtenerPorId`, `eliminar`, `obtenerUltimoId`) los hereda de `Model` y no se usan.

## 2. `protected string $tabla = 'usuarios'` — lo único que heredan los modelos

Es la línea que hace que `{$this->tabla}` valga `usuarios` en todas las consultas heredadas. Está explicada en detalle en `../Core/Model-explicado.md`.

## 3. `buscarPorEmail()`: la consulta de autenticación

### Por qué este método existe en vez de usar el `obtenerPorId()` heredado

Porque el login busca por **email**, no por id. La clave de búsqueda es distinta en cada modelo:

| Modelo | Busca por | Hereda `obtenerPorId()` |
|---|---|---|
| `Equipo` | id | Sí, se usa |
| `Jugador` | id | Sí, se usa |
| `Partido` | id | Sí, se usa |
| `Usuario` | **email** | ❌ No se usa |

Por eso `Usuario` **no sobrescribe** `obtenerPorId()` ni `obtenerTodos()`: ninguna de las dos operaciones tiene sentido para el login. Un modelo bien diseñado solo tiene los métodos que se usan.

### Las cuatro líneas del patrón PDO

```php
$sql = "SELECT * FROM {$this->tabla} WHERE email = :email LIMIT 1";
$stmt = $this->db->prepare($sql);
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->execute();
```

**a) `WHERE email = :email`** → el `:email` es un **hueco con nombre**. El nombre no es decorativo: `bindValue(':email', ...)` indica qué hueco se rellena. Puedes repetir el mismo hueco en la consulta:

```sql
--Usar el mismo hueco varias veces es una ventaja de los named:
SELECT * FROM usuarios WHERE email = :email OR nombre = :email
```

⚠️ Ojo: con placeholders **posicionales** (`?`), no se puede repetir. Por eso los modelos usan `?` en los métodos simples del `Model` y `:nombre` en los métodos propios. Ambos estilos conviven.

**b) `LIMIT 1`** → solo trae la primera coincidencia. Como `email` es `UNIQUE`, siempre habrá 0 o 1. El `LIMIT` es una garantía de rendimiento y una red de seguridad si alguien quita el `UNIQUE` de la tabla.

**c) `bindValue(':email', $email, PDO::PARAM_STR)`** → declara que es una **cadena**. No es un detalle menor: sin el tipo, PDO adivina, y si adivina mal, MySQL no puede usar el índice `email` y la consulta se vuelve lenta.

⚠️ **Y un riesgo real de seguridad:** si el valor se enviara sin el tipo y PDO lo tratara como número, un email como `"1"` podría compararse con `id`. El tipo explícito elimina esa ambigüedad.

**d) `execute()` sin parámetros** → porque ya se rellenó todo con `bindValue()`.

### `$resultado === false ? null : $resultado`

El `fetch()` de PDO devuelve **`false`** cuando no hay filas, no `null`. Como el método declara `?array`, hay que traducirlo. Está explicado en `../Core/Model-explicado.md`.

⚠️ **El error típico del principiante:**
```php
// ❌ Incorrecto: fetch() devuelve false, nunca null
$stmt->execute();
return $stmt->fetch() === null ? null : $stmt->fetch();
```
Así que el `if ($usuario === null)` del controlador **nunca se cumpliría** y el login intentaría verificar una contraseña contra un array `false`.

## 4. `existeEmail()`: una función que "no hace casi nada"

```php
public function existeEmail(string $email): bool {
    $usuario = $this->buscarPorEmail($email);
    return $usuario !== null;
}
```

⚠️ **Ineficiente a propósito.** Trae la fila **entera** (incluyendo el hash de la contraseña) solo para devolver un `true`/`false`. Un `SELECT 1` sería más eficiente:

```php
public function existeEmail(string $email): bool {
    $sql = "SELECT 1 FROM {$this->tabla} WHERE email = :email LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn() !== false;
}
```

⚠️ **⚠️ Y hay una razón de seguridad para dejarlo así.** Esta función está en el camino del registro: si un atacante pudiera distinguir "el email existe" de "otra cosa" por el **tiempo** de respuesta, tendría una forma de enumerar usuarios registrados (la base ya lo impide con el `UNIQUE`, pero no siempre). Traer la fila entera cuesta un poco más y no deja esa fuga.

SeDocumenta aquí como ejemplo de que "ineficiente" y "más seguro" a veces van de la mano.

## 5. `crear()`: el parámetro que se llama `passwordHash`

```php
public function crear(string $nombre, string $email, string $passwordHash): bool {
```

**El nombre del parámetro es la documentación más importante de este método.** Se llama `passwordHash`, no `password`. Con solo mirar la firma sabes que:

- Aquí **no** se hashea nada.
- Quien llama **ya** ha applied `password_hash()`.

⚠️ **Este es un error que se comete a menudo:** si el modelo aplicara `password_hash()` por su cuenta, y alguien lo llamara dos veces, tendrías un hash de un hash, y el login nunca funcionaría. **Cada transformación ocurre en un solo sitio.** El hashing, en el controlador; el INSERT, en el modelo.

**⚠️ Nunca confíes en un modelo.** En un proyecto real, un modelo que guarde contraseñas debería refuse-se a hacerlo salvo que el valor ya viene hasheado. La defense in depth (defensa en profundidad) dice que si alguien mete una contraseña en claro por accidente, la capa que guarda en la base debería detectarla:

```php
// Defensa adicional (no implementado en este proyecto)
if (str_starts_with($passwordHash, '$2y$') === false) {
    throw new InvalidArgumentException('La contraseña debe venir hasheada');
}
```

## 6. Lo que este modelo NO hace

**No verifica contraseñas.** Ni `password_verify()`, ni `password_hash()`. No las toca. Toda la criptografía vive en `AuthController`:

```php
// En AuthController::login()
$usuario = $usuarioModel->buscarPorEmail($email);
if ($usuario === null || !password_verify($password, $usuario['password'])) { ... }

// En AuthController::guardarRegistro()
$hash = password_hash($password, PASSWORD_DEFAULT);
$usuarioModel->crear($nombre, $email, $hash);
```

**¿Por qué esta separación es buena?** Porque la lógica de contraseñas debe estar en **un solo lugar**. Si estuviera repartida entre el modelo y el controlador, un día alguien añadiría un `crear()` nuevo y olvidaría hashear.

## 7. El hash, a fondo

### Cómo se genera

```php
$hash = password_hash('password', PASSWORD_DEFAULT);
```

Salida típica:
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

Estructura:

| Parte | Significado |
|---|---|
| `$2y$` | Algoritmo bcrypt (`$2y$` es la variante corregida de `$2$`) |
| `10` | Coste: 2^10 = 1024 rondas |
| `92IXUNpkjO0rOQ5byMi.Ye...` | Sal (22 caracteres) + huella, base64 |

### Por qué la sal

Dos contraseñas idénticas (`123`, `123`) producen **hashes distintos**, porque la sal es aleatoria. Sin sal, si dos bases tuvieran usuarios con la contraseña `123`, un atacante vería dos hashes iguales y sabría que las contraseñas son iguales. Con sal, es imposible saberlo solo con la base.

### Por qué no se puede "descifrar"

El hash es una **huella de una sola dirección**: no hay forma de volver atrás. Por eso el login no busca la contraseña en la base: trae la fila del usuario y compara con `password_verify()`, que rehace el hash con la sal guardada y compara el resultado.

### Por qué `PASSWORD_DEFAULT` y no `'bcrypt'`

`PASSWORD_DEFAULT` significa "el mejor algoritmo disponible". Si PHP cambia el algoritmo por defecto en el futuro (por ejemplo, a argon2), tu código **seguirá funcionando** sin tocar nada, y los hashes nuevos usarán el algoritmo más seguro. Si escribieras `'bcrypt'` a secas, te quedarías anclado a bcrypt para siempre.

⚠️ **Los hashes antiguos siguen funcionando.** `password_verify()` lee el algoritmo del propio hash (`$2y$`), así que una base con hashes bcrypt y argon2 mezclados funciona sin problema.

### Verificar la contraseña

```php
password_verify('password', $hashAlmacenado);   // true
```

⚠️ **Y el error que nunca debes cometer:** guardar `password_hash()` de la contraseña en el `WHERE` de la consulta. Como la sal es aleatoria, el hash nunca coincidirá. Y sería una invitación a la inyección.

## 8. Esquema de la tabla

```sql
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del usuario',
  email VARCHAR(150) NOT NULL UNIQUE COMMENT 'Email único para login',
  password VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada con password_hash()',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**`VARCHAR(255)` para el hash, no `VARCHAR(50)`.** Bcrypt ocupa 60 caracteres, pero argon2id ocupa más de 95. Con `255` hay margen para cambiar de algoritmo sin tocar el esquema.

⚠️ **Y `VARCHAR` de nuevo, no `CHAR`.** `CHAR(n)` reserva un espacio fijo y rellena con espacios; `VARCHAR(n)` usa solo lo que necesita. Para un hash, `VARCHAR` es lo correcto.

**`email VARCHAR(150) NOT NULL UNIQUE`** → el `UNIQUE` es una restricción que **la base aplica siempre**, sin importar lo que haga PHP. Es la segunda capa contra el registro duplicado.

⚠️ **MySQL no distingue mayúsculas en un `UNIQUE` con la collation por defecto** (`utf8mb4_unicode_ci`): `Demo@Liga.com` y `demo@liga.com` son la misma clave. Por eso `AuthController` normaliza con `strtolower()` **antes** de guardar, y así el mensaje de duplicado también es consistente.

## 9. Errores frecuentes con este modelo

| Error | Síntoma | Causa |
|---|---|---|
| `fetch()` esperando `null` | El login nunca detecta "usuario no encontrado" | Devuelve `false` |
| Olvidar `PDO::PARAM_STR` | Consultas lentas | MySQL no puede usar el índice |
| Sin `LIMIT 1` | Consulta innecesariamente lenta | Trae todas las coincidencias |
| `crear($nombre, $email, $password)` sin hashear | Nadie puede iniciar sesión | El hash no se parece a la contraseña |
| Nombres en minúscula (`password_hash()` vs `PASSWORD_HASH`) | `undefined constant` | Las constantes van en MAYÚSCULAS |
| Buscar la contraseña en el `WHERE` | El login nunca funciona | La sal hace los hashes distintos |
| Mensajes "usuario no encontrado" / "contraseña incorrecta" | Permiten enumerar usuarios | revealing de información |

## 10. Verificación

```bash
mysql -u root liga_basquet --batch -e "
SELECT id, nombre, email, LEFT(password, 7) AS hash_prefijo, LENGTH(password) AS largo
FROM usuarios;"
```

Salida esperada:

```
id	nombre		email			hash_prefijo	largo
1	Usuario Demo	demo@liga.com	$2y$10$	60
2	Administrador	admin@liga.com	$2y$10$	60
```

**Si `largo` fuera 8 y el prefijo no empezara por `$2y$`, tendrías contraseñas en texto plano en la base.** Es la comprobación más importante que puedes hacer.

**Verificado en las pruebas:** el hash almacenado empieza por `$2y$` y la contraseña nunca aparece en claro.

---

**Archivos relacionados:** `../Controllers/AuthController-explicado.md` (donde ocurre el hashing) · `../Core/Model-explicado.md` · `../../02-Fundamentos/06-Sesiones-y-Auth.md` · `../../04-BD/schema-explicado.md` (sección 3)

**Siguiente paso →** `Equipo-explicado.md` (el manejo del `NULL` en un campo opcional).