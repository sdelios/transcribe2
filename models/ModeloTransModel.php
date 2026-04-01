<?php
class ModeloTransModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
    }

    public function obtenerActivos() {
        $resultado = $this->conn->query("SELECT iIdModeloTrans, cNombreModeloTrans FROM modelotrans WHERE iStatusModeloTrans = 1");
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerTodos() {
        $resultado = $this->conn->query("SELECT * FROM modelotrans ORDER BY iIdModeloTrans ASC");
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerPorId($id) {
        $stmt = $this->conn->prepare("SELECT * FROM modelotrans WHERE iIdModeloTrans = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }
}
