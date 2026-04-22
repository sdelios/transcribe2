<?php

class CorreccionModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // ===========================================================
    //          GUARDAR NUEVA CORRECCIÓN (INSERT)
    // ===========================================================
    public function guardar($transcripcionId, $charsOriginal, $charsNuevo, $texto) {

        $sql = "INSERT INTO correcciones 
                (transcripcion_id, chars_original, chars_taquigrafica, texto_taquigrafico, created_at) 
                VALUES (?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("iiis",
            $transcripcionId,
            $charsOriginal,
            $charsNuevo,
            $texto
        );

        if (!$stmt->execute()) return false;

        return (int)$this->conn->insert_id;
    }

    // ===========================================================
    //          ACTUALIZAR CORRECCIÓN EXISTENTE (UPDATE)
    // ===========================================================
    public function actualizar($idCorreccion, $charsNuevo, $textoNuevo) {

        $sql = "UPDATE correcciones 
                SET chars_taquigrafica = ?, texto_taquigrafico = ?, created_at = NOW()
                WHERE id = ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("isi",
            $charsNuevo,
            $textoNuevo,
            $idCorreccion
        );

        return $stmt->execute();
    }

    // ===========================================================
    //     OBTENER LA ÚLTIMA CORRECCIÓN POR TRANSCRIPCIÓN
    // ===========================================================
    public function obtenerUltima($transcripcionId) {

        $sql = "SELECT * FROM correcciones 
                WHERE transcripcion_id = ?
                ORDER BY id DESC 
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("i", $transcripcionId);
        $stmt->execute();

        $res = $stmt->get_result();
        return $res ? $res->fetch_assoc() : null;
    }

    // ===========================================================
    //     OBTENER TIPOS DE SESIÓN ACTIVOS
    // ===========================================================
    public function obtenerTiposSesionActivos() {
        try {
            $sql = "SELECT iIdCatTipoSesiones, cCatTipoSesiones,
                           IFNULL(cModalidadSesion, 'pleno') AS cModalidadSesion
                    FROM cattiposesesiones
                    WHERE iStatusCatTipoSesiones = 1
                    ORDER BY iOrder ASC, cCatTipoSesiones ASC";
            $res = $this->conn->query($sql);
        } catch (\mysqli_sql_exception $e) {
            $sql = "SELECT iIdCatTipoSesiones, cCatTipoSesiones,
                           'pleno' AS cModalidadSesion
                    FROM cattiposesesiones
                    WHERE iStatusCatTipoSesiones = 1
                    ORDER BY iOrder ASC, cCatTipoSesiones ASC";
            $res = $this->conn->query($sql);
        }
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // ===========================================================
    //     OBTENER MODALIDAD DE UN TIPO DE SESIÓN
    // ===========================================================
    public function obtenerModalidadTipo(int $idTipo): string {
        try {
            $sql = "SELECT IFNULL(cModalidadSesion, 'pleno') AS cModalidadSesion
                    FROM cattiposesesiones WHERE iIdCatTipoSesiones = ? LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return 'pleno';
            $stmt->bind_param("i", $idTipo);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            return $row['cModalidadSesion'] ?? 'pleno';
        } catch (\mysqli_sql_exception $e) {
            return 'pleno';
        }
    }

    // ===========================================================
    //     OBTENER SESIONES POR TIPO
    // ===========================================================
    public function obtenerSesionesPorTipo($idTipo) {
        $sql = "SELECT iIdCatSesion, cNombreCatSesion
                FROM catsesiones
                WHERE iStatusCatSesion = 1
                  AND iTipoSesion = ?
                ORDER BY cNombreCatSesion ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param("i", $idTipo);
        $stmt->execute();

        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // ===========================================================
    //     OBTENER METADATOS POR CORRECCIÓN
    // ===========================================================
    public function obtenerMetadatosPorCorreccion($idCorreccion) {
        $sql = "SELECT * FROM sesion_metadatos WHERE iIdCorreccion = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("i", $idCorreccion);
        $stmt->execute();

        $res = $stmt->get_result();
        return $res ? $res->fetch_assoc() : null;
    }

    // ===========================================================
    //     UPSERT METADATOS DE SESIÓN (por iIdCorreccion)
    //     Devuelve array con ok + error si falla
    // ===========================================================
    public function upsertMetadatosSesion(array $data): array {

        $sql = "INSERT INTO sesion_metadatos
            (iIdCorreccion, iIdTranscripcion, iIdCatTipoSesiones, iIdCatSesion,
             iIdPresidente, iIdVicepresidente, iIdSecretario1, iIdSecretario2,
             jAsistentes, dFechaSesion, tObservaciones, created_at)
            VALUES (
              ?, NULLIF(?,0), ?, NULLIF(?,0),
              NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0),
              ?, ?, ?, NOW()
            )
            ON DUPLICATE KEY UPDATE
              iIdTranscripcion   = NULLIF(VALUES(iIdTranscripcion),0),
              iIdCatTipoSesiones = VALUES(iIdCatTipoSesiones),
              iIdCatSesion       = NULLIF(VALUES(iIdCatSesion),0),
              iIdPresidente      = NULLIF(VALUES(iIdPresidente),0),
              iIdVicepresidente  = NULLIF(VALUES(iIdVicepresidente),0),
              iIdSecretario1     = NULLIF(VALUES(iIdSecretario1),0),
              iIdSecretario2     = NULLIF(VALUES(iIdSecretario2),0),
              jAsistentes        = VALUES(jAsistentes),
              dFechaSesion       = VALUES(dFechaSesion),
              tObservaciones     = VALUES(tObservaciones),
              updated_at         = NOW()";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [
                'ok' => false,
                'stage' => 'prepare',
                'errno' => (int)$this->conn->errno,
                'error' => (string)$this->conn->error,
            ];
        }

        $iIdCorreccion      = (int)($data['iIdCorreccion'] ?? 0);
        $iIdTranscripcion   = (int)($data['iIdTranscripcion'] ?? 0);
        $iIdCatTipoSesiones = (int)($data['iIdCatTipoSesiones'] ?? 0);
        $iIdCatSesion       = (int)($data['iIdCatSesion'] ?? 0);

        $iIdPresidente      = (int)($data['iIdPresidente'] ?? 0);
        $iIdVicepresidente  = (int)($data['iIdVicepresidente'] ?? 0);
        $iIdSecretario1     = (int)($data['iIdSecretario1'] ?? 0);
        $iIdSecretario2     = (int)($data['iIdSecretario2'] ?? 0);

        $jAsistentes    = $data['jAsistentes'] ?? '[]';
        $dFechaSesion   = $data['dFechaSesion'] ?? null;
        $tObservaciones = $data['tObservaciones'] ?? null;

        $stmt->bind_param(
            "iiiiiiiisss",
            $iIdCorreccion,
            $iIdTranscripcion,
            $iIdCatTipoSesiones,
            $iIdCatSesion,
            $iIdPresidente,
            $iIdVicepresidente,
            $iIdSecretario1,
            $iIdSecretario2,
            $jAsistentes,
            $dFechaSesion,
            $tObservaciones
        );

        $ok = $stmt->execute();
        if (!$ok) {
            return [
                'ok' => false,
                'stage' => 'execute',
                'errno' => (int)($stmt->errno ?: $this->conn->errno),
                'error' => (string)($stmt->error ?: $this->conn->error),
            ];
        }

        return [
            'ok' => true,
            'affected_rows' => (int)$stmt->affected_rows
        ];
    }
}

