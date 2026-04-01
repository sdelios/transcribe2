
<?php
/**
 * procesar.php
 * - Recibe POST con 'orden' y 'transcripcion'.
 * - Llama a OpenAI Responses API.
 * - Guarda el HTML generado en $_SESSION['resultado_html'] y redirige a index.php#resultado
 */

session_start();

// ===== 1) Validación de entrada =====
$orden = isset($_POST['orden']) ? trim($_POST['orden']) : '';
$trans = isset($_POST['transcripcion']) ? trim($_POST['transcripcion']) : '';

$_SESSION['ultimo_orden'] = $orden;
$_SESSION['ultimo_transcripcion'] = $trans;

if ($orden === '' || $trans === '') {
  $_SESSION['error_msg'] = "Faltan datos: asegúrate de pegar la Orden del Día y la Transcripción.";
  header("Location: index.php#resultado");
  exit;
}

// ===== 2) Configuración =====
$apiKey = $_ENV['OPENAI_API_KEY'] ?? null;// o reemplaza por tu string; mejor por variable de entorno
if (!$apiKey) {
  $_SESSION['error_msg'] = "No se encontró OPENAI_API_KEY en el entorno.";
  header("Location: index.php#resultado");
  exit;
}

// Modelo sugerido (coincide con tu log)
$model = "gpt-4.1-mini-2025-04-14";

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
-Asistencia y quórum (si aparece).
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
// Pedimos JSON Schema pero con salida HTML simple. El Responses API permite 'text.format'
// Aquí definimos que el texto sea una cadena que contenga HTML. (additionalProperties=false requerido)
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

// ===== 5) Payload para Responses API =====
$payload = [
  "model" => $model,
  "input" => [
    ["role" => "system", "content" => $instrucciones],
    ["role" => "user",   "content" => $inputUsuario],
  ],
  // Nuevo formato: text.format.*
  "text" => [
    "format" => [
      "type"   => "json_schema",
      "name"   => "html_acta_sintesis",
      "schema" => $schema
    ]
  ],
  // Recomendable controlar longitud
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
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

// ===== 7) Manejo de errores de red =====
if ($response === false) {
  $_SESSION['error_msg'] = "Error de red al llamar a la API: $curlErr";
  header("Location: index.php#resultado");
  exit;
}

// ===== 8) Decodificar y extraer texto =====
$data = json_decode($response, true);

if ($httpCode >= 400) {
  // Guardamos la respuesta cruda para que la veas
  $_SESSION['error_msg'] = "Error HTTP $httpCode:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  header("Location: index.php#resultado");
  exit;
}

/**
 * Estructura Responses API (conveniente):
 * - $data['output_text']  -> Texto “aplanado”
 * - Si usamos json_schema, lo seguro es parsear JSON del output_text.
 */
$texto = isset($data['output_text']) ? $data['output_text'] : '';
if ($texto === '') {
  // Fallback por si cambia la estructura
  // Intentamos ubicar el contenido en $data['output'][0]['content'][0]['text']
  $texto = $data['output'][0]['content'][0]['text'] ?? '';
}

$htmlFinal = '';
// Cuando pedimos json_schema, el modelo devuelve un JSON con {"html":"..."}
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
  header("Location: index.php#resultado");
  exit;
}

// ===== 9) Guardar en sesión y redirigir a index =====
$_SESSION['resultado_html'] = $htmlFinal;
header("Location: index.php#resultado");
exit;
