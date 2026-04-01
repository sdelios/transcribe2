<h3 class="text-center">Transcribir desde un enlace</h3>

<form id="formulario" method="post" class="mt-4">
<div class="mb-3 row align-items-end">
    <div class="col-md-9">
        <label for="url" class="form-label text-white">URL del video:</label>
        <input type="text" class="form-control" id="url" name="url" required>
    </div>
    <div class="col-md-3">
        <label class="form-label text-white d-none d-md-block">&nbsp;</label>
        <button type="submit" class="btn btn-success w-100">Transcribir</button>
    </div>
</div>
    <div class="mb-3">
        <label for="nombre" class="form-label text-white">Nombre del archivo:</label>
        <input type="text" class="form-control" id="nombre" name="nombre" required>
    </div>
</form>

<div id="progress" class="progress mt-4" style="display: none;">
    <div id="bar" class="progress-bar" role="progressbar" style="width: 0%">0%</div>
</div>

<div id="procesando" class="mt-2" style="display: none;">
    <strong class="text-white">Procesando<span id="puntos">.</span></strong>
    <span class="text-white ms-3">Tiempo: <span id="cronometro">00:00</span></span>
</div>

<form action="index.php?ruta=transcripcion/guardar" method="post" class="mt-4">
    <input type="hidden" name="cTituloTrans" id="cTituloTrans">
    <input type="hidden" name="dFechaTrans" id="dFechaTrans">
    <input type="hidden" name="cLinkTrans" id="cLinkTrans">
    <input type="hidden" name="tTrans" id="tTrans">
    <input type="hidden" name="iIdAudio" id="iIdAudio">
    <button type="submit" id="btnGuardar" class="btn btn-info" style="display: none;">Guardar Transcripción</button>
</form>

<div id="output" class="mt-4 text-white"></div>
<!-- Botón para abrir modal -->
<button type="button" id="btnModalGuardar" class="btn btn-success mt-3" style="display:none;" data-bs-toggle="modal" data-bs-target="#modalGuardar">
    Guardar y Editar Transcripción
</button>

<!-- Modal para editar antes de guardar -->
<div class="modal fade" id="modalGuardar" tabindex="-1" aria-labelledby="modalGuardarLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form action="index.php?ruta=transcripcion/guardar" method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="modalGuardarLabel">Guardar Transcripción</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Título del audio</label>
            <input type="text" class="form-control" name="cTituloTrans" id="cTituloTrans" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" name="dFechaTrans" id="dFechaTrans" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Enlace del audio</label>
            <input type="text" class="form-control" name="cLinkTrans" id="cLinkTrans" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Texto transcrito</label>
            <textarea class="form-control" rows="10" name="tTrans" id="tTrans" required></textarea>
          </div>
          <input type="hidden" name="iIdAudio" id="iIdAudio">
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Guardar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

