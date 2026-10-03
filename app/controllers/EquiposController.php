<?php

/**
 * EquiposController - CRUD de Equipos
 */
class EquiposController extends Controller {
    private Equipo $equipoModel;

    public function __construct() {
        require_once '../app/models/Equipo.php';
        $this->equipoModel = new Equipo();
    }

    public function index(): void {
        $this->requiereLogin();
        $equipos = $this->equipoModel->obtenerTodos();
        $this->view('equipos/index', [
            'titulo' => 'Listado de Equipos',
            'equipos' => $equipos
        ]);
    }

    public function crear(): void {
        $this->requiereLogin();
        $old = $_SESSION['old_equipo'] ?? [];
        unset($_SESSION['old_equipo']);
        $this->view('equipos/crear', [
            'titulo' => 'Crear Equipo',
            'old' => $old
        ]);
    }

    public function guardar(): void {
        $this->requiereLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
            exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $fundacion = $_POST['fundacion'] ?? '';

        $_SESSION['old_equipo'] = [
            'nombre' => $nombre,
            'ciudad' => $ciudad,
            'fundacion' => $fundacion
        ];

        if (empty($nombre) || empty($ciudad)) {
            $_SESSION['error'] = 'Nombre y ciudad son obligatorios';
            header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
            exit;
        }

        $fundacionInt = null;
        if ($fundacion !== '') {
            $fundacionInt = (int)$fundacion;
            if ($fundacionInt < 1800 || $fundacionInt > (int)date('Y')) {
                $_SESSION['error'] = 'Año de fundación no válido';
                header('Location: ' . BASE_URL . '?controller=Equipos&action=crear');
                exit;
            }
        }

        $this->equipoModel->crear($nombre, $ciudad, $fundacionInt);
        unset($_SESSION['old_equipo']);
        $_SESSION['exito'] = 'Equipo creado correctamente';
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }

    public function editar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        $equipo = $this->equipoModel->obtenerPorId($id);
        if ($equipo === null) {
            $_SESSION['error'] = 'Equipo no encontrado';
            header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
            exit;
        }
        $old = $_SESSION['old_equipo'] ?? [];
        unset($_SESSION['old_equipo']);
        $this->view('equipos/editar', [
            'titulo' => 'Editar Equipo',
            'equipo' => $equipo,
            'old' => $old
        ]);
    }

    public function actualizar(): void {
        $this->requiereLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $fundacion = $_POST['fundacion'] ?? '';

        $_SESSION['old_equipo'] = [
            'nombre' => $nombre,
            'ciudad' => $ciudad,
            'fundacion' => $fundacion
        ];

        if (empty($nombre) || empty($ciudad) || $id <= 0) {
            $_SESSION['error'] = 'Datos inválidos para actualizar equipo';
            header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
            exit;
        }

        $fundacionInt = null;
        if ($fundacion !== '') {
            $fundacionInt = (int)$fundacion;
            if ($fundacionInt < 1800 || $fundacionInt > (int)date('Y')) {
                $_SESSION['error'] = 'Año de fundación no válido';
                header('Location: ' . BASE_URL . '?controller=Equipos&action=editar&id=' . $id);
                exit;
            }
        }

        $this->equipoModel->actualizar($id, $nombre, $ciudad, $fundacionInt);
        unset($_SESSION['old_equipo']);
        $_SESSION['exito'] = 'Equipo actualizado correctamente';
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }

    public function eliminar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        if ($id > 0) {
            $this->equipoModel->eliminar($id);
            $_SESSION['exito'] = 'Equipo eliminado correctamente';
        } else {
            $_SESSION['error'] = 'ID de equipo inválido';
        }
        header('Location: ' . BASE_URL . '?controller=Equipos&action=index');
        exit;
    }
}
