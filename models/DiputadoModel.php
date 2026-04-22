<?php
class DiputadoModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) die("Error de conexión: " . $this->conn->connect_error);
        $this->conn->set_charset("utf8mb4");
    }

    // ================================================================
    //  PARTIDOS
    // ================================================================

    public function listarPartidos(): array {
        $res = $this->conn->query("SELECT * FROM partidos ORDER BY nombre ASC");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function guardarPartido(array $d): int|false {
        $id     = (int)($d['id']     ?? 0);
        $nombre = trim($d['nombre']  ?? '');
        $siglas = trim($d['siglas']  ?? '');
        $color  = trim($d['color']   ?? '#6b7280');
        $activo = (int)($d['activo'] ?? 1);

        if ($nombre === '' || $siglas === '') return false;

        if ($id > 0) {
            $stmt = $this->conn->prepare(
                "UPDATE partidos SET nombre=?, siglas=?, color=?, activo=? WHERE id=?"
            );
            if (!$stmt) return false;
            $stmt->bind_param("sssii", $nombre, $siglas, $color, $activo, $id);
            return $stmt->execute() ? $id : false;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO partidos (nombre, siglas, color, activo) VALUES (?,?,?,?)"
        );
        if (!$stmt) return false;
        $stmt->bind_param("sssi", $nombre, $siglas, $color, $activo);
        return $stmt->execute() ? (int)$this->conn->insert_id : false;
    }

    public function toggleActivoPartido(int $id): bool {
        $stmt = $this->conn->prepare(
            "UPDATE partidos SET activo = IF(activo=1,0,1) WHERE id=?"
        );
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ================================================================
    //  LEGISLATURAS
    // ================================================================

    public function listarLegislaturas(): array {
        $res = $this->conn->query("SELECT * FROM legislaturas ORDER BY numero DESC");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function legislaturaActiva(): ?array {
        $res = $this->conn->query("SELECT * FROM legislaturas WHERE activa=1 LIMIT 1");
        return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
    }

    public function guardarLegislatura(array $d): int|false {
        $id     = (int)($d['id'] ?? 0);
        $numero = (int)($d['numero'] ?? 0);
        $clave  = trim($d['clave']  ?? '');
        $nombre = trim($d['nombre'] ?? '');
        $inicio = ($d['fecha_inicio'] ?? '') ?: null;
        $fin    = ($d['fecha_fin']    ?? '') ?: null;
        $activa = (int)($d['activa'] ?? 0);

        if (!$numero || $clave === '' || $nombre === '') return false;

        if ($activa) {
            $this->conn->query("UPDATE legislaturas SET activa=0");
        }

        if ($id > 0) {
            $stmt = $this->conn->prepare(
                "UPDATE legislaturas SET numero=?,clave=?,nombre=?,fecha_inicio=?,fecha_fin=?,activa=? WHERE id=?"
            );
            if (!$stmt) return false;
            $stmt->bind_param("issssii", $numero, $clave, $nombre, $inicio, $fin, $activa, $id);
            return $stmt->execute() ? $id : false;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO legislaturas (numero,clave,nombre,fecha_inicio,fecha_fin,activa) VALUES (?,?,?,?,?,?)"
        );
        if (!$stmt) return false;
        $stmt->bind_param("issssi", $numero, $clave, $nombre, $inicio, $fin, $activa);
        return $stmt->execute() ? (int)$this->conn->insert_id : false;
    }

    public function activarLegislatura(int $id): bool {
        $this->conn->query("UPDATE legislaturas SET activa=0");
        $stmt = $this->conn->prepare("UPDATE legislaturas SET activa=1 WHERE id=?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ================================================================
    //  DIPUTADOS
    // ================================================================

    public function listarDiputados(?int $legislaturaId = null): array {
        $select = "SELECT d.*, l.clave AS leg_clave, l.nombre AS leg_nombre,
                          t.nombre AS nombre_titular,
                          p.nombre AS partido_nombre, p.siglas AS partido_siglas,
                          p.color  AS partido_color";
        $joins  = "FROM diputados d
                   JOIN legislaturas l ON d.legislatura_id = l.id
                   LEFT JOIN diputados t ON d.suplente_de = t.id
                   LEFT JOIN partidos p ON d.partido_id = p.id";
        $order  = "ORDER BY d.tipo_mandato ASC, d.tipo_eleccion ASC, d.distrito ASC, d.nombre ASC";

        if ($legislaturaId) {
            $stmt = $this->conn->prepare("$select $joins WHERE d.legislatura_id = ? $order");
            if (!$stmt) return [];
            $stmt->bind_param("i", $legislaturaId);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $this->conn->query(
                "$select $joins ORDER BY d.legislatura_id DESC, d.tipo_mandato ASC,
                 d.tipo_eleccion ASC, d.distrito ASC, d.nombre ASC"
            );
        }
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function obtenerActivosPorLegislatura(?int $legId = null): array {
        if (!$legId) {
            $leg   = $this->legislaturaActiva();
            $legId = $leg ? (int)$leg['id'] : 0;
        }
        if (!$legId) return [];

        $stmt = $this->conn->prepare(
            "SELECT id, nombre FROM diputados
             WHERE legislatura_id = ? AND activo = 1
               AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
             ORDER BY nombre ASC"
        );
        if (!$stmt) return [];
        $stmt->bind_param("i", $legId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = ['iIdUsuario' => (int)$row['id'], 'nombre' => $row['nombre']];
            }
        }
        return $out;
    }

    public function obtenerNombrePorId(int $id): ?string {
        if ($id <= 0) return null;
        $stmt = $this->conn->prepare("SELECT nombre FROM diputados WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (string)$row['nombre'] : null;
    }

    public function obtenerTitularesPorLegislatura(?int $legId = null): array {
        if (!$legId) {
            $leg   = $this->legislaturaActiva();
            $legId = $leg ? (int)$leg['id'] : 0;
        }
        if (!$legId) return [];

        $stmt = $this->conn->prepare(
            "SELECT id, nombre FROM diputados
             WHERE legislatura_id = ? AND tipo_mandato = 'titular'
             ORDER BY nombre ASC"
        );
        if (!$stmt) return [];
        $stmt->bind_param("i", $legId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function guardarDiputado(array $d): int|false {
        $id         = (int)($d['id'] ?? 0);
        $legId      = (int)($d['legislatura_id'] ?? 0);
        $nombre     = trim($d['nombre'] ?? '');
        $tipoEl     = in_array($d['tipo_eleccion'] ?? '', ['nominal','plurinominal'])
                        ? $d['tipo_eleccion'] : 'nominal';
        $distrito   = ($tipoEl === 'nominal' && !empty($d['distrito']))
                        ? (int)$d['distrito'] : null;
        $tipoMand   = in_array($d['tipo_mandato'] ?? '', ['titular','suplente'])
                        ? $d['tipo_mandato'] : 'titular';
        $suplenteDe = (!empty($d['suplente_de']) && (int)$d['suplente_de'] > 0)
                        ? (int)$d['suplente_de'] : null;
        $inicio     = ($d['fecha_inicio'] ?? '') ?: null;
        $fin        = ($d['fecha_fin']    ?? '') ?: null;
        $activo     = (int)($d['activo']  ?? 1);
        $partidoId  = (!empty($d['partido_id']) && (int)$d['partido_id'] > 0)
                        ? (int)$d['partido_id'] : null;

        if ($nombre === '' || $legId <= 0) return false;

        if ($id > 0) {
            $stmt = $this->conn->prepare(
                "UPDATE diputados
                 SET legislatura_id=?, nombre=?, tipo_eleccion=?, distrito=?,
                     tipo_mandato=?, suplente_de=?, fecha_inicio=?, fecha_fin=?,
                     activo=?, partido_id=?
                 WHERE id=?"
            );
            if (!$stmt) return false;
            $stmt->bind_param("issisissi" . "ii",
                $legId, $nombre, $tipoEl, $distrito,
                $tipoMand, $suplenteDe, $inicio, $fin, $activo,
                $partidoId, $id
            );
            return $stmt->execute() ? $id : false;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO diputados
             (legislatura_id, nombre, tipo_eleccion, distrito,
              tipo_mandato, suplente_de, fecha_inicio, fecha_fin, activo, partido_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        if (!$stmt) return false;
        $stmt->bind_param("issisissi" . "i",
            $legId, $nombre, $tipoEl, $distrito,
            $tipoMand, $suplenteDe, $inicio, $fin, $activo,
            $partidoId
        );
        return $stmt->execute() ? (int)$this->conn->insert_id : false;
    }

    public function finalizarMandato(int $id, string $fecha): bool {
        $stmt = $this->conn->prepare(
            "UPDATE diputados SET fecha_fin=?, activo=0 WHERE id=?"
        );
        if (!$stmt) return false;
        $stmt->bind_param("si", $fecha, $id);
        return $stmt->execute();
    }

    public function toggleActivo(int $id): bool {
        $stmt = $this->conn->prepare(
            "UPDATE diputados SET activo = IF(activo=1,0,1) WHERE id=?"
        );
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getLastError(): string {
        return $this->conn->error ?: '';
    }
}
