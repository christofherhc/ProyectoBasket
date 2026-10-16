<?php

/**
 * JugadoresController - CRUD de Jugadores
 */
class JugadoresController extends Controller {
    private Jugador $jugadorModel;
    private Equipo $equipoModel;

    public function __construct() {
        require_once __DIR__ . '/../models/Jugador.php';
        require_once __DIR__ . '/../models/Equipo.php';
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
        exigirPost('controller=Jugadores&action=crear');

        [$datos, $errores] = $this->validarCampos();

        if ($errores !== []) {
            flashError(implode(' ', $errores));
            redir('controller=Jugadores&action=crear');
        }

        $this->jugadorModel->crear(
            $datos['nombre'],
            $datos['numero'],
            $datos['posicion'],
            $datos['equipo_id']
        );

        unset($_SESSION['old_jugador']);
        flashExito('Jugador creado correctamente');
        redir('controller=Jugadores&action=index');
    }

    public function editar(array $params): void {
        $this->requiereLogin();
        $id = (int)($params['id'] ?? 0);
        $jugador = $this->jugadorModel->obtenerPorId($id);
        if ($jugador === null) {
            flashError('Jugador no encontrado');
            redir('controller=Jugadores&action=index');
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
        exigirPost('controller=Jugadores&action=index');

        $id = (int)post('id');
        [$datos, $errores] = $this->validarCampos($id);

        if ($errores === []) {
            $errores = $this->validarId($id);
        }

        // Devuelve al formulario de edición, no al listado, para no perder
        // los datos que el usuario ya había escrito.
        $volver = 'controller=Jugadores&action=editar&id=' . $id;

        if ($errores !== []) {
            flashError(implode(' ', $errores));
            redir($volver);
        }

        $actualizado = $this->jugadorModel->actualizar(
            $id,
            $datos['nombre'],
            $datos['numero'],
            $datos['posicion'],
            $datos['equipo_id']
        );

        unset($_SESSION['old_jugador']);

        if (!$actualizado) {
            flashError('No se encontró el jugador que se quiso modificar');
            redir('controller=Jugadores&action=index');
        }

        flashExito('Jugador actualizado correctamente');
        redir('controller=Jugadores&action=index');
    }

    public function eliminar(array $params): void {
        $this->requiereLogin();
        exigirPost('controller=Jugadores&action=index');

        $id = (int)post('id');

        if ($id <= 0) {
            flashError('ID de jugador inválido');
            redir('controller=Jugadores&action=index');
        }

        if (!$this->jugadorModel->eliminar($id)) {
            flashError('No se encontró el jugador que se quiso eliminar');
            redir('controller=Jugadores&action=index');
        }

        flashExito('Jugador eliminado correctamente');
        redir('controller=Jugadores&action=index');
    }

    /**
     * Valida los campos del formulario de jugador.
     *
     * @param int|null $ignorarId Al editar, el id del propio jugador (para no
     *                            marcarlo como dorsal duplicado de sí mismo).
     * @return array [datos, errores]
     */
    private function validarCampos(?int $ignorarId = null): array {
        $nombre    = trim(post('nombre'));
        $numero    = trim(post('numero'));
        $posicion  = trim(post('posicion'));
        $equipoId  = (int)post('equipo_id', '0');

        $_SESSION['old_jugador'] = [
            'nombre'    => $nombre,
            'numero'    => $numero,
            'posicion'  => $posicion,
            'equipo_id' => $equipoId,
        ];

        $errores = [];

        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio';
        } elseif (!cabeEnColumna($nombre, MAX_NOMBRE)) {
            $errores[] = 'El nombre no puede superar los ' . MAX_NOMBRE . ' caracteres';
        }

        // Dorsal: opcional. El 0 es válido (hay jugadores que lo llevan).
        $numeroInt = null;
        if ($numero !== '') {
            if (!ctype_digit($numero)) {
                $errores[] = 'El número de camiseta debe ser un número entero';
            } else {
                $numeroInt = (int)$numero;
                if ($numeroInt > MAX_CAMISETA) {
                    $errores[] = 'El número de camiseta debe estar entre 0 y ' . MAX_CAMISETA;
                }
            }
        }

        // Posición: lista blanca, porque la columna es VARCHAR y no ENUM.
        if ($posicion !== '' && !in_array($posicion, POSICIONES, true)) {
            $errores[] = 'Posición no válida. Opciones: ' . implode(', ', POSICIONES);
        }

        // Equipo: debe existir de verdad, o la clave foránea revienta con 1452.
        $equipoExiste = false;
        if ($equipoId <= 0) {
            $errores[] = 'Debe seleccionar un equipo';
        } elseif ($this->equipoModel->obtenerPorId($equipoId) === null) {
            $errores[] = 'El equipo seleccionado no existe';
        } else {
            $equipoExiste = true;
        }

        // Dorsal repetido dentro del mismo equipo. El índice UNIQUE lo bloquea
        // igualmente, pero así el mensaje es claro y apunta al campo.
        if ($numeroInt !== null && $equipoExiste
            && $this->jugadorModel->existeDorsalEnEquipo($equipoId, $numeroInt, $ignorarId ?? 0)) {
            $errores[] = 'Ese número de camiseta ya está en uso en el equipo seleccionado';
        }

        return [
            [
                'nombre'    => $nombre,
                'numero'    => $numeroInt,
                'posicion'  => $posicion === '' ? null : $posicion,
                'equipo_id' => $equipoId,
            ],
            $errores,
        ];
    }

    /**
     * Comprueba que el id sea válido y que el jugador exista.
     *
     * @param int $id
     * @return array Lista de errores
     */
    private function validarId(int $id): array {
        if ($id <= 0) {
            return ['ID de jugador inválido'];
        }
        if ($this->jugadorModel->obtenerPorId($id) === null) {
            return ['No se encontró el jugador que se quiso modificar'];
        }
        return [];
    }
}