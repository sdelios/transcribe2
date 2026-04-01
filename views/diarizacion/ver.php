<?php include_once __DIR__ . '/../layout.php'; ?>

<div class="container mt-4">
    <h3 class="mb-3">Diarización del audio</h3>

    <?php if (isset($_GET['exito'])): ?>
    <div class="alert alert-success">✅ Diarización completada correctamente.</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger">❌ Ocurrió un error al procesar la diarización. Verifica que el archivo sea válido y que el entorno Python funcione.</div>
<?php endif; ?>

    <?php if (count($segmentos) === 0): ?>
        <div class="alert alert-warning">No se encontraron segmentos diarizados para este audio.</div>
    <?php else: ?>
        <form method="post" action="index.php?c=Diarizacion&a=guardarCambios">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Speaker</th>
                        <th>Texto</th>
                        <th>Participante asignado</th>
                        <th>Asignar participante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    require_once __DIR__ . '/../../models/UsuarioModel.php';
                    $modeloUsuario = new UsuarioModel();
                    $usuarios = $modeloUsuario->obtenerTodos();

                    foreach ($segmentos as $index => $s): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $s['fInicio'] ?>s</td>
                            <td><?= $s['fFin'] ?>s</td>
                            <td><?= htmlspecialchars($s['cSpeaker']) ?></td>
                            <td>
                                <input type="hidden" name="segmentos[<?= $index ?>][iIdDiarizacion]" value="<?= $s['iIdDiarizacion'] ?>">
                                <textarea name="segmentos[<?= $index ?>][tTexto]" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($s['tTexto']) ?></textarea>
                            </td>
                            <td>
                                <?= $s['cNombre'] ?? '<em>No asignado</em>' ?>
                            </td>
                            <td>
                                <select name="segmentos[<?= $index ?>][iIdUsuario]" class="form-select form-select-sm">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?= $u['iIdUsuario'] ?>" <?= ($u['iIdUsuario'] == $s['iIdParticipante']) ? 'selected' : '' ?>>
                                            <?= $u['cNombre'] ?> (<?= $u['cAlias'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-success">Guardar todos los cambios</button>
        </form>
    <?php endif; ?>
</div>
