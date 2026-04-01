<?php
require_once 'models/TranscripcionModel.php';
require_once 'models/CorreccionModel.php';

class CorreccionController
{
    private $modelo;

    public function __construct() {
        $this->modelo = new CorreccionModel();
    }

    // ============================================================
    //                        VISTA DE REVISIÓN
    // ============================================================
    public function iniciar() {

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) { echo "ID inválido"; return; }

        // Traer transcripción original
        $transModel = new TranscripcionModel();
        $trans = $transModel->obtenerPorId($id);

        if (!$trans) {
            echo "Transcripción no encontrada";
            return;
        }

        // LISTA OFICIAL COMPLETA DE LOS 35 DIPUTADOS
        $diputados = [
            "Larissa Acosta Escalante", "Claudia Estefanía Baeza Martínez", "María Teresa Boehm Calero",
            "José Julián Bustillos Medina", "Maribel del Rosario Chuc Ayala", "Manuela de Jesús Cocom Solio",
            "Rosana de Jesús Couoh Chan", "Mario Alejandro Cuevas Mena", "Wilber Dzul Canul",
            "Itzel Falla Uribe", "Melba Rosana Gamboa Ávila", "Daniel Enrique González Quintal",
            "Aydé Verónica Interián Argüello", "Samuel de Jesús Lizama Gasca",
            "María Esther Magadán Alonzo", "Zhazil Leonor Méndez Hernández",
            "Wilmer Manuel Monforte Marfil", "Rafael Gerardo Montalvo Mata",
            "Bayardo Ojeda Marrufo", "Javier Renán Osante Solís", "Marco Antonio Pasos Tec",
            "Naomi Raquel Peniche López", "Ana Cristina Polanco Bautista", "Eric Edgardo Quijano González",
            "Rafael Germán Quintal Medina", "Gaspar Armando Quintal Parra", "Sayda Melina Rodríguez Gómez",
            "Clara Paola Rosales Montiel", "Francisco Rosas Villavicencio",
            "Harry Gerardo Rodríguez Botello Fierro", "Álvaro Cetina Puerto",
            "Roger José Torres Peniche", "Neyda Aracelly Pat Dzul", "Alba Cristina Cob Cortes",
            "Ángel David Valdez Jiménez"
        ];

        // ¿Hay corrección previa?
        $ultimaCorreccion = $this->modelo->obtenerUltima($id);

        // Carga de vista
        $view = __DIR__ . '/../views/correcciones/iniciar.php';
        include __DIR__ . '/../layout.php'; // layout cargará la vista
    }



    // ============================================================
    //                     PROCESAR CORRECCIÓN
    // ============================================================
    public function procesar() {

        header('Content-Type: application/json; charset=utf-8');

        $id = intval($_POST['id'] ?? 0);
        $texto = $_POST['texto'] ?? '';
        $nombres = $_POST['nombres'] ?? '[]';
        $idCorreccion = $_POST['correccion_id'] ?? '';

        if ($id <= 0 || !$texto) {
            echo json_encode(['error' => 'Faltan parámetros.']);
            return;
        }

        // Chunks más seguros
        $chunks = $this->chunkText($texto, 9000);

        // API KEY
       $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            echo json_encode(['error' => 'OPENAI_API_KEY no configurada']);
            return;
        }

        $diputadosLista = implode("\n", json_decode($nombres, true));
        $modelo = "gpt-4.1-2025-04-14";

        $resultado = '';

        foreach ($chunks as $parte) {

            // PROMPT PROFESIONAL
            $prompt = "
Eres el taquígrafo oficial del H. Congreso del Estado de Yucatán.

Reglas obligatorias:
1) Corrige ortografía, acentuación y puntuación sin alterar el contenido.
2) Usa estrictamente la grafía correcta de los nombres según la lista oficial:
$diputadosLista

3) Formato taquigráfico:
   • Identifica quién habla y colócalo como encabezado en mayúsculas:
     EJEMPLO:
     DIPUTADO PRESIDENTE MARIO ALEJANDRO CUEVAS MENA:
   • Diálogos formales: 
     —Presente. 
     —Ausente con justificación.
   • Mantén estructura oficial: lista de asistencia, declaratoria de quórum, orden del día, votaciones, intervenciones, etc.
   • La mayoría de las veces, cuando alguien termina de hablar dirá 'Es cuanto' puedes usarlo como una guía adicional, aunque no es regla que así termine.

4) NO resumas, NO omitas nada, NO combines párrafos.
5) Si una palabra no se entiende: «[sic: texto]».
6) No inventes información.
7) Devuelve solo el texto taquigráfico corregido, sin comentarios.
8) Termina cada freagmento con la frese (CONTNIUARA) e inicia cada fragmento con la frase (INICIA)
Fragmento:
$parte
";

            // Llamada OpenAI
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
                echo json_encode(['error' => 'Error OpenAI', 'detalle' => $resp]);
                return;
            }

            $json = json_decode($resp, true);
            $resultado .= ($json['choices'][0]['message']['content'] ?? '') . "\n";
        }


        // =======================================================
        //           GUARDAR O ACTUALIZAR LA CORRECCIÓN
        // =======================================================

        $charsNuevo = mb_strlen($resultado);

        if (!empty($idCorreccion)) {
            // ACTUALIZAR
            $this->modelo->actualizar($idCorreccion, $charsNuevo, $resultado);
            $idCorr = $idCorreccion;
        } else {
            // NUEVA CORRECCIÓN
            $idCorr = $this->modelo->guardar(
                $id,
                mb_strlen($texto),
                $charsNuevo,
                $resultado
            );
        }

        echo json_encode([
            'id_correccion' => $idCorr,
            'chars_original' => mb_strlen($texto),
            'chars_taquigrafica' => $charsNuevo,
            'diferencia_porcentual' => round((($charsNuevo - mb_strlen($texto)) / mb_strlen($texto)) * 100, 2),
            'texto_taquigrafico' => $resultado
        ]);
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

    // Cargar PHPWord
    require_once __DIR__ . "/../vendor/autoload.php";

    $phpWord = new \PhpOffice\PhpWord\PhpWord();

    // Crear sección
    $section = $phpWord->addSection();

    // Estilo del texto
    $fontStyle = [
        'name' => 'Arial',
        'size' => 11,
    ];

    // Convertir saltos de línea a Word
    $paragraphs = explode("\n", $texto);

    foreach ($paragraphs as $p) {
        $section->addText($p, $fontStyle);
    }

    // Nombre del archivo
    $filename = "Transcripcion_Taquigrafica_ID{$id}.docx";

    // Headers de descarga
    header("Content-Description: File Transfer");
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header("Content-Disposition: attachment; filename=$filename");
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save("php://output");

    exit;
    }

}
