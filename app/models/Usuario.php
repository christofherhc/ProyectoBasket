<?php

/**
 * Modelo Usuario - Gestión de usuarios
 */
class Usuario extends Model {
    protected string $tabla = 'usuarios';

    /**
     * Busca usuario por email
     */
    public function buscarPorEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->tabla} WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado === false ? null : $resultado;
    }

    /**
     * Verifica si email existe
     */
    public function existeEmail(string $email): bool {
        $usuario = $this->buscarPorEmail($email);
        return $usuario !== null;
    }

    /**
     * Crea nuevo usuario
     */
    public function crear(string $nombre, string $email, string $passwordHash): bool {
        $sql = "INSERT INTO {$this->tabla} (nombre, email, password) 
                VALUES (:nombre, :email, :password)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
        return $stmt->execute();
    }
}
