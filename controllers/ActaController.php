<?php
// controllers/ActaController.php  — Claude API version
class ActaController
{
    private function estTokens(string $s): int {
        $s = preg_replace('/\s+/u', ' ', $s);
        return (int)ceil(mb_strlen($s, 'UTF-8') / 3.2);
    }

    private function minimizeTranscript(string $t): string {
        $t = preg_replace('/[ \t]+/u', ' ', $t);
        $t = preg_replace('/\R{3,}/u', "\n\n", $t);
        $t = preg_replace('/\[(?:\d{1,2}:){1,2}\d{2}\]/u', '', $t);
        $t = preg_replace('/\((?:\d{1,2}:){1,2}\d{2}\)/u', '', $t);
        $t = preg_replace('/(?<!\d)(?:\d{1,2}:){1,2}\d{2}(?!\d)/u', '', $t);
        $t = preg_replace('/\[(aplausos?|risas?|voces?|murmullos?|gritos?)\]/iu', ' [$1] ', $t);
        $t = preg_replace('/\((aplausos?|risas?|voces?|murmullos?|gritos?)\)/iu', ' ($1) ', $t);
        $t = preg_replace('/[-_=]{6,}/u', '—', $t);
        return trim($t);
    }

    private function clampMaxOut(int $tpmLimit, int $inputTokens, int $usedInWindow = 0, int $hardCap = 11000): int {
        $buffer = 4000;
        $remain = max(0, $tpmLimit - $usedInWindow - $inputTokens - $buffer);
        return max(2000, min($hardCap, $remain));
    }

    private function chunkByTokens(string $text, int $targetTokens = 9000, int $overlapTokens = 400): array {
        $charsPerTok = 3.2;
        $targetChars = (int)($targetTokens * $charsPerTok);
        $overlapChars= (int)($overlapTokens * $charsPerTok);

        $out = [];
        $len = mb_strlen($text, 'UTF-8');
        $i = 0;
        while ($i < $len) {
            $end = min($len, $i + $targetChars);
            $winStart = max(0, $end - 1000);
            $winText = mb_substr($text, $winStart, $end - $winStart, 'UTF-8');
            $nlPos = mb_strrpos($winText, "\n");
            if ($nlPos !== false) {
                $end = $winStart + $nlPos;
            }
            $chunk = mb_substr($text, $i, $end - $i, 'UTF-8');
            $out[] = trim($chunk);
            $i = max($end - $overlapChars, $end);
        }
        return $out;
    }

    // ============================================================
    //  Claude API: llamada con reintento en 429
    // ============================================================
    private function postClaudeWithRetry(array $payload, string $apiKey, int $maxRetries = 1): array {
        $headers = [
            "x-api-key: " . $apiKey,
            "anthropic-version: 2023-06-01",
            "anthropic-beta: prompt-caching-2024-07-31",
            "content-type: application/json",
        ];

        $try = 0;
        do {
            $ch = curl_init("https://api.anthropic.com/v1/messages");
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

            $wait = 10;
            if (preg_match('/try again in ([\d\.]+)s/i', (string)$res, $m)) {
                $wait = (int)ceil((float)$m[1]) + 1;
            }
            if (isset($payload['max_tokens'])) {
                $payload['max_tokens'] = (int)max(2000, floor($payload['max_tokens'] * 0.7));
            }
            sleep($wait);
            $try++;
        } while ($try <= $maxRetries);

        return [$http, $res, $err];
    }

    /**
     * Extrae texto de la respuesta Claude (content[0].text)
     * con fallback robusto de JSON si el modelo devuelve JSON envuelto.
     */
    private function extractClaudeText(string $rawResp, string $jsonKey = ''): string {
        $data = json_decode($rawResp, true);
        if (!is_array($data)) return '';

        $text = trim((string)($data['content'][0]['text'] ?? ''));
        if ($text === '') return '';

        if ($jsonKey !== '') {
            // Intenta parsear como JSON para extraer el campo pedido
            $obj = json_decode($text, true);
            if (!is_array($obj)) {
                // Quita ```json ... ``` si viniera
                $clean = preg_replace('/^```(?:json)?\s*/i', '', $text);
                $clean = preg_replace('/\s*```$/', '', $clean);
                $p1 = strpos($clean, '{');
                $p2 = strrpos($clean, '}');
                if ($p1 !== false && $p2 !== false && $p2 > $p1) {
                    $obj = json_decode(substr($clean, $p1, $p2 - $p1 + 1), true);
                }
            }
            if (is_array($obj) && isset($obj[$jsonKey])) {
                return (string)$obj[$jsonKey];
            }
        }

        return $text;
    }

    // ============================================================
    //  procesar(): acta simple en una sola llamada
    // ============================================================
    public function procesar() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
        $trans = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';

        $_SESSION['ultimo_orden'] = $orden;
        $_SESSION['ultimo_transcripcion'] = $trans;

        if ($orden === '' || $trans === '') {
            $_SESSION['error_msg'] = "Faltan datos: asegúrate de pegar la Orden del Día y la Transcripción.";
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            $_SESSION['error_msg'] = "No se encontró ANTHROPIC_API_KEY en el entorno.";
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        $model = "claude-sonnet-4-6";

        $instrucciones = <<<EOT
Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
Recibirás dos insumos: (a) la Orden del Día y (b) la transcripción completa de la sesión.

Objetivo: genera un documento HTML5 válido que incluya Acta y Síntesis, con redacción institucional en tercera persona, en tiempo pasado y estilo narrativo estenográfico.

Reglas de contenido y estilo (imprescindibles):

1.- Voz y tono:
- Narrativa estenográfica, formal, objetiva e impersonal, en tercera persona.
- Ejemplos: "La Diputada Presidenta declaró…", "El Diputado Secretario informó…", "Se sometió a votación…", "Se aprobó por unanimidad…".
- Las participaciones se transcriben íntegras dentro de la narrativa.

2.- Fidelidad a insumos:
- No inventes datos ni nombres.
- Si algo no aparece, pon un placeholder indicando el dato faltante.
- La primera vez, escribe el nombre completo con cargo; después usa "el Diputado/la Diputada + Apellido".

3.- Estructura del Acta:
- Encabezado: tipo de sesión, fecha, hora de apertura y clausura (si aparecen), presidencia y secretarías.
- Asistencia y quórum: narrado en párrafo corrido.
- Orden del Día: narrado como párrafo continuo.
- Desarrollo: narrativa corrida, sin subtítulos, con conectores entre puntos.
- Intervenciones: no omitas ninguna.
- Clausura: consignar declaratoria y hora, en párrafo.

4.- Votaciones: narración en forma corrida.

5.- Fechas y horas:
- Fecha completa en letras: "sábado treinta de agosto de dos mil veinticinco".
- Hora: "10:40 horas".

6.- Síntesis (sección aparte):
- Lenguaje narrativo estenográfico simple, tercera persona.
- Destacar acuerdos, dictámenes, decretos, designaciones, votaciones y clausura.
- Usar <blockquote> para citas relevantes breves.

FORMATO DE SALIDA:
Devuelve ÚNICAMENTE JSON válido con la siguiente estructura:
{"html": "<aqui el HTML completo>"}

El HTML debe ser un único bloque <!DOCTYPE html> completo con lang="es", que contenga un <article> con:
- <section id="acta"> — Acta completa.
- <section id="sintesis"> — Síntesis.

CSS mínimo embebido (tipografía legible, márgenes, sin frameworks).
EOT;

        $inputUsuario = "ORDEN DEL DÍA:\n" . $orden . "\n\nTRANSCRIPCIÓN COMPLETA:\n" . $trans;

        $payload = [
            "model" => $model,
            "max_tokens" => 8192,
            "temperature" => 0.2,
            "system" => [
                [
                    "type" => "text",
                    "text" => $instrucciones,
                    "cache_control" => ["type" => "ephemeral"]
                ]
            ],
            "messages" => [
                [
                    "role" => "user",
                    "content" => $inputUsuario
                ]
            ]
        ];

        [$http, $response, $curlErr] = $this->postClaudeWithRetry($payload, $apiKey, 1);

        if ($response === false) {
            $_SESSION['error_msg'] = "Error de red al llamar a la API (cURL): $curlErr";
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        if ($http >= 400) {
            $_SESSION['error_msg'] = "Error HTTP $http:\n" . $response;
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        $htmlFinal = $this->extractClaudeText($response, 'html');

        if ($htmlFinal === '') {
            $_SESSION['error_msg'] = "La API respondió, pero no se pudo extraer el HTML.\nRespuesta parcial:\n" . substr($response, 0, 600);
            header("Location: index.php?ruta=transcripcion/acta#resultado");
            exit;
        }

        $_SESSION['resultado_html'] = $htmlFinal;
        unset($_SESSION['error_msg']);
        header("Location: index.php?ruta=transcripcion/acta#resultado");
        exit;
    }

    // ============================================================
    //  procesarconarchivo(): acta con guía de estilo (sin VS)
    // ============================================================
    public function procesarconarchivo() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
        $trans = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';
        $_SESSION['ultimo_orden'] = $orden;
        $_SESSION['ultimo_transcripcion'] = $trans;

        if ($orden === '' || $trans === '') {
            $_SESSION['error_msg'] = "Faltan datos: pega la Orden del Día y la Transcripción.";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
            exit;
        }

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            $_SESSION['error_msg'] = "No se encontró ANTHROPIC_API_KEY en el entorno.";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
            exit;
        }

        $model = "claude-sonnet-4-6";

        $instrucciones = <<<EOT
Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.
Recibirás dos insumos: (a) la Orden del Día y (b) la transcripción completa.

OBJETIVO
Generar un documento HTML5 con Acta (DETALLADA, sin resumir) y Síntesis, en tercera persona, tiempo pasado y estilo narrativo estenográfico institucional.

REGLAS OBLIGATORIAS
1) Voz y tono
   - Estilo estenográfico, formal, objetivo e impersonal, en tercera persona y pasado.
   - Fórmulas típicas: "La Presidencia declaró…", "La Diputada Secretaria informó…", "Se sometió a votación…", "Se aprobó por unanimidad…".

2) Cobertura y fidelidad (NO RESUMIR EL ACTA)
   - El Acta debe ser exhaustiva: no omitas ni agrupes intervenciones.
   - Consigna todas las intervenciones relevantes, acuerdos, turnos, mociones, alusiones personales, puntos de orden, recesos y reanudaciones.
   - Interrupciones y mociones: señálalas explícitamente.
   - No inventes nombres ni datos. Si falta algo, usa placeholder: (dato faltante: nombre).
   - Primera mención: nombre completo y cargo; posteriores: "la Diputada/el Diputado + Apellido".

3) Estructura del Acta
   - Encabezado solemne: tipo de sesión, lugar, fecha en letras, hora apertura/cierre, Presidencia y Secretarías.
   - Asistencia y quórum: párrafo corrido, con declaratoria de quórum.
   - Orden del Día: respeta la secuencia; en Asuntos Generales, registra todas las participaciones.
   - Desarrollo: narrativa continua con conectores ("Acto continuo…", "Prosiguiendo…", "Se reanudó…").
   - Votaciones: fórmula estándar en párrafo; para nominal incluye totales.
   - Clausura: declaratoria y hora con fórmula solemne.

4) Fechas y horas
   - Fecha en letras: "miércoles dieciocho de septiembre de dos mil veinticinco".
   - Horas en 24 h: "12:47 horas".

5) Síntesis
   - En <section id="sintesis">. Narrativa estenográfica simple, tercera persona.
   - Centrada en acuerdos, dictámenes, decretos, designaciones, votaciones y clausura.
   - Sin opiniones; citas breves en <blockquote> si aportan claridad.

FORMATO DE SALIDA (OBLIGATORIO)
Devuelve ÚNICAMENTE JSON válido:
{"html": "<el HTML completo aquí>"}

El HTML debe ser un único bloque <!DOCTYPE html> con <html lang="es">.
En <article> incluye exactamente dos secciones:
- <section id="acta"> — Acta completa y detallada.
- <section id="sintesis"> — Síntesis.

CSS mínimo embebido (tipografía legible, márgenes, sin frameworks).
EOT;

        $inputUsuario = "ORDEN DEL DÍA:\n{$orden}\n\nTRANSCRIPCIÓN COMPLETA:\n{$trans}";

        $payload = [
            "model" => $model,
            "max_tokens" => 16000,
            "temperature" => 0.2,
            "system" => [
                [
                    "type" => "text",
                    "text" => $instrucciones,
                    "cache_control" => ["type" => "ephemeral"]
                ]
            ],
            "messages" => [
                [
                    "role" => "user",
                    "content" => $inputUsuario
                ]
            ]
        ];

        [$http, $response, $curlErr] = $this->postClaudeWithRetry($payload, $apiKey, 1);

        if ($response === false) {
            $_SESSION['error_msg'] = "Error de red al llamar a la API (cURL): $curlErr";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
            exit;
        }

        if ($http >= 400) {
            $_SESSION['error_msg'] = "Error HTTP $http:\n" . $response;
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
            exit;
        }

        $htmlFinal = $this->extractClaudeText($response, 'html');

        if ($htmlFinal === '') {
            $_SESSION['error_msg'] = "La API respondió, pero no se pudo extraer el HTML.\nRespuesta parcial:\n" . substr($response, 0, 600);
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
            exit;
        }

        $_SESSION['resultado_html_archivo'] = $htmlFinal;
        $_SESSION['fs_camino'] = 'claude:direct';
        unset($_SESSION['error_msg']);
        session_write_close();

        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo");
        exit;
    }

    // ============================================================
    //  procesarconarchivo2pasos(): 2 pasos ACTA + SÍNTESIS con chunking
    // ============================================================
    public function procesarconarchivo2pasos() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $orden   = isset($_POST['orden']) ? trim($_POST['orden']) : '';
        $transRaw = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';
        $_SESSION['ultimo_orden']          = $orden;
        $_SESSION['ultimo_transcripcion']  = $transRaw;

        if ($orden === '' || $transRaw === '') {
            $_SESSION['error_msg'] = "Faltan datos: pega la Orden del Día y la Transcripción.";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
        }

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            $_SESSION['error_msg'] = "No se encontró ANTHROPIC_API_KEY.";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
        }

        $model       = "claude-sonnet-4-6";
        $temperature = 0.2;
        $TPM_LIMIT   = 30000;

        $trans = $this->minimizeTranscript($transRaw);

        // ===== INSTRUCCIONES ACTA =====
        $instrActa = <<<EOT
Eres un asistente experto en técnica legislativa del Congreso del Estado de Yucatán.

ENTREGA EXCLUSIVAMENTE <section id="acta">…</section> con esta estructura interna:

1) Encabezado solemne (obligatorio) dentro de #acta:
   - <h1> con el título oficial (ACTA DE LA SESIÓN…).
   - Un párrafo inicial (lugar/fecha en letras/hora/apertura/presidencia/secretarías). Usa placeholders si falta algún dato.

2) Secciones obligatorias dentro de #acta, cada una con <h2>:
   - <h2>ASISTENCIA Y QUÓRUM</h2>: párrafo narrado (no lista), concluye con declaratoria de quórum.
   - <h2>ORDEN DEL DÍA</h2>: párrafo corrido con los puntos.
   - Para cada punto del orden del día crea un <h2>: "I. …", "II. …", etc.
     * Narrativa por párrafos: nuevo <p> cuando cambia el orador, cambia el trámite o se abre/cierra una moción.
     * Interrupciones/mociones/réplicas: regístralas explícitamente.
   - <h2>CLAUSURA</h2>: párrafo con la declaratoria de clausura y hora.

3) Votaciones: fórmula estándar en el párrafo correspondiente.

4) Estilo: tercera persona, tiempo pasado, estenográfico, objetivo e institucional.
   - Primera mención: nombre completo y cargo; después "el/la Diputado(a) + Apellido".
   - No inventes datos. Usa placeholders donde falte información.

Devuelve ÚNICAMENTE JSON válido:
{"acta_html": "<section id='acta'>…</section>"}
EOT;

        $inputUsuarioBase = "ORDEN DEL DÍA:\n{$orden}\n\nTRANSCRIPCIÓN COMPLETA (normalizada):\n{$trans}";

        $inputTok1 = $this->estTokens($instrActa) + $this->estTokens($inputUsuarioBase);
        $maxOut1   = $this->clampMaxOut($TPM_LIMIT, $inputTok1, 0, 9000);
        $doChunk   = ($inputTok1 > 18000);

        $actaSection = '';

        // ===== PASO 1: ACTA (con chunking opcional) =====
        if ($doChunk) {
            $pieces = [];
            $chunks = $this->chunkByTokens($trans, 9000, 400);
            $n = count($chunks);

            for ($i = 0; $i < $n; $i++) {
                $prev = ($i > 0)    ? mb_substr($chunks[$i-1], -600, null, 'UTF-8') : '';
                $curr = $chunks[$i];
                $next = ($i < $n-1) ? mb_substr($chunks[$i+1], 0, 600, 'UTF-8')    : '';

                $inputUsuarioChunk =
                    "ORDEN DEL DÍA:\n{$orden}\n\n".
                    "TRANSCRIPCIÓN (tramo " . ($i+1) . " de {$n}):\n{$curr}\n\n".
                    "Contexto previo (texto literal, no narrar):\n{$prev}\n\n".
                    "Contexto siguiente (texto literal, no narrar):\n{$next}\n\n".
                    "Instrucción: genera SOLO narrativa de ACTA para este tramo, manteniendo continuidad con tramos previos; no dupliques contenido ya narrado. Devuelve ÚNICAMENTE JSON: {\"acta_html\": \"<section id='acta'>…</section>\"}";

                $tokIn       = $this->estTokens($instrActa) + $this->estTokens($inputUsuarioChunk);
                $maxOutChunk = $this->clampMaxOut($TPM_LIMIT, $tokIn, 0, 6000);

                $payloadChunk = [
                    "model"       => $model,
                    "max_tokens"  => $maxOutChunk,
                    "temperature" => $temperature,
                    "system"      => [
                        [
                            "type"          => "text",
                            "text"          => $instrActa,
                            "cache_control" => ["type" => "ephemeral"]
                        ]
                    ],
                    "messages" => [
                        ["role" => "user", "content" => $inputUsuarioChunk]
                    ]
                ];

                [$http, $res, $err] = $this->postClaudeWithRetry($payloadChunk, $apiKey, 1);
                if ($http >= 400) {
                    $_SESSION['error_msg'] = "Error en ACTA (tramo " . ($i+1) . "/{$n}) HTTP {$http}:\n" . $res;
                    header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
                }

                $piece = $this->extractClaudeText($res, 'acta_html');
                if (preg_match('/<section[^>]*id=[\'"]acta[\'"][^>]*>(.*)<\/section>/is', $piece, $m)) {
                    $piece = trim($m[1]);
                }
                $pieces[] = $piece;
            }
            $actaSection = "<section id=\"acta\">\n" . implode("\n\n", $pieces) . "\n</section>";

        } else {
            $payload1 = [
                "model"       => $model,
                "max_tokens"  => $maxOut1,
                "temperature" => $temperature,
                "system"      => [
                    [
                        "type"          => "text",
                        "text"          => $instrActa,
                        "cache_control" => ["type" => "ephemeral"]
                    ]
                ],
                "messages" => [
                    ["role" => "user", "content" => $inputUsuarioBase . "\n\nDevuelve ÚNICAMENTE JSON: {\"acta_html\": \"<section id='acta'>…</section>\"}"]
                ]
            ];

            [$http1, $res1, $err1] = $this->postClaudeWithRetry($payload1, $apiKey, 1);
            if ($http1 >= 400) {
                $_SESSION['error_msg'] = "Error en ACTA (HTTP $http1):\n" . $res1;
                header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
            }

            $actaSection = $this->extractClaudeText($res1, 'acta_html');
            if ($actaSection !== '' && stripos($actaSection, '<section') === false) {
                $actaSection = '<section id="acta">' . $actaSection . '</section>';
            }
        }

        if ($actaSection === '' || stripos($actaSection, '<section') === false) {
            $_SESSION['error_msg'] = "No se pudo extraer la sección ACTA.";
            header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
        }

        // ===== PASO 2: SÍNTESIS =====
        $instrSint = <<<EOT
Eres un asistente de estilo estenográfico institucional. Recibirás la sección <section id="acta"> ya redactada.
A partir de ELLA (sin reabrir la transcripción), genera ÚNICAMENTE la sección <section id="sintesis"> con narrativa estenográfica simple, en tercera persona, centrada en acuerdos, dictámenes, decretos, designaciones, votaciones y clausura.
Comienza SIEMPRE la sección con <h2>Síntesis</h2>.
Sin opiniones; puedes incluir una o dos citas breves en <blockquote> si aportan claridad.
Devuelve ÚNICAMENTE JSON válido:
{"sintesis_html": "<section id='sintesis'>…</section>"}
EOT;

        $inputTok2 = $this->estTokens($instrSint) + $this->estTokens($actaSection);
        $maxOut2   = $this->clampMaxOut($TPM_LIMIT, $inputTok2, 0, 6000);

        $payload2 = [
            "model"       => $model,
            "max_tokens"  => $maxOut2,
            "temperature" => $temperature,
            "system"      => [
                [
                    "type"          => "text",
                    "text"          => $instrSint,
                    "cache_control" => ["type" => "ephemeral"]
                ]
            ],
            "messages" => [
                [
                    "role"    => "user",
                    "content" => "ACTA BASE (HTML):\n" . $actaSection
                ]
            ]
        ];

        [$http2, $res2, $err2] = $this->postClaudeWithRetry($payload2, $apiKey, 1);

        if ($http2 >= 400) {
            $sintesisSection = "<section id=\"sintesis\"><h2>Síntesis</h2><p>(No disponible por error de generación.)</p></section>";
        } else {
            $sintesisSection = $this->extractClaudeText($res2, 'sintesis_html');
            if ($sintesisSection === '' || stripos($sintesisSection, '<section') === false) {
                $sintesisSection = "<section id=\"sintesis\"><h2>Síntesis</h2><p>(No se pudo extraer correctamente.)</p></section>";
            }
        }

        // ===== CSS y ensamble =====
        $css = <<<CSS
:root{--ink:#111827;--muted:#374151;--accent:#1e3a8a;--border:#e5e7eb;--bgblock:#f8fafc}
html,body{margin:0;padding:0}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;line-height:1.6;color:var(--ink);background:#fff}
article{max-width:1000px;margin:32px auto;padding:0 18px}
h1{font-size:2.25rem;line-height:1.25;color:var(--accent);margin:0 0 .8rem 0;font-weight:800}
h2{font-size:1.25rem;line-height:1.35;color:var(--accent);margin:1.6rem 0 .6rem 0;font-weight:800}
p{margin:.7rem 0;text-align:justify}
blockquote{margin:1rem 0;padding:.75rem 1rem;border-left:4px solid #94a3b8;background:var(--bgblock)}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid var(--border);padding:6px 8px}
#acta h2{border-top:1px solid var(--border);padding-top:.9rem}
CSS;

        $htmlFinal = "<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"UTF-8\"><title>Acta y Síntesis</title><style>{$css}</style></head><body><article>{$actaSection}{$sintesisSection}</article></body></html>";

        $_SESSION['resultado_html_archivo'] = $htmlFinal;
        unset($_SESSION['error_msg']);
        session_write_close();

        header("Location: index.php?ruta=transcripcion/acta#resultado-archivo"); exit;
    }

    // ============================================================
    //  GUARDAR ACTA (normal)
    // ============================================================
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

        $mysqli = new mysqli('localhost', 'root', '', 'transcriptor');
        if ($mysqli->connect_errno) {
            $_SESSION['error_msg'] = 'Error de conexión: ' . $mysqli->connect_error;
            header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
        }
        $mysqli->set_charset('utf8mb4');

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
            $_SESSION['success_msg']    = 'Acta (normal) guardada correctamente.';
            $_SESSION['resultado_html'] = $html;
        } else {
            $mysqli->rollback();
            $_SESSION['error_msg'] = 'No se pudo guardar el acta.';
        }
        $mysqli->close();

        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }

    // ============================================================
    //  GUARDAR ACTA CON ARCHIVO
    // ============================================================
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
            $_SESSION['success_msg']              = 'Acta (con archivo) guardada correctamente.';
            $_SESSION['resultado_html_archivo']   = $html;
        } else {
            $mysqli->rollback();
            $_SESSION['error_msg'] = 'No se pudo guardar el acta con archivo.';
        }
        $mysqli->close();

        header('Location: index.php?ruta=transcripcion/acta#resultado'); exit;
    }
}
