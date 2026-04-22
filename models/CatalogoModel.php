<?php

class CatalogoModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // ================================================================
    //  TIPOS DE SESIÓN  (cattiposesesiones)
    // ================================================================

    public function listarTiposSesion(): array {
        try {
            $sql = "SELECT iIdCatTipoSesiones, cCatTipoSesiones,
                           IFNULL(cModalidadSesion, 'pleno') AS cModalidadSesion,
                           iStatusCatTipoSesiones, IFNULL(iOrder, 0) AS iOrder
                    FROM cattiposesesiones
                    ORDER BY iOrder ASC, cCatTipoSesiones ASC";
        } catch (\Throwable $e) {
            $sql = "SELECT iIdCatTipoSesiones, cCatTipoSesiones,
                           'pleno' AS cModalidadSesion,
                           iStatusCatTipoSesiones, 0 AS iOrder
                    FROM cattiposesesiones
                    ORDER BY cCatTipoSesiones ASC";
        }
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** @return int|false */
    public function guardarTipoSesion(array $d) {
        $id        = (int)($d['iIdCatTipoSesiones'] ?? 0);
        $nombre    = trim($d['cCatTipoSesiones'] ?? '');
        $modalidad = $d['cModalidadSesion'] ?? 'pleno';
        $orden     = (int)($d['iOrder'] ?? 0);
        $status    = (int)($d['iStatusCatTipoSesiones'] ?? 1);

        if (!in_array($modalidad, ['pleno', 'comision', 'diputacion'], true)) {
            $modalidad = 'pleno';
        }

        if ($id > 0) {
            $sql  = "UPDATE cattiposesesiones
                     SET cCatTipoSesiones=?, cModalidadSesion=?, iOrder=?, iStatusCatTipoSesiones=?
                     WHERE iIdCatTipoSesiones=?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param("ssiii", $nombre, $modalidad, $orden, $status, $id);
            return $stmt->execute() ? $id : false;
        }

        $sql  = "INSERT INTO cattiposesesiones (cCatTipoSesiones, cModalidadSesion, iOrder, iStatusCatTipoSesiones)
                 VALUES (?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("ssii", $nombre, $modalidad, $orden, $status);
        return $stmt->execute() ? (int)$this->conn->insert_id : false;
    }

    public function toggleStatusTipoSesion(int $id): bool {
        $sql  = "UPDATE cattiposesesiones
                 SET iStatusCatTipoSesiones = IF(iStatusCatTipoSesiones=1,0,1)
                 WHERE iIdCatTipoSesiones=?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ================================================================
    //  SESIONES  (catsesiones)
    // ================================================================

    public function listarSesiones(): array {
        $sql = "SELECT s.iIdCatSesion, s.cNombreCatSesion, s.iTipoSesion, s.iStatusCatSesion,
                       IFNULL(t.cCatTipoSesiones, '—') AS cTipoNombre
                FROM catsesiones s
                LEFT JOIN cattiposesesiones t ON t.iIdCatTipoSesiones = s.iTipoSesion
                ORDER BY t.iOrder ASC, s.cNombreCatSesion ASC";
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** @return int|false */
    public function guardarSesion(array $d) {
        $id     = (int)($d['iIdCatSesion'] ?? 0);
        $nombre = trim($d['cNombreCatSesion'] ?? '');
        $tipo   = (int)($d['iTipoSesion'] ?? 0);
        $status = (int)($d['iStatusCatSesion'] ?? 1);

        if ($id > 0) {
            $sql  = "UPDATE catsesiones SET cNombreCatSesion=?, iTipoSesion=?, iStatusCatSesion=? WHERE iIdCatSesion=?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param("siii", $nombre, $tipo, $status, $id);
            return $stmt->execute() ? $id : false;
        }

        $sql  = "INSERT INTO catsesiones (cNombreCatSesion, iTipoSesion, iStatusCatSesion) VALUES (?,?,?)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("sii", $nombre, $tipo, $status);
        return $stmt->execute() ? (int)$this->conn->insert_id : false;
    }

    public function toggleStatusSesion(int $id): bool {
        $sql  = "UPDATE catsesiones SET iStatusCatSesion = IF(iStatusCatSesion=1,0,1) WHERE iIdCatSesion=?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getLastError(): string {
        return $this->conn->error ?: '';
    }
}
