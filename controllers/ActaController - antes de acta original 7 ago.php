<?php
// controllers/ActaController.php
class ActaController
{
    // Si algún día quieres abrir la vista por esta ruta:
//  public function index() {
//      if (session_status() === PHP_SESSION_NONE) session_start();
//      $pageTitle = 'Generador de Acta y Síntesis';
//      $view = __DIR__ . '/../views/transcripcion/acta.php';
//      $data = compact('pageTitle');
//      require __DIR__ . '/../layout.php';
//  }

    public function procesar() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // ===== 1) Validación de entrada =====
        $orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
        $trans = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';

        $_SESSION['ultimo_orden'] = $orden;
        $_SESSION['ultimo_transcripcion'] = $trans;

        if ($orden === '' || $trans === '') {
            $_SESSION['error_msg'] = "Faltan datos: asegúrate de pegar la Orden del Día y la Transcripción.";
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        // ===== 2) Configuración =====
        // ⚠️ Mejor usar variable de entorno: setx OPENAI_API_KEY "sk-xxxx" (Windows) o export en Linux/macOS
       $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            // Si quisieras, aquí podrías poner un fallback hardcodeado solo para pruebas locales:
            // $apiKey = 'sk-...';
        }
        if (!$apiKey) {
            $_SESSION['error_msg'] = "No se encontró OPENAI_API_KEY en el entorno.";
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

       // $model = "gpt-4.1-mini-2025-04-14"; // el mismo que usas es mas barato pero tiende a resumir
        $model = "gpt-4.1-2025-04-14"; // el mismo que usas es mas caro pero con mejor fidelidad

        // ===== 3) Prompt =====
        $instrucciones = <<<EOT
            Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
            Recibirás dos insumos: (a) la Orden del Día y (b) la transcripción completa de la sesión.

            Objetivo: Con esa información, genera un solo documento HTML5 válido que incluya Acta y Síntesis, con redacción institucional en tercera persona, en tiempo pasado y con el estilo narrativo estenográfico de las actas oficiales del Congreso (relato objetivo y cronológico de lo sucedido).

            Reglas de contenido y estilo (imprescindibles)
            1.- Voz y tono:
            Narrativa estenográfica en tercera persona, formal, impersonal y objetiva.
            -Ejemplos: “El Presidente de la Mesa Directiva declaró…”, “La Diputada Secretaria informó…”, “Se sometió a votación…”, “Se aprobó por unanimidad…”.
            -Discursos y participaciones: transcribe íntegramente lo dicho en la intervención (no lo resumas), pero encuádrala dentro de la narrativa en tercera persona:
            -Ejemplo: “El Diputado X hizo uso de la voz para manifestar: <blockquote>‘Texto textual de su intervención’</blockquote>”.

            2.- Fidelidad a insumos:
            -No inventes datos ni nombres.
            -Si un dato no aparece, omítelo (no coloques placeholders).
            -La primera vez, menciona nombre completo y cargo; posteriormente, usa “el Diputado/la Diputada + apellido”.

            3.- Estructura del Acta (en secuencia del Orden del Día):
            -Encabezado: tipo de sesión, fecha, hora de apertura y clausura (si constan en la transcripción), nombre de quien preside y secretarías.
            -Asistencia y quórum: detallar la lista de diputadas y diputados conforme aparezca en la transcripción, indicando si están “Presentes” o no, y consignar la declaratoria de quórum.
            -Orden del Día: transcribirlo si aparece.
            -Desarrollo de la Sesión:
                +Utilizar narrativa estenográfica en tercera persona, objetiva e impersonal, siguiendo el estilo oficial del Congreso.
                +Redactar cada punto del Orden del Día en un bloque con título <h2>.
                +Dentro de cada punto, si hay fases diferenciadas, usar subtítulos <h3> para “Discusión”, “Votación”, “Acuerdo/Decreto”, según corresponda.
                +Mantener la redacción fiel al desarrollo de la sesión (por ejemplo: “Se sometió a votación…”, “Se aprobó por unanimidad…”, “El Diputado X hizo uso de la palabra…”).
            -Clausura: consignar la declaratoria de clausura y la hora, si aparece en la transcripción.

            4.- Votaciones (estandariza):
            -Económica: “aprobado por unanimidad en votación económica” (u otra fórmula que conste).
            -Nominal: “aprobado por mayoría con … votos a favor, … en contra y … omitidos”.
            -Si aparecen números exactos, colócalos en tabla:| Tipo de votación | A favor | En contra | Omitidos | Resultado |
            -Si solo consta “por unanimidad”, mantén esa forma.

            5.- Fechas y horas:
            -Fecha: “viernes 15 de diciembre de 2023”.
            -Horario en formato 24 h: “11:41 h”, “14:14 h”.

            6.- Síntesis (máx. 3000 palabras):
            -Lenguaje narrativo estenográfico simple.
            -Destaca: acuerdos aprobados, dictámenes, decretos/reformas, designaciones o elecciones, votaciones (tipo y resultado), y clausura.
            -Discursos: solo resumir el sentido general; si hay una cita relevante, usar <blockquote> con fragmento breve.

            Devuelve un único bloque <!DOCTYPE html> completo con lang="es", que contenga un <article> con:
            -<section id="acta"> — Acta completa (narrativa estenográfica, un <h2> por punto).
            -<section id="sintesis"> — Síntesis breve con narrativa estenografica simple resumida (máx. 3000 palabras).

            CSS mínimo embebido (ajústalo sin usar frameworks): tipografía legible, márgenes y tablas limpias, estilos discretos para <blockquote> y listas.
            EOT;

        $inputUsuario = "ORDEN DEL DÍA:\n" . $orden . "\n\nTRANSCRIPCIÓN COMPLETA:\n" . $trans;

        // ===== 4) JSON Schema para asegurar HTML (text.format) =====
        $schema = [
            "type" => "object",
            "additionalProperties" => false,
            "properties" => [
                "html" => [
                    "type" => "string",
                    "description" => "HTML completo (empieza con <!DOCTYPE html> y contiene <article> con #acta y #sintesis)."
                ]
            ],
            "required" => ["html"]
        ];

        // ===== 5) Payload Responses API =====
        $payload = [
            "model" => $model,
            "input" => [
                ["role" => "system", "content" => $instrucciones],
                ["role" => "user",   "content" => $inputUsuario],
            ],
            "text" => [
                "format" => [
                    "type"   => "json_schema",
                    "name"   => "html_acta_sintesis",
                    "schema" => $schema
                ]
            ],
            "max_output_tokens" => 4096
        ];

        // ===== 6) cURL =====
        $ch = curl_init("https://api.openai.com/v1/responses");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // ===== 7) Manejo de errores de red =====
        if ($response === false) {
            $_SESSION['error_msg'] = "Error de red al llamar a la API: $curlErr";
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        // ===== 8) Decodificar y extraer texto =====
        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $_SESSION['error_msg'] = "Error HTTP $httpCode:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        // output_text (cómodo) o fallback a la estructura interna
        $texto = $data['output_text'] ?? ($data['output'][0]['content'][0]['text'] ?? '');

        $htmlFinal = '';
        if ($texto !== '') {
            $maybeJson = json_decode($texto, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($maybeJson['html'])) {
                $htmlFinal = $maybeJson['html'];
            } else {
                // Si no vino en JSON, asumimos que ya es HTML
                $htmlFinal = $texto;
            }
        }

        if ($htmlFinal === '') {
            $_SESSION['error_msg'] = "La API respondió, pero no se pudo extraer el HTML.\nRespuesta parcial:\n" . substr($texto, 0, 600);
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        // ===== 9) Guardar en sesión y redirigir =====
        $_SESSION['resultado_html'] = $htmlFinal;
        unset($_SESSION['error_msg']);
        header("Location: index.php?ruta=transcripcion/acta#resultado");
        exit;
    }

    public function guardar() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $id  = isset($_POST['iIdTrans']) ? (int)$_POST['iIdTrans'] : 0;
    $html = $_POST['tActaHtml'] ?? '';

    if ($id <= 0 || trim($html) === '') {
        $_SESSION['error_msg'] = 'Faltan datos para guardar el acta (id o contenido).';
        header('Location: index.php?ruta=transcripcion/acta#resultado');
        exit;
    }

    // Conexión (ajusta si usas otra configuración)
    $mysqli = new mysqli('localhost', 'root', '', 'transcriptor');
    if ($mysqli->connect_error) {
        $_SESSION['error_msg'] = 'Error de conexión: ' . $mysqli->connect_error;
        header('Location: index.php?ruta=transcripcion/acta#resultado');
        exit;
    }
    $mysqli->set_charset('utf8mb4');

    $stmt = $mysqli->prepare('UPDATE transcripciones SET tActaHtml = ?, dActaGenerada = NOW() WHERE iIdTrans = ?');
    if (!$stmt) {
        $_SESSION['error_msg'] = 'Error al preparar la consulta.';
        header('Location: index.php?ruta=transcripcion/acta#resultado');
        exit;
    }
    $stmt->bind_param('si', $html, $id);
    $ok = $stmt->execute();
    $stmt->close();
    $mysqli->close();

    if (!$ok) {
        $_SESSION['error_msg'] = 'No se pudo guardar el acta.';
    } else {
        $_SESSION['success_msg'] = 'Acta guardada correctamente.';
        // Mantén el último HTML en sesión para volver a mostrarlo si quieres:
        $_SESSION['resultado_html'] = $html;
    }

    header('Location: index.php?ruta=transcripcion/acta#resultado');
    exit;
}

}
