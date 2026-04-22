<?php
set_time_limit(1800);
require_once __DIR__ . '/../models/AudioModel.php';
require_once __DIR__ . '/../models/TranscripcionModel.php';

class AudioController {
    private $modelo;

    public function __construct() {
        $this->modelo = new AudioModel();
    }

    private function requireAdmin(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ((int)($_SESSION['auth']['iTipo'] ?? 0) !== 1) {
            http_response_code(403);
            exit('<p style="font-family:sans-serif;padding:2rem;color:#c00">Acceso denegado. Solo administradores pueden ver esta sección.</p>');
        }
    }

    public function lista() {
        $this->requireAdmin();
        $audios = $this->modelo->obtenerTodosConTranscripciones();

        // Obtener modelos activos
        $conn = new mysqli("localhost", "root", "", "transcriptor");
        $modelos = $conn->query("SELECT iIdModeloTrans, cNombreModeloTrans FROM modelotrans WHERE iStatusModeloTrans = 1")->fetch_all(MYSQLI_ASSOC);

        $view = __DIR__ . '/../views/audio/lista.php';
        include __DIR__ . '/../layout.php';
    }

    public function descargar() {
        $this->requireAdmin();
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
    $this->requireAdmin();
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
