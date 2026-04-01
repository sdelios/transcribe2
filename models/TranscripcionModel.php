<?php
class TranscripcionModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
    }

// public function guardarTranscripcion($titulo, $fecha, $link, $texto, $idAudio, $modelo = 2) {
//     $stmt = $this->conn->prepare("INSERT INTO transcripciones (cTituloTrans, dFechaTrans, cLinkTrans, tTrans, iIdAudio, iIdModeloTrans) VALUES (?, ?, ?, ?, ?, ?)");
//     $stmt->bind_param("ssssii", $titulo, $fecha, $link, $texto, $idAudio, $modelo);
//     $stmt->execute();
//     $id = $stmt->insert_id;
//     $stmt->close();
//     return $id;
// }

public function guardarTranscripcion($titulo, $fecha, $link, $texto, $idAudio, $modelo = 2) {
    $stmt = $this->conn->prepare("INSERT INTO transcripciones (cTituloTrans, dFechaTrans, cLinkTrans, tTrans, iIdAudio, iIdModeloTrans) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Asegurar que los valores null sean interpretados correctamente
    $titulo = $titulo ?: null;
    $fecha = $fecha ?: null;

    $stmt->bind_param("ssssii", $titulo, $fecha, $link, $texto, $idAudio, $modelo);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}



    public function obtenerTodas() {
        $resultado = $this->conn->query("SELECT t.*, m.cNombreModeloTrans FROM transcripciones t LEFT JOIN modelotrans m ON t.iIdModeloTrans = m.iIdModeloTrans ORDER BY iIdTrans DESC");
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerPorId($id) {
        $stmt = $this->conn->prepare("SELECT * FROM transcripciones WHERE iIdTrans = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function actualizarTranscripcion($id, $titulo, $fecha, $link, $texto, $idModelo) {
        $stmt = $this->conn->prepare("UPDATE transcripciones SET cTituloTrans=?, dFechaTrans=?, cLinkTrans=?, tTrans=?, iIdModeloTrans=? WHERE iIdTrans=?");
        $stmt->bind_param("ssssii", $titulo, $fecha, $link, $texto, $idModelo, $id);
        return $stmt->execute();
    }

    public function obtenerModelos() {
        $result = $this->conn->query("SELECT * FROM modelotrans");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function tieneCorreccion($idTrans) {
    $sql = "SELECT id FROM correcciones WHERE transcripcion_id = ? LIMIT 1";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("i", $idTrans);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc(); // devuelve null si no existe
    }


}

