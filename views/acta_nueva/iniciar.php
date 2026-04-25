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

$metaActa   = $metaActa   ?? null;
$metaSesion = $metaSesion ?? null;
$legislaturas  = $legislaturas  ?? [];
$cat_periodo   = $cat_periodo   ?? [];
$cat_ejercicio = $cat_ejercicio ?? [];

// Defaults de mesa directiva: prioriza acta_metadata, fallback a sesion_metadatos
$selPres = $metaActa['presidente']    ?? ($metaSesion['iIdPresidente']  ?? '');
$selS1   = $metaActa['secretaria_1']  ?? ($metaSesion['iIdSecretario1'] ?? '');
$selS2   = $metaActa['secretaria_2']  ?? '';

// Defaults de catálogos para el modal
$selLeg  = $metaActa['id_legislatura'] ?? ($legActiva['id'] ?? '');
$selPer  = $metaActa['id_periodo']     ?? '';
$selEj   = $metaActa['id_ejercicio']   ?? '';

// Tipo y sesión (read-only, vienen de sesion_metadatos via join)
$tipoSesionNombre = $metaActa['tipo_sesion_nombre'] ?? ($metaSesion['cCatTipoSesiones'] ?? '');
$sesionNombre     = $metaActa['sesion_nombre_cat']  ?? ($metaSesion['cNombreCatSesion'] ?? '');

// Boot para JS
$metaDefaults = [
  'presidente'     => (string)$selPres,
  'secretaria_1'   => (string)$selS1,
  'secretaria_2'   => (string)$selS2,
  'id_legislatura' => (string)$selLeg,
  'id_periodo'     => (string)$selPer,
  'id_ejercicio'   => (string)$selEj,
  'tipo_sesion_ro' => $tipoSesionNombre,
  'sesion_ro'      => $sesionNombre,
];
?>

<style>
/* ── Text sections ── */
.txt-section { background:#fff; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; }
.txt-section-header {
    background: linear-gradient(90deg, #1e3a5f 0%, #1d4ed8 100%);
    color:#fff; padding:.7rem 1.1rem;
    display:flex; align-items:center; justify-content:space-between;
    font-weight:600; font-size:.92rem; letter-spacing:.02em;
    font-family:'Lato',sans-serif;
}
.txt-result-header  { background: linear-gradient(90deg, #065f46 0%, #059669 100%); }
.txt-stat-badge { background:rgba(255,255,255,.18); color:#fff; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:500; }
.txt-textarea {
    display:block; width:100%; border:none; border-top:1px solid #e5e7eb;
    padding:1rem 1.1rem; font-family:'Courier New',Courier,monospace;
    font-size:.82rem; line-height:1.7; background:#fafafa; resize:vertical; color:#1f2937;
}
.txt-textarea:focus { outline:none; background:#fff; box-shadow:inset 0 0 0 2px rgba(29,78,216,.12); }
.txt-textarea-result { background:#f0fdf4; }
.txt-textarea-result:focus { box-shadow:inset 0 0 0 2px rgba(5,150,105,.12); }
.txt-section-actions {
    display:flex; align-items:center; gap:1rem;
    padding:.8rem 1.1rem; border-top:1px solid #e5e7eb; background:#f9fafb;
}

/* ── Action buttons ── */
.btn-corregir {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color:#fff; border:none; padding:.7rem 2.2rem;
    border-radius:9px; font-weight:700; font-size:1rem;
    box-shadow:0 4px 14px rgba(245,158,11,.4); cursor:pointer;
    transition:opacity .15s,transform .1s,box-shadow .15s;
    font-family:'Lato',sans-serif;
}
.btn-corregir:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
.btn-corregir:disabled { opacity:.4; cursor:not-allowed; }

.btn-actualizar {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color:#fff; border:none; padding:.5rem 1.2rem;
    border-radius:7px; font-weight:600; font-size:.85rem;
    box-shadow:0 2px 8px rgba(37,99,235,.3); cursor:pointer;
    transition:opacity .15s; font-family:'Lato',sans-serif;
}
.btn-actualizar:hover:not(:disabled) { opacity:.88; }
.btn-actualizar:disabled { opacity:.4; cursor:not-allowed; }

.btn-datos-acta {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color:#fff; border:none; padding:.5rem 1.2rem;
    border-radius:8px; font-weight:600; font-size:.88rem;
    box-shadow:0 2px 8px rgba(99,102,241,.35); cursor:pointer;
    transition:opacity .15s; font-family:'Lato',sans-serif;
}
.btn-datos-acta:hover:not(:disabled) { opacity:.88; }
.btn-datos-acta:disabled { opacity:.4; cursor:not-allowed; }

.btn-word-dl {
    background: linear-gradient(135deg, #1f2937, #374151);
    color:#fff; border:none; padding:.5rem 1.2rem;
    border-radius:8px; font-weight:600; font-size:.88rem;
    text-decoration:none; display:inline-flex; align-items:center;
    box-shadow:0 2px 8px rgba(0,0,0,.3); transition:opacity .15s;
    font-family:'Lato',sans-serif;
}
.btn-word-dl:hover { opacity:.85; color:#fff; }
.btn-word-dl.disabled { opacity:.35; pointer-events:none; color:#fff; }

/* ── Bottom bar ── */
.bottom-action-bar {
    display:flex; align-items:center; gap:1rem;
    padding:.9rem 1.4rem; background:#f1f5f9;
    border-top:2px solid #e2e8f0; border-radius:0 0 14px 14px; margin-top:1rem;
}
.btn-edicion {
    display:inline-flex; align-items:center; gap:.4rem;
    background:#fff; color:#374151; border:2px solid #d1d5db;
    padding:.48rem 1.1rem; border-radius:7px;
    font-weight:600; font-size:.85rem; text-decoration:none;
    transition:border-color .15s,background .15s,color .15s;
    font-family:'Lato',sans-serif;
}
.btn-edicion:hover { border-color:#6b7280; background:#f9fafb; color:#111827; }

.meta-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin-bottom:.3rem; display:block; }

/* ── Lato override for modal inputs & encabezado textareas ── */
.modal .form-control,
.modal .form-select,
#panelEncabezadoAI .txt-textarea {
    font-family: 'Lato', sans-serif !important;
    font-size: .9rem;
    line-height: 1.6;
}
</style>

<div class="table-container card-style mb-4">
  <div class="card-header-title">Generar Acta</div>

  <div class="card-body">

    <!-- ORIGEN -->
    <div class="txt-section mb-4">
      <div class="txt-section-header">
        <span>📝 Transcripción taquigráfica de origen</span>
        <div class="d-flex gap-2 align-items-center">
          <span class="txt-stat-badge">ID: <?= (int)$idTrans ?></span>
          <span class="txt-stat-badge"><strong id="charsOrigen"><?= mb_strlen($textoFuente) ?></strong>&nbsp;caracteres</span>
        </div>
      </div>
      <textarea id="textoFuente" class="txt-textarea" rows="10"><?= htmlspecialchars($textoFuente) ?></textarea>
    </div>

    <!-- Debug toggle (solo admins) -->
    <?php if ((int)($_SESSION['auth']['iTipo'] ?? 0) === 1): ?>
    <div class="form-check mb-2 ms-1">
      <input class="form-check-input" type="checkbox" id="chkDebug">
      <label class="form-check-label small text-muted" for="chkDebug">
        Modo prueba (solo chunking, sin OpenAI)
      </label>
    </div>
    <?php endif; ?>

    <!-- GENERAR -->
    <div class="d-flex justify-content-center my-4" id="panelTopInicial">
      <button id="btnGenerarActa" class="btn-corregir">
        <?= $tieneActa ? '🔄 Regenerar Acta' : '📝 Generar Acta' ?>
      </button>
    </div>

    <!-- DEBUG CHUNKING -->
    <div id="panelDebug" class="txt-section mb-4" style="display:none;">
      <div class="txt-section-header" style="background:linear-gradient(90deg,#1f2937,#374151);">
        <span>🧪 Resultado de prueba (chunking)</span>
      </div>
      <div class="p-3">
        <div class="small text-muted mb-2" id="debugInfo"></div>
        <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
          <button type="button" id="btnPrevChunk" class="btn btn-outline-secondary btn-sm">⬅ Anterior</button>
          <select id="debugSelect" class="form-select form-select-sm" style="max-width:220px;"></select>
          <button type="button" id="btnNextChunk" class="btn btn-outline-secondary btn-sm">Siguiente ➡</button>
          <span class="small text-muted" id="debugLen"></span>
          <button type="button" id="btnCopyChunk" class="btn btn-outline-primary btn-sm ms-auto">📋 Copiar</button>
        </div>
        <textarea id="debugOutput" class="txt-textarea" rows="15" readonly></textarea>
      </div>
    </div>

    <!-- PANEL ACTA -->
    <div id="panelActa" style="display: <?= $tieneActa ? 'block' : 'none' ?>;">

      <!-- ACTA RESULT -->
      <div class="txt-section mb-3">
        <div class="txt-section-header txt-result-header">
          <span>✅ Acta generada</span>
          <div class="d-flex gap-2 align-items-center">
            <span class="txt-stat-badge"><strong id="charsActa"><?= (int)$charsActa ?></strong>&nbsp;caracteres</span>
            <span class="txt-stat-badge">Δ&nbsp;<strong id="diffActa"><?php if ($tieneActa): ?><?= round((($charsActa - mb_strlen($textoFuente)) / max(1, mb_strlen($textoFuente))) * 100, 2) ?>%<?php else: ?>0%<?php endif; ?></strong></span>
          </div>
        </div>
        <textarea id="textoActa" class="txt-textarea txt-textarea-result" rows="14"><?= htmlspecialchars($textoActa) ?></textarea>
      </div>

      <!-- BOTONES DE ACCIÓN -->
      <div class="d-flex gap-2 mb-4 flex-wrap align-items-center" id="panelBotonesTop">
        <button id="btnMeta" class="btn-datos-acta" <?= $tieneActa ? '' : 'disabled' ?>>
          🧾 Datos del Acta
        </button>
        <button id="btnToggleEncabezado" class="btn-actualizar" style="display:none;" <?= $tieneActa ? '' : 'disabled' ?>>
          🤖 Generar Encabezado
        </button>
        <a id="btnWord" class="btn-word-dl disabled" style="display:none;"
           href="index.php?ruta=actanueva/descargarWord&acta_id=<?= (int)$idActa ?>"
           target="_blank" aria-disabled="true">
          🧾 Descargar Word
        </a>
        <button id="btnGenerarSintesis" class="btn-corregir" style="display:none;font-size:.85rem;padding:.5rem 1.2rem;" <?= $tieneActa ? '' : 'disabled' ?>>
          📄 Generar Síntesis
        </button>
        <?php if ((int)($_SESSION['auth']['iTipo'] ?? 0) === 1): ?>
        <button id="btnDebugSintesisChunks" class="btn btn-outline-secondary btn-sm" style="display:none;" <?= $tieneActa ? '' : 'disabled' ?>>
          🧪 Debug Chunks
        </button>
        <?php endif; ?>
      </div>

      <!-- ENCABEZADO -->
      <div id="panelEncabezadoAI" class="txt-section mb-3" style="display:none;">
        <div class="txt-section-header" style="background:linear-gradient(90deg,#2d3748,#4a5568);">
          <span>🤖 Encabezado y primer párrafo</span>
          <button type="button" id="btnHideEncabezado" class="btn-edicion"
                  style="font-size:.73rem;padding:.28rem .7rem;">Ocultar</button>
        </div>
        <div class="p-3">
          <p class="small text-muted mb-3">Guardado en <strong>acta_metadata</strong> (encabezado_ai / primer_parrafo_ai).</p>
          <label class="meta-label">Encabezado</label>
          <textarea id="txtEncabezadoAI" class="txt-textarea mb-3" rows="8" readonly></textarea>
          <label class="meta-label">Primer párrafo</label>
          <textarea id="txtPrimerParrafoAI" class="txt-textarea mb-3" rows="6" readonly></textarea>
          <div class="d-flex justify-content-end">
            <button id="btnRegenerarEncabezado" class="btn-actualizar" <?= $tieneActa ? '' : 'disabled' ?>>
              🔄 Regenerar Encabezado
            </button>
          </div>
        </div>
      </div>

      <!-- SÍNTESIS -->
      <div id="panelSintesis" style="display: <?= ($tieneActa && trim($textoSintesis) !== '') ? 'block' : 'none' ?>;">
        <div class="txt-section mb-3">
          <div class="txt-section-header" style="background:linear-gradient(90deg,#0e7490,#0891b2);">
            <span>📄 Síntesis del acta</span>
            <span class="txt-stat-badge"><strong id="charsSintesis"><?= (int)$charsSintesis ?></strong>&nbsp;caracteres</span>
          </div>
          <textarea id="textoSintesis" class="txt-textarea" rows="10"><?= htmlspecialchars($textoSintesis) ?></textarea>
          <div class="txt-section-actions" id="panelSintesisActions"
               style="display: <?= ($tieneActa && trim($textoSintesis) !== '') ? 'flex' : 'none' ?>;">
            <button id="btnRegenerarSintesis" class="btn-corregir" style="font-size:.85rem;padding:.5rem 1.2rem;" <?= $tieneActa ? '' : 'disabled' ?>>
              🔄 Regenerar Síntesis
            </button>
            <?php if ((int)($_SESSION['auth']['iTipo'] ?? 0) === 1): ?>
            <button id="btnDebugSintesisChunks2" class="btn btn-outline-secondary btn-sm" <?= $tieneActa ? '' : 'disabled' ?>>
              🧪 Debug Chunks
            </button>
            <?php endif; ?>
            <a id="btnWordSintesis" class="btn-word-dl ms-auto"
               href="index.php?ruta=actanueva/descargarWordSintesis&acta_id=<?= (int)$idActa ?>"
               target="_blank">
              🧾 Word Síntesis
            </a>
          </div>
        </div>
      </div>

      <!-- DEBUG SÍNTESIS -->
      <div id="panelDebugSintesis" class="txt-section mb-3" style="display:none;">
        <div class="txt-section-header" style="background:linear-gradient(90deg,#1f2937,#374151);">
          <span>🧪 Debug: Chunks para Síntesis</span>
        </div>
        <div class="p-3">
          <div class="small text-muted mb-2" id="debugSintesisInfo"></div>
          <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
            <button type="button" id="btnPrevChunkS" class="btn btn-outline-secondary btn-sm">⬅ Anterior</button>
            <select id="debugSelectS" class="form-select form-select-sm" style="max-width:220px;"></select>
            <button type="button" id="btnNextChunkS" class="btn btn-outline-secondary btn-sm">Siguiente ➡</button>
            <span class="small text-muted" id="debugLenS"></span>
            <button type="button" id="btnCopyChunkS" class="btn btn-outline-primary btn-sm ms-auto">📋 Copiar</button>
          </div>
          <textarea id="debugOutputS" class="txt-textarea" rows="15" readonly></textarea>
        </div>
      </div>

      <div class="alert alert-success border-0 mb-2" id="msgActa" style="display:none;"></div>

    </div><!-- /panelActa -->

  </div>

  <!-- BARRA INFERIOR -->
  <div class="bottom-action-bar">
    <a href="index.php?ruta=transcripcion/editar&id=<?= (int)$idTrans ?>" class="btn-edicion">
      ⬅ Regresar a edición manual
    </a>
  </div>

</div>

<!-- MODAL METADATA -->
<div class="modal fade" id="modalMeta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content modal-alcey">

      <div class="modal-header modal-alcey-header">
        <div class="modal-alcey-icon">📋</div>
        <h5 class="modal-title">Datos del Acta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body modal-alcey-body">

        <!-- Sección: Identificación -->
        <div class="modal-section">
          <div class="modal-section-title">Identificación</div>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label modal-label">Clave del acta</label>
              <input type="text" class="form-control modal-input" id="m_clave_acta"
                     placeholder="Acta 10/2o.A/1er.P.Ord./2025/LXIV">
            </div>
            <div class="col-md-4">
              <label class="form-label modal-label">Tipo de sesión</label>
              <div class="form-control modal-input modal-readonly bg-light" id="m_tipo_sesion_ro"
                   style="color:#495057;">
                <?= htmlspecialchars($tipoSesionNombre ?: '—') ?>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label modal-label">Sesión</label>
              <div class="form-control modal-input modal-readonly bg-light" id="m_sesion_ro"
                   style="color:#495057;">
                <?= htmlspecialchars($sesionNombre ?: '—') ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Sección: Legislatura -->
        <div class="modal-section">
          <div class="modal-section-title">Legislatura</div>
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label modal-label">Legislatura</label>
              <select class="form-select modal-input" id="m_legislatura">
                <option value="">— Selecciona —</option>
                <?php foreach ($legislaturas as $leg): ?>
                <option value="<?= (int)$leg['id'] ?>"
                  <?= ((string)$selLeg === (string)$leg['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($leg['clave'] . ' — ' . $leg['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label modal-label">Periodo</label>
              <select class="form-select modal-input" id="m_periodo">
                <option value="">— Selecciona —</option>
                <?php foreach ($cat_periodo as $p): ?>
                <option value="<?= (int)$p['id'] ?>"
                  <?= ((string)$selPer === (string)$p['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label modal-label">Ejercicio constitucional</label>
              <select class="form-select modal-input" id="m_ejercicio">
                <option value="">— Selecciona —</option>
                <?php foreach ($cat_ejercicio as $e): ?>
                <option value="<?= (int)$e['id'] ?>"
                  <?= ((string)$selEj === (string)$e['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($e['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Sección: Fecha y lugar -->
        <div class="modal-section">
          <div class="modal-section-title">Fecha y lugar</div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label modal-label">Fecha</label>
              <input type="date" class="form-control modal-input" id="m_fecha">
            </div>
            <div class="col-md-4">
              <label class="form-label modal-label">Hora inicio</label>
              <input type="time" class="form-control modal-input" id="m_hora_inicio">
            </div>
            <div class="col-md-4">
              <label class="form-label modal-label">Ciudad</label>
              <input type="text" class="form-control modal-input" id="m_ciudad" placeholder="Mérida">
            </div>
            <div class="col-12">
              <label class="form-label modal-label">Recinto</label>
              <input type="text" class="form-control modal-input" id="m_recinto"
                     placeholder="Salón de Sesiones Constituyentes 1918">
            </div>
          </div>
        </div>

        <!-- Sección: Mesa directiva -->
        <div class="modal-section mb-0">
          <div class="modal-section-title">Mesa directiva</div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label modal-label">Presidente</label>
              <select class="form-select modal-input" id="m_presidente" required>
                <option value="">— Seleccione presidente —</option>
                <?php foreach ($diputados as $d):
                  $uid = (int)$d['iIdUsuario'];
                  $sel = ((string)$selPres === (string)$uid) ? 'selected' : '';
                ?>
                  <option value="<?= $uid ?>" <?= $sel ?>><?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label modal-label">Secretaria 1</label>
              <select class="form-select modal-input" id="m_secretaria_1" required>
                <option value="">— Seleccione secretaria —</option>
                <?php foreach ($diputados as $d):
                  $uid = (int)$d['iIdUsuario'];
                  $sel = ((string)$selS1 === (string)$uid) ? 'selected' : '';
                ?>
                  <option value="<?= $uid ?>" <?= $sel ?>><?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label modal-label">Secretaria 2</label>
              <select class="form-select modal-input" id="m_secretaria_2">
                <option value="">— Seleccione secretaria —</option>
                <?php foreach ($diputados as $d):
                  $uid = (int)$d['iIdUsuario'];
                  $sel = ((string)$selS2 === (string)$uid) ? 'selected' : '';
                ?>
                  <option value="<?= $uid ?>" <?= $sel ?>><?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="alert alert-info mt-3 mb-0 small" id="metaMsg" style="display:none;"></div>
      </div>

      <div class="modal-footer modal-alcey-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary fw-semibold px-4" id="btnGuardarMeta">Guardar</button>
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

<script src="public/js/acta_nueva/iniciar.js?v=<?= time() ?>"></script>
