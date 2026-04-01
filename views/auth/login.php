<div class="login-wrap">
  <div class="login-card-wrap">

    <!-- Etiqueta tipo "tarjeta" (como tu sección Transcribir) -->
    <div class="login-tag">Iniciar sesión</div>

    <div class="login-card">
      <form method="POST" action="index.php?ruta=auth/validar" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">Usuario</label>
          <input type="text" name="usuario" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <!-- Botón centrado, 50% ancho, rojo -->
        <div class="text-center mt-4">
          <button class="btn btn-danger login-btn" type="submit">Entrar</button>
        </div>
      </form>
    </div>

    <!-- ALERTA abajo (fuera del card) -->
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger mt-3 login-alert">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="text-center mt-3 text-muted small">
      Transcriptor • Acceso restringido
    </div>

  </div>
</div>
