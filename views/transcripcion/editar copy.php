<script>
function copiarTexto() {
    const textarea = document.querySelector('textarea[name="tTrans"]');
    if (textarea) {
        textarea.select();
        textarea.setSelectionRange(0, 99999); // Para móviles
        document.execCommand("copy");
        alert("Texto copiado al portapapeles.");
    }
}
</script>
<div class="table-container card-style mb-4">
    <div class="card-header-title">Editar Transcripción</div>
    <div class="table-responsive">
<!-- <h3 class="text-center text-white mb-4">Editar Transcripción</h3> -->

<form action="index.php?ruta=transcripcion/actualizar" method="post" class="mt-4" onsubmit="return false;">
    <input type="hidden" name="iIdTrans" value="<?= $transcripcion['iIdTrans'] ?>">

    <div class="row mb-3">
        <div class="col-md-9">
            <span class="tag-title tag-danger">Título:</span>
            <!-- <label class="form-label text-white">Título:</label> -->
            <input type="text" name="cTituloTrans" class="form-control" value="<?= htmlspecialchars($transcripcion['cTituloTrans']) ?>" required>
        </div>
        <div class="col-md-3">
            <span class="tag-title tag-danger">Fecha:</span>
            <!-- <label class="form-label text-white">Fecha:</label> -->
            <input type="date" name="dFechaTrans" class="form-control" value="<?= $transcripcion['dFechaTrans'] ?>" required>
        </div>
    </div>

    <div class="mb-3">
        <span class="tag-title tag-danger">Link del video:</span>
        <!-- <label class="form-label text-white">Link del video:</label> -->
        <input type="text" class="form-control" value="<?= htmlspecialchars($transcripcion['cLinkTrans']) ?>" disabled>
        <input type="hidden" name="cLinkTrans" value="<?= htmlspecialchars($transcripcion['cLinkTrans']) ?>">
    </div>

    <?php
    $conn = new mysqli("localhost", "root", "", "transcriptor");
    $stmt = $conn->prepare("SELECT cRuta FROM audios WHERE iIdAudio = ?");
    $stmt->bind_param("i", $transcripcion['iIdAudio']);
    $stmt->execute();
    $res = $stmt->get_result();
    $audio = $res->fetch_assoc();
    $stmt->close();
    ?>
    <?php if ($audio && file_exists($audio['cRuta'])): ?>
        <div class="row mb-4 align-items-center">
            <div class="col-md-9">
                <span class="tag-title tag-info">Audio:</span>
                <!-- <label class="form-label text-white">Audio:</label> -->
                <audio id="audioPlayer" controls style="width: 100%;">
                    <source src="<?= $audio['cRuta'] ?>" type="audio/mpeg">
                    Tu navegador no soporta la reproducción de audio.
                </audio>
            </div>
            <div class="col-md-3">
                <span class="tag-title tag-success">Velocidad:</span>
                <!-- <label class="form-label text-white">Velocidad de reproducción:</label> -->
                <select id="velocidadAudio" class="form-control audio-style-select">
                    <option value="0.5">0.5x</option>
                    <option value="0.75">0.75x</option>
                    <option value="1" selected>1x</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2">2x</option>
                </select>
            </div>
        </div>

        <script>
            const audio = document.getElementById("audioPlayer");
            const velocidad = document.getElementById("velocidadAudio");
            if (audio && velocidad) {
                velocidad.addEventListener("change", () => {
                    audio.playbackRate = parseFloat(velocidad.value);
                });
            }
        </script>
    <?php endif; ?>

    <div class="mb-4">
        <label class="form-label text-white">Texto de la transcripción:</label>
        <textarea name="tTrans" rows="10" class="form-control" required><?= htmlspecialchars($transcripcion['tTrans']) ?></textarea>
    </div>

    <!-- Cuadro de sugerencias -->
    <div id="cuadro-ortografia" class="mb-3 p-3 rounded" style="display: none; background-color: white;">
        <div id="barra-contador" class="fw-bold mb-3" style="color: #8b0022;"></div>
        <hr class="my-3">
        <div class="alert p-3" style="background-color: #f8e0e6; color: #8b0022;">
            <strong>Sugerencias encontradas:</strong>
            <ul class="mt-2" id="lista-sugerencias"></ul>
        </div>
    </div>

    <button type="submit" onclick="this.form.submit();" class="btn btn-danger">Guardar Cambios</button>
    <button type="button" class="btn btn-warning" onclick="revisarOrtografia()">Revisar ortografía</button>
    <button type="button" class="btn btn-success" onclick="copiarTexto()">Copiar Texto</button>
    <a href="index.php?ruta=transcripcion/lista" class="btn btn-secondary ms-2">Cancelar</a>
</form>
    </div>
</div>
<!-- Script de revisión -->
<script>
let erroresTotales = 0;
let erroresRechazados = 0;
let erroresCorregidos = 0;
let puntoTimer;

function actualizarContador() {
    const pendientes = erroresTotales - erroresRechazados - erroresCorregidos;
    const barra = document.getElementById("barra-contador");

    if (barra) {
        barra.innerHTML = `
            Errores detectados: <strong>${erroresTotales}</strong> |
            <span class="text-success">Corregidos:</span> ${erroresCorregidos} |
            <span class="text-muted">Rechazados:</span> ${erroresRechazados} |
            <span class="text-warning">Pendientes:</span> ${pendientes}
        `;
    }
}

function revisarOrtografia() {
    const textarea = document.querySelector('textarea[name="tTrans"]');
    const texto = textarea.value;
    const cuadro = document.getElementById("cuadro-ortografia");
    

    erroresTotales = 0;
    erroresRechazados = 0;
    erroresCorregidos = 0;

    cuadro.style.display = "block";
    document.getElementById("barra-contador").innerHTML =
        `<strong style="color:#003366;">Revisando ortografía<span id="puntos">.</span></strong>`;
    document.getElementById("lista-sugerencias").innerHTML = "";

    let puntos = 1;
    clearInterval(puntoTimer);
    puntoTimer = setInterval(() => {
        const el = document.getElementById("puntos");
        if (el) {
            puntos = (puntos % 3) + 1;
            el.textContent = '.'.repeat(puntos);
        }
    }, 400);

    fetch('index.php?ruta=transcripcion/revisarTexto', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ texto: texto })
    })
    .then(res => res.text())
    .then(text => {
        clearInterval(puntoTimer);
        const data = JSON.parse(text);

        if (data.error) {
            document.getElementById("barra-contador").innerHTML = `<div class="text-danger">${data.error}</div>`;
            return;
        }

        erroresTotales = data.length;

        if (erroresTotales === 0) {
            document.getElementById("barra-contador").innerHTML = `<div class="text-success">No se encontraron errores ortográficos.</div>`;
            return;
        }

        actualizarContador();

        const lista = document.getElementById("lista-sugerencias");
        data.forEach((e, index) => {
            const sugerencia = e.reemplazos.length > 0 ? e.reemplazos[0] : null;
            const idFila = `sugerencia-${index}`;

            const item = document.createElement("li");
            item.classList.add("mb-3");
            item.id = idFila;

            item.innerHTML = `
                <span class="fw-bold">"${e.texto}"</span>: ${e.mensaje}
                <div class="mt-1">
                    ${sugerencia ? `
                    <button type="button" class="btn btn-sm btn-outline-primary me-2"
                        onclick="reemplazarTexto(${e.offset}, ${e.length}, '${sugerencia.replace(/'/g, "\\'")}', '${idFila}')">
                        Reemplazar por: <strong>${sugerencia}</strong>
                    </button>` : ''}
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="rechazarSugerencia('${idFila}')">Rechazar</button>
                </div>
            `;
            lista.appendChild(item);
        });
    })
    .catch(err => {
        clearInterval(puntoTimer);
        console.error(err);
        document.getElementById("barra-contador").innerHTML = '<div class="text-danger">Error al revisar el texto.</div>';
    });
}

function reemplazarTexto(offset, length, nuevo, idFila) {
    const textarea = document.querySelector('textarea[name="tTrans"]');
    const texto = textarea.value;

    const antes = texto.slice(0, offset);
    const despues = texto.slice(offset + length);
    textarea.value = antes + nuevo + despues;

    const fila = document.getElementById(idFila);
    if (fila) {
        fila.remove();
        erroresCorregidos++;
        actualizarContador();
    }

    alert(`Se reemplazó por: "${nuevo}"`);
}

function rechazarSugerencia(idFila) {
    const fila = document.getElementById(idFila);
    if (fila) {
        fila.remove();
        erroresRechazados++;
        actualizarContador();
    }
}
</script>

