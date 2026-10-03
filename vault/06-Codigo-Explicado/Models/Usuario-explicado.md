# Usuario.php - Explicación

Hereda Model. `$tabla='usuarios'`.

Métodos:
- `buscarPorEmail(string $email): ?array` → WHERE email LIMIT 1, fetch, null si false
- `existeEmail(string $email): bool` → usa buscarPorEmail
- `crear(string $nombre, string $email, string $passwordHash): bool` → INSERT con hash

Uso: AuthController.
