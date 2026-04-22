<?php
// =====================================================
//  editar.php (vista Transcripciones) - versión ajustada
//  Cambios:
//   - OCULTA botón "Generar Acta" (amarillo)
//   - OCULTA botón "Copiar Texto" (verde)
//   - "Generar Acta (nuevo flujo)" SIEMPRE visible, pero
//     DESHABILITADO hasta que exista corrección en tabla correcciones
//     (correcciones.transcripcion_id = transcripciones.iIdTrans)
// =====================================================

// ==== Detectar si ya existe Acta / Corrección para esta transcripción ====
$idTrans = 0;
// Toma el id desde un input oculto, variable de controlador o la URL (?id=123)
if (isset($iIdTrans)) { $idTrans = (int)$iIdTrans; }
elseif (isset($_GET['id'])) { $idTrans = (int)$_GET['id']; }
elseif (!empty($_POST['iIdTrans'])) { $idTrans = (int)$_POST['iIdTrans']; }

$tieneActa = false;

// ---- Corrección (para habilitar Acta nuevo flujo) ----
$tieneCorreccion = false;
$idCorreccion = 0;

if ($idTrans > 0) {
    $mysqli = new mysqli('localhost', 'root', '', 'transcriptor');
    if (!$mysqli->connect_error) {
        $mysqli->set_charset('utf8mb4');

        // Acta (si la usas en algún lugar)
        $stmt = $mysqli->prepare('SELECT LENGTH(tActaHtml) FROM transcripciones WHERE iIdTrans = ?');
        $stmt->bind_param('i', $idTrans);
        $stmt->execute();
        $stmt->bind_result($len);
        if ($stmt->fetch() && (int)$len > 0) { $tieneActa = true; }
        $stmt->close();

        // ✅ Corrección (FK real: transcripcion_id)
        $stmt2 = $mysqli->prepare('SELECT id FROM correcciones WHERE transcripcion_id = ? ORDER BY id DESC LIMIT 1');
        $stmt2->bind_param('i', $idTrans);
        $stmt2->execute();
        $stmt2->bind_result($idCorr);
        if ($stmt2->fetch() && (int)$idCorr > 0) {
            $tieneCorreccion = true;
            $idCorreccion = (int)$idCorr;
        }
        $stmt2->close();

        $mysqli->close();
    }
}
// ==== Fin detecciones ====
?>

<style>
.btn-guardar {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color:#fff; border:none; padding:.55rem 1.4rem;
    border-radius:8px; font-weight:700; font-size:.9rem;
    box-shadow:0 3px 10px rgba(220,38,38,.35); cursor:pointer;
    transition:opacity .15s; font-family:'Lato',sans-serif; text-decoration:none; display:inline-flex; align-items:center;
}
.btn-guardar:hover { opacity:.88; color:#fff; }

.btn-revisar {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color:#fff; border:none; padding:.55rem 1.4rem;
    border-radius:8px; font-weight:700; font-size:.9rem;
    box-shadow:0 3px 10px rgba(37,99,235,.35); cursor:pointer;
    transition:opacity .15s; font-family:'Lato',sans-serif; text-decoration:none; display:inline-flex; align-items:center;
}
.btn-revisar:hover { opacity:.88; color:#fff; }

.btn-generar-acta {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color:#fff; border:none; padding:.55rem 1.4rem;
    border-radius:8px; font-weight:700; font-size:.9rem;
    box-shadow:0 3px 10px rgba(245,158,11,.35); cursor:pointer;
    transition:opacity .15s; font-family:'Lato',sans-serif; text-decoration:none; display:inline-flex; align-items:center;
}
.btn-generar-acta:hover:not(.disabled) { opacity:.88; color:#fff; }
.btn-generar-acta.disabled { opacity:.45; pointer-events:none; color:#fff; }

.btn-cancelar {
    background:#fff; color:#374151; border:2px solid #d1d5db;
    padding:.48rem 1.2rem; border-radius:8px;
    font-weight:600; font-size:.9rem;
    transition:border-color .15s,background .15s,color .15s;
    font-family:'Lato',sans-serif; text-decoration:none; display:inline-flex; align-items:center;
}
.btn-cancelar:hover { border-color:#6b7280; background:#f9fafb; color:#111827; }

/* Lato for all form inputs in this view */
.form-control, .form-select, textarea.form-control {
    font-family: 'Lato', sans-serif !important;
}
</style>

<div class="table-container card-style mb-4">
    <div class="card-header-title">Editar Transcripción</div>
    <div class="table-responsive" style="overflow-x: hidden;">

        <form action="index.php?ruta=transcripcion/actualizar" method="post" class="mt-4" onsubmit="return false;">
            <input type="hidden" name="iIdTrans" value="<?= $transcripcion['iIdTrans'] ?>">
            <input type="hidden" name="iIdModeloTrans" value="<?= $transcripcion['iIdModeloTrans'] ?>">
            <input type="hidden" name="cLinkTrans" value="<?= htmlspecialchars($transcripcion['cLinkTrans']) ?>">

            <!-- Fila: Título y Fecha -->
            <div class="row mb-3">
                <div class="col-md-8 d-flex align-items-center">
                    <span class="tag-title tag-danger me-2 mb-0">Título:</span>
                    <input type="text" name="cTituloTrans" class="form-control underline input-danger flex-grow-1"
                           value="<?= htmlspecialchars($transcripcion['cTituloTrans']) ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <span class="tag-title tag-danger me-2 mb-0">Fecha:</span>
                    <input type="date" name="dFechaTrans" class="form-control underline input-danger flex-grow-1"
                           value="<?= $transcripcion['dFechaTrans'] ?>" required>
                </div>
            </div>

            <!-- Fila: Link -->
            <div class="row mb-3">
                <div class="col-md-12 d-flex align-items-center">
                    <span class="tag-title tag-danger me-2 mb-0">Link:</span>
                    <input type="text" class="form-control flex-grow-1"
                           value="<?= htmlspecialchars($transcripcion['cLinkTrans']) ?>" disabled>
                </div>
            </div>

            <!-- Fila: Audio + Velocidad en una sola línea -->
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
                <div class="row mb-4">
                    <div class="col-md-8 d-flex align-items-center">
                        <span class="tag-title tag-info me-2 mb-0">Audio:</span>
                        <audio id="audioPlayer" controls class="flex-grow-1">
                            <source src="<?= $audio['cRuta'] ?>" type="audio/mpeg">
                            Tu navegador no soporta la reproducción de audio.
                        </audio>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <span class="tag-title tag-success me-2 mb-0">Velocidad:</span>
                        <select id="velocidadAudio" class="form-control audio-style-select underline input-success flex-grow-1">
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
                    (function () {
                        const audio = document.getElementById("audioPlayer");
                        const velocidad = document.getElementById("velocidadAudio");
                        if (audio && velocidad) {
                            velocidad.addEventListener("change", () => {
                                audio.playbackRate = parseFloat(velocidad.value);
                            });
                        }
                    })();
                </script>
            <?php endif; ?>

            <!-- Campo de transcripción -->
            <div class="mb-4">
                <label class="form-label text-white">Texto de la transcripción:</label>
                <textarea name="tTrans" rows="10" class="form-control" required><?= htmlspecialchars($transcripcion['tTrans']) ?></textarea>
            </div>

            <!-- Cuadro de sugerencias (si lo usas en otro flujo, lo dejamos intacto) -->
            <div id="cuadro-ortografia" class="mb-3 p-3 rounded" style="display: none; background-color: white;">
                <div id="barra-contador" class="fw-bold mb-3" style="color: #8b0022;"></div>
                <hr class="my-3">
                <div class="alert p-3" style="background-color: #f8e0e6; color: #8b0022;">
                    <strong>Sugerencias encontradas:</strong>
                    <ul class="mt-2" id="lista-sugerencias"></ul>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="submit" onclick="this.form.submit();" class="btn-guardar">Guardar Cambios</button>

                <a class="btn-revisar"
                   href="index.php?ruta=correccion/iniciar&id=<?= intval($transcripcion['iIdTrans']) ?>">
                    Revisar ortografía
                </a>

                <?php
                $hrefActaNueva = "index.php?ruta=actanueva/iniciar&id=" . intval($transcripcion['iIdTrans']);
                ?>

                <?php if ($tieneCorreccion): ?>
                    <a href="<?= $hrefActaNueva ?>" class="btn-generar-acta">
                        📝 Generar Acta (nuevo flujo)
                    </a>
                <?php else: ?>
                    <a href="#"
                       class="btn-generar-acta disabled"
                       aria-disabled="true"
                       title="Primero realiza la revisión ortográfica para habilitar este botón."
                       onclick="return false;">
                        📝 Generar Acta (nuevo flujo)
                    </a>
                <?php endif; ?>

                <a href="index.php?ruta=transcripcion/lista" class="btn-cancelar">Cancelar</a>
            </div>

        </form>

    </div>
</div>
