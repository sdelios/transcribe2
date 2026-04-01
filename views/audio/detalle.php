

<div class="table-container card-style mb-4">
<div class="card-header-title">Audio - <?= htmlspecialchars($audio['cNameAudio']) ?></div>

    <!-- Primera fila: Link del video -->
    <div class="row align-items-center mb-3">
        <div class="col-auto">
            <span class="tag-title tag-danger">Link del video:</span>
        </div>
        <div class="col">
            <input type="text" class="form-control" value="<?= htmlspecialchars($audio['cLink']) ?>" disabled>
            <input type="hidden" name="ruta_oculta" value="<?= htmlspecialchars($audio['cLink']) ?>">
        </div>
    </div>

    <!-- Segunda fila: Audio y Velocidad -->
    <div class="row align-items-center mb-4">
        <div class="col-auto">
            <span class="tag-title tag-info">Audio:</span>
        </div>
        <div class="col">
            <audio id="audioPlayer" controls class="w-100">
                <source src="<?= $audio['cRuta'] ?>" type="audio/mp3">
                Tu navegador no soporta el elemento de audio.
            </audio>
        </div>
        <div class="col-auto">
            <span class="tag-title tag-success">Velocidad:</span>
        </div>
        <div class="col-auto">
            <select id="velocidad" class="audio-style-select" onchange="document.getElementById('audioPlayer').playbackRate = this.value">
                <option value="0.5">0.5x</option>
                <option value="1" selected>1x</option>
                <option value="1.5">1.5x</option>
                <option value="2">2x</option>
            </select>
        </div>
    </div>

    <hr class="bg-white">  </hr>  <hr class="bg-white">  </hr>


    <!-- <h5 class="tag-title tag-black">Transcripciones por Modelo</h5> -->
    <div class="card-header-title">Transcripciones por Modelo</div>

    <hr class="bg-white">  </hr>

<?php foreach ($modelos as $modelo): ?>
    <?php
        $trans = array_filter($audio['transcripciones'], function($t) use ($modelo) {
            return $t['iIdModeloTrans'] == $modelo['iIdModeloTrans'];
        });
        $modeloNombre = strtolower($modelo['cNombreModeloTrans']);
        $transcripcion = count($trans) ? $trans[array_key_first($trans)] : null;
    ?>

    <div class="card mb-4">
        <div class="card-header fw-bold bg-dark text-white d-flex justify-content-between align-items-center">
            <?= htmlspecialchars($modelo['cNombreModeloTrans']) ?>

            <?php if ($transcripcion): ?>
                <a href="index.php?ruta=transcripcion/editar&id=<?= $transcripcion['iIdTrans'] ?>" class="btn btn-sm btn-success">Editar</a>
            <?php else: ?>
                <button class="btn btn-sm btn-warning btn-generar-modelo"
                        data-modelo="<?= $modeloNombre ?>"
                        data-ruta="<?= htmlspecialchars($audio['cRuta']) ?>"
                        data-idaudio="<?= $audio['iIDAudio'] ?>"
                        data-idmodelo="<?= $modelo['iIdModeloTrans'] ?>"
                        data-clink="<?= htmlspecialchars($audio['cLink']) ?>"
                        data-contenedor="contenedor-<?= $modeloNombre ?>">
                    Generar
                </button>
            <?php endif; ?>
        </div>

        <div class="card-body text-justify" id="contenedor-<?= $modeloNombre ?>" style="white-space: pre-wrap;">
            <?php if ($transcripcion): ?>
                <?= htmlspecialchars($transcripcion['tTrans']) ?>
            <?php else: ?>
                <em class="text-muted">No se ha generado transcripción para este modelo.</em>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>


    </div>
</div>

<script src="public/js/modeloTranscripcion.js"></script>
