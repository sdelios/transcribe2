<style>
.cat-badge-active   { background:#198754; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.cat-badge-inactive { background:#6c757d; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.badge-vigente      { background:#dcfce7; color:#166534; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:600; }
.badge-terminado    { background:#f3f4f6; color:#6b7280; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.badge-nominal      { background:#dbeafe; color:#1d4ed8; padding:2px 8px; border-radius:12px; font-size:.75rem; }
.badge-pluri        { background:#ede9fe; color:#6d28d9; padding:2px 8px; border-radius:12px; font-size:.75rem; }
.badge-titular      { background:#fef9c3; color:#854d0e; padding:2px 8px; border-radius:12px; font-size:.75rem; }
.badge-suplente     { background:#f0fdf4; color:#166534; padding:2px 8px; border-radius:12px; font-size:.75rem; }
.partido-dot        { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:5px; vertical-align:middle; flex-shrink:0; }
.partido-chip       { display:inline-flex; align-items:center; font-size:.78rem; font-weight:600; }
tr.dip-inactive td  { opacity:.6; }
</style>

<?php if ($mensaje): ?>
<div class="alert alert-info alert-dismissible fade show mt-3 py-2" role="alert">
  <?= htmlspecialchars($mensaje) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filtro de legislatura -->
<div class="d-flex align-items-center gap-2 mt-3 mb-2">
  <label class="form-label mb-0 fw-semibold small">Legislatura:</label>
  <select class="form-select form-select-sm" style="width:auto"
          onchange="location='index.php?ruta=catalogo/diputados&leg='+this.value">
    <option value="0">— Todas —</option>
    <?php foreach ($legislaturas as $leg): ?>
    <option value="<?= (int)$leg['id'] ?>"
      <?= ($legFiltro == $leg['id']) ? 'selected' : '' ?>>
      <?= htmlspecialchars($leg['clave'] . ' — ' . $leg['nombre']) ?>
      <?= $leg['activa'] ? ' ★' : '' ?>
    </option>
    <?php endforeach; ?>
  </select>
</div>

<div class="table-container card-style">
  <div class="card-header-title d-flex justify-content-between align-items-center">
    <span>Diputados</span>
    <button class="btn btn-sm btn-light fw-semibold" onclick="abrirNuevo()">
      + Agregar diputado
    </button>
  </div>

  <div class="table-responsive">
    <table class="custom-table table mb-0">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Nombre</th>
          <th style="width:140px">Partido</th>
          <th style="width:105px" class="text-center">Tipo elección</th>
          <th style="width:85px"  class="text-center">Mandato</th>
          <th style="width:75px"  class="text-center">Distrito</th>
          <th style="width:70px"  class="text-center">Leg.</th>
          <th style="width:105px" class="text-center">Fin mandato</th>
          <th style="width:75px"  class="text-center">Estado</th>
          <th style="width:165px" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($diputados)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">Sin registros.</td></tr>
<?php else: ?>
<?php foreach ($diputados as $d):
    $vigente = ($d['activo'] == 1 && empty($d['fecha_fin']));
    $pColor  = htmlspecialchars($d['partido_color'] ?? '#6b7280');
    $pSiglas = htmlspecialchars($d['partido_siglas'] ?? '—');
?>
        <tr class="<?= $vigente ? '' : 'dip-inactive' ?>">
          <td class="text-muted small"><?= (int)$d['id'] ?></td>
          <td class="fw-semibold">
            <?= htmlspecialchars($d['nombre']) ?>
            <?php if (!empty($d['nombre_titular'])): ?>
              <div class="text-muted small">Suplente de: <?= htmlspecialchars($d['nombre_titular']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($d['partido_siglas'])): ?>
            <span class="partido-chip">
              <span class="partido-dot" style="background:<?= $pColor ?>"></span>
              <?= $pSiglas ?>
            </span>
            <?php else: ?>
            <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($d['tipo_eleccion'] === 'nominal'): ?>
              <span class="badge-nominal">Nominal</span>
            <?php else: ?>
              <span class="badge-pluri">Plurinominal</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($d['tipo_mandato'] === 'titular'): ?>
              <span class="badge-titular">Titular</span>
            <?php else: ?>
              <span class="badge-suplente">Suplente</span>
            <?php endif; ?>
          </td>
          <td class="text-center small">
            <?= ($d['tipo_eleccion'] === 'nominal' && $d['distrito']) ? 'Dto. ' . (int)$d['distrito'] : '—' ?>
          </td>
          <td class="text-center small fw-semibold"><?= htmlspecialchars($d['leg_clave'] ?? '—') ?></td>
          <td class="text-center small">
            <?php if (!empty($d['fecha_fin'])): ?>
              <span class="badge-terminado"><?= date('d/m/Y', strtotime($d['fecha_fin'])) ?></span>
            <?php else: ?>
              <span class="badge-vigente">Vigente</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($d['activo']): ?>
              <span class="cat-badge-active">Activo</span>
            <?php else: ?>
              <span class="cat-badge-inactive">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-warning btn-sm me-1"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($d)) ?>)">
              Editar
            </button>
            <?php if ($vigente): ?>
            <button class="btn btn-sm btn-outline-danger"
                    onclick="abrirFinalizar(<?= (int)$d['id'] ?>, <?= json_encode($d['nombre']) ?>)">
              Finalizar
            </button>
            <?php else: ?>
            <form method="POST" action="index.php?ruta=catalogo/diputadosToggle" class="d-inline">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button class="btn btn-sm btn-success">Reactivar</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
<?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Agregar / Editar -->
<div class="modal fade" id="modalDip" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/diputadosGuardar">
        <input type="hidden" name="id" id="fId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDipTitle">Agregar diputado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="fNombre" class="form-control" required maxlength="200"
                   placeholder="Ej: DIP. JUAN PÉREZ MARTÍNEZ">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Legislatura <span class="text-danger">*</span></label>
              <select name="legislatura_id" id="fLegId" class="form-select" required>
                <?php foreach ($legislaturas as $leg): ?>
                <option value="<?= (int)$leg['id'] ?>"
                  <?= ($legActiva && $legActiva['id'] == $leg['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($leg['clave'] . ' — ' . $leg['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Partido</label>
              <select name="partido_id" id="fPartidoId" class="form-select">
                <option value="">— Sin partido —</option>
                <?php foreach ($partidos as $p): ?>
                <option value="<?= (int)$p['id'] ?>" data-color="<?= htmlspecialchars($p['color']) ?>">
                  <?= htmlspecialchars($p['siglas'] . ' — ' . $p['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Tipo de elección <span class="text-danger">*</span></label>
              <select name="tipo_eleccion" id="fTipoEleccion" class="form-select" onchange="onTipoEleccionChange()">
                <option value="nominal">Nominal (Distrital)</option>
                <option value="plurinominal">Plurinominal (R. Proporcional)</option>
              </select>
            </div>
            <div class="col-6" id="distritoWrap">
              <label class="form-label fw-semibold">Distrito electoral</label>
              <select name="distrito" id="fDistrito" class="form-select">
                <option value="">— Sin distrito —</option>
                <?php for ($i = 1; $i <= 21; $i++): ?>
                <option value="<?= $i ?>">Distrito <?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Tipo de mandato <span class="text-danger">*</span></label>
              <select name="tipo_mandato" id="fTipoMandato" class="form-select" onchange="onTipoMandatoChange()">
                <option value="titular">Titular</option>
                <option value="suplente">Suplente</option>
              </select>
            </div>
            <div class="col-6" id="suplenteWrap" style="display:none">
              <label class="form-label fw-semibold">Suplente de</label>
              <select name="suplente_de" id="fSuplenteDe" class="form-select">
                <option value="">— Seleccionar titular —</option>
                <?php foreach ($titulares as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Inicio de mandato</label>
              <input type="date" name="fecha_inicio" id="fInicio" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Fin de mandato</label>
              <input type="date" name="fecha_fin" id="fFin" class="form-control">
              <div class="form-text">Dejar vacío si sigue vigente.</div>
            </div>
          </div>

          <div class="col-4">
            <label class="form-label fw-semibold">Estado</label>
            <select name="activo" id="fActivo" class="form-select">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Finalizar Mandato -->
<div class="modal fade" id="modalFinalizar" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/diputadosFinalizarMandato">
        <input type="hidden" name="id" id="fFinId" value="">
        <div class="modal-header" style="background:#fff3cd">
          <h5 class="modal-title">Finalizar mandato</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-1">Diputado: <strong id="fFinNombre"></strong></p>
          <p class="text-muted small mb-3">El registro histórico se conserva. Se marcará como inactivo.</p>
          <label class="form-label fw-semibold">Fecha de término <span class="text-danger">*</span></label>
          <input type="date" name="fecha_fin" id="fFinFecha" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-semibold">Finalizar mandato</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const legActivaId = <?= (int)($legActiva['id'] ?? 0) ?>;

function onTipoEleccionChange() {
    const v = document.getElementById('fTipoEleccion').value;
    document.getElementById('distritoWrap').style.opacity = (v === 'nominal') ? '1' : '.4';
    document.getElementById('fDistrito').disabled = (v !== 'nominal');
    if (v !== 'nominal') document.getElementById('fDistrito').value = '';
}
function onTipoMandatoChange() {
    const v = document.getElementById('fTipoMandato').value;
    document.getElementById('suplenteWrap').style.display = (v === 'suplente') ? '' : 'none';
}

function abrirNuevo() {
    document.getElementById('modalDipTitle').textContent = 'Agregar diputado';
    document.getElementById('fId').value           = '0';
    document.getElementById('fNombre').value       = '';
    document.getElementById('fLegId').value        = legActivaId || '';
    document.getElementById('fPartidoId').value    = '';
    document.getElementById('fTipoEleccion').value = 'nominal';
    document.getElementById('fDistrito').value     = '';
    document.getElementById('fTipoMandato').value  = 'titular';
    document.getElementById('fSuplenteDe').value   = '';
    document.getElementById('fInicio').value       = '';
    document.getElementById('fFin').value          = '';
    document.getElementById('fActivo').value       = '1';
    onTipoEleccionChange();
    onTipoMandatoChange();
    new bootstrap.Modal(document.getElementById('modalDip')).show();
}
function abrirEditar(d) {
    document.getElementById('modalDipTitle').textContent = 'Editar diputado';
    document.getElementById('fId').value           = d.id;
    document.getElementById('fNombre').value       = d.nombre;
    document.getElementById('fLegId').value        = d.legislatura_id;
    document.getElementById('fPartidoId').value    = d.partido_id || '';
    document.getElementById('fTipoEleccion').value = d.tipo_eleccion;
    document.getElementById('fDistrito').value     = d.distrito || '';
    document.getElementById('fTipoMandato').value  = d.tipo_mandato;
    document.getElementById('fSuplenteDe').value   = d.suplente_de || '';
    document.getElementById('fInicio').value       = d.fecha_inicio || '';
    document.getElementById('fFin').value          = d.fecha_fin    || '';
    document.getElementById('fActivo').value       = d.activo;
    onTipoEleccionChange();
    onTipoMandatoChange();
    new bootstrap.Modal(document.getElementById('modalDip')).show();
}
function abrirFinalizar(id, nombre) {
    document.getElementById('fFinId').value           = id;
    document.getElementById('fFinNombre').textContent = nombre;
    document.getElementById('fFinFecha').value        = new Date().toISOString().slice(0,10);
    new bootstrap.Modal(document.getElementById('modalFinalizar')).show();
}
</script>
