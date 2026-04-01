<?php
class UsuarioModel {
    private $conn; // mysqli

    public function __construct() {
        $this->conn = new mysqli("localhost", "root", "", "transcriptor");
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // ============================================================
    //  DIPUTADOS (Tipo 3) - catálogos / histórico
    // ============================================================
    public function obtenerDiputadosActivos(): array
    {
        $sql = "SELECT iIdUsuario, cNombre AS nombre
                FROM usuarios
                WHERE iTipo = 3 AND iStatus = 1
                ORDER BY cNombre ASC";

        $res = $this->conn->query($sql);
        $out = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $out[] = $row;
            $res->free();
        }
        return $out;
    }

    public function obtenerNombrePorId($idUsuario): ?string
    {
        $idUsuario = (int)$idUsuario;
        $sql = "SELECT cNombre FROM usuarios WHERE iIdUsuario = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ? (string)$row['cNombre'] : null;
    }

    // ============================================================
    //  LOGIN (Tipo 1 y 2 por ahora)
    // ============================================================
    public function buscarPorUsuario(string $cUsuario): ?array
    {
        $cUsuario = trim($cUsuario);

        $sql = "SELECT iIdUsuario, cNombre, cUsuario, cPasswordHash,
                       bPuedeLogin, dUltimoAcceso, iTipo, iStatus
                FROM usuarios
                WHERE cUsuario = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("s", $cUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }

    public function marcarUltimoAcceso(int $idUsuario): bool
    {
        $sql = "UPDATE usuarios SET dUltimoAcceso = NOW() WHERE iIdUsuario = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("i", $idUsuario);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }

    // (opcional) Para usar más adelante en admin: validar que exista username
    public function existeUsuario(string $cUsuario): bool
    {
        $sql = "SELECT 1 FROM usuarios WHERE cUsuario = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("s", $cUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        $existe = $res && $res->num_rows > 0;
        $stmt->close();

        return $existe;
    }
}
