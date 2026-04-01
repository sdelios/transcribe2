<h3 class="text-center text-white mb-3">¿Cómo vamos a transcribir hoy?</h3>

<!-- Selector animado (link / file) -->
<div class="transcribe-toggle mx-auto" id="modeToggle" data-mode="link" style="width:520px;max-width:90vw;">
  <div class="pill"></div>
  <button type="button" class="btn-link">Desde un enlace</button>
  <button type="button" class="btn-file">Desde un archivo de audio</button>
</div>

<style>
/* Toggle */
.transcribe-toggle{background:rgba(255,255,255,.06) !important;border:1px solid rgba(255,255,255,.15);border-radius:999px;display:flex;position:relative;backdrop-filter:blur(4px)}
.transcribe-toggle button{flex:1;padding:12px 14px;background:transparent;color:#ddd;font-weight:600;border:0;cursor:pointer;position:relative;z-index:2}
.transcribe-toggle .pill{position:absolute;top:4px;bottom:4px;width:calc(50% - 6px);left:4px;border-radius:999px;background:#e63946;transition:transform .25s ease;z-index:1;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.transcribe-toggle[data-mode="file"] .pill{transform:translateX(100%)}
.transcribe-toggle[data-mode="link"] .btn-link,.transcribe-toggle[data-mode="file"] .btn-file{color:#fff !important;}

/* Tarjetas de formulario */
.card-transcribe{max-width:1150px;margin:18px auto;padding:22px;border-radius:18px;background:rgba(0, 0, 0, 0.06);border:1px solid rgba(255,255,255,.15);box-shadow:0 5px 15px rgba(0,0,0,.25)}
.card-title{font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:16px;}
.input-underline{background:transparent;border:0;border-bottom:2px solid rgba(255,255,255,.2);color:#000;padding:8px 10px;width:100%;outline:none}
.input-underline:focus{border-bottom-color:#e63946}
.dropzone{border:2px dashed rgba(0, 0, 0, 0.49);border-radius:14px;padding:28px;text-align:center;color:#000;transition:border-color .2s ease,background .2s ease}
.dropzone.dragover{border-color:#e63946;background:rgba(255, 253, 253, 0.45)}
.hidden{display:none!important}
.btn-guinda{background:#e63946;border:0;color:#fff;font-weight:700;padding:10px 18px;border-radius:10px;cursor:pointer}
.btn-guinda:disabled{opacity:.6;cursor:not-allowed}
.file-preview{margin-top:12px;display:flex;justify-content:center;}
.file-chip{display:inline-flex;align-items:center;gap:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px 14px;box-shadow:0 6px 16px rgba(0,0,0,.08)}
.file-icon{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:8px;background:#1f2937;color:#fff;font-weight:900;letter-spacing:.5px;font-size:.85rem}
.file-name{color:#111;font-weight:700}

/* Tarjeta de progreso */
.progress-card{max-width:1150px;margin:18px auto;border-radius:16px;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,.15);overflow:hidden}
.progress-card-header{display:flex;align-items:center;gap:12px;padding:12px 16px;background:#111;color:#fff;font-weight:800}
.progress-card-body{padding:16px 16px 20px}
.dots-spinner{width:20px;height:20px;margin-left:6px;border:4px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin 1.1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.progress-rail{height:22px;border-radius:12px;background:#f1f1f1;overflow:hidden;box-shadow:inset 0 1px 3px rgba(0,0,0,.08)}
.progress-fill{height:100%;width:0%;border-radius:12px;background:linear-gradient(90deg,#ffc1c7,#e84b59);box-shadow:inset 0 0 12px rgba(0,0,0,.15);transition:width .25s ease,background .25s ease}

/* util */
.d-none{display:none!important}
</style>

<!-- TARJETA: Enlace -->
<div class="table-container card-style mb-4">
  <div class="card-transcribe" id="cardLink">
    <div class="card-title">Transcribir desde un enlace</div>
    <form id="formulario" method="post" class="mt-2">
      <div class="row g-2 justify-content-center">
        <div class="col-md-5">
          <input type="text" class="input-underline" id="url" name="url" placeholder="Pega la URL del video" required>
        </div>
        <div class="col-md-4">
          <input type="text" class="input-underline" id="nombre" name="nombre" placeholder="Nombre del audio" required>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn-guinda">Transcribir</button>
        </div>
      </div>
    </form>
  </div>

  <!-- TARJETA: Archivo -->
  <div class="card-transcribe hidden" id="cardFile">
    <div class="card-title">Transcribir desde un archivo de audio</div>
    <form id="form-file" method="post" enctype="multipart/form-data" class="mt-2">
      <div class="row g-3">
        <div class="col-12">
          <div class="dropzone text-center p-4 border rounded" id="dropzone">
            Arrastra tu archivo de audio aquí o
            <label for="audioFile" style="text-decoration:underline; cursor:pointer;">haz clic para examinar</label>.
            <input type="file" id="audioFile" name="audio" accept="audio/*" class="d-none">
            <div id="fileInfo" class="mt-2 text-muted small"></div>

            <!-- PREVIEW BONITO -->
            <div id="filePreview" class="file-preview d-none">
              <div class="file-chip">
                <span class="file-icon">MP3</span>
                <span class="file-name" id="fileName">nombre.mp3</span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-8">
          <input type="text" class="input-underline" id="nombre_archivo" name="nombre_archivo" placeholder="Nombre del audio" required>
        </div>
        <div class="col-md-4 d-grid">
          <button class="btn-guinda" id="btnFile">Transcribir</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- PROGRESO EN TARJETA -->
<div id="progressCard" class="progress-card d-none">
  <div class="progress-card-header">
    <span id="progressTitle">Procesando, por favor espere...</span>
    <span id="spinner" class="dots-spinner" aria-hidden="true"></span>
    <span class="ms-auto">Tiempo: <span id="cronometro">00:00</span></span>
  </div>
  <div class="progress-card-body">
    <div class="progress-rail">
      <div id="bar" class="progress-fill"></div>
    </div>
  </div>
</div>

<!-- Resultado -->
<div id="output" class="mt-4"></div>

<!-- Botón para abrir modal -->
<button type="button" id="btnModalGuardar" class="btn btn-success mt-3" style="display:none;" data-bs-toggle="modal" data-bs-target="#modalGuardar">
  Guardar y Editar Transcripción
</button>

<!-- Modal para editar antes de guardar -->
<div class="modal fade" id="modalGuardar" tabindex="-1" aria-labelledby="modalGuardarLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form action="index.php?ruta=transcripcion/guardar" method="post">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title">Guardar Transcripción</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
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
          <input type="hidden" name="iIdModeloTrans" value="2">
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Guardar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ----- Toggle (link/file)
const toggle = document.getElementById('modeToggle');
const cardLink = document.getElementById('cardLink');
const cardFile = document.getElementById('cardFile');
toggle.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-file')) {
    toggle.dataset.mode = 'file';
    cardLink.classList.add('hidden');
    cardFile.classList.remove('hidden');
  } else if (e.target.classList.contains('btn-link')) {
    toggle.dataset.mode = 'link';
    cardFile.classList.add('hidden');
    cardLink.classList.remove('hidden');
  }
});

// ----- Drag & Drop + preview (una sola vez)
const dz          = document.getElementById('dropzone');
const inputFile   = document.getElementById('audioFile');
const fileInfo    = document.getElementById('fileInfo');
const filePreview = document.getElementById('filePreview');
const fileNameEl  = document.getElementById('fileName');

['dragenter','dragover'].forEach(ev =>
  dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); })
);
['dragleave','drop'].forEach(ev =>
  dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); })
);
dz.addEventListener('drop', e => {
  const f = e.dataTransfer.files?.[0];
  if (f) { inputFile.files = e.dataTransfer.files; showPreview(f); }
});
dz.addEventListener('click', () => inputFile.click());
inputFile.addEventListener('change', () => {
  const f = inputFile.files?.[0]; if (f) showPreview(f);
});
function showPreview(f){
  fileInfo.textContent   = `Seleccionado: ${f.name}`;
  fileNameEl.textContent = f.name;
  filePreview.classList.remove('d-none');
}

// ====== PROGRESO (común para ambos flujos) ======
let ivCrono=null, seg=0, ivBar=null, pSim=2;

function arrancarUI(){
  // mostrar tarjeta
  document.getElementById('progressCard').classList.remove('d-none');
  document.getElementById('progressTitle').textContent = 'Procesando, por favor espere...';
  document.getElementById('spinner').style.display = 'inline-block';

  // reiniciar crono
  seg=0;
  document.getElementById('cronometro').textContent='00:00';
  if (ivCrono) clearInterval(ivCrono);
  ivCrono = setInterval(() => {
    seg++;
    document.getElementById('cronometro').textContent = new Date(seg*1000).toISOString().substr(14,5);
  }, 1000);

  // reiniciar barra + avance simulado
  setBar(2);
  pSim = 2;
  if (ivBar) clearInterval(ivBar);
  ivBar = setInterval(() => {
    // sube lento, no más de 92% hasta que termine
    if (pSim < 92) {
      pSim += Math.random() * 1.2; // 0 - 1.2%
      setBar(pSim);
    }
  }, 500);
}

function setBar(p){
  const bar = document.getElementById('bar');
  if (!bar) return;
  const pct = Math.max(0, Math.min(100, p));
  bar.style.width = pct + '%';
  // Gradiente más oscuro según % (hsl con menos luminosidad)
  const lum = Math.max(30, 75 - pct*0.45); // 75% -> 30%
  const dark = `hsl(355, 85%, ${lum}%)`;
  bar.style.background = `linear-gradient(90deg, #ffc1c7, ${dark})`;
}

function finalizarUI(ok){
  clearInterval(ivCrono);
  clearInterval(ivBar);
  setBar(ok ? 100 : 0);

  // Encabezado final
  const title   = document.getElementById('progressTitle');
  const spinner = document.getElementById('spinner');
  spinner.style.display = 'none';
  title.textContent = ok
    ? `Tiempo total de transcripción: ${document.getElementById('cronometro').textContent}`
    : 'Error en el proceso';

      // Ocultar tarjeta de progreso 2s después (ya en 100%)
  setTimeout(() => {
    const card = document.getElementById('progressCard');
    card.classList.add('d-none');
  }, 2000);

}

// ====== Submit ENLACE ======
document.getElementById('formulario').addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = new URLSearchParams(new FormData(e.target));

  try {
    arrancarUI();
    const r = await fetch('index.php?ruta=proceso/transcribir', { method:'POST', body });
    const text = await r.text();
    let j; try { j = JSON.parse(text); } catch(_){ throw new Error('La respuesta no es JSON:\n'+text.substring(0,200)); }
    if (!j.ok) throw new Error(j.error || 'Error desconocido');

    // Pintar resultado y popular modal
    const textoPlano = (j.transcripcion || '').trim();
    output.innerHTML = `
      <div class="card mt-3">
        <div class="card-header bg-dark text-white fw-bold">
          Tiempo total de transcripción: ${document.getElementById('cronometro').textContent}
        </div>
        <div class="card-body text-justify">
          <p>${textoPlano.replace(/\n/g, '<br>')}</p>
        </div>
      </div>
    `;
    document.getElementById("btnModalGuardar").style.display = "inline-block";
    document.getElementById("cTituloTrans").value = j.titulo || (document.getElementById('nombre').value || '');
    document.getElementById("dFechaTrans").value = new Date().toISOString().split('T')[0];
    document.getElementById("cLinkTrans").value = j.ruta || (document.getElementById('url').value || '');
    document.getElementById("tTrans").value = textoPlano;
    document.getElementById("iIdAudio").value = j.id_audio || '';

    finalizarUI(true);
  } catch (err) {
    finalizarUI(false);
    output.innerHTML = `<div class="alert alert-danger"><strong>Ocurrió un problema:</strong><br>${String(err).replaceAll('<','&lt;')}</div>`;
  }
});

// ====== Submit ARCHIVO ======
const formFile = document.getElementById('form-file');
formFile?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btnFile'); btn.disabled = true;

  try {
    arrancarUI();
    const fd = new FormData(formFile);
    const r  = await fetch('index.php?ruta=proceso/procesarArchivo', { method:'POST', body: fd });
    const tx = await r.text();
    let j; try { j = JSON.parse(tx); } catch(_){ throw new Error('La respuesta no es JSON:\n'+tx.substring(0,200)); }
    if (!j.ok) throw new Error(j.error || 'Error desconocido');

    const textoPlano = (j.transcripcion || '').trim();
    output.innerHTML = `
      <div class="card mt-3">
        <div class="card-header bg-dark text-white fw-bold">
          Tiempo total de transcripción: ${document.getElementById('cronometro').textContent}
        </div>
        <div class="card-body text-justify">
          <p>${textoPlano.replace(/\n/g, '<br>')}</p>
        </div>
      </div>
    `;
    document.getElementById("btnModalGuardar").style.display = "inline-block";
    document.getElementById("cTituloTrans").value = j.titulo || (document.getElementById('nombre_archivo').value || '');
    document.getElementById("dFechaTrans").value = new Date().toISOString().split('T')[0];
    document.getElementById("cLinkTrans").value = j.ruta || 'Carga de archivo de audio';
    document.getElementById("tTrans").value = textoPlano;
    document.getElementById("iIdAudio").value = j.id_audio || '';

    finalizarUI(true);
  } catch (err) {
    finalizarUI(false);
    output.innerHTML = `<div class="alert alert-danger"><strong>Ocurrió un problema:</strong><br>${String(err).replaceAll('<','&lt;')}</div>`;
  } finally {
    btn.disabled = false;
  }
});
</script>
