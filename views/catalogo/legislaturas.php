<style>
.badge-activa   { background:#166534; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:600; }
.badge-inactiva { background:#6c757d; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
</style>

<?php if ($mensaje): ?>
<div class="alert alert-info alert-dismissible fade show mt-3 py-2" role="alert">
  <?= htmlspecialchars($mensaje) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="table-container card-style mt-3">
  <div class="card-header-title d-flex justify-content-between align-items-center">
    <span>Legislaturas</span>
    <button class="btn btn-sm btn-light fw-semibold" onclick="abrirNuevo()">
      + Nueva legislatura
    </button>
  </div>

  <div class="table-responsive">
    <table class="custom-table table mb-0">
      <thead>
        <tr>
          <th style="width:55px">#</th>
          <th style="width:80px" class="text-center">Núm.</th>
          <th style="width:90px" class="text-center">Clave</th>
          <th>Nombre</th>
          <th style="width:120px" class="text-center">Inicio</th>
          <th style="width:120px" class="text-center">Fin</th>
          <th style="width:90px"  class="text-center">Estado</th>
          <th style="width:150px" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($legislaturas)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Sin registros.</td></tr>
<?php else: ?>
<?php foreach ($legislaturas as $l): ?>
        <tr>
          <td class="text-muted small"><?= (int)$l['id'] ?></td>
          <td class="text-center"><?= (int)$l['numero'] ?></td>
          <td class="text-center fw-semibold"><?= htmlspecialchars($l['clave']) ?></td>
          <td><?= htmlspecialchars($l['nombre']) ?></td>
          <td class="text-center small">
            <?= $l['fecha_inicio'] ? date('d/m/Y', strtotime($l['fecha_inicio'])) : '—' ?>
          </td>
          <td class="text-center small">
            <?= $l['fecha_fin'] ? date('d/m/Y', strtotime($l['fecha_fin'])) : '—' ?>
          </td>
          <td class="text-center">
            <?php if ($l['activa']): ?>
              <span class="badge-activa">Activa</span>
            <?php else: ?>
              <span class="badge-inactiva">Inactiva</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-warning btn-sm me-1"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($l)) ?>)">
              Editar
            </button>
            <?php if (!$l['activa']): ?>
            <form method="POST" action="index.php?ruta=catalogo/legislaturasActivar" class="d-inline">
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="btn btn-sm btn-success" title="Marcar como legislatura activa">
                Activar
              </button>
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
<div class="modal fade" id="modalLeg" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/legislaturasGuardar">
        <input type="hidden" name="id" id="fId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalLegTitle">Nueva legislatura</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-4">
              <label class="form-label fw-semibold">Número <span class="text-danger">*</span></label>
              <input type="number" name="numero" id="fNumero" class="form-control" required
                     min="1" placeholder="64">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Clave <span class="text-danger">*</span></label>
              <input type="text" name="clave" id="fClave" class="form-control" required
                     maxlength="20" placeholder="LXIV">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">¿Activa?</label>
              <select name="activa" id="fActiva" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="fNombre" class="form-control" required
                   maxlength="150" placeholder="Sexagésima Cuarta">
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Fecha inicio</label>
              <input type="date" name="fecha_inicio" id="fInicio" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Fecha fin</label>
              <input type="date" name="fecha_fin" id="fFin" class="form-control">
            </div>
          </div>
          <p class="text-muted small mt-2">
            Al marcar como activa, las demás legislaturas quedarán inactivas automáticamente.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirNuevo() {
    document.getElementById('modalLegTitle').textContent = 'Nueva legislatura';
    document.getElementById('fId').value     = '0';
    document.getElementById('fNumero').value = '';
    document.getElementById('fClave').value  = '';
    document.getElementById('fNombre').value = '';
    document.getElementById('fActiva').value = '0';
    document.getElementById('fInicio').value = '';
    document.getElementById('fFin').value    = '';
    new bootstrap.Modal(document.getElementById('modalLeg')).show();
}
function abrirEditar(l) {
    document.getElementById('modalLegTitle').textContent = 'Editar legislatura';
    document.getElementById('fId').value     = l.id;
    document.getElementById('fNumero').value = l.numero;
    document.getElementById('fClave').value  = l.clave;
    document.getElementById('fNombre').value = l.nombre;
    document.getElementById('fActiva').value = l.activa;
    document.getElementById('fInicio').value = l.fecha_inicio || '';
    document.getElementById('fFin').value    = l.fecha_fin    || '';
    new bootstrap.Modal(document.getElementById('modalLeg')).show();
}
</script>
