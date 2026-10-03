<?php

/**
 * Clase Controller - Clase base para todos los controladores
 * 
 * Proporciona funcionalidad común para renderizar vistas
 * y gestión de autenticación básica.
 */
class Controller {
    /**
     * Renderiza una vista dentro del layout principal
     * 
     * @param string $vista Ruta de la vista (ej. 'equipos/index')
     * @param array $datos Datos a pasar a la vista
     * @return void
     */
    protected function view(string $vista, array $datos = []): void {
        // Extraer datos para convertir claves en variables
        if (!empty($datos)) {
            extract($datos);
        }
        
        // Cargar layout principal (contiene header, nav, footer)
        require_once '../app/views/layouts/main.php';
    }

    /**
     * Requiere que el usuario esté autenticado
     * Redirige a login si no está logueado
     * 
     * @return void
     */
    protected function requiereLogin(): void {
        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['error'] = 'Debes iniciar sesión para acceder a esta página';
            header('Location: ' . BASE_URL . '?controller=Auth&action=index');
            exit;
        }
    }
}
