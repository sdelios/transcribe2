<?php $esClaude = ($apiProveedor !== 'openai'); ?>

<?php if ($mensaje): ?>
<div id="toastCfg" style="
  position:fixed;bottom:28px;right:28px;z-index:9999;
  background:#0f172a;color:#fff;padding:14px 22px 14px 18px;
  border-radius:12px;box-shadow:0 6px 32px rgba(0,0,0,.35);
  display:flex;align-items:center;gap:12px;font-size:.93rem;
  min-width:260px;max-width:400px;animation:slideInToast .3s ease;">
  <span style="font-size:1.2rem">✓</span>
  <span style="flex:1"><?= htmlspecialchars($mensaje) ?></span>
  <button onclick="document.getElementById('toastCfg').remove()"
          style="background:none;border:none;color:#94a3b8;font-size:1.1rem;cursor:pointer;padding:0">&times;</button>
</div>
<style>@keyframes slideInToast{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}</style>
<script>setTimeout(function(){var t=document.getElementById('toastCfg');if(t)t.remove();},4000);</script>
<?php endif; ?>

<div class="table-container card-style mt-3" style="max-width:700px;">
  <div class="card-header-title">Configuración del sistema</div>

  <div class="p-4">

    <p class="text-muted small mb-4">
      Selecciona el proveedor de IA para <strong>corrección ortográfica</strong> y
      <strong>generación de actas</strong>. El cambio es inmediato para todos los usuarios.
    </p>

    <form method="POST" action="index.php?ruta=catalogo/configuracionGuardar" id="formCfg">

      <!-- Claude -->
      <label id="lbl-claude"
             class="d-flex align-items-start gap-3 p-3 mb-3 rounded-3 border api-card <?= $esClaude ? 'active-claude' : '' ?>"
             style="cursor:pointer;">
        <input type="radio" name="api_proveedor" value="claude" <?= $esClaude ? 'checked' : '' ?>
               class="form-check-input mt-1" style="width:1.1rem;height:1.1rem;"
               onchange="onProveedorChange('claude')">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
          <svg width="28" height="28" viewBox="0 0 28 28"><circle cx="14" cy="14" r="14" fill="#d97757"/>
            <path d="M14 6.5 L18.5 19.5 L16.5 19.5 L15.3 15.8 L12.7 15.8 L11.5 19.5 L9.5 19.5 Z M14 10 L13.2 14.2 L14.8 14.2 Z" fill="white"/>
          </svg>
          <div>
            <div class="fw-bold">Anthropic Claude
              <?php if ($esClaude): ?><span class="badge bg-warning text-dark ms-1" style="font-size:.7rem;">Activo</span><?php endif; ?>
            </div>
            <div class="text-muted small">Requiere <code>ANTHROPIC_API_KEY</code> en el <code>.env</code></div>
          </div>
        </div>
      </label>

      <!-- OpenAI -->
      <label id="lbl-openai"
             class="d-flex align-items-start gap-3 p-3 mb-4 rounded-3 border api-card <?= !$esClaude ? 'active-openai' : '' ?>"
             style="cursor:pointer;">
        <input type="radio" name="api_proveedor" value="openai" <?= !$esClaude ? 'checked' : '' ?>
               class="form-check-input mt-1" style="width:1.1rem;height:1.1rem;"
               onchange="onProveedorChange('openai')">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
          <svg width="28" height="28" viewBox="0 0 28 28"><circle cx="14" cy="14" r="14" fill="#000"/>
            <path d="M19.5 10.8a4.3 4.3 0 0 0-.29-1.6 4.45 4.45 0 0 0-7.7-.94 4.3 4.3 0 0 0-2.99 1.94 4.45 4.45 0 0 0 .6 8.44 4.3 4.3 0 0 0 1.13 1.49 4.45 4.45 0 0 0 7.47-1.67A4.45 4.45 0 0 0 19.5 10.8z" fill="white" opacity=".9"/>
          </svg>
          <div>
            <div class="fw-bold">OpenAI
              <?php if (!$esClaude): ?><span class="badge bg-success ms-1" style="font-size:.7rem;">Activo</span><?php endif; ?>
            </div>
            <div class="text-muted small">Requiere <code>OPENAI_API_KEY</code> en el <code>.env</code></div>
          </div>
        </div>
      </label>

      <div class="d-flex justify-content-between align-items-center">
        <button type="button" id="btnVerificar" class="btn btn-outline-secondary btn-sm"
                onclick="verificarApi()">
          🔌 Verificar conexión y uso
        </button>
        <button type="submit" class="btn btn-primary fw-semibold px-4">Guardar cambio</button>
      </div>

    </form>

    <!-- Panel de estado API -->
    <div id="panelEstado" class="mt-4" style="display:none;">
      <hr>
      <div id="estadoContent"></div>
    </div>

  </div>
</div>

<style>
.api-card { transition: border-color .2s, background .2s; }
.active-claude { border-color:#d97757 !important; background:#fff5f0; }
.active-openai { border-color:#000 !important; background:#f8f8f8; }
</style>

<script>
var proveedorSeleccionado = '<?= $apiProveedor ?>';

function onProveedorChange(p) {
    proveedorSeleccionado = p;
    document.getElementById('lbl-claude').className =
        'd-flex align-items-start gap-3 p-3 mb-3 rounded-3 border api-card' + (p==='claude' ? ' active-claude' : '');
    document.getElementById('lbl-openai').className =
        'd-flex align-items-start gap-3 p-3 mb-4 rounded-3 border api-card' + (p==='openai' ? ' active-openai' : '');
    document.getElementById('panelEstado').style.display = 'none';
}

function verificarApi() {
    var btn = document.getElementById('btnVerificar');
    var panel = document.getElementById('panelEstado');
    var content = document.getElementById('estadoContent');

    btn.disabled = true;
    btn.textContent = '⏳ Consultando...';
    panel.style.display = 'block';
    content.innerHTML = '<div class="text-muted small">Conectando con la API…</div>';

    fetch('index.php?ruta=catalogo/apiEstado&proveedor=' + proveedorSeleccionado)
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = '🔌 Verificar conexión y uso';

            if (!d.ok) {
                content.innerHTML = '<div class="alert alert-danger py-2 small mb-0">❌ ' + d.error + '</div>';
                return;
            }

            if (d.proveedor === 'openai') {
                var rows = '';
                if (d.modelos && d.modelos.length > 0) {
                    d.modelos.forEach(function(m) {
                        rows += '<tr><td class="small font-monospace">' + m.modelo + '</td>' +
                                '<td class="small text-end">$' + m.costo_usd.toFixed(4) + '</td></tr>';
                    });
                } else {
                    rows = '<tr><td colspan="2" class="small text-muted text-center">Sin datos de uso este mes</td></tr>';
                }

                var saldoHtml = '';
                if (d.limite_usd !== null) {
                    var pct = d.gastado_usd !== null ? Math.min(100, (d.gastado_usd / d.limite_usd * 100)).toFixed(1) : 0;
                    var color = pct > 80 ? 'danger' : pct > 50 ? 'warning' : 'success';
                    saldoHtml = '<div class="mb-3">' +
                        '<div class="d-flex justify-content-between small mb-1">' +
                        '<span>Gastado este mes: <strong>$' + (d.gastado_usd || 0).toFixed(4) + '</strong></span>' +
                        '<span>Límite: <strong>$' + d.limite_usd.toFixed(2) + '</strong></span></div>' +
                        '<div class="progress" style="height:8px;">' +
                        '<div class="progress-bar bg-' + color + '" style="width:' + pct + '%"></div></div>' +
                        '<div class="text-end small mt-1 text-muted">Disponible: <strong class="text-' + color + '">$' + (d.restante_usd || 0).toFixed(4) + '</strong></div></div>';
                } else {
                    saldoHtml = '<div class="alert alert-info py-2 small mb-3">ℹ️ No se pudo obtener el saldo (cuenta de pago por uso). Consulta <a href="https://platform.openai.com/usage" target="_blank">platform.openai.com/usage</a></div>';
                }

                content.innerHTML =
                    '<div class="d-flex align-items-center gap-2 mb-3">' +
                    '<span class="badge bg-success">✓ Conectado</span>' +
                    '<span class="small text-muted">Periodo: ' + d.periodo + '</span></div>' +
                    saldoHtml +
                    '<div class="small fw-semibold mb-2">Consumo por modelo (mes actual):</div>' +
                    '<table class="table table-sm table-bordered mb-0"><thead><tr>' +
                    '<th class="small">Modelo</th><th class="small text-end">Costo (USD)</th>' +
                    '</tr></thead><tbody>' + rows + '</tbody></table>';

            } else {
                content.innerHTML =
                    '<div class="d-flex align-items-center gap-2 mb-3">' +
                    '<span class="badge bg-success">✓ Conectado</span>' +
                    '<span class="small text-muted font-monospace">' + d.modelo + '</span></div>' +
                    '<div class="alert alert-info py-2 small mb-0">' +
                    'ℹ️ ' + d.nota + '<br>' +
                    '<a href="https://console.anthropic.com" target="_blank">console.anthropic.com →</a></div>';
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.textContent = '🔌 Verificar conexión y uso';
            content.innerHTML = '<div class="alert alert-danger py-2 small mb-0">Error de red: ' + e.message + '</div>';
        });
}
</script>
