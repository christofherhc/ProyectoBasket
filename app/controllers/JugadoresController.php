<?php

/**
 * JugadoresController - CRUD de Jugadores
 */
class JugadoresController extends Controller {
    private Jugador $jugadorModel;
    private Equipo $equipoModel;

    public function __construct() {
        require_once '../app/models/Jugador.php';
        require_once '../app/models/Equipo.php';
        $this->jugadorModel = new Jugador();
        $this->equipoModel = new Equipo();
    }

    public function index(): void {
        $this->requiereLogin();
        $jugadores = $this->jugadorModel->obtenerTodos();
        $this->view('jugadores/index', [
            'titulo' => 'Listado de Jugadores',
            'jugadores' => $jugadores
        ]);
    }

    public function crear(): void {
        $this->requiereLogin();
        $equipos = $this->equipoModel->obtenerTodos();
        $old = $_SESSION['old_jugador'] ?? [];
        unset($_SESSION['old_jugador']);
        $this->view('jugadores/crear', [
            'titulo' => 'Crear Jugador',
            'equipos' => $equipos,
            'old' => $old
        ]);
    }

    public function guardar(): void {
        $this->requiereLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Jugadores&action=crear');
            exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $numero = $_POST['numero'] ?? '';
        $posicion = trim($_POST['posicion'] ?? '');
        $equipoId = (int)($_POST['equipo_id'] ?? 0);

        $_SESSION['old_jugador'] = [
            'nombre' => $nombre,
            'numero' => $numero,
            'posicion' => $posicion,
            'equipo_id' => $equipoId
        ];

        if (empty($nombre) || $equipoId <= 0) {
            $_SESSION['error'] = 'Nombre y equipo son obligatorios';
            header('Location: ' . BASE_URL . '?controller=Jugadores&action=crear');
            exit;
        }

        $numeroInt = null;
        if ($numero !== '') {
            $numeroInt = (int)$numero;
            if ($numeroInt < 0 || $numeroInt > 99) {
                $_SESSION['error'] = 'Número de camiseta inválido (0-99)';
                header('Location: ' . BASE_URL . '?controller=Jugadores&action=crear');
                exit;
            }
        }

        $this->jugadorModel->crear($nombre, $numeroInt, $posicion, $equipoId);
        unset($_SESSION['old_jugador']);
        $_SESSION['exito'] = 'Jugador creado correctamente';
        header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
        exit;
    }

    public function editar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        $jugador = $this->jugadorModel->obtenerPorId($id);
        if ($jugador === null) {
            $_SESSION['error'] = 'Jugador no encontrado';
            header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
            exit;
        }
        $equipos = $this->equipoModel->obtenerTodos();
        $old = $_SESSION['old_jugador'] ?? [];
        unset($_SESSION['old_jugador']);
        $this->view('jugadores/editar', [
            'titulo' => 'Editar Jugador',
            'jugador' => $jugador,
            'equipos' => $equipos,
            'old' => $old
        ]);
    }

    public function actualizar(): void {
        $this->requiereLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $numero = $_POST['numero'] ?? '';
        $posicion = trim($_POST['posicion'] ?? '');
        $equipoId = (int)($_POST['equipo_id'] ?? 0);

        $_SESSION['old_jugador'] = [
            'nombre' => $nombre,
            'numero' => $numero,
            'posicion' => $posicion,
            'equipo_id' => $equipoId
        ];

        if (empty($nombre) || $equipoId <= 0 || $id <= 0) {
            $_SESSION['error'] = 'Datos inválidos para actualizar jugador';
            header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
            exit;
        }

        $numeroInt = null;
        if ($numero !== '') {
            $numeroInt = (int)$numero;
            if ($numeroInt < 0 || $numeroInt > 99) {
                $_SESSION['error'] = 'Número de camiseta inválido (0-99)';
                header('Location: ' . BASE_URL . '?controller=Jugadores&action=editar&id=' . $id);
                exit;
            }
        }

        $this->jugadorModel->actualizar($id, $nombre, $numeroInt, $posicion, $equipoId);
        unset($_SESSION['old_jugador']);
        $_SESSION['exito'] = 'Jugador actualizado correctamente';
        header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
        exit;
    }

    public function eliminar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        if ($id > 0) {
            $this->jugadorModel->eliminar($id);
            $_SESSION['exito'] = 'Jugador eliminado correctamente';
        } else {
            $_SESSION['error'] = 'ID de jugador inválido';
        }
        header('Location: ' . BASE_URL . '?controller=Jugadores&action=index');
        exit;
    }
}
