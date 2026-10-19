<?php

/**
 * EquiposController - CRUD de Equipos
 */
class EquiposController extends Controller {
    private Equipo $equipoModel;

    public function __construct() {
        require_once __DIR__ . '/../models/Equipo.php';
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
        exigirPost('controller=Equipos&action=crear');

        [$nombre, $ciudad, $fundacionInt, $errores] = $this->validarCampos();

        if ($errores !== []) {
            flashError(implode(' ', $errores));
            redir('controller=Equipos&action=crear');
        }

        $this->equipoModel->crear($nombre, $ciudad, $fundacionInt);
        unset($_SESSION['old_equipo']);
        flashExito('Equipo creado correctamente');
        redir('controller=Equipos&action=index');
    }

    public function editar(array $params): void {
        $this->requiereLogin();
        $id = enteroPositivo($params['id'] ?? '') ?? 0;
        $equipo = $this->equipoModel->obtenerPorId($id);
        if ($equipo === null) {
            flashError('Equipo no encontrado');
            redir('controller=Equipos&action=index');
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
        exigirPost('controller=Equipos&action=index');

        $id = enteroPositivo(post('id')) ?? 0;
        [$nombre, $ciudad, $fundacionInt, $errores] = $this->validarCampos($id);

        if ($errores === []) {
            $errores = $this->validarId($id, 'equipo');
        }

        // Los errores de un formulario de edición deben devolver al formulario,
        // no al listado: si no, el usuario pierde todo lo que había escrito.
        $volver = 'controller=Equipos&action=editar&id=' . $id;

        if ($errores !== []) {
            flashError(implode(' ', $errores));
            redir($volver);
        }

        if (!$this->equipoModel->actualizar($id, $nombre, $ciudad, $fundacionInt)) {
            flashError('No se encontró el equipo que se quiso modificar');
            redir('controller=Equipos&action=index');
        }

        unset($_SESSION['old_equipo']);
        flashExito('Equipo actualizado correctamente');
        redir('controller=Equipos&action=index');
    }

    public function eliminar(array $params): void {
        $this->requiereLogin();
        exigirPost('controller=Equipos&action=index');

        $id = enteroPositivo(post('id')) ?? 0;

        if ($id <= 0) {
            flashError('ID de equipo inválido');
            redir('controller=Equipos&action=index');
        }

        // Se informa del bloqueo antes de intentar el DELETE para poder
        // dar un mensaje concreto en lugar de la pantalla en blanco del error 1451.
        $dependientes = $this->contarDependientes($id);
        if ($dependientes['jugadores'] > 0 || $dependientes['partidos'] > 0) {
            flashError(
                'No se puede eliminar "' . $this->nombreEquipo($id) . '": tiene '
                . $dependientes['jugadores'] . ' jugador(es) y '
                . $dependientes['partidos'] . ' partido(s) registrados.'
            );
            redir('controller=Equipos&action=index');
        }

        if (!$this->equipoModel->eliminar($id)) {
            flashError('No se encontró el equipo que se quiso eliminar');
            redir('controller=Equipos&action=index');
        }

        flashExito('Equipo eliminado correctamente');
        redir('controller=Equipos&action=index');
    }

    /**
     * Valida los campos del formulario de equipo.
     *
     * @param int|null $ignorarId Al editar, el id del propio equipo (para no
     *                            marcarlo como nombre duplicado de sí mismo).
     * @return array [nombre, ciudad, fundacion, errores]
     */
    private function validarCampos(?int $ignorarId = null): array {
        $nombre    = trim(post('nombre'));
        $ciudad    = trim(post('ciudad'));
        $fundacion = trim(post('fundacion'));

        // Se guardan para repoblar el formulario tras un error.
        $_SESSION['old_equipo'] = [
            'nombre'    => $nombre,
            'ciudad'    => $ciudad,
            'fundacion' => $fundacion,
        ];

        $errores = [];

        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio';
        } elseif (!cabeEnColumna($nombre, MAX_NOMBRE)) {
            $errores[] = 'El nombre no puede superar los ' . MAX_NOMBRE . ' caracteres';
        } elseif ($this->equipoModel->existeNombre($nombre, $ignorarId ?? 0)) {
            // Dos equipos con el mismo nombre son indistinguibles en la tabla
            // de posiciones. El índice UNIQUE lo bloquea igualmente, pero así
            // el mensaje apunta al campo en vez de ser un error genérico.
            $errores[] = 'Ya existe un equipo con ese nombre';
        }

        if ($ciudad === '') {
            $errores[] = 'La ciudad es obligatoria';
        } elseif (!cabeEnColumna($ciudad, MAX_CIUDAD)) {
            $errores[] = 'La ciudad no puede superar los ' . MAX_CIUDAD . ' caracteres';
        }

        $fundacionInt = null;
        if ($fundacion !== '') {
            if (!ctype_digit($fundacion)) {
                $errores[] = 'El año de fundación debe ser un número entero';
            } else {
                $fundacionInt = (int)$fundacion;
                if ($fundacionInt < FUNDACION_MIN || $fundacionInt > (int)date('Y')) {
                    $errores[] = 'El año de fundación debe estar entre ' . FUNDACION_MIN . ' y ' . date('Y');
                }
            }
        }

        return [$nombre, $ciudad, $fundacionInt, $errores];
    }

    /**
     * Comprueba que un id sea válido y que el registro exista.
     *
     * @param int    $id
     * @param string $nombre
     * @return array Lista de errores (vacía si todo está bien)
     */
    private function validarId(int $id, string $nombre): array {
        if ($id <= 0) {
            return ['ID de ' . $nombre . ' inválido'];
        }
        if ($this->equipoModel->obtenerPorId($id) === null) {
            return ['No se encontró el ' . $nombre . ' que se quiso modificar'];
        }
        return [];
    }

    /**
     * Cuenta los registros que impiden borrar un equipo.
     *
     * @param int $id
     * @return array [jugadores, partidos]
     */
    private function contarDependientes(int $id): array {
        $db = Database::getConexion();
        $stmt = $db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM jugadores WHERE equipo_id = :a) AS jugadores,
                (SELECT COUNT(*) FROM partidos
                  WHERE equipo_local_id = :b OR equipo_visitante_id = :c) AS partidos'
        );
        $stmt->bindValue(':a', $id, PDO::PARAM_INT);
        $stmt->bindValue(':b', $id, PDO::PARAM_INT);
        $stmt->bindValue(':c', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch() ?: ['jugadores' => 0, 'partidos' => 0];
        return [
            'jugadores' => (int)$fila['jugadores'],
            'partidos'  => (int)$fila['partidos'],
        ];
    }

    /**
     * Nombre del equipo, para poder mencionarlo en el mensaje de error.
     *
     * @param int $id
     * @return string
     */
    private function nombreEquipo(int $id): string {
        $equipo = $this->equipoModel->obtenerPorId($id);
        return $equipo['nombre'] ?? ('#' . $id);
    }
}