<style>
.badge-admin    { background:#7c3aed; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.badge-debate   { background:#2563eb; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.badge-active   { background:#198754; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
.badge-inactive { background:#6c757d; color:#fff; padding:3px 10px; border-radius:20px; font-size:.78rem; }
</style>

<?php if ($mensaje): ?>
<div id="toastUsuario" style="
  position:fixed; bottom:28px; right:28px; z-index:9999;
  background:#0f172a; color:#fff;
  padding:14px 22px 14px 18px;
  border-radius:12px;
  box-shadow:0 6px 32px rgba(0,0,0,.35);
  display:flex; align-items:center; gap:12px;
  font-size:.93rem; min-width:260px; max-width:400px;
  animation: slideInToast .3s ease;">
  <span style="font-size:1.2rem">✓</span>
  <span style="flex:1"><?= htmlspecialchars($mensaje) ?></span>
  <button onclick="document.getElementById('toastUsuario').remove()"
          style="background:none;border:none;color:#94a3b8;font-size:1.1rem;cursor:pointer;padding:0;line-height:1">&times;</button>
</div>
<style>
@keyframes slideInToast {
  from { opacity:0; transform:translateY(20px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>
<script>
setTimeout(function(){ var t=document.getElementById('toastUsuario'); if(t) t.remove(); }, 4000);
</script>
<?php endif; ?>

<div class="table-container card-style mt-3">
  <div class="card-header-title d-flex justify-content-between align-items-center">
    <span>Usuarios del sistema</span>
    <button class="btn btn-sm btn-light fw-semibold" onclick="abrirNuevo()">
      + Nuevo usuario
    </button>
  </div>

  <p class="text-muted small mb-3">
    Tipo <strong>Administrador</strong> tiene acceso total. Tipo <strong>Debates</strong> accede solo a transcripciones y correcciones.
  </p>

  <div class="table-responsive">
    <table class="custom-table table mb-0">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>Nombre</th>
          <th style="width:150px">Usuario</th>
          <th style="width:120px" class="text-center">Tipo</th>
          <th style="width:180px" class="text-center">Último acceso</th>
          <th style="width:80px"  class="text-center">Estado</th>
          <th style="width:200px" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($usuarios)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Sin registros.</td></tr>
<?php else: ?>
<?php foreach ($usuarios as $u): ?>
        <tr <?= ($u['iStatus'] == 0) ? 'style="opacity:.6"' : '' ?>>
          <td class="text-muted small"><?= (int)$u['iIdUsuario'] ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($u['cNombre']) ?></td>
          <td class="small font-monospace"><?= htmlspecialchars($u['cUsuario']) ?></td>
          <td class="text-center">
            <?php if ($u['iTipo'] == 1): ?>
              <span class="badge-admin">Administrador</span>
            <?php else: ?>
              <span class="badge-debate">Debates</span>
            <?php endif; ?>
          </td>
          <td class="text-center small text-muted">
            <?= $u['dUltimoAcceso'] ? date('d/m/Y H:i', strtotime($u['dUltimoAcceso'])) : '—' ?>
          </td>
          <td class="text-center">
            <?php if ($u['iStatus']): ?>
              <span class="badge-active">Activo</span>
            <?php else: ?>
              <span class="badge-inactive">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-warning btn-sm"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($u)) ?>)">
              Editar
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="abrirReset(<?= (int)$u['iIdUsuario'] ?>, <?= htmlspecialchars(json_encode($u['cNombre']), ENT_QUOTES) ?>)">
              Contraseña
            </button>
            <form method="POST" action="index.php?ruta=catalogo/usuariosToggle" class="d-inline">
              <input type="hidden" name="id" value="<?= (int)$u['iIdUsuario'] ?>">
              <button class="btn btn-sm <?= $u['iStatus'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                      title="<?= $u['iStatus'] ? 'Desactivar' : 'Activar' ?>">
                <?= $u['iStatus'] ? '✕' : '✓' ?>
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
<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/usuariosGuardar">
        <input type="hidden" name="iIdUsuario" id="fId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUserTitle">Nuevo usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" name="cNombre" id="fNombre" class="form-control" required maxlength="150">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Nombre de usuario <span class="text-danger">*</span></label>
              <input type="text" name="cUsuario" id="fUsuario" class="form-control" required maxlength="60"
                     autocomplete="off" placeholder="sin espacios">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Tipo</label>
              <select name="iTipo" id="fTipo" class="form-select">
                <option value="2">Debates</option>
                <option value="1">Administrador</option>
              </select>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6" id="pwdWrap">
              <label class="form-label fw-semibold">Contraseña <span class="text-danger" id="pwdRequired">*</span></label>
              <input type="password" name="cPassword" id="fPwd" class="form-control"
                     autocomplete="new-password" placeholder="mínimo 6 caracteres">
              <div class="form-text" id="pwdHint" style="display:none">Dejar vacío para no cambiar.</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Estado</label>
              <select name="iStatus" id="fStatus" class="form-select">
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

<!-- Modal Resetear Contraseña -->
<div class="modal fade" id="modalReset" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="index.php?ruta=catalogo/usuariosReset">
        <input type="hidden" name="id" id="fResetId" value="">
        <div class="modal-header" style="background:#fef3c7">
          <h5 class="modal-title">Restablecer contraseña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Usuario: <strong id="fResetNombre"></strong></p>
          <label class="form-label fw-semibold">Nueva contraseña <span class="text-danger">*</span></label>
          <input type="password" name="nueva_password" id="fResetPwd" class="form-control"
                 required minlength="6" autocomplete="new-password"
                 placeholder="mínimo 6 caracteres">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-semibold">Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirNuevo() {
    document.getElementById('modalUserTitle').textContent = 'Nuevo usuario';
    document.getElementById('fId').value      = '0';
    document.getElementById('fNombre').value  = '';
    document.getElementById('fUsuario').value = '';
    document.getElementById('fTipo').value    = '2';
    document.getElementById('fPwd').value     = '';
    document.getElementById('fStatus').value  = '1';
    document.getElementById('pwdRequired').style.display = '';
    document.getElementById('pwdHint').style.display     = 'none';
    new bootstrap.Modal(document.getElementById('modalUser')).show();
}
function abrirEditar(u) {
    document.getElementById('modalUserTitle').textContent = 'Editar usuario';
    document.getElementById('fId').value      = u.iIdUsuario;
    document.getElementById('fNombre').value  = u.cNombre;
    document.getElementById('fUsuario').value = u.cUsuario;
    document.getElementById('fTipo').value    = u.iTipo;
    document.getElementById('fPwd').value     = '';
    document.getElementById('fStatus').value  = u.iStatus;
    document.getElementById('pwdRequired').style.display = 'none';
    document.getElementById('pwdHint').style.display     = '';
    new bootstrap.Modal(document.getElementById('modalUser')).show();
}
function abrirReset(id, nombre) {
    document.getElementById('fResetId').value           = id;
    document.getElementById('fResetNombre').textContent = nombre;
    document.getElementById('fResetPwd').value          = '';
    new bootstrap.Modal(document.getElementById('modalReset')).show();
}
</script>
