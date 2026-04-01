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

        $model = "gpt-4.1-mini-2025-04-14"; // el mismo que usas

        // ===== 3) Prompt =====
        $instrucciones = <<<EOT
Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
Recibirás dos insumos: (a) la Orden del Día y (b) la transcripción completa de la sesión.

Objetivo: Con esa información, genera un solo documento HTML5 válido que incluya Acta y Síntesis, con redacción institucional en tercera persona, en tiempo pasado y con el estilo narrativo estenográfico de las actas oficiales del Congreso (relato objetivo y cronológico de lo sucedido, sin opiniones ni inferencias).

Reglas de contenido y estilo (imprescindibles)
1.- Voz y tono:
Narrativa en tercera persona, formal, impersonal y objetiva.
-Ejemplos: “El Presidente de la Mesa Directiva declaró…”, “La Diputada Secretaria informó…”, “Se sometió a votación…”, “Se aprobó por unanimidad…”.
-Discursos y participaciones: transcribe íntegramente lo dicho en la intervención (no lo resumas), pero encuádrala dentro de la narrativa en tercera persona:
-Ejemplo: “El Diputado X hizo uso de la voz para manifestar: <blockquote>‘Texto textual de su intervención’</blockquote>”.

2.- Fidelidad a insumos:
-No inventes datos ni nombres.
-Si un dato no aparece, omítelo (no coloques placeholders).
-La primera vez, menciona nombre completo y cargo; posteriormente, usa “el Diputado/la Diputada + apellido”.

3.- Estructura del Acta (en secuencia del Orden del Día):
-Encabezado: tipo de sesión, fecha, hora de apertura y clausura (si constan), presidencia y secretarías.
-Asistencia y quórum, especifica si estan presente o no, en la transcripción generlamente despues de su nombre, ejemplo: "Claro que sí, presidente, con mucho gusto. Diputada María Esther Magadan Alonso "Presente", Diputado Eric Edgardo Quijano González "Presente", Diputado Francisco Rosas Villavicencio." .
-Orden del Día (si aparece).
-Desarrollo de la sesión: un <h2> por punto, en orden. Cada punto puede llevar subtítulos <h3> (“Discusión”, “Votación”, “Acuerdo/Decreto”).
-Clausura: consigna la declaratoria y hora, si aparecen.

4.- Votaciones (estandariza):
-Económica: “aprobado por unanimidad en votación económica” (u otra fórmula que conste).
-Nominal: “aprobado por mayoría con … votos a favor, … en contra y … omitidos”.
-Si aparecen números exactos, colócalos en tabla:| Tipo de votación | A favor | En contra | Omitidos | Resultado |
-Si solo consta “por unanimidad”, mantén esa forma.

5.- Fechas y horas:
-Fecha: “viernes 15 de diciembre de 2023”.
-Horario en formato 24 h: “11:41 h”, “14:14 h”.

6.- Síntesis (máx. 1500 palabras):
-Lenguaje narrativo estenográfico simple.
-En viñetas, destaca: acuerdos aprobados, dictámenes, decretos/reformas, designaciones o elecciones, votaciones (tipo y resultado), y clausura.
-Discursos: solo resumir el sentido general; si hay una cita relevante, usar <blockquote> con fragmento breve.

Devuelve un único bloque <!DOCTYPE html> completo con lang="es", que contenga un <article> con:
-<section id="acta"> — Acta completa (narrativa estenográfica, un <h2> por punto).
-<section id="sintesis"> — Síntesis breve en viñetas (máx. 1500 palabras).

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

    public function ver() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $id = isset($_GET['iIdTrans']) ? (int)$_GET['iIdTrans'] : 0;

    $html = '';
    if ($id > 0) {
        $db = new mysqli('localhost','root','','transcriptor');
        if (!$db->connect_error) {
            $db->set_charset('utf8mb4');
            $st = $db->prepare('SELECT tActaHtml FROM transcripciones WHERE iIdTrans=?');
            $st->bind_param('i', $id);
            $st->execute();
            $st->bind_result($html);
            $st->fetch();
            $st->close();
            $db->close();
        }
    }

    // Pásalo a una vista que solo dibuje el iframe con $html
    $pageTitle = 'Ver Acta';
    $view = __DIR__ . '/../views/transcripcion/acta_ver.php';
    $data = compact('pageTitle','html');
    require __DIR__ . '/../layout.php';
}


}
