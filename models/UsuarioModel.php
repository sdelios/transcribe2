<?php
require_once __DIR__ . '/DiputadoModel.php';

class UsuarioModel {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) die("Error de conexión: " . $this->conn->connect_error);
        $this->conn->set_charset("utf8mb4");
    }

    // ================================================================
    //  DIPUTADOS — ahora delegan a DiputadoModel
    // ================================================================

    public function obtenerDiputadosActivos(): array {
        $dm = new DiputadoModel();
        return $dm->obtenerActivosPorLegislatura();
    }

    /** Busca nombre en diputados primero, luego fallback a usuarios */
    public function obtenerNombrePorId($id): ?string {
        $id = (int)$id;
        if ($id <= 0) return null;

        $dm   = new DiputadoModel();
        $name = $dm->obtenerNombrePorId($id);
        if ($name !== null) return $name;

        // Fallback: buscar en usuarios (admin / tipo 2)
        $stmt = $this->conn->prepare("SELECT cNombre FROM usuarios WHERE iIdUsuario = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (string)$row['cNombre'] : null;
    }

    // ================================================================
    //  LOGIN
    // ================================================================

    public function buscarPorUsuario(string $cUsuario): ?array {
        $cUsuario = trim($cUsuario);
        $stmt = $this->conn->prepare(
            "SELECT iIdUsuario, cNombre, cUsuario, cPasswordHash,
                    bPuedeLogin, dUltimoAcceso, iTipo, iStatus
             FROM usuarios
             WHERE cUsuario = ? AND iTipo IN (1,2)
             LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param("s", $cUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function marcarUltimoAcceso(int $idUsuario): bool {
        $stmt = $this->conn->prepare("UPDATE usuarios SET dUltimoAcceso = NOW() WHERE iIdUsuario = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $idUsuario);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    // ================================================================
    //  GESTIÓN DE USUARIOS (admin + tipo 2)
    // ================================================================

    public function listarUsuarios(): array {
        $res = $this->conn->query(
            "SELECT iIdUsuario, cNombre, cUsuario, iTipo, iStatus, dUltimoAcceso
             FROM usuarios
             WHERE iTipo IN (1,2)
             ORDER BY iTipo ASC, cNombre ASC"
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function guardarUsuario(array $d): int|false {
        $id       = (int)($d['iIdUsuario'] ?? 0);
        $nombre   = trim($d['cNombre']   ?? '');
        $usuario  = trim($d['cUsuario']  ?? '');
        $tipo     = (int)($d['iTipo']    ?? 2);
        $status   = (int)($d['iStatus']  ?? 1);
        $password = trim($d['cPassword'] ?? '');

        if ($nombre === '' || $usuario === '') return false;
        if (!in_array($tipo, [1, 2])) $tipo = 2;

        if ($id > 0) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $this->conn->prepare(
                    "UPDATE usuarios SET cNombre=?, cUsuario=?, cPasswordHash=?, iTipo=?, iStatus=? WHERE iIdUsuario=?"
                );
                if (!$stmt) return false;
                $stmt->bind_param("sssiii", $nombre, $usuario, $hash, $tipo, $status, $id);
            } else {
                $stmt = $this->conn->prepare(
                    "UPDATE usuarios SET cNombre=?, cUsuario=?, iTipo=?, iStatus=? WHERE iIdUsuario=?"
                );
                if (!$stmt) return false;
                $stmt->bind_param("ssiii", $nombre, $usuario, $tipo, $status, $id);
            }
            return $stmt->execute() ? $id : false;
        }

        if ($password === '') return false;
        if ($this->existeUsuario($usuario)) return false;

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (cNombre, cUsuario, cPasswordHash, iTipo, bPuedeLogin, iStatus)
             VALUES (?, ?, ?, ?, 1, ?)"
        );
        if (!$stmt) return false;
        $stmt->bind_param("sssii", $nombre, $usuario, $hash, $tipo, $status);
        return $stmt->execute() ? (int)$this->conn->insert_id : false;
    }

    public function resetPassword(int $id, string $newPassword): bool {
        if ($id <= 0 || $newPassword === '') return false;
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("UPDATE usuarios SET cPasswordHash=? WHERE iIdUsuario=?");
        if (!$stmt) return false;
        $stmt->bind_param("si", $hash, $id);
        return $stmt->execute();
    }

    public function toggleStatus(int $id): bool {
        $stmt = $this->conn->prepare(
            "UPDATE usuarios SET iStatus = IF(iStatus=1,0,1) WHERE iIdUsuario=? AND iTipo IN (1,2)"
        );
        if (!$stmt) return false;
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function existeUsuario(string $cUsuario, int $excludeId = 0): bool {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM usuarios WHERE cUsuario = ? AND iIdUsuario <> ? LIMIT 1"
        );
        if (!$stmt) return false;
        $stmt->bind_param("si", $cUsuario, $excludeId);
        $stmt->execute();
        $res   = $stmt->get_result();
        $existe = $res && $res->num_rows > 0;
        $stmt->close();
        return $existe;
    }
}
