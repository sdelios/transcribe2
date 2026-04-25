<?php
require_once 'models/TranscripcionModel.php';
require_once 'models/CorreccionModel.php';
require_once 'models/UsuarioModel.php';

class CorreccionController
{
    private $modelo;

    public function __construct() {
        $this->modelo = new CorreccionModel();
    }

    private function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    // ============================================================
    //                        VISTA DE REVISIÓN
    // ============================================================
    public function iniciar() {

        $this->ensureSession();

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) { echo "ID inválido"; return; }

        $transModel = new TranscripcionModel();
        $trans = $transModel->obtenerPorId($id);
        if (!$trans) { echo "Transcripción no encontrada"; return; }

        $usuarioModel = new UsuarioModel();
        $diputadosDB = $usuarioModel->obtenerDiputadosActivos();
        $diputados = array_map(fn($d) => $d['nombre'], $diputadosDB);

        $ultimaCorreccion = $this->modelo->obtenerUltima($id);

        // ✅ guardar en sesión para fallbacks
        if ($ultimaCorreccion && !empty($ultimaCorreccion['id'])) {
            $_SESSION['ultima_correccion_por_trans'][$id] = (int)$ultimaCorreccion['id'];
        }

        $tiposSesion = $this->modelo->obtenerTiposSesionActivos();

        $metadatosSesion = null;
        if ($ultimaCorreccion) {
            $metadatosSesion = $this->modelo->obtenerMetadatosPorCorreccion((int)$ultimaCorreccion['id']);
        }

        $metaGuardada = false;
        $metaToken = '';
        if (!empty($_SESSION['correccion_meta'][$id])) {
            $metaGuardada = true;
            $metaToken = $_SESSION['correccion_meta'][$id]['token'] ?? '';
            if (!$metadatosSesion) {
                $metadatosSesion = $_SESSION['correccion_meta'][$id]['data'] ?? null;
            }
        }

        $tipoInicial = null;
        if ($metadatosSesion && !empty($metadatosSesion['iIdCatTipoSesiones'])) {
            $tipoInicial = (int)$metadatosSesion['iIdCatTipoSesiones'];
        } elseif (!empty($tiposSesion[0]['iIdCatTipoSesiones'])) {
            $tipoInicial = (int)$tiposSesion[0]['iIdCatTipoSesiones'];
        }

        $sesionesIniciales = $tipoInicial ? $this->modelo->obtenerSesionesPorTipo($tipoInicial) : [];

        $view = __DIR__ . '/../views/correcciones/iniciar.php';
        include __DIR__ . '/../layout.php';
    }

    // ============================================================
    //          ENDPOINT: SESIONES POR TIPO
    // ============================================================
    public function sesionesPorTipo() {
        header('Content-Type: application/json; charset=utf-8');

        $idTipo = (int)($_GET['idTipo'] ?? 0);
        if ($idTipo <= 0) {
            echo json_encode(['ok'=>false,'error'=>'idTipo inválido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->modelo->obtenerSesionesPorTipo($idTipo);
        echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //        GUARDAR METADATOS -> SESSION + BD (sesion_metadatos)
    //        Si no existe corrección, crea placeholder y regresa id
    // ============================================================
    public function guardarMetadatos() {

        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $idTrans = (int)($_POST['id'] ?? 0);
        if ($idTrans <= 0) {
            echo json_encode(['ok'=>false,'error'=>'ID de transcripción inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $iIdCatTipoSesiones = (int)($_POST['iIdCatTipoSesiones'] ?? 0);
        $iIdCatSesionRaw = $_POST['iIdCatSesion'] ?? null;
        $iIdCatSesion = ($iIdCatSesionRaw === '' || $iIdCatSesionRaw === null) ? 0 : (int)$iIdCatSesionRaw;

        $dFechaSesion = $_POST['dFechaSesion'] ?? null;

        $iIdPresidente     = (int)($_POST['iIdPresidente'] ?? 0);
        $iIdVicepresidente = (int)($_POST['iIdVicepresidente'] ?? 0);
        $iIdSecretario1    = (int)($_POST['iIdSecretario1'] ?? 0);
        $iIdSecretario2    = (int)($_POST['iIdSecretario2'] ?? 0);

        $jAsistentes = $_POST['jAsistentes'] ?? '[]';
        $asist = json_decode($jAsistentes, true);
        if (!is_array($asist)) $asist = [];

        if ($iIdCatTipoSesiones <= 0) {
            echo json_encode(['ok'=>false,'error'=>'Selecciona el Tipo de sesión.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$dFechaSesion) {
            echo json_encode(['ok'=>false,'error'=>'Selecciona la Fecha de sesión.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $modalidad = $this->modelo->obtenerModalidadTipo($iIdCatTipoSesiones);

        if ($modalidad === 'pleno') {
            if ($iIdPresidente <= 0 || $iIdSecretario1 <= 0 || $iIdSecretario2 <= 0) {
                echo json_encode(['ok'=>false,'error'=>'Selecciona Presidente, Secretario 1 y Secretario 2.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $mesa = [$iIdPresidente, $iIdSecretario1, $iIdSecretario2];
        } else {
            if ($iIdPresidente <= 0 || $iIdVicepresidente <= 0 || $iIdSecretario1 <= 0 || $iIdSecretario2 <= 0) {
                echo json_encode(['ok'=>false,'error'=>'Selecciona Presidente, Vicepresidente, Secretario 1 y Secretario 2.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $mesa = [$iIdPresidente, $iIdVicepresidente, $iIdSecretario1, $iIdSecretario2];
        }

        if (count(array_unique($mesa)) !== count($mesa)) {
            echo json_encode(['ok'=>false,'error'=>'La Mesa Directiva no puede tener personas repetidas.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $asist = array_values(array_unique(array_map('intval', $asist)));
        $asist = array_values(array_diff($asist, $mesa));

        // ✅ Determinar/crear corrección para amarrar metadatos a BD
        $ultima = $this->modelo->obtenerUltima($idTrans);
        $idCorreccion = $ultima && !empty($ultima['id']) ? (int)$ultima['id'] : 0;

        if ($idCorreccion <= 0) {
            $transModel = new TranscripcionModel();
            $trans = $transModel->obtenerPorId($idTrans);
            $textoOriginal = $trans['tTrans'] ?? '';
            $len = mb_strlen($textoOriginal);

            $newId = $this->modelo->guardar($idTrans, $len, $len, $textoOriginal);
            if (!$newId) {
                echo json_encode(['ok'=>false,'error'=>'No se pudo crear la corrección placeholder para guardar metadatos.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $idCorreccion = (int)$newId;
        }

        $_SESSION['ultima_correccion_por_trans'][$idTrans] = $idCorreccion;

        $token = bin2hex(random_bytes(16));

        $_SESSION['correccion_meta'][$idTrans] = [
            'token' => $token,
            'data' => [
                'iIdCorreccion'      => $idCorreccion,
                'iIdTranscripcion'   => $idTrans,
                'iIdCatTipoSesiones' => $iIdCatTipoSesiones,
                'iIdCatSesion'       => $iIdCatSesion,
                'dFechaSesion'       => $dFechaSesion,
                'iIdPresidente'      => $iIdPresidente,
                'iIdVicepresidente'  => $iIdVicepresidente,
                'iIdSecretario1'     => $iIdSecretario1,
                'iIdSecretario2'     => $iIdSecretario2,
                'jAsistentes'        => json_encode($asist, JSON_UNESCAPED_UNICODE),
                'cModalidadSesion'   => $modalidad,
            ]
        ];

        $metaDb = $this->modelo->upsertMetadatosSesion([
            'iIdCorreccion'      => $idCorreccion,
            'iIdTranscripcion'   => $idTrans,
            'iIdCatTipoSesiones' => $iIdCatTipoSesiones,
            'iIdCatSesion'       => $iIdCatSesion,
            'iIdPresidente'      => $iIdPresidente,
            'iIdVicepresidente'  => $iIdVicepresidente,
            'iIdSecretario1'     => $iIdSecretario1,
            'iIdSecretario2'     => $iIdSecretario2,
            'jAsistentes'        => json_encode($asist, JSON_UNESCAPED_UNICODE),
            'dFechaSesion'       => $dFechaSesion,
            'tObservaciones'     => null
        ]);

        $okMeta = is_array($metaDb) ? (bool)($metaDb['ok'] ?? false) : (bool)$metaDb;

        if (!$okMeta) {
            echo json_encode([
                'ok' => false,
                'error' => 'Metadatos guardados en sesión, pero FALLÓ guardar en BD.',
                'id_correccion' => $idCorreccion,
                'token' => $token,
                'meta_db' => $metaDb
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok'=>true,
            'token'=>$token,
            'id_correccion' => $idCorreccion,
            'meta_db' => $metaDb
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //                     PROCESAR CORRECCIÓN (Claude API)
    // ============================================================
    public function procesar() {

        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $id = intval($_POST['id'] ?? 0);
        $texto = $_POST['texto'] ?? '';
        $nombres = $_POST['nombres'] ?? '[]';
        $idCorreccion = $_POST['correccion_id'] ?? '';

        $jInasistencias = $_POST['jInasistencias'] ?? '[]';

        $tokenCliente = $_POST['meta_token'] ?? '';
        $metaSession = $_SESSION['correccion_meta'][$id] ?? null;

        if (!$metaSession || empty($metaSession['token']) || $metaSession['token'] !== $tokenCliente) {
            echo json_encode([
                'error' => 'Primero guarda los metadatos de la sesión (botón 💾 Guardar metadatos) antes de corregir.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $meta = $metaSession['data'] ?? [];

        $iIdCatTipoSesiones = (int)($meta['iIdCatTipoSesiones'] ?? 0);
        $iIdCatSesion = (int)($meta['iIdCatSesion'] ?? 0);

        $dFechaSesion = $meta['dFechaSesion'] ?? null;

        $iIdPresidente     = (int)($meta['iIdPresidente'] ?? 0);
        $iIdVicepresidente = (int)($meta['iIdVicepresidente'] ?? 0);
        $iIdSecretario1    = (int)($meta['iIdSecretario1'] ?? 0);
        $iIdSecretario2    = (int)($meta['iIdSecretario2'] ?? 0);
        $modalidad         = $meta['cModalidadSesion'] ?? 'pleno';

        $jAsistentes = $meta['jAsistentes'] ?? '[]';

        $tObservaciones = null;

        if ($id <= 0 || !$texto) {
            echo json_encode(['error' => 'Faltan parámetros.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Chunking inteligente por límites de hablante
        $chunks = $this->chunkTextInteligente($texto, 9000);

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;
        if (!$apiKey) {
            echo json_encode(['error' => 'ANTHROPIC_API_KEY no configurada en el servidor'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $diputadosLista = implode("\n", json_decode($nombres, true));
        $modelo = "claude-sonnet-4-6";

        $usuarioModel = new UsuarioModel();

        $nomPres = $iIdPresidente ? $usuarioModel->obtenerNombrePorId($iIdPresidente) : null;
        $nomVP   = $iIdVicepresidente ? $usuarioModel->obtenerNombrePorId($iIdVicepresidente) : null;
        $nomSec1 = $iIdSecretario1 ? $usuarioModel->obtenerNombrePorId($iIdSecretario1) : null;
        $nomSec2 = $iIdSecretario2 ? $usuarioModel->obtenerNombrePorId($iIdSecretario2) : null;

        $asistentesIds = [];
        $tmp = json_decode($jAsistentes, true);
        if (is_array($tmp)) $asistentesIds = array_map('intval', $tmp);

        if ($modalidad === 'pleno') {
            $mesaIds = array_values(array_filter([$iIdPresidente, $iIdSecretario1, $iIdSecretario2]));
        } else {
            $mesaIds = array_values(array_filter([$iIdPresidente, $iIdVicepresidente, $iIdSecretario1, $iIdSecretario2]));
        }

        $asistentesIds = array_values(array_diff($asistentesIds, $mesaIds));

        $asistentesNombres = [];
        foreach ($asistentesIds as $uid) {
            $n = $usuarioModel->obtenerNombrePorId((int)$uid);
            if ($n) $asistentesNombres[] = $n;
        }

        $inasistenciasIds = [];
        $tmpInas = json_decode($jInasistencias, true);
        if (is_array($tmpInas)) $inasistenciasIds = array_map('intval', $tmpInas);
        $inasistenciasIds = array_values(array_diff($inasistenciasIds, $mesaIds, $asistentesIds));
        $inasistenciasNombres = [];
        foreach ($inasistenciasIds as $uid) {
            $n = $usuarioModel->obtenerNombrePorId((int)$uid);
            if ($n) $inasistenciasNombres[] = $n;
        }

        $mesaTexto = "MESA DIRECTIVA:\n- Presidente: " . ($nomPres ?? "NO DEFINIDO");
        if ($modalidad !== 'pleno') {
            $mesaTexto .= "\n- Vicepresidente: " . ($nomVP ?? "NO DEFINIDO");
        }
        $mesaTexto .= "\n- Secretario 1: " . ($nomSec1 ?? "NO DEFINIDO");
        $mesaTexto .= "\n- Secretario 2: " . ($nomSec2 ?? "NO DEFINIDO");

        if ($modalidad === 'pleno') {
            $participantesTexto =
"DIPUTADOS ASISTENTES (según selección del usuario):
" . (count($asistentesNombres) ? "- " . implode("\n- ", $asistentesNombres) : "- (No se proporcionó lista)") . "

DIPUTADOS INASISTENTES (según presidencia / no marcados como asistentes):
" . (count($inasistenciasNombres) ? "- " . implode("\n- ", $inasistenciasNombres) : "- (Sin inasistencias registradas)");
            $reglasAsistencia =
"- En la lista de asistencia, menciona explícitamente Asistentes e Inasistentes usando SOLO las listas proporcionadas.
- No inventes integrantes de la Mesa Directiva, asistentes o inasistentes.";
        } else {
            $etiqueta = $modalidad === 'comision' ? 'VOCALES' : 'ASISTENTES';
            $participantesTexto =
"{$etiqueta} (según selección del usuario):
" . (count($asistentesNombres) ? "- " . implode("\n- ", $asistentesNombres) : "- (No se proporcionó lista)");

            if (count($inasistenciasNombres) > 0) {
                $participantesTexto .=
"\n\nAUSENTES JUSTIFICADOS:
- " . implode("\n- ", $inasistenciasNombres);
                $reglasAsistencia =
"- En la lista de asistencia, menciona los participantes y los ausentes justificados por separado.
- Los diputados no listados en ninguna categoría simplemente no forman parte de esta sesión.
- No inventes integrantes de la Mesa Directiva ni participantes.";
            } else {
                $participantesTexto .=
"\n\nNOTA: Esta NO es una sesión plenaria. Los diputados no listados no son inasistentes; simplemente no forman parte de esta sesión.";
                $reglasAsistencia =
"- En la lista de asistencia, menciona solo a los participantes seleccionados; NO generes lista de inasistencias.
- No inventes integrantes de la Mesa Directiva ni participantes.";
            }
        }

        $contextoSesion =
"CONTEXTO DE SESIÓN (metadatos proporcionados por el usuario):
- Tipo de sesión (ID): {$iIdCatTipoSesiones}
- Modalidad: {$modalidad}
- Sesión (ID): " . ($iIdCatSesion ? $iIdCatSesion : "NULL") . "
- Fecha: " . ($dFechaSesion ?? "NULL") . "

{$mesaTexto}

{$participantesTexto}

{$reglasAsistencia}
- Si necesitas referirte a la Mesa Directiva, usa exactamente los nombres anteriores.
";

        // System prompt que será cacheado en Claude (se repite por chunk)
        $systemPromptText =
$contextoSesion . "
Eres el taquígrafo oficial del H. Congreso del Estado de Yucatán.

Reglas obligatorias:
1) Corrige ortografía, acentuación y puntuación sin alterar el contenido.
2) Usa estrictamente la grafía correcta de los nombres según la lista oficial:
$diputadosLista

3) Formato taquigráfico:
   • Identifica quién habla y colócalo como encabezado en mayúsculas:
     EJEMPLO:
     DIPUTADO PRESIDENTE 'NOMBRE DE DIPUTADO':
   • Diálogos formales:
     —Presente.
     —Ausente con justificación.
   • Mantén estructura oficial: lista de asistencia, declaratoria de quórum, orden del día, votaciones, intervenciones, etc.
   • En la lista de asistencia, incluye un apartado de INASISTENCIAS SOLO si la modalidad es 'pleno' y la lista fue proporcionada.

4) NO resumas, NO omitas nada, NO combines párrafos.
5) Intenta siempre deducir la palabra correcta por contexto antes de marcarla. Solo usa «[sic: texto]» cuando una secuencia sea genuinamente incomprensible e imposible de deducir incluso con contexto. Nunca uses sic para palabras que simplemente tienen acento incorrecto, están mal separadas o son nombres propios conocidos.
6) No inventes información.
7) Devuelve ÚNICAMENTE el texto taquigráfico corregido, sin comentarios, sin marcadores de fragmento, sin encabezados propios.
8) Inicia cada intervención con la palabra DIPUTADO o DIPUTADA según el caso.";

        $resultado = '';
        $totalChunks = count($chunks);

        foreach ($chunks as $idx => $parte) {

            // Pausa entre chunks para no superar el rate limit de 30k tokens/min
            if ($idx > 0) sleep(8);

            $nChunk = $idx + 1;
            $userContent = $nChunk === 1
                ? "Fragmento $nChunk de $totalChunks:\n\n$parte"
                : "Fragmento $nChunk de $totalChunks (continuación del anterior):\n\n$parte";

            $payload = [
                "model" => $modelo,
                "max_tokens" => 8000,
                "temperature" => 0.1,
                "system" => [
                    [
                        "type" => "text",
                        "text" => $systemPromptText,
                        "cache_control" => ["type" => "ephemeral"]
                    ]
                ],
                "messages" => [
                    [
                        "role" => "user",
                        "content" => $userContent
                    ]
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
                CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_CONNECTTIMEOUT  => 30,
                CURLOPT_TIMEOUT         => 600,
                CURLOPT_TCP_KEEPALIVE   => 1,
                CURLOPT_SSL_VERIFYPEER  => false,
                CURLOPT_SSL_VERIFYHOST  => false,
            ]);

            $resp    = curl_exec($ch);
            $http    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                echo json_encode([
                    'error' => "Error de conexión con la API: $curlErr"
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($http !== 200) {
                $apiMsg = '';
                $parsed = json_decode($resp, true);
                if (!empty($parsed['error']['message'])) {
                    $apiMsg = $parsed['error']['message'];
                } elseif (!empty($parsed['error']['type'])) {
                    $apiMsg = $parsed['error']['type'];
                }
                echo json_encode([
                    'error' => "Error API Claude (HTTP $http)" . ($apiMsg ? ": $apiMsg" : ''),
                    'detalle' => $resp
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $json = json_decode($resp, true);
            $resultado .= ($json['content'][0]['text'] ?? '') . "\n";
        }

        $resultado  = $this->limpiarMarcas($resultado);
        $charsNuevo = mb_strlen($resultado);

        if (!empty($idCorreccion)) {
            $this->modelo->actualizar((int)$idCorreccion, $charsNuevo, $resultado);
            $idCorr = (int)$idCorreccion;
        } else {
            $idCorr = $this->modelo->guardar(
                $id,
                mb_strlen($texto),
                $charsNuevo,
                $resultado
            );
        }

        $_SESSION['ultima_correccion_por_trans'][$id] = (int)$idCorr;

        if ($iIdCatTipoSesiones > 0 && $idCorr > 0) {
            $this->modelo->upsertMetadatosSesion([
                'iIdCorreccion'      => (int)$idCorr,
                'iIdTranscripcion'   => (int)$id,
                'iIdCatTipoSesiones' => (int)$iIdCatTipoSesiones,
                'iIdCatSesion'       => (int)$iIdCatSesion,
                'iIdPresidente'      => (int)$iIdPresidente,
                'iIdVicepresidente'  => (int)$iIdVicepresidente,
                'iIdSecretario1'     => (int)$iIdSecretario1,
                'iIdSecretario2'     => (int)$iIdSecretario2,
                'jAsistentes'        => json_encode($asistentesIds, JSON_UNESCAPED_UNICODE),
                'dFechaSesion'       => $dFechaSesion,
                'tObservaciones'     => $tObservaciones,
            ]);
        }

        echo json_encode([
            'id_correccion' => $idCorr,
            'chars_original' => mb_strlen($texto),
            'chars_taquigrafica' => $charsNuevo,
            'diferencia_porcentual' => round((($charsNuevo - mb_strlen($texto)) / max(1, mb_strlen($texto))) * 100, 2),
            'texto_taquigrafico' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //      ACTUALIZAR RESULTADO (edición manual del textarea)
    // ============================================================
    public function actualizarResultado() {

        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $idCorreccion = (int)($_POST['id_correccion'] ?? 0);
        $idTrans      = (int)($_POST['id_transcripcion'] ?? ($_POST['id'] ?? 0));
        $textoNuevo   = $_POST['texto'] ?? '';

        if (trim($textoNuevo) === '') {
            echo json_encode(['ok'=>false,'error'=>'El texto está vacío.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($idCorreccion <= 0 && $idTrans > 0 && !empty($_SESSION['ultima_correccion_por_trans'][$idTrans])) {
            $idCorreccion = (int)$_SESSION['ultima_correccion_por_trans'][$idTrans];
        }

        if ($idCorreccion <= 0 && $idTrans > 0) {
            $ultima = $this->modelo->obtenerUltima($idTrans);
            if ($ultima && !empty($ultima['id'])) {
                $idCorreccion = (int)$ultima['id'];
            }
        }

        if ($idCorreccion <= 0) {
            echo json_encode([
                'ok'=>false,
                'error'=>'ID de corrección inválido.',
                'debug'=>[
                    'id_correccion_recibido' => (int)($_POST['id_correccion'] ?? 0),
                    'id_transcripcion_recibido' => (int)($_POST['id_transcripcion'] ?? 0),
                    'id_recibido' => (int)($_POST['id'] ?? 0),
                    'session_fallback' => $idTrans > 0 ? ($_SESSION['ultima_correccion_por_trans'][$idTrans] ?? null) : null
                ]
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $textoLimpio = $this->limpiarMarcas($textoNuevo);
        $charsNuevo  = mb_strlen($textoLimpio);

        $ok = $this->modelo->actualizar($idCorreccion, $charsNuevo, $textoLimpio);

        echo json_encode([
            'ok' => (bool)$ok,
            'id_correccion' => $idCorreccion,
            'texto' => $textoLimpio,
            'chars' => $charsNuevo
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    //   CHUNKING INTELIGENTE: respeta límites de hablante
    // ============================================================

    /**
     * Divide el texto en chunks de hasta $maxLen caracteres,
     * intentando siempre cortar en un cambio de hablante (speaker boundary).
     * Cada chunk de continuación incluye nota del último hablante detectado.
     */
    private function chunkTextInteligente(string $texto, int $maxLen = 9000): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        $segmentos = $this->dividirPorHablante($texto);

        $chunks = [];
        $actual = '';
        $ultimoHablante = '';

        foreach ($segmentos as $seg) {
            $seg = trim($seg);
            if ($seg === '') continue;

            // Detectar hablante al inicio del segmento
            if (preg_match('/^([A-ZÁÉÍÓÚÜÑ][^\n:]{2,80}:)/u', $seg, $m)) {
                $ultimoHablante = rtrim($m[1], ':');
            }

            // Si el segmento solo excede el límite, subdividirlo
            if (mb_strlen($seg, 'UTF-8') > $maxLen) {
                if (trim($actual) !== '') {
                    $chunks[] = trim($actual);
                    $actual   = '';
                }
                foreach ($this->dividirSegmentoGrande($seg, $maxLen) as $sub) {
                    $chunks[] = $sub;
                }
                continue;
            }

            $candidato = $actual === '' ? $seg : $actual . "\n\n" . $seg;

            if (mb_strlen($candidato, 'UTF-8') > $maxLen) {
                if ($actual !== '') $chunks[] = trim($actual);
                $actual = $seg;
            } else {
                $actual = $candidato;
            }
        }

        if (trim($actual) !== '') $chunks[] = trim($actual);

        return $chunks;
    }

    /**
     * Intenta detectar cambios de hablante con tres niveles de granularidad:
     * 1) Encabezados taquigráficos formales (DIPUTADO/PRESIDENCIA/etc.)
     * 2) Cualquier línea en MAYÚSCULAS terminada en dos puntos
     * 3) Fallback: párrafos separados por doble salto de línea
     */
    private function dividirPorHablante(string $texto): array
    {
        // Nivel 1: formato taquigráfico oficial
        $p1 = '/(?=^(?:DIPUTADO|DIPUTADA|PRESIDENCIA|SECRETARIO|SECRETARIA|MESA\s+DIRECTIVA|VICEPRESIDENCIA|PROSECRETARIA|PROSECRETARIO)\b[^\n:]{0,180}:)/mu';
        $partes = preg_split($p1, $texto, -1, PREG_SPLIT_NO_EMPTY);
        if ($partes && count($partes) > 3) {
            return array_values(array_filter(array_map('trim', $partes)));
        }

        // Nivel 2: cualquier encabezado ALL-CAPS terminado en colon (formato Whisper diarizado)
        $p2 = '/(?=^[A-ZÁÉÍÓÚÜÑ][A-ZÁÉÍÓÚÜÑ\s\.\'\-]{2,70}:\s*$)/mu';
        $partes = preg_split($p2, $texto, -1, PREG_SPLIT_NO_EMPTY);
        if ($partes && count($partes) > 3) {
            return array_values(array_filter(array_map('trim', $partes)));
        }

        // Nivel 3: párrafos (mucho mejor que corte duro por caracteres)
        $partes = preg_split('/\n{2,}/', $texto, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $partes)));
    }

    /**
     * Divide un segmento grande en sub-chunks de ≤ $maxLen,
     * agrupando párrafos y añadiendo el nombre del hablante en los fragmentos de continuación.
     */
    private function dividirSegmentoGrande(string $seg, int $maxLen): array
    {
        $partes = preg_split('/\n{2,}/', $seg, -1, PREG_SPLIT_NO_EMPTY);
        $salida = [];
        $buffer = '';

        foreach ($partes as $p) {
            $p = trim($p);
            if ($p === '') continue;

            $cand = $buffer === '' ? $p : $buffer . "\n\n" . $p;

            if (mb_strlen($cand, 'UTF-8') > $maxLen) {
                if ($buffer !== '') {
                    $salida[] = trim($buffer);
                    $buffer   = $p;
                } else {
                    // Párrafo suelto demasiado grande: corte duro con traslape
                    foreach ($this->corteDuroConTraslape($p, $maxLen, 0) as $x) {
                        $salida[] = trim($x);
                    }
                }
            } else {
                $buffer = $cand;
            }
        }

        if (trim($buffer) !== '') $salida[] = trim($buffer);

        return $salida ?: [trim($seg)];
    }

    private function corteDuroConTraslape(string $texto, int $maxLen, int $overlap = 200): array
    {
        $len = mb_strlen($texto, 'UTF-8');
        if ($len <= $maxLen) return [$texto];

        $salida = [];
        $i = 0;
        $margen = 150; // caracteres de margen para buscar límite de palabra

        while ($i < $len) {
            $chunk = mb_substr($texto, $i, $maxLen, 'UTF-8');

            // Si no estamos al final, retroceder hasta el último espacio
            if ($i + $maxLen < $len) {
                $chunkLen  = mb_strlen($chunk, 'UTF-8');
                $buscarEn  = mb_substr($chunk, max(0, $chunkLen - $margen), $margen, 'UTF-8');
                $posEspacio = mb_strrpos($buscarEn, ' ', 0, 'UTF-8');
                if ($posEspacio !== false) {
                    $chunk = mb_substr($chunk, 0, $chunkLen - $margen + $posEspacio, 'UTF-8');
                }
            }

            $salida[] = $chunk;
            $i += max(1, mb_strlen($chunk, 'UTF-8') - $overlap);
        }
        return $salida;
    }

    // Método legacy mantenido por compatibilidad
    private function chunkText($text, $maxLen) {
        $result = [];
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i += $maxLen) {
            $result[] = mb_substr($text, $i, $maxLen);
        }
        return $result;
    }

    // ============================================================
    //                  UTILIDAD PARA EXPORTAR A WORD
    // ============================================================
    public function exportarWord() {

        if (!isset($_POST['texto']) || empty($_POST['texto'])) {
            die("No hay texto para exportar.");
        }

        $texto = $_POST['texto'];
        $id    = intval($_POST['id']);

        require_once __DIR__ . "/../vendor/autoload.php";

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop'    => 1134,
            'marginBottom' => 1134,
            'marginLeft'   => 1800,
            'marginRight'  => 1800,
        ]);

        $fontStyle = ['name' => 'Arial', 'size' => 11];
        $parStyle  = [
            'alignment'   => 'both',
            'lineHeight'  => 1.15,
            'spaceAfter'  => 0,
            'spaceBefore' => 0,
        ];
        $parVacio  = ['spaceAfter' => 80, 'spaceBefore' => 0];

        $lineas = explode("\n", str_replace(["\r\n", "\r"], "\n", $texto));
        foreach ($lineas as $linea) {
            if (trim($linea) === '') {
                $section->addText('', $fontStyle, $parVacio);
            } else {
                $section->addText($linea, $fontStyle, $parStyle);
            }
        }

        $filename = "Transcripcion_Taquigrafica_ID{$id}.docx";

        header("Content-Description: File Transfer");
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=$filename");
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save("php://output");
        exit;
    }

    private function limpiarMarcas(string $txt): string
    {
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);
        // Elimina líneas que sean solo marcadores INICIA o CONTINUARA (con o sin contexto entre paréntesis)
        $txt = preg_replace('/^\s*\(?INICIA\b[^\n]*\)?\s*$/mi', '', $txt);
        $txt = preg_replace('/^\s*\(?CONTINUA[RÁA]\b[^\n]*\)?\s*$/mi', '', $txt);
        $txt = preg_replace("/\n{3,}/", "\n\n", $txt);
        return trim($txt);
    }
}
