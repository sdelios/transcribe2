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

        $iIdPresidente  = (int)($_POST['iIdPresidente'] ?? 0);
        $iIdSecretario1 = (int)($_POST['iIdSecretario1'] ?? 0);
        $iIdSecretario2 = (int)($_POST['iIdSecretario2'] ?? 0);

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
        if ($iIdPresidente <= 0 || $iIdSecretario1 <= 0 || $iIdSecretario2 <= 0) {
            echo json_encode(['ok'=>false,'error'=>'Selecciona Presidente, Secretario 1 y Secretario 2.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $mesa = [$iIdPresidente, $iIdSecretario1, $iIdSecretario2];
        if (count(array_unique($mesa)) !== 3) {
            echo json_encode(['ok'=>false,'error'=>'La Mesa Directiva no puede tener personas repetidas.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $asist = array_values(array_unique(array_map('intval', $asist)));
        $asist = array_values(array_diff($asist, $mesa));

        // ✅ Determinar/crear corrección para amarrar metadatos a BD
        $ultima = $this->modelo->obtenerUltima($idTrans);
        $idCorreccion = $ultima && !empty($ultima['id']) ? (int)$ultima['id'] : 0;

        if ($idCorreccion <= 0) {
            // Crea placeholder usando el texto original (para que exista corrección_id)
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

        // ✅ Guardar en sesión (para exigirlo antes de corregir)
        $_SESSION['correccion_meta'][$idTrans] = [
            'token' => $token,
            'data' => [
                'iIdCorreccion'      => $idCorreccion,
                'iIdTranscripcion'   => $idTrans,
                'iIdCatTipoSesiones' => $iIdCatTipoSesiones,
                'iIdCatSesion'       => $iIdCatSesion,
                'dFechaSesion'       => $dFechaSesion,
                'iIdPresidente'      => $iIdPresidente,
                'iIdSecretario1'     => $iIdSecretario1,
                'iIdSecretario2'     => $iIdSecretario2,
                'jAsistentes'        => json_encode($asist, JSON_UNESCAPED_UNICODE),
            ]
        ];

        // ✅ Guardar/actualizar en BD
        $metaDb = $this->modelo->upsertMetadatosSesion([
            'iIdCorreccion'      => $idCorreccion,
            'iIdTranscripcion'   => $idTrans,
            'iIdCatTipoSesiones' => $iIdCatTipoSesiones,
            'iIdCatSesion'       => $iIdCatSesion,
            'iIdPresidente'      => $iIdPresidente,
            'iIdVicepresidente'  => 0,
            'iIdSecretario1'     => $iIdSecretario1,
            'iIdSecretario2'     => $iIdSecretario2,
            'jAsistentes'        => json_encode($asist, JSON_UNESCAPED_UNICODE),
            'dFechaSesion'       => $dFechaSesion,
            'tObservaciones'     => null
        ]);

        // Tu modelo puede devolver bool o array; soportamos ambos
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
    //                     PROCESAR CORRECCIÓN
    //         ✅ NUEVO: incluye INASISTENCIAS para mencionarlas
    // ============================================================
    public function procesar() {

        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $id = intval($_POST['id'] ?? 0);
        $texto = $_POST['texto'] ?? '';
        $nombres = $_POST['nombres'] ?? '[]';
        $idCorreccion = $_POST['correccion_id'] ?? '';

        // ✅ NUEVO: viene desde la vista (universo - mesa - asistentes)
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

        $iIdPresidente  = (int)($meta['iIdPresidente'] ?? 0);
        $iIdSecretario1 = (int)($meta['iIdSecretario1'] ?? 0);
        $iIdSecretario2 = (int)($meta['iIdSecretario2'] ?? 0);

        $jAsistentes = $meta['jAsistentes'] ?? '[]';

        $iIdVicepresidente = 0;
        $tObservaciones = null;

        if ($id <= 0 || !$texto) {
            echo json_encode(['error' => 'Faltan parámetros.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $chunks = $this->chunkText($texto, 9000);

        // ✅ usa variable de entorno (no hardcode)
        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            echo json_encode(['error' => 'OPENAI_API_KEY no configurada en el servidor'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $diputadosLista = implode("\n", json_decode($nombres, true));
        $modelo = "gpt-4.1-2025-04-14";

        $usuarioModel = new UsuarioModel();

        $nomPres = $iIdPresidente ? $usuarioModel->obtenerNombrePorId($iIdPresidente) : null;
        $nomSec1 = $iIdSecretario1 ? $usuarioModel->obtenerNombrePorId($iIdSecretario1) : null;
        $nomSec2 = $iIdSecretario2 ? $usuarioModel->obtenerNombrePorId($iIdSecretario2) : null;

        // Asistentes IDs (desde meta en sesión)
        $asistentesIds = [];
        $tmp = json_decode($jAsistentes, true);
        if (is_array($tmp)) $asistentesIds = array_map('intval', $tmp);

        // Mesa IDs
        $mesaIds = array_filter([$iIdPresidente, $iIdSecretario1, $iIdSecretario2]);
        $asistentesIds = array_values(array_diff($asistentesIds, $mesaIds));

        // Asistentes nombres
        $asistentesNombres = [];
        foreach ($asistentesIds as $uid) {
            $n = $usuarioModel->obtenerNombrePorId((int)$uid);
            if ($n) $asistentesNombres[] = $n;
        }

        // ✅ NUEVO: Inasistencias IDs (desde vista) y nombres
        $inasistenciasIds = [];
        $tmpInas = json_decode($jInasistencias, true);
        if (is_array($tmpInas)) $inasistenciasIds = array_map('intval', $tmpInas);

        // seguridad extra: quitar mesa y asistentes por si el frontend manda algo raro
        $inasistenciasIds = array_values(array_diff($inasistenciasIds, $mesaIds, $asistentesIds));

        $inasistenciasNombres = [];
        foreach ($inasistenciasIds as $uid) {
            $n = $usuarioModel->obtenerNombrePorId((int)$uid);
            if ($n) $inasistenciasNombres[] = $n;
        }

        $contextoSesion =
"CONTEXTO DE SESIÓN (metadatos proporcionados por el usuario):
- Tipo de sesión (ID): {$iIdCatTipoSesiones}
- Sesión (ID): " . ($iIdCatSesion ? $iIdCatSesion : "NULL") . "
- Fecha: " . ($dFechaSesion ?? "NULL") . "

MESA DIRECTIVA:
- Presidente: " . ($nomPres ?? "NO DEFINIDO") . "
- Secretario 1: " . ($nomSec1 ?? "NO DEFINIDO") . "
- Secretario 2: " . ($nomSec2 ?? "NO DEFINIDO") . "

DIPUTADOS ASISTENTES (según selección del usuario):
" . (count($asistentesNombres) ? "- " . implode("\n- ", $asistentesNombres) : "- (No se proporcionó lista)") . "

DIPUTADOS INASISTENTES (según presidencia / no marcados como asistentes):
" . (count($inasistenciasNombres) ? "- " . implode("\n- ", $inasistenciasNombres) : "- (Sin inasistencias registradas)") . "

Reglas adicionales por contexto:
- No inventes integrantes de la Mesa Directiva, asistentes o inasistentes.
- Si necesitas referirte a la Mesa Directiva, usa exactamente los nombres anteriores.
- En la lista de asistencia, menciona explícitamente Asistentes e Inasistentes usando SOLO las listas proporcionadas.
";

        $resultado = '';

        foreach ($chunks as $parte) {

            $prompt = "
$contextoSesion
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
   • En la lista de asistencia, incluye un apartado de INASISTENCIAS si la lista fue proporcionada.

4) NO resumas, NO omitas nada, NO combines párrafos.
5) Si una palabra no se entiende: «[sic: texto]».
6) No inventes información.
7) Devuelve solo el texto taquigráfico corregido, sin comentarios.
8) Inicia cada intervención con la palabra DIPUTADO o DIPUTADA según el caso.
9) Termina cada fragmento con (CONTINUARA) e inicia cada fragmento con (INICIA)

Fragmento:
$parte
";

            $payload = [
                "model" => $modelo,
                "temperature" => 0.1,
                "messages" => [
                    ["role" => "user", "content" => $prompt]
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
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);

            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http !== 200) {
                echo json_encode(['error' => 'Error OpenAI', 'detalle' => $resp], JSON_UNESCAPED_UNICODE);
                return;
            }

            $json = json_decode($resp, true);
            $resultado .= ($json['choices'][0]['message']['content'] ?? '') . "\n";
        }

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

        // ✅ Garantiza metadatos en BD (siempre que haya corrección)
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
                'tObservaciones'     => $tObservaciones
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

        // Acepta ambos nombres por compatibilidad
        $idCorreccion = (int)($_POST['id_correccion'] ?? 0);
        $idTrans      = (int)($_POST['id_transcripcion'] ?? ($_POST['id'] ?? 0));
        $textoNuevo   = $_POST['texto'] ?? '';

        if (trim($textoNuevo) === '') {
            echo json_encode(['ok'=>false,'error'=>'El texto está vacío.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ✅ 1) Fallback por sesión (si ya abriste iniciar y ahí sí sabemos el id)
        if ($idCorreccion <= 0 && $idTrans > 0 && !empty($_SESSION['ultima_correccion_por_trans'][$idTrans])) {
            $idCorreccion = (int)$_SESSION['ultima_correccion_por_trans'][$idTrans];
        }

        // ✅ 2) Fallback por BD usando transcripción
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

        // limpiar marcas
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
    //                  UTILIDAD PARA DIVIDIR EN CHUNKS
    // ============================================================
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
        $id = intval($_POST['id']);

        require_once __DIR__ . "/../vendor/autoload.php";

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();

        $fontStyle = ['name' => 'Arial','size' => 11];

        $paragraphs = explode("\n", $texto);
        foreach ($paragraphs as $p) $section->addText($p, $fontStyle);

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
        // Normaliza saltos
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);

        // Quita líneas que sean SOLO "INICIA" o "(INICIA)" (con espacios)
        $txt = preg_replace('/^\s*\(?(INICIA)\)?\s*$/mi', '', $txt);

        // Quita líneas que sean SOLO "CONTINUARA" o "(CONTINUARA)" (incluye variante con acento)
        $txt = preg_replace('/^\s*\(?(CONTINUARA|CONTINUARÁ)\)?\s*$/mi', '', $txt);

        // Limpia exceso de líneas en blanco
        $txt = preg_replace("/\n{3,}/", "\n\n", $txt);

        return trim($txt);
    }
}
