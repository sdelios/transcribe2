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
       $apiKey = getenv('OPENAI_API_KEY');
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

            Reglas de contenido y estilo (imprescindibles):  

            1. Voz y tono:  
            - Narrativa estenográfica en tercera persona, formal, solemne, impersonal y objetiva.  
            - Ejemplos: “El Presidente de la Mesa Directiva declaró…”, “La Diputada Secretaria informó…”, “Se sometió a votación…”, “Se aprobó por unanimidad…”.  
            - No utilizar primera persona ni discursos íntegros.  

            2. Fidelidad a insumos:  
            - No inventes datos ni nombres, es posible encontrar errores en los nombres de los participantes en la transcripción pero basate en los de pase de lista. 
            - Si un dato no aparece, señala entre parentesis el dato que debe ir, ejemplo: si hay una intervención diran "Con que objeto diputado" en caso de no tener nombre poner ahi la señal entre parentesis.  
            - La primera vez, menciona nombre completo y cargo; posteriormente, usa “el Diputado/la Diputada + apellido”.  

            3. Estructura del Acta (seguir el Orden del Día):  
            - **Encabezado solemne**: en mayúsculas, con la fórmula oficial. Ejemplo:  
                *“ACTA DE LA SESIÓN DE TRABAJO DE LA COMISIÓN PERMANENTE DE VIGILANCIA DE LA CUENTA PÚBLICA, TRANSPARENCIA Y ANTICORRUPCIÓN, DEL HONORABLE CONGRESO DEL ESTADO DE YUCATÁN, DE FECHA… DE … DEL AÑO …”*.  
                Incluir lugar, fecha en letras, hora de inicio, presidencia y secretarías.  
            - **Asistencia y quórum**: listar diputadas y diputados en el orden de aparición, indicando “Presente” o “Se justificó la inasistencia”. Finalizar con la declaratoria de quórum.  
            - **Orden del Día**: transcribirlo si aparece.  
            - **Desarrollo de la Sesión**:  
                + Un `<h2>` por cada punto del Orden del Día.  
                + Subtítulos `<h3>` solo cuando existan fases diferenciadas (“Discusión”, “Votación”, “Acuerdo/Decreto”).  
                + Redactar siempre en tercera persona, sin juicios de valor.  
                + Si un legislador interviene, introduce con narrativa (“En el uso de la palabra, el Diputado X manifestó…”) y, solo si es necesario, coloca un fragmento breve relevante dentro de `<blockquote>`. Nunca transcribir discursos íntegros.  
            - **Clausura**: consignar la declaratoria solemne: *“No habiendo más asuntos que tratar, la Presidencia clausuró formalmente la sesión, siendo las … horas … del día …”*.  

            4. Votaciones (estandariza):  
            - Económica: “aprobado por unanimidad en votación económica”.  
            - Nominal: “aprobado por mayoría con … votos a favor, … en contra y … omitidos”.  
            - Si aparecen números exactos, presentarlos en tabla:  
                | Tipo de votación | A favor | En contra | Omitidos | Resultado |  

            5. Fechas y horas:  
            - Fecha en letras: “viernes 15 de diciembre de 2023”.  
            - Horario en formato 24 hrs: “11:41 hrs”, “14:14 hrs”.  

            6. Síntesis (máx. 3000 palabras):  
            - Incluir en `<section id="sintesis">`.  
            - Lenguaje narrativo estenográfico simple, en tercera persona.  
            - Resumir acuerdos aprobados, dictámenes, decretos, designaciones, votaciones y clausura.  
            - Intervenciones: solo resumir el sentido general; si hay una cita relevante, incluirla breve dentro de `<blockquote>`.  
            - No usar expresiones subjetivas (“preocupante”, “llamó la atención”, etc.).  

            7. Formato de entrega:  
            - Devuelve un único bloque `<!DOCTYPE html>` completo con `lang="es"`.  
            - Dentro de `<article>` incluye dos secciones:  
                - `<section id="acta">` — Acta completa.  
                - `<section id="sintesis">` — Síntesis breve.  

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
