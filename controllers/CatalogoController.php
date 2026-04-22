<?php
require_once __DIR__ . '/../controllers/Controller.php';
require_once __DIR__ . '/../models/CatalogoModel.php';
require_once __DIR__ . '/../models/DiputadoModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class CatalogoController extends Controller {

    private CatalogoModel $model;
    private DiputadoModel $dipModel;
    private UsuarioModel  $userModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->model     = new CatalogoModel();
        $this->dipModel  = new DiputadoModel();
        $this->userModel = new UsuarioModel();
    }

    // ================================================================
    //  TIPOS DE SESIÓN
    // ================================================================

    public function tiposSesion(): void {
        $this->requireAdmin();
        $tipos   = $this->model->listarTiposSesion();
        $mensaje = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/tipos_sesion.php';
        include __DIR__ . '/../layout.php';
    }

    public function tiposSesionGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/tiposSesion');
            exit;
        }
        $ok = $this->model->guardarTipoSesion($_POST);
        $_SESSION['cat_msg'] = $ok !== false
            ? 'Tipo de sesión guardado correctamente.'
            : 'Error al guardar: ' . htmlspecialchars($this->model->getLastError());
        header('Location: index.php?ruta=catalogo/tiposSesion');
        exit;
    }

    public function tiposSesionToggle(): void {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $this->model->toggleStatusTipoSesion($id);
        header('Location: index.php?ruta=catalogo/tiposSesion');
        exit;
    }

    // ================================================================
    //  SESIONES
    // ================================================================

    public function sesiones(): void {
        $this->requireAdmin();
        $sesiones    = $this->model->listarSesiones();
        $tiposSesion = $this->model->listarTiposSesion();
        $mensaje     = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/sesiones.php';
        include __DIR__ . '/../layout.php';
    }

    public function sesionesGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/sesiones');
            exit;
        }
        $ok = $this->model->guardarSesion($_POST);
        $_SESSION['cat_msg'] = $ok !== false
            ? 'Sesión guardada correctamente.'
            : 'Error al guardar: ' . htmlspecialchars($this->model->getLastError());
        header('Location: index.php?ruta=catalogo/sesiones');
        exit;
    }

    public function sesionesToggle(): void {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $this->model->toggleStatusSesion($id);
        header('Location: index.php?ruta=catalogo/sesiones');
        exit;
    }

    // ================================================================
    //  LEGISLATURAS
    // ================================================================

    public function legislaturas(): void {
        $this->requireAdmin();
        $legislaturas = $this->dipModel->listarLegislaturas();
        $mensaje      = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/legislaturas.php';
        include __DIR__ . '/../layout.php';
    }

    public function legislaturasGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/legislaturas');
            exit;
        }
        $ok = $this->dipModel->guardarLegislatura($_POST);
        $_SESSION['cat_msg'] = $ok !== false
            ? 'Legislatura guardada correctamente.'
            : 'Error al guardar: ' . htmlspecialchars($this->dipModel->getLastError());
        header('Location: index.php?ruta=catalogo/legislaturas');
        exit;
    }

    public function legislaturasActivar(): void {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $this->dipModel->activarLegislatura($id);
        header('Location: index.php?ruta=catalogo/legislaturas');
        exit;
    }

    // ================================================================
    //  DIPUTADOS
    // ================================================================

    public function diputados(): void {
        $this->requireAdmin();
        $legislaturas = $this->dipModel->listarLegislaturas();
        $legActiva    = $this->dipModel->legislaturaActiva();
        $legFiltro    = (int)($_GET['leg'] ?? ($legActiva['id'] ?? 0));
        $diputados    = $this->dipModel->listarDiputados($legFiltro ?: null);
        $titulares    = $this->dipModel->obtenerTitularesPorLegislatura($legFiltro ?: null);
        $partidos     = $this->dipModel->listarPartidos();
        $mensaje      = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/diputados.php';
        include __DIR__ . '/../layout.php';
    }

    public function diputadosGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/diputados');
            exit;
        }
        $ok = $this->dipModel->guardarDiputado($_POST);
        $_SESSION['cat_msg'] = $ok !== false
            ? 'Diputado guardado correctamente.'
            : 'Error al guardar: ' . htmlspecialchars($this->dipModel->getLastError());
        header('Location: index.php?ruta=catalogo/diputados');
        exit;
    }

    public function diputadosFinalizarMandato(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/diputados');
            exit;
        }
        $id    = (int)($_POST['id'] ?? 0);
        $fecha = trim($_POST['fecha_fin'] ?? date('Y-m-d'));
        if ($id && $fecha) {
            $this->dipModel->finalizarMandato($id, $fecha);
            $_SESSION['cat_msg'] = 'Mandato finalizado. Puede registrar al diputado sustituto.';
        }
        header('Location: index.php?ruta=catalogo/diputados');
        exit;
    }

    public function diputadosToggle(): void {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $this->dipModel->toggleActivo($id);
        header('Location: index.php?ruta=catalogo/diputados');
        exit;
    }

    // ================================================================
    //  PARTIDOS
    // ================================================================

    public function partidos(): void {
        $this->requireAdmin();
        $partidos = $this->dipModel->listarPartidos();
        $mensaje  = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/partidos.php';
        include __DIR__ . '/../layout.php';
    }

    public function partidosGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/partidos');
            exit;
        }
        $ok = $this->dipModel->guardarPartido($_POST);
        $_SESSION['cat_msg'] = $ok !== false
            ? 'Partido guardado correctamente.'
            : 'Error al guardar: ' . htmlspecialchars($this->dipModel->getLastError());
        header('Location: index.php?ruta=catalogo/partidos');
        exit;
    }

    public function partidosToggle(): void {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $this->dipModel->toggleActivoPartido($id);
        header('Location: index.php?ruta=catalogo/partidos');
        exit;
    }

    // ================================================================
    //  USUARIOS (admin + tipo 2)
    // ================================================================

    public function usuarios(): void {
        $this->requireAdmin();
        $usuarios = $this->userModel->listarUsuarios();
        $mensaje  = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/usuarios.php';
        include __DIR__ . '/../layout.php';
    }

    public function usuariosGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/usuarios');
            exit;
        }
        $ok = $this->userModel->guardarUsuario($_POST);
        $_SESSION['cat_msg'] = $ok !== false
            ? 'Usuario guardado correctamente.'
            : 'Error al guardar: revise que el nombre de usuario no esté en uso.';
        header('Location: index.php?ruta=catalogo/usuarios');
        exit;
    }

    public function usuariosReset(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/usuarios');
            exit;
        }
        $id  = (int)($_POST['id'] ?? 0);
        $pwd = trim($_POST['nueva_password'] ?? '');
        if ($id && strlen($pwd) >= 6) {
            $ok = $this->userModel->resetPassword($id, $pwd);
            $_SESSION['cat_msg'] = $ok ? 'Contraseña actualizada.' : 'Error al actualizar contraseña.';
        } else {
            $_SESSION['cat_msg'] = 'La contraseña debe tener al menos 6 caracteres.';
        }
        header('Location: index.php?ruta=catalogo/usuarios');
        exit;
    }

    public function usuariosToggle(): void {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $this->userModel->toggleStatus($id);
        header('Location: index.php?ruta=catalogo/usuarios');
        exit;
    }
}
