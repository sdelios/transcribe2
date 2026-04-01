<?php
$textoFuente = $ultimaCorr['texto_taquigrafico'];
$idTrans     = $trans['iIdTrans'];
$idCorreccion= $ultimaCorr['id'];

$tieneActa   = $acta ? true : false;
$idActa      = $tieneActa ? $acta['id'] : null;

$textoActa   = $tieneActa ? ($acta['texto_acta'] ?? '') : '';
$charsActa   = $tieneActa ? ($acta['chars_acta'] ?? 0) : 0;

$textoSintesis   = $tieneActa ? ($acta['texto_sintesis'] ?? '') : '';
$charsSintesis   = $tieneActa ? ($acta['chars_sintesis'] ?? 0) : 0;

// =====================================================
// ✅ NUEVO: Defaults de mesa directiva desde:
// - acta_metadata (si existe)
// - si no existe, sesion_metadatos ligado a corrección
// =====================================================
$metaActa   = $metaActa   ?? null;
$metaSesion = $metaSesion ?? null;

// Defaults desde acta_metadata (si existe)
$selPres = $metaActa['presidente']    ?? '';
$selS1   = $metaActa['secretaria_1']  ?? '';
$selS2   = $metaActa['secretaria_2']  ?? '';

// Si NO hay metaActa, usa metadatos de sesión por corrección
if (!$metaActa) {
  $selPres = $metaSesion['iIdPresidente']  ?? $selPres;
  $selS1   = $metaSesion['iIdSecretario1'] ?? $selS1;
  $selS2   = $metaSesion['iIdSecretario2'] ?? $selS2;
}

// (Opcional) fecha default desde metaSesion si lo quieres usar en el modal
$defaultFechaSesion = $metaSesion['dFechaSesion'] ?? '';

// Defaults PHP -> JS para precargar el modal cuando NO hay acta_metadata aún
$metaDefaults = [
  'presidente'   => (string)$selPres,
  'secretaria_1' => (string)$selS1,
  'secretaria_2' => (string)$selS2,
  // 'fecha' => (string)$defaultFechaSesion, // si quieres precargar fecha
];
?>

<div class="table-container card-style mb-4">
  <div class="card-header-title">Generar Acta (nuevo flujo)</div>

  <div class="card-body">

    <h4 class="text-black">Transcripción taquigráfica de origen</h4>
    <div class="mb-2 small text-muted">
      ID Transcripción: <strong><?= (int)$idTrans ?></strong> ·
      Caracteres origen: <strong id="charsOrigen"><?= mb_strlen($textoFuente) ?></strong>
    </div>
    <textarea id="textoFuente" class="form-control" rows="10"><?= htmlspecialchars($textoFuente) ?></textarea>

    <!-- MODO DEBUG -->
    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="chkDebug">
      <label class="form-check-label" for="chkDebug">
        Modo prueba (solo chunking, sin OpenAI)
      </label>
    </div>

    <div class="d-flex gap-2 mt-3 mb-4" id="panelTopInicial">
      <button id="btnGenerarActa" class="btn btn-primary">
        <?= $tieneActa ? '🔄 Regenerar Acta' : '📝 Generar Acta' ?>
      </button>

      <a href="index.php?ruta=transcripcion/editar&id=<?= (int)$idTrans ?>" class="btn btn-secondary">
        ⬅ Volver a edición
      </a>
    </div>

    <!-- RESULTADO DEBUG -->
    <div id="panelDebug" class="card mb-3" style="display:none;">
      <div class="card-header bg-dark text-white">Resultado de prueba (chunking)</div>
      <div class="card-body">
        <div class="small text-muted mb-2" id="debugInfo"></div>

        <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
          <button type="button" id="btnPrevChunk" class="btn btn-outline-secondary btn-sm">⬅ Anterior</button>
          <select id="debugSelect" class="form-select form-select-sm" style="max-width: 220px;"></select>
          <button type="button" id="btnNextChunk" class="btn btn-outline-secondary btn-sm">Siguiente ➡</button>
          <span class="small text-muted" id="debugLen"></span>
          <button type="button" id="btnCopyChunk" class="btn btn-outline-primary btn-sm ms-auto">📋 Copiar</button>
        </div>

        <textarea id="debugOutput" class="form-control" rows="18" readonly></textarea>
      </div>
    </div>

    <!-- Panel Acta -->
    <div id="panelActa" style="display: <?= $tieneActa ? 'block' : 'none' ?>;">
      <div class="card mb-3">
        <div class="card-header bg-success text-white">Acta generada</div>
        <div class="card-body">
          <div class="mb-2 small text-muted">
            Caracteres acta: <strong id="charsActa"><?= (int)$charsActa ?></strong> ·
            Diferencia: <strong id="diffActa">
              <?php if ($tieneActa): ?>
                <?= round((($charsActa - mb_strlen($textoFuente)) / max(1, mb_strlen($textoFuente))) * 100, 2) ?>%
              <?php else: ?>0%<?php endif; ?>
            </strong>
          </div>
          <textarea id="textoActa" class="form-control" rows="12"><?= htmlspecialchars($textoActa) ?></textarea>
        </div>
      </div>

      <!-- BOTONES TOP (por estados) -->
      <div class="d-flex gap-2 mb-3 flex-wrap" id="panelBotonesTop">

        <!-- 1) Siempre visible cuando hay acta -->
        <button id="btnMeta" class="btn btn-danger" <?= $tieneActa ? '' : 'disabled' ?>>
          🧾 Datos del Acta
        </button>

        <!-- 2) Visible solo si metadata completo -->
        <button id="btnToggleEncabezado" class="btn btn-success" style="display:none;" <?= $tieneActa ? '' : 'disabled' ?>>
          🤖 Generar Encabezado
        </button>

        <!-- 3) Visible solo si hay encabezado -->
        <a id="btnWord" class="btn btn-dark disabled" style="display:none;"
           href="index.php?ruta=actanueva/descargarWord&acta_id=<?= (int)$idActa ?>"
           target="_blank" aria-disabled="true">
          🧾 Descargar Word
        </a>

        <!-- 4) Visible solo si hay encabezado y NO hay síntesis -->
        <button id="btnGenerarSintesis" class="btn btn-warning" style="display:none;" <?= $tieneActa ? '' : 'disabled' ?>>
          📄 Generar Síntesis
        </button>

        <button id="btnDebugSintesisChunks" class="btn btn-outline-dark" style="display:none;" <?= $tieneActa ? '' : 'disabled' ?>>
          🧪 Debug Chunks Síntesis
        </button>

      </div>

      <!-- PANEL ENCABEZADO -->
      <div id="panelEncabezadoAI" class="card mb-3" style="display:none;">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
          <span>Encabezado y primer párrafo (OpenAI)</span>
          <button type="button" class="btn btn-sm btn-outline-light" id="btnHideEncabezado">Ocultar</button>
        </div>

        <div class="card-body">
          <div class="small text-muted mb-2">
            Se guardó en <strong>acta_metadata</strong> (encabezado_ai / primer_parrafo_ai)
          </div>

          <label class="form-label mb-1">Encabezado</label>
          <textarea id="txtEncabezadoAI" class="form-control mb-3" rows="8" readonly></textarea>

          <label class="form-label mb-1">Primer párrafo</label>
          <textarea id="txtPrimerParrafoAI" class="form-control mb-3" rows="6" readonly></textarea>

          <div class="d-flex gap-2 justify-content-end">
            <button id="btnRegenerarEncabezado" class="btn btn-success" <?= $tieneActa ? '' : 'disabled' ?>>
              🔄 Regenerar Encabezado
            </button>
          </div>
        </div>
      </div>

      <!-- Panel Síntesis -->
      <div id="panelSintesis" style="display: <?= ($tieneActa && trim($textoSintesis) !== '') ? 'block' : 'none' ?>;">
        <div class="card mb-3">
          <div class="card-header bg-info text-white">Síntesis del acta</div>
          <div class="card-body">
            <div class="mb-2 small text-muted">
              Caracteres síntesis: <strong id="charsSintesis"><?= (int)$charsSintesis ?></strong>
            </div>
            <textarea id="textoSintesis" class="form-control" rows="10"><?= htmlspecialchars($textoSintesis) ?></textarea>
          </div>

          <!-- Acciones: SOLO cuando ya existe síntesis -->
          <div class="card-footer d-flex gap-2 flex-wrap" id="panelSintesisActions"
               style="display: <?= ($tieneActa && trim($textoSintesis) !== '') ? 'flex' : 'none' ?>;">

            <button id="btnRegenerarSintesis" class="btn btn-warning" <?= $tieneActa ? '' : 'disabled' ?>>
              🔄 Regenerar Síntesis
            </button>

            <button id="btnDebugSintesisChunks2" class="btn btn-outline-dark" <?= $tieneActa ? '' : 'disabled' ?>>
              🧪 Debug Chunks Síntesis
            </button>

            <a id="btnWordSintesis" class="btn btn-dark"
               href="index.php?ruta=actanueva/descargarWordSintesis&acta_id=<?= (int)$idActa ?>"
               target="_blank">
              🧾 Word Síntesis
            </a>

          </div>
        </div>
      </div>

      <div id="panelDebugSintesis" class="card mb-3" style="display:none;">
        <div class="card-header bg-dark text-white">Debug: Chunks para Síntesis (sin OpenAI)</div>
        <div class="card-body">
          <div class="small text-muted mb-2" id="debugSintesisInfo"></div>

          <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
            <button type="button" id="btnPrevChunkS" class="btn btn-outline-secondary btn-sm">⬅ Anterior</button>
            <select id="debugSelectS" class="form-select form-select-sm" style="max-width: 220px;"></select>
            <button type="button" id="btnNextChunkS" class="btn btn-outline-secondary btn-sm">Siguiente ➡</button>
            <span class="small text-muted" id="debugLenS"></span>
            <button type="button" id="btnCopyChunkS" class="btn btn-outline-primary btn-sm ms-auto">📋 Copiar</button>
          </div>

          <textarea id="debugOutputS" class="form-control" rows="18" readonly></textarea>
        </div>
      </div>

      <div class="alert alert-info" id="msgActa" style="display:none;"></div>

    </div><!-- /panelActa -->

  </div>
</div>

<!-- MODAL METADATA -->
<div class="modal fade" id="modalMeta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Datos del Acta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Clave del acta</label>
            <input type="text" class="form-control" id="m_clave_acta" placeholder="Acta 10/2o.A/1er.P.Ord./2025/LXIV">
          </div>

          <div class="col-md-6">
            <label class="form-label">Tipo de sesión</label>
            <select class="form-select" id="m_tipo_sesion">
              <option value="Ordinaria">Ordinaria</option>
              <option value="Extraordinaria">Extraordinaria</option>
              <option value="Solemne">Solemne</option>
              <option value="Comisión">Comisión</option>
              <option value="Comisiones Unidas">Comisiones Unidas</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Legislatura</label>
            <input type="text" class="form-control" id="m_legislatura" placeholder="LXIV">
          </div>

          <div class="col-md-8">
            <label class="form-label">Legislatura en texto</label>
            <input type="text" class="form-control" id="m_legislatura_texto" placeholder="Sexagésima Cuarta">
          </div>

          <div class="col-md-6">
            <label class="form-label">Periodo</label>
            <input type="text" class="form-control" id="m_periodo" placeholder="Primer Periodo Ordinario de Sesiones">
          </div>

          <div class="col-md-6">
            <label class="form-label">Ejercicio</label>
            <input type="text" class="form-control" id="m_ejercicio" placeholder="Segundo Año de su Ejercicio Constitucional">
          </div>

          <div class="col-md-4">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" id="m_fecha">
          </div>

          <div class="col-md-4">
            <label class="form-label">Hora inicio</label>
            <input type="time" class="form-control" id="m_hora_inicio">
          </div>

          <div class="col-md-4">
            <label class="form-label">Ciudad</label>
            <input type="text" class="form-control" id="m_ciudad" placeholder="Mérida">
          </div>

          <div class="col-md-12">
            <label class="form-label">Recinto</label>
            <input type="text" class="form-control" id="m_recinto" placeholder="Salón de Sesiones Constituyentes 1918">
          </div>

          <div class="col-md-12">
            <label class="form-label">Presidente</label>
            <select class="form-select" id="m_presidente" name="m_presidente" required>
              <option value="">-- Seleccione presidente --</option>
              <?php foreach ($diputados as $d):
                $id = (int)$d['iIdUsuario'];
                $selected = ((string)$selPres === (string)$id) ? 'selected' : '';
              ?>
                <option value="<?= $id ?>" <?= $selected ?>>
                  <?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Secretaria 1</label>
            <select class="form-select" id="m_secretaria_1" name="m_secretaria_1" required>
              <option value="">-- Seleccione secretaria --</option>
              <?php foreach ($diputados as $d):
                $id = (int)$d['iIdUsuario'];
                $selected = ((string)$selS1 === (string)$id) ? 'selected' : '';
              ?>
                <option value="<?= $id ?>" <?= $selected ?>>
                  <?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Secretaria 2</label>
            <select class="form-select" id="m_secretaria_2" name="m_secretaria_2">
              <option value="">-- Seleccione secretaria --</option>
              <?php foreach ($diputados as $d):
                $id = (int)$d['iIdUsuario'];
                $selected = ((string)$selS2 === (string)$id) ? 'selected' : '';
              ?>
                <option value="<?= $id ?>" <?= $selected ?>>
                  <?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

        </div>

        <div class="alert alert-info mt-3 mb-0 small" id="metaMsg" style="display:none;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarMeta">Guardar</button>
      </div>

    </div>
  </div>
</div>

<script>
  // BOOTSTRAP de variables PHP -> JS
  window.ACTA_NUEVA_BOOT = {
    actaId: <?= $tieneActa ? (int)$idActa : 'null' ?>,
    correccionId: <?= (int)$idCorreccion ?>,
    transcripcionId: <?= (int)$idTrans ?>,
    metaDefaults: <?= json_encode($metaDefaults, JSON_UNESCAPED_UNICODE) ?>
  };
</script>

<script src="public/js/acta_nueva/iniciar.js?v=1"></script>
