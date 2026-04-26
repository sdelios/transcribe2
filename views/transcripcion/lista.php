<div class="table-container card-style mb-4">
    <div class="card-header-title">Lista de Transcripciones</div>
    <div class="table-responsive">
        <table class="custom-table table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Fecha</th>
                    <th>Enlace</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transcripciones as $index => $trans): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($trans['cTituloTrans']) ?></td>
                        <td><?= htmlspecialchars($trans['dFechaTrans']) ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($trans['cLinkTrans']) ?>" target="_blank">
                                <img src="public/images/YouTube_full-color_icon_(2017).svg" alt="YouTube" style="height: 24px;" />
                            </a>
                        </td>
                        <td>
                            <div class="acciones-lista">

                                <!-- Editar -->
                                <a href="index.php?ruta=transcripcion/editar&id=<?= $trans['iIdTrans'] ?>"
                                   class="accion-icono"
                                   title="Editar transcripción">
                                    <img src="public/images/icon/edit.png" alt="Editar" style="height:24px;width:auto;">
                                </a>

                                <!-- Acta Word -->
                                <?php if (!empty($trans['tiene_acta']) && !empty($trans['acta_id'])): ?>
                                    <a href="index.php?ruta=actanueva/descargarWord&acta_id=<?= $trans['acta_id'] ?>"
                                       class="accion-icono"
                                       title="Descargar Acta (.docx)">
                                        <img src="public/images/icon/acta.png" alt="Acta" style="height:24px;width:auto;">
                                    </a>
                                <?php else: ?>
                                    <span class="accion-icono accion-disabled" title="Acta no disponible">
                                        <img src="public/images/icon/acta.png" alt="Acta" style="height:24px;width:auto;filter:grayscale(100%) opacity(0.35);">
                                    </span>
                                <?php endif; ?>

                                <!-- Síntesis Word -->
                                <?php if (!empty($trans['tiene_sintesis']) && !empty($trans['acta_id'])): ?>
                                    <a href="index.php?ruta=actanueva/descargarWordSintesis&acta_id=<?= $trans['acta_id'] ?>"
                                       class="accion-icono"
                                       title="Descargar Síntesis (.docx)">
                                        <img src="public/images/icon/sintesis.png" alt="Síntesis" style="height:24px;width:auto;">
                                    </a>
                                <?php else: ?>
                                    <span class="accion-icono accion-disabled" title="Síntesis no disponible">
                                        <img src="public/images/icon/sintesis.png" alt="Síntesis" style="height:24px;width:auto;filter:grayscale(100%) opacity(0.35);">
                                    </span>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
