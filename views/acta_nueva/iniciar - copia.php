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
/** GLOBAL */
let actaId = <?= $tieneActa ? (int)$idActa : 'null' ?>;

// ✅ NUEVO: defaults del modal (mesa directiva) desde PHP
const META_DEFAULTS = <?= json_encode($metaDefaults, JSON_UNESCAPED_UNICODE) ?>;

// Cache local de estados (para no depender 100% del fetch)
let UI_META_OK_OVERRIDE = null;  // true/false/null
let UI_HAS_ENC_OVERRIDE = null;  // true/false/null

document.addEventListener("DOMContentLoaded", function() {

  // =========================
  // ELEMENTOS PRINCIPALES
  // =========================
  const txtFuente      = document.getElementById('textoFuente');
  const charsOrigen    = document.getElementById('charsOrigen');
  const btnActa        = document.getElementById('btnGenerarActa');

  const panelActa      = document.getElementById('panelActa');
  const txtActa        = document.getElementById('textoActa');
  const charsActaSpan  = document.getElementById('charsActa');
  const diffActaSpan   = document.getElementById('diffActa');
  const msgActa        = document.getElementById('msgActa');

  const chkDebug       = document.getElementById('chkDebug');

  // Debug chunks ACTA
  const panelDebug     = document.getElementById('panelDebug');
  const debugInfo      = document.getElementById('debugInfo');
  const debugOutput    = document.getElementById('debugOutput');
  const debugSelect    = document.getElementById('debugSelect');
  const debugLen       = document.getElementById('debugLen');
  const btnPrevChunk   = document.getElementById('btnPrevChunk');
  const btnNextChunk   = document.getElementById('btnNextChunk');
  const btnCopyChunk   = document.getElementById('btnCopyChunk');

  const correccionId     = <?= (int)$idCorreccion ?>;
  const transcripcionId  = <?= (int)$idTrans ?>;

  // Botones TOP
  const btnMeta        = document.getElementById('btnMeta');
  const btnToggleEnc   = document.getElementById('btnToggleEncabezado');
  const btnWord        = document.getElementById('btnWord');
  const btnSintesisTop = document.getElementById('btnGenerarSintesis');
  const btnDebugSintesisTop = document.getElementById('btnDebugSintesisChunks');

  // Modal metadata
  const modalMetaEl    = document.getElementById('modalMeta');
  const metaMsg        = document.getElementById('metaMsg');
  const btnGuardarMeta = document.getElementById('btnGuardarMeta');
  const modalMeta = (modalMetaEl && typeof bootstrap !== "undefined")
    ? new bootstrap.Modal(modalMetaEl)
    : null;

  // Encabezado panel
  const panelEncAI     = document.getElementById('panelEncabezadoAI');
  const btnHideEnc     = document.getElementById('btnHideEncabezado');
  const btnRegenEnc    = document.getElementById('btnRegenerarEncabezado');
  const txtEncAI       = document.getElementById('txtEncabezadoAI');
  const txtParAI       = document.getElementById('txtPrimerParrafoAI');

  // Síntesis panel
  const panelSintesis  = document.getElementById('panelSintesis');
  const txtSintesis    = document.getElementById('textoSintesis');
  const charsSintesis  = document.getElementById('charsSintesis');

  // Acciones síntesis (dentro del panel)
  const panelSintesisActions = document.getElementById('panelSintesisActions');
  const btnRegenSintesis = document.getElementById('btnRegenerarSintesis');
  const btnDebugSintesisBottom = document.getElementById('btnDebugSintesisChunks2');
  const btnWordSintesis = document.getElementById('btnWordSintesis');

  // Debug chunks SÍNTESIS
  const panelDebugS  = document.getElementById('panelDebugSintesis');
  const debugInfoS   = document.getElementById('debugSintesisInfo');
  const debugSelectS = document.getElementById('debugSelectS');
  const debugLenS    = document.getElementById('debugLenS');
  const debugOutputS = document.getElementById('debugOutputS');
  const btnPrevS     = document.getElementById('btnPrevChunkS');
  const btnNextS     = document.getElementById('btnNextChunkS');
  const btnCopyS     = document.getElementById('btnCopyChunkS');

  // =========================
  // HELPERS
  // =========================
  if (txtFuente && charsOrigen) {
    txtFuente.addEventListener('input', () => {
      charsOrigen.textContent = txtFuente.value.length;
    });
  }

  function setMetaMsg(txt, ok=true){
    if (!metaMsg) return;
    metaMsg.style.display = 'block';
    metaMsg.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger') + ' mt-3 mb-0 small';
    metaMsg.textContent = txt;
  }

  async function safeFetchJson(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      return { ok: true, status: res.status, json, raw: text };
    } catch (e) {
      return { ok: false, status: res.status, json: null, raw: text };
    }
  }

  function showEncPanel(show){
    if (!panelEncAI) return;
    panelEncAI.style.display = show ? "block" : "none";
  }

  function setEncButtonLabel(hasEnc){
    if (!btnToggleEnc) return;
    btnToggleEnc.textContent = hasEnc ? "👁 Ver Encabezado" : "🤖 Generar Encabezado";
  }

  function setDisplay(el, show){
    if (!el) return;
    el.style.display = show ? "" : "none";
  }

  function setEnabled(el, enabled){
    if (!el) return;
    el.disabled = !enabled;
  }

  function setLinkEnabled(a, enabled){
    if (!a) return;
    a.classList.toggle('disabled', !enabled);
    a.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    if (!enabled) a.setAttribute('tabindex','-1');
    else a.removeAttribute('tabindex');
  }

  function hasText(v){
    return (v || '').toString().trim() !== '';
  }

  function pick(d, keys){
    for (const k of keys) {
      const v = d?.[k];
      if (hasText(v)) return v;
    }
    return '';
  }

  function updateWordLinks(){
    if (actaId) {
      if (btnWord) btnWord.href = `index.php?ruta=actanueva/descargarWord&acta_id=${actaId}`;
      if (btnWordSintesis) btnWordSintesis.href = `index.php?ruta=actanueva/descargarWordSintesis&acta_id=${actaId}`;
    }
  }

  // ============ UI por estados ============
  function applyUIState({ hasActa, metaOK, hasEnc, hasSintesis }) {
    // Panel acta
    setDisplay(panelActa, !!hasActa);

    // 1) Datos del acta
    setDisplay(btnMeta, !!hasActa);
    setEnabled(btnMeta, !!hasActa);

    // 2) Encabezado (solo con metadata OK)
    setDisplay(btnToggleEnc, !!hasActa && !!metaOK);
    setEnabled(btnToggleEnc, !!hasActa && !!metaOK);
    setEncButtonLabel(!!hasEnc);

    // 3) Word acta (solo con encabezado)
    setDisplay(btnWord, !!hasActa && !!hasEnc);
    setLinkEnabled(btnWord, !!hasActa && !!hasEnc);

    // 4) Síntesis top (solo con encabezado y si no existe síntesis)
    const showSintTop = !!hasActa && !!hasEnc && !hasSintesis;
    setDisplay(btnSintesisTop, showSintTop);
    setEnabled(btnSintesisTop, showSintTop);

    setDisplay(btnDebugSintesisTop, showSintTop);
    setEnabled(btnDebugSintesisTop, showSintTop);

    // Panel síntesis y acciones
    setDisplay(panelSintesis, !!hasActa && !!hasSintesis);
    setDisplay(panelSintesisActions, !!hasActa && !!hasSintesis);
    setEnabled(btnRegenSintesis, !!hasActa && !!hasSintesis);
    setEnabled(btnDebugSintesisBottom, !!hasActa && !!hasSintesis);
    setLinkEnabled(btnWordSintesis, !!hasActa && !!hasSintesis);

    // Encabezado panel NO auto-mostrar
  }

  // ============ Determinar estado desde BD ============
  async function evaluateAndApplyState(){
    const hasActa = !!actaId;

    let metaOK = false;
    let hasEnc = false;

    // base: síntesis por texto
    const hasSintesis = hasText(txtSintesis?.value);

    if (hasActa) {
      const resp = await safeFetchJson(`index.php?ruta=actanueva/obtenerMetadata&acta_id=${actaId}`);
      const d = (resp.ok && resp.json && resp.json.data) ? resp.json.data : null;

      if (d) {
        // Encabezado (acepta variantes por si cambiaste nombres en backend)
        const enc = pick(d, ['encabezado_ai','encabezadoAI','cEncabezadoAI','encabezado']);
        const par = pick(d, ['primer_parrafo_ai','primerParrafoAI','cPrimerParrafoAI','primer_parrafo']);
        hasEnc = hasText(enc) || hasText(par);

        // Precargar textareas
        if (txtEncAI) txtEncAI.value = (enc || '').trim();
        if (txtParAI) txtParAI.value = (par || '').trim();

        // Metadata completo (acepta variantes)
        const req = [
          ['clave_acta','clave','cClaveActa'],
          ['tipo_sesion','tipo','cTipoSesion'],
          ['legislatura','cLegislatura'],
          ['periodo','cPeriodo'],
          ['ejercicio','cEjercicio'],
          ['fecha','dFecha'],
          ['hora_inicio','horaInicio','cHoraInicio'],   // lo hacemos requerido
          ['ciudad','cCiudad'],                        // requerido
          ['recinto','cRecinto'],
          ['presidente','iIdPresidente'],
          ['secretaria_1','secretaria1','iIdSecretaria1'],
        ];

        metaOK = req.every(keys => hasText(pick(d, keys)));
      }
    }

    // Overrides (para que no “desaparezca” el botón por mismatch de keys)
    if (UI_META_OK_OVERRIDE !== null) metaOK = UI_META_OK_OVERRIDE;
    if (UI_HAS_ENC_OVERRIDE !== null) hasEnc = UI_HAS_ENC_OVERRIDE;

    updateWordLinks();
    applyUIState({ hasActa, metaOK, hasEnc, hasSintesis });

    return { hasActa, metaOK, hasEnc, hasSintesis };
  }

  // ============ ESTADO INICIAL ============
  evaluateAndApplyState();

  // =========================
  // BLOQUEO DE DUPLICADOS (selects)
  // =========================
  function enforceUniqueDiputados() {
    const selPres = document.getElementById('m_presidente');
    const selS1   = document.getElementById('m_secretaria_1');
    const selS2   = document.getElementById('m_secretaria_2');
    if (!selPres || !selS1 || !selS2) return;

    const selects = [selPres, selS1, selS2];
    const chosen = new Set(selects.map(s => s.value).filter(v => v));

    selects.forEach(sel => {
      Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        const isChosenSomewhere = chosen.has(opt.value);
        const isChosenHere = sel.value === opt.value;
        opt.disabled = isChosenSomewhere && !isChosenHere;
      });
    });
  }

  document.addEventListener('change', (e) => {
    if (['m_presidente','m_secretaria_1','m_secretaria_2'].includes(e.target.id)) {
      enforceUniqueDiputados();
    }
  });

  // =========================
  // DEBUG CHUNKS SÍNTESIS (sin OpenAI)
  // =========================
  function renderDebugSintesis(data) {
    const chunks = data?.chunks || [];
    if (!panelDebugS || !debugSelectS || !debugOutputS) return;

    panelDebugS.style.display = "block";

    if (debugInfoS) {
      debugInfoS.textContent =
        `Total: ${data.chars_total} chars · Chunks: ${data.total_chunks} · target=${data.target} · window=${data.window} · overlap=${data.overlap}`;
    }

    debugSelectS.innerHTML = "";
    chunks.forEach((ch, idx) => {
      const opt = document.createElement('option');
      opt.value = idx;
      opt.textContent = `Chunk ${ch.n} (${ch.len})`;
      debugSelectS.appendChild(opt);
    });

    function show(i) {
      i = Math.max(0, Math.min(i, chunks.length - 1));
      debugSelectS.value = String(i);
      const ch = chunks[i];

      if (debugLenS) debugLenS.textContent = `Longitud: ${ch.len} caracteres`;
      debugOutputS.value = ch.texto || "";

      if (btnPrevS) btnPrevS.disabled = (i === 0);
      if (btnNextS) btnNextS.disabled = (i === chunks.length - 1);
    }

    debugSelectS.onchange = () => show(parseInt(debugSelectS.value, 10));
    if (btnPrevS) btnPrevS.onclick = () => show(parseInt(debugSelectS.value, 10) - 1);
    if (btnNextS) btnNextS.onclick = () => show(parseInt(debugSelectS.value, 10) + 1);

    if (btnCopyS) {
      btnCopyS.onclick = async () => {
        try {
          await navigator.clipboard.writeText(debugOutputS.value);
          const old = btnCopyS.textContent;
          btnCopyS.textContent = "✅ Copiado";
          setTimeout(() => btnCopyS.textContent = old, 1200);
        } catch (e) {
          alert("No se pudo copiar. Revisa permisos del navegador.");
        }
      };
    }

    show(0);
  }

  async function runDebugSintesisChunks(){
    if (!actaId) return alert("Primero genera el acta.");

    const target  = 10000;
    const window  = 600;
    const overlap = 0;

    const url = `index.php?ruta=actanueva/debugSintesisChunks&acta_id=${actaId}&target=${target}&window=${window}&overlap=${overlap}`;
    const resp = await safeFetchJson(url);

    if (!resp.ok) {
      console.error("debugSintesisChunks NO devolvió JSON:", resp.raw);
      alert("El servidor no devolvió JSON. Revisa consola (F12).");
      return;
    }

    const j = resp.json;
    if (!j.ok) {
      alert(j.error || "Error en debug.");
      return;
    }

    renderDebugSintesis(j);
  }

  btnDebugSintesisTop?.addEventListener('click', runDebugSintesisChunks);
  btnDebugSintesisBottom?.addEventListener('click', runDebugSintesisChunks);

  // =========================
  // DEBUG CHUNKS ACTA UI
  // =========================
  function renderDebugChunks(data) {
    const chunks = data.chunks || [];
    if (!panelDebug) return;

    panelDebug.style.display = "block";
    if (debugInfo) debugInfo.textContent = `Segmentos detectados: ${data.segmentos} · Chunks generados: ${data.total_chunks}`;

    if (debugSelect) debugSelect.innerHTML = "";

    chunks.forEach((ch, idx) => {
      const opt = document.createElement('option');
      opt.value = idx;
      opt.textContent = `Chunk ${ch.n}`;
      debugSelect.appendChild(opt);
    });

    function mostrarChunk(idx) {
      idx = Math.max(0, Math.min(idx, chunks.length - 1));
      debugSelect.value = String(idx);
      const ch = chunks[idx];
      if (!ch) return;

      if (debugLen) debugLen.textContent = `Longitud: ${ch.len} caracteres`;
      if (debugOutput) debugOutput.value = ch.texto || "";

      if (btnPrevChunk) btnPrevChunk.disabled = idx === 0;
      if (btnNextChunk) btnNextChunk.disabled = idx === chunks.length - 1;
    }

    if (debugSelect) debugSelect.onchange = () => mostrarChunk(parseInt(debugSelect.value, 10));
    if (btnPrevChunk) btnPrevChunk.onclick = () => mostrarChunk(parseInt(debugSelect.value, 10) - 1);
    if (btnNextChunk) btnNextChunk.onclick = () => mostrarChunk(parseInt(debugSelect.value, 10) + 1);

    if (btnCopyChunk) {
      btnCopyChunk.onclick = async () => {
        try {
          await navigator.clipboard.writeText(debugOutput.value);
          const original = btnCopyChunk.textContent;
          btnCopyChunk.textContent = "✅ Copiado";
          setTimeout(() => btnCopyChunk.textContent = original, 1200);
        } catch (e) {
          alert("No se pudo copiar. Revisa permisos del navegador.");
        }
      };
    }

    mostrarChunk(0);
  }

  // =========================
  // GENERAR ACTA
  // =========================
  btnActa?.addEventListener('click', async () => {
    const texto = (txtFuente?.value || '').trim();
    if (!texto) return alert("No hay texto taquigráfico para generar el acta.");

    // reset debug
    if (panelDebug) panelDebug.style.display = "none";
    if (debugInfo) debugInfo.textContent = "";
    if (debugOutput) debugOutput.value = "";
    if (debugSelect) debugSelect.innerHTML = "";
    if (debugLen) debugLen.textContent = "";

    // Si regeneras acta: resetea estado forzado
    UI_META_OK_OVERRIDE = null;
    UI_HAS_ENC_OVERRIDE = null;
    showEncPanel(false);

    const body = new URLSearchParams();
    body.append("transcripcion_id", transcripcionId);
    body.append("correccion_id", correccionId);
    body.append("texto_fuente", texto);
    body.append("acta_id", actaId ?? "");
    if (chkDebug?.checked) body.append("modo", "debug");

    btnActa.disabled = true;
    btnActa.textContent = chkDebug?.checked ? "Probando chunks…" : "Procesando acta…";

    try {
      const r = await fetch("index.php?ruta=actanueva/generarActa", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body: body.toString()
      });
      const data = await r.json();

      if (data.error) return alert("Error: " + data.error);

      if (data.modo === "debug") {
        renderDebugChunks(data);
        return;
      }

      actaId = data.id_acta;
      updateWordLinks();

      if (txtActa) txtActa.value = data.texto_acta;
      if (charsActaSpan) charsActaSpan.textContent = data.chars_acta;
      if (diffActaSpan) diffActaSpan.textContent  = data.diferencia_pct + "%";

      if (msgActa) {
        msgActa.style.display = "block";
        msgActa.textContent = "Acta guardada correctamente (ID: " + actaId + ")";
      }

      await evaluateAndApplyState();

    } catch (err) {
      console.error(err);
      alert("Hubo un error en la solicitud.");
    } finally {
      btnActa.disabled = false;
      btnActa.textContent = actaId ? "🔄 Regenerar Acta" : "📝 Generar Acta";
    }
  });

  // =========================
  // ENCABEZADO: toggle (Ver/Generar)
  // =========================
  async function generarEncabezadoAI() {
    if (!actaId) return alert("Primero genera el acta.");

    setEnabled(btnToggleEnc, false);
    setEnabled(btnRegenEnc, false);
    const oldTop = btnToggleEnc.textContent;
    btnToggleEnc.textContent = "Generando…";

    try {
      const body = new URLSearchParams();
      body.append("acta_id", actaId);

      const resp = await safeFetchJson("index.php?ruta=actanueva/generarEncabezadoAI", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body: body.toString()
      });

      if (!resp.ok) {
        console.error("generarEncabezadoAI NO devolvió JSON:", resp.raw);
        alert("El servidor no devolvió JSON. Revisa consola (F12).");
        return;
      }

      const j = resp.json;
      if (!j.ok) {
        alert(j.error || "No se pudo generar el encabezado.");
        return;
      }

      if (txtEncAI) txtEncAI.value = j.encabezado_ai || "";
      if (txtParAI) txtParAI.value = j.primer_parrafo_ai || "";

      // Forzar estado
      UI_HAS_ENC_OVERRIDE = true;

      showEncPanel(true);

      if (msgActa) {
        msgActa.style.display = "block";
        msgActa.textContent = "Encabezado generado y guardado correctamente.";
      }

      await evaluateAndApplyState();

    } finally {
      setEnabled(btnToggleEnc, true);
      setEnabled(btnRegenEnc, true);
      const hasNow = hasText(txtEncAI?.value) || hasText(txtParAI?.value);
      btnToggleEnc.textContent = hasNow ? "👁 Ver Encabezado" : oldTop;
    }
  }

  btnToggleEnc?.addEventListener('click', async () => {
    if (!actaId) return alert("Primero genera el acta.");

    const visible = panelEncAI && panelEncAI.style.display !== "none";
    if (visible) {
      showEncPanel(false);
      return;
    }

    const hasEncNow = hasText(txtEncAI?.value) || hasText(txtParAI?.value);
    if (hasEncNow) showEncPanel(true);
    else await generarEncabezadoAI();
  });

  btnHideEnc?.addEventListener('click', () => showEncPanel(false));
  btnRegenEnc?.addEventListener('click', generarEncabezadoAI);

  // =========================
  // SÍNTESIS (generar/regenerar)
  // =========================
  async function generarSintesis() {
    if (!actaId) return alert("Primero genere el acta.");

    const body = new URLSearchParams();
    body.append("acta_id", actaId);

    if (btnSintesisTop) btnSintesisTop.disabled = true;
    if (btnRegenSintesis) btnRegenSintesis.disabled = true;

    const original = btnSintesisTop ? btnSintesisTop.textContent : "";
    if (btnSintesisTop) btnSintesisTop.textContent = "Procesando síntesis…";

    try {
      const r = await fetch("index.php?ruta=actanueva/generarSintesis", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body: body.toString()
      });

      const data = await r.json();
      if (data.error) return alert("Error: " + data.error);

      if (txtSintesis) txtSintesis.value = data.texto_sintesis;
      if (charsSintesis) charsSintesis.textContent = data.chars_sintesis;

      updateWordLinks();

      if (msgActa) {
        msgActa.style.display = "block";
        msgActa.textContent = "Síntesis generada correctamente.";
      }

      await evaluateAndApplyState();

    } finally {
      if (btnSintesisTop) {
        btnSintesisTop.disabled = false;
        btnSintesisTop.textContent = original;
      }
      if (btnRegenSintesis) btnRegenSintesis.disabled = false;
    }
  }

  btnSintesisTop?.addEventListener("click", generarSintesis);
  btnRegenSintesis?.addEventListener("click", generarSintesis);

  // =========================
  // ABRIR MODAL METADATA
  // =========================
  function fillMetaForm(d){
    document.getElementById('m_clave_acta').value        = d?.clave_acta ?? '';
    document.getElementById('m_tipo_sesion').value       = d?.tipo_sesion ?? 'Ordinaria';
    document.getElementById('m_legislatura').value       = d?.legislatura ?? 'LXIV';
    document.getElementById('m_legislatura_texto').value = d?.legislatura_texto ?? '';
    document.getElementById('m_periodo').value           = d?.periodo ?? '';
    document.getElementById('m_ejercicio').value         = d?.ejercicio ?? '';
    document.getElementById('m_fecha').value             = d?.fecha ?? '';
    document.getElementById('m_hora_inicio').value       = (d?.hora_inicio ?? '').substring(0,5);
    document.getElementById('m_ciudad').value            = d?.ciudad ?? 'Mérida';
    document.getElementById('m_recinto').value           = d?.recinto ?? '';

    // ✅ mesa directiva
    document.getElementById('m_presidente').value        = d?.presidente ?? '';
    document.getElementById('m_secretaria_1').value      = d?.secretaria_1 ?? '';
    document.getElementById('m_secretaria_2').value      = d?.secretaria_2 ?? '';

    enforceUniqueDiputados();
  }

  btnMeta?.addEventListener('click', async () => {
    if (!actaId) return alert("Primero genera el acta para poder guardar metadatos.");
    if (!modalMeta) return alert("Bootstrap Modal no está disponible. Verifica bootstrap.bundle.");

    if (metaMsg) metaMsg.style.display = 'none';

    // ✅ NUEVO: en vez de limpiar, precarga defaults desde corrección
    fillMetaForm(META_DEFAULTS);

    const resp = await safeFetchJson(`index.php?ruta=actanueva/obtenerMetadata&acta_id=${actaId}`);

    if (!resp.ok) {
      console.error("obtenerMetadata NO devolvió JSON:", resp.raw);
      setMetaMsg("El servidor no devolvió JSON. Revisa consola (F12).", false);
      modalMeta.show();
      return;
    }

    const j = resp.json;
    if (j.error) setMetaMsg(j.error, false);
    else if (j.data) fillMetaForm(j.data); // si existe meta guardada, sobreescribe defaults

    modalMeta.show();
  });

  // =========================
  // GUARDAR METADATA
  // =========================
  btnGuardarMeta?.addEventListener('click', async () => {
    if (!actaId) return;

    const pres = document.getElementById('m_presidente').value;
    const s1   = document.getElementById('m_secretaria_1').value;
    const s2   = document.getElementById('m_secretaria_2').value;

    const ids = [pres, s1, s2].filter(v => v);
    const unique = new Set(ids);
    if (ids.length !== unique.size) {
      setMetaMsg("No se puede repetir la misma persona en Presidente/Secretarías.", false);
      return;
    }

    const body = new URLSearchParams();
    body.append('acta_id', actaId);
    body.append('clave_acta', document.getElementById('m_clave_acta').value);
    body.append('tipo_sesion', document.getElementById('m_tipo_sesion').value);
    body.append('legislatura', document.getElementById('m_legislatura').value);
    body.append('legislatura_texto', document.getElementById('m_legislatura_texto').value);
    body.append('periodo', document.getElementById('m_periodo').value);
    body.append('ejercicio', document.getElementById('m_ejercicio').value);
    body.append('fecha', document.getElementById('m_fecha').value);
    body.append('hora_inicio', document.getElementById('m_hora_inicio').value);
    body.append('ciudad', document.getElementById('m_ciudad').value);
    body.append('recinto', document.getElementById('m_recinto').value);
    body.append('presidente', pres);
    body.append('secretaria_1', s1);
    body.append('secretaria_2', s2);

    btnGuardarMeta.disabled = true;

    const resp = await safeFetchJson("index.php?ruta=actanueva/guardarMetadata", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
      body: body.toString()
    });

    btnGuardarMeta.disabled = false;

    if (!resp.ok) {
      console.error("guardarMetadata NO devolvió JSON:", resp.raw);
      setMetaMsg("El servidor no devolvió JSON. Revisa consola (F12).", false);
      return;
    }

    const j = resp.json;
    if (j.ok) {
      setMetaMsg("Metadatos guardados correctamente.");

      // ✅ FORZAR el estado “metadata OK” para que NO desaparezca el botón
      UI_META_OK_OVERRIDE = true;

      // Debe aparecer Generar Encabezado
      await evaluateAndApplyState();

      // (opcional) cerrar modal:
      // modalMeta?.hide();

    } else {
      setMetaMsg(j.error || "No se pudo guardar.", false);
    }
  });

}); // DOMContentLoaded
</script>