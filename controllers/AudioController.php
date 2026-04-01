<?php
set_time_limit(1800);
require_once __DIR__ . '/../models/AudioModel.php';
require_once __DIR__ . '/../models/TranscripcionModel.php';

class AudioController {
    private $modelo;

    public function __construct() {
        $this->modelo = new AudioModel();
    }

    public function lista() {
        $audios = $this->modelo->obtenerTodosConTranscripciones();

        // Obtener modelos activos
        $conn = new mysqli("localhost", "root", "", "transcriptor");
        $modelos = $conn->query("SELECT iIdModeloTrans, cNombreModeloTrans FROM modelotrans WHERE iStatusModeloTrans = 1")->fetch_all(MYSQLI_ASSOC);

        $view = __DIR__ . '/../views/audio/lista.php';
        include __DIR__ . '/../layout.php';
    }

    public function descargar() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit('ID no proporcionado');

        $audio = $this->modelo->obtenerPorId($id);
        if (!$audio || !file_exists($audio['cRuta'])) exit('Archivo no encontrado');

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($audio['cRuta']) . '"');
        header('Content-Length: ' . filesize($audio['cRuta']));
        readfile($audio['cRuta']);
        exit;
    }

public function detalle() {
    $idAudio = $_GET['idAudio'] ?? null;
    if (!$idAudio) {
        echo "ID de audio no proporcionado.";
        return;
    }

    require_once __DIR__ . '/../models/AudioModel.php';
    require_once __DIR__ . '/../models/TranscripcionModel.php';

    $modeloAudio = new AudioModel();
    $modeloTrans = new TranscripcionModel();

    $audio = $modeloAudio->obtenerAudioConTranscripciones($idAudio);
    $modelos = $modeloTrans->obtenerModelos();

    $view = __DIR__ . '/../views/audio/detalle.php'; // ✅ define $view
    include __DIR__ . '/../layout.php';
}

}
