<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ALCEY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/transcribe2/public/css/estilos.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php
// Aseguramos sesión para saber si hay usuario logueado
if (session_status() === PHP_SESSION_NONE) session_start();
$auth = $_SESSION['auth'] ?? null;
?>

<header class="bg-dark text-white p-3">
    <div class="container d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">Asistente Legislativo</h1>

        <nav class="d-flex align-items-center">
<?php if ($auth): ?>

    <div class="bg-white rounded-4 shadow-sm px-3 py-2 d-flex align-items-center gap-2"
         style="box-shadow: 0 6px 18px rgba(0,0,0,.25);">

        <!-- Botones -->
        <a href="index.php?ruta=proceso/formulario"
           class="btn btn-sm btn-outline-dark fw-semibold">
            Transcribir
        </a>

        <a href="index.php?ruta=audio/lista"
           class="btn btn-sm btn-outline-dark fw-semibold">
            Lista de Audios
        </a>

        <a href="index.php?ruta=transcripcion/lista"
           class="btn btn-sm btn-outline-dark fw-semibold">
            Transcripciones
        </a>

        <!-- Separador -->
        <div class="vr mx-2"></div>

        <!-- Usuario -->
        <span class="badge bg-light text-dark border fw-semibold px-3 py-2"
              style="box-shadow: inset 0 0 4px rgba(0,0,0,.15);">
            <?= htmlspecialchars($auth['cNombre']) ?>
        </span>

        <!-- Cerrar sesión -->
        <a href="index.php?ruta=auth/logout"
           class="btn btn-danger btn-sm rounded-circle d-flex align-items-center justify-content-center"
           style="width:34px;height:34px;"
           title="Cerrar sesión">
            ✕
        </a>

    </div>

<?php endif; ?>
</nav>

    </div>
</header>
<main class="container">
    <?php include_once($view); ?>
</main>

<footer class="mt-auto bg-dark text-white text-center p-3">
    <div class="container">
        ALCEY V1.0 - <?= date('Y'); ?>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="public/js/script.js"></script>
</body>
</html>
