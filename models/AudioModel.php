<?php
class AudioModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
    }

    public function guardarAudio($nombre, $ruta, $link) {
        $stmt = $this->conn->prepare("INSERT INTO audios (cNameAudio, cRuta, cLink, iStatus) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $nombre, $ruta, $link);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function obtenerTodos() {
        $resultado = $this->conn->query("SELECT * FROM audios ORDER BY iIDAudio DESC");
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerPorId($id) {
        $stmt = $this->conn->prepare("SELECT * FROM audios WHERE iIDAudio = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $resultado;
    }

public function obtenerTodosConTranscripciones() {
    $audios = $this->conn->query("SELECT * FROM audios ORDER BY iIDAudio DESC")->fetch_all(MYSQLI_ASSOC);

    foreach ($audios as &$audio) {
        $id = $audio['iIDAudio'];

        // Buscar transcripciones asociadas
        $query = "SELECT t.iIdModeloTrans, m.cNombreModeloTrans 
                  FROM transcripciones t
                  JOIN modelotrans m ON t.iIdModeloTrans = m.iIdModeloTrans
                  WHERE t.iIDAudio = $id";
        $res = $this->conn->query($query);
        $audio['transcripciones'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $audio['diarizado'] = false;
    }

    return $audios;
}


    public function obtenerAudioConTranscripciones($idAudio) {
        $stmt = $this->conn->prepare("SELECT * FROM audios WHERE iIdAudio = ?");
        $stmt->bind_param("i", $idAudio);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $audio = $resultado->fetch_assoc();
        $stmt->close();

        $stmt = $this->conn->prepare("SELECT * FROM transcripciones WHERE iIdAudio = ?");
        $stmt->bind_param("i", $idAudio);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $audio['transcripciones'] = $resultado->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $audio;
    }
}
