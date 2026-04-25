<?php

class ActaNuevaModel {

    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // =========================
    // ACTAS
    // =========================

    public function obtenerPorTranscripcion($transcripcionId) {
        $sql = "SELECT * FROM actas_nuevas WHERE transcripcion_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $transcripcionId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function guardarActa($transcripcionId, $correccionId, $charsOrigen, $textoActa, $charsActa) {
        $sql = "INSERT INTO actas_nuevas
                (transcripcion_id, correccion_id, chars_origen, texto_acta, chars_acta, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("iiisi",
            $transcripcionId,
            $correccionId,
            $charsOrigen,
            $textoActa,
            $charsActa
        );
        if (!$stmt->execute()) return false;
        return $this->conn->insert_id;
    }

    public function actualizarActa($idActa, $charsOrigen, $textoActa, $charsActa) {
        $sql = "UPDATE actas_nuevas
                SET chars_origen = ?, texto_acta = ?, chars_acta = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("isii", $charsOrigen, $textoActa, $charsActa, $idActa);
        return $stmt->execute();
    }

    public function guardarSintesis($idActa, $textoSintesis, $charsSintesis) {
        $sql = "UPDATE actas_nuevas
                SET texto_sintesis = ?, chars_sintesis = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("sii", $textoSintesis, $charsSintesis, $idActa);
        return $stmt->execute();
    }

    public function obtenerPorId($idActa) {
        $sql = "SELECT * FROM actas_nuevas WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $idActa);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    // =========================
    // METADATA
    // =========================

    public function obtenerMetadata($actaId)
    {
        $sql = "SELECT * FROM acta_metadata WHERE acta_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("i", $actaId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

        public function guardarOActualizarMetadata($actaId, $data)
        {
            $existe = $this->obtenerMetadata($actaId);

            $data = array_merge([
                'clave_acta'               => '',
                'fecha'                    => '',
                'hora_inicio'              => '',
                'ciudad'                   => '',
                'recinto'                  => '',
                'presidente'               => '',
                'secretaria_1'             => '',
                'secretaria_2'             => '',
                'encabezado_ai'            => null,
                'primer_parrafo_ai'        => null,
                'encabezado_ai_model'      => null,
                'encabezado_ai_prompt_hash'=> null,
            ], $data);

            $touchEnc = ($data['encabezado_ai'] !== null && trim((string)$data['encabezado_ai']) !== '') ? 1 : 0;

            if ($existe) {
                // 8 strings + 4 strings + 1 int + 1 int = 14 params
                $sql = "UPDATE acta_metadata SET
                    clave_acta=?, fecha=?, hora_inicio=?, ciudad=?, recinto=?,
                    presidente=?, secretaria_1=?, secretaria_2=?,
                    encabezado_ai=?, primer_parrafo_ai=?,
                    encabezado_ai_model=?, encabezado_ai_prompt_hash=?,
                    encabezado_ai_updated_at=IF(?, NOW(), encabezado_ai_updated_at),
                    updated_at=NOW()
                    WHERE acta_id=?";

                $stmt = $this->conn->prepare($sql);
                if (!$stmt) return false;

                $stmt->bind_param(
                    "ssssssssssssii",
                    $data['clave_acta'],
                    $data['fecha'],
                    $data['hora_inicio'],
                    $data['ciudad'],
                    $data['recinto'],
                    $data['presidente'],
                    $data['secretaria_1'],
                    $data['secretaria_2'],
                    $data['encabezado_ai'],
                    $data['primer_parrafo_ai'],
                    $data['encabezado_ai_model'],
                    $data['encabezado_ai_prompt_hash'],
                    $touchEnc,
                    $actaId
                );

                return $stmt->execute();

            } else {
                // 1 int + 12 strings + 1 int = 14 params
                $sql = "INSERT INTO acta_metadata (
                    acta_id, clave_acta, fecha, hora_inicio, ciudad, recinto,
                    presidente, secretaria_1, secretaria_2,
                    encabezado_ai, primer_parrafo_ai, encabezado_ai_model, encabezado_ai_prompt_hash,
                    encabezado_ai_updated_at, created_at, updated_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?,NOW(),NULL),NOW(),NOW())";

                $stmt = $this->conn->prepare($sql);
                if (!$stmt) return false;

                $stmt->bind_param(
                    "issssssssssssi",
                    $actaId,
                    $data['clave_acta'],
                    $data['fecha'],
                    $data['hora_inicio'],
                    $data['ciudad'],
                    $data['recinto'],
                    $data['presidente'],
                    $data['secretaria_1'],
                    $data['secretaria_2'],
                    $data['encabezado_ai'],
                    $data['primer_parrafo_ai'],
                    $data['encabezado_ai_model'],
                    $data['encabezado_ai_prompt_hash'],
                    $touchEnc
                );

                return $stmt->execute();
            }
        }

    public function guardarCatalogosEnMetadata($actaId, $idLegislatura, $idPeriodo, $idEjercicio)
    {
        $sql = "UPDATE acta_metadata SET id_legislatura=?, id_periodo=?, id_ejercicio=? WHERE acta_id=?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("iiii", $idLegislatura, $idPeriodo, $idEjercicio, $actaId);
        return $stmt->execute();
    }

    // Devuelve la metadata completa con nombres resueltos desde catálogos y sesion_metadatos
    public function obtenerMetadataCompleta($actaId)
    {
        $sql = "
            SELECT
                am.*,
                l.clave          AS leg_clave,
                l.nombre         AS leg_nombre,
                cp.nombre        AS periodo_nombre,
                ce.nombre        AS ejercicio_nombre,
                sm.iIdCatTipoSesiones,
                sm.iIdCatSesion,
                ts.cCatTipoSesiones  AS tipo_sesion_nombre,
                cs.cNombreCatSesion  AS sesion_nombre_cat
            FROM acta_metadata am
            LEFT JOIN legislaturas        l  ON l.id  = am.id_legislatura
            LEFT JOIN cat_periodo         cp ON cp.id = am.id_periodo
            LEFT JOIN cat_ejercicio       ce ON ce.id = am.id_ejercicio
            LEFT JOIN actas_nuevas        an ON an.id = am.acta_id
            LEFT JOIN sesion_metadatos    sm ON sm.iIdCorreccion = an.correccion_id
            LEFT JOIN cattiposesesiones   ts ON ts.iIdCatTipoSesiones = sm.iIdCatTipoSesiones
            LEFT JOIN catsesiones         cs ON cs.iIdCatSesion = sm.iIdCatSesion
            WHERE am.acta_id = ?
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $actaId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * ✅ Método dedicado: guarda SOLO el encabezado y primer párrafo generado por OpenAI
     * Ideal para el endpoint actanueva/generarEncabezadoAI
     */
public function guardarEncabezadoAI($actaId, $encabezado, $primerParrafo, $model = null, $promptHash = null)
{
    $meta = $this->obtenerMetadata($actaId);
    if (!$meta) {
        // crea registro base
        $ok = $this->guardarOActualizarMetadata($actaId, []);
        if (!$ok) return false;
    }

    $sql = "UPDATE acta_metadata
            SET encabezado_ai = ?,
                primer_parrafo_ai = ?,
                encabezado_ai_model = ?,
                encabezado_ai_prompt_hash = ?,
                encabezado_ai_updated_at = NOW(),
                updated_at = NOW()
            WHERE acta_id = ?";
    $stmt = $this->conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("ssssi", $encabezado, $primerParrafo, $model, $promptHash, $actaId);
    return $stmt->execute();
}

}
