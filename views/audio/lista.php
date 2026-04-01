<div class="table-container card-style mb-4">
    <div class="card-header-title">Lista de Audios</div>
    <div class="table-responsive">
        <table class="custom-table table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th colspan="<?= count($modelos) ?>" class="text-center">Transcripciones</th>
                    <!-- <th>Diarización</th> -->
                    <th>Acciones</th>
                </tr>
                <tr>
                    <th></th><th></th>
                    <?php foreach ($modelos as $modelo): ?>
                        <th class="text-center"><?= $modelo['cNombreModeloTrans'] ?></th>
                    <?php endforeach; ?>
                    <th></th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audios as $index => $audio): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($audio['cNameAudio']) ?></td>

                    <?php foreach ($modelos as $modelo): ?>
                        <td class="text-center">
                            <?php
                            $tiene = array_filter($audio['transcripciones'], function($t) use ($modelo) {
                                return $t['iIdModeloTrans'] == $modelo['iIdModeloTrans'];
                            });
                            echo count($tiene) ? '<span class="text-success">&#x2713</span>' : '<span class="text-danger">X</span>';
                            ?>
                        </td>
                    <?php endforeach; ?>

                    <!-- Nueva columna: Diarización 
                    <td class="text-center">
                        <?php if ($audio['diarizado']): ?>
                            <span class="text-success">&#x2713;</span>
                        <?php else: ?>
                            <span class="text-danger">X</span>
                        <?php endif; ?>
                    </td>-->

                    <td>
                        <a href="index.php?ruta=audio/detalle&idAudio=<?= $audio['iIDAudio'] ?>" class="btn btn-warning btn-sm">Ver</a>

                        <!-- <?php if ($audio['diarizado']): ?>
                            <a href="index.php?c=Diarizacion&a=ver&id=<?= $audio['iIDAudio'] ?>" class="btn btn-secondary btn-sm mt-1">Ver Diarización</a>
                        <?php else: ?>
                            <a href="index.php?ruta=diarizacion/procesar&idAudio=<?= $audio['iIDAudio'] ?>" class="btn btn-outline-primary btn-sm mt-1">Diarizar</a>

                        <?php endif; ?> -->
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
