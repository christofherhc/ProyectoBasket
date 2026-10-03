<?php

/**
 * Clase Router - Enrutador para Front Controller
 * 
 * Traduce URL con parámetros GET (controller, action, params)
 * a controladores y métodos correspondientes.
 */
class Router {
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
     * 
     * @return void
     */
    private function parseUrl(): void {
        // Obtener nombre del controlador
        if (isset($_GET['controller']) && !empty(trim($_GET['controller']))) {
            $controller = trim($_GET['controller']);
            // Normalizar: primera mayúscula, resto minúsculas
            $this->controllerNombre = ucfirst(strtolower($controller));
        }

        // Obtener nombre de la acción
        if (isset($_GET['action']) && !empty(trim($_GET['action']))) {
            $action = trim($_GET['action']);
            $this->actionNombre = strtolower($action);
        }

        // Obtener parámetros adicionales (id, etc.)
        $this->params = $_GET;
        // Eliminar controller y action de params
        unset($this->params['controller']);
        unset($this->params['action']);
    }

    /**
     * Carga el archivo del controlador e instancia la clase
     * 
     * @return void
     */
    private function loadController(): void {
        $nombreClase = $this->controllerNombre . 'Controller';
        $rutaControlador = '../app/controllers/' . $nombreClase . '.php';

        // Verificar si existe el archivo del controlador
        if (!file_exists($rutaControlador)) {
            die("Error: No se encontró el controlador: " . $nombreClase);
        }

        // Cargar archivo
        require_once $rutaControlador;

        // Verificar si existe la clase
        if (!class_exists($nombreClase)) {
            die("Error: No existe la clase: " . $nombreClase);
        }

        // Instanciar controlador
        $this->controllerObjeto = new $nombreClase();
    }

    /**
     * Ejecuta la acción (método) en el controlador
     * 
     * @return void
     */
    private function executeAction(): void {
        if ($this->controllerObjeto === null) {
            die("Error: Controlador no instanciado");
        }

        // Verificar si el método existe
        if (!method_exists($this->controllerObjeto, $this->actionNombre)) {
            die("Error: El método '{$this->actionNombre}' no existe en " . get_class($this->controllerObjeto));
        }

        // Ejecutar método pasando parámetros
        call_user_func_array(
            [$this->controllerObjeto, $this->actionNombre],
            [$this->params]
        );
    }
}
