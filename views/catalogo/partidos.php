<style>
.partido-swatch { display:inline-block; width:16px; height:16px; border-radius:50%; vertical-align:middle; margin-right:6px; border:1px solid rgba(0,0,0,.15); }
.badge-p-active   { background:#198754; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.badge-p-inactive { background:#6c757d; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
</style>

<?php if ($mensaje): ?>
<div class="alert alert-info alert-dismissible fade show mt-3 py-2" role="alert">
  <?= htmlspecialchars($mensaje) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="table-container card-style mt-3">
  <div class="card-header-title d-flex justify-content-between align-items-center">
    <span>Partidos políticos</span>
    <button class="btn btn-sm btn-light fw-semibold" onclick="abrirNuevo()">
      + Nuevo partido
    </button>
  </div>

  <div class="table-responsive">
    <table class="custom-table table mb-0">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th style="width:100px" class="text-center">Siglas</th>
          <th>Nombre</th>
          <th style="width:100px" class="text-center">Color</th>
          <th style="width:90px"  class="text-center">Estado</th>
          <th style="width:130px" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($partidos)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Sin registros.</td></tr>
<?php else: ?>
<?php foreach ($partidos as $p): ?>
        <tr <?= (!$p['activo']) ? 'style="opacity:.6"' : '' ?>>
          <td class="text-muted small"><?= (int)$p['id'] ?></td>
          <td class="text-center fw-bold"><?= htmlspecialchars($p['siglas']) ?></td>
          <td>
            <span class="partido-swatch" style="background:<?= htmlspecialchars($p['color']) ?>"></span>
            <?= htmlspecialchars($p['nombre']) ?>
          </td>
          <td class="text-center">
            <code class="small"><?= htmlspecialchars($p['color']) ?></code>
          </td>
          <td class="text-center">
            <?php if ($p['activo']): ?>
              <span class="badge-p-active">Activo</span>
            <?php else: ?>
              <span class="badge-p-inactive">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-warning btn-sm me-1"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($p)) ?>)">
              Editar
            </button>
            <form method="POST" action="index.php?ruta=catalogo/partidosToggle" class="d-inline">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm <?= $p['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                      title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>">
                <?= $p['activo'] ? '✕' : '✓' ?>
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
<div class="modal fade" id="modalPartido" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/partidosGuardar">
        <input type="hidden" name="id" id="fId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalPartidoTitle">Nuevo partido</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="fNombre" class="form-control" required maxlength="150">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Siglas <span class="text-danger">*</span></label>
              <input type="text" name="siglas" id="fSiglas" class="form-control" required
                     maxlength="30" placeholder="PAN">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Color</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="color" id="fColor" class="form-control form-control-color"
                       style="width:48px; height:38px; padding:2px">
                <span id="fColorHex" class="small text-muted">#6b7280</span>
              </div>
            </div>
          </div>
          <div>
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

<script>
document.getElementById('fColor').addEventListener('input', function() {
    document.getElementById('fColorHex').textContent = this.value;
});

function abrirNuevo() {
    document.getElementById('modalPartidoTitle').textContent = 'Nuevo partido';
    document.getElementById('fId').value     = '0';
    document.getElementById('fNombre').value = '';
    document.getElementById('fSiglas').value = '';
    document.getElementById('fColor').value  = '#6b7280';
    document.getElementById('fColorHex').textContent = '#6b7280';
    document.getElementById('fActivo').value = '1';
    new bootstrap.Modal(document.getElementById('modalPartido')).show();
}
function abrirEditar(p) {
    document.getElementById('modalPartidoTitle').textContent = 'Editar partido';
    document.getElementById('fId').value     = p.id;
    document.getElementById('fNombre').value = p.nombre;
    document.getElementById('fSiglas').value = p.siglas;
    document.getElementById('fColor').value  = p.color;
    document.getElementById('fColorHex').textContent = p.color;
    document.getElementById('fActivo').value = p.activo;
    new bootstrap.Modal(document.getElementById('modalPartido')).show();
}
</script>
