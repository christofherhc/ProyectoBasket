<?php

/**
 * Clase Model - Clase base para todos los modelos
 * 
 * Proporciona funcionalidad común para acceso a base de datos
 * (CRUD básico) que heredarán todos los modelos de la aplicación.
 */
class Model {
    protected PDO $db;
    protected string $tabla;

    /**
     * Constructor del modelo base
     * Obtiene la conexión a base de datos
     */
    public function __construct() {
        $this->db = Database::getConexion();
    }

    /**
     * Obtiene todos los registros de la tabla
     * 
     * @return array Array asociativo con todos los registros
     */
    public function obtenerTodos(): array {
        $sql = "SELECT * FROM {$this->tabla}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un registro por su ID
     * 
     * @param int $id ID del registro
     * @return array|null Array con datos o null si no existe
     */
    public function obtenerPorId(int $id): ?array {
        $sql = "SELECT * FROM {$this->tabla} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado === false ? null : $resultado;
    }

    /**
     * Elimina un registro por su ID
     * 
     * @param int $id ID del registro a eliminar
     * @return bool True si se eliminó correctamente
     */
    public function eliminar(int $id): bool {
        $sql = "DELETE FROM {$this->tabla} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene el último ID insertado
     * 
     * @return string ID autoincremental generado
     */
    public function obtenerUltimoId(): string {
        return $this->db->lastInsertId();
    }
}
