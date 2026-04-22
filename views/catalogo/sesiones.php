<style>
.cat-badge-active   { background:#198754; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.cat-badge-inactive { background:#6c757d; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
</style>

<?php if ($mensaje): ?>
<div class="alert alert-info alert-dismissible fade show mt-3 py-2" role="alert">
  <?= htmlspecialchars($mensaje) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="table-container card-style">
  <div class="card-header-title d-flex justify-content-between align-items-center">
    <span>Sesiones</span>
    <button class="btn btn-sm btn-light fw-semibold" onclick="abrirNuevo()"
            data-bs-toggle="modal" data-bs-target="#modalSesion">
      + Agregar sesión
    </button>
  </div>

  <div class="table-responsive">
    <table class="custom-table table mb-0">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>Nombre</th>
          <th>Tipo de Sesión</th>
          <th style="width:100px" class="text-center">Estado</th>
          <th style="width:170px" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($sesiones)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Sin registros.</td></tr>
<?php else: ?>
<?php foreach ($sesiones as $s): ?>
        <tr>
          <td class="text-muted small"><?= (int)$s['iIdCatSesion'] ?></td>
          <td><?= htmlspecialchars($s['cNombreCatSesion']) ?></td>
          <td><?= htmlspecialchars($s['cTipoNombre']) ?></td>
          <td class="text-center">
            <?php if ($s['iStatusCatSesion']): ?>
              <span class="cat-badge-active">Activo</span>
            <?php else: ?>
              <span class="cat-badge-inactive">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-warning btn-sm me-1"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($s)) ?>)">
              Editar
            </button>
            <form method="POST" action="index.php?ruta=catalogo/sesionesToggle" class="d-inline"
                  onsubmit="return confirm('¿Cambiar estado?')">
              <input type="hidden" name="id" value="<?= (int)$s['iIdCatSesion'] ?>">
              <button class="btn btn-sm <?= $s['iStatusCatSesion'] ? 'btn-secondary' : 'btn-success' ?>">
                <?= $s['iStatusCatSesion'] ? 'Desactivar' : 'Activar' ?>
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
<div class="modal fade" id="modalSesion" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/sesionesGuardar">
        <input type="hidden" name="iIdCatSesion" id="fId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalSesionTitle">Agregar sesión</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="cNombreCatSesion" id="fNombre" class="form-control" required maxlength="180">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Tipo de Sesión <span class="text-danger">*</span></label>
            <select name="iTipoSesion" id="fTipo" class="form-select" required>
              <option value="">— Seleccione —</option>
              <?php foreach ($tiposSesion as $t): ?>
              <option value="<?= (int)$t['iIdCatTipoSesiones'] ?>">
                <?= htmlspecialchars($t['cCatTipoSesiones']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Estado</label>
            <select name="iStatusCatSesion" id="fStatus" class="form-select">
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

<script>
function abrirNuevo() {
    document.getElementById('modalSesionTitle').textContent = 'Agregar sesión';
    document.getElementById('fId').value     = '0';
    document.getElementById('fNombre').value = '';
    document.getElementById('fTipo').value   = '';
    document.getElementById('fStatus').value = '1';
}
function abrirEditar(s) {
    document.getElementById('modalSesionTitle').textContent = 'Editar sesión';
    document.getElementById('fId').value     = s.iIdCatSesion;
    document.getElementById('fNombre').value = s.cNombreCatSesion;
    document.getElementById('fTipo').value   = s.iTipoSesion;
    document.getElementById('fStatus').value = s.iStatusCatSesion;
    new bootstrap.Modal(document.getElementById('modalSesion')).show();
}
</script>
