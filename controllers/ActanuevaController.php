<?php
require_once 'models/TranscripcionModel.php';
require_once 'models/CorreccionModel.php';
require_once 'models/ActaNuevaModel.php';
require_once 'models/UsuarioModel.php';
require_once 'models/DiputadoModel.php';

class ActanuevaController
{
    private $transModel;
    private $corrModel;
    private $actaModel;

    public function __construct() {
        $this->transModel = new TranscripcionModel();
        $this->corrModel  = new CorreccionModel();
        $this->actaModel  = new ActaNuevaModel();
    }

    // ============================================================
    //                       VISTA PRINCIPAL
    // ============================================================
    public function iniciar() {

        $idTrans = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($idTrans <= 0) { echo "ID de transcripción inválido"; return; }

        $trans = $this->transModel->obtenerPorId($idTrans);
        if (!$trans) {
            echo "Transcripción no encontrada";
            return;
        }

        $ultimaCorr = $this->corrModel->obtenerUltima($idTrans);
        $idCorreccion = (int)$ultimaCorr['id'];
        $metaSesion = $this->corrModel->obtenerMetadatosPorCorreccion($idCorreccion);

        if (!$ultimaCorr) {
            echo "No hay transcripción taquigráfica corregida. Primero realiza la revisión ortográfica.";
            return;
        }

        $acta = $this->actaModel->obtenerPorTranscripcion($idTrans);

        $usuarioModel = new UsuarioModel();
        $diputados    = $usuarioModel->obtenerDiputadosActivos();

        $dipModel     = new DiputadoModel();
        $legActiva    = $dipModel->legislaturaActiva();
        $tiposSesion  = $this->corrModel->obtenerTiposSesionActivos();

        $view = __DIR__ . '/../views/acta_nueva/iniciar.php';
        include __DIR__ . '/../layout.php';
    }

    // ============================================================
    //                 GENERADOR DE ACTA (CHUNKS) + MODO DEBUG
    // ============================================================
    public function generarActa()
    {
        header('Content-Type: application/json; charset=utf-8');

        $transcripcionId = intval($_POST['transcripcion_id'] ?? 0);
        $correccionId    = intval($_POST['correccion_id'] ?? 0);
        $textoFuente     = $_POST['texto_fuente'] ?? '';
        $actaId          = $_POST['acta_id'] ?? '';

        $modo = trim($_POST['modo'] ?? '');

        if ($transcripcionId <= 0 || !$textoFuente) {
            echo json_encode(['error' => 'Faltan parámetros.']);
            return;
        }

        // Chunking inteligente por participación
        $segmentos = $this->dividirPorParticipacion($textoFuente);
        $chunks    = $this->crearChunksInteligentes($segmentos, 14000);

        if (empty($chunks)) {
            echo json_encode(['error' => 'No se pudieron generar chunks a partir del texto fuente.']);
            return;
        }

        $totalChunks  = count($chunks);
        $rutaProgreso = __DIR__ . '/../tmp/progreso_acta.json';

        @file_put_contents(
            $rutaProgreso,
            json_encode(['actual' => 0, 'total' => $totalChunks, 'porcentaje' => 0], JSON_UNESCAPED_UNICODE)
        );

        // MODO DEBUG
        if ($modo === 'debug') {
            $vistaChunks = [];
            foreach ($chunks as $i => $c) {
                $porcentaje = $totalChunks > 0 ? round((($i+1) / $totalChunks) * 100) : 0;
                @file_put_contents(
                    $rutaProgreso,
                    json_encode(['actual' => ($i+1), 'total' => $totalChunks, 'porcentaje' => $porcentaje], JSON_UNESCAPED_UNICODE)
                );
                $vistaChunks[] = [
                    'n'      => $i + 1,
                    'len'    => mb_strlen($c, 'UTF-8'),
                    'inicio' => mb_substr($c, 0, 140, 'UTF-8'),
                    'texto'  => $c
                ];
            }
            @file_put_contents(
                $rutaProgreso,
                json_encode(['actual' => $totalChunks, 'total' => $totalChunks, 'porcentaje' => 100], JSON_UNESCAPED_UNICODE)
            );
            echo json_encode([
                'modo'         => 'debug',
                'segmentos'    => count($segmentos),
                'total_chunks' => $totalChunks,
                'chunks'       => $vistaChunks
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Pipeline Claude
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            echo json_encode(['error' => 'ANTHROPIC_API_KEY no configurada en el entorno.']);
            return;
        }

        $modelo    = "claude-sonnet-4-6";
        $contexto  = "";
        $actaFinal = "";
        $indice    = 0;

        foreach ($chunks as $chunk) {
            $indice++;
            if ($indice > 1) sleep(5);
            $porcentaje = $totalChunks > 0 ? round(($indice / $totalChunks) * 100) : 0;

            @file_put_contents(
                $rutaProgreso,
                json_encode([
                    'actual'     => $indice,
                    'total'      => $totalChunks,
                    'porcentaje' => $porcentaje,
                    'estado'     => 'procesando',
                    'mensaje'    => "Procesando chunk $indice de $totalChunks"
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );

            $respuesta = $this->procesarChunkClaudeConReintentos($chunk, $contexto, $apiKey, $modelo, 5);

            if (!is_array($respuesta) || empty($respuesta['__ok']) || !isset($respuesta['acta_fragmento'], $respuesta['resumen'])) {
                $rutaError = __DIR__ . '/../tmp/error_acta.json';
                @file_put_contents(
                    $rutaError,
                    json_encode([
                        'fecha'        => date('Y-m-d H:i:s'),
                        'chunk'        => $indice,
                        'total'        => $totalChunks,
                        'chunk_len'    => mb_strlen($chunk, 'UTF-8'),
                        'chunk_inicio' => mb_substr($chunk, 0, 400, 'UTF-8'),
                        'contexto_len' => mb_strlen($contexto, 'UTF-8'),
                        'diagnostico'  => $respuesta
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    LOCK_EX
                );

                echo json_encode([
                    'error' => 'Falló Claude (parse). Revisa tmp/error_acta.json',
                    'chunk' => $indice,
                    'total' => $totalChunks,
                    'json_error' => $respuesta['__json_error_msg'] ?? null,
                    'http'  => $respuesta['__http'] ?? null
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $actaFinal .= $respuesta['acta_fragmento'] . "\n\n";
            $contexto   = $respuesta['resumen'];
        }

        @file_put_contents(
            $rutaProgreso,
            json_encode(['actual' => $totalChunks, 'total' => $totalChunks, 'porcentaje' => 100], JSON_UNESCAPED_UNICODE)
        );

        $charsOrigen = mb_strlen($textoFuente, 'UTF-8');
        $charsActa   = mb_strlen($actaFinal, 'UTF-8');

        if (!empty($actaId)) {
            $this->actaModel->actualizarActa($actaId, $charsOrigen, $actaFinal, $charsActa);
        } else {
            $actaId = $this->actaModel->guardarActa(
                $transcripcionId,
                $correccionId,
                $charsOrigen,
                $actaFinal,
                $charsActa
            );
        }

        echo json_encode([
            'id_acta'        => $actaId,
            'chars_origen'   => $charsOrigen,
            'chars_acta'     => $charsActa,
            'diferencia_pct' => round((($charsActa - $charsOrigen) / max(1, $charsOrigen)) * 100, 2),
            'texto_acta'     => $actaFinal
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //  ENDPOINT: progresoActa
    // ============================================================
    public function progresoActa()
    {
        header('Content-Type: application/json; charset=utf-8');

        $ruta = __DIR__ . '/../tmp/progreso_acta.json';

        if (!file_exists($ruta)) {
            echo json_encode(['actual' => 0, 'total' => 0, 'porcentaje' => 0], JSON_UNESCAPED_UNICODE);
            return;
        }

        $contenido = file_get_contents($ruta);
        $json = json_decode($contenido, true);

        if (!$json) {
            echo json_encode(['actual' => 0, 'total' => 0, 'porcentaje' => 0], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //  HELPER: Dividir por participación (uso de la palabra)
    // ============================================================
    private function dividirPorParticipacion(string $texto): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        $pattern = '/(?=^(?:DIPUTADO|DIPUTADA|PRESIDENCIA|SECRETARIO|SECRETARIA|MESA\s+DIRECTIVA|VICEPRESIDENCIA|PROSECRETARIA|PROSECRETARIO)\b[^\n:]{0,180}:)/m';

        $partes = preg_split($pattern, $texto, -1, PREG_SPLIT_NO_EMPTY);

        if (!$partes || count($partes) === 0) {
            return [trim($texto)];
        }

        return array_values(array_filter(array_map('trim', $partes)));
    }

    // ============================================================
    //  HELPER: Crear chunks sin cortar participaciones
    // ============================================================
    private function crearChunksInteligentes(array $segmentos, int $maxLen = 14000): array
    {
        $chunks = [];
        $actual = '';

        foreach ($segmentos as $seg) {
            $seg = trim($seg);
            if ($seg === '') continue;

            if (mb_strlen($seg, 'UTF-8') > $maxLen) {
                if (trim($actual) !== '') {
                    $chunks[] = trim($actual);
                    $actual   = '';
                }
                $sub = $this->dividirIntervencionGrande($seg, $maxLen);
                foreach ($sub as $s) {
                    $chunks[] = trim($s);
                }
                continue;
            }

            $candidato = (trim($actual) === '') ? $seg : ($actual . "\n\n" . $seg);

            if (mb_strlen($candidato, 'UTF-8') > $maxLen) {
                if (trim($actual) !== '') $chunks[] = trim($actual);
                $actual = $seg;
            } else {
                $actual = $candidato;
            }
        }

        if (trim($actual) !== '') $chunks[] = trim($actual);

        return $chunks;
    }

    // ============================================================
    //  HELPER: dividir Intervencion Grande
    // ============================================================
    private function dividirIntervencionGrande(string $texto, int $maxLen = 14000): array
    {
        $texto = trim($texto);
        if ($texto === '') return [];

        $partes = preg_split("/\n{2,}/", $texto);
        $salida = [];
        $buffer = '';

        foreach ($partes as $p) {
            $p = trim($p);
            if ($p === '') continue;

            $cand = ($buffer === '') ? $p : ($buffer . "\n\n" . $p);

            if (mb_strlen($cand, 'UTF-8') > $maxLen) {
                if ($buffer !== '') {
                    $salida[] = $buffer;
                    $buffer   = $p;
                } else {
                    foreach ($this->dividirPorOracionesODuro($p, $maxLen) as $x) {
                        $salida[] = $x;
                    }
                    $buffer = '';
                }
            } else {
                $buffer = $cand;
            }
        }

        if ($buffer !== '') $salida[] = $buffer;

        if (count($salida) > 1) {
            foreach ($salida as $i => $t) {
                if ($i > 0) $salida[$i] = "[CONTINÚA MISMA INTERVENCIÓN]\n" . $t;
            }
        }

        return $salida;
    }

    private function dividirPorOracionesODuro(string $texto, int $maxLen): array
    {
        $texto = trim($texto);
        if ($texto === '') return [];

        $oraciones = preg_split('/(?<=\.)\s+/', $texto);
        $salida = [];
        $buffer = '';

        foreach ($oraciones as $o) {
            $o = trim($o);
            if ($o === '') continue;

            $cand = ($buffer === '') ? $o : ($buffer . ' ' . $o);

            if (mb_strlen($cand, 'UTF-8') > $maxLen) {
                if ($buffer !== '') {
                    $salida[] = $buffer;
                    $buffer   = $o;
                } else {
                    foreach ($this->corteDuroConTraslape($o, $maxLen, 300) as $x) {
                        $salida[] = $x;
                    }
                    $buffer = '';
                }
            } else {
                $buffer = $cand;
            }
        }

        if ($buffer !== '') $salida[] = $buffer;

        return $salida;
    }

    private function corteDuroConTraslape(string $texto, int $maxLen, int $overlap = 300): array
    {
        $texto = trim($texto);
        $len   = mb_strlen($texto, 'UTF-8');
        if ($len <= $maxLen) return [$texto];

        $salida = [];
        $i = 0;

        while ($i < $len) {
            $chunk    = mb_substr($texto, $i, $maxLen, 'UTF-8');
            $salida[] = $chunk;
            $i += ($maxLen - $overlap);
            if ($i < 0) break;
        }

        if (count($salida) > 1) {
            foreach ($salida as $k => $t) {
                if ($k > 0) $salida[$k] = "[CONTINÚA MISMA INTERVENCIÓN]\n" . $t;
            }
        }

        return $salida;
    }

    // ============================================================
    //  HELPER: Llamada a Claude con reintentos
    // ============================================================
    private function procesarChunkClaudeConReintentos(
        string $chunk,
        string $contexto,
        string $apiKey,
        string $modelo,
        int $maxReintentos = 5
    ): array {
        $systemText =
            "Eres redactor parlamentario del H. Congreso del Estado de Yucatán. " .
            "Tu tarea es CONVERTIR una transcripción taquigráfica a redacción de ACTA oficial. " .
            "Reglas: (1) NO inventes datos, (2) NO omitas intervenciones ni acuerdos, (3) NO resumas: conserva el contenido, " .
            "(4) SÍ reescribe en tercera persona, tono institucional y formato narrativo de acta, " .
            "(5) NO mantengas encabezados tipo 'DIPUTADO X:' salvo casos estrictamente necesarios; en su lugar usa fórmulas de acta. " .
            "Devuelve ÚNICAMENTE JSON válido con llaves: acta_fragmento, resumen.";

        $userText =
            "CONTEXTO ACUMULADO (muy breve, solo para continuidad):\n" . ($contexto ?? '') . "\n\n" .
            "FRAGMENTO TAQUIGRÁFICO A CONVERTIR (NO RESUMIR):\n" . $chunk . "\n\n" .
            "REGLAS CRÍTICAS (OBLIGATORIAS):\n" .
            "1) NO RESUMAS NI CONDENSES. NO OMITAS INTERVENCIONES, ARGUMENTOS, EJEMPLOS NI DETALLES.\n" .
            "2) SÍ reescribe a formato ACTA (tercera persona, tono institucional, conectores).\n" .
            "3) CONSERVA LA EXTENSIÓN: el campo \"acta_fragmento\" debe medir entre 90% y 115% de la longitud del fragmento de entrada.\n" .
            "4) No inventes datos.\n" .
            "5) Evita listas; redacta en narrativa corrida.\n\n" .
            "FORMATO: Devuelve ÚNICAMENTE JSON válido:\n" .
            "{\n" .
            "  \"acta_fragmento\": \"(texto del acta para ESTE chunk, largo y completo)\",\n" .
            "  \"resumen\": \"(6-10 líneas máximo: acuerdos, estado del orden del día, quién habló y en qué tema)\"\n" .
            "}";

        $payload = [
            "model"       => $modelo,
            "max_tokens"  => 12000,
            "temperature" => 0.15,
            "system" => [
                [
                    "type"          => "text",
                    "text"          => $systemText,
                    "cache_control" => ["type" => "ephemeral"]
                ]
            ],
            "messages" => [
                ["role" => "user", "content" => $userText]
            ]
        ];

        $ultimo = null;

        for ($i = 1; $i <= $maxReintentos; $i++) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                return [
                    '__ok'             => false,
                    '__http'           => 0,
                    '__try'            => $i,
                    '__json_error_msg' => 'json_encode(payload) falló: ' . json_last_error_msg(),
                ];
            }

            $ch = curl_init("https://api.anthropic.com/v1/messages");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    "x-api-key: {$apiKey}",
                    "anthropic-version: 2023-06-01",
                    "anthropic-beta: prompt-caching-2024-07-31",
                    "content-type: application/json",
                ],
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 240,
                CURLOPT_CONNECTTIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $respRaw = curl_exec($ch);
            $cno     = curl_errno($ch);
            $cerr    = curl_error($ch);
            $http    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $diagnostico = [
                '__ok'             => false,
                '__http'           => $http,
                '__try'            => $i,
                '__parse_error'    => false,
                '__json_error_msg' => null,
                '__resp_raw'       => is_string($respRaw) ? $respRaw : null,
                '__curl_errno'     => $cno,
                '__curl_error'     => $cerr,
            ];

            if ($respRaw === false || $http === 0) { $ultimo = $diagnostico; continue; }
            if ($http !== 200) { $ultimo = $diagnostico; continue; }

            try {
                $parsed = $this->extraer_json_de_claude($respRaw);

                if (!isset($parsed['acta_fragmento'], $parsed['resumen'])) {
                    throw new RuntimeException('Faltan llaves acta_fragmento/resumen');
                }

                return [
                    '__ok'           => true,
                    '__http'         => 200,
                    '__try'          => $i,
                    'acta_fragmento' => $parsed['acta_fragmento'],
                    'resumen'        => $parsed['resumen'],
                    '__resp_raw'     => $respRaw,
                ];

            } catch (Throwable $e) {
                $diagnostico['__parse_error']    = true;
                $diagnostico['__json_error_msg'] = $e->getMessage();
                $ultimo = $diagnostico;
                continue;
            }
        }

        return $ultimo ?: [
            '__ok'             => false,
            '__http'           => 0,
            '__try'            => $maxReintentos,
            '__json_error_msg' => 'Sin diagnóstico',
        ];
    }

    // ============================================================
    //  HELPER: Decodificación robusta de JSON
    // ============================================================
    private function decode_json_robusto(string $s): array
    {
        $s = trim($s);

        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```(?:json)?\s*/i', '', $s);
            $s = preg_replace('/\s*```$/', '', $s);
            $s = trim($s);
        }

        $p1 = strpos($s, '{');
        $p2 = strrpos($s, '}');
        if ($p1 !== false && $p2 !== false && $p2 > $p1) {
            $s = substr($s, $p1, $p2 - $p1 + 1);
        }

        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);

        $data = json_decode($s, true);
        if (!is_array($data)) {
            throw new RuntimeException('json_decode: ' . json_last_error_msg());
        }

        return $data;
    }

    // ============================================================
    //  HELPER: Extraer JSON del response de Claude
    // ============================================================
    private function extraer_json_de_claude(string $respRaw): array
    {
        $resp = json_decode($respRaw, true);
        if (!is_array($resp)) {
            throw new RuntimeException('Respuesta Claude no es JSON: ' . json_last_error_msg());
        }

        $content = (string)($resp['content'][0]['text'] ?? '');
        if ($content === '') {
            throw new RuntimeException('Claude: content[0].text vacío');
        }

        return $this->decode_json_robusto($content);
    }

    // ============================================================
    //  HELPER: cURL Claude con JSON (para síntesis y encabezado)
    // ============================================================
    private function curlClaudeJson(array $payload, string $apiKey): array
    {
        $ch = curl_init("https://api.anthropic.com/v1/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "x-api-key: $apiKey",
                "anthropic-version: 2023-06-01",
                "anthropic-beta: prompt-caching-2024-07-31",
                "content-type: application/json"
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 240,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        $cno  = curl_errno($ch);
        curl_close($ch);

        if ($http !== 200 || $resp === false) {
            return [
                '__ok'         => false,
                '__http'       => $http,
                '__curl_errno' => $cno,
                '__curl_error' => $cerr,
                '__resp_raw'   => is_string($resp) ? $resp : ''
            ];
        }

        $jsonOuter  = json_decode($resp, true);
        $content    = trim((string)($jsonOuter['content'][0]['text'] ?? ''));

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $decoded['__ok'] = true;
            return $decoded;
        }

        // Intenta extraer JSON si vino con texto extra
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $m)) {
            $decoded2 = json_decode($m[0], true);
            if (is_array($decoded2)) {
                $decoded2['__ok'] = true;
                return $decoded2;
            }
        }

        return [
            '__ok'          => false,
            '__http'        => 200,
            '__parse_error' => true,
            '__content_raw' => $content,
            '__resp_raw'    => $resp
        ];
    }

    // ============================================================
    //  DATOS PARA GUARDAR EN LOS METADATOS
    // ============================================================
    public function obtenerMetadata()
    {
        header('Content-Type: application/json; charset=utf-8');

        $actaId = intval($_GET['acta_id'] ?? 0);
        if ($actaId <= 0) {
            echo json_encode(['error' => 'acta_id inválido']);
            return;
        }

        $m   = new ActaNuevaModel();
        $row = $m->obtenerMetadata($actaId);

        echo json_encode(['ok' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
    }

    public function guardarMetadata()
    {
        header('Content-Type: application/json; charset=utf-8');

        $actaId = intval($_POST['acta_id'] ?? 0);
        if ($actaId <= 0) {
            echo json_encode(['error' => 'acta_id inválido']);
            return;
        }

        $data = [
            'clave_acta'        => trim($_POST['clave_acta'] ?? ''),
            'tipo_sesion'       => trim($_POST['tipo_sesion'] ?? 'Ordinaria'),
            'legislatura'       => trim($_POST['legislatura'] ?? 'LXIV'),
            'legislatura_texto' => trim($_POST['legislatura_texto'] ?? ''),
            'periodo'           => trim($_POST['periodo'] ?? ''),
            'ejercicio'         => trim($_POST['ejercicio'] ?? ''),
            'fecha'             => trim($_POST['fecha'] ?? ''),
            'hora_inicio'       => trim($_POST['hora_inicio'] ?? ''),
            'ciudad'            => trim($_POST['ciudad'] ?? 'Mérida'),
            'recinto'           => trim($_POST['recinto'] ?? ''),
            'presidente'        => trim($_POST['presidente'] ?? ''),
            'secretaria_1'      => trim($_POST['secretaria_1'] ?? ''),
            'secretaria_2'      => trim($_POST['secretaria_2'] ?? ''),
        ];

        $ids = array_filter([
            $data['presidente'],
            $data['secretaria_1'],
            $data['secretaria_2']
        ]);

        if (count($ids) !== count(array_unique($ids))) {
            echo json_encode(['ok' => false, 'error' => 'No se puede repetir la misma persona en Presidente/Secretarías.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->actaModel->guardarOActualizarMetadata($actaId, $data);

        echo json_encode(['ok' => (bool)$ok, 'error' => $ok ? null : 'No se pudo guardar metadata'], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //  GENERAR ENCABEZADO AI (Claude)
    // ============================================================
    public function generarEncabezadoAI()
    {
        header('Content-Type: application/json; charset=utf-8');

        $actaId = intval($_POST['acta_id'] ?? 0);
        if ($actaId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'acta_id inválido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $meta = $this->actaModel->obtenerMetadata($actaId);
        if (!$meta) {
            echo json_encode(['ok' => false, 'error' => 'Primero guarda los metadatos del acta.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $required = ['clave_acta','tipo_sesion','legislatura','legislatura_texto','periodo','ejercicio','fecha','hora_inicio','ciudad','recinto','presidente','secretaria_1'];
        foreach ($required as $k) {
            if (!isset($meta[$k]) || trim((string)$meta[$k]) === '') {
                echo json_encode(['ok' => false, 'error' => "Falta completar el metadato: $k"], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        $ids = array_filter([trim($meta['presidente'] ?? ''), trim($meta['secretaria_1'] ?? ''), trim($meta['secretaria_2'] ?? '')]);
        if (count($ids) !== count(array_unique($ids))) {
            echo json_encode(['ok' => false, 'error' => 'No se puede repetir la misma persona en Presidente/Secretarías.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $u          = new UsuarioModel();
        $nombrePres = $u->obtenerNombrePorId($meta['presidente']);
        $nombreS1   = $u->obtenerNombrePorId($meta['secretaria_1']);
        $nombreS2   = trim((string)($meta['secretaria_2'] ?? '')) !== '' ? $u->obtenerNombrePorId($meta['secretaria_2']) : '';

        if (!$nombrePres || !$nombreS1) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo resolver nombre de Presidente/Secretaria en usuarios.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $modelo = "claude-sonnet-4-6";
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            echo json_encode(['ok' => false, 'error' => 'Falta ANTHROPIC_API_KEY en el servidor.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $fechaISO   = $meta['fecha'];
        $fechaTexto = $this->fechaEnTexto($fechaISO);

        $prompt =
            "Genera el ENCABEZADO y el PRIMER PÁRRAFO de un acta oficial del H. Congreso del Estado de Yucatán.\n" .
            "No inventes datos. Usa exclusivamente los metadatos proporcionados.\n" .
            "Devuelve ÚNICAMENTE JSON válido con estas llaves:\n" .
            "{ \"encabezado_ai\": \"...\", \"primer_parrafo_ai\": \"...\" }\n\n" .

            "REQUISITO PARA encabezado_ai (OBLIGATORIO):\n" .
            "- Debe incluir, en este orden:\n" .
            "  1) 'GOBIERNO DEL ESTADO DE YUCATÁN' y 'PODER LEGISLATIVO'\n" .
            "  2) La clave del acta\n" .
            "  3) Una línea/título en MAYÚSCULAS exactamente con esta estructura:\n" .
            "     ACTA DE LA SESIÓN {TIPO_SESION_EN_MAYUSCULAS} CELEBRADA POR LA {LEGISLATURA_TEXTO_EN_MAYUSCULAS} LEGISLATURA DEL ESTADO DE YUCATÁN, ESTADOS UNIDOS MEXICANOS; {FECHA_TEXTO}\n" .
            "  4) Bloque de MESA DIRECTIVA:\n" .
            "     PRESIDENTE: DIP. NOMBRE.\n" .
            "     SECRETARIAS: DIP. NOMBRE. / DIP. NOMBRE.\n" .
            "- No pongas viñetas ni listas.\n\n" .

            "METADATOS:\n" .
            "Clave del acta: {$meta['clave_acta']}\n" .
            "Tipo de sesión: {$meta['tipo_sesion']}\n" .
            "Legislatura (texto): {$meta['legislatura_texto']}\n" .
            "Fecha (texto oficial): {$fechaTexto}\n" .
            "Ciudad: {$meta['ciudad']}\n" .
            "Recinto: {$meta['recinto']}\n" .
            "Presidente: DIP. {$nombrePres}\n" .
            "Secretaria 1: DIP. {$nombreS1}\n" .
            "Secretaria 2: " . ($nombreS2 ? "DIP. {$nombreS2}" : "(no aplica)") . "\n\n" .

            "REGLAS PARA primer_parrafo_ai:\n" .
            "- Párrafo formal, estilo acta oficial. Longitud: entre 600 y 900 caracteres.\n" .
            "- Incluir: ciudad, país, Diputados que integran la legislatura, recinto, tipo de sesión, periodo, ejercicio, debidamente convocados, fecha y hora.\n" .
            "- NO inventes datos que no estén en los metadatos.\n";

        $promptHash = hash('sha256', $prompt);

        $ai = $this->procesarEncabezadoClaude($prompt, $apiKey, $modelo);

        if (!$ai['__ok']) {
            echo json_encode([
                'ok'    => false,
                'error' => 'Claude no devolvió JSON válido',
                'debug' => $ai
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $encabezado = $ai['encabezado_ai'];
        $primerPar  = $ai['primer_parrafo_ai'];

        $ok = $this->actaModel->guardarEncabezadoAI($actaId, $encabezado, $primerPar, $modelo, $promptHash);

        echo json_encode([
            'ok'                        => (bool)$ok,
            'encabezado_ai'             => $encabezado,
            'primer_parrafo_ai'         => $primerPar,
            'encabezado_ai_model'       => $modelo,
            'encabezado_ai_prompt_hash' => $promptHash
        ], JSON_UNESCAPED_UNICODE);
    }

    private function procesarEncabezadoClaude(string $prompt, string $apiKey, string $modelo): array
    {
        $payload = [
            "model"       => $modelo,
            "max_tokens"  => 2500,
            "temperature" => 0.2,
            "system" => [
                [
                    "type" => "text",
                    "text" => "Eres redactor parlamentario del H. Congreso del Estado de Yucatán. " .
                              "Tu tarea es redactar ENCABEZADO y PRIMER PÁRRAFO de un acta oficial. " .
                              "No inventes datos. Devuelve ÚNICAMENTE JSON válido.",
                    "cache_control" => ["type" => "ephemeral"]
                ]
            ],
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ]
        ];

        $ch = curl_init("https://api.anthropic.com/v1/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "x-api-key: $apiKey",
                "anthropic-version: 2023-06-01",
                "anthropic-beta: prompt-caching-2024-07-31",
                "content-type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT    => 180
        ]);

        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        $cno  = curl_errno($ch);
        curl_close($ch);

        if ($http !== 200 || $resp === false) {
            return [
                '__ok'         => false,
                '__http'       => $http,
                '__curl_errno' => $cno,
                '__curl_error' => $cerr,
                '__resp_raw'   => is_string($resp) ? $resp : ''
            ];
        }

        $jsonOuter = json_decode($resp, true);
        $content   = trim((string)($jsonOuter['content'][0]['text'] ?? ''));

        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['encabezado_ai'], $decoded['primer_parrafo_ai'])) {
            $decoded['__ok'] = true;
            return $decoded;
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $m)) {
            $decoded2 = json_decode($m[0], true);
            if (is_array($decoded2) && isset($decoded2['encabezado_ai'], $decoded2['primer_parrafo_ai'])) {
                $decoded2['__ok'] = true;
                return $decoded2;
            }
        }

        return [
            '__ok'          => false,
            '__http'        => 200,
            '__parse_error' => true,
            '__content_raw' => $content,
            '__resp_raw'    => $resp
        ];
    }

    // ============================================================
    //  FECHA EN TEXTO
    // ============================================================
    private function fechaEnTexto($yyyy_mm_dd)
    {
        $t = strtotime($yyyy_mm_dd);
        if (!$t) return '';

        $dias = [
            1=>'uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve','diez',
            11=>'once','doce','trece','catorce','quince','dieciséis','diecisiete','dieciocho','diecinueve',
            20=>'veinte',21=>'veintiuno',22=>'veintidós',23=>'veintitrés',24=>'veinticuatro',25=>'veinticinco',
            26=>'veintiséis',27=>'veintisiete',28=>'veintiocho',29=>'veintinueve',30=>'treinta',31=>'treinta y uno'
        ];

        $meses = [
            1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
        ];

        $y = (int)date('Y', $t);
        $m = (int)date('n', $t);
        $d = (int)date('j', $t);

        $diaTxt  = $dias[$d] ?? (string)$d;
        $mesTxt  = $meses[$m] ?? '';
        $anioTxt = $this->anioEnTexto($y);

        return "FECHA " . mb_strtoupper($diaTxt, 'UTF-8') . " DE " . mb_strtoupper($mesTxt, 'UTF-8')
             . " DEL AÑO " . mb_strtoupper($anioTxt, 'UTF-8') . ".";
    }

    private function anioEnTexto($y)
    {
        if ($y < 2000 || $y > 2099) return (string)$y;

        $u = $y - 2000;
        if ($u === 0) return "dos mil";

        $map = [
            1=>"dos mil uno",2=>"dos mil dos",3=>"dos mil tres",4=>"dos mil cuatro",5=>"dos mil cinco",
            6=>"dos mil seis",7=>"dos mil siete",8=>"dos mil ocho",9=>"dos mil nueve",10=>"dos mil diez",
            11=>"dos mil once",12=>"dos mil doce",13=>"dos mil trece",14=>"dos mil catorce",15=>"dos mil quince",
            16=>"dos mil dieciséis",17=>"dos mil diecisiete",18=>"dos mil dieciocho",19=>"dos mil diecinueve",
            20=>"dos mil veinte",21=>"dos mil veintiuno",22=>"dos mil veintidós",23=>"dos mil veintitrés",
            24=>"dos mil veinticuatro",25=>"dos mil veinticinco",26=>"dos mil veintiséis",27=>"dos mil veintisiete",
            28=>"dos mil veintiocho",29=>"dos mil veintinueve",30=>"dos mil treinta",31=>"dos mil treinta y uno",
            32=>"dos mil treinta y dos",33=>"dos mil treinta y tres",34=>"dos mil treinta y cuatro",35=>"dos mil treinta y cinco",
            40=>"dos mil cuarenta",41=>"dos mil cuarenta y uno",42=>"dos mil cuarenta y dos",
            50=>"dos mil cincuenta"
        ];

        return $map[$u] ?? ("dos mil " . $u);
    }

    // ============================================================
    //  DESCARGAR WORD (ACTA)
    // ============================================================
    public function descargarWord()
    {
        $actaId = intval($_GET['acta_id'] ?? 0);
        if ($actaId <= 0) { die("acta_id inválido"); }

        $meta = $this->actaModel->obtenerMetadata($actaId);
        if (!$meta) { die("No hay metadatos para esta acta."); }

        $acta = $this->actaModel->obtenerPorId($actaId);
        if (!$acta) { die("Acta no encontrada."); }

        $encRaw    = trim((string)($meta['encabezado_ai'] ?? ''));
        $parRaw    = trim((string)($meta['primer_parrafo_ai'] ?? ''));
        $cuerpoRaw = trim((string)($acta['texto_acta'] ?? ''));

        if ($encRaw === '' || $parRaw === '' || $cuerpoRaw === '') {
            die("Falta encabezado, primer párrafo o cuerpo del acta.");
        }

        require_once __DIR__ . '/../vendor/autoload.php';

        $templatePath = __DIR__ . '/../templates/acta_template.docx';
        if (!file_exists($templatePath)) { die("No se encontró la plantilla: $templatePath"); }

        $claveActa  = trim((string)($meta['clave_acta'] ?? ''));
        [, $tituloActa] = $this->parseEncabezadoAI_simple($encRaw);

        $idPres = (int)($meta['presidente'] ?? 0);
        $idS1   = (int)($meta['secretaria_1'] ?? 0);
        $idS2   = (int)($meta['secretaria_2'] ?? 0);

        $usuarioModel = new UsuarioModel();

        $presNombre = $idPres ? $usuarioModel->obtenerNombrePorId($idPres) : '';
        $sec1Nombre = $idS1   ? $usuarioModel->obtenerNombrePorId($idS1)   : '';
        $sec2Nombre = $idS2   ? $usuarioModel->obtenerNombrePorId($idS2)   : '';

        $presTxt = $presNombre ? "DIP. " . $presNombre : '';
        $sec1Txt = $sec1Nombre ? "DIP. " . $sec1Nombre : '';
        $sec2Txt = $sec2Nombre ? "DIP. " . $sec2Nombre : '';

        $tpl = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

        if (method_exists($tpl, 'setOptions')) {
            $tpl->setOptions(['parseLineBreaks' => true, 'breakWords' => true]);
        }

        $tpl->setValue('CLAVE_ACTA', $claveActa);
        $tpl->setValue('TITULO_ACTA', $tituloActa);
        $tpl->setValue('PRESIDENTE', $presTxt);
        $tpl->setValue('SECRETARIO_1', $sec1Txt);
        $tpl->setValue('SECRETARIO_2', $sec2Txt);
        $tpl->setValue('FIRMA_PRESIDENTE', $presNombre ?: '');
        $tpl->setValue('FIRMA_SECRETARIO_1', $sec1Nombre ?: '');
        $tpl->setValue('FIRMA_SECRETARIO_2', $sec2Nombre ?: '');
        $tpl->setValue('PRIMER_PARRAFO', '___PRIMER_PARRAFO___');
        $tpl->setValue('CUERPO_ACTA',    '___CUERPO_ACTA___');

        $fileName = "Acta_$actaId.docx";
        $tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        $tpl->saveAs($tmpPath);

        $this->injectTextoJustificado($tmpPath, '___PRIMER_PARRAFO___', $parRaw);
        $this->injectTextoJustificado($tmpPath, '___CUERPO_ACTA___',    $cuerpoRaw);

        if (!file_exists($tmpPath) || filesize($tmpPath) < 2000) { die("No se generó el DOCX correctamente."); }

        header("Content-Description: File Transfer");
        header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Length: " . filesize($tmpPath));
        readfile($tmpPath);
        @unlink($tmpPath);
        exit;
    }

    /**
     * Inyecta texto multi-párrafo como XML Word real (un <w:p> por párrafo).
     * Evita el <w:br/> que causa justificado defectuoso cuando hay líneas cortas.
     */
    private function injectTextoJustificado(string $tmpPath, string $marcador, string $texto): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($tmpPath) !== true) return;

        $docXml = $zip->getFromName('word/document.xml');
        if ($docXml === false) { $zip->close(); return; }

        $pos = strpos($docXml, $marcador);
        if ($pos === false) { $zip->close(); return; }

        // Localizar el <w:p> que contiene el marcador
        $before   = substr($docXml, 0, $pos);
        $posP     = strrpos($before, '<w:p>');
        $posPS    = strrpos($before, '<w:p ');
        $startPos = max($posP !== false ? $posP : -1, $posPS !== false ? $posPS : -1);
        if ($startPos < 0) { $zip->close(); return; }

        $endPos = strpos($docXml, '</w:p>', $pos);
        if ($endPos === false) { $zip->close(); return; }
        $endPos += strlen('</w:p>');

        $parrafoTpl = substr($docXml, $startPos, $endPos - $startPos);

        // Extraer pPr (propiedades de párrafo) y garantizar justificado "both"
        $pPr = '<w:pPr><w:jc w:val="both"/></w:pPr>';
        if (preg_match('/<w:pPr>.*?<\/w:pPr>/s', $parrafoTpl, $m)) {
            $pPr = $m[0];
            if (!preg_match('/<w:jc\s/', $pPr)) {
                $pPr = str_replace('</w:pPr>', '<w:jc w:val="both"/></w:pPr>', $pPr);
            }
        }

        // Extraer rPr (propiedades de fuente/tamaño del run)
        $rPr = '';
        if (preg_match('/<w:rPr>.*?<\/w:rPr>/s', $parrafoTpl, $m)) {
            $rPr = $m[0];
        }

        // Construir XML: un <w:p> por cada párrafo + párrafo vacío de separación
        $parrafos = preg_split('/\n{2,}/', trim($texto));
        $parrafos = array_values(array_filter(array_map('trim', $parrafos)));

        // Párrafo vacío que actúa como línea en blanco entre párrafos
        $parrafoVacio = '<w:p>' . $pPr . '</w:p>';

        $newXml = '';
        $total  = count($parrafos);
        foreach ($parrafos as $idx => $p) {
            $lines  = explode("\n", $p);
            $runXml = '';
            foreach ($lines as $i => $linea) {
                if ($i > 0) $runXml .= '<w:br/>';
                $runXml .= '<w:t xml:space="preserve">' . htmlspecialchars($linea, ENT_XML1) . '</w:t>';
            }
            $newXml .= '<w:p>' . $pPr . '<w:r>' . $rPr . $runXml . '</w:r></w:p>';
            // Línea en blanco después de cada párrafo excepto el último
            if ($idx < $total - 1) {
                $newXml .= $parrafoVacio;
            }
        }

        $docXml = substr($docXml, 0, $startPos) . $newXml . substr($docXml, $endPos);
        $zip->addFromString('word/document.xml', $docXml);
        $zip->close();
    }

    private function parseEncabezadoAI_simple($encRaw)
    {
        $lines = preg_split("/\R+/", trim((string)$encRaw));
        $lines = array_values(array_filter(array_map('trim', $lines), fn($x) => $x !== ''));

        $titulo = '';
        foreach ($lines as $ln) {
            if ($titulo === '' && preg_match('/^ACTA DE LA\s+/iu', $ln)) {
                $titulo = $ln;
            }
        }

        // La clave ahora siempre viene de $meta['clave_acta'] en el caller;
        // devolvemos string vacío como primer elemento para mantener compatibilidad.
        return ['', $titulo];
    }

    // ============================================================
    //  GENERAR SÍNTESIS — single-pass (≤130 K chars) + map-reduce
    // ============================================================
    public function generarSintesis()
    {
        header('Content-Type: application/json; charset=utf-8');

        $actaId = intval($_POST['acta_id'] ?? 0);
        if ($actaId <= 0) {
            echo json_encode(['error' => 'acta_id inválido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $acta = $this->actaModel->obtenerPorId($actaId);
        if (!$acta) {
            echo json_encode(['error' => 'Acta no encontrada'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $textoActa = trim((string)($acta['texto_acta'] ?? ''));
        if ($textoActa === '') {
            echo json_encode(['error' => 'La acta no tiene texto_acta'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            echo json_encode(['error' => 'Falta ANTHROPIC_API_KEY en el servidor.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $modelo = 'claude-haiku-4-5-20251001';
        $chars  = mb_strlen($textoActa, 'UTF-8');
        $modo   = $chars <= 130000 ? 'single-pass' : 'map-reduce';

        $r = $chars <= 130000
            ? $this->sintesisSinglePassClaude($textoActa, $apiKey, $modelo)
            : $this->sintesisMapReduceClaude($textoActa, $apiKey, $modelo);

        if (empty($r['__ok'])) {
            echo json_encode(['error' => 'Error al generar síntesis', 'detalle' => $r], JSON_UNESCAPED_UNICODE);
            return;
        }

        $textoSintesis = trim((string)($r['sintesis'] ?? ''));
        if ($textoSintesis === '') {
            echo json_encode(['error' => 'La síntesis resultó vacía'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $charsSintesis = mb_strlen($textoSintesis, 'UTF-8');

        $ok = $this->actaModel->guardarSintesis($actaId, $textoSintesis, $charsSintesis);
        if (!$ok) {
            echo json_encode(['error' => 'No se pudo guardar la síntesis en BD'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok'            => true,
            'texto_sintesis'=> $textoSintesis,
            'chars_sintesis'=> $charsSintesis,
            'chars_acta'    => $chars,
            'pct_total'     => $chars > 0 ? round(($charsSintesis / $chars) * 100, 2) : 0,
            'modo'          => $modo,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sistemaPromptSintesis(): string
    {
        return
            "Eres un redactor experto del H. Congreso del Estado de Yucatán. " .
            "Tu tarea es generar una SÍNTESIS INSTITUCIONAL de un acta de sesión legislativa.\n\n" .
            "QUÉ CONSERVAR:\n" .
            "- Verificación de quórum y apertura (una oración)\n" .
            "- Aprobación del orden del día\n" .
            "- Cada punto tratado con su resultado\n" .
            "- Acuerdos y resoluciones (con votación si se menciona: a favor/en contra/abstenciones)\n" .
            "- Iniciativas: título resumido, proponente, turno dado\n" .
            "- Dictámenes: comisión, materia, resultado de votación\n" .
            "- Reservas al dictamen: diputado y motivo breve\n" .
            "- Intervenciones sustantivas que planteen argumentos o cambien el debate\n" .
            "- Mociones, propuestas de modificación, puntos de acuerdo\n" .
            "- Cierre de sesión\n\n" .
            "QUÉ OMITIR O COMPRIMIR:\n" .
            "- Lista completa de asistencia → solo 'Se verificó quórum legal'\n" .
            "- Fórmulas ceremoniales y saludos ('Con mucho gusto', saludos a redes sociales)\n" .
            "- Lectura textual de documentos ya identificados por título\n" .
            "- Trámites procedimentales sin contenido sustantivo\n\n" .
            "ESTILO: Institucional, narrativo, tercera persona, tiempos pasados. " .
            "NO inventes. NO incluyas lo que no esté en el texto original. " .
            "Devuelve ÚNICAMENTE JSON válido.";
    }

    private function sintesisSinglePassClaude(string $texto, string $apiKey, string $modelo): array
    {
        $payload = [
            "model"       => $modelo,
            "max_tokens"  => 8000,
            "temperature" => 0.15,
            "system" => [[
                "type"          => "text",
                "text"          => $this->sistemaPromptSintesis(),
                "cache_control" => ["type" => "ephemeral"]
            ]],
            "messages" => [[
                "role"    => "user",
                "content" =>
                    "Genera la síntesis del siguiente acta de sesión legislativa.\n\n" .
                    "ACTA COMPLETA:\n" . $texto . "\n\n" .
                    "FORMATO: Devuelve ÚNICAMENTE JSON válido:\n" .
                    '{"sintesis": "(texto de la síntesis)"}'
            ]]
        ];

        return $this->curlClaudeJson($payload, $apiKey);
    }

    private function sintesisMapReduceClaude(string $texto, string $apiKey, string $modelo): array
    {
        $chunks  = $this->splitTextSmartByNewline($texto, 25000, 0);
        $eventos = [];

        foreach ($chunks as $i => $chunk) {
            if ($i > 0) sleep(8);
            $r = $this->extraerEventosChunkClaude($chunk, $i + 1, count($chunks), $apiKey, $modelo);
            if (!empty($r['__ok']) && is_array($r['eventos'] ?? null)) {
                foreach ($r['eventos'] as $ev) {
                    $eventos[] = $ev;
                }
            }
        }

        if (empty($eventos)) {
            return ['__ok' => false, 'error' => 'No se pudieron extraer eventos del acta'];
        }

        return $this->narrativaDesdEventosClaude($eventos, $apiKey, $modelo);
    }

    private function extraerEventosChunkClaude(string $chunk, int $n, int $total, string $apiKey, string $modelo): array
    {
        $payload = [
            "model"       => $modelo,
            "max_tokens"  => 4000,
            "temperature" => 0.1,
            "system" => [[
                "type"          => "text",
                "text"          =>
                    "Eres un extractor de información legislativa. " .
                    "Identifica y extrae ÚNICAMENTE los hechos sustantivos de un fragmento de acta. " .
                    "No redactes narrativa. Solo extrae datos estructurados. " .
                    "Devuelve ÚNICAMENTE JSON válido.",
                "cache_control" => ["type" => "ephemeral"]
            ]],
            "messages" => [[
                "role"    => "user",
                "content" =>
                    "Fragmento {$n} de {$total}.\n\n" .
                    "FRAGMENTO DEL ACTA:\n" . $chunk . "\n\n" .
                    "Extrae todos los eventos sustantivos. Cada evento debe tener:\n" .
                    "- tipo: apertura|orden_dia|acuerdo|votacion|iniciativa|dictamen|reserva|intervencion|punto_acuerdo|cierre|otro\n" .
                    "- quien: nombre del diputado o comisión (null si no aplica)\n" .
                    "- descripcion: hecho concreto, máx 2 oraciones\n" .
                    "- resultado: resolución o resultado (null si no aplica)\n\n" .
                    "OMITIR: lista de asistencia, fórmulas ceremoniales, trámites sin contenido.\n\n" .
                    "FORMATO JSON:\n" .
                    '{"eventos": [{"tipo":"...","quien":"...","descripcion":"...","resultado":"..."}]}'
            ]]
        ];

        return $this->curlClaudeJson($payload, $apiKey);
    }

    private function narrativaDesdEventosClaude(array $eventos, string $apiKey, string $modelo): array
    {
        $eventosJson = json_encode($eventos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $payload = [
            "model"       => $modelo,
            "max_tokens"  => 8000,
            "temperature" => 0.2,
            "system" => [[
                "type"          => "text",
                "text"          => $this->sistemaPromptSintesis(),
                "cache_control" => ["type" => "ephemeral"]
            ]],
            "messages" => [[
                "role"    => "user",
                "content" =>
                    "A partir de los siguientes eventos extraídos de un acta legislativa, " .
                    "redacta una SÍNTESIS INSTITUCIONAL narrativa, completa y en orden cronológico.\n\n" .
                    "EVENTOS EXTRAÍDOS:\n" . $eventosJson . "\n\n" .
                    "FORMATO: Devuelve ÚNICAMENTE JSON válido:\n" .
                    '{"sintesis": "(texto narrativo de la síntesis)"}'
            ]]
        ];

        return $this->curlClaudeJson($payload, $apiKey);
    }
// ============================================================
    //  DEBUG SÍNTESIS CHUNKS
    // ============================================================
    public function debugSintesisChunks()
    {
        header('Content-Type: application/json; charset=utf-8');

        $actaId = intval($_GET['acta_id'] ?? 0);
        if ($actaId <= 0) {
            echo json_encode(['ok'=>false,'error'=>'acta_id inválido']);
            return;
        }

        $acta = $this->actaModel->obtenerPorId($actaId);
        if (!$acta) {
            echo json_encode(['ok'=>false,'error'=>'Acta no encontrada']);
            return;
        }

        $texto = trim((string)($acta['texto_acta'] ?? ''));
        if ($texto === '') {
            echo json_encode(['ok'=>false,'error'=>'El acta no tiene texto_acta']);
            return;
        }

        $target  = intval($_GET['target'] ?? 10000);
        $window  = intval($_GET['window'] ?? 600);
        $overlap = intval($_GET['overlap'] ?? 0);

        $chunks = $this->splitTextSmart($texto, $target, $window, $overlap);

        echo json_encode([
            'ok'           => true,
            'chars_total'  => mb_strlen($texto, 'UTF-8'),
            'target'       => $target,
            'window'       => $window,
            'overlap'      => $overlap,
            'total_chunks' => count($chunks),
            'chunks'       => array_map(function($t, $i){
                return [
                    'n'       => $i+1,
                    'len'     => mb_strlen($t, 'UTF-8'),
                    'preview' => mb_substr($t, 0, 220, 'UTF-8'),
                    'texto'   => $t
                ];
            }, $chunks, array_keys($chunks))
        ], JSON_UNESCAPED_UNICODE);
    }

    private function splitTextSmart(string $text, int $target=10000, int $window=600, int $overlap=0): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $len  = mb_strlen($text, 'UTF-8');

        $chunks = [];
        $pos    = 0;

        while ($pos < $len) {
            $end = min($pos + $target, $len);

            if ($end >= $len) {
                $chunks[] = trim(mb_substr($text, $pos, $len - $pos, 'UTF-8'));
                break;
            }

            $bestCut    = $end;
            $searchStart = max($pos + 1, $end - $window);
            $searchEnd   = min($len - 1, $end + $window);

            $cutBack = $this->mb_last_pos_in_range($text, "\n", $searchStart, $end);
            $cutFwd  = $this->mb_first_pos_in_range($text, "\n", $end, $searchEnd);

            if ($cutBack === null && $cutFwd === null) {
                $cutBack = $this->mb_last_pos_in_range($text, ".", $searchStart, $end);
                $cutFwd  = $this->mb_first_pos_in_range($text, ".", $end, $searchEnd);
                if ($cutBack === null && $cutFwd === null) {
                    $bestCut = $end;
                } else {
                    $bestCut = $this->closestTo($end, $cutBack, $cutFwd);
                    $bestCut = min($bestCut + 1, $len);
                }
            } else {
                $bestCut = $this->closestTo($end, $cutBack, $cutFwd);
                $bestCut = min($bestCut + 1, $len);
            }

            $piece = trim(mb_substr($text, $pos, $bestCut - $pos, 'UTF-8'));
            if ($piece !== '') $chunks[] = $piece;

            $pos = $overlap > 0 ? max($bestCut - $overlap, 0) : $bestCut;
        }

        return $chunks;
    }

    private function closestTo(int $center, ?int $a, ?int $b): int
    {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return (abs($center - $a) <= abs($center - $b)) ? $a : $b;
    }

    private function mb_last_pos_in_range(string $text, string $needle, int $start, int $end): ?int
    {
        $slice = mb_substr($text, $start, $end - $start, 'UTF-8');
        $pos   = mb_strrpos($slice, $needle, 0, 'UTF-8');
        if ($pos === false) return null;
        return $start + $pos;
    }

    private function mb_first_pos_in_range(string $text, string $needle, int $start, int $end): ?int
    {
        $slice = mb_substr($text, $start, $end - $start, 'UTF-8');
        $pos   = mb_strpos($slice, $needle, 0, 'UTF-8');
        if ($pos === false) return null;
        return $start + $pos;
    }

    // ============================================================
    //  DESCARGAR WORD SÍNTESIS
    // ============================================================
    public function descargarWordSintesis()
    {
        $actaId = intval($_GET['acta_id'] ?? 0);
        if ($actaId <= 0) { die("acta_id inválido"); }

        $meta = $this->actaModel->obtenerMetadata($actaId);
        if (!$meta) { die("No hay metadatos para esta acta."); }

        $acta = $this->actaModel->obtenerPorId($actaId);
        if (!$acta) { die("Acta no encontrada."); }

        $encRaw        = trim((string)($meta['encabezado_ai'] ?? ''));
        $cuerpoSintesis = trim((string)($acta['texto_sintesis'] ?? ''));

        if ($encRaw === '' || $cuerpoSintesis === '') {
            die("Falta encabezado_ai o texto_sintesis.");
        }

        require_once __DIR__ . '/../vendor/autoload.php';

        $templatePath = __DIR__ . '/../templates/sintesis_template.docx';
        if (!file_exists($templatePath)) { die("No se encontró la plantilla: $templatePath"); }

        $claveActa  = trim((string)($meta['clave_acta'] ?? ''));
        [, $tituloActa] = $this->parseEncabezadoAI_simple($encRaw);
        $tituloSintesis = $this->tituloActaToTituloSintesis($tituloActa);

        $idPres = (int)($meta['presidente'] ?? 0);
        $idS1   = (int)($meta['secretaria_1'] ?? 0);
        $idS2   = (int)($meta['secretaria_2'] ?? 0);

        $usuarioModel = new UsuarioModel();

        $presNombre = $idPres ? $usuarioModel->obtenerNombrePorId($idPres) : '';
        $sec1Nombre = $idS1   ? $usuarioModel->obtenerNombrePorId($idS1)   : '';
        $sec2Nombre = $idS2   ? $usuarioModel->obtenerNombrePorId($idS2)   : '';

        $presTxt = $presNombre ? "DIP. " . $presNombre : '';
        $sec1Txt = $sec1Nombre ? "DIP. " . $sec1Nombre : '';
        $sec2Txt = $sec2Nombre ? "DIP. " . $sec2Nombre : '';

        $tpl = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

        if (method_exists($tpl, 'setOptions')) {
            $tpl->setOptions(['parseLineBreaks' => true, 'breakWords' => true]);
        }

        $tpl->setValue('CLAVE_ACTA', $claveActa);
        $tpl->setValue('TITULO_SINTESIS', $tituloSintesis);
        $tpl->setValue('PRESIDENTE', $presTxt);
        $tpl->setValue('SECRETARIO_1', $sec1Txt);
        $tpl->setValue('SECRETARIO_2', $sec2Txt);
        $tpl->setValue('FIRMA_PRESIDENTE', $presNombre ?: '');
        $tpl->setValue('FIRMA_SECRETARIO_1', $sec1Nombre ?: '');
        $tpl->setValue('FIRMA_SECRETARIO_2', $sec2Nombre ?: '');
        $tpl->setValue('CUERPO_SINTESIS', '___CUERPO_SINTESIS___');

        $fileName = "Sintesis_Acta_$actaId.docx";
        $tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        $tpl->saveAs($tmpPath);

        $this->injectTextoJustificado($tmpPath, '___CUERPO_SINTESIS___', $cuerpoSintesis);

        if (!file_exists($tmpPath) || filesize($tmpPath) < 2000) { die("No se generó el DOCX correctamente."); }

        header("Content-Description: File Transfer");
        header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Length: " . filesize($tmpPath));
        readfile($tmpPath);
        @unlink($tmpPath);
        exit;
    }

    private function tituloActaToTituloSintesis(string $tituloActa): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $tituloActa));
        if (preg_match('/^ACTA DE LA\s+/iu', $t)) {
            return preg_replace('/^ACTA DE LA\s+/iu', 'SÍNTESIS DEL ACTA DE LA ', $t);
        }
        if (!preg_match('/^S[ÍI]NTESIS\s+/iu', $t)) {
            return 'SÍNTESIS DEL ' . $t;
        }
        return $t;
    }
}
