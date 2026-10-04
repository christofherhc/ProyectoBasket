<?php

/**
 * Clase Router - Enrutador para Front Controller
 *
 * Traduce URL con parámetros GET (controller, action, params)
 * a controladores y métodos correspondientes.
 *
 * Toda entrada de la URL se contrasta contra la lista blanca RUTAS antes de
 * usarla. Sin esa comprobación, `?controller[]=x` provoca un TypeError y
 * `?action=view` permite llamar a métodos protegidos. Ver errores #17 y #19.
 */
class Router {
    /**
     * Rutas permitidas: controlador => acciones públicas.
     * Los nombres de acciones están en minúsculas porque la comparación es
     * insensible a mayúsculas (igual que lo era PHP al resolver los métodos).
     */
    private const RUTAS = [
        'auth' => [
            'index', 'login', 'register', 'guardarregistro', 'logout',
        ],
        'equipos' => [
            'index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar',
        ],
        'jugadores' => [
            'index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar',
        ],
        'partidos' => [
            'index', 'crear', 'guardar', 'editar', 'actualizar', 'eliminar',
        ],
        'estadisticas' => [
            'index',
        ],
    ];

    private string $controllerNombre = 'Auth';
    private string $actionNombre = 'index';
    private array $params = [];
    private ?object $controllerObjeto = null;

    /**
     * Constructor del Router
     * Procesa la URL y ejecuta controlador/acción
     */
    public function __construct() {
        $this->parseUrl();
        $this->loadController();
        $this->executeAction();
    }

    /**
     * Extrae controller, action y parámetros de la URL ($_GET)
     * Descarta cualquier valor que no esté en la lista blanca.
     *
     * @return void
     */
    private function parseUrl(): void {
        $controlador = strtolower(trim(get('controller', '')));
        $accion      = strtolower(trim(get('action', 'index')));

        if ($controlador === '') {
            $controlador = 'auth';
        }

        if (!array_key_exists($controlador, self::RUTAS)) {
            $this->error('Controlador no permitido. Vuelve a la página principal.');
        }

        $permitidas = self::RUTAS[$controlador];
        if (!in_array($accion, $permitidas, true)) {
            $this->error('Acción no permitida para este controlador.');
        }

        $this->controllerNombre = ucfirst($controlador);
        $this->actionNombre = $accion;

        // El resto de parámetros (id, etc.) solo se acepta si son escalares.
        $this->params = [];
        foreach ($_GET as $clave => $valor) {
            if ($clave === 'controller' || $clave === 'action') {
                continue;
            }
            if (is_string($valor)) {
                $this->params[$clave] = $valor;
            }
        }
    }

    /**
     * Carga el archivo del controlador e instancia la clase
     *
     * @return void
     */
    private function loadController(): void {
        $nombreClase = $this->controllerNombre . 'Controller';
        $rutaControlador = __DIR__ . '/../controllers/' . $nombreClase . '.php';

        if (!is_file($rutaControlador)) {
            $this->error('No se encontró el archivo del controlador.');
        }

        require_once $rutaControlador;

        if (!class_exists($nombreClase)) {
            $this->error('No existe la clase del controlador.');
        }

        $this->controllerObjeto = new $nombreClase();
    }

    /**
     * Ejecuta la acción (método) en el controlador
     *
     * @return void
     */
    private function executeAction(): void {
        if ($this->controllerObjeto === null) {
            $this->error('Controlador no instanciado.');
        }

        // is_callable() descarta métodos protegidos y privados.
        // method_exists() los aceptaba y provoke pantallas en blanco.
        if (!is_callable([$this->controllerObjeto, $this->actionNombre])) {
            $this->error('La acción solicitada no está disponible.');
        }

        call_user_func_array(
            [$this->controllerObjeto, $this->actionNombre],
            [$this->params]
        );
    }

    /**
     * Muestra un error breve y termina la petición.
     *
     * No se imprime nunca el valor recibido de la URL: hacerlo permitiría
     * reflejar HTML o JavaScript en la respuesta (XSS reflejado, error #17).
     *
     * @param string $mensaje
     * @return void
     */
    private function error(string $mensaje): void {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<title>Petición no válida</title></head><body>'
            . '<h1>Petición no válida</h1><p>' . e($mensaje) . '</p>'
            . '<p><a href="' . e(BASE_URL) . '">Volver al inicio</a></p>'
            . '</body></html>';
        exit;
    }
}