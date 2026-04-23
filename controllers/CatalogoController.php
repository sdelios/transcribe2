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

    // ================================================================
    //  CONFIGURACIÓN
    // ================================================================

    public function configuracion(): void {
        $this->requireAdmin();
        $apiProveedor = $this->model->obtenerConfig('api_proveedor') ?? 'claude';
        $mensaje      = $_SESSION['cat_msg'] ?? '';
        unset($_SESSION['cat_msg']);
        $view = __DIR__ . '/../views/catalogo/configuracion.php';
        include __DIR__ . '/../layout.php';
    }

    public function apiEstado(): void {
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $proveedor = $_GET['proveedor'] ?? 'claude';

        if ($proveedor === 'openai') {
            $key = $_ENV['OPENAI_API_KEY'] ?? '';
            if (!$key) { echo json_encode(['ok'=>false,'error'=>'OPENAI_API_KEY no configurada']); return; }

            // Verificar conexión
            $ch = curl_init('https://api.openai.com/v1/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key"],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $res  = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http !== 200) {
                echo json_encode(['ok'=>false,'error'=>"API Key inválida o sin acceso (HTTP $http)"]); return;
            }

            // Saldo / suscripción
            $hoy     = date('Y-m-d');
            $inicio  = date('Y-m-01');
            $chSub   = curl_init('https://api.openai.com/v1/dashboard/billing/subscription');
            curl_setopt_array($chSub, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key"],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $resSub = curl_exec($chSub);
            curl_close($chSub);
            $sub = json_decode($resSub, true);

            // Uso del mes actual
            $chUso = curl_init("https://api.openai.com/v1/dashboard/billing/usage?start_date=$inicio&end_date=$hoy");
            curl_setopt_array($chUso, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer $key"],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $resUso = curl_exec($chUso);
            curl_close($chUso);
            $uso = json_decode($resUso, true);

            $limite   = isset($sub['hard_limit_usd'])   ? (float)$sub['hard_limit_usd']   : null;
            $gastado  = isset($uso['total_usage'])       ? round($uso['total_usage'] / 100, 4) : null;
            $restante = ($limite !== null && $gastado !== null) ? round($limite - $gastado, 4) : null;

            // Top modelos del mes
            $lineas = [];
            if (!empty($uso['daily_costs'])) {
                $porModelo = [];
                foreach ($uso['daily_costs'] as $dia) {
                    foreach ($dia['line_items'] ?? [] as $li) {
                        $m = $li['name'] ?? 'unknown';
                        $porModelo[$m] = ($porModelo[$m] ?? 0) + ($li['cost'] ?? 0);
                    }
                }
                arsort($porModelo);
                foreach (array_slice($porModelo, 0, 8, true) as $mod => $costo) {
                    $lineas[] = ['modelo' => $mod, 'costo_usd' => round($costo / 100, 4)];
                }
            }

            echo json_encode([
                'ok'        => true,
                'proveedor' => 'openai',
                'limite_usd'   => $limite,
                'gastado_usd'  => $gastado,
                'restante_usd' => $restante,
                'periodo'   => "$inicio → $hoy",
                'modelos'   => $lineas,
            ], JSON_UNESCAPED_UNICODE);

        } else {
            // Claude / Anthropic — no tiene billing API pública, solo verificamos la key
            $key = $_ENV['ANTHROPIC_API_KEY'] ?? '';
            if (!$key) { echo json_encode(['ok'=>false,'error'=>'ANTHROPIC_API_KEY no configurada']); return; }

            $payload = json_encode([
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 1,
                'messages'   => [['role'=>'user','content'=>'hi']]
            ]);
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    "x-api-key: $key",
                    "anthropic-version: 2023-06-01",
                    "content-type: application/json",
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $res  = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($res, true);
            if ($http === 200) {
                $uso_in  = $data['usage']['input_tokens']  ?? '—';
                $uso_out = $data['usage']['output_tokens'] ?? '—';
                echo json_encode([
                    'ok'        => true,
                    'proveedor' => 'claude',
                    'modelo'    => $data['model'] ?? 'claude-haiku',
                    'ping_in'   => $uso_in,
                    'ping_out'  => $uso_out,
                    'nota'      => 'Anthropic no expone saldo ni historial de uso vía API. Consulta console.anthropic.com',
                ], JSON_UNESCAPED_UNICODE);
            } else {
                $msg = $data['error']['message'] ?? "HTTP $http";
                echo json_encode(['ok'=>false,'error'=>$msg]);
            }
        }
    }

    public function configuracionGuardar(): void {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?ruta=catalogo/configuracion');
            exit;
        }
        $proveedor = in_array($_POST['api_proveedor'] ?? '', ['claude', 'openai'])
            ? $_POST['api_proveedor']
            : 'claude';
        $this->model->guardarConfig('api_proveedor', $proveedor);
        $_SESSION['api_proveedor_nav'] = $proveedor; // actualiza cache del icono en nav
        $_SESSION['cat_msg'] = 'Configuración guardada. Proveedor activo: ' . strtoupper($proveedor);
        header('Location: index.php?ruta=catalogo/configuracion');
        exit;
    }
}
