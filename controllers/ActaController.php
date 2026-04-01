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
    private function estTokens(string $s): int {
        // Estimación conservadora: 1 token ≈ 3.2 caracteres (mejor que /4 cuando hay mucho contenido)
        $s = preg_replace('/\s+/u', ' ', $s);
        return (int)ceil(mb_strlen($s, 'UTF-8') / 3.2);
    }

    private function minimizeTranscript(string $t): string {
        // 1) Normaliza espacios
        $t = preg_replace('/[ \t]+/u', ' ', $t);
        $t = preg_replace('/\R{3,}/u', "\n\n", $t);

        // 2) Quita timestamps [00:12:34], (00:12), 00:12:34 y similares
        $t = preg_replace('/\[(?:\d{1,2}:){1,2}\d{2}\]/u', '', $t);
        $t = preg_replace('/\((?:\d{1,2}:){1,2}\d{2}\)/u', '', $t);
        $t = preg_replace('/(?<!\d)(?:\d{1,2}:){1,2}\d{2}(?!\d)/u', '', $t);

        // 3) Estandariza acotaciones; conserva su existencia sin verbosidad
        $t = preg_replace('/\[(aplausos?|risas?|voces?|murmullos?|gritos?)\]/iu', ' [$1] ', $t);
        $t = preg_replace('/\((aplausos?|risas?|voces?|murmullos?|gritos?)\)/iu', ' ($1) ', $t);

        // 4) Elimina caracteres “decorativos” largos / separadores
        $t = preg_replace('/[-_=]{6,}/u', '—', $t);
        return trim($t);
    }

    private function clampMaxOut(int $tpmLimit, int $inputTokens, int $usedInWindow = 0, int $hardCap = 11000): int {
        // GRAN margen por recuperación/file_search y metadatos
        $buffer = 6000;
        $remain = max(0, $tpmLimit - $usedInWindow - $inputTokens - $buffer);
        // Nunca pedir más de lo que matemáticamente cabe; y aplica techo de seguridad
        return max(2000, min($hardCap, $remain));
    }

    private function postJSONWithRetry(string $url, array $payload, string $apiKey, int $maxRetries = 1) {
        $headers = [
            "Authorization: Bearer ".$apiKey,
            "Content-Type: application/json",
            "OpenAI-Beta: text-format=v0",
        ];
        $try = 0;
        do {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT        => 600,
                CURLOPT_TCP_KEEPALIVE  => 1,
            ]);
            $res  = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($http !== 429) return [$http, $res, $err];

            // 429: intenta leer "Please try again in Xs" y espera
            $wait = 10;
            if (preg_match('/try again in ([\d\.]+)s/i', (string)$res, $m)) {
                $wait = (int)ceil((float)$m[1]) + 1;
            }
            // Reduce el output pedido para el reintento (30% menos)
            if (isset($payload['max_output_tokens'])) {
                $payload['max_output_tokens'] = (int)max(2000, floor($payload['max_output_tokens'] * 0.7));
            }
            sleep($wait);
            $try++;
        } while ($try <= $maxRetries);

        return [$http, $res, $err];
    }

    private function chunkByTokens(string $text, int $targetTokens = 9000, int $overlapTokens = 400): array {
        // Partición aproximada por caracteres (tokens≈chars/3.2)
        $charsPerTok = 3.2;
        $targetChars = (int)($targetTokens * $charsPerTok);
        $overlapChars= (int)($overlapTokens * $charsPerTok);

        $out = [];
        $len = mb_strlen($text, 'UTF-8');
        $i = 0;
        while ($i < $len) {
            $end = min($len, $i + $targetChars);
            // Trata de cortar en salto de línea cercano (±1k chars) para no romper oraciones
            $winStart = max(0, $end - 1000);
            $winText = mb_substr($text, $winStart, $end - $winStart, 'UTF-8');
            $nlPos = mb_strrpos($winText, "\n");
            if ($nlPos !== false) {
                $end = $winStart + $nlPos;
            }
            $chunk = mb_substr($text, $i, $end - $i, 'UTF-8');
            $out[] = trim($chunk);
            $i = max($end - $overlapChars, $end); // superposición suave
        }
        return $out;
    }

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

            Objetivo: Con esa información, genera un documento HTML5 válido que incluya **Acta** y **Síntesis**, con redacción institucional en tercera persona, en tiempo pasado y con el estilo narrativo estenográfico de las actas oficiales del Congreso (relato objetivo y cronológico de lo sucedido).

            Reglas de contenido y estilo (imprescindibles):

            1.- Voz y tono:
            - Narrativa estenográfica, formal, objetiva e impersonal, en tercera persona.
            - Ejemplos de construcción: “La Diputada Presidenta declaró…”, “El Diputado Secretario informó…”, “Se sometió a votación…”, “Se aprobó por unanimidad…”.
            - Las participaciones o discursos se transcriben íntegros dentro de la narrativa, ejemplos “El Diputado -Nombre diputado- señaló…”,“El Presidente de la Mesa Directiva -Nombre diputado- dijo...".

            2.- Fidelidad a insumos:
            - No inventes datos ni nombres, generalmente los nombres correctos de los diputados estaran en la pase de lista.
            - Si algo no aparece, no lo omitas pon un placeholders indicando el dato que hace falta.
            - La primera vez, escribe el nombre completo con cargo; después usa “el Diputado/la Diputada + Apellido”.

            3.- Estructura del Acta:
            - **Contenido**: Usa toda la transcripción posible, basate en la orden del día, en el caso de asuntos generales pueden tener muchas intervenciones no omitas ninguna
            - **Encabezado**: tipo de sesión, fecha, hora de apertura y clausura (si aparecen), presidencia y secretarías.
            - **Asistencia y quórum**: lista de presentes y justificación de inasistencias, narrado en párrafo corrido (no en lista) usa los nombres de los diputados del pase de lista.
            - **Orden del Día**: incluirlo si aparece, narrado como párrafo continuo.
            - **Desarrollo**: redactar como narrativa corrida, sin subtítulos ni bloques, siguiendo el estilo oficial del Congreso. Mantén el orden de los puntos, para cada punto del orden del día usa conectores (ejemplos: "Prosiguiendo con la orden del día", "Reaunando la sesión") pero integrados en párrafos concatenados dale continuidad a cada parrafo ejemplos "Siguiendo con la sesión el Presidente de la Mesa Directiva señalo..." o "Al finalizar los dos minutos permitidos para el registro de asistencia, la Secretaria Diputada informo".
            - **Intervenviones**: Si hay intervenciones de diputados no las omitas, narralas de acuerdo como sucedió, ejemplo "El diputado -Nombre diputado- expuso y el diputado -nombre dipudato- pidió hacer una pregunta y el diputado presidente intervino"
            - **Clausura**: consignar declaratoria y hora, narrado en párrafo.

            4.- Votaciones:
            - Estiliza la narración en forma corrida: “aprobado por unanimidad en votación económica” o la fórmula que conste.
            - Si existen datos numéricos, inclúyelos, pero narrados dentro del texto (no en tablas).

            5.- Fechas y horas:
            - Fecha completa en formato: “sábado treinta de agosto de dos mil veinticinco”.
            - Hora en formato: “10:40 horas”, “10:56 horas”.

            6.- Síntesis (máx. 5000 palabras):
            -Lenguaje narrativo estenográfico simple.
            -Destaca: acuerdos aprobados, dictámenes, decretos/reformas, designaciones o elecciones, votaciones (tipo y resultado), y clausura.
            -Discursos: solo resumir el sentido general; si hay una cita relevante, usar <blockquote> con fragmento breve.

            Devuelve un único bloque <!DOCTYPE html> completo con lang="es", que contenga un <article> con:
            -<section id="acta"> — Acta completa (narrativa estenográfica, un <h2> por punto).
            -<section id="sintesis"> — Síntesis breve con narrativa estenografica simple resumida (máx. 3000 palabras).

            CSS mínimo embebido (ajústalo sin usar frameworks): tipografía legible, márgenes y tablas limpias, estilos discretos para <blockquote> y listas.

            6. Síntesis (máx. 5000 palabras):  
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
            "max_output_tokens" => 8192
        ];

        // ===== 6) cURL =====
        $ch = curl_init("https://api.openai.com/v1/responses");

        // timeouts más generosos: 10 min lectura, 30 s conexión
        $timeoutSeconds = 600;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_HTTPHEADER      => [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json",
                // Recomendado cuando usas text.format/json_schema:
                "OpenAI-Beta: text-format=v0",
            ],
            CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_TIMEOUT         => $timeoutSeconds,    // o CURLOPT_TIMEOUT_MS => 600000
            CURLOPT_TCP_KEEPALIVE   => 1,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // ===== 7) Manejo de errores de red =====
        if ($response === false) {
            $_SESSION['error_msg'] = "Error de red al llamar a la API (cURL): $curlErr";
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

        // 1) Obtén el texto bruto
        $raw = $data['output_text'] ?? ($data['output'][0]['content'][0]['text'] ?? '');
        $raw = is_string($raw) ? trim($raw) : '';

        // 2) Intenta parsear JSON normal
        $htmlFinal = '';
        if ($raw !== '') {
            $obj = json_decode($raw, true);

            // 3) Si no parsea, prueba doble-decodificación (a veces viene doblemente serializado)
            if (!is_array($obj)) {
                $tmp = json_decode($raw, true);         // puede devolver string JSON
                if (is_string($tmp)) {
                    $obj = json_decode($tmp, true);
                }
            }

            if (is_array($obj) && isset($obj['html'])) {
                $htmlFinal = (string)$obj['html'];       // ✔ HTML real
            } else {
                // Fallback: tratarlo ya como HTML directo
                $htmlFinal = $raw;
            }
        }

        if ($htmlFinal === '') {
            $_SESSION['error_msg'] = "La API respondió, pero no se pudo extraer el HTML.\nRespuesta parcial:\n" . substr($raw, 0, 600);
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        // ===== 9) Guardar en sesión y redirigir =====
        $_SESSION['resultado_html'] = $htmlFinal;
        unset($_SESSION['error_msg']);
        header("Location: index.php?ruta=transcripcion/acta#resultado");
        exit;

    }

    
//     public function procesarconarchivo() {
//         if (session_status() === PHP_SESSION_NONE) session_start();

//         // ===== 1) Entrada =====
//         $orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
//         $trans = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';

//         $_SESSION['ultimo_orden']         = $orden;
//         $_SESSION['ultimo_transcripcion'] = $trans;

//         if ($orden === '' || $trans === '') {
//             $_SESSION['error_msg'] = "Faltan datos: pega la Orden del Día y la Transcripción.";
//             header("Location: index.php?ruta=transcripcion/acta#resultado");
//             exit;
//         }

//         // ===== 2) Config =====
//        $apiKey = getenv('OPENAI_API_KEY');
//         if (!$apiKey) {
//             $_SESSION['error_msg'] = "No se encontró OPENAI_API_KEY en el entorno.";
//             header("Location: index.php?ruta=transcripcion/acta#resultado");
//             exit;
//         }

//         // $model = "gpt-4.1-mini-2025-04-14"; // barato pero resume más
//         $model = "gpt-4.1-2025-04-14";        // mejor fidelidad

//         // ===== 3) Prompt =====
//  $instrucciones = <<<EOT
//             Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
//             Recibirás dos insumos: (a) la Orden del Día y (b) la transcripción completa de la sesión.

//             Objetivo: Con esa información, genera un documento HTML5 válido que incluya **Acta** y **Síntesis**, con redacción institucional en tercera persona, en tiempo pasado y con el estilo narrativo estenográfico de las actas oficiales del Congreso (relato objetivo y cronológico de lo sucedido).

//             Reglas de contenido y estilo (imprescindibles):

//             1.- Voz y tono:
//             - Narrativa estenográfica, formal, objetiva e impersonal, en tercera persona.
//             - Ejemplos de construcción: “La Diputada Presidenta declaró…”, “El Diputado Secretario informó…”, “Se sometió a votación…”, “Se aprobó por unanimidad…”.
//             - Las participaciones o discursos se transcriben íntegros dentro de la narrativa, ejemplos “El Diputado -Nombre diputado- señaló…”,“El Presidente de la Mesa Directiva -Nombre diputado- dijo...".

//             2.- Fidelidad a insumos:
//             - No inventes datos ni nombres, generalmente los nombres correctos de los diputados estaran en la pase de lista.
//             - Si algo no aparece, no lo omitas pon un placeholders indicando el dato que hace falta.
//             - La primera vez, escribe el nombre completo con cargo; después usa “el Diputado/la Diputada + Apellido”.

//             3.- Estructura del Acta:
//             - **Contenido**: Usa toda la transcripción posible, basate en la orden del día, en el caso de asuntos generales pueden tener muchas intervenciones no omitas ninguna
//             - **Encabezado**: tipo de sesión, fecha, hora de apertura y clausura (si aparecen), presidencia y secretarías.
//             - **Asistencia y quórum**: lista de presentes y justificación de inasistencias, narrado en párrafo corrido (no en lista) usa los nombres de los diputados del pase de lista.
//             - **Orden del Día**: incluirlo si aparece, narrado como párrafo continuo.
//             - **Desarrollo**: redactar como narrativa corrida, sin subtítulos ni bloques, siguiendo el estilo oficial del Congreso. Mantén el orden de los puntos, para cada punto del orden del día usa conectores (ejemplos: "Prosiguiendo con la orden del día", "Reaunando la sesión") pero integrados en párrafos concatenados dale continuidad a cada parrafo ejemplos "Siguiendo con la sesión el Presidente de la Mesa Directiva señalo..." o "Al finalizar los dos minutos permitidos para el registro de asistencia, la Secretaria Diputada informo".
//             - **Intervenviones**: Si hay intervenciones de diputados no las omitas, narralas de acuerdo como sucedió, ejemplo "El diputado -Nombre diputado- expuso y el diputado -nombre dipudato- pidió hacer una pregunta y el diputado presidente intervino"
//             - **Clausura**: consignar declaratoria y hora, narrado en párrafo.

//             4.- Votaciones:
//             - Estiliza la narración en forma corrida: “aprobado por unanimidad en votación económica” o la fórmula que conste.
//             - Si existen datos numéricos, inclúyelos, pero narrados dentro del texto (no en tablas).

//             5.- Fechas y horas:
//             - Fecha completa en formato: “sábado treinta de agosto de dos mil veinticinco”.
//             - Hora en formato: “10:40 horas”, “10:56 horas”.

//             CSS mínimo embebido (ajústalo sin usar frameworks): tipografía legible, márgenes y tablas limpias, estilos discretos para <blockquote> y listas.

//             6. Síntesis:  
//             - Incluir en `<section id="sintesis">`.  
//             - Lenguaje narrativo estenográfico simple, en tercera persona.  
//             - Resumir acuerdos aprobados, dictámenes, decretos, designaciones, votaciones y clausura.  
//             - Intervenciones: solo resumir el sentido general; si hay una cita relevante, incluirla breve dentro de `<blockquote>`.  
//             - No usar expresiones subjetivas (“preocupante”, “llamó la atención”, etc.).  

//             7. Formato de entrega:  
//             - Devuelve un único bloque `<!DOCTYPE html>` completo con `lang="es"`.  
//             - Dentro de `<article>` incluye dos secciones:  
//                -<section id="acta"> — Acta completa (narrativa estenográfica).
//               -<section id="sintesis"> — Síntesis breve con narrativa estenografica simple resumida.

//             CSS mínimo embebido (ajústalo sin usar frameworks): tipografía legible, márgenes y tablas limpias, estilos discretos para <blockquote> y listas.
//             EOT;

//         $inputUsuario = "ORDEN DEL DÍA:\n{$orden}\n\nTRANSCRIPCIÓN COMPLETA:\n{$trans}";

//         // ===== 4) JSON Schema para text.format =====
//         $schema = [
//             "type" => "object",
//             "additionalProperties" => false,
//             "properties" => [
//                 "html" => [
//                     "type" => "string",
//                     "description" => "HTML completo (<!DOCTYPE html>, <article>, #acta y #sintesis)."
//                 ]
//             ],
//             "required" => ["html"]
//         ];

//         // ===== 5) IDs de tus archivos/vector store (Rellena los tuyos) =====
//         // Si YA tienes un Vector Store con el archivo de estilo, pon su id aquí para reusarlo SIEMPRE:
//         $VECTOR_STORE_ID    = ''; // p.ej. 'vs_abc123...'  (déjalo vacío si no lo tienes)
//         // Archivo subido con purpose=assistants (mejor para File Search):
//         $ASSISTANTS_FILE_ID = 'file-XPN7sRdDntVwsNGDmEmx19'; // p.ej. 'file_aaaa...'   (opcional)
//         // Archivo subido con purpose=user_data (fallback con input_file):
//         $USERDATA_FILE_ID   = 'file_xBfdzxb4CBrKtHNnvB5MUL'; // <-- ejemplo de tu pantallazo

//         // ===== 6) Preparar caminos A/B =====
//         $useVectorSearch = false;
//         $vectorStoreId   = null;
//         $jsonHeaders = [
//             "Authorization: Bearer " . $apiKey,
//             "Content-Type: application/json",
//         ];

//         // ---- Plan A: Vector Store + file_search
//         if (!empty($VECTOR_STORE_ID)) {
//             $useVectorSearch = true;
//             $vectorStoreId   = $VECTOR_STORE_ID;
//         } elseif (!empty($ASSISTANTS_FILE_ID)) {
//             // crear un vector store al vuelo y adjuntar el archivo assistants
//             // 6.1 crear VS
//             $ch = curl_init("https://api.openai.com/v1/vector_stores");
//             curl_setopt_array($ch, [
//                 CURLOPT_RETURNTRANSFER => true,
//                 CURLOPT_POST           => true,
//                 CURLOPT_HTTPHEADER     => $jsonHeaders,
//                 CURLOPT_POSTFIELDS     => json_encode(["name" => "Acta_Estilo"], JSON_UNESCAPED_UNICODE),
//                 CURLOPT_CONNECTTIMEOUT => 30,
//                 CURLOPT_TIMEOUT        => 120,
//             ]);
//             $vsRes  = curl_exec($ch);
//             $vsHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//             $vsErr  = curl_error($ch);
//             curl_close($ch);

//             if ($vsRes !== false && $vsHttp < 400) {
//                 $vsData       = json_decode($vsRes, true);
//                 $vectorStoreId = $vsData['id'] ?? null;
//             }

//             // 6.2 adjuntar archivo assistants al VS
//             if ($vectorStoreId) {
//                 $ch = curl_init("https://api.openai.com/v1/vector_stores/{$vectorStoreId}/files");
//                 curl_setopt_array($ch, [
//                     CURLOPT_RETURNTRANSFER => true,
//                     CURLOPT_POST           => true,
//                     CURLOPT_HTTPHEADER     => $jsonHeaders,
//                     CURLOPT_POSTFIELDS     => json_encode(["file_id" => $ASSISTANTS_FILE_ID], JSON_UNESCAPED_UNICODE),
//                     CURLOPT_CONNECTTIMEOUT => 30,
//                     CURLOPT_TIMEOUT        => 120,
//                 ]);
//                 $attRes  = curl_exec($ch);
//                 $attHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//                 curl_close($ch);

//                 if ($attRes !== false && $attHttp < 400) {
//                     $useVectorSearch = true;
//                 }
//             }
//         }

//         // ===== 7) Payload según el camino =====
//         if ($useVectorSearch && $vectorStoreId) {
//             // ---- Camino A: file_search con vector_store_ids
//             $payload = [
//                 "model" => $model,
//                 "tools" => [[
//                     "type" => "file_search",
//                     "vector_store_ids" => [$vectorStoreId],
//                 ]],
//                 "input" => [
//                     [
//                         "role"    => "system",
//                         "content" => [["type" => "input_text", "text" => $instrucciones]]
//                     ],
//                     [
//                         "role"    => "user",
//                         "content" => [
//                             ["type" => "input_text", "text" => $inputUsuario],
//                             ["type" => "input_text", "text" => "Usa el Vector Store adjunto como referencia de narrativa, fórmulas y estructura del acta."]
//                         ]
//                     ],
//                 ],
//                 "text" => [
//                     "format" => [
//                         "type"   => "json_schema",
//                         "name"   => "html_acta_sintesis",
//                         "schema" => $schema
//                     ]
//                 ],
//                 "max_output_tokens" => 4096
//             ];
//         } else {
//             // ---- Camino B: input_file con archivo user_data (o assistants si no pudimos crear VS)
//             $fileIdForInline = !empty($USERDATA_FILE_ID) ? $USERDATA_FILE_ID : $ASSISTANTS_FILE_ID;

//             if (empty($fileIdForInline)) {
//                 $_SESSION['error_msg'] = "No hay archivo de referencia disponible. Sube uno con propósito 'assistants' para File Search o usa 'user_data' para input_file.";
//                 header("Location: index.php?ruta=transcripcion/acta#resultado");
//                 exit;
//             }

//             $payload = [
//                 "model" => $model,
//                 "input" => [
//                     [
//                         "role"    => "system",
//                         "content" => [["type" => "input_text", "text" => $instrucciones]]
//                     ],
//                     [
//                         "role"    => "user",
//                         "content" => [
//                             ["type" => "input_text", "text" => $inputUsuario],
//                             ["type" => "input_text", "text" => "Referencia de estilo (leer completa y seguir su narrativa):"],
//                             ["type" => "input_file", "file_id" => $fileIdForInline]
//                         ]
//                     ],
//                 ],
//                 "text" => [
//                     "format" => [
//                         "type"   => "json_schema",
//                         "name"   => "html_acta_sintesis",
//                         "schema" => $schema
//                     ]
//                 ],
//                 "max_output_tokens" => 4096
//             ];
//         }

//         // ===== 8) Llamada a Responses =====
//         $ch = curl_init("https://api.openai.com/v1/responses");
//         $timeoutSeconds = 600;

//         curl_setopt_array($ch, [
//             CURLOPT_RETURNTRANSFER  => true,
//             CURLOPT_POST            => true,
//             CURLOPT_HTTPHEADER      => array_merge($jsonHeaders, [
//                 "OpenAI-Beta: text-format=v0",
//             ]),
//             CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
//             CURLOPT_CONNECTTIMEOUT  => 30,
//             CURLOPT_TIMEOUT         => $timeoutSeconds,
//             CURLOPT_TCP_KEEPALIVE   => 1,
//         ]);

//         $response = curl_exec($ch);
//         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//         $curlErr  = curl_error($ch);
//         curl_close($ch);

//         if ($response === false) {
//             $_SESSION['error_msg'] = "Error de red al llamar a la API (cURL): $curlErr";
//             header("Location: index.php?ruta=transcripcion/acta#resultado");
//             exit;
//         }

//         // ===== 9) Decodificar y extraer HTML =====
//         $data = json_decode($response, true);

//         if ($httpCode >= 400) {
//             $_SESSION['error_msg'] = "Error HTTP $httpCode:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//             header("Location: index.php?ruta=transcripcion/acta#resultado");
//             exit;
//         }

//         // output_text o fallback
//         $raw = $data['output_text'] ?? ($data['output'][0]['content'][0]['text'] ?? '');
//         $raw = is_string($raw) ? trim($raw) : '';

//         $htmlFinal = '';
//         if ($raw !== '') {
//             $obj = json_decode($raw, true);
//             if (!is_array($obj)) {
//                 $tmp = json_decode($raw, true);
//                 if (is_string($tmp)) $obj = json_decode($tmp, true);
//             }
//             if (is_array($obj) && isset($obj['html'])) {
//                 $htmlFinal = (string)$obj['html'];
//             } else {
//                 $htmlFinal = $raw; // por si vino como HTML directo
//             }
//         }

//         if ($htmlFinal === '') {
//             $_SESSION['error_msg'] = "La API respondió, pero no se pudo extraer el HTML.\nRespuesta parcial:\n" . substr($raw, 0, 600);
//             header("Location: index.php?ruta=transcripcion/acta#resultado");
//             exit;
//         }

//         // ===== 10) Guardar en sesión y redirigir =====
//             if ($useVectorSearch && $vectorStoreId) {
//                 // Es el flujo "con archivo de referencia"
//                 $_SESSION['resultado_html_archivo'] = $htmlFinal;
//             } else {
//                 // Fallback por input_file: también corresponde al panel "con archivo"
//                 $_SESSION['resultado_html_archivo'] = $htmlFinal;
//             }

//             // Si quieres limpiar cualquier error previo:
//             unset($_SESSION['error_msg']);

//             // MUY ÚTIL: asegurar que la sesión se escriba antes del redirect
//             session_write_close();

//             header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
//             exit;
//     }

public function procesarconarchivo() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // ===== 1) Entrada =====
    $orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
    $trans = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';
    $_SESSION['ultimo_orden'] = $orden;
    $_SESSION['ultimo_transcripcion'] = $trans;

    if ($orden === '' || $trans === '') {
        $_SESSION['error_msg'] = "Faltan datos: pega la Orden del Día y la Transcripción.";
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
        exit;
    }

    // ===== 2) Config =====
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        $_SESSION['error_msg'] = "No se encontró OPENAI_API_KEY en el entorno.";
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
        exit;
    }

    $model = "gpt-4.1-2025-04-14";

    // ===== 3) Prompt (Acta DETALLADA, no resumir) =====
    $instrucciones = <<<EOT
Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
Recibirás dos insumos: (a) la Orden del Día y (b) la transcripción completa.
Además, se adjunta una **GUÍA DE ESTILO** (archivo de referencia). **Debes consultarla exhaustivamente** y **adoptar su narrativa, fórmulas y transiciones** como norma de estilo. 
Prioridad de fuentes: **hechos** de la transcripción > orden del día > **guía** (solo para forma/estilo). Nunca inventes contenido.

OBJETIVO
Generar un documento HTML5 con **Acta** (DETALLADA, sin resumir) y **Síntesis**, en tercera persona, tiempo pasado y estilo narrativo estenográfico institucional, relatando cronológicamente lo sucedido y reflejando fielmente los hechos.

REGLAS OBLIGATORIAS
1) Voz y tono
   - Estilo estenográfico, formal, objetivo e impersonal, en tercera persona y pasado.
   - Fórmulas típicas de la guía (p. ej.: “La Presidencia declaró…”, “La Diputada Secretaria informó…”, “Se sometió a votación…”, “Se aprobó por unanimidad…”).

2) Cobertura y fidelidad (**NO RESUMIR EL ACTA**)
   - El Acta debe ser **exhaustiva**: no omitas ni agrupes intervenciones (“entre otros”, “diversos temas”, etc., **PROHIBIDO**).
   - Consigna **todas** las intervenciones relevantes: quién habla y en qué carácter (Presidencia, Secretaría, diputación, comisión), qué solicita o expone, acuerdos, turnos, **mociones**, **alusiones personales**, **puntos de orden**, **recesos** y reanudaciones.
   - **Interrupciones, mociones y réplicas**: **siempre** señálalas explícitamente, con redacción sobria acorde a la guía. 
     Ej.: “Durante la intervención se registró una interrupción por parte de (Apellido); la Presidencia llamó al orden…”, 
     “El Diputado (Apellido) planteó moción de orden; la Presidencia la concedió/negó…”.
   - Si hubo manifestaciones del público (voces, aplausos), menciónalo de forma sobria.
   - Si hubo intervenciones en **lengua maya** u otra lengua, indícalo (“expresó parte de su intervención en lengua maya”).
   - **No inventes** nombres ni datos. Si algo no consta, utiliza **placeholder** entre paréntesis: 
     (dato faltante: nombre del Presidente), (hora de clausura), (N), (FECHA EN LETRAS), (CARGO), etc.
   - Primera mención de personas: **nombre completo y cargo**; posteriores: “la Diputada/el Diputado + Apellido”.

3) Estructura del Acta (seguir Orden del Día)
   - **Encabezado solemne**: tipo de sesión, lugar, **fecha en letras**, hora de apertura/cierre si constan, Presidencia y Secretarías. 
     Usa la plantilla y fórmulas de la **guía**.
   - **Asistencia y quórum**: narración en párrafo corrido (no lista). Registrar apertura del sistema, presentes y, en su caso, inasistencias justificadas; concluir con la **declaratoria de quórum**. Fórmulas y conectores de la guía.
   - **Orden del Día**: respeta la secuencia; en “Asuntos Generales”, **registra todas las participaciones**.
   - **Desarrollo**: narrativa **continua** (sin subtítulos internos), con **conectores** propios de la guía (“Acto continuo…”, “Prosiguiendo…”, “Se reanudó…”). 
     Cuando alguien interviene: “En uso de la palabra, la/ el Diputado(a) (Apellido) manifestó…”. 
     Usa **citas breves** en `<blockquote>` **solo** para frases puntualmente relevantes; evita discursos íntegros.
   - **Votaciones**:
     - Económica: “aprobado por unanimidad en votación económica” u otra fórmula que conste.
     - Nominal: consigna **totales** (a favor, en contra, abstenciones/omitidos) y resultado. 
       Si se leyó lista extensa, **no** transcribas nombres uno a uno: narra el resultado; nombra solo si es indispensable.
   - **Clausura**: consignar la declaratoria y hora (si aparece), con fórmula solemne según la guía.

4) Fechas y horas
   - Fecha en letras: “miércoles dieciocho de septiembre de dos mil veinticinco”.
   - Horas en 24 h: “12:47 horas”, “14:14 horas”.

5) **Síntesis** (sección aparte)
   - Incluir en `<section id="sintesis">`.
   - Resumen narrativo estenográfico **simple** (tercera persona), centrado en **acuerdos, dictámenes, decretos, designaciones, votaciones y clausura**.
   - Sin opiniones; puedes incluir **una o dos** citas breves en `<blockquote>` si aportan claridad.
   - **Sin límite numérico de palabras**: la brevedad es conceptual, no contable. No repitas íntegramente el Acta.

FORMATO DE SALIDA (OBLIGATORIO)
- Devuelve **un único** bloque `<!DOCTYPE html>` con `<html lang="es">`.
- En `<head>` incluye `<meta charset="UTF-8">` y **CSS mínimo embebido** (tipografía legible, márgenes, listas y estilo discreto para `<blockquote>`).
- En `<article>` incluye **exactamente** dos secciones:
   - `<section id="acta">` — Acta completa y **detallada** (sin resumir).
   - `<section id="sintesis">` — Síntesis conforme a la regla 5.
- No uses tablas salvo necesidad estricta; prioriza **texto corrido** y las **plantillas** de la guía para encabezado, quórum, votaciones y clausura.
- Revisa al final: todos los puntos del Orden del Día desahogados; todas las **interrupciones/mociones** consignadas; placeholders donde falten datos; fecha/hora en formato correcto.

**Instrucción final:** Consulta y **aplica** las **plantillas, conectores y ejemplos** de la **GUÍA DE ESTILO adjunta** (vector store o input_file). 
En caso de duda de redacción, sigue la guía; en caso de duda de contenido, prevalece la transcripción.
EOT;

    $inputUsuario = "ORDEN DEL DÍA:\n{$orden}\n\nTRANSCRIPCIÓN COMPLETA:\n{$trans}";

    // ===== 4) JSON Schema =====
    $schema = [
        "type" => "object",
        "additionalProperties" => false,
        "properties" => [
            "html" => [
                "type" => "string",
                "description" => "HTML completo (<!DOCTYPE html>, <article>, #acta y #sintesis)."
            ]
        ],
        "required" => ["html"]
    ];

    // ===== 5) IDs =====
    $VECTOR_STORE_ID_FIXED = ''; // si tienes uno permanente, colócalo aquí
    $ASSISTANTS_FILE_ID = 'file-W6aZtftNdBRbfLmSD8LGwD'; // purpose=assistants
    $USERDATA_FILE_ID   = 'file-CAEEPnkGsw94qZVpcvjzqk'; // purpose=user_data

    // ===== 6) Camino A/B =====
    $useVectorSearch = false;
    $vectorStoreId   = null;
    $jsonHeaders = [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
    ];

    // Reusar VS cacheado en sesión o fijo
    if (!empty($_SESSION['vs_id_cache'])) {
        $vectorStoreId = $_SESSION['vs_id_cache'];
        $useVectorSearch = true;
        error_log("[Acta] Re-using VS from session: {$vectorStoreId}");
    } elseif (!empty($VECTOR_STORE_ID_FIXED)) {
        $vectorStoreId = $VECTOR_STORE_ID_FIXED;
        $useVectorSearch = true;
        error_log("[Acta] Using fixed VS: {$vectorStoreId}");
    } elseif (!empty($ASSISTANTS_FILE_ID)) {
        // Crear VS y adjuntar archivo assistants
        $ch = curl_init("https://api.openai.com/v1/vector_stores");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $jsonHeaders,
            CURLOPT_POSTFIELDS     => json_encode(["name" => "Acta_Estilo"], JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $vsRes  = curl_exec($ch);
        $vsHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $vsErr  = curl_error($ch);
        curl_close($ch);

        if ($vsRes !== false && $vsHttp < 400) {
            $vsData        = json_decode($vsRes, true);
            $vectorStoreId = $vsData['id'] ?? null;
            if ($vectorStoreId) {
                $ch = curl_init("https://api.openai.com/v1/vector_stores/{$vectorStoreId}/files");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => $jsonHeaders,
                    CURLOPT_POSTFIELDS     => json_encode(["file_id" => $ASSISTANTS_FILE_ID], JSON_UNESCAPED_UNICODE),
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_TIMEOUT        => 120,
                ]);
                $attRes  = curl_exec($ch);
                $attHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($attRes !== false && $attHttp < 400) {
                    $useVectorSearch = true;
                    $_SESSION['vs_id_cache'] = $vectorStoreId;
                    error_log("[Acta] Created VS {$vectorStoreId} + attached {$ASSISTANTS_FILE_ID}");
                }
            }
        } else {
            error_log("[Acta] VS create error: HTTP={$vsHttp} err={$vsErr}");
        }
    }

    // ===== 7) Payload =====
    $temperature = 0.2; // menos “resumen”, más fidelidad
    if ($useVectorSearch && $vectorStoreId) {
        $payload = [
            "model" => $model,
            "temperature" => $temperature,
            "tools" => [[
                "type" => "file_search",
                "vector_store_ids" => [$vectorStoreId],
            ]],
            "input" => [
                [
                    "role"    => "system",
                    "content" => [["type" => "input_text", "text" => $instrucciones]]
                ],
                [
                    "role"    => "user",
                    "content" => [
                        ["type" => "input_text", "text" => $inputUsuario],
                        ["type" => "input_text", "text" => "Consulta el Vector Store adjunto y replica su narrativa, fórmulas y estructura."]
                    ]
                ],
            ],
            "text" => [
                "format"  => [
                    "type"   => "json_schema",
                    "name"   => "html_acta_sintesis",
                    "schema" => $schema
                ]
            ],
            "max_output_tokens" => 16000
        ];
        $camino = 'A:file_search';
    } else {
        $fileIdForInline = !empty($USERDATA_FILE_ID) ? $USERDATA_FILE_ID : $ASSISTANTS_FILE_ID;
        if (empty($fileIdForInline)) {
            $_SESSION['error_msg'] = "No hay archivo de referencia disponible. Sube uno con 'assistants' para File Search o usa 'user_data' para input_file.";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
            exit;
        }

        $payload = [
            "model" => $model,
            "temperature" => $temperature,
            "input" => [
                [
                    "role"    => "system",
                    "content" => [["type" => "input_text", "text" => $instrucciones]]
                ],
                [
                    "role"    => "user",
                    "content" => [
                        ["type" => "input_text", "text" => $inputUsuario],
                        ["type" => "input_text", "text" => "Referencia de estilo (leer completa y seguir su narrativa fielmente):"],
                        ["type" => "input_file", "file_id" => $fileIdForInline]
                    ]
                ],
            ],
            "text" => [
                "format"  => [
                    "type"   => "json_schema",
                    "name"   => "html_acta_sintesis",
                    "schema" => $schema
                ]
            ],
            "max_output_tokens" => 11000
        ];
        $camino = 'B:input_file';
    }

    // ===== 8) Llamada a Responses =====
    $ch = curl_init("https://api.openai.com/v1/responses");
    $timeoutSeconds = 600;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_POST            => true,
        CURLOPT_HTTPHEADER      => array_merge($jsonHeaders, [
            "OpenAI-Beta: text-format=v0",
        ]),
        CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT  => 30,
        CURLOPT_TIMEOUT         => $timeoutSeconds,
        CURLOPT_TCP_KEEPALIVE   => 1,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $_SESSION['error_msg'] = "Error de red al llamar a la API (cURL): $curlErr";
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
        exit;
    }

    // ===== 9) Decodificar y extraer HTML =====
    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $_SESSION['error_msg'] = "Error HTTP $httpCode:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
        exit;
    }

    $raw = $data['output_text'] ?? ($data['output'][0]['content'][0]['text'] ?? '');
    $raw = is_string($raw) ? trim($raw) : '';
    $htmlFinal = '';
    if ($raw !== '') {
        $obj = json_decode($raw, true);
        if (!is_array($obj)) {
            $tmp = json_decode($raw, true);
            if (is_string($tmp)) $obj = json_decode($tmp, true);
        }
        $htmlFinal = (is_array($obj) && isset($obj['html'])) ? (string)$obj['html'] : $raw;
    }

    if ($htmlFinal === '') {
        $_SESSION['error_msg'] = "La API respondió, pero no se pudo extraer el HTML.\nRespuesta parcial:\n" . substr($raw, 0, 600);
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
        exit;
    }

    // ===== 10) Guardar en sesión, log y redirect =====
    $_SESSION['resultado_html_archivo'] = $htmlFinal;
    $_SESSION['fs_camino'] = $camino;
    if (!empty($vectorStoreId)) $_SESSION['fs_vs_id'] = $vectorStoreId;

    unset($_SESSION['error_msg']);
    session_write_close();

    error_log("[Acta] {$camino} OK, len=" . strlen($htmlFinal));
    header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
    exit;
}

//USAREMOS ESTE AHOARA DE PRUEBAS
public function procesarconarchivo2pasos() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // ===== 1) Entrada =====
    $orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
    $transRaw = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';
    $_SESSION['ultimo_orden'] = $orden;
    $_SESSION['ultimo_transcripcion'] = $transRaw;

    if ($orden === '' || $transRaw === '') {
        $_SESSION['error_msg'] = "Faltan datos: pega la Orden del Día y la Transcripción.";
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
    }

    // ===== 2) Config =====
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        $_SESSION['error_msg'] = "No se encontró OPENAI_API_KEY.";
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
    }

    $model       = "gpt-4.1-2025-04-14";
    $temperature = 0.2;
    $TPM_LIMIT   = 30000;

    // Files de Storage
    $ASSISTANTS_FILE_ID = 'file-W6aZtftNdBRbfLmSD8LGwD'; // assistants -> file_search
    $USERDATA_FILE_ID   = 'file-CAEEPnkGsw94qZVpcvjzqk'; // user_data -> input_file (fallback)

    // ===== 3) Reusar/crear Vector Store (file_search) =====
    $jsonHeaders = [
        "Authorization: Bearer ".$apiKey,
        "Content-Type: application/json",
    ];

    $vectorStoreId   = $_SESSION['vs_id_cache'] ?? null;
    $useVectorSearch = !empty($vectorStoreId);

    if (!$useVectorSearch && !empty($ASSISTANTS_FILE_ID)) {
        // Crear VS
        $ch = curl_init("https://api.openai.com/v1/vector_stores");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $jsonHeaders,
            CURLOPT_POSTFIELDS     => json_encode(["name"=>"Acta_Estilo"], JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $vsRes  = curl_exec($ch);
        $vsHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($vsHttp < 400 && $vsRes) {
            $vsData = json_decode($vsRes, true);
            $vectorStoreId = $vsData['id'] ?? null;
            if ($vectorStoreId) {
                // Adjuntar archivo assistants
                $ch = curl_init("https://api.openai.com/v1/vector_stores/{$vectorStoreId}/files");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => $jsonHeaders,
                    CURLOPT_POSTFIELDS     => json_encode(["file_id"=>$ASSISTANTS_FILE_ID], JSON_UNESCAPED_UNICODE),
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_TIMEOUT        => 120,
                ]);
                $attRes  = curl_exec($ch);
                $attHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($attHttp < 400) {
                    $_SESSION['vs_id_cache'] = $vectorStoreId;
                    $useVectorSearch = true;
                }
            }
        }
    }

    // ===== 4) Normalizar transcripción y preparar entrada =====
    $trans = $this->minimizeTranscript($transRaw);
$instrActa = <<<EOT
Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
Consulta la **GUÍA DE ESTILO** adjunta (vía file_search o input_file) y adopta sus fórmulas, plantillas y transiciones.

ENTREGA EXCLUSIVAMENTE **<section id="acta">…</section>** con esta estructura interna:

1) **Encabezado solemne** (obligatorio) dentro de #acta:
   - `<h1>` con el título oficial (ACTA DE LA SESIÓN…).
   - Un párrafo inicial (lugar/fecha en letras/hora/apertura/presidencia/secretarías). Usa placeholders si falta algún dato: (FECHA EN LETRAS), (HORA), (NOMBRE COMPLETO).

2) **Secciones obligatorias** dentro de #acta, cada una con `<h2>`:
   - `<h2>ASISTENCIA Y QUÓRUM</h2>`: párrafo narrado (no lista), concluye con declaratoria de quórum.
   - `<h2>ORDEN DEL DÍA</h2>`: párrafo corrido con los puntos.
   - Para cada punto del orden del día crea un `<h2>`: “I. …”, “II. …”, etc.
     * **Narrativa por párrafos**: 
       - **Nuevo `<p>`** cuando cambia el orador, cambia el trámite o se abre/cierra una moción.
       - Evita párrafos gigantes (≈ 3–6 oraciones por párrafo).
       - Si hay frase textual relevante, usa `<blockquote>` breve (no discursos íntegros).
     * **Interrupciones/mociones/réplicas**: regístralas explícitamente en la narrativa (p.ej., “En ese momento, el Diputado X pidió moción de orden; la Presidencia concedió/negó…”). 
     * Si alguien se expresa en lengua maya u otra, indícalo (“parte de su intervención fue en lengua maya”).

   - `<h2>CLAUSURA</h2>`: párrafo con la declaratoria de clausura y hora (usa placeholder si falta).

3) **Votaciones**: incorpora la fórmula estándar en el párrafo correspondiente
   (“aprobado por unanimidad en votación económica”, o totales de nominal si existen).

4) **Estilo**:
   - Tercera persona, tiempo pasado, estenográfico, objetivo e institucional.
   - Primera mención: nombre completo y cargo; después “el/la Diputado(a) + Apellido”.
   - **No** inventes datos. Usa placeholders cuando falte información.

**Devuelve solo** `<section id="acta">…</section>`. No incluyas `<html>`, `<head>` ni CSS.
EOT;


    $inputUsuarioBase = "ORDEN DEL DÍA:\n{$orden}\n\nTRANSCRIPCIÓN COMPLETA (normalizada):\n{$trans}";

    // JSON schema para ACTA-solo
    $schemaActa = [
        "type"=>"object", "additionalProperties"=>false,
        "properties"=>[
            "acta_html"=>[
                "type"=>"string",
                "description"=>"Únicamente el bloque <section id='acta'>…</section>."
            ]
        ],
        "required"=>["acta_html"]
    ];

    // Estimar tokens y decidir chunking
    $inputTok1 = $this->estTokens($instrActa) + $this->estTokens($inputUsuarioBase);
    $maxOut1   = $this->clampMaxOut($TPM_LIMIT, $inputTok1, 0, 9000);
    $doChunk   = ($inputTok1 > 18000); // umbral para chunking del ACTA

    $actaSection = '';

    // ===== 5) Paso 1 — ACTA (con chunking opcional) =====
    if ($doChunk) {
        $pieces = [];
        $chunks = $this->chunkByTokens($trans, 9000, 400);
        $n = count($chunks);

        for ($i=0; $i<$n; $i++) {
            $prev = ($i>0)     ? mb_substr($chunks[$i-1], -600, null, 'UTF-8') : '';
            $curr = $chunks[$i];
            $next = ($i<$n-1)  ? mb_substr($chunks[$i+1], 0, 600, 'UTF-8')     : '';

            $inputUsuarioChunk =
                "ORDEN DEL DÍA:\n{$orden}\n\n".
                "TRANSCRIPCIÓN (tramo ".($i+1)." de {$n}):\n{$curr}\n\n".
                "Contexto previo (texto literal, no narrar):\n{$prev}\n\n".
                "Contexto siguiente (texto literal, no narrar):\n{$next}\n\n".
                "Instrucción: genera SOLO narrativa de ACTA para este tramo, manteniendo continuidad con tramos previos; no dupliques contenido ya narrado. No incluyas encabezado ni clausura todavía; devuelve únicamente <section id='acta'>…</section>.";

            $tokIn = $this->estTokens($instrActa) + $this->estTokens($inputUsuarioChunk);
            $maxOutChunk = $this->clampMaxOut($TPM_LIMIT, $tokIn, 0, 6000);

            if ($useVectorSearch && $vectorStoreId) {
                $payloadChunk = [
                    "model"=>$model, "temperature"=>$temperature,
                    "tools"=>[["type"=>"file_search","vector_store_ids"=>[$vectorStoreId]]],
                    "input"=>[
                        ["role"=>"system","content"=>[["type"=>"input_text","text"=>$instrActa]]],
                        ["role"=>"user","content"=>[
                            ["type"=>"input_text","text"=>$inputUsuarioChunk],
                            ["type"=>"input_text","text"=>"Consulta la GUÍA DE ESTILO adjunta y replica su narrativa."]
                        ]],
                    ],
                    "text"=>["format"=>["type"=>"json_schema","name"=>"acta_sola","schema"=>$schemaActa]],
                    "max_output_tokens"=>$maxOutChunk
                ];
            } else {
                $fileIdForInline = $USERDATA_FILE_ID ?: $ASSISTANTS_FILE_ID;
                $payloadChunk = [
                    "model"=>$model, "temperature"=>$temperature,
                    "input"=>[
                        ["role"=>"system","content"=>[["type"=>"input_text","text"=>$instrActa]]],
                        ["role"=>"user","content"=>[
                            ["type"=>"input_text","text"=>$inputUsuarioChunk],
                            ["type"=>"input_text","text"=>"Referencia de estilo (leer y seguir su narrativa fielmente):"],
                            ["type"=>"input_file","file_id"=>$fileIdForInline]
                        ]],
                    ],
                    "text"=>["format"=>["type"=>"json_schema","name"=>"acta_sola","schema"=>$schemaActa]],
                    "max_output_tokens"=>$maxOutChunk
                ];
            }

            // Llamada con retry/backoff
            [$http,$res,$err] = $this->postJSONWithRetry("https://api.openai.com/v1/responses", $payloadChunk, $apiKey, 1);
            if ($http >= 400) {
                $_SESSION['error_msg'] = "Error en ACTA (tramo ".($i+1)."/{$n}) HTTP {$http}:\n".$res;
                header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
            }
            $data = json_decode($res,true);
            $raw  = $data['output_text'] ?? ($data['output'][0]['content'][0]['text'] ?? '');
            $raw  = is_string($raw) ? trim($raw) : '';
            $piece = '';
            if ($raw !== '') {
                $obj = json_decode($raw,true);
                $piece = (is_array($obj) && isset($obj['acta_html'])) ? (string)$obj['acta_html'] : $raw;
            }
            // Extrae solo el interior si vino con <section id="acta">…</section>
            if (preg_match('/<section[^>]*id=[\'"]acta[\'"][^>]*>(.*)<\/section>/is', $piece, $m)) {
                $piece = trim($m[1]);
            }
            $pieces[] = $piece;
        }
        $actaSection = "<section id=\"acta\">\n".implode("\n\n", $pieces)."\n</section>";
    } else {
        // Flujo simple (sin chunking)
        $inputUsuario = $inputUsuarioBase;

        if ($useVectorSearch && $vectorStoreId) {
            $payload1 = [
                "model"=>$model, "temperature"=>$temperature,
                "tools"=>[["type"=>"file_search","vector_store_ids"=>[$vectorStoreId]]],
                "input"=>[
                    ["role"=>"system","content"=>[["type"=>"input_text","text"=>$instrActa]]],
                    ["role"=>"user","content"=>[
                        ["type"=>"input_text","text"=>$inputUsuario],
                        ["type"=>"input_text","text"=>"Consulta la GUÍA DE ESTILO adjunta y replica su narrativa."]
                    ]],
                ],
                "text"=>["format"=>["type"=>"json_schema","name"=>"acta_sola","schema"=>$schemaActa]],
                "max_output_tokens"=>$maxOut1
            ];
        } else {
            $fileIdForInline = $USERDATA_FILE_ID ?: $ASSISTANTS_FILE_ID;
            $payload1 = [
                "model"=>$model, "temperature"=>$temperature,
                "input"=>[
                    ["role"=>"system","content"=>[["type"=>"input_text","text"=>$instrActa]]],
                    ["role"=>"user","content"=>[
                        ["type"=>"input_text","text"=>$inputUsuario],
                        ["type"=>"input_text","text"=>"Referencia de estilo (leer y seguir su narrativa fielmente):"],
                        ["type"=>"input_file","file_id"=>$fileIdForInline]
                    ]],
                ],
                "text"=>["format"=>["type"=>"json_schema","name"=>"acta_sola","schema"=>$schemaActa]],
                "max_output_tokens"=>$maxOut1
            ];
        }

        [$http1,$res1,$err1] = $this->postJSONWithRetry("https://api.openai.com/v1/responses", $payload1, $apiKey, 1);
        if ($http1 >= 400) {
            $_SESSION['error_msg'] = "Error en ACTA (HTTP $http1):\n".$res1;
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
        }
        $data1 = json_decode($res1,true);
        $raw1  = $data1['output_text'] ?? ($data1['output'][0]['content'][0]['text'] ?? '');
        $raw1  = is_string($raw1) ? trim($raw1) : '';
        $actaSection = '';
        if ($raw1 !== '') {
            $obj = json_decode($raw1,true);
            $actaSection = (is_array($obj) && isset($obj['acta_html'])) ? (string)$obj['acta_html'] : $raw1;
        }
        if ($actaSection !== '' && stripos($actaSection, '<section') === false) {
            $actaSection = '<section id="acta">'.$actaSection.'</section>';
        }
    }

    if ($actaSection === '' || stripos($actaSection, "<section") === false) {
        $_SESSION['error_msg'] = "No se pudo extraer la sección ACTA.";
        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
    }

    // ===== 6) Paso 2 — SÍNTESIS (solo a partir del ACTA) =====
$instrSint = <<<EOT
Eres un asistente de estilo estenográfico institucional. Recibirás la sección <section id="acta"> ya redactada.
A partir de ELLA (sin reabrir la transcripción), genera **ÚNICAMENTE** la sección <section id="sintesis"> con narrativa estenográfica simple, en tercera persona, centrada en acuerdos, dictámenes, decretos, designaciones, votaciones y clausura.
Comienza SIEMPRE la sección con `<h2>Síntesis</h2>`.
Sin opiniones; puedes incluir una o dos citas breves en <blockquote> si aportan claridad.
No impongas límite de palabras; sé conciso pero completo.
Entrega solo <section id="sintesis">…</section>.
EOT;


    $schemaSint = [
        "type"=>"object", "additionalProperties"=>false,
        "properties"=>[
            "sintesis_html"=>[
                "type"=>"string",
                "description"=>"Únicamente el bloque <section id='sintesis'>…</section>."
            ]
        ],
        "required"=>["sintesis_html"]
    ];

    $inputTok2 = $this->estTokens($instrSint) + $this->estTokens($actaSection);
    $maxOut2   = $this->clampMaxOut($TPM_LIMIT, $inputTok2, 0, 6000);

    $payload2 = [
        "model"=>$model, "temperature"=>$temperature,
        "input"=>[
            ["role"=>"system","content"=>[["type"=>"input_text","text"=>$instrSint]]],
            ["role"=>"user","content"=>[
                ["type"=>"input_text","text"=>"ACTA BASE (HTML):"],
                ["type"=>"input_text","text"=>$actaSection]
            ]],
        ],
        "text"=>["format"=>["type"=>"json_schema","name"=>"sintesis_sola","schema"=>$schemaSint]],
        "max_output_tokens"=>$maxOut2
    ];

    [$http2,$res2,$err2] = $this->postJSONWithRetry("https://api.openai.com/v1/responses", $payload2, $apiKey, 1);
    if ($http2 >= 400) {
        $sintesisSection = "<section id=\"sintesis\"><h2>Síntesis</h2><p>(No disponible por error de generación. Puede intentarse nuevamente.)</p></section>";
    } else {
        $data2 = json_decode($res2,true);
        $raw2  = $data2['output_text'] ?? ($data2['output'][0]['content'][0]['text'] ?? '');
        $raw2  = is_string($raw2) ? trim($raw2) : '';
        if ($raw2 !== '') {
            $o2 = json_decode($raw2,true);
            $sintesisSection = (is_array($o2) && isset($o2['sintesis_html'])) ? (string)$o2['sintesis_html'] : $raw2;
        } else {
            $sintesisSection = '';
        }
        if ($sintesisSection === '' || stripos($sintesisSection, "<section") === false) {
            $sintesisSection = "<section id=\"sintesis\"><h2>Síntesis</h2><p>(No se pudo extraer correctamente. Reinténtalo.)</p></section>";
        }
    }

    // ===== 7) Ensamblar HTML final =====
$css = <<<CSS
:root{
  --ink:#111827; --muted:#374151; --accent:#1e3a8a; --border:#e5e7eb; --bgblock:#f8fafc;
}
html,body{margin:0;padding:0}
body{
  font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;
  line-height:1.6; color:var(--ink);
  background:#fff;
}
article{max-width:1000px; margin:32px auto; padding:0 18px;}
h1{font-size:2.25rem; line-height:1.25; color:var(--accent); margin:0 0 .8rem 0; font-weight:800;}
h2{font-size:1.25rem; line-height:1.35; color:var(--accent); margin:1.6rem 0 .6rem 0; font-weight:800;}
h3{font-size:1.05rem; margin:1.2rem 0 .4rem 0;}
p{margin:.7rem 0; text-align:justify;}
blockquote{margin:1rem 0; padding:.75rem 1rem; border-left:4px solid #94a3b8; background:var(--bgblock);}
table{border-collapse:collapse; width:100%;}
td,th{border:1px solid var(--border); padding:6px 8px;}
/* separa mejor secciones largas */
#acta h2{border-top:1px solid var(--border); padding-top:.9rem;}
CSS;


    $htmlFinal = "<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"UTF-8\"><title>Acta y Síntesis</title><style>{$css}</style></head><body><article>{$actaSection}{$sintesisSection}</article></body></html>";

    // ===== 8) Guardar en sesión y redirigir =====
    $_SESSION['resultado_html_archivo'] = $htmlFinal;
    unset($_SESSION['error_msg']);
    session_write_close();

    header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
}


// AQUI INICAN LOS GUARDAR

    public function guardar() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error_msg'] = 'Método no permitido.';
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }

    $id   = filter_input(INPUT_POST, 'iIdTrans', FILTER_VALIDATE_INT) ?: 0;
    $html = $_POST['tActaHtml'] ?? '';

    if ($id <= 0 || trim($html) === '') {
        $_SESSION['error_msg'] = 'Faltan datos para guardar el acta (id o contenido).';
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }

    // Conexión
    $mysqli = new mysqli('localhost', 'root', '', 'transcriptor');
    if ($mysqli->connect_errno) {
        $_SESSION['error_msg'] = 'Error de conexión: ' . $mysqli->connect_error;
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }
    $mysqli->set_charset('utf8mb4');

    // Transacción por seguridad
    $mysqli->begin_transaction();
    $stmt = $mysqli->prepare('UPDATE transcripciones SET tActaHtml = ?, dActaGenerada = NOW() WHERE iIdTrans = ?');
    if (!$stmt) {
        $mysqli->rollback();
        $_SESSION['error_msg'] = 'Error al preparar la consulta.';
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }
    $stmt->bind_param('si', $html, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $mysqli->commit();
        $_SESSION['success_msg']   = 'Acta (normal) guardada correctamente.';
        $_SESSION['resultado_html'] = $html; // para re-mostrar en la vista
    } else {
        $mysqli->rollback();
        $_SESSION['error_msg'] = 'No se pudo guardar el acta.';
    }
    $mysqli->close();

    header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
}

/**
 * Guarda el resultado generado "con archivo de referencia"
 * en la columna tActaArchivoHtml (y, si la tienes, dActaArchivoGenerada).
 */
public function guardarArchivo() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error_msg'] = 'Método no permitido.';
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }

    $id   = filter_input(INPUT_POST, 'iIdTrans', FILTER_VALIDATE_INT) ?: 0;
    $html = $_POST['tActaArchivoHtml'] ?? '';

    if ($id <= 0 || trim($html) === '') {
        $_SESSION['error_msg'] = 'Faltan datos para guardar el acta con archivo (id o contenido).';
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }

    $mysqli = new mysqli('localhost', 'root', '', 'transcriptor');
    if ($mysqli->connect_errno) {
        $_SESSION['error_msg'] = 'Error de conexión: ' . $mysqli->connect_error;
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }
    $mysqli->set_charset('utf8mb4');

    $mysqli->begin_transaction();
    // Si tienes una columna de fecha separada, úsala (ajusta el nombre):
    $stmt = $mysqli->prepare('UPDATE transcripciones SET tActaArchivoHtml = ?, dActaArchivoGenerada = NOW() WHERE iIdTrans = ?');
    if (!$stmt) {
        $mysqli->rollback();
        $_SESSION['error_msg'] = 'Error al preparar la consulta.';
        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }
    $stmt->bind_param('si', $html, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $mysqli->commit();
        $_SESSION['success_msg'] = 'Acta (con archivo) guardada correctamente.';
        $_SESSION['resultado_html_archivo'] = $html; // para re-mostrar en su panel
    } else {
        $mysqli->rollback();
        $_SESSION['error_msg'] = 'No se pudo guardar el acta con archivo.';
    }
    $mysqli->close();

    header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
}

}
