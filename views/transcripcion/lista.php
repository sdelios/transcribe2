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
                    <td ><a href="<?= htmlspecialchars($trans['cLinkTrans']) ?>" target="_blank"><img src="public/images/YouTube_full-color_icon_(2017).svg" alt="YouTube" style="height: 24px;" /></a></td>
                    <td>
                        <a href="index.php?ruta=transcripcion/editar&id=<?= $trans['iIdTrans'] ?>" class="btn btn-warning btn-sm">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>