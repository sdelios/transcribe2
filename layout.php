<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ALCEY — Asistente Legislativo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/transcribe2/public/css/estilos.css">
</head>
<body>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$auth = $_SESSION['auth'] ?? null;
$ruta_actual = $_GET['ruta'] ?? '';
?>

<header class="bg-dark text-white py-2">
    <div class="container d-flex justify-content-between align-items-center">

        <!-- Logo -->
        <a href="index.php?ruta=proceso/formulario" class="text-white text-decoration-none d-flex align-items-center gap-2">
            <span style="font-size:1.6rem;line-height:1;">⚖</span>
            <div>
                <div style="font-size:1.15rem;font-weight:900;letter-spacing:.08em;line-height:1.1;font-family:'Lato',sans-serif;">ALCEY</div>
                <div style="font-size:.58rem;color:#9ca3af;letter-spacing:.1em;text-transform:uppercase;font-weight:400;font-family:'Lato',sans-serif;">Asistente Legislativo</div>
            </div>
        </a>

        <!-- Nav -->
        <?php if ($auth): ?>
        <div class="nav-card">

            <a href="index.php?ruta=proceso/formulario"
               class="nav-btn <?= strpos($ruta_actual,'proceso') === 0 ? 'active' : '' ?>">
                Transcribir
            </a>
            <?php if (($auth['iTipo'] ?? 0) == 1): ?>
            <a href="index.php?ruta=audio/lista"
               class="nav-btn <?= strpos($ruta_actual,'audio') === 0 ? 'active' : '' ?>">
                Audios
            </a>
            <?php endif; ?>

            <a href="index.php?ruta=transcripcion/lista"
               class="nav-btn <?= strpos($ruta_actual,'transcripcion') === 0 ? 'active' : '' ?>">
                Transcripciones
            </a>

            <?php if (($auth['iTipo'] ?? 0) == 1): ?>
            <div class="dropdown">
                <button class="nav-btn <?= strpos($ruta_actual,'catalogo') === 0 ? 'active' : '' ?>"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Catálogos <span style="font-size:.65rem;opacity:.7;">▾</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/tiposSesion">Tipos de Sesión</a></li>
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/sesiones">Sesiones</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/legislaturas">Legislaturas</a></li>
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/diputados">Diputados</a></li>
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/partidos">Partidos</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/usuarios">Usuarios</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="index.php?ruta=catalogo/configuracion">⚙ Configuración</a></li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="nav-sep"></div>

            <?php
            // Icono de API activa (cache en sesión para evitar query en cada carga)
            if (!isset($_SESSION['api_proveedor_nav'])) {
                try {
                    $cfgN = new mysqli("localhost","root","","transcriptor");
                    $cfgN->set_charset("utf8mb4");
                    $r = $cfgN->query("SELECT valor FROM config WHERE clave='api_proveedor' LIMIT 1");
                    $_SESSION['api_proveedor_nav'] = ($r && $r->num_rows > 0) ? $r->fetch_assoc()['valor'] : 'claude';
                    $cfgN->close();
                } catch (\Throwable $e) { $_SESSION['api_proveedor_nav'] = 'claude'; }
            }
            $navApi = $_SESSION['api_proveedor_nav'];
            ?>
            <a href="index.php?ruta=catalogo/configuracion" title="API activa: <?= $navApi === 'openai' ? 'OpenAI' : 'Claude' ?>"
               style="display:flex;align-items:center;text-decoration:none;opacity:.9;">
            <?php if ($navApi === 'openai'): ?>
              <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="11" cy="11" r="11" fill="#000"/>
                <path d="M15.5 8.27a3.38 3.38 0 0 0-.23-1.27 3.5 3.5 0 0 0-6.05-.74A3.38 3.38 0 0 0 6.5 8.1a3.5 3.5 0 0 0 .47 6.63 3.38 3.38 0 0 0 .89 1.17 3.5 3.5 0 0 0 5.87-1.31A3.5 3.5 0 0 0 15.5 8.27z" fill="white" opacity=".9"/>
              </svg>
            <?php else: ?>
              <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="11" cy="11" r="11" fill="#d97757"/>
                <path d="M11 5.5 L14.5 14.5 L12.8 14.5 L11.9 12.1 L10.1 12.1 L9.2 14.5 L7.5 14.5 Z M11 8.2 L10.6 11 L11.4 11 Z" fill="white"/>
              </svg>
            <?php endif; ?>
            </a>

            <span class="nav-user">
                <?= htmlspecialchars($auth['cNombre']) ?>
            </span>

            <a href="index.php?ruta=auth/logout" class="nav-logout" title="Cerrar sesión">✕</a>

        </div>
        <?php endif; ?>

    </div>
</header>

<main class="container">
    <?php include_once($view); ?>
</main>

<footer class="site-footer">
    <div class="container">
        ALCEY V1.0 &nbsp;·&nbsp; <?= date('Y') ?>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="public/js/script.js"></script>
</body>
</html>
