<?php
require_once __DIR__ . '/../models/UsuarioModel.php';

class AuthController
{
    private $usuarioModel;

    public function __construct()
    {
        // La sesión ya se inicia en index.php, pero por seguridad:
        if (session_status() === PHP_SESSION_NONE) session_start();

        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Muestra el formulario de login
     * Ruta: index.php?ruta=auth/login
     */
    public function login()
    {
        // Si ya está logueado, lo mandamos al inicio (ajústalo si quieres otra landing)
        if (!empty($_SESSION['auth'])) {
            header("Location: index.php?ruta=proceso/formulario");
            exit;
        }

        $error = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']);

        // Si tienes layout.php y lo usas en tus otras vistas, úsalo igual aquí.
        // Si no tienes layout, puedes incluir directo la vista.
        $view = __DIR__ . '/../views/auth/login.php';

        // Si tu layout.php espera una variable $view para incluirla, esto es compatible.
        // Si tu layout funciona diferente, me pasas tu layout y lo ajusto.
        if (file_exists(__DIR__ . '/../layout.php')) {
            include __DIR__ . '/../layout.php';
        } else {
            include $view;
        }
    }

    /**
     * Procesa el login (POST)
     * Ruta: index.php?ruta=auth/validar
     */
    public function validar()
    {
        // Si llega por GET, lo regresamos
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header("Location: index.php?ruta=auth/login");
            exit;
        }

        $usuario = trim($_POST['usuario'] ?? '');
        $pass    = (string)($_POST['password'] ?? '');

        if ($usuario === '' || $pass === '') {
            $_SESSION['login_error'] = "Completa usuario y contraseña.";
            header("Location: index.php?ruta=auth/login");
            exit;
        }

        $u = $this->usuarioModel->buscarPorUsuario($usuario);

        /**
         * Reglas de acceso:
         * - Debe existir
         * - iStatus = 1
         * - bPuedeLogin = 1
         * - Por ahora SOLO iTipo 1 y 2
         */
        if (
            !$u ||
            (int)$u['iStatus'] !== 1 ||
            (int)$u['bPuedeLogin'] !== 1 ||
            !in_array((int)$u['iTipo'], [1, 2], true)
        ) {
            $_SESSION['login_error'] = "Acceso no permitido.";
            header("Location: index.php?ruta=auth/login");
            exit;
        }

        // Verificación de password con hash
        if (!password_verify($pass, $u['cPasswordHash'] ?? '')) {
            $_SESSION['login_error'] = "Credenciales incorrectas.";
            header("Location: index.php?ruta=auth/login");
            exit;
        }

        // Login OK: guardamos sesión
        $_SESSION['auth'] = [
            'iIdUsuario' => (int)$u['iIdUsuario'],
            'cNombre'    => (string)$u['cNombre'],
            'cUsuario'   => (string)$u['cUsuario'],
            'iTipo'      => (int)$u['iTipo'],
        ];

        // Marca último acceso
        $this->usuarioModel->marcarUltimoAcceso((int)$u['iIdUsuario']);

        // Redirección post-login (ajústala a tu landing real)
        header("Location: index.php?ruta=proceso/formulario");
        exit;
    }

    /**
     * Cierra sesión
     * Ruta: index.php?ruta=auth/logout
     */
    public function logout()
    {
        // Limpia sesión
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p["path"], $p["domain"], $p["secure"], $p["httponly"]
            );
        }
        session_destroy();

        header("Location: index.php?ruta=auth/login");
        exit;
    }
}
