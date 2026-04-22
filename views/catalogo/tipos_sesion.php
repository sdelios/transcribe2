<?php
$modalidades = ['pleno' => 'Pleno', 'comision' => 'Comisión', 'diputacion' => 'Diputación Permanente'];
?>
<style>
.cat-badge-active   { background:#198754; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.cat-badge-inactive { background:#6c757d; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.mod-badge-pleno      { background:#dbeafe; color:#1d4ed8; padding:3px 12px; border-radius:20px; font-size:.8rem; font-weight:600; }
.mod-badge-comision   { background:#dcfce7; color:#166534; padding:3px 12px; border-radius:20px; font-size:.8rem; font-weight:600; }
.mod-badge-diputacion { background:#fef9c3; color:#854d0e; padding:3px 12px; border-radius:20px; font-size:.8rem; font-weight:600; }
.modal-modalidad span { display:inline-block; padding:5px 14px; border-radius:20px; font-size:.85rem; cursor:pointer; border:2px solid transparent; margin:3px; }
.modal-modalidad span.selected { border-color:#0d6efd; font-weight:600; }
.modal-modalidad span[data-val="pleno"]      { background:#dbeafe; color:#1d4ed8; }
.modal-modalidad span[data-val="comision"]   { background:#dcfce7; color:#166534; }
.modal-modalidad span[data-val="diputacion"] { background:#fef9c3; color:#854d0e; }
</style>

<?php if ($mensaje): ?>
<div class="alert alert-info alert-dismissible fade show mt-3 py-2" role="alert">
  <?= htmlspecialchars($mensaje) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="table-container card-style">
  <div class="card-header-title d-flex justify-content-between align-items-center">
    <span>Tipos de Sesión</span>
    <button class="btn btn-sm btn-light fw-semibold" onclick="abrirNuevo()"
            data-bs-toggle="modal" data-bs-target="#modalTipo">
      + Agregar tipo
    </button>
  </div>

  <div class="table-responsive">
    <table class="custom-table table mb-0">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>Nombre</th>
          <th>Modalidad</th>
          <th style="width:80px" class="text-center">Orden</th>
          <th style="width:100px" class="text-center">Estado</th>
          <th style="width:170px" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($tipos)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Sin registros.</td></tr>
<?php else: ?>
<?php foreach ($tipos as $t): ?>
        <tr>
          <td class="text-muted small"><?= (int)$t['iIdCatTipoSesiones'] ?></td>
          <td><?= htmlspecialchars($t['cCatTipoSesiones']) ?></td>
          <td>
            <?php $mod = $t['cModalidadSesion'] ?? 'pleno'; ?>
            <span class="mod-badge-<?= htmlspecialchars($mod) ?>">
              <?= htmlspecialchars($modalidades[$mod] ?? $mod) ?>
            </span>
          </td>
          <td class="text-center"><?= (int)$t['iOrder'] ?></td>
          <td class="text-center">
            <?php if ($t['iStatusCatTipoSesiones']): ?>
              <span class="cat-badge-active">Activo</span>
            <?php else: ?>
              <span class="cat-badge-inactive">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-warning btn-sm me-1"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($t)) ?>)">
              Editar
            </button>
            <form method="POST" action="index.php?ruta=catalogo/tiposSesionToggle" class="d-inline"
                  onsubmit="return confirm('¿Cambiar estado?')">
              <input type="hidden" name="id" value="<?= (int)$t['iIdCatTipoSesiones'] ?>">
              <button class="btn btn-sm <?= $t['iStatusCatTipoSesiones'] ? 'btn-secondary' : 'btn-success' ?>">
                <?= $t['iStatusCatTipoSesiones'] ? 'Desactivar' : 'Activar' ?>
              </button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
<?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Agregar / Editar -->
<div class="modal fade" id="modalTipo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/tiposSesionGuardar">
        <input type="hidden" name="iIdCatTipoSesiones" id="fId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTipoTitle">Agregar tipo de sesión</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="cCatTipoSesiones" id="fNombre" class="form-control" required maxlength="120">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Modalidad</label>
            <div class="modal-modalidad d-flex flex-wrap gap-1" id="fModalidadWrap">
              <span data-val="pleno"      onclick="selModalidad('pleno')">Pleno</span>
              <span data-val="comision"   onclick="selModalidad('comision')">Comisión</span>
              <span data-val="diputacion" onclick="selModalidad('diputacion')">Diputación Permanente</span>
            </div>
            <input type="hidden" name="cModalidadSesion" id="fModalidad" value="pleno">
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Orden de visualización</label>
              <input type="number" name="iOrder" id="fOrden" class="form-control" value="0" min="0">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Estado</label>
              <select name="iStatusCatTipoSesiones" id="fStatus" class="form-select">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </select>
            </div>
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

<script>
function selModalidad(val) {
    document.getElementById('fModalidad').value = val;
    document.querySelectorAll('#fModalidadWrap span').forEach(s => {
        s.classList.toggle('selected', s.dataset.val === val);
    });
}
function abrirNuevo() {
    document.getElementById('modalTipoTitle').textContent = 'Agregar tipo de sesión';
    document.getElementById('fId').value     = '0';
    document.getElementById('fNombre').value = '';
    document.getElementById('fOrden').value  = '0';
    document.getElementById('fStatus').value = '1';
    selModalidad('pleno');
}
function abrirEditar(t) {
    document.getElementById('modalTipoTitle').textContent = 'Editar tipo de sesión';
    document.getElementById('fId').value     = t.iIdCatTipoSesiones;
    document.getElementById('fNombre').value = t.cCatTipoSesiones;
    document.getElementById('fOrden').value  = t.iOrder || 0;
    document.getElementById('fStatus').value = t.iStatusCatTipoSesiones;
    selModalidad(t.cModalidadSesion || 'pleno');
    new bootstrap.Modal(document.getElementById('modalTipo')).show();
}
selModalidad('pleno');
</script>
