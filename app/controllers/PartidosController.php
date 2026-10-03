<?php

/**
 * PartidosController - CRUD + lógica partidos
 */
class PartidosController extends Controller {
    private Partido $partidoModel;
    private Equipo $equipoModel;

    public function __construct() {
        require_once '../app/models/Partido.php';
        require_once '../app/models/Equipo.php';
        $this->partidoModel = new Partido();
        $this->equipoModel = new Equipo();
    }

    public function index(): void {
        $this->requiereLogin();
        $partidos = $this->partidoModel->obtenerTodos();
        $this->view('partidos/index', [
            'titulo' => 'Listado de Partidos',
            'partidos' => $partidos
        ]);
    }

    public function crear(): void {
        $this->requiereLogin();
        $equipos = $this->equipoModel->obtenerTodos();
        $old = $_SESSION['old_partido'] ?? [];
        unset($_SESSION['old_partido']);
        $this->view('partidos/crear', [
            'titulo' => 'Registrar Partido',
            'equipos' => $equipos,
            'old' => $old
        ]);
    }

    public function guardar(): void {
        $this->requiereLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
            exit;
        }

        $equipoLocalId = (int)($_POST['equipo_local_id'] ?? 0);
        $equipoVisitanteId = (int)($_POST['equipo_visitante_id'] ?? 0);
        $puntosLocal = (int)($_POST['puntos_local'] ?? 0);
        $puntosVisitante = (int)($_POST['puntos_visitante'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');

        $_SESSION['old_partido'] = [
            'equipo_local_id' => $equipoLocalId,
            'equipo_visitante_id' => $equipoVisitanteId,
            'puntos_local' => $puntosLocal,
            'puntos_visitante' => $puntosVisitante,
            'fecha' => $fecha
        ];

        if ($equipoLocalId <= 0 || $equipoVisitanteId <= 0) {
            $_SESSION['error'] = 'Debe seleccionar ambos equipos';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
            exit;
        }
        if ($equipoLocalId === $equipoVisitanteId) {
            $_SESSION['error'] = 'El equipo local y visitante no pueden ser iguales';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
            exit;
        }
        if ($fecha === '') {
            $_SESSION['error'] = 'La fecha es obligatoria';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
            exit;
        }
        if ($puntosLocal < 0 || $puntosVisitante < 0) {
            $_SESSION['error'] = 'Los puntos no pueden ser negativos';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=crear');
            exit;
        }

        $this->partidoModel->crear($equipoLocalId, $equipoVisitanteId, $puntosLocal, $puntosVisitante, $fecha);
        unset($_SESSION['old_partido']);
        $_SESSION['exito'] = 'Partido registrado correctamente';
        header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
        exit;
    }

    public function editar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        $partido = $this->partidoModel->obtenerPorId($id);
        if ($partido === null) {
            $_SESSION['error'] = 'Partido no encontrado';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
            exit;
        }
        $equipos = $this->equipoModel->obtenerTodos();
        $old = $_SESSION['old_partido'] ?? [];
        unset($_SESSION['old_partido']);
        $this->view('partidos/editar', [
            'titulo' => 'Editar Partido',
            'partido' => $partido,
            'equipos' => $equipos,
            'old' => $old
        ]);
    }

    public function actualizar(): void {
        $this->requiereLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $equipoLocalId = (int)($_POST['equipo_local_id'] ?? 0);
        $equipoVisitanteId = (int)($_POST['equipo_visitante_id'] ?? 0);
        $puntosLocal = (int)($_POST['puntos_local'] ?? 0);
        $puntosVisitante = (int)($_POST['puntos_visitante'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');

        $_SESSION['old_partido'] = [
            'equipo_local_id' => $equipoLocalId,
            'equipo_visitante_id' => $equipoVisitanteId,
            'puntos_local' => $puntosLocal,
            'puntos_visitante' => $puntosVisitante,
            'fecha' => $fecha
        ];

        if ($id <= 0 || $equipoLocalId <= 0 || $equipoVisitanteId <= 0) {
            $_SESSION['error'] = 'Datos inválidos';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
            exit;
        }
        if ($equipoLocalId === $equipoVisitanteId) {
            $_SESSION['error'] = 'Equipos no pueden ser iguales';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=editar&id=' . $id);
            exit;
        }
        if ($fecha === '' || $puntosLocal < 0 || $puntosVisitante < 0) {
            $_SESSION['error'] = 'Datos inválidos (fecha/puntos)';
            header('Location: ' . BASE_URL . '?controller=Partidos&action=editar&id=' . $id);
            exit;
        }

        $this->partidoModel->actualizar($id, $equipoLocalId, $equipoVisitanteId, $puntosLocal, $puntosVisitante, $fecha);
        unset($_SESSION['old_partido']);
        $_SESSION['exito'] = 'Partido actualizado correctamente';
        header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
        exit;
    }

    public function eliminar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        if ($id > 0) {
            $this->partidoModel->eliminar($id);
            $_SESSION['exito'] = 'Partido eliminado correctamente';
        } else {
            $_SESSION['error'] = 'ID inválido';
        }
        header('Location: ' . BASE_URL . '?controller=Partidos&action=index');
        exit;
    }
}
