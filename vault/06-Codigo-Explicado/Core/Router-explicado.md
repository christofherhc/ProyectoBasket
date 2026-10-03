# Explicación detallada: Router.php

Front Controller routing GET.

## parseUrl()
- `strtolower + ucfirst` normaliza controller → PascalCase
- `strtolower` action
- Extrae params, `unset` controller/action

## loadController()
Construye `NombreController.php`, `require_once`, `class_exists`, instancia.

## executeAction()
`method_exists` + `call_user_func_array([$obj, $action], [$params])` pasa array params.

## Diseño
Dinámico, claro, sin mod_rewrite.
