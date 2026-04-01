<?php
set_time_limit(5000);
require_once __DIR__ . '/../models/AudioModel.php';

class ProcesoController {
    private $modelo;

    public function __construct() {
        $this->modelo = new AudioModel();
    }

    public function formulario() {
        $view = __DIR__ . '/../views/proceso/formulario.php';
        include __DIR__ . '/../layout.php';
    }

    public function procesar() {
       header('Content-Type: application/json; charset=UTF-8');
        ob_start(); // buffer

        $url    = $_POST['url']    ?? '';
        $nombre = $_POST['nombre'] ?? '';

        if ($url === '' || $nombre === '') {
            ob_end_clean(); // limpia cualquier eco/notice previo
            echo json_encode(['error' => 'Faltan datos (url / nombre)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ---- RUTA DE SALIDA (relativa para DB, absoluta para Python) ----
        $nombreArchivo   = uniqid() . '.mp3';
        $rutaRelativa    = 'public/audios/' . $nombreArchivo;                  // se guarda en DB
        $rutaAbsoluta    = realpath(__DIR__ . '/..');                           // /.../transcribe2
        $rutaAbsoluta   .= DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa);

        // Asegura carpeta public/audios
        $dirAudios = dirname($rutaAbsoluta);
        if (!is_dir($dirAudios)) {
            @mkdir($dirAudios, 0777, true);
        }

        // ---- COMANDO PYTHON (sin carets, con escapeshellarg) ----
        $python = 'python -X utf8';  // o ruta completa a python.exe si lo prefieres
        $script = __DIR__ . '/../public/transcribe.py';

        $cmd = $python . ' ' .
               escapeshellarg($script) . ' ' .
               escapeshellarg($url)    . ' ' .
               escapeshellarg($rutaAbsoluta);

        // Ejecuta y captura salida y código de retorno
        $salida = [];
        $ret = 0;
        exec($cmd . ' 2>&1', $salida, $ret);
        $texto = trim(implode("\n", $salida));

        if ($ret !== 0 || $texto === '') {
            echo json_encode([
                'error'         => 'Falló la transcripción',
                'debug_comando' => $cmd,
                'detalle'       => $texto
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ---- Guarda audio en DB con la ruta RELATIVA ----
        // AudioModel::guardarAudio($nombre, $rutaRelativa, $url)
        $id_audio = $this->modelo->guardarAudio($nombre, $rutaRelativa, $url);

        echo json_encode([
            'id_audio'       => $id_audio,
            'transcripcion'  => $texto
        ], JSON_UNESCAPED_UNICODE);
    }

    public function transcribirArchivo() {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            // Validaciones básicas
            $nombre = trim($_POST['nombre_archivo'] ?? '');
            if ($nombre === '') throw new Exception('Falta el nombre del audio.');
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibió el archivo o hubo un error al subirlo.');
            }

            // Directorio destino (ajusta si tu estructura es distinta)
            $basePublic = realpath(__DIR__ . '/../public');
            if ($basePublic === false) throw new Exception('Ruta base inválida.');
            $dirAudios = $basePublic . DIRECTORY_SEPARATOR . 'audios';
            if (!is_dir($dirAudios) && !mkdir($dirAudios, 0775, true)) {
                throw new Exception('No se pudo crear el directorio de audios.');
            }

            // Renombrado y copia
            $ext = '.mp3'; // si quieres convertir, aquí siempre dejamos mp3
            $nombreFinal = bin2hex(random_bytes(6)) . $ext;
            $rutaFs = $dirAudios . DIRECTORY_SEPARATOR . $nombreFinal; // ruta en disco
            if (!move_uploaded_file($_FILES['audio']['tmp_name'], $rutaFs)) {
                throw new Exception('No se pudo guardar el archivo subido.');
            }

            // URL o ruta pública para BD (ajústalo a tu despliegue)
            $rutaWeb = 'public/audios/' . $nombreFinal;

            // Guardar en tabla audios (usa tu AudioModel)
            require_once __DIR__ . '/../models/AudioModel.php';
            require_once __DIR__ . '/../models/TranscripcionModel.php';
            $audioModel = new AudioModel();
            $transModel = new TranscripcionModel();

            $iIdAudio = $audioModel->guardarAudio([
                'cNameAudio' => $nombre,
                'ruta'       => $rutaWeb,
                'status'     => 0,
                'cLink'      => 'Carga de archivo de audio',
            ]);

            if (!$iIdAudio) throw new Exception('No se pudo guardar el registro de audio.');

            // Ejecutar Whisper (transcribe.py) en modo local (sin yt-dlp)
            $python = 'python -X utf8'; // en tu Windows/XAMPP esto te funcionó
            $script = realpath(__DIR__ . '/../public/transcribe.py'); // ajusta si está en otra ruta
            if ($script === false) throw new Exception('No se encontró transcribe.py.');

            // Modo local: --mode=local  (ver cambio en el punto 3)
            $cmd = sprintf(
                '%s %s %s %s %s',
                $python,
                escapeshellarg($script),
                escapeshellarg('local'),             // arg1: “local” (antes era url)
                escapeshellarg($rutaFs),             // arg2: ruta al mp3 ya guardado
                escapeshellarg('--mode=local')       // flag para saltar la descarga
            );

            // Ejecutar y capturar salida
            $output = [];
            $code = 0;
            exec($cmd . ' 2>&1', $output, $code);
            if ($code !== 0) {
                throw new Exception("Error al transcribir: " . implode("\n", $output));
            }
            $texto = trim(implode("\n", $output));

            // Guardar transcripción
            $okTrans = $transModel->guardarTranscripcion(
                $nombre,
                date('Y-m-d'),
                $rutaWeb,              // aquí va la liga/ruta del audio
                $texto,
                $iIdAudio,
                /* idModelo si tu método lo pide; ajusta firmas según tu código */
            );

            if (!$okTrans) throw new Exception('No se pudo guardar la transcripción.');

            // Actualiza status si así lo usas
            // $audioModel->actualizarStatus($iIdAudio, 1);

            echo json_encode(['ok' => true, 'iIdAudio' => $iIdAudio]);
           } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
              }
    }

    public function procesarArchivo() {
            header('Content-Type: application/json; charset=UTF-8');
            ob_start(); // evita que notices rompan el JSON

            try {
                // 1) Validar entrada
                if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('No se recibió el archivo o hubo un error al subirlo.');
                }
                $titulo = trim($_POST['nombre_archivo'] ?? '');
                if ($titulo === '') throw new Exception('Falta el nombre del audio.');

                // 2) Directorio destino
                $basePublic = realpath(__DIR__ . '/../public');
                if ($basePublic === false) throw new Exception('Ruta base inválida.');
                $dirAudios = $basePublic . DIRECTORY_SEPARATOR . 'audios';
                if (!is_dir($dirAudios) && !mkdir($dirAudios, 0775, true)) {
                    throw new Exception('No se pudo crear el directorio de audios.');
                }

                // 3) Renombrar y mover
                $ext = '.mp3'; // si quieres mantener la extensión real: $ext = '.' . strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
                $nombreFinal = bin2hex(random_bytes(6)) . $ext;
                $rutaFs  = $dirAudios . DIRECTORY_SEPARATOR . $nombreFinal;   // ruta física
                $rutaWeb = 'public/audios/' . $nombreFinal;                    // ruta pública para BD

                if (!move_uploaded_file($_FILES['audio']['tmp_name'], $rutaFs)) {
                    throw new Exception('No se pudo guardar el archivo subido.');
                }

                // 4) Guardar registro en tabla audios con compatibilidad de firma
                require_once __DIR__ . '/../models/AudioModel.php';
                require_once __DIR__ . '/../models/TranscripcionModel.php';
                $audioModel = new AudioModel();
                $transModel = new TranscripcionModel();

                // soporte ambas firmas: guardarAudio(array $data)  ó  guardarAudio($cNameAudio, $ruta, $status, $cLink)
                $rm = new ReflectionMethod($audioModel, 'guardarAudio');
                $paramCount = $rm->getNumberOfParameters();

                if ($paramCount === 1) {
                    // firma tipo arreglo
                    $iIdAudio = $audioModel->guardarAudio([
                        'cNameAudio' => $titulo,
                        'ruta'       => $rutaWeb,
                        'status'     => 0,
                        'cLink'      => 'Carga de archivo de audio',
                    ]);
                } else {
                    // firma tradicional (ajusta el orden si tu método es distinto)
                    // ejemplo esperado: guardarAudio($cNameAudio, $ruta, $status, $cLink)
                    $iIdAudio = $audioModel->guardarAudio($titulo, $rutaWeb, 0, 'Carga de archivo de audio');
                }

                if (!$iIdAudio) throw new Exception('No se pudo guardar el registro de audio.');

                // 5) Ejecutar Whisper en modo local (NO descarga; transcribe archivo ya copiado)
                $python = 'python -X utf8'; // en Windows/XAMPP
                $script = realpath(__DIR__ . '/../public/transcribe.py');
                if ($script === false) throw new Exception('No se encontró transcribe.py.');

                // transcribe.py local "<rutaFs>"
                $cmd = sprintf(
                    '%s %s %s %s',
                    $python,
                    escapeshellarg($script),
                    escapeshellarg('local'),
                    escapeshellarg($rutaFs)
                );

                $salida = [];
                $code = 0;
                exec($cmd . ' 2>&1', $salida, $code);
                if ($code !== 0) {
                    throw new Exception("Error al transcribir: " . implode("\n", $salida));
                }
                $texto = trim(implode("\n", $salida));

                // 6) Guardar transcripción (ajusta la firma a tu TranscripcionModel)
                // Ejemplo: guardarTranscripcion($titulo, $fecha, $link, $texto, $iIdAudio, $iIdModeloTrans)
                $okTrans = $transModel->guardarTranscripcion(
                    $titulo,
                    date('Y-m-d'),
                    $rutaWeb,
                    $texto,
                    $iIdAudio,
                    2 // id del modelo usado, si aplica
                );
                if (!$okTrans) throw new Exception('No se pudo guardar la transcripción.');

                ob_end_clean();
                echo json_encode([
                    'ok'            => true,
                    'id_audio'      => $iIdAudio,
                    'ruta'          => $rutaWeb,
                    'titulo'        => $titulo,
                    'transcripcion' => $texto,
                    'tiempo'        => null
                ], JSON_UNESCAPED_UNICODE);
                return;

            } catch (Exception $e) {
                ob_end_clean();
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                return;
            }
    }

      public function transcribir() {
            header('Content-Type: application/json; charset=UTF-8');
            ob_start();
            try {
                $url    = $_POST['url']    ?? '';
                $nombre = $_POST['nombre'] ?? '';

                if (!$url || !$nombre) {
                    throw new Exception('Faltan datos: url y nombre son requeridos.');
                }

                // --------- 1) Construir ruta LOCAL destino .mp3 -----------
                // Carpeta audios dentro de /public (ajústala a la que ya uses)
                $baseDirAbs = realpath(__DIR__ . '/../public');
                if ($baseDirAbs === false) {
                    throw new Exception('No se encontró la carpeta /public.');
                }
                $audiosDirAbs = $baseDirAbs . DIRECTORY_SEPARATOR . 'audios';
                if (!is_dir($audiosDirAbs)) {
                    @mkdir($audiosDirAbs, 0777, true);
                }

                // Nombre de archivo limpio y único
                $slug = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', trim($nombre));
                if ($slug === '') $slug = 'audio';
                $fileName   = $slug . '_' . date('Ymd_His') . '.mp3';
                $rutaAbs    = $audiosDirAbs . DIRECTORY_SEPARATOR . $fileName;        // RUTA LOCAL (ABSOLUTA)
                $rutaWeb    = 'public/audios/' . $fileName;                            // Ruta “web” (para guardar en DB)
                $cLink      = $url;                                                    // Enlace de origen

                // --------- 2) Llamar a Python: transcribe.py URL RUTA_LOCAL -----------
                $python = 'python -X utf8'; // o 'py -3' en Windows si te conviene
                $script = realpath(__DIR__ . '/../public/transcribe.py');
                if ($script === false) {
                    throw new Exception('No se encontró transcribe.py.');
                }

                // Pasamos URL y ruta ABSOLUTA local
                $cmd = sprintf('%s %s %s %s',
                    $python,
                    escapeshellarg($script),
                    escapeshellarg($url),
                    escapeshellarg($rutaAbs)
                );

                $salida = [];
                $code   = 0;
                exec($cmd . ' 2>&1', $salida, $code);
                if ($code !== 0) {
                    throw new Exception("Error al transcribir:\n" . implode("\n", $salida));
                }
                $texto = trim(implode("\n", $salida));

                // --------- 3) Guardar en la base de datos -----------
                require_once __DIR__ . '/../models/AudioModel.php';
                require_once __DIR__ . '/../models/TranscripcionModel.php';
                $audioModel = new AudioModel();
                $transModel = new TranscripcionModel();

                $iIdAudio = $audioModel->guardarAudio($nombre, $rutaWeb, $cLink);

                // Modelo 2 (Whisper) o el que corresponda
                $okTrans = $transModel->guardarTranscripcion(
                    $nombre,
                    date('Y-m-d'),
                    $rutaWeb,
                    $texto,
                    (int)$iIdAudio,
                    2
                );
                if (!$okTrans) throw new Exception('No se pudo guardar la transcripción.');

                ob_end_clean();
                echo json_encode([
                    'ok'            => true,
                    'id_audio'      => $iIdAudio,
                    'ruta'          => $rutaWeb,     // dónde quedó tu .mp3
                    'titulo'        => $nombre,
                    'transcripcion' => $texto
                ], JSON_UNESCAPED_UNICODE);
                return;

            } catch (Exception $e) {
                ob_end_clean();
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                return;
            }
     }




}
