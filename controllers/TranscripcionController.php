<?php
set_time_limit(5000);
require_once __DIR__ . '/../models/TranscripcionModel.php';

class TranscripcionController {
    private $modelo;

    public function __construct() {
        $this->modelo = new TranscripcionModel();
    }

    public function lista() {
        $transcripciones = $this->modelo->obtenerTodas();
        $view = __DIR__ . '/../views/transcripcion/lista.php';
        include __DIR__ . '/../layout.php';
    }

    public function guardar() {
        // Datos del POST
        $titulo    = $_POST['cTituloTrans'] ?? '';
        $fecha     = $_POST['dFechaTrans'] ?? null;  // puede venir null
        $link      = $_POST['cLinkTrans']   ?? '';
        $texto     = $_POST['tTrans']       ?? '';
        $iIdAudio  = isset($_POST['iIdAudio']) ? (int)$_POST['iIdAudio'] : 0;
        $iIdModelo = isset($_POST['iIdModeloTrans']) ? (int)$_POST['iIdModeloTrans'] : 0;

        // Upsert para evitar duplicados
        $okId = $this->upsertTranscripcion($iIdAudio, $iIdModelo, $titulo, $fecha, $link, $texto);

        header("Location: index.php?ruta=transcripcion/lista");
        exit;
    }

    public function editar() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit('ID no proporcionado');

        $transcripcion = $this->modelo->obtenerPorId($id);

        // Obtener modelos disponibles
        $conn = new mysqli("localhost", "root", "", "transcriptor");
        $modelos = $conn->query("SELECT iIdModeloTrans, cNombreModeloTrans FROM modelotrans WHERE iStatusModeloTrans = 1")->fetch_all(MYSQLI_ASSOC);

         // NUEVO: verificar si existe corrección
         $tieneCorreccion = $this->modelo->tieneCorreccion($id) ? true : false;

        $view = __DIR__ . '/../views/transcripcion/editar.php';
        include __DIR__ . '/../layout.php';

        
    }

    public function actualizar() {
        $id       = $_POST['iIdTrans'];
        $titulo   = $_POST['cTituloTrans'];
        $fecha    = $_POST['dFechaTrans'];
        $link     = $_POST['cLinkTrans'];
        $texto    = $_POST['tTrans'];
        $idModelo = $_POST['iIdModeloTrans'];

        $this->modelo->actualizarTranscripcion($id, $titulo, $fecha, $link, $texto, $idModelo);
        header("Location: index.php?ruta=transcripcion/lista");
        exit;
    }

    public function revisarTexto() {
        $texto = $_POST['texto'] ?? '';

        if (empty($texto)) {
            echo json_encode(['error' => 'Texto vacío']);
            return;
        }

        // Crear archivo temporal con el texto
        $rutaTemporal = __DIR__ . '/../public/temp_texto.txt';
        file_put_contents($rutaTemporal, mb_convert_encoding($texto, 'UTF-8'), LOCK_EX);

        // Ejecutar Python usando ese archivo
        $comando = "python public/corregir.py \"$rutaTemporal\" 2>&1";
        $salida = shell_exec($comando);

        if (!$salida) {
            echo json_encode(['error' => 'No se obtuvo respuesta del corrector.']);
            return;
        }

        echo $salida;
    }

    public function generarModelo() {
        $ruta         = $_POST['ruta'] ?? '';
        $modelo       = $_POST['modelo'] ?? '';
        $iIdAudio     = isset($_POST['idAudio'])  ? (int)$_POST['idAudio']  : 0;
        $iIdModelo    = isset($_POST['idModelo']) ? (int)$_POST['idModelo'] : 0;
        $cLinkTrans   = $_POST['cLink'] ?? '';

        if (!$ruta || !$modelo || !$iIdAudio || !$iIdModelo || !$cLinkTrans) {
            echo json_encode(['error' => 'Faltan datos requeridos.']);
            return;
        }

        // Ejecutar script Python con redirección de errores
        $cmd = "python public/transcribir_modelo.py " . escapeshellarg($ruta) . " " . escapeshellarg($modelo) . " 2>&1";
        $output = shell_exec($cmd);

        if (!$output) {
            echo json_encode(['error' => 'La transcripción no generó contenido.', 'debug_comando' => $cmd]);
            return;
        }

        // Guardar (idempotente) en base de datos: evita duplicado (iIdAudio, iIdModelo)
        $titulo = 'Poner el titulo de la transcrión';
        $fecha  = null; // si quieres, coloca date('Y-m-d')
        $texto  = strip_tags($output);

        $idTrans = $this->upsertTranscripcion($iIdAudio, $iIdModelo, $titulo, $fecha, $cLinkTrans, $texto);

        echo json_encode(['transcripcion' => $output, 'iIdTrans' => $idTrans]);
    }

    public function acta() {
        // Datos a pasar a la vista
        $texto = $_POST['tTrans'] ?? '';
        $pageTitle = 'Generar Acta';

        // **RUTA ABSOLUTA** al archivo de vista
        $view = __DIR__ . '/../views/transcripcion/acta.php';

        // Paquete de datos que la vista necesitará
        $data = compact('texto', 'pageTitle');

        // Render con layout
        require __DIR__ . '/../layout.php';
    }

    /* ============================================================
     * Upsert idempotente para evitar duplicados por (audio, modelo)
     * ============================================================ */
    private function upsertTranscripcion(int $iIdAudio, int $iIdModeloTrans, string $titulo, ?string $fecha, string $link, string $texto) {
        $conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($conn->connect_error) {
            // Si falla conexión, como fallback intentamos insertar con el modelo original
            return $this->modelo->guardarTranscripcion($titulo, $fecha, $link, $texto, $iIdAudio, $iIdModeloTrans);
        }

        // 1) ¿ya existe para (audio, modelo)?
        $stmt = $conn->prepare("SELECT iIdTrans FROM transcripciones WHERE iIdAudio = ? AND iIdModeloTrans = ? LIMIT 1");
        $stmt->bind_param("ii", $iIdAudio, $iIdModeloTrans);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            // 2) Actualiza (idempotente)
            $idExistente = (int)$row['iIdTrans'];
            $upd = $conn->prepare(
                "UPDATE transcripciones 
                 SET cTituloTrans = ?, dFechaTrans = ?, cLinkTrans = ?, tTrans = ?, iIdModeloTrans = ?
                 WHERE iIdTrans = ?"
            );
            // dFechaTrans puede ser null
            $upd->bind_param("ssssii", $titulo, $fecha, $link, $texto, $iIdModeloTrans, $idExistente);
            $ok = $upd->execute();
            $upd->close();
            $conn->close();
            return $ok ? $idExistente : false;
        } else {
            // 3) Inserta (utiliza tu modelo)
            $conn->close();
            return $this->modelo->guardarTranscripcion($titulo, $fecha, $link, $texto, $iIdAudio, $iIdModeloTrans);
        }
    }


}
