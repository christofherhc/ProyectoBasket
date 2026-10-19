<?php

/**
 * PartidosController - CRUD + lógica partidos
 */
class PartidosController extends Controller {
    private Partido $partidoModel;
    private Equipo $equipoModel;

    public function __construct() {
        require_once __DIR__ . '/../models/Partido.php';
        require_once __DIR__ . '/../models/Equipo.php';
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
        exigirPost('controller=Partidos&action=crear');

        [$datos, $errores] = $this->validarCampos();

        if ($errores !== []) {
            flashError(implode(' ', $errores));
            redir('controller=Partidos&action=crear');
        }

        $this->partidoModel->crear(
            $datos['equipo_local_id'],
            $datos['equipo_visitante_id'],
            $datos['puntos_local'],
            $datos['puntos_visitante'],
            $datos['fecha']
        );

        unset($_SESSION['old_partido']);
        flashExito('Partido registrado correctamente');
        redir('controller=Partidos&action=index');
    }

    public function editar(array $params): void {
        $this->requiereLogin();
        $id = enteroPositivo($params['id'] ?? '') ?? 0;
        $partido = $this->partidoModel->obtenerPorId($id);
        if ($partido === null) {
            flashError('Partido no encontrado');
            redir('controller=Partidos&action=index');
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
        exigirPost('controller=Partidos&action=index');

        $id = enteroPositivo(post('id')) ?? 0;
        [$datos, $errores] = $this->validarCampos();

        if ($errores === []) {
            $errores = $this->validarId($id);
        }

        $volver = 'controller=Partidos&action=editar&id=' . $id;

        if ($errores !== []) {
            flashError(implode(' ', $errores));
            redir($volver);
        }

        $actualizado = $this->partidoModel->actualizar(
            $id,
            $datos['equipo_local_id'],
            $datos['equipo_visitante_id'],
            $datos['puntos_local'],
            $datos['puntos_visitante'],
            $datos['fecha']
        );

        unset($_SESSION['old_partido']);

        if (!$actualizado) {
            flashError('No se encontró el partido que se quiso modificar');
            redir('controller=Partidos&action=index');
        }

        flashExito('Partido actualizado correctamente');
        redir('controller=Partidos&action=index');
    }

    public function eliminar(array $params): void {
        $this->requiereLogin();
        exigirPost('controller=Partidos&action=index');

        $id = enteroPositivo(post('id')) ?? 0;

        if ($id <= 0) {
            flashError('ID de partido inválido');
            redir('controller=Partidos&action=index');
        }

        if (!$this->partidoModel->eliminar($id)) {
            flashError('No se encontró el partido que se quiso eliminar');
            redir('controller=Partidos&action=index');
        }

        flashExito('Partido eliminado correctamente');
        redir('controller=Partidos&action=index');
    }

    /**
     * Valida los campos del formulario de partido.
     *
     * @return array [datos, errores]
     */
    private function validarCampos(): array {
        $local     = (int)post('equipo_local_id', '0');
        $visitante = (int)post('equipo_visitante_id', '0');
        // Si el campo no viene, post() devuelve '' y el marcador se avisa
        // como obligatorio. Antes el valor por defecto era '0', y un POST
        // sin los campos guardaba un 0-0 sin avisar.
        $puntosLocal     = trim(post('puntos_local'));
        $puntosVisitante = trim(post('puntos_visitante'));
        $fechaBruta = trim(post('fecha'));

        $_SESSION['old_partido'] = [
            'equipo_local_id'     => $local,
            'equipo_visitante_id' => $visitante,
            'puntos_local'        => $puntosLocal,
            'puntos_visitante'    => $puntosVisitante,
            'fecha'               => $fechaBruta,
        ];

        $errores = [];

        // Equipos: los dos deben existir (si no, error 1452 de clave foránea).
        if ($local <= 0 || $visitante <= 0) {
            $errores[] = 'Debe seleccionar ambos equipos';
        } elseif ($local === $visitante) {
            $errores[] = 'El equipo local y el visitante no pueden ser iguales';
        } else {
            if ($this->equipoModel->obtenerPorId($local) === null) {
                $errores[] = 'El equipo local seleccionado no existe';
            }
            if ($this->equipoModel->obtenerPorId($visitante) === null) {
                $errores[] = 'El equipo visitante seleccionado no existe';
            }
        }

        // Puntos: se validan como texto para detectar "abc" o "-5" antes de que
        // MySQL lance el error 1264 (out of range).
        $pl = $this->validarPuntos($puntosLocal, 'local', $errores);
        $pv = $this->validarPuntos($puntosVisitante, 'visitante', $errores);

        // Fecha: se normaliza a DATETIME; si no existe de verdad, error 1292.
        $fecha = fechaValida($fechaBruta);
        if ($fechaBruta === '') {
            $errores[] = 'La fecha es obligatoria';
        } elseif ($fecha === null) {
            $errores[] = 'La fecha indicada no es válida';
        }

        return [
            [
                'equipo_local_id'     => $local,
                'equipo_visitante_id' => $visitante,
                'puntos_local'        => $pl ?? 0,
                'puntos_visitante'    => $pv ?? 0,
                'fecha'               => $fecha ?? '',
            ],
            $errores,
        ];
    }

    /**
     * Valida un marcador y lo añade a la lista de errores si no cuadra.
     *
     * @param string $valor
     * @param string $lado
     * @param array  $errores
     * @return int|null
     */
    private function validarPuntos(string $valor, string $lado, array &$errores): ?int {
        if ($valor === '') {
            $errores[] = 'Los puntos del equipo ' . $lado . ' son obligatorios';
            return null;
        }
        if (!ctype_digit($valor)) {
            $errores[] = 'Los puntos del equipo ' . $lado . ' deben ser un número entero';
            return null;
        }
        $puntos = (int)$valor;
        if ($puntos > MAX_PUNTOS) {
            $errores[] = 'Los puntos del equipo ' . $lado . ' no pueden superar ' . MAX_PUNTOS;
            return null;
        }
        return $puntos;
    }

    /**
     * Comprueba que el id sea válido y que el partido exista.
     *
     * @param int $id
     * @return array Lista de errores
     */
    private function validarId(int $id): array {
        if ($id <= 0) {
            return ['ID de partido inválido'];
        }
        if ($this->partidoModel->obtenerPorId($id) === null) {
            return ['No se encontró el partido que se quiso modificar'];
        }
        return [];
    }
}