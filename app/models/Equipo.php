<?php

/**
 * Modelo Equipo - Gestión de equipos
 */
class Equipo extends Model {
    protected string $tabla = 'equipos';

    /**
     * Crea un nuevo equipo
     */
    public function crear(string $nombre, string $ciudad, ?int $fundacion = null): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, ciudad, fundacion) 
                VALUES (:nombre, :ciudad, :fundacion)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
        if ($fundacion === null || $fundacion === 0) {
            $stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
        }
        return $stmt->execute();
    }

    /**
     * Actualiza un equipo existente
     */
    public function actualizar(int $id, string $nombre, string $ciudad, ?int $fundacion = null): bool {
        $sql = "UPDATE {$this->tabla} 
                SET nombre = :nombre, ciudad = :ciudad, fundacion = :fundacion 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':ciudad', $ciudad, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($fundacion === null || $fundacion === 0) {
            $stmt->bindValue(':fundacion', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fundacion', $fundacion, PDO::PARAM_INT);
        }
        // execute() devuelve true aunque no se modifique ninguna fila.
        // rowCount() dice si el registro existía, PERO MySQL cuenta filas
        // *cambiadas*, no filas *encontradas*: si se guarda un equipo sin
        // tocar nada, rowCount() es 0 aunque el equipo exista de sobra.
        // Por eso, cuando no cambia nada, se pregunta aparte si el id existe.
        if (!$stmt->execute()) {
            return false;
        }
        if ($stmt->rowCount() > 0) {
            return true;
        }
        return $this->obtenerPorId($id) !== null;
    }

    /**
     * Obtiene todos ordenados por nombre
     */
    public function obtenerTodos(): array {
        $sql = "SELECT * FROM {$this->tabla} ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
