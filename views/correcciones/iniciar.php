<?php
$textoOriginal = $trans['tTrans'];
$idTrans = (int)$trans['iIdTrans'];
?>
<style>
/* ── Metadata card ───────────────────────────────────────────── */
.meta-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,.10);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.meta-card-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    padding: 1rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: .02em;
}
.meta-card-body {
    padding: 1.4rem;
    background: #f8f9fc;
}

/* ── Section panels (Mesa / Diputados) ───────────────────────── */
.meta-section {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 1rem;
}
.meta-section-header {
    background: linear-gradient(90deg, #2d3748 0%, #4a5568 100%);
    color: #fff;
    padding: .65rem 1.1rem;
    font-weight: 600;
    font-size: .92rem;
    letter-spacing: .03em;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.meta-section-body {
    padding: 1.1rem 1.1rem .9rem;
}

/* ── Form labels ──────────────────────────────────────────────── */
.meta-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6b7280;
    margin-bottom: .3rem;
    display: block;
}

/* ── Guardar button ───────────────────────────────────────────── */
.btn-guardar-meta {
    background: linear-gradient(135deg, #1a1a2e, #2d3748);
    color: #fff;
    border: none;
    padding: .55rem 1.4rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: .9rem;
    letter-spacing: .02em;
    transition: opacity .15s, box-shadow .15s;
    box-shadow: 0 2px 8px rgba(0,0,0,.20);
}
.btn-guardar-meta:hover { opacity: .88; box-shadow: 0 4px 14px rgba(0,0,0,.28); color:#fff; }
.btn-guardar-meta:disabled { opacity: .5; cursor: not-allowed; }

/* ── Status badge ─────────────────────────────────────────────── */
.meta-badge-ok      { background:#22c55e; color:#fff; padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:600; white-space:nowrap; }
.meta-badge-pending { background:#f59e0b; color:#1a1a2e; padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:600; white-space:nowrap; }

/* ── Toggle label color ───────────────────────────────────────── */
.meta-section-header .form-check-label { color: #fff; font-size: .82rem; }
.meta-section-header .form-check-input { cursor: pointer; }

.dip-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.35rem;
}
.dip-btn {
    font-size: 0.75rem;
    padding: 0.35rem 0.5rem;
    border-radius: 7px;
    text-align: center;
    border: 1.5px solid;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.13s, border-color 0.13s, color 0.13s, box-shadow 0.13s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    background: none;
    line-height: 1.3;
}
.dip-btn.v-off {
    border-color: #ced4da;
    color: #495057;
    background: #f8f9fa;
}
.dip-btn.v-off:hover {
    background: #e2e6ea;
    border-color: #868e96;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}
.dip-btn.v-on {
    border-color: #1a7340;
    background: #198754;
    color: #fff;
    box-shadow: 0 1px 4px rgba(25,135,84,.35);
}
.dip-btn.v-on:hover { background: #157347; border-color: #146c43; }
.dip-btn.a-off {
    border-color: #f5c2c7;
    color: #842029;
    background: #fff8f8;
}
.dip-btn.a-off:hover {
    background: #f8d7da;
    border-color: #dc3545;
    box-shadow: 0 1px 3px rgba(220,53,69,.2);
}
.dip-btn.a-on {
    border-color: #b02a37;
    background: #dc3545;
    color: #fff;
    box-shadow: 0 1px 4px rgba(220,53,69,.4);
}
.dip-btn.a-on:hover { background: #bb2d3b; border-color: #a52834; }
.dip-badge-inas {
    font-size: 0.73rem;
    padding: 0.3rem 0.45rem;
    border-radius: 6px;
    text-align: center;
    border: 1px solid #dee2e6;
    color: #6c757d;
    background: #f8f9fa;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Text sections ────────────────────────────────────────────── */
.txt-section {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
}
.txt-section-header {
    background: linear-gradient(90deg, #1e3a5f 0%, #1d4ed8 100%);
    color: #fff;
    padding: .7rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
    font-size: .92rem;
    letter-spacing: .02em;
}
.txt-result-header {
    background: linear-gradient(90deg, #065f46 0%, #059669 100%);
}
.txt-stat-badge {
    background: rgba(255,255,255,.18);
    color: #fff;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 500;
}
.txt-textarea {
    display: block;
    width: 100%;
    border: none;
    border-top: 1px solid #e5e7eb;
    padding: 1rem 1.1rem;
    font-family: 'Courier New', Courier, monospace;
    font-size: .82rem;
    line-height: 1.7;
    background: #fafafa;
    resize: vertical;
    color: #1f2937;
}
.txt-textarea:focus {
    outline: none;
    background: #fff;
    box-shadow: inset 0 0 0 2px rgba(29,78,216,.12);
}
.txt-textarea-result { background: #f0fdf4; }
.txt-textarea-result:focus { box-shadow: inset 0 0 0 2px rgba(5,150,105,.12); }
.txt-section-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .8rem 1.1rem;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}

/* ── Action buttons ───────────────────────────────────────────── */
.btn-corregir {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border: none;
    padding: .7rem 2.2rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 4px 14px rgba(245,158,11,.4);
    cursor: pointer;
    transition: opacity .15s, transform .1s, box-shadow .15s;
    letter-spacing: .01em;
}
.btn-corregir:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); box-shadow:0 6px 18px rgba(245,158,11,.45); }
.btn-corregir:disabled { opacity:.4; cursor:not-allowed; }

.btn-actualizar {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border: none;
    padding: .5rem 1.2rem;
    border-radius: 7px;
    font-weight: 600;
    font-size: .85rem;
    box-shadow: 0 2px 8px rgba(37,99,235,.3);
    cursor: pointer;
    transition: opacity .15s;
}
.btn-actualizar:hover:not(:disabled) { opacity:.88; }
.btn-actualizar:disabled { opacity:.4; cursor:not-allowed; }

.btn-exportar {
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: #fff;
    border: none;
    padding: .5rem 1.2rem;
    border-radius: 7px;
    font-weight: 600;
    font-size: .85rem;
    box-shadow: 0 2px 8px rgba(22,163,74,.3);
    cursor: pointer;
    transition: opacity .15s;
}
.btn-exportar:hover { opacity:.88; }

/* ── Bottom action bar ────────────────────────────────────────── */
.bottom-action-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .9rem 1.4rem;
    background: #f1f5f9;
    border-top: 2px solid #e2e8f0;
    border-radius: 0 0 14px 14px;
    margin-top: 1rem;
}
.btn-edicion {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: #fff;
    color: #374151;
    border: 2px solid #d1d5db;
    padding: .48rem 1.1rem;
    border-radius: 7px;
    font-weight: 600;
    font-size: .85rem;
    text-decoration: none;
    transition: border-color .15s, background .15s, color .15s;
}
.btn-edicion:hover { border-color: #6b7280; background: #f9fafb; color: #111827; }
</style>
<?php

$tieneCorreccion = $ultimaCorreccion ? true : false;
$textoCorregido  = $tieneCorreccion ? ($ultimaCorreccion['texto_taquigrafico'] ?? '') : '';
$charsCorregido  = $tieneCorreccion ? (int)($ultimaCorreccion['chars_taquigrafica'] ?? 0) : 0;
$idCorreccion    = $tieneCorreccion ? (int)($ultimaCorreccion['id'] ?? 0) : 0;

$meta = $metadatosSesion ?? [];
$tipoSel  = $meta['iIdCatTipoSesiones'] ?? ($tiposSesion[0]['iIdCatTipoSesiones'] ?? '');
$sesionSel = $meta['iIdCatSesion'] ?? '';
$fechaSel  = $meta['dFechaSesion'] ?? '';

$presSel = $meta['iIdPresidente'] ?? '';
$vpSel   = $meta['iIdVicepresidente'] ?? '';
$sec1Sel = $meta['iIdSecretario1'] ?? '';
$sec2Sel = $meta['iIdSecretario2'] ?? '';

$asistSel = [];
if (!empty($meta['jAsistentes'])) {
    $tmp = json_decode($meta['jAsistentes'], true);
    if (is_array($tmp)) $asistSel = array_map('intval', $tmp);
}

$metaGuardada = $metaGuardada ?? false;
$metaToken    = $metaToken ?? '';

// Bloqueo de campos: si viene con valor de BD → no se puede editar
$lockTipo   = ($metadatosSesion !== null) && !empty($meta['iIdCatTipoSesiones']);
$lockSesion = ($metadatosSesion !== null) && !empty($meta['iIdCatSesion']);
$lockFecha  = ($metadatosSesion !== null) && !empty($meta['dFechaSesion']);
$lockPres   = ($metadatosSesion !== null) && !empty($meta['iIdPresidente']);
$lockVP     = ($metadatosSesion !== null) && !empty($meta['iIdVicepresidente']);
$lockSec1   = ($metadatosSesion !== null) && !empty($meta['iIdSecretario1']);
$lockSec2   = ($metadatosSesion !== null) && !empty($meta['iIdSecretario2']);
?>

<div class="table-container card-style mb-4">
    <div class="card-header-title">Revisión ortográfica</div>

    <div class="table-responsive" style="overflow-x: hidden;">
        <div class="card-body">

            <!-- ═══ METADATOS DE SESIÓN ═══ -->
            <div class="meta-card mb-4">

                <div class="meta-card-header">
                    <span>🗂 Metadatos de sesión</span>
                    <span id="estadoMeta" class="<?= $metaGuardada ? 'meta-badge-ok' : 'meta-badge-pending' ?>">
                        <?= $metaGuardada ? '✓ Metadatos guardados' : '⏳ Pendiente guardar' ?>
                    </span>
                </div>

                <div class="meta-card-body">

                    <!-- Fila: tipo + sesión + fecha -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="meta-label">Tipo de sesión <?= $lockTipo ? '🔒' : '' ?></label>
                            <select id="iIdCatTipoSesiones" class="form-select form-select-sm" <?= $lockTipo ? 'disabled title="Valor registrado en BD"' : '' ?>>
                                <option value="">— Selecciona —</option>
                                <?php foreach($tiposSesion as $t): ?>
                                    <option value="<?= (int)$t['iIdCatTipoSesiones'] ?>" <?= ((string)$tipoSel === (string)$t['iIdCatTipoSesiones']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['cCatTipoSesiones']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="meta-label">Sesión (filtrada por tipo) <?= $lockSesion ? '🔒' : '' ?></label>
                            <select id="iIdCatSesion" class="form-select form-select-sm" <?= $lockSesion ? 'disabled title="Valor registrado en BD"' : '' ?>>
                                <option value="">— Selecciona —</option>
                                <?php foreach($sesionesIniciales as $s): ?>
                                    <option value="<?= (int)$s['iIdCatSesion'] ?>" <?= ((string)$sesionSel === (string)$s['iIdCatSesion']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['cNombreCatSesion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="meta-label">Fecha de sesión <?= $lockFecha ? '🔒' : '' ?></label>
                            <input type="date" id="dFechaSesion" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($fechaSel) ?>"
                                   <?= $lockFecha ? 'disabled title="Valor registrado en BD"' : '' ?>>
                        </div>
                    </div>

                    <!-- ── MESA DIRECTIVA ── -->
                    <div class="meta-section mb-3">
                        <div class="meta-section-header">
                            <span>⚖️ &nbsp;Mesa Directiva</span>
                        </div>
                        <div class="meta-section-body">
                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label class="meta-label">Presidente <?= $lockPres ? '🔒' : '' ?></label>
                                    <select id="iIdPresidente" class="form-select form-select-sm" <?= $lockPres ? 'disabled title="Valor registrado en BD"' : '' ?>>
                                        <option value="">— Selecciona —</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$presSel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="meta-label">Secretario 1 <?= $lockSec1 ? '🔒' : '' ?></label>
                                    <select id="iIdSecretario1" class="form-select form-select-sm" <?= $lockSec1 ? 'disabled title="Valor registrado en BD"' : '' ?>>
                                        <option value="">— Selecciona —</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$sec1Sel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="meta-label">Secretario 2 <?= $lockSec2 ? '🔒' : '' ?></label>
                                    <select id="iIdSecretario2" class="form-select form-select-sm" <?= $lockSec2 ? 'disabled title="Valor registrado en BD"' : '' ?>>
                                        <option value="">— Selecciona —</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$sec2Sel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4" id="vpCol" style="display:none">
                                    <label class="meta-label">Vicepresidente <?= $lockVP ? '🔒' : '' ?></label>
                                    <select id="iIdVicepresidente" class="form-select form-select-sm" <?= $lockVP ? 'disabled title="Valor registrado en BD"' : '' ?>>
                                        <option value="">— Selecciona —</option>
                                        <?php foreach($diputadosDB as $d): ?>
                                            <option value="<?= (int)$d['iIdUsuario'] ?>" <?= ((string)$vpSel === (string)$d['iIdUsuario']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                            <div class="mt-2" style="font-size:.78rem;color:#9ca3af;">
                                ⚠ No se puede repetir la misma persona en la Mesa Directiva ni como asistente.
                            </div>
                        </div>
                    </div>

                    <!-- ── DIPUTADOS ── -->
                    <div class="meta-section">
                        <div class="meta-section-header">
                            <span>👥 &nbsp;Diputados</span>
                            <div class="d-flex gap-3 align-items-center">
                                <div class="form-check form-switch m-0" id="inasToggleWrap" style="display:none">
                                    <input class="form-check-input" type="checkbox" id="chkInasNoPlen">
                                    <label class="form-check-label" for="chkInasNoPlen">Registrar ausentes justificados</label>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="chkTodos">
                                    <label class="form-check-label" for="chkTodos">Seleccionar todos (vocales)</label>
                                </div>
                            </div>
                        </div>
                        <div class="meta-section-body">

                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="meta-label mb-2" id="asistentesLabel">Asistentes</div>
                                    <div id="asistentesWrap" class="dip-grid"></div>
                                    <div class="mt-2" style="font-size:.78rem;color:#9ca3af;" id="asistentesDesc">
                                        Los de la Mesa Directiva no aparecen aquí.
                                    </div>
                                </div>

                                <div class="col-lg-6" id="inasistentesCol">
                                    <div class="meta-label mb-2" id="inasistentesColHeader">Inasistencias (auto)</div>
                                    <div id="inasistentesWrap" class="dip-grid"></div>
                                    <div class="mt-2" style="font-size:.78rem;color:#9ca3af;" id="inasistentesDesc">
                                        Se calculan como: <em>Diputados activos − Mesa − Asistentes</em>.
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="jAsistentes" value="">
                            <input type="hidden" id="jInasistencias" value="[]">

                        </div>
                    </div>

                    <!-- Botón guardar metadatos -->
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <button id="btnGuardarMeta" class="btn-guardar-meta">💾 Guardar metadatos</button>
                        <small class="text-muted" id="metaHint">Debes guardar metadatos antes de corregir.</small>
                    </div>

                </div>
            </div>

            <!-- ORIGINAL -->
            <div class="txt-section">
                <div class="txt-section-header">
                    <span>📝 Transcripción original</span>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="txt-stat-badge">ID: <?= (int)$idTrans ?></span>
                        <span class="txt-stat-badge"><strong id="charsOriginal"><?= mb_strlen($textoOriginal) ?></strong>&nbsp;caracteres</span>
                    </div>
                </div>
                <textarea id="textoOriginal" class="txt-textarea" rows="10"><?= htmlspecialchars($textoOriginal) ?></textarea>
            </div>
        </div>
    </div>

    <!-- BOTÓN CORREGIR -->
    <div class="d-flex justify-content-center my-4 px-3">
        <button id="btnCorregir" class="btn-corregir" <?= $metaGuardada ? '' : 'disabled' ?>>
            <?= $tieneCorreccion ? '🔄 Revisar de nuevo' : '⚙️ Corregir con IA' ?>
        </button>
    </div>

    <!-- RESULTADO -->
    <div id="panelResultado" style="display: <?= $tieneCorreccion ? 'block' : 'none' ?>;" class="px-3 pb-2">
        <div class="txt-section mb-3">
            <div class="txt-section-header txt-result-header">
                <span>✅ Resultado taquigráfico</span>
                <div class="d-flex gap-2 align-items-center">
                    <span class="txt-stat-badge"><strong id="charsNuevo"><?= (int)$charsCorregido ?></strong>&nbsp;caracteres</span>
                    <span class="txt-stat-badge">Δ&nbsp;<strong id="charsDiff"><?php if ($tieneCorreccion && mb_strlen($textoOriginal) > 0): ?><?= round((($charsCorregido - mb_strlen($textoOriginal)) / mb_strlen($textoOriginal)) * 100, 2) ?>%<?php else: ?>0%<?php endif; ?></strong></span>
                </div>
            </div>
            <textarea id="textoNuevo" class="txt-textarea txt-textarea-result" rows="14"><?= htmlspecialchars($textoCorregido) ?></textarea>
            <input type="hidden" id="idCorreccionActual" value="<?= $tieneCorreccion ? (int)$idCorreccion : '' ?>">
            <div class="txt-section-actions">
                <button id="btnActualizarResultado" class="btn-actualizar" <?= $tieneCorreccion ? '' : 'disabled' ?>>
                    💾 Actualizar resultado
                </button>
                <small class="text-muted">Guarda el texto y elimina marcas INICIA/CONTINUARA automáticamente.</small>
            </div>
        </div>

        <div class="alert alert-success border-0 py-2 mb-2" id="msgGuardado" style="display: <?= $tieneCorreccion ? 'block' : 'none' ?>">
            <?php if ($tieneCorreccion): ?>
                ✅ Corrección cargada (ID <?= (int)$idCorreccion ?>). Puede actualizarla si lo desea.
            <?php endif; ?>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="bottom-action-bar">
        <form method="POST" action="index.php?ruta=correccion/exportarWord" target="_blank" class="m-0">
            <input type="hidden" name="texto" id="inputTextoWord">
            <input type="hidden" name="id" value="<?= (int)$idTrans ?>">
            <button type="submit" class="btn-exportar">📄 Exportar a Word</button>
        </form>
        <div class="vr"></div>
        <a href="index.php?ruta=transcripcion/editar&id=<?= (int)$idTrans ?>" class="btn-edicion">
            ✏️ Regresar a edición manual
        </a>
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
            estadoMeta.className = 'meta-badge-ok';
            estadoMeta.textContent = '✓ Metadatos guardados';
            btnCorregir.disabled = false;
            metaHint.textContent = 'Listo: ya puedes corregir.';
        } else {
            estadoMeta.className = 'meta-badge-pending';
            estadoMeta.textContent = '⏳ Pendiente guardar';
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

    const tiposModalidad = <?= json_encode(
        array_column($tiposSesion, 'cModalidadSesion', 'iIdCatTipoSesiones'),
        JSON_UNESCAPED_UNICODE
    ) ?>;

    const selVP = document.getElementById('iIdVicepresidente');
    const vpCol = document.getElementById('vpCol');
    const inasistentesCol    = document.getElementById('inasistentesCol');
    const inasToggleWrap     = document.getElementById('inasToggleWrap');
    const chkInasNoPlen      = document.getElementById('chkInasNoPlen');
    const inasistentesHeader = document.getElementById('inasistentesColHeader');
    const inasistentesDescEl = document.getElementById('inasistentesDesc');
    const asistentesLabel = document.getElementById('asistentesLabel');
    const asistentesDesc  = document.getElementById('asistentesDesc');
    const chkTodosLabel   = document.querySelector('label[for="chkTodos"]');

    function getModalidad() {
        const id = parseInt(selTipo?.value, 10);
        return tiposModalidad[id] || 'pleno';
    }

    function updateMesaUI() {
        const mod = getModalidad();
        const esPleno = mod === 'pleno';

        vpCol.style.display = esPleno ? 'none' : '';
        if (esPleno && selVP && !selVP.disabled) selVP.value = '';

        if (esPleno) {
            inasToggleWrap.style.display = 'none';
            chkInasNoPlen.checked = false;
            inasistentesCol.style.display = '';
            if (inasistentesHeader) inasistentesHeader.textContent = 'Inasistencias (auto)';
            if (inasistentesDescEl) inasistentesDescEl.innerHTML =
                'Se calculan como: <em>Diputados activos - Mesa - Asistentes</em>. Se mandan a la IA para mencionarlos en la lista de asistencia.';
        } else {
            inasToggleWrap.style.display = '';
            inasistentesCol.style.display = chkInasNoPlen.checked ? '' : 'none';
            if (inasistentesHeader) inasistentesHeader.textContent = 'Ausentes justificados';
            if (inasistentesDescEl) inasistentesDescEl.textContent =
                'Selecciona quiénes faltaron con justificación. No pueden coincidir con vocales/asistentes.';
        }

        if (asistentesLabel) {
            asistentesLabel.textContent = mod === 'comision' ? 'Vocales' : 'Asistentes';
        }
        if (asistentesDesc) {
            asistentesDesc.textContent = esPleno
                ? 'Los de la Mesa Directiva no aparecen aquí.'
                : 'Selecciona quiénes participaron en esta sesión. Los no seleccionados NO se marcan como inasistentes.';
        }
        if (chkTodosLabel) {
            chkTodosLabel.textContent = mod === 'comision'
                ? 'Seleccionar todos (vocales)'
                : 'Seleccionar todos (asistentes)';
        }

        syncMesaDisables();
    }

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

    // Tipo -> sesiones + actualizar UI de mesa
    selTipo?.addEventListener('change', () => {
        setMetaEstado(false);
        updateMesaUI();

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
        const vals = [selPres.value, selSec1.value, selSec2.value];
        if (getModalidad() !== 'pleno' && selVP) vals.push(selVP.value);
        return new Set(vals.map(v => parseInt(v, 10)).filter(n => Number.isInteger(n) && n > 0));
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
        const selects = [selPres, selSec1, selSec2];
        if (getModalidad() !== 'pleno' && selVP) selects.push(selVP);

        selects.forEach(sel => {
            if (!sel) return;
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
            b.className = 'dip-btn ' + (sel.has(id) ? 'v-on' : 'v-off');
            b.title = d.nombre;
            b.textContent = d.nombre;

            b.addEventListener('click', () => {
                setMetaEstado(false);

                const cur = getSetFromHidden(hidAsist);
                if (cur.has(id)) cur.delete(id);
                else {
                    cur.add(id);
                    // Si estaba en inasistentes (no-pleno), quitarlo de ahí
                    if (getModalidad() !== 'pleno') {
                        const curInas = getSetFromHidden(hidInas);
                        if (curInas.has(id)) { curInas.delete(id); setHiddenFromSet(hidInas, curInas); }
                    }
                }
                setHiddenFromSet(hidAsist, cur);

                renderAsistentes();
                renderInasistentes();
                syncChkTodos();
            });

            asistentesWrap.appendChild(b);
        });

        syncChkTodos();
    }

    // ===== inasistentes =====
    function renderInasistentes() {
        const wrap = document.getElementById('inasistentesWrap');
        const mod  = getModalidad();

        if (mod !== 'pleno') {
            // No-pleno: botones manuales, solo si el toggle está activo
            if (!chkInasNoPlen?.checked) {
                setHiddenFromSet(hidInas, new Set());
                if (wrap) wrap.innerHTML = '';
                return;
            }

            const mesa       = mesaIds();
            const asistentes = getSetFromHidden(hidAsist);
            const inas       = getSetFromHidden(hidInas);

            // Limpiar inas si alguien ya está en mesa o asistentes
            let dirty = false;
            inas.forEach(id => {
                if (mesa.has(id) || asistentes.has(id)) { inas.delete(id); dirty = true; }
            });
            if (dirty) setHiddenFromSet(hidInas, inas);

            if (!wrap) return;
            wrap.innerHTML = '';

            diputadosDB.forEach(d => {
                const id = parseInt(d.iIdUsuario, 10);
                if (!id || mesa.has(id) || asistentes.has(id)) return;

                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'dip-btn ' + (inas.has(id) ? 'a-on' : 'a-off');
                b.title = d.nombre;
                b.textContent = d.nombre;
                b.addEventListener('click', () => {
                    setMetaEstado(false);
                    const cur = getSetFromHidden(hidInas);
                    if (cur.has(id)) cur.delete(id); else cur.add(id);
                    setHiddenFromSet(hidInas, cur);
                    renderInasistentes();
                });
                wrap.appendChild(b);
            });
            return;
        }

        // Pleno: auto-calculado como badges de solo lectura
        const mesa       = mesaIds();
        const asistentes = getSetFromHidden(hidAsist);
        const all  = diputadosDB.map(d => parseInt(d.iIdUsuario,10)).filter(id => id > 0);
        const inas = new Set(all.filter(id => !mesa.has(id) && !asistentes.has(id)));

        setHiddenFromSet(hidInas, inas);
        if (!wrap) return;
        wrap.innerHTML = '';
        const mapNombre = new Map(diputadosDB.map(d => [parseInt(d.iIdUsuario,10), d.nombre]));
        Array.from(inas).sort((a,b)=>a-b).forEach(id => {
            const s = document.createElement('span');
            s.className = 'dip-badge-inas';
            s.title = mapNombre.get(id) || ('ID ' + id);
            s.textContent = mapNombre.get(id) || ('ID ' + id);
            wrap.appendChild(s);
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
    selVP?.addEventListener('change', syncMesaDisables);
    selSec1?.addEventListener('change', syncMesaDisables);
    selSec2?.addEventListener('change', syncMesaDisables);
    document.getElementById('dFechaSesion')?.addEventListener('change', () => setMetaEstado(false));
    selSesion?.addEventListener('change', () => setMetaEstado(false));

    chkInasNoPlen?.addEventListener('change', () => {
        setMetaEstado(false);
        inasistentesCol.style.display = chkInasNoPlen.checked ? '' : 'none';
        if (!chkInasNoPlen.checked) setHiddenFromSet(hidInas, new Set());
        renderInasistentes();
    });

    updateMesaUI();

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
        body.append('iIdVicepresidente', selVP?.value || '');
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
            btnCorregir.textContent = <?= json_encode($tieneCorreccion ? "🔄 Revisar de nuevo" : "⚙️ Corregir con IA") ?>;
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
