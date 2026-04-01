<?php
session_start();
$hayResultado = isset($_SESSION['resultado_html']);
$hayError = isset($_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Generador de Acta y Síntesis</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;
         margin:0;background:#f5f7fb;color:#1e293b}
    header{padding:24px 16px;background:white;border-bottom:1px solid #e5e7eb}
    .wrap{max-width:1100px;margin:0 auto;padding:24px 16px}
    .card{background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px 18px}
    .grid{display:grid;gap:16px}
    @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
    textarea{width:100%;min-height:220px;border:1px solid #cbd5e1;border-radius:10px;padding:10px;font-size:14px}
    label{font-weight:600;margin-bottom:6px;display:block}
    .btn{background:#0ea5e9;color:white;border:none;border-radius:10px;padding:12px 16px;
         font-weight:600;cursor:pointer}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .muted{color:#64748b;font-size:12px}
    .notice{padding:12px 14px;border-radius:10px;margin-bottom:16px}
    .error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .result{padding:0}
    .result .inner{padding:18px}
    .result h1,.result h2,.result h3{margin-top:0}
    .footer-note{margin-top:8px}
  </style>
</head>
<body>
<header>
  <div class="wrap">
    <h1>Generador de Acta y Síntesis</h1>
    <p class="muted">Pega la <strong>Orden del Día</strong> y la <strong>Transcripción completa</strong>. El resultado se mostrará abajo.</p>
  </div>
</header>

<main class="wrap">
  <?php if ($hayError): ?>
    <div class="notice error">
      <?php echo nl2br(htmlspecialchars($_SESSION['error_msg'])); unset($_SESSION['error_msg']); ?>
    </div>
  <?php endif; ?>

  <form class="card" action="procesar.php" method="post">
    <div class="grid">
      <div>
        <label for="orden">Orden del día</label>
        <textarea id="orden" name="orden" placeholder="Pega aquí la Orden del Día..." required><?php
          echo isset($_SESSION['ultimo_orden']) ? htmlspecialchars($_SESSION['ultimo_orden']) : '';
        ?></textarea>
      </div>
      <div>
        <label for="transcripcion">Transcripción completa</label>
        <textarea id="transcripcion" name="transcripcion" placeholder="Pega aquí la transcripción de la sesión..." required><?php
          echo isset($_SESSION['ultimo_transcripcion']) ? htmlspecialchars($_SESSION['ultimo_transcripcion']) : '';
        ?></textarea>
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;margin-top:12px">
      <button class="btn" type="submit">Generar Acta + Síntesis</button>
      <span class="muted">El procesamiento puede tardar unos segundos.</span>
    </div>
  </form>

  <section id="resultado" class="card result" style="margin-top:18px;">
    <div class="inner">
      <h2>Resultado</h2>
      <div class="footer-note muted">Se mostrará aquí cuando termine el procesamiento.</div>
      <hr>
      <div>
        <?php
          if ($hayResultado) {
            // Mostramos el HTML tal cual lo generó el modelo (ya viene con <h1>, <section>, etc.)
            echo $_SESSION['resultado_html'];
            // Limpiamos el resultado para no re-renderizar en cada reload
            unset($_SESSION['resultado_html']);
          }
          // Limpieza de campos “últimos”
          unset($_SESSION['ultimo_orden'], $_SESSION['ultimo_transcripcion']);
        ?>
      </div>
    </div>
  </section>
</main>
</body>
</html>


