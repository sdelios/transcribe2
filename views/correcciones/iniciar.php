<?php
$textoOriginal = $trans['tTrans'];
$idTrans = (int)$trans['iIdTrans'];

$tieneCorreccion = $ultimaCorreccion ? true : false;
$textoCorregido  = $tieneCorreccion ? ($ultimaCorreccion['texto_taquigrafico'] ?? '') : '';
$charsCorregido  = $tieneCorreccion ? (int)($ultimaCorreccion['chars_taquigrafica'] ?? 0) : 0;
$idCorreccion    = $tieneCorreccion ? (int)($ultimaCorreccion['id'] ?? 0) : 0;

$meta = $metadatosSesion ?? [];
$tipoSel  = $meta['iIdCatTipoSesiones'] ?? ($tiposSesion[0]['iIdCatTipoSesiones'] ?? '');
$sesionSel = $meta['iIdCatSesion'] ?? '';
$fechaSel  = $meta['dFechaSesion'] ?? '';

$presSel = $meta['iIdPresidente'] ?? '';
$sec1Sel = $meta['iIdSecretario1'] ?? '';
$sec2Sel = $meta['iIdSecretario2'] ?? '';

$asistSel = [];
if (!empty($meta['jAsistentes'])) {
    $tmp = json_decode($meta['jAsistentes'], true);
    if (is_array($tmp)) $asistSel = array_map('intval', $tmp);
}

$metaGuardada = $metaGuardada ?? false;
$metaToken    = $metaToken ?? '';
?>

<div class="table-container card-style mb-4">
    <div class="card-header-title">Revisión ortográfica</div>

    <div class="table-responsive" style="overflow-x: hidden;">
        <div class="card-body">

            <div class="card mb-3">
                <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
                    <span>Metadatos de sesión</span>
                    <span id="estadoMeta" class="badge <?= $metaGuardada ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= $metaGuardada ? 'Metadatos guardados' : 'Pendiente guardar' ?>
                    </span>
                </div>

                <div class="card-body">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de sesión</label>
                            <select id="iIdCatTipoSesiones" class="form-select">
                                <option value="">-- Selecciona --</option>
                                <?php foreach($tiposSesion as $t): ?>
                                    <option value="<?= (int)$t['iIdCatTipoSesiones'] ?>" <?= ((string)$tipoSel === (string)$t['iIdCatTipoSesiones']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['cCatTipoSesiones']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Sesión (filtrada por tipo)</label>
                            <select id="iIdCatSesion" class="form-select">
                                <option value="">-- Selecciona --</option>
                                <?php foreach($sesionesIniciales as $s): ?>
                                    <option value="<?= (int)$s['iIdCatSesion'] ?>" <?= ((string)$sesionSel === (string)$s['iIdCatSesion']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['cNombreCatSesion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Fecha de sesión</label>
                            <input type="date" id="dFechaSesion" class="form-control" value="<?= htmlspecialchars($fechaSel) ?>">
                        </div>
                    </div>

                    <!-- MESA -->
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white">Mesa Directiva</div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label class="form-label">Presidente</label>
                                    <select id="iIdPresidente" class="form-select">
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$presSel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Secretario 1</label>
                                    <select id="iIdSecretario1" class="form-select">
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$sec1Sel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Secretario 2</label>
                                    <select id="iIdSecretario2" class="form-select">
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$sec2Sel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                            <div class="form-text mt-2">
                                No se puede repetir la misma persona en Presidente/Secretarios ni como asistente.
                            </div>
                        </div>
                    </div>

                    <!-- ASISTENTES + INASISTENTES -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white d-flex align-items-center justify-content-between">
                            <span>Diputados</span>
                            <div class="d-flex gap-3 align-items-center">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="chkTodos">
                                    <label class="form-check-label" for="chkTodos">Seleccionar todos (asistentes)</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">

                            <div class="row g-3">
                                <div class="col-lg-7">
                                    <div class="fw-bold mb-2">Asistentes</div>
                                    <div id="asistentesWrap" class="d-flex flex-wrap gap-2"></div>
                                    <div class="form-text mt-2">
                                        Los de la Mesa Directiva no aparecen aquí.
                                    </div>
                                </div>

                                <div class="col-lg-5">
                                    <div class="fw-bold mb-2">Inasistencias (auto)</div>
                                    <div id="inasistentesWrap" class="d-flex flex-wrap gap-2"></div>
                                    <div class="form-text mt-2">
                                        Se calculan como: <em>Diputados activos - Mesa - Asistentes</em>.
                                        Se mandan a OpenAI para mencionarlos en la lista de asistencia.
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="jAsistentes" value="">
                            <input type="hidden" id="jInasistencias" value="[]">

                        </div>
                    </div>

                    <!-- Botón guardar metadatos -->
                    <div class="d-flex gap-2 mt-3">
                        <button id="btnGuardarMeta" class="btn btn-outline-dark">💾 Guardar metadatos</button>
                        <small class="text-muted align-self-center" id="metaHint">
                            Debes guardar metadatos antes de corregir.
                        </small>
                    </div>

                </div>
            </div>

            <!-- ORIGINAL -->
            <h3 class="text-black">Transcripción original</h3>

            <div class="mb-2 small text-muted">
                ID Transcripción: <strong><?= (int)$idTrans ?></strong> ·
                Caracteres: <strong id="charsOriginal"><?= mb_strlen($textoOriginal) ?></strong>
            </div>

            <textarea id="textoOriginal" class="form-control" rows="10"><?= htmlspecialchars($textoOriginal) ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 mt-3">
        <button id="btnCorregir" class="btn btn-warning" <?= $metaGuardada ? '' : 'disabled' ?>>
            <?= $tieneCorreccion ? '🔄 Revisar de nuevo' : '⚙️ Corregir con OpenAI' ?>
        </button>

        <a href="index.php?ruta=transcripcion/editar&id=<?= (int)$idTrans ?>" class="btn btn-secondary">
            ✏️ Ir a edición (manual)
        </a>
    </div>

    <!-- RESULTADO -->
    <div id="panelResultado" style="display: <?= $tieneCorreccion ? 'block' : 'none' ?>">
        <div class="card mb-3">
            <div class="card-header bg-success text-white">Resultado taquigráfico</div>
            <div class="card-body">

                <div class="mb-2 small text-muted">
                    Caracteres nuevo:
                    <strong id="charsNuevo"><?= (int)$charsCorregido ?></strong> ·
                    Diferencia:
                    <strong id="charsDiff">
                        <?php if ($tieneCorreccion && mb_strlen($textoOriginal) > 0): ?>
                            <?= round((($charsCorregido - mb_strlen($textoOriginal)) / mb_strlen($textoOriginal)) * 100, 2) ?>%
                        <?php else: ?>0%<?php endif; ?>
                    </strong>
                </div>

                <textarea id="textoNuevo" class="form-control" rows="12"><?= htmlspecialchars($textoCorregido) ?></textarea>

                <input type="hidden" id="idCorreccionActual" value="<?= $tieneCorreccion ? (int)$idCorreccion : '' ?>">

                <div class="d-flex gap-2 mt-3">
                    <button id="btnActualizarResultado" class="btn btn-primary" <?= $tieneCorreccion ? '' : 'disabled' ?>>
                        💾 Actualizar resultado
                    </button>
                    <small class="text-muted align-self-center">
                        Esto guarda el texto del cuadro (y quita INICIA/CONTINUARA automáticamente).
                    </small>
                </div>

            </div>
        </div>

        <div class="alert alert-info" id="msgGuardado" style="display: <?= $tieneCorreccion ? 'block' : 'none' ?>">
            <?php if ($tieneCorreccion): ?>
                Corrección cargada (ID <?= (int)$idCorreccion ?>). Puede actualizarla si lo desea.
            <?php endif; ?>
        </div>

        <!-- BOTÓN EXPORTAR A WORD -->
        <form method="POST" action="index.php?ruta=correccion/exportarWord" target="_blank" class="mt-3">
            <input type="hidden" name="texto" id="inputTextoWord">
            <input type="hidden" name="id" value="<?= (int)$idTrans ?>">
            <button type="submit" class="btn btn-success">📄 Exportar Word</button>
        </form>
    </div>
</div>

<script>
(function() {

    // ====== Estado meta ======
    let metaGuardada = <?= $metaGuardada ? 'true' : 'false' ?>;
    let metaToken = <?= json_encode($metaToken, JSON_UNESCAPED_UNICODE) ?>;

    const estadoMeta = document.getElementById('estadoMeta');
    const btnGuardarMeta = document.getElementById('btnGuardarMeta');
    const btnCorregir = document.getElementById('btnCorregir');
    const metaHint = document.getElementById('metaHint');

    function setMetaEstado(guardada) {
        metaGuardada = !!guardada;
        if (metaGuardada) {
            estadoMeta.className = 'badge bg-success';
            estadoMeta.textContent = 'Metadatos guardados';
            btnCorregir.disabled = false;
            metaHint.textContent = 'Listo: ya puedes corregir.';
        } else {
            estadoMeta.className = 'badge bg-warning text-dark';
            estadoMeta.textContent = 'Pendiente guardar';
            btnCorregir.disabled = true;
            metaHint.textContent = 'Debes guardar metadatos antes de corregir.';
        }
    }

    const textoOriginal = document.getElementById('textoOriginal');
    const charsOriginalSpan = document.getElementById('charsOriginal');

    const panelResultado = document.getElementById('panelResultado');
    const textoNuevo = document.getElementById('textoNuevo');
    const charsNuevoSpan = document.getElementById('charsNuevo');
    const charsDiffSpan = document.getElementById('charsDiff');
    const msgGuardado = document.getElementById('msgGuardado');

    const btnActualizarResultado = document.getElementById('btnActualizarResultado');

    const selTipo = document.getElementById('iIdCatTipoSesiones');
    const selSesion = document.getElementById('iIdCatSesion');
    const selPres = document.getElementById('iIdPresidente');
    const selSec1 = document.getElementById('iIdSecretario1');
    const selSec2 = document.getElementById('iIdSecretario2');

    const asistentesWrap = document.getElementById('asistentesWrap');
    const inasistentesWrap = document.getElementById('inasistentesWrap');

    const hidAsist = document.getElementById('jAsistentes');
    const hidInas  = document.getElementById('jInasistencias');

    const chkTodos = document.getElementById('chkTodos');

    const diputadosDB = <?= json_encode($diputadosDB, JSON_UNESCAPED_UNICODE) ?>;
    const nombresDiputados = <?= json_encode($diputados, JSON_UNESCAPED_UNICODE) ?>;
    const asistentesPre = new Set(<?= json_encode(array_values($asistSel), JSON_UNESCAPED_UNICODE) ?>);

    // ✅ Corrección: ID dinámico
    const idCorreccionInput = document.getElementById('idCorreccionActual');
    function getIdCorreccion() {
        return parseInt(idCorreccionInput?.value || '0', 10) || 0;
    }
    function setIdCorreccion(id) {
        if (idCorreccionInput) idCorreccionInput.value = String(id || '');
    }

    // contador original
    textoOriginal?.addEventListener('input', () => {
        charsOriginalSpan.textContent = textoOriginal.value.length;
    });

    // Tipo -> sesiones
    selTipo?.addEventListener('change', () => {
        setMetaEstado(false);

        const idTipo = selTipo.value;
        selSesion.innerHTML = `<option value="">-- Selecciona --</option>`;
        if (!idTipo) return;

        fetch(`index.php?ruta=correccion/sesionesPorTipo&idTipo=${encodeURIComponent(idTipo)}`)
            .then(r => r.json())
            .then(j => {
                if (!j.ok) throw new Error(j.error || 'Error cargando sesiones');
                (j.data || []).forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.iIdCatSesion;
                    opt.textContent = s.cNombreCatSesion;
                    selSesion.appendChild(opt);
                });
            })
            .catch(err => {
                console.error(err);
                alert('No se pudieron cargar las sesiones del tipo seleccionado.');
            });
    });

    // ===== utilidades =====
    function mesaIds() {
        const ids = [selPres.value, selSec1.value, selSec2.value]
            .map(v => parseInt(v, 10))
            .filter(n => Number.isInteger(n) && n > 0);
        return new Set(ids);
    }

    function getSetFromHidden(inputEl) {
        const raw = inputEl.value || '[]';
        try {
            const arr = JSON.parse(raw);
            return new Set(Array.isArray(arr) ? arr.map(n => parseInt(n,10)).filter(n => n>0) : []);
        } catch {
            return new Set();
        }
    }

    function setHiddenFromSet(inputEl, setIds) {
        const arr = Array.from(setIds).sort((a,b)=>a-b);
        inputEl.value = JSON.stringify(arr);
    }

    // ===== MESA anti-duplicado =====
    function syncMesaDisables() {
        setMetaEstado(false);

        const chosen = mesaIds();
        [selPres, selSec1, selSec2].forEach(sel => {
            const myVal = parseInt(sel.value, 10) || 0;
            Array.from(sel.options).forEach(opt => {
                const v = parseInt(opt.value, 10) || 0;
                if (!v) return;
                opt.disabled = (chosen.has(v) && v !== myVal);
            });
        });

        renderAsistentes();
        renderInasistentes();
    }

    // ===== asistentes =====
    function renderAsistentes() {
        const mesa = mesaIds();
        const sel = getSetFromHidden(hidAsist);

        mesa.forEach(id => sel.delete(id));
        setHiddenFromSet(hidAsist, sel);

        asistentesWrap.innerHTML = '';

        diputadosDB.forEach(d => {
            const id = parseInt(d.iIdUsuario, 10);
            if (!id) return;
            if (mesa.has(id)) return;

            const b = document.createElement('button');
            b.type = 'button';
            b.className = sel.has(id) ? 'btn btn-dark btn-sm' : 'btn btn-outline-secondary btn-sm';
            b.textContent = d.nombre;

            b.addEventListener('click', () => {
                setMetaEstado(false);

                const cur = getSetFromHidden(hidAsist);
                if (cur.has(id)) cur.delete(id);
                else cur.add(id);
                setHiddenFromSet(hidAsist, cur);

                renderAsistentes();
                renderInasistentes();
                syncChkTodos();
            });

            asistentesWrap.appendChild(b);
        });

        syncChkTodos();
    }

    // ===== inasistentes (auto) =====
    function computeInasistenciasIds() {
        const mesa = mesaIds();
        const asistentes = getSetFromHidden(hidAsist);

        const all = diputadosDB
            .map(d => parseInt(d.iIdUsuario,10))
            .filter(id => id > 0);

        const inas = all.filter(id => !mesa.has(id) && !asistentes.has(id));
        return new Set(inas);
    }

    function renderInasistentes() {
        const inas = computeInasistenciasIds();
        setHiddenFromSet(hidInas, inas);

        inasistentesWrap.innerHTML = '';
        const mapNombre = new Map(diputadosDB.map(d => [parseInt(d.iIdUsuario,10), d.nombre]));

        Array.from(inas).sort((a,b)=>a-b).forEach(id => {
            const s = document.createElement('span');
            s.className = 'badge text-bg-light border';
            s.textContent = mapNombre.get(id) || ('ID ' + id);
            inasistentesWrap.appendChild(s);
        });
    }

    function syncChkTodos() {
        const mesa = mesaIds();
        const visibles = diputadosDB
            .map(d => parseInt(d.iIdUsuario,10))
            .filter(id => id>0 && !mesa.has(id));

        const sel = getSetFromHidden(hidAsist);
        const allSelected = visibles.length > 0 && visibles.every(id => sel.has(id));
        chkTodos.checked = allSelected;
    }

    chkTodos?.addEventListener('change', () => {
        setMetaEstado(false);

        const mesa = mesaIds();
        const visibles = diputadosDB
            .map(d => parseInt(d.iIdUsuario,10))
            .filter(id => id>0 && !mesa.has(id));

        const sel = getSetFromHidden(hidAsist);
        if (chkTodos.checked) visibles.forEach(id => sel.add(id));
        else visibles.forEach(id => sel.delete(id));

        setHiddenFromSet(hidAsist, sel);
        renderAsistentes();
        renderInasistentes();
    });

    // init asistentes
    (function initAsist() {
        const mesa = mesaIds();
        const init = new Set();
        asistentesPre.forEach(id => {
            const n = parseInt(id,10);
            if (n>0 && !mesa.has(n)) init.add(n);
        });
        setHiddenFromSet(hidAsist, init);
        renderAsistentes();
        renderInasistentes();
    })();

    selPres?.addEventListener('change', syncMesaDisables);
    selSec1?.addEventListener('change', syncMesaDisables);
    selSec2?.addEventListener('change', syncMesaDisables);
    document.getElementById('dFechaSesion')?.addEventListener('change', () => setMetaEstado(false));
    selSesion?.addEventListener('change', () => setMetaEstado(false));
    syncMesaDisables();

    // Export Word sync
    const inputTextoWord = document.getElementById("inputTextoWord");
    function syncTextoWord() {
        if (textoNuevo && inputTextoWord) inputTextoWord.value = textoNuevo.value;
    }
    syncTextoWord();
    textoNuevo?.addEventListener("input", syncTextoWord);

    // Guardar metadatos
    btnGuardarMeta?.addEventListener('click', () => {
        const body = new URLSearchParams();
        body.append('id', <?= (int)$idTrans ?>);
        body.append('iIdCatTipoSesiones', selTipo?.value || '');
        body.append('iIdCatSesion', selSesion?.value || '');
        body.append('dFechaSesion', document.getElementById('dFechaSesion')?.value || '');
        body.append('iIdPresidente', selPres?.value || '');
        body.append('iIdSecretario1', selSec1?.value || '');
        body.append('iIdSecretario2', selSec2?.value || '');
        body.append('jAsistentes', hidAsist.value || '[]');

        btnGuardarMeta.disabled = true;
        btnGuardarMeta.textContent = 'Guardando...';

        fetch('index.php?ruta=correccion/guardarMetadatos', {
            method: 'POST',
            headers: {"Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"},
            body: body.toString()
        })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) {
                alert(j.error || 'No se pudo guardar');
                console.error(j);
                return;
            }
            metaToken = j.token || '';
            setMetaEstado(true);

            if (j.id_correccion) {
                setIdCorreccion(j.id_correccion);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error guardando metadatos');
        })
        .finally(() => {
            btnGuardarMeta.disabled = false;
            btnGuardarMeta.textContent = '💾 Guardar metadatos';
        });
    });

    setMetaEstado(metaGuardada);

    // Corregir
    btnCorregir?.addEventListener("click", () => {

        if (!metaGuardada || !metaToken) {
            alert("Primero guarda los metadatos (💾 Guardar metadatos).");
            return;
        }

        const texto = (textoOriginal?.value || '').trim();
        if (!texto) { alert("No hay texto para corregir."); return; }

        // ✅ recalcular inasistencias antes de mandar
        renderInasistentes();

        const body = new URLSearchParams();
        body.append("id", <?= (int)$idTrans ?>);
        body.append("texto", texto);
        body.append("nombres", JSON.stringify(nombresDiputados));
        body.append("correccion_id", getIdCorreccion() ? String(getIdCorreccion()) : '');
        body.append("meta_token", metaToken);

        // ✅ NUEVO: mandar inasistencias
        body.append("jInasistencias", hidInas.value || '[]');

        btnCorregir.disabled = true;
        btnCorregir.textContent = "Procesando…";

        fetch("index.php?ruta=correccion/procesar", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
            body: body.toString()
        })
        .then(async r => {
            const raw = await r.text();
            if (raw.trim().startsWith("<")) throw new Error("Backend devolvió HTML:\n" + raw.substring(0, 200));
            return JSON.parse(raw);
        })
        .then(data => {
            if (data.error) {
                alert("Error: " + data.error);
                console.error(data);
                return;
            }

            panelResultado.style.display = "block";
            textoNuevo.value = data.texto_taquigrafico;
            charsNuevoSpan.textContent = data.chars_taquigrafica;
            charsDiffSpan.textContent = data.diferencia_porcentual + "%";

            msgGuardado.style.display = "block";
            msgGuardado.textContent = "Guardado con éxito. ID corrección: " + data.id_correccion;

            setIdCorreccion(data.id_correccion);
            btnActualizarResultado.disabled = false;

            syncTextoWord();
        })
        .catch(err => {
            console.error("Error:", err);
            alert("Hubo un error. Revisa la consola.");
        })
        .finally(() => {
            btnCorregir.disabled = false;
            btnCorregir.textContent = <?= json_encode($tieneCorreccion ? "🔄 Revisar de nuevo" : "⚙️ Corregir con OpenAI") ?>;
        });

    });

    // ACTUALIZAR RESULTADO
    btnActualizarResultado?.addEventListener('click', () => {

        const idCorr = getIdCorreccion(); // puede ser 0, backend usará fallback
        const texto = textoNuevo?.value || '';

        if (!texto.trim()) {
            alert("El texto está vacío.");
            return;
        }

        const body = new URLSearchParams();
        body.append('id_correccion', String(idCorr || ''));
        body.append('id_transcripcion', String(<?= (int)$idTrans ?>));
        body.append('texto', texto);

        btnActualizarResultado.disabled = true;
        btnActualizarResultado.textContent = 'Actualizando...';

        fetch('index.php?ruta=correccion/actualizarResultado', {
            method: 'POST',
            headers: {"Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"},
            body: body.toString()
        })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) {
                console.error(j);
                alert(j.error || 'No se pudo actualizar');
                return;
            }

            textoNuevo.value = j.texto;
            charsNuevoSpan.textContent = j.chars;

            const origLen = (textoOriginal?.value || '').length || 1;
            const diff = ((j.chars - origLen) / origLen) * 100;
            charsDiffSpan.textContent = diff.toFixed(2) + "%";

            if (j.id_correccion) setIdCorreccion(j.id_correccion);

            msgGuardado.style.display = "block";
            msgGuardado.textContent = "Resultado actualizado ✅ (corrección ID " + (j.id_correccion || idCorr) + ").";

            syncTextoWord();
        })
        .catch(err => {
            console.error(err);
            alert('Error actualizando resultado');
        })
        .finally(() => {
            btnActualizarResultado.disabled = false;
            btnActualizarResultado.textContent = '💾 Actualizar resultado';
        });
    });

})();
</script>
