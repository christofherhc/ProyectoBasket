# AuthController - Explicación

Flujo:
- index: redirige si logueado
- login: POST, trim+strtolower email, password_verify, session_regenerate_id(true), set sesión, redirect + exito
- register/guardarRegistro: validaciones completas, existeEmail, password_hash, old flash
- logout: limpia $_SESSION, borra cookie, session_destroy

Mensajes genéricos credenciales (seguridad).
