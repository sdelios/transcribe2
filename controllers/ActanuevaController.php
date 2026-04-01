<?php
require_once 'models/TranscripcionModel.php';
require_once 'models/CorreccionModel.php';
require_once 'models/ActaNuevaModel.php';
require_once 'models/UsuarioModel.php';

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
        // ✅ NUEVO: traer metadatos de sesión (mesa directiva) desde corrección
$metaSesion = $this->corrModel->obtenerMetadatosPorCorreccion($idCorreccion);
// $metaSesion trae: iIdPresidente, iIdSecretario1, iIdSecretario2, dFechaSesion, etc.

        if (!$ultimaCorr) {
            echo "No hay transcripción taquigráfica corregida. Primero realiza la revisión ortográfica.";
            return;
        }

        // ¿Ya existe acta generada?
        $acta = $this->actaModel->obtenerPorTranscripcion($idTrans);
        
        // ✅ NUEVO: Traer diputados activos para los selects del modal
        $usuarioModel = new UsuarioModel();
        // usa el método que te recomendé (o ajusta al nombre que tengas)
        $diputados = $usuarioModel->obtenerDiputadosActivos();

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

        // MODO DEBUG: si llega modo=debug, NO llama a OpenAI
        $modo = trim($_POST['modo'] ?? '');

        if ($transcripcionId <= 0 || !$textoFuente) {
            echo json_encode(['error' => 'Faltan parámetros.']);
            return;
        }

        // ================================
        // PARTE 1: Chunking inteligente
        // ================================
        $segmentos = $this->dividirPorParticipacion($textoFuente);
        $chunks    = $this->crearChunksInteligentes($segmentos, 14000);

        if (empty($chunks)) {
            echo json_encode(['error' => 'No se pudieron generar chunks a partir del texto fuente.']);
            return;
        }

        // ---- Configuración de progreso ----
        $totalChunks  = count($chunks);
        $rutaProgreso = __DIR__ . '/../tmp/progreso_acta.json';

        // Inicializar archivo de progreso (aunque sea debug)
        @file_put_contents(
            $rutaProgreso,
            json_encode([
                'actual'     => 0,
                'total'      => $totalChunks,
                'porcentaje' => 0
            ], JSON_UNESCAPED_UNICODE)
        );

        // ================================
        // MODO DEBUG: SOLO REGRESAR INFO
        // ================================
        if ($modo === 'debug') {
            // Simular avance rápido para ver la barra (opcional)
            // y regresarte datos útiles para validar cortes

            $vistaChunks = [];
            foreach ($chunks as $i => $c) {
                $porcentaje = $totalChunks > 0 ? round((($i+1) / $totalChunks) * 100) : 0;

                @file_put_contents(
                    $rutaProgreso,
                    json_encode([
                        'actual'     => ($i+1),
                        'total'      => $totalChunks,
                        'porcentaje' => $porcentaje
                    ], JSON_UNESCAPED_UNICODE)
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
                json_encode([
                    'actual'     => $totalChunks,
                    'total'      => $totalChunks,
                    'porcentaje' => 100
                ], JSON_UNESCAPED_UNICODE)
            );

            echo json_encode([
                'modo'      => 'debug',
                'segmentos' => count($segmentos),
                'total_chunks' => $totalChunks,
                'chunks'    => $vistaChunks
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        // ================================
        // PARTE 2: Pipeline OpenAI (ACTIVO)
        // ================================
       $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            echo json_encode(['error' => 'OPENAI_API_KEY no configurada en el entorno.']);
            return;
        }

        $modelo    = "gpt-4.1";
        $contexto  = "";
        $actaFinal = "";

        $indice = 0;

        foreach ($chunks as $chunk) {
                $indice++;

                // Calcular porcentaje (tu fórmula)
                $porcentaje = $totalChunks > 0 ? round(($indice / $totalChunks) * 100) : 0;

                // ✅ PASO 3: actualizar progreso con estado/mensaje (ANTES de llamar a OpenAI)
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

                // Llamada a OpenAI para este chunk
               // $respuesta = $this->procesarChunkOpenAI($chunk, $contexto, $apiKey, $modelo);
                    $respuesta = $this->procesarChunkOpenAIConReintentos($chunk, $contexto, $apiKey, $modelo, 5);

                    if (!is_array($respuesta) || empty($respuesta['__ok']) || !isset($respuesta['acta_fragmento'], $respuesta['resumen'])) {

                        $rutaError = __DIR__ . '/../tmp/error_acta.json';

                        @file_put_contents(
                            $rutaError,
                            json_encode([
                                'fecha'   => date('Y-m-d H:i:s'),
                                'chunk'   => $indice,
                                'total'   => $totalChunks,
                                'chunk_len' => mb_strlen($chunk, 'UTF-8'),
                                'chunk_inicio' => mb_substr($chunk, 0, 400, 'UTF-8'),
                                'contexto_len' => mb_strlen($contexto, 'UTF-8'),
                                'diagnostico' => $respuesta
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            LOCK_EX
                        );

                        echo json_encode([
                        'error' => 'Falló OpenAI (parse). Revisa tmp/error_acta.json',
                        'chunk' => $indice,
                        'total' => $totalChunks,
                        'json_error' => $respuesta['__json_error_msg'] ?? null,
                        'http' => $respuesta['__http'] ?? null
                        ], JSON_UNESCAPED_UNICODE);

                        return;
                    }


            $actaFinal .= $respuesta['acta_fragmento'] . "\n\n";
            $contexto   = $respuesta['resumen']; // Memoria para el siguiente chunk
        }

        // Al finalizar, dejamos el progreso en 100% explícitamente
        @file_put_contents(
            $rutaProgreso,
            json_encode([
                'actual'     => $totalChunks,
                'total'      => $totalChunks,
                'porcentaje' => 100
            ], JSON_UNESCAPED_UNICODE)
        );

        // ================================
        // PARTE 3: Guardar Base de Datos
        // ================================
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
            echo json_encode([
                'actual'     => 0,
                'total'      => 0,
                'porcentaje' => 0
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $contenido = file_get_contents($ruta);
        $json = json_decode($contenido, true);

        if (!$json) {
            echo json_encode([
                'actual'     => 0,
                'total'      => 0,
                'porcentaje' => 0
            ], JSON_UNESCAPED_UNICODE);
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

    /**
     * Detecta encabezados típicos de taquigrafía como:
     * "DIPUTADO PRESIDENTE ...:"
     * "DIPUTADA SECRETARIA ...:"
     * "PRESIDENCIA:"
     * "SECRETARIO:"
     * etc.
     *
     * Requisitos:
     * - inicio de línea (^)
     * - palabras en MAYÚSCULAS
     * - terminan con ":"
     */
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

        // ✅ Si una intervención sola excede el límite, la dividimos internamente
        if (mb_strlen($seg, 'UTF-8') > $maxLen) {

            // 1) Primero cerramos el chunk actual si tiene algo
            if (trim($actual) !== '') {
                $chunks[] = trim($actual);
                $actual = '';
            }

            // 2) Dividimos la intervención grande en subpartes
            $sub = $this->dividirIntervencionGrande($seg, $maxLen);

            foreach ($sub as $s) {
                $chunks[] = trim($s);
            }

            continue;
        }

        // ✅ Caso normal: intentar agregar al chunk actual
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

    // Intento 1: por párrafos
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
                $buffer = $p;
            } else {
                // Párrafo solo demasiado grande -> dividir por oraciones o corte duro
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

    // Marcador de continuidad (opcional pero recomendado)
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

    // Intento 2: por oraciones (punto + espacio)
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
                $buffer = $o;
            } else {
                // Último recurso: corte duro con traslape
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
    $len = mb_strlen($texto, 'UTF-8');
    if ($len <= $maxLen) return [$texto];

    $salida = [];
    $i = 0;

    while ($i < $len) {
        $chunk = mb_substr($texto, $i, $maxLen, 'UTF-8');
        $salida[] = $chunk;

        // avanzar dejando traslape para no perder contexto
        $i += ($maxLen - $overlap);
        if ($i < 0) break;
    }

    // Marcador continuidad
    if (count($salida) > 1) {
        foreach ($salida as $k => $t) {
            if ($k > 0) $salida[$k] = "[CONTINÚA MISMA INTERVENCIÓN]\n" . $t;
        }
    }

    return $salida;
}
    // ============================================================
    //  HELPER: Llamada a OpenAI (1 intento)
    // ============================================================
    private function procesarChunkOpenAI($chunk, $contexto, $apiKey, $modelo)
    {
        $payload = [
            "model" => $modelo,
            "temperature" => 0.15,
            "max_tokens" => 12000,
            "messages" => [
                [
                    "role" => "system",
                    "content" =>
                        "Eres redactor parlamentario del H. Congreso del Estado de Yucatán. " .
                        "Tu tarea es CONVERTIR una transcripción taquigráfica a redacción de ACTA oficial. " .
                        "Reglas: (1) NO inventes datos, (2) NO omitas intervenciones ni acuerdos, (3) NO resumas: conserva el contenido, " .
                        "(4) SÍ reescribe en tercera persona, tono institucional y formato narrativo de acta, " .
                        "(5) NO mantengas encabezados tipo 'DIPUTADO X:' salvo casos estrictamente necesarios; en su lugar usa fórmulas de acta: " .
                        "'La Presidencia concedió el uso de la palabra…', 'En uso de la palabra…', 'La Secretaría informó…', 'Se sometió a votación…'. " .
                        "Mantén nombres completos tal como aparecen. " .
                        "Devuelve ÚNICAMENTE JSON válido."
                ],
                [
                    "role" => "user",
                    "content" =>
                        "CONTEXTO ACUMULADO (muy breve, solo para continuidad):\n" . ($contexto ?? '') . "\n\n" .
                        "FRAGMENTO TAQUIGRÁFICO A CONVERTIR (NO RESUMIR):\n" . $chunk . "\n\n" .
                        "REGLAS CRÍTICAS (OBLIGATORIAS):\n" .
                        "1) NO RESUMAS NI CONDENSES. NO OMITAS INTERVENCIONES, ARGUMENTOS, EJEMPLOS NI DETALLES.\n" .
                        "2) SÍ reescribe a formato ACTA (tercera persona, tono institucional, conectores).\n" .
                        "3) CONSERVA LA EXTENSIÓN: el campo \"acta_fragmento\" debe medir entre 90% y 115% de la longitud del fragmento de entrada.\n" .
                        "   - Si el fragmento de entrada tiene X caracteres, tu salida debe estar entre 0.90X y 1.15X.\n" .
                        "4) No inventes datos. Si falta información (hora, lugar, etc.), no la agregues.\n" .
                        "5) Evita listas; redacta en narrativa corrida.\n\n" .
                        "FORMATO DE RESPUESTA: Devuelve ÚNICAMENTE JSON válido:\n" .
                        "{\n" .
                        "  \"acta_fragmento\": \"(texto del acta para ESTE chunk, largo y completo)\",\n" .
                        "  \"resumen\": \"(6-10 líneas máximo: acuerdos, estado del orden del día, quién habló y en qué tema, para continuidad)\"\n" .
                        "}\n\n" .
                        "IMPORTANTE: Si tu \"acta_fragmento\" queda más corto que 90% del fragmento, está MAL. Corrige antes de responder."
                ]
            ]
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return [
                '__ok' => false,
                '__http' => 0,
                '__curl_errno' => 0,
                '__curl_error' => null,
                '__parse_error' => false,
                '__json_error_msg' => 'json_encode(payload) falló: ' . json_last_error_msg(),
                '__content_raw' => null,
                '__resp_raw' => null
            ];
        }

        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$apiKey}",
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_CONNECTTIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $respRaw = curl_exec($ch);

        $cno  = curl_errno($ch);
        $cerr = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Error de transporte o HTTP != 200
        if ($respRaw === false || $http !== 200) {
            return [
                '__ok' => false,
                '__http' => $http,
                '__curl_errno' => $cno,
                '__curl_error' => $cerr,
                '__resp_raw' => is_string($respRaw) ? $respRaw : null
            ];
        }

        // Parse robusto del JSON dentro de message.content
        try {
            $parsed = $this->extraer_json_de_openai($respRaw);

            if (!isset($parsed['acta_fragmento'], $parsed['resumen'])) {
                throw new RuntimeException('Faltan llaves acta_fragmento/resumen');
            }

            return [
                '__ok' => true,
                '__http' => 200,
                'acta_fragmento' => $parsed['acta_fragmento'],
                'resumen' => $parsed['resumen'],
                '__resp_raw' => $respRaw
            ];

        } catch (Throwable $e) {
            return [
                '__ok' => false,
                '__http' => 200,
                '__parse_error' => true,
                '__json_error_msg' => $e->getMessage(),
                '__content_raw' => null,  // si quieres, aquí puedes guardar el content limpio (ver extraer_json_de_openai)
                '__resp_raw' => $respRaw
            ];
        }
    }


    // ============================================================
    //  HELPER: Llamada a OpenAI con reintentos
    // ============================================================
    private function procesarChunkOpenAIConReintentos($chunk, $contexto, $apiKey, $modelo, $maxReintentos = 5)
    {
        $ultimo = null;

        $payload = [
            "model" => $modelo,
            "temperature" => 0.15,
            "max_tokens" => 12000,
            "messages" => [
                [
                    "role" => "system",
                    "content" =>
                        "Eres redactor parlamentario del H. Congreso del Estado de Yucatán. " .
                        "Tu tarea es CONVERTIR una transcripción taquigráfica a redacción de ACTA oficial. " .
                        "Reglas: (1) NO inventes datos, (2) NO omitas intervenciones ni acuerdos, (3) NO resumas: conserva el contenido, " .
                        "(4) SÍ reescribe en tercera persona, tono institucional y formato narrativo de acta, " .
                        "(5) NO mantengas encabezados tipo 'DIPUTADO X:' salvo casos estrictamente necesarios; en su lugar usa fórmulas de acta. " .
                        "Devuelve ÚNICAMENTE JSON válido."
                ],
                [
                    "role" => "user",
                    "content" =>
                        "CONTEXTO ACUMULADO (muy breve, solo para continuidad):\n" . ($contexto ?? '') . "\n\n" .
                        "FRAGMENTO TAQUIGRÁFICO A CONVERTIR (NO RESUMIR):\n" . $chunk . "\n\n" .
                        "REGLAS CRÍTICAS (OBLIGATORIAS):\n" .
                        "1) NO RESUMAS NI CONDENSES. NO OMITAS INTERVENCIONES, ARGUMENTOS, EJEMPLOS NI DETALLES.\n" .
                        "2) SÍ reescribe a formato ACTA (tercera persona, tono institucional, conectores).\n" .
                        "3) CONSERVA LA EXTENSIÓN: \"acta_fragmento\" entre 90% y 115% del chunk.\n" .
                        "4) No inventes datos.\n" .
                        "5) Evita listas; redacta en narrativa corrida.\n\n" .
                        "RESPUESTA: Devuelve ÚNICAMENTE JSON válido con llaves: acta_fragmento, resumen."
                ]
            ]
        ];

        for ($i = 1; $i <= $maxReintentos; $i++) {

            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                return [
                    '__ok' => false,
                    '__http' => 0,
                    '__try' => $i,
                    '__parse_error' => false,
                    '__json_error_msg' => 'json_encode(payload) falló: ' . json_last_error_msg(),
                    '__resp_raw' => null,
                    '__curl_errno' => 0,
                    '__curl_error' => null,
                ];
            }

            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$apiKey}",
                    "Content-Type: application/json",
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 240,
                CURLOPT_CONNECTTIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $respRaw = curl_exec($ch);

            $cno  = curl_errno($ch);
            $cerr = curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            $diagnostico = [
                '__ok' => false,
                '__http' => $http,
                '__try' => $i,
                '__parse_error' => false,
                '__json_error_msg' => null,
                '__resp_raw' => is_string($respRaw) ? $respRaw : null,
                '__curl_errno' => $cno,
                '__curl_error' => $cerr,
            ];

            // Si cURL falló o no hay HTTP
            if ($respRaw === false || $http === 0) {
                $ultimo = $diagnostico;
                continue;
            }

            // Si no es 200 (429/500/etc), reintenta
            if ($http !== 200) {
                $ultimo = $diagnostico;
                continue;
            }

            // Parse robusto
            try {
                $parsed = $this->extraer_json_de_openai($respRaw);

                if (!isset($parsed['acta_fragmento'], $parsed['resumen'])) {
                    throw new RuntimeException('Faltan llaves acta_fragmento/resumen');
                }

                return [
                    '__ok' => true,
                    '__http' => 200,
                    '__try' => $i,
                    'acta_fragmento' => $parsed['acta_fragmento'],
                    'resumen' => $parsed['resumen'],
                    '__resp_raw' => $respRaw,
                ];

            } catch (Throwable $e) {
                $diagnostico['__parse_error'] = true;
                $diagnostico['__json_error_msg'] = $e->getMessage();
                $ultimo = $diagnostico;
                continue;
            }
        }

        return $ultimo ?: [
            '__ok' => false,
            '__http' => 0,
            '__try' => $maxReintentos,
            '__parse_error' => false,
            '__json_error_msg' => 'Sin diagnóstico',
            '__resp_raw' => null,
            '__curl_errno' => 0,
            '__curl_error' => null,
        ];
    }


    // ============================================================
    //  HELPER: Decodificación robusta de JSON (content)
    // ============================================================
    private function decode_json_robusto(string $s): array
    {
        $s = trim($s);

        // Si vino dentro de ```json ... ```
        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```(?:json)?\s*/i', '', $s);
            $s = preg_replace('/\s*```$/', '', $s);
            $s = trim($s);
        }

        // Recortar del primer { al último } por si llegó texto extra
        $p1 = strpos($s, '{');
        $p2 = strrpos($s, '}');
        if ($p1 !== false && $p2 !== false && $p2 > $p1) {
            $s = substr($s, $p1, $p2 - $p1 + 1);
        }

        // Forzar UTF-8 (clave para acentos/ñ desde Windows)
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        // Quitar caracteres de control invisibles (excepto \n\r\t)
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);

        $data = json_decode($s, true);
        if (!is_array($data)) {
            throw new RuntimeException('json_decode(content): ' . json_last_error_msg());
        }

        return $data;
    }


    // ============================================================
    //  HELPER: Extraer JSON (acta_fragmento/resumen) de respRaw OpenAI
    // ============================================================
    private function extraer_json_de_openai(string $respRaw): array
    {
        $resp = json_decode($respRaw, true);
        if (!is_array($resp)) {
            throw new RuntimeException('Respuesta OpenAI no es JSON: ' . json_last_error_msg());
        }

        $content = (string)($resp['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            throw new RuntimeException('OpenAI: choices[0].message.content vacío');
        }

        // OJO: aquí sale el JSON final (acta_fragmento/resumen)
        return $this->decode_json_robusto($content);
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

            $m = new ActaNuevaModel();
            $row = $m->obtenerMetadata($actaId);

            // ✅ si no hay registro, NO es error
            echo json_encode([
                'ok' => true,
                'data' => $row // null si no existe
            ], JSON_UNESCAPED_UNICODE);
        }

        public function guardarMetadata()
        {
            header('Content-Type: application/json; charset=utf-8');

            $actaId = intval($_POST['acta_id'] ?? 0);
            if ($actaId <= 0) {
                echo json_encode(['error' => 'acta_id inválido']);
                return;
            }

            // Limpieza básica
            $data = [
                'clave_acta'        => trim($_POST['clave_acta'] ?? ''),
                'tipo_sesion'       => trim($_POST['tipo_sesion'] ?? 'Ordinaria'),
                'legislatura'       => trim($_POST['legislatura'] ?? 'LXIV'),
                'legislatura_texto' => trim($_POST['legislatura_texto'] ?? ''),
                'periodo'           => trim($_POST['periodo'] ?? ''),
                'ejercicio'         => trim($_POST['ejercicio'] ?? ''),
                'fecha'             => trim($_POST['fecha'] ?? ''),        // YYYY-MM-DD
                'hora_inicio'       => trim($_POST['hora_inicio'] ?? ''),  // HH:MM (o HH:MM:SS)
                'ciudad'            => trim($_POST['ciudad'] ?? 'Mérida'),
                'recinto'           => trim($_POST['recinto'] ?? ''),
                'presidente'        => trim($_POST['presidente'] ?? ''),
                'secretaria_1'      => trim($_POST['secretaria_1'] ?? ''),
                'secretaria_2'      => trim($_POST['secretaria_2'] ?? ''),
            ];

            // ✅ Validación: no permitir duplicados entre presidente/secretarias
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

    public function generarEncabezadoAI()
            {
                header('Content-Type: application/json; charset=utf-8');

                $actaId = intval($_POST['acta_id'] ?? 0);
                if ($actaId <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'acta_id inválido'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                // 1) Traer metadata
                $meta = $this->actaModel->obtenerMetadata($actaId);
                if (!$meta) {
                    echo json_encode(['ok' => false, 'error' => 'Primero guarda los metadatos del acta.'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                // 2) Validación mínima (ajusta según tus campos obligatorios)
                $required = ['clave_acta','tipo_sesion','legislatura','legislatura_texto','periodo','ejercicio','fecha','hora_inicio','ciudad','recinto','presidente','secretaria_1'];
                foreach ($required as $k) {
                    if (!isset($meta[$k]) || trim((string)$meta[$k]) === '') {
                        echo json_encode(['ok' => false, 'error' => "Falta completar el metadato: $k"], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                }

                // 3) Validación duplicados (backend)
                $ids = array_filter([trim($meta['presidente'] ?? ''), trim($meta['secretaria_1'] ?? ''), trim($meta['secretaria_2'] ?? '')]);
                if (count($ids) !== count(array_unique($ids))) {
                    echo json_encode(['ok' => false, 'error' => 'No se puede repetir la misma persona en Presidente/Secretarías.'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                // 4) Resolver nombres por ID (usuarios)
                $u = new UsuarioModel();

                // 👉 Si aún NO tienes este método, te lo dejo más abajo para agregarlo
                $nombrePres = $u->obtenerNombrePorId($meta['presidente']);
                $nombreS1   = $u->obtenerNombrePorId($meta['secretaria_1']);
                $nombreS2   = trim((string)($meta['secretaria_2'] ?? '')) !== '' ? $u->obtenerNombrePorId($meta['secretaria_2']) : '';

                if (!$nombrePres || !$nombreS1) {
                    echo json_encode(['ok' => false, 'error' => 'No se pudo resolver nombre de Presidente/Secretaria en usuarios.'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $fechaISO = $meta['fecha']; // YYYY-MM-DD
                $fechaTexto = $this->fechaEnTexto($fechaISO); // "FECHA DOCE DE NOVIEMBRE DEL AÑO DOS MIL VEINTICINCO"

                // 5) Preparar prompt + hash (auditoría)
                $modelo = "gpt-4.1"; // cámbialo por el que ya uses
                $apiKey = getenv('OPENAI_API_KEY');
                if (!$apiKey) {
                    echo json_encode(['ok' => false, 'error' => 'Falta OPENAI_API_KEY en el servidor.'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                // Aquí armamos un prompt que produzca SOLO el encabezado y el primer párrafo
                $prompt =
                "Genera el ENCABEZADO y el PRIMER PÁRRAFO de un acta oficial del H. Congreso del Estado de Yucatán.\n" .
                "No inventes datos. Usa exclusivamente los metadatos proporcionados.\n" .
                "Devuelve ÚNICAMENTE JSON válido con estas llaves:\n" .
                "{ \"encabezado_ai\": \"...\", \"primer_parrafo_ai\": \"...\" }\n\n" .

                "REQUISITO PARA encabezado_ai (OBLIGATORIO):\n" .
                "- Debe incluir, en este orden:\n" .
                "  1) 'GOBIERNO DEL ESTADO DE YUCATÁN' y 'PODER LEGISLATIVO'\n" .
                "  2) La clave del acta\n" .
                "  3) Una línea/título en MAYÚSCULAS exactamente con esta estructura (respeta puntos y mayúsculas):\n" .
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
                "- Debe ser un párrafo formal, estilo acta oficial.\n" .
                "- Longitud obligatoria: entre 600 y 900 caracteres (aprox.).\n" .
                "- Debe incluir (siempre que exista en metadatos):\n" .
                "  * Ciudad (si es 'Mérida', escribe 'capital del Estado de Yucatán').\n" .
                "  * País: 'Estados Unidos Mexicanos'.\n" .
                "  * Que se reunieron los ciudadanos Diputados que integran la {LEGISLATURA_TEXTO} Legislatura.\n" .
                "  * Recinto y/o salón (recinto) donde se celebra.\n" .
                "  * Tipo de sesión, periodo y ejercicio constitucional.\n" .
                "  * Que fueron debidamente convocados.\n" .
                "  * La fecha en texto (usa la que se proporciona) y la hora (si existe).\n" .
                "- NO inventes datos que no estén en los metadatos.\n" .
                "- No resumas en 1 sola oración; debe sentirse como el ejemplo real.\n\n"
;


                $promptHash = hash('sha256', $prompt);

                // 6) Llamar OpenAI (con cURL similar a tu chunking)
                $ai = $this->procesarEncabezadoOpenAI($prompt, $apiKey, $modelo);

                if (!$ai['__ok']) {
                    echo json_encode([
                        'ok' => false,
                        'error' => 'OpenAI no devolvió JSON válido',
                        'debug' => $ai
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $encabezado = $ai['encabezado_ai'];
                $primerPar  = $ai['primer_parrafo_ai'];

                // 7) Guardar en BD
                $ok = $this->actaModel->guardarEncabezadoAI($actaId, $encabezado, $primerPar, $modelo, $promptHash);

                echo json_encode([
                    'ok' => (bool)$ok,
                    'encabezado_ai' => $encabezado,
                    'primer_parrafo_ai' => $primerPar,
                    'encabezado_ai_model' => $modelo,
                    'encabezado_ai_prompt_hash' => $promptHash
                ], JSON_UNESCAPED_UNICODE);
            }

        
    private function procesarEncabezadoOpenAI($prompt, $apiKey, $modelo)
        {
            $payload = [
                "model" => $modelo,
                "temperature" => 0.2,
                "max_tokens" => 2500,
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "Eres redactor parlamentario del H. Congreso del Estado de Yucatán. " .
                            "Tu tarea es redactar ENCABEZADO y PRIMER PÁRRAFO de un acta oficial. " .
                            "No inventes datos. Devuelve ÚNICAMENTE JSON válido."
                    ],
                    [
                        "role" => "user",
                        "content" => $prompt
                    ]
                ]
            ];

            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $apiKey",
                    "Content-Type: application/json"
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT => 180
            ]);

            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            $cno  = curl_errno($ch);
            curl_close($ch);

            if ($http !== 200 || $resp === false) {
                return [
                    '__ok' => false,
                    '__http' => $http,
                    '__curl_errno' => $cno,
                    '__curl_error' => $cerr,
                    '__resp_raw' => is_string($resp) ? $resp : ''
                ];
            }

            $json = json_decode($resp, true);
            $content = trim((string)($json['choices'][0]['message']['content'] ?? ''));

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
                '__ok' => false,
                '__http' => 200,
                '__parse_error' => true,
                '__content_raw' => $content,
                '__resp_raw' => $resp
            ];
    }

    private function fechaEnTexto($yyyy_mm_dd)
            {
                // Espera "YYYY-MM-DD"
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

                $diaTxt = $dias[$d] ?? (string)$d;
                $mesTxt = $meses[$m] ?? '';

                // Año (simple para 2000–2099; suficiente para tu uso)
                $anioTxt = $this->anioEnTexto($y);

                $s = "FECHA " . mb_strtoupper($diaTxt, 'UTF-8') . " DE " . mb_strtoupper($mesTxt, 'UTF-8')
                . " DEL AÑO " . mb_strtoupper($anioTxt, 'UTF-8') . ".";

                return $s;
            }

    private function anioEnTexto($y)
            {
                // 2000–2099
                if ($y < 2000 || $y > 2099) return (string)$y;

                $u = $y - 2000; // 0..99
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
                    36=>"dos mil treinta y seis",37=>"dos mil treinta y siete",38=>"dos mil treinta y ocho",39=>"dos mil treinta y nueve",
                    40=>"dos mil cuarenta",41=>"dos mil cuarenta y uno",42=>"dos mil cuarenta y dos",43=>"dos mil cuarenta y tres",
                    44=>"dos mil cuarenta y cuatro",45=>"dos mil cuarenta y cinco",46=>"dos mil cuarenta y seis",47=>"dos mil cuarenta y siete",
                    48=>"dos mil cuarenta y ocho",49=>"dos mil cuarenta y nueve",
                    50=>"dos mil cincuenta"
                    // Si quieres, completo hasta 99, pero con esto cubres bastante.
                ];

                return $map[$u] ?? ("dos mil " . $u);
            }


    public function descargarWord()
        {
            $actaId = intval($_GET['acta_id'] ?? 0);
            if ($actaId <= 0) { die("acta_id inválido"); }

            $meta = $this->actaModel->obtenerMetadata($actaId);
            if (!$meta) { die("No hay metadatos para esta acta."); }

            $acta = $this->actaModel->obtenerPorId($actaId);
            if (!$acta) { die("Acta no encontrada."); }

            $encRaw = trim((string)($meta['encabezado_ai'] ?? ''));
            $parRaw = trim((string)($meta['primer_parrafo_ai'] ?? ''));
            $cuerpoRaw = trim((string)($acta['texto_acta'] ?? ''));

            if ($encRaw === '' || $parRaw === '' || $cuerpoRaw === '') {
                die("Falta encabezado, primer párrafo o cuerpo del acta.");
            }

            require_once __DIR__ . '/../vendor/autoload.php';

            $templatePath = __DIR__ . '/../templates/acta_template.docx';
            if (!file_exists($templatePath)) {
                die("No se encontró la plantilla: $templatePath");
            }

            // 1) Partes del encabezado (solo clave y titulo; ya no "mesa")
            [$claveActa, $tituloActa] = $this->parseEncabezadoAI_simple($encRaw);

            // 2) Nombres de presidente y secretarios (desde IDs guardados en metadata)
            $idPres = (int)($meta['presidente'] ?? 0);
            $idS1   = (int)($meta['secretaria_1'] ?? 0);
            $idS2   = (int)($meta['secretaria_2'] ?? 0);

            $usuarioModel = new UsuarioModel();

            $presNombre = $idPres ? $usuarioModel->obtenerNombrePorId($idPres) : '';
            $sec1Nombre = $idS1   ? $usuarioModel->obtenerNombrePorId($idS1)   : '';
            $sec2Nombre = $idS2   ? $usuarioModel->obtenerNombrePorId($idS2)   : '';

            // Opcional: agregar "DIP. " si lo quieres siempre
            $presTxt = $presNombre ? "DIP. " . $presNombre : '';
            $sec1Txt = $sec1Nombre ? "DIP. " . $sec1Nombre : '';
            $sec2Txt = $sec2Nombre ? "DIP. " . $sec2Nombre : '';

            $tpl = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

            // Para saltos de línea
            if (method_exists($tpl, 'setOptions')) {
                $tpl->setOptions([
                    'parseLineBreaks' => true,
                    'breakWords' => true,
                ]);
            }

            // 3) Reemplazos
            $tpl->setValue('CLAVE_ACTA', $claveActa);
            $tpl->setValue('TITULO_ACTA', $tituloActa);

            $tpl->setValue('PRESIDENTE', $presTxt);
            $tpl->setValue('SECRETARIO_1', $sec1Txt);
            $tpl->setValue('SECRETARIO_2', $sec2Txt);

            // Si tienes área de firmas y quieres que sea igual:
            $tpl->setValue('FIRMA_PRESIDENTE', $presNombre ?: '');
            $tpl->setValue('FIRMA_SECRETARIO_1', $sec1Nombre ?: '');
            $tpl->setValue('FIRMA_SECRETARIO_2', $sec2Nombre ?: '');

            $tpl->setValue('PRIMER_PARRAFO', $parRaw);
            $tpl->setValue('CUERPO_ACTA', $cuerpoRaw);

            // 4) Guardar y descargar
            $fileName = "Acta_$actaId.docx";
            $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;

            $tpl->saveAs($tmpPath);

            if (!file_exists($tmpPath) || filesize($tmpPath) < 2000) {
                die("No se generó el DOCX correctamente. tmpPath=$tmpPath");
            }

            header("Content-Description: File Transfer");
            header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
            header("Content-Disposition: attachment; filename=\"$fileName\"");
            header("Content-Length: " . filesize($tmpPath));
            readfile($tmpPath);
            @unlink($tmpPath);
            exit;
        }



    private function tpText($text)
        {
            $text = (string)$text;

            // Escapar XML básico para no romper el docx
            $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            // Convertir saltos a <w:br/>
            return str_replace(["\r\n", "\n", "\r"], "</w:t><w:br/><w:t>", $text);
        }

    private function parseEncabezadoAI($encRaw)
        {
            $lines = preg_split("/\R+/", trim($encRaw));
            $lines = array_values(array_filter(array_map('trim', $lines), fn($x) => $x !== ''));

            // defaults
            $clave = '';
            $titulo = '';
            $mesa = '';

            // 1) Clave: buscamos una línea que empiece con "Acta" (ej "Acta 13/...")
            foreach ($lines as $ln) {
                if (preg_match('/^acta\s+/i', $ln)) {
                    $clave = $ln;
                    break;
                }
            }

            // 2) Título: línea que empiece con "ACTA DE LA"
            foreach ($lines as $ln) {
                if (preg_match('/^ACTA DE LA\s+/u', $ln) || preg_match('/^ACTA DE LA\s+/iu', $ln)) {
                    $titulo = $ln;
                    break;
                }
            }

            // 3) Mesa directiva: armamos “MESA DIRECTIVA: PRESIDENTE: ... SECRETARIOS: ...”
            // Soportamos tu formato: "PRESIDENTE: ..." y "SECRETARIAS:" o "SECRETARIOS:"
            $pres = '';
            $secs = [];

            foreach ($lines as $ln) {
                if (preg_match('/^PRESIDENTE:\s*(.+)$/iu', $ln, $m)) {
                    $pres = trim($m[1]);
                    continue;
                }
                if (preg_match('/^SECRETAR(?:IA|IAS|IO|IOS)?:\s*(.+)$/iu', $ln, $m)) {
                    // si viene en la misma línea
                    $rest = trim($m[1]);
                    if ($rest !== '') $secs[] = $rest;
                    continue;
                }
                // En tu encabezado actual a veces viene:
                // "SECRETARIAS:" y luego en líneas separadas los DIP.
                if (preg_match('/^DIP\./iu', $ln) && $pres !== '' && count($secs) < 2) {
                    // Heurística: si ya encontramos presidente y luego vienen DIP. como secretarías
                    // OJO: si tus encabezados traen más DIP. en otras partes, me dices y lo afinamos.
                    // Aquí tomamos máximo 2 secretarios.
                    $secs[] = $ln;
                }
            }

            // Normalizar: si el presidente ya viene sin "DIP.", no lo agregamos a fuerza, pero en tu ejemplo sí lo quieres
            $presTxt = $pres ?: '';
            $secTxt = '';

            // Algunos encabezados traen SECRETARIAS en dos líneas:
            // "DIP. X" / "DIP. Y"
            if (count($secs) > 0) {
                // si ya vienen dos, unimos con " / "
                $secTxt = implode(" / ", array_slice($secs, 0, 2));
            }

            // Construcción final como tú lo pediste
            // MESA DIRECTIVA: PRESIDENTE: "..."  SECRETARIOS: "..." 
            // (lo dejamos en una sola línea)
            $mesa = "MESA DIRECTIVA: ";
            $mesa .= "PRESIDENTE: " . ($presTxt ?: ''); 
            $mesa .= "  SECRETARIOS: " . ($secTxt ?: '');

            return [$clave, $titulo, $mesa];
        }

        private function parseEncabezadoAI_simple($encRaw)
        {
            $lines = preg_split("/\R+/", trim((string)$encRaw));
            $lines = array_values(array_filter(array_map('trim', $lines), fn($x) => $x !== ''));

            $clave = '';
            $titulo = '';

            foreach ($lines as $ln) {
                if ($clave === '' && preg_match('/^acta\s+/i', $ln)) {
                    $clave = $ln;
                }
                if ($titulo === '' && preg_match('/^ACTA DE LA\s+/iu', $ln)) {
                    $titulo = $ln;
                }
            }

            return [$clave, $titulo];
        }
//--------------------------------------------------------------------------------------------
// DE AQUI EN ADELANTE FUNCIONES PARA SINTESIS DEL ACTA
//--------------------------------------------------------------------------------------------
    public function generarSintesis()
        {
            header('Content-Type: application/json; charset=utf-8');

            $actaId = intval($_POST['acta_id'] ?? 0);
            if ($actaId <= 0) {
                echo json_encode(['error' => 'acta_id inválido'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 1) Obtener acta
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

            // 2) Config OpenAI (⚠️ NO dejes la key hardcodeada en producción)
            $apiKey = getenv('OPENAI_API_KEY');
            if (!$apiKey) {
                echo json_encode(['error' => 'Falta OPENAI_API_KEY en el servidor.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $modelo = 'gpt-4o-mini';

            // 3) Chunking inteligente
            $chunks = $this->splitTextSmartByNewline($textoActa, 10000, 500);

            // 4) Fase 1: síntesis por chunk con contexto acumulado
            $contexto = "";
            $sintesisChunks = [];
            $debug = [];

            foreach ($chunks as $i => $chunk) {

                $lenIn = mb_strlen($chunk, 'UTF-8');
                $min = (int)floor($lenIn * 0.50);
                $max = (int)floor($lenIn * 0.60);

                $r = $this->procesarChunkSintesisOpenAI($chunk, $contexto, $apiKey, $modelo);

                if (empty($r['__ok'])) {
                    echo json_encode([
                        'error' => 'Error al procesar chunk de síntesis',
                        'chunk' => $i + 1,
                        'detalle' => $r
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $sint = trim((string)($r['sintesis_chunk'] ?? ''));
                $ctx  = (string)($r['contexto_actualizado'] ?? $contexto);
                $lenOut = mb_strlen($sint, 'UTF-8');

                $reintento = false;

                // si salió corto, reintenta UNA VEZ con una instrucción de expansión
                if ($lenOut < $min) {
                    $r2 = $this->expandirSintesisChunkOpenAI($chunk, $contexto, $sint, $min, $max, $apiKey, $modelo);

                    if (!empty($r2['__ok'])) {
                        $sint2 = trim((string)($r2['sintesis_chunk'] ?? ''));
                        if ($sint2 !== '') {
                            $sint = $sint2;
                            $lenOut = mb_strlen($sint, 'UTF-8');
                            $ctx = (string)($r2['contexto_actualizado'] ?? $ctx);
                            $reintento = true;
                        }
                    }
                }

                // Guardar chunk final (solo sintesis_chunk)
                $sintesisChunks[] = $sint;

                // Actualiza contexto (NO lo pises después)
                $contexto = $this->truncateUtf8((string)$ctx, 1200);

                $debug[] = [
                    'n' => $i + 1,
                    'len_in' => $lenIn,
                    'min' => $min,
                    'max' => $max,
                    'len_out' => $lenOut,
                    'pct' => ($lenIn > 0) ? round(($lenOut / $lenIn) * 100, 2) : 0,
                    'reintento' => $reintento ? 1 : 0
                ];
            }

            // 5) Fase 2: (opcional) consolidación
            // Si tu fase 2 te “encoge” demasiado, comenta esto y usa solo el implode.
            $union = trim(implode("\n\n", $sintesisChunks));

            $final = $this->procesarSintesisFinalOpenAI($union, $apiKey, $modelo);

            if (empty($final['__ok'])) {
                echo json_encode([
                    'error' => 'Error al generar síntesis final',
                    'detalle' => $final,
                    'debug' => $debug
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $textoSintesis = trim((string)($final['texto_sintesis_final'] ?? ''));
            if ($textoSintesis === '') {
                // fallback: por si la fase 2 falla en contenido
                $textoSintesis = $union;
            }

            $charsSintesis = mb_strlen($textoSintesis, 'UTF-8');

            // 6) Guardar en BD
            $ok = $this->actaModel->guardarSintesis($actaId, $textoSintesis, $charsSintesis);
            if (!$ok) {
                echo json_encode(['error' => 'No se pudo guardar la síntesis en BD'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'ok' => true,
                'texto_sintesis' => $textoSintesis,
                'chars_sintesis' => $charsSintesis,
                'chars_acta' => mb_strlen($textoActa, 'UTF-8'),
                'pct_total' => (mb_strlen($textoActa, 'UTF-8') > 0) ? round(($charsSintesis / mb_strlen($textoActa, 'UTF-8')) * 100, 2) : 0,
                'chunks' => count($chunks),
                'debug' => $debug
            ], JSON_UNESCAPED_UNICODE);
        }


    private function splitTextSmartByNewline(string $text, int $targetLen = 10000, int $overlap = 500): array
            {
                $text = str_replace("\r\n", "\n", $text);
                $len = mb_strlen($text, 'UTF-8');
                if ($len <= $targetLen) return [$text];

                $chunks = [];
                $pos = 0;

                while ($pos < $len) {
                    $end = min($pos + $targetLen, $len);

                    if ($end < $len) {
                        // buscamos salto cerca de $end (preferir hacia atrás)
                        $cut = $this->findNearestNewlineCut($text, $end, 1200); // busca +/-1200 chars
                        if ($cut > $pos + 2000) { // evita chunks ridículamente pequeños
                            $end = $cut;
                        }
                    }

                    $chunk = $this->mb_substr_safe($text, $pos, $end - $pos);
                    $chunk = trim($chunk);

                    if ($chunk !== '') $chunks[] = $chunk;

                    if ($end >= $len) break;

                    // overlap: retrocede un poco para mantener continuidad
                    $pos = max(0, $end - $overlap);
                }

                return $chunks;
            }

    private function findNearestNewlineCut(string $text, int $targetPos, int $window = 1200): int
            {
                $len = mb_strlen($text, 'UTF-8');
                $start = max(0, $targetPos - $window);
                $end   = min($len, $targetPos + $window);

                $segment = $this->mb_substr_safe($text, $start, $end - $start);

                // buscamos el \n más cercano a targetPos: primero hacia atrás
                $relTarget = $targetPos - $start;

                // último \n antes de relTarget
                $before = mb_strrpos($segment, "\n", -(mb_strlen($segment, 'UTF-8') - $relTarget), 'UTF-8');
                if ($before !== false) {
                    return $start + (int)$before;
                }

                // si no hay atrás, buscamos el primero después
                $after = mb_strpos($segment, "\n", $relTarget, 'UTF-8');
                if ($after !== false) {
                    return $start + (int)$after;
                }

                // si no hay saltos, cortar donde estaba
                return $targetPos;
            }

    private function mb_substr_safe(string $text, int $start, int $length): string
            {
                return mb_substr($text, $start, $length, 'UTF-8');
            }

            private function truncateUtf8(string $text, int $maxChars): string
            {
                if (mb_strlen($text, 'UTF-8') <= $maxChars) return $text;
                return mb_substr($text, 0, $maxChars, 'UTF-8');
            }

        private function procesarChunkSintesisOpenAI(string $chunk, string $contexto, string $apiKey, string $modelo): array
        {
            $charsIn = mb_strlen($chunk, 'UTF-8');
            $minChars = (int)floor($charsIn * 0.50);
            $maxChars = (int)floor($charsIn * 0.60);

            $payload = [
                "model" => $modelo,
                "temperature" => 0.2,
                // ✅ 2500 es poco para 50-60%. Sube esto.
                "max_tokens" => 6000,
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "Eres un experto asesor legislativo del H. Congreso del Estado de Yucatán. " .
                            "Tu tarea es redactar una SÍNTESIS INSTITUCIONAL AMPLIA basada en un ACTA. " .
                            "No inventes información. Mantén tono formal. Devuelve ÚNICAMENTE JSON válido."
                    ],
                    [
                        "role" => "user",
                        "content" =>
                            "CONTEXTO ACUMULADO (breve, para continuidad):\n" . $contexto . "\n\n" .
                            "FRAGMENTO DEL ACTA (para sintetizar):\n" . $chunk . "\n\n" .
                            "OBJETIVO DE LONGITUD (CRÍTICO):\n" .
                            "- Entrada: {$charsIn} caracteres.\n" .
                            "- Tu salida \"sintesis_chunk\" DEBE medir entre {$minChars} y {$maxChars} caracteres (50%–60%).\n" .
                            "- Si tu salida queda por debajo de {$minChars}, está MAL: AMPLÍA antes de responder.\n\n" .
                            "INSTRUCCIONES:\n" .
                            "1) Genera una SÍNTESIS AMPLIA (no corta) del fragmento, conservando el orden de los hechos.\n" .
                            "2) Incluye acuerdos, votaciones, turnos, dictámenes, iniciativas, reservas, debates y puntos discutidos.\n" .
                            "3) No inventes información ni agregues hechos que no estén en el fragmento.\n" .
                            "4) Estilo institucional, redacción narrativa y clara.\n" .
                            "5) Devuelve contexto_actualizado (máx 10 líneas) para el siguiente fragmento.\n\n" .
                            "FORMATO: Devuelve ÚNICAMENTE JSON válido, sin texto extra:\n" .
                            "{\n" .
                            "  \"sintesis_chunk\": \"(texto amplio)\",\n" .
                            "  \"contexto_actualizado\": \"(máx 10 líneas)\"\n" .
                            "}"
                    ]
                ]
            ];

            return $this->curlOpenAIJson($payload, $apiKey);
        }


private function procesarSintesisFinalOpenAI(string $texto, string $apiKey, string $modelo): array
{
    $charsIn = mb_strlen($texto, 'UTF-8');
    $minChars = (int)floor($charsIn * 0.95);
    $maxChars = (int)floor($charsIn * 1.05);

    $payload = [
        "model" => $modelo,
        "temperature" => 0.15,
        "max_tokens" => 12000,
        "messages" => [
            [
                "role" => "system",
                "content" =>
                    "Eres redactor legislativo del H. Congreso del Estado de Yucatán. " .
                    "Tu tarea es PULIR un texto ya sintetizado: mejorar fluidez, cohesión y eliminar repeticiones obvias. " .
                    "NO RESUMAS. NO ACORTES de forma significativa. NO inventes. Devuelve ÚNICAMENTE JSON válido."
            ],
            [
                "role" => "user",
                "content" =>
                    "TEXTO A PULIR (ya es síntesis):\n" . $texto . "\n\n" .
                    "REGLAS CRÍTICAS:\n" .
                    "1) NO RESUMAS NI CONDENSES.\n" .
                    "2) Mantén el orden de los hechos.\n" .
                    "3) Longitud objetivo: entre {$minChars} y {$maxChars} caracteres.\n" .
                    "   Si quedas por debajo de {$minChars}, está MAL: reescribe para conservar longitud.\n\n" .
                    "FORMATO: Devuelve solo JSON:\n" .
                    "{\n" .
                    "  \"texto_sintesis_final\": \"...\"\n" .
                    "}"
            ]
        ]
    ];

    return $this->curlOpenAIJson($payload, $apiKey);
}


        private function curlOpenAIJson(array $payload, string $apiKey): array
        {
            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $apiKey",
                    "Content-Type: application/json"
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT => 240
            ]);

            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            $cno  = curl_errno($ch);
            curl_close($ch);

            if ($http !== 200 || $resp === false) {
                return [
                    '__ok' => false,
                    '__http' => $http,
                    '__curl_errno' => $cno,
                    '__curl_error' => $cerr,
                    '__resp_raw' => is_string($resp) ? $resp : ''
                ];
            }

            $json = json_decode($resp, true);
            $content = trim((string)($json['choices'][0]['message']['content'] ?? ''));

            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $decoded['__ok'] = true;
                return $decoded;
            }

            // intenta extraer JSON si vino con texto extra
            if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $m)) {
                $decoded2 = json_decode($m[0], true);
                if (is_array($decoded2)) {
                    $decoded2['__ok'] = true;
                    return $decoded2;
                }
            }

            return [
                '__ok' => false,
                '__http' => 200,
                '__parse_error' => true,
                '__content_raw' => $content,
                '__resp_raw' => $resp
            ];
        }

        private function expandirSintesisChunkOpenAI(string $chunk, string $contexto, string $borrador, int $minChars, int $maxChars, string $apiKey, string $modelo): array
        {
            $payload = [
                "model" => $modelo,
                "temperature" => 0.2,
                "max_tokens" => 6500,
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "Eres un experto asesor legislativo del H. Congreso del Estado de Yucatán. " .
                            "Vas a REESCRIBIR y AMPLIAR una síntesis para cumplir un rango de longitud sin inventar datos. " .
                            "Devuelve ÚNICAMENTE JSON válido."
                    ],
                    [
                        "role" => "user",
                        "content" =>
                            "CONTEXTO:\n" . $contexto . "\n\n" .
                            "FRAGMENTO ORIGINAL DEL ACTA:\n" . $chunk . "\n\n" .
                            "SÍNTESIS ACTUAL (DEMASIADO CORTA):\n" . $borrador . "\n\n" .
                            "OBJETIVO (CRÍTICO):\n" .
                            "- La nueva \"sintesis_chunk\" debe medir entre {$minChars} y {$maxChars} caracteres.\n" .
                            "- Amplía agregando detalles relevantes del fragmento (intervenciones, argumentos, acuerdos), SIN inventar.\n\n" .
                            "FORMATO: JSON válido:\n" .
                            "{\n" .
                            "  \"sintesis_chunk\": \"...\",\n" .
                            "  \"contexto_actualizado\": \"(máx 10 líneas)\"\n" .
                            "}"
                    ]
                ]
            ];

            return $this->curlOpenAIJson($payload, $apiKey);
        }
// para debug y probar chunkig de la sintesis
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

            $texto = (string)($acta['texto_acta'] ?? '');
            $texto = trim($texto);
            if ($texto === '') {
                echo json_encode(['ok'=>false,'error'=>'El acta no tiene texto_acta']);
                return;
            }

            $target = intval($_GET['target'] ?? 10000);     // tamaño ideal
            $window = intval($_GET['window'] ?? 600);       // margen para buscar salto
            $overlap = intval($_GET['overlap'] ?? 0);       // si quieres solapado (luego)

            $chunks = $this->splitTextSmart($texto, $target, $window, $overlap);

            echo json_encode([
                'ok' => true,
                'chars_total' => mb_strlen($texto, 'UTF-8'),
                'target' => $target,
                'window' => $window,
                'overlap' => $overlap,
                'total_chunks' => count($chunks),
                'chunks' => array_map(function($t, $i){
                    return [
                        'n' => $i+1,
                        'len' => mb_strlen($t, 'UTF-8'),
                        'preview' => mb_substr($t, 0, 220, 'UTF-8'),
                        'texto' => $t
                    ];
                }, $chunks, array_keys($chunks))
            ], JSON_UNESCAPED_UNICODE);
        }

        private function splitTextSmart(string $text, int $target=10000, int $window=600, int $overlap=0): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $len = mb_strlen($text, 'UTF-8');

    $chunks = [];
    $pos = 0;

    while ($pos < $len) {

        $end = min($pos + $target, $len);

        // si ya es el último, corta y termina
        if ($end >= $len) {
            $chunks[] = trim(mb_substr($text, $pos, $len - $pos, 'UTF-8'));
            break;
        }

        // buscamos el salto de línea más cercano al "end"
        $bestCut = $end;

        // rango de búsqueda alrededor del end
        $searchStart = max($pos + 1, $end - $window);
        $searchEnd   = min($len - 1, $end + $window);

        // buscamos hacia atrás el último "\n"
        $cutBack = $this->mb_last_pos_in_range($text, "\n", $searchStart, $end);

        // buscamos hacia adelante el primer "\n"
        $cutFwd  = $this->mb_first_pos_in_range($text, "\n", $end, $searchEnd);

        if ($cutBack === null && $cutFwd === null) {
            // fallback: busca punto cerca (.)
            $cutBack = $this->mb_last_pos_in_range($text, ".", $searchStart, $end);
            $cutFwd  = $this->mb_first_pos_in_range($text, ".", $end, $searchEnd);

            if ($cutBack === null && $cutFwd === null) {
                $bestCut = $end; // fallback duro
            } else {
                $bestCut = $this->closestTo($end, $cutBack, $cutFwd);
                $bestCut = min($bestCut + 1, $len); // incluye el punto
            }
        } else {
            $bestCut = $this->closestTo($end, $cutBack, $cutFwd);
            $bestCut = min($bestCut + 1, $len); // incluye el \n
        }

        $piece = trim(mb_substr($text, $pos, $bestCut - $pos, 'UTF-8'));
        if ($piece !== '') $chunks[] = $piece;

        // avance con overlap opcional
        if ($overlap > 0) {
            $pos = max($bestCut - $overlap, 0);
        } else {
            $pos = $bestCut;
        }
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
    $pos = mb_strrpos($slice, $needle, 0, 'UTF-8');
    if ($pos === false) return null;
    return $start + $pos;
}

private function mb_first_pos_in_range(string $text, string $needle, int $start, int $end): ?int
{
    $slice = mb_substr($text, $start, $end - $start, 'UTF-8');
    $pos = mb_strpos($slice, $needle, 0, 'UTF-8');
    if ($pos === false) return null;
    return $start + $pos;
}


// terminan debug sintesis

public function descargarWordSintesis()
{
    $actaId = intval($_GET['acta_id'] ?? 0);
    if ($actaId <= 0) { die("acta_id inválido"); }

    $meta = $this->actaModel->obtenerMetadata($actaId);
    if (!$meta) { die("No hay metadatos para esta acta."); }

    $acta = $this->actaModel->obtenerPorId($actaId);
    if (!$acta) { die("Acta no encontrada."); }

    $encRaw = trim((string)($meta['encabezado_ai'] ?? ''));
    $cuerpoSintesis = trim((string)($acta['texto_sintesis'] ?? ''));

    if ($encRaw === '' || $cuerpoSintesis === '') {
        die("Falta encabezado_ai o texto_sintesis.");
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    // Plantilla específica de síntesis
    $templatePath = __DIR__ . '/../templates/sintesis_template.docx';
    if (!file_exists($templatePath)) {
        die("No se encontró la plantilla: $templatePath");
    }

    // 1) Clave y título base desde encabezado del acta
    [$claveActa, $tituloActa] = $this->parseEncabezadoAI_simple($encRaw);

    // 2) Convertir el título a "SÍNTESIS DEL ..."
    $tituloSintesis = $this->tituloActaToTituloSintesis($tituloActa);

    // 3) Nombres desde IDs (igual que tu Word del acta)
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
        $tpl->setOptions([
            'parseLineBreaks' => true,
            'breakWords' => true,
        ]);
    }

    // 4) Reemplazos (ajusta marcadores en tu plantilla)
    $tpl->setValue('CLAVE_ACTA', $claveActa);
    $tpl->setValue('TITULO_SINTESIS', $tituloSintesis);

    $tpl->setValue('PRESIDENTE', $presTxt);
    $tpl->setValue('SECRETARIO_1', $sec1Txt);
    $tpl->setValue('SECRETARIO_2', $sec2Txt);

    // Si firmas:
    $tpl->setValue('FIRMA_PRESIDENTE', $presNombre ?: '');
    $tpl->setValue('FIRMA_SECRETARIO_1', $sec1Nombre ?: '');
    $tpl->setValue('FIRMA_SECRETARIO_2', $sec2Nombre ?: '');

    $tpl->setValue('CUERPO_SINTESIS', $cuerpoSintesis);

    // 5) Guardar y descargar
    $fileName = "Sintesis_Acta_$actaId.docx";
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;

    $tpl->saveAs($tmpPath);

    if (!file_exists($tmpPath) || filesize($tmpPath) < 2000) {
        die("No se generó el DOCX correctamente. tmpPath=$tmpPath");
    }

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
    $t = trim($tituloActa);

    // normaliza espacios
    $t = preg_replace('/\s+/u', ' ', $t);

    // Si empieza con "ACTA DE LA", lo convertimos
    if (preg_match('/^ACTA DE LA\s+/iu', $t)) {
        return preg_replace('/^ACTA DE LA\s+/iu', 'SÍNTESIS DEL ACTA DE LA ', $t);
    }

    // fallback
    if (!preg_match('/^S[ÍI]NTESIS\s+/iu', $t)) {
        return 'SÍNTESIS DEL ' . $t;
    }

    return $t;
}



}
