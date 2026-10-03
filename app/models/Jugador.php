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
        if ($numero === null || $numero === 0) {
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
        if ($numero === null || $numero === 0) {
            $stmt->bindValue(':numero', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':numero', $numero, PDO::PARAM_INT);
        }
        $stmt->bindValue(':posicion', $posicion === '' ? null : $posicion, PDO::PARAM_STR);
        $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
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
}
