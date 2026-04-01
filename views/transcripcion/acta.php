<?php
session_start();

if (isset($_POST['tTrans'])) {
  $_SESSION['ultimo_transcripcion'] = $_POST['tTrans'];
}
if (isset($_POST['iIdTrans'])) {
  $_SESSION['id_trans_actual'] = (int) $_POST['iIdTrans'];
}
$idTransActual = $_SESSION['id_trans_actual'] ?? 0;

/* ====== Consultar si ya hay Actas (normal y con archivo) ====== */
$tieneActa = false;
$tieneActaArchivo = false;
$actaGuardada = '';
$actaArchivoGuardada = '';

if ($idTransActual > 0) {
  $mysqli = new mysqli('localhost','root','','transcriptor');
  if (!$mysqli->connect_error) {
    $mysqli->set_charset('utf8mb4');
    $stmt = $mysqli->prepare('SELECT tActaHtml, tActaArchivoHtml FROM transcripciones WHERE iIdTrans = ?');
    $stmt->bind_param('i', $idTransActual);
    $stmt->execute();
    $stmt->bind_result($actaHtml, $actaArchivoHtml);
    if ($stmt->fetch()) {
      if (!empty($actaHtml)) {
        $tieneActa = true;
        $actaGuardada = $actaHtml;
        $_SESSION['resultado_html'] = $actaGuardada; // para reusar flujo
      }
      if (!empty($actaArchivoHtml)) {
        $tieneActaArchivo = true;
        $actaArchivoGuardada = $actaArchivoHtml;
        $_SESSION['resultado_html_archivo'] = $actaArchivoGuardada; // para panel 2
      }
    }
    $stmt->close();
    $mysqli->close();
  }
}

/* Resultado en sesión (uno o ambos) */
$hayResultadoNormal  = isset($_SESSION['resultado_html']);
$hayResultadoArchivo = isset($_SESSION['resultado_html_archivo']);
$hayError            = isset($_SESSION['error_msg']);
?>

<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;}
  header{padding:24px 16px;background:white;border-bottom:1px solid #e5e7eb}
  .wrap{max-width:1100px;margin:0 auto;padding:24px 16px}
  .card{background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px 18px}
  .grid{display:grid;gap:16px}
  @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
  textarea{width:100%;min-height:220px;border:1px solid #cbd5e1;border-radius:10px;padding:10px;font-size:14px}
  label{font-weight:600;margin-bottom:6px;display:block}
  .btn2{background:#0ea5e9;color:white;border:none;border-radius:10px;padding:12px 16px;font-weight:600;cursor:pointer}
  .btn2:disabled{opacity:.6;cursor:not-allowed}
  .muted{color:#64748b;font-size:14px}
  .notice{padding:12px 14px;border-radius:10px;margin-bottom:16px}
  .error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
  .success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
  .result{padding:0}
  .result .inner{padding:18px}
  .result h1,.result h2,.result h3{margin-top:0}
  .acta-frame{width:100%;min-height:900px;border:1px solid #e5e7eb;background:#fff;}
  .btns-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px}
</style>

<div class="table-container card-style mb-4">
  <div class="card-header-title">Generador de Acta y Síntesis</div>
  <div class="table-responsive">
    <main class="wrap">
      <?php if ($hayError): ?>
        <div class="notice error">
          <?php echo nl2br(htmlspecialchars($_SESSION['error_msg'])); unset($_SESSION['error_msg']); ?>
        </div>
      <?php endif; ?>

      <?php if ($tieneActa): ?>
        <div class="notice success">Esta transcripción ya tiene un <strong>Acta (normal)</strong> generada.</div>
      <?php endif; ?>
      <?php if ($tieneActaArchivo): ?>
        <div class="notice success">Esta transcripción ya tiene un <strong>Acta (con archivo de referencia)</strong> generada.</div>
      <?php endif; ?>

      <!-- Un solo formulario con dos botones: normal y con archivo -->
      <form class="card" method="post">
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

        <div class="btns-row">
          <!-- Botón Normal -->
          <button class="btn2" type="submit"
                  formaction="index.php?ruta=acta/procesar"
                  <?php echo $tieneActa ? 'disabled' : ''; ?>>
            Generar Acta + Síntesis
          </button>

          <!-- Botón Con Archivo -->
          <button class="btn2" type="submit"
                  formaction="index.php?ruta=acta/procesarconarchivo2pasos"
                  <?php echo $tieneActaArchivo ? 'disabled' : ''; ?>>
            Generar con archivo de referencia
          </button>

          <span class="muted">El procesamiento puede tardar unos segundos.</span>
        </div>
      </form>
    </main>
  </div>
</div>

<!-- ========= Resultado NORMAL ========= -->
<section id="resultado-normal" class="card result" style="margin-top:18px;">
  <div class="inner">
    <h2>Resultado (normal)</h2>
    <div class="footer-note muted">Se mostrará aquí cuando termine el procesamiento.</div>
    <hr>
    <?php if ($hayResultadoNormal): ?>
        <?php $doc = $_SESSION['resultado_html']; ?>
        <iframe id="actaFrameNormal" class="acta-frame"></iframe>
        <script>
        (function(){
          const html = <?=
            json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
          ?>;
          const ifr = document.getElementById('actaFrameNormal');
          const doc = ifr.contentDocument || ifr.contentWindow.document;
          doc.open(); doc.write(html); doc.close();

          function resize(){
            try{
              const h = Math.max(doc.body.scrollHeight, doc.documentElement?.scrollHeight || 0);
              ifr.style.height = (h + 20) + 'px';
            }catch(e){}
          }
          ifr.addEventListener('load', resize);
          setTimeout(resize, 300);
        })();
        </script>

      <?php if ($idTransActual): ?>
        <form action="index.php?ruta=acta/guardar" method="POST" style="margin-top:12px;">
          <input type="hidden" name="iIdTrans" value="<?= (int)$idTransActual ?>">
          <textarea name="tActaHtml" style="display:none;"><?= $doc ?></textarea>
          <button type="submit" class="btn2" <?= $tieneActa ? 'disabled' : '' ?>>Guardar Acta (normal)</button>
          <span class="muted" style="margin-left:8px;">Se guardará el HTML completo con sus estilos.</span>
        </form>
      <?php endif; ?>

      <?php unset($_SESSION['resultado_html']); ?>
    <?php endif; ?>
  </div>
</section>

<!-- ========= Resultado CON ARCHIVO ========= -->
<section id="resultado-archivo" class="card result" style="margin-top:18px;">
  <div class="inner">
    <h2>Resultado (con archivo de referencia)</h2>
    <div class="footer-note muted">Se mostrará aquí cuando termine el procesamiento.</div>
    <hr>
    <?php if ($hayResultadoArchivo): ?>
      <?php $docA = $_SESSION['resultado_html_archivo']; ?>
        <iframe id="actaFrameArchivo" class="acta-frame"></iframe>
        <script>
        (function(){
          const html = <?=
            json_encode($docA, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
          ?>;
          const ifr = document.getElementById('actaFrameArchivo');
          const doc = ifr.contentDocument || ifr.contentWindow.document;
          doc.open(); doc.write(html); doc.close();

          function resize(){
            try{
              const h = Math.max(doc.body.scrollHeight, doc.documentElement?.scrollHeight || 0);
              ifr.style.height = (h + 20) + 'px';
            }catch(e){}
          }
          ifr.addEventListener('load', resize);
          setTimeout(resize, 300);
        })();
        </script>


      <?php if ($idTransActual): ?>
        <form action="index.php?ruta=acta/guardarArchivo" method="POST" style="margin-top:12px;">
          <input type="hidden" name="iIdTrans" value="<?= (int)$idTransActual ?>">
          <textarea name="tActaArchivoHtml" style="display:none;"><?= $docA ?></textarea>
          <button type="submit" class="btn2" <?= $tieneActaArchivo ? 'disabled' : '' ?>>Guardar Acta (con archivo)</button>
          <span class="muted" style="margin-left:8px;">Se guardará el HTML completo con sus estilos.</span>
        </form>
      <?php endif; ?>

      <?php unset($_SESSION['resultado_html_archivo']); ?>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($_SESSION['fs_camino'])): ?>
  <div class="muted">Camino usado: <?= htmlspecialchars($_SESSION['fs_camino']) ?> <?php
    if (!empty($_SESSION['fs_vs_id'])) echo ' · VS: ' . htmlspecialchars($_SESSION['fs_vs_id']);
    unset($_SESSION['fs_camino'], $_SESSION['fs_vs_id']);
  ?></div>
<?php endif; ?>


<?php
// Limpiar “últimos” pegados después de render
unset($_SESSION['ultimo_orden'], $_SESSION['ultimo_transcripcion']);
?>
