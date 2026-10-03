<?php

/**
 * Modelo Partido - Gestión de partidos
 */
class Partido extends Model {
    protected string $tabla = 'partidos';

    public function crear(int $equipoLocalId, int $equipoVisitanteId, int $puntosLocal, int $puntosVisitante, string $fecha): bool {
        $sql = "INSERT INTO {$this->tabla} (equipo_local_id, equipo_visitante_id, puntos_local, puntos_visitante, fecha) 
                VALUES (:local, :visitante, :pl, :pv, :fecha)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':local', $equipoLocalId, PDO::PARAM_INT);
        $stmt->bindValue(':visitante', $equipoVisitanteId, PDO::PARAM_INT);
        $stmt->bindValue(':pl', $puntosLocal, PDO::PARAM_INT);
        $stmt->bindValue(':pv', $puntosVisitante, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function actualizar(int $id, int $equipoLocalId, int $equipoVisitanteId, int $puntosLocal, int $puntosVisitante, string $fecha): bool {
        $sql = "UPDATE {$this->tabla} 
                SET equipo_local_id = :local, equipo_visitante_id = :visitante, puntos_local = :pl, puntos_visitante = :pv, fecha = :fecha 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':local', $equipoLocalId, PDO::PARAM_INT);
        $stmt->bindValue(':visitante', $equipoVisitanteId, PDO::PARAM_INT);
        $stmt->bindValue(':pl', $puntosLocal, PDO::PARAM_INT);
        $stmt->bindValue(':pv', $puntosVisitante, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function obtenerTodos(): array {
        $sql = "SELECT p.*, 
                       el.nombre AS nombre_local, 
                       ev.nombre AS nombre_visitante
                FROM {$this->tabla} p
                LEFT JOIN equipos el ON el.id = p.equipo_local_id
                LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
                ORDER BY p.fecha DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $sql = "SELECT p.*, 
                       el.nombre AS nombre_local, 
                       ev.nombre AS nombre_visitante
                FROM {$this->tabla} p
                LEFT JOIN equipos el ON el.id = p.equipo_local_id
                LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
                WHERE p.id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado === false ? null : $resultado;
    }
}
