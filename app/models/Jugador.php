<?php

/**
 * Modelo Jugador - Gestión de jugadores
 */
class Jugador extends Model {
    protected string $tabla = 'jugadores';

    public function crear(string $nombre, ?int $numero, ?string $posicion, int $equipoId): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, numero, posicion, equipo_id) 
                VALUES (:nombre, :numero, :posicion, :equipo_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        // null significa "sin dorsal". El 0 SÍ es un dorsal válido (lo llevan
        // jugadores reales), así que no se puede confundir con "vacío".
        if ($numero === null) {
            $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
        }
        $stmt->bindValue(':posicion', $posicion === '' ? null : $posicion, PDO::PARAM_STR);
        $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function actualizar(int $id, string $nombre, ?int $numero, ?string $posicion, int $equipoId): bool {
        $sql = "UPDATE {$this->tabla} 
                SET nombre = :nombre, numero = :numero, posicion = :posicion, equipo_id = :equipo_id 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        // El 0 es un dorsal válido; solo null significa "sin dorsal".
        if ($numero === null) {
            $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
        }
        $stmt->bindValue(':posicion', $posicion === '' ? null : $posicion, PDO::PARAM_STR);
        $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        // execute() devuelve true aunque no se modifique ninguna fila.
        // rowCount() dice si el registro existía, PERO MySQL cuenta filas
        // *cambiadas*, no filas *encontradas*: si se guarda un jugador sin
        // tocar nada, rowCount() es 0 aunque el jugador exista de sobra.
        // Por eso, cuando no cambia nada, se pregunta aparte si el id existe.
        if (!$stmt->execute()) {
            return false;
        }
        if ($stmt->rowCount() > 0) {
            return true;
        }
        return $this->obtenerPorId($id) !== null;
    }

    public function obtenerTodos(): array {
        $sql = "SELECT j.*, e.nombre AS nombre_equipo 
                FROM {$this->tabla} j 
                LEFT JOIN equipos e ON e.id = j.equipo_id 
                ORDER BY j.nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $sql = "SELECT j.*, e.nombre AS nombre_equipo 
                FROM {$this->tabla} j 
                LEFT JOIN equipos e ON e.id = j.equipo_id 
                WHERE j.id = :id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado === false ? null : $resultado;
    }

    /**
     * Indica si un dorsal ya está ocupado dentro de un equipo.
     *
     * El índice UNIQUE (equipo_id, numero) lo impide de todos modos, pero
     * comprobarlo antes permite dar un mensaje claro en el formulario en vez
     * del error 1062 genérico "Ya existe un registro con esos datos".
     *
     * @param int $equipoId
     * @param int $numero
     * @param int $ignorarId Al editar, el propio jugador no debe contar.
     * @return bool
     */
    public function existeDorsalEnEquipo(int $equipoId, int $numero, int $ignorarId = 0): bool {
        $sql = "SELECT COUNT(*) FROM {$this->tabla}
                WHERE equipo_id = :equipo AND numero = :numero AND id <> :ignorar";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':equipo', $equipoId, PDO::PARAM_INT);
        $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
        $stmt->bindValue(':ignorar', $ignorarId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }
}
