/** acta_nueva/iniciar.js */

(function () {
  "use strict";

  const BOOT = window.ACTA_NUEVA_BOOT || {};
  let actaId = (BOOT.actaId === null || BOOT.actaId === undefined) ? null : BOOT.actaId;

  const correccionId = Number(BOOT.correccionId || 0);
  const transcripcionId = Number(BOOT.transcripcionId || 0);

  // defaults del modal (mesa directiva) desde PHP
  const META_DEFAULTS = BOOT.metaDefaults || {};

  // Cache local de estados (para no depender 100% del fetch)
  let UI_META_OK_OVERRIDE = null;  // true/false/null
  let UI_HAS_ENC_OVERRIDE = null;  // true/false/null

  document.addEventListener("DOMContentLoaded", function () {

    // =========================
    // ELEMENTOS PRINCIPALES
    // =========================
    const txtFuente      = document.getElementById('textoFuente');
    const charsOrigen    = document.getElementById('charsOrigen');
    const btnActa        = document.getElementById('btnGenerarActa');

    const panelActa      = document.getElementById('panelActa');
    const txtActa        = document.getElementById('textoActa');
    const charsActaSpan  = document.getElementById('charsActa');
    const diffActaSpan   = document.getElementById('diffActa');
    const msgActa        = document.getElementById('msgActa');

    const chkDebug       = document.getElementById('chkDebug');

    // Debug chunks ACTA
    const panelDebug     = document.getElementById('panelDebug');
    const debugInfo      = document.getElementById('debugInfo');
    const debugOutput    = document.getElementById('debugOutput');
    const debugSelect    = document.getElementById('debugSelect');
    const debugLen       = document.getElementById('debugLen');
    const btnPrevChunk   = document.getElementById('btnPrevChunk');
    const btnNextChunk   = document.getElementById('btnNextChunk');
    const btnCopyChunk   = document.getElementById('btnCopyChunk');

    // Botones TOP
    const btnMeta        = document.getElementById('btnMeta');
    const btnToggleEnc   = document.getElementById('btnToggleEncabezado');
    const btnWord        = document.getElementById('btnWord');
    const btnSintesisTop = document.getElementById('btnGenerarSintesis');
    const btnDebugSintesisTop = document.getElementById('btnDebugSintesisChunks');

    // Modal metadata
    const modalMetaEl    = document.getElementById('modalMeta');
    const metaMsg        = document.getElementById('metaMsg');
    const btnGuardarMeta = document.getElementById('btnGuardarMeta');
    const modalMeta = (modalMetaEl && typeof bootstrap !== "undefined")
      ? new bootstrap.Modal(modalMetaEl)
      : null;

    // Encabezado panel
    const panelEncAI     = document.getElementById('panelEncabezadoAI');
    const btnHideEnc     = document.getElementById('btnHideEncabezado');
    const btnRegenEnc    = document.getElementById('btnRegenerarEncabezado');
    const txtEncAI       = document.getElementById('txtEncabezadoAI');
    const txtParAI       = document.getElementById('txtPrimerParrafoAI');

    // Síntesis panel
    const panelSintesis  = document.getElementById('panelSintesis');
    const txtSintesis    = document.getElementById('textoSintesis');
    const charsSintesis  = document.getElementById('charsSintesis');

    // Acciones síntesis (dentro del panel)
    const panelSintesisActions = document.getElementById('panelSintesisActions');
    const btnRegenSintesis = document.getElementById('btnRegenerarSintesis');
    const btnDebugSintesisBottom = document.getElementById('btnDebugSintesisChunks2');
    const btnWordSintesis = document.getElementById('btnWordSintesis');

    // Debug chunks SÍNTESIS
    const panelDebugS  = document.getElementById('panelDebugSintesis');
    const debugInfoS   = document.getElementById('debugSintesisInfo');
    const debugSelectS = document.getElementById('debugSelectS');
    const debugLenS    = document.getElementById('debugLenS');
    const debugOutputS = document.getElementById('debugOutputS');
    const btnPrevS     = document.getElementById('btnPrevChunkS');
    const btnNextS     = document.getElementById('btnNextChunkS');
    const btnCopyS     = document.getElementById('btnCopyChunkS');

    // =========================
    // HELPERS
    // =========================
    if (txtFuente && charsOrigen) {
      txtFuente.addEventListener('input', () => {
        charsOrigen.textContent = txtFuente.value.length;
      });
    }

    function setMetaMsg(txt, ok=true){
      if (!metaMsg) return;
      metaMsg.style.display = 'block';
      metaMsg.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger') + ' mt-3 mb-0 small';
      metaMsg.textContent = txt;
    }

    async function safeFetchJson(url, options = {}) {
      const res = await fetch(url, options);
      const text = await res.text();
      try {
        const json = JSON.parse(text);
        return { ok: true, status: res.status, json, raw: text };
      } catch (e) {
        return { ok: false, status: res.status, json: null, raw: text };
      }
    }

    function showEncPanel(show){
      if (!panelEncAI) return;
      panelEncAI.style.display = show ? "block" : "none";
    }

    function setEncButtonLabel(hasEnc){
      if (!btnToggleEnc) return;
      btnToggleEnc.textContent = hasEnc ? "👁 Ver Encabezado" : "🤖 Generar Encabezado";
    }

    function setDisplay(el, show){
      if (!el) return;
      el.style.display = show ? "" : "none";
    }

    function setEnabled(el, enabled){
      if (!el) return;
      el.disabled = !enabled;
    }

    function setLinkEnabled(a, enabled){
      if (!a) return;
      a.classList.toggle('disabled', !enabled);
      a.setAttribute('aria-disabled', enabled ? 'false' : 'true');
      if (!enabled) a.setAttribute('tabindex','-1');
      else a.removeAttribute('tabindex');
    }

    function hasText(v){
      return (v || '').toString().trim() !== '';
    }

    function pick(d, keys){
      for (const k of keys) {
        const v = d?.[k];
        if (hasText(v)) return v;
      }
      return '';
    }

    function updateWordLinks(){
      if (actaId) {
        if (btnWord) btnWord.href = `index.php?ruta=actanueva/descargarWord&acta_id=${actaId}`;
        if (btnWordSintesis) btnWordSintesis.href = `index.php?ruta=actanueva/descargarWordSintesis&acta_id=${actaId}`;
      }
    }

    // ============ UI por estados ============
    function applyUIState({ hasActa, metaOK, hasEnc, hasSintesis }) {
      // Panel acta
      setDisplay(panelActa, !!hasActa);

      // 1) Datos del acta
      setDisplay(btnMeta, !!hasActa);
      setEnabled(btnMeta, !!hasActa);

      // 2) Encabezado (solo con metadata OK)
      setDisplay(btnToggleEnc, !!hasActa && !!metaOK);
      setEnabled(btnToggleEnc, !!hasActa && !!metaOK);
      setEncButtonLabel(!!hasEnc);

      // 3) Word acta (solo con encabezado)
      setDisplay(btnWord, !!hasActa && !!hasEnc);
      setLinkEnabled(btnWord, !!hasActa && !!hasEnc);

      // 4) Síntesis top (solo con encabezado y si no existe síntesis)
      const showSintTop = !!hasActa && !!hasEnc && !hasSintesis;
      setDisplay(btnSintesisTop, showSintTop);
      setEnabled(btnSintesisTop, showSintTop);

      setDisplay(btnDebugSintesisTop, showSintTop);
      setEnabled(btnDebugSintesisTop, showSintTop);

      // Panel síntesis y acciones
      setDisplay(panelSintesis, !!hasActa && !!hasSintesis);
      setDisplay(panelSintesisActions, !!hasActa && !!hasSintesis);
      setEnabled(btnRegenSintesis, !!hasActa && !!hasSintesis);
      setEnabled(btnDebugSintesisBottom, !!hasActa && !!hasSintesis);
      setLinkEnabled(btnWordSintesis, !!hasActa && !!hasSintesis);

      // Encabezado panel NO auto-mostrar
    }

    // ============ Determinar estado desde BD ============
    async function evaluateAndApplyState(){
      const hasActa = !!actaId;

      let metaOK = false;
      let hasEnc = false;

      // base: síntesis por texto
      const hasSintesis = hasText(txtSintesis?.value);

      if (hasActa) {
        const resp = await safeFetchJson(`index.php?ruta=actanueva/obtenerMetadata&acta_id=${actaId}`);
        const d = (resp.ok && resp.json && resp.json.data) ? resp.json.data : null;

        if (d) {
          // Encabezado (acepta variantes por si cambiaste nombres en backend)
          const enc = pick(d, ['encabezado_ai','encabezadoAI','cEncabezadoAI','encabezado']);
          const par = pick(d, ['primer_parrafo_ai','primerParrafoAI','cPrimerParrafoAI','primer_parrafo']);
          hasEnc = hasText(enc) || hasText(par);

          // Precargar textareas
          if (txtEncAI) txtEncAI.value = (enc || '').trim();
          if (txtParAI) txtParAI.value = (par || '').trim();

          // Metadata completo (acepta variantes)
          const req = [
            ['clave_acta','clave','cClaveActa'],
            ['id_legislatura'],
            ['id_periodo'],
            ['id_ejercicio'],
            ['fecha','dFecha'],
            ['hora_inicio','horaInicio','cHoraInicio'],
            ['ciudad','cCiudad'],
            ['recinto','cRecinto'],
            ['presidente','iIdPresidente'],
            ['secretaria_1','secretaria1','iIdSecretaria1'],
          ];

          metaOK = req.every(keys => hasText(pick(d, keys)));
        }
      }

      // Overrides
      if (UI_META_OK_OVERRIDE !== null) metaOK = UI_META_OK_OVERRIDE;
      if (UI_HAS_ENC_OVERRIDE !== null) hasEnc = UI_HAS_ENC_OVERRIDE;

      updateWordLinks();
      applyUIState({ hasActa, metaOK, hasEnc, hasSintesis });

      return { hasActa, metaOK, hasEnc, hasSintesis };
    }

    // ============ ESTADO INICIAL ============
    evaluateAndApplyState();

    // =========================
    // BLOQUEO DE DUPLICADOS (selects)
    // =========================
    function enforceUniqueDiputados() {
      const selPres = document.getElementById('m_presidente');
      const selS1   = document.getElementById('m_secretaria_1');
      const selS2   = document.getElementById('m_secretaria_2');
      if (!selPres || !selS1 || !selS2) return;

      const selects = [selPres, selS1, selS2];
      const chosen = new Set(selects.map(s => s.value).filter(v => v));

      selects.forEach(sel => {
        Array.from(sel.options).forEach(opt => {
          if (!opt.value) return;
          const isChosenSomewhere = chosen.has(opt.value);
          const isChosenHere = sel.value === opt.value;
          opt.disabled = isChosenSomewhere && !isChosenHere;
        });
      });
    }

    document.addEventListener('change', (e) => {
      if (['m_presidente','m_secretaria_1','m_secretaria_2'].includes(e.target.id)) {
        enforceUniqueDiputados();
      }
    });

    // =========================
    // DEBUG CHUNKS SÍNTESIS (sin OpenAI)
    // =========================
    function renderDebugSintesis(data) {
      const chunks = data?.chunks || [];
      if (!panelDebugS || !debugSelectS || !debugOutputS) return;

      panelDebugS.style.display = "block";

      if (debugInfoS) {
        debugInfoS.textContent =
          `Total: ${data.chars_total} chars · Chunks: ${data.total_chunks} · target=${data.target} · window=${data.window} · overlap=${data.overlap}`;
      }

      debugSelectS.innerHTML = "";
      chunks.forEach((ch, idx) => {
        const opt = document.createElement('option');
        opt.value = idx;
        opt.textContent = `Chunk ${ch.n} (${ch.len})`;
        debugSelectS.appendChild(opt);
      });

      function show(i) {
        i = Math.max(0, Math.min(i, chunks.length - 1));
        debugSelectS.value = String(i);
        const ch = chunks[i];

        if (debugLenS) debugLenS.textContent = `Longitud: ${ch.len} caracteres`;
        debugOutputS.value = ch.texto || "";

        if (btnPrevS) btnPrevS.disabled = (i === 0);
        if (btnNextS) btnNextS.disabled = (i === chunks.length - 1);
      }

      debugSelectS.onchange = () => show(parseInt(debugSelectS.value, 10));
      if (btnPrevS) btnPrevS.onclick = () => show(parseInt(debugSelectS.value, 10) - 1);
      if (btnNextS) btnNextS.onclick = () => show(parseInt(debugSelectS.value, 10) + 1);

      if (btnCopyS) {
        btnCopyS.onclick = async () => {
          try {
            await navigator.clipboard.writeText(debugOutputS.value);
            const old = btnCopyS.textContent;
            btnCopyS.textContent = "✅ Copiado";
            setTimeout(() => btnCopyS.textContent = old, 1200);
          } catch (e) {
            alert("No se pudo copiar. Revisa permisos del navegador.");
          }
        };
      }

      show(0);
    }

    async function runDebugSintesisChunks(){
      if (!actaId) return alert("Primero genera el acta.");

      const target  = 10000;
      const window  = 600;
      const overlap = 0;

      const url = `index.php?ruta=actanueva/debugSintesisChunks&acta_id=${actaId}&target=${target}&window=${window}&overlap=${overlap}`;
      const resp = await safeFetchJson(url);

      if (!resp.ok) {
        console.error("debugSintesisChunks NO devolvió JSON:", resp.raw);
        alert("El servidor no devolvió JSON. Revisa consola (F12).");
        return;
      }

      const j = resp.json;
      if (!j.ok) {
        alert(j.error || "Error en debug.");
        return;
      }

      renderDebugSintesis(j);
    }

    btnDebugSintesisTop?.addEventListener('click', runDebugSintesisChunks);
    btnDebugSintesisBottom?.addEventListener('click', runDebugSintesisChunks);

    // =========================
    // DEBUG CHUNKS ACTA UI
    // =========================
    function renderDebugChunks(data) {
      const chunks = data.chunks || [];
      if (!panelDebug) return;

      panelDebug.style.display = "block";
      if (debugInfo) debugInfo.textContent = `Segmentos detectados: ${data.segmentos} · Chunks generados: ${data.total_chunks}`;

      if (debugSelect) debugSelect.innerHTML = "";

      chunks.forEach((ch, idx) => {
        const opt = document.createElement('option');
        opt.value = idx;
        opt.textContent = `Chunk ${ch.n}`;
        debugSelect.appendChild(opt);
      });

      function mostrarChunk(idx) {
        idx = Math.max(0, Math.min(idx, chunks.length - 1));
        debugSelect.value = String(idx);
        const ch = chunks[idx];
        if (!ch) return;

        if (debugLen) debugLen.textContent = `Longitud: ${ch.len} caracteres`;
        if (debugOutput) debugOutput.value = ch.texto || "";

        if (btnPrevChunk) btnPrevChunk.disabled = idx === 0;
        if (btnNextChunk) btnNextChunk.disabled = idx === chunks.length - 1;
      }

      if (debugSelect) debugSelect.onchange = () => mostrarChunk(parseInt(debugSelect.value, 10));
      if (btnPrevChunk) btnPrevChunk.onclick = () => mostrarChunk(parseInt(debugSelect.value, 10) - 1);
      if (btnNextChunk) btnNextChunk.onclick = () => mostrarChunk(parseInt(debugSelect.value, 10) + 1);

      if (btnCopyChunk) {
        btnCopyChunk.onclick = async () => {
          try {
            await navigator.clipboard.writeText(debugOutput.value);
            const original = btnCopyChunk.textContent;
            btnCopyChunk.textContent = "✅ Copiado";
            setTimeout(() => btnCopyChunk.textContent = original, 1200);
          } catch (e) {
            alert("No se pudo copiar. Revisa permisos del navegador.");
          }
        };
      }

      mostrarChunk(0);
    }

    // =========================
    // GENERAR ACTA
    // =========================
    btnActa?.addEventListener('click', async () => {
      const texto = (txtFuente?.value || '').trim();
      if (!texto) return alert("No hay texto taquigráfico para generar el acta.");

      // reset debug
      if (panelDebug) panelDebug.style.display = "none";
      if (debugInfo) debugInfo.textContent = "";
      if (debugOutput) debugOutput.value = "";
      if (debugSelect) debugSelect.innerHTML = "";
      if (debugLen) debugLen.textContent = "";

      // Si regeneras acta: resetea estado forzado
      UI_META_OK_OVERRIDE = null;
      UI_HAS_ENC_OVERRIDE = null;
      showEncPanel(false);

      const body = new URLSearchParams();
      body.append("transcripcion_id", transcripcionId);
      body.append("correccion_id", correccionId);
      body.append("texto_fuente", texto);
      body.append("acta_id", actaId ?? "");
      if (chkDebug?.checked) body.append("modo", "debug");

      btnActa.disabled = true;
      btnActa.textContent = chkDebug?.checked ? "Probando chunks…" : "Procesando acta…";

      try {
        const r = await fetch("index.php?ruta=actanueva/generarActa", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
          body: body.toString()
        });
        const data = await r.json();

        if (data.error) return alert("Error: " + data.error);

        if (data.modo === "debug") {
          renderDebugChunks(data);
          return;
        }

        actaId = data.id_acta;
        updateWordLinks();

        if (txtActa) txtActa.value = data.texto_acta;
        if (charsActaSpan) charsActaSpan.textContent = data.chars_acta;
        if (diffActaSpan) diffActaSpan.textContent  = data.diferencia_pct + "%";

        if (msgActa) {
          msgActa.style.display = "block";
          msgActa.textContent = "Acta guardada correctamente (ID: " + actaId + ")";
        }

        await evaluateAndApplyState();

      } catch (err) {
        console.error(err);
        alert("Hubo un error en la solicitud.");
      } finally {
        btnActa.disabled = false;
        btnActa.textContent = actaId ? "🔄 Regenerar Acta" : "📝 Generar Acta";
      }
    });

    // =========================
    // ENCABEZADO: toggle (Ver/Generar)
    // =========================
    async function generarEncabezadoAI() {
      if (!actaId) return alert("Primero genera el acta.");

      setEnabled(btnToggleEnc, false);
      setEnabled(btnRegenEnc, false);
      const oldTop = btnToggleEnc.textContent;
      btnToggleEnc.textContent = "Generando…";

      try {
        const body = new URLSearchParams();
        body.append("acta_id", actaId);

        const resp = await safeFetchJson("index.php?ruta=actanueva/generarEncabezadoAI", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
          body: body.toString()
        });

        if (!resp.ok) {
          console.error("generarEncabezadoAI NO devolvió JSON:", resp.raw);
          alert("El servidor no devolvió JSON. Revisa consola (F12).");
          return;
        }

        const j = resp.json;
        if (!j.ok) {
          alert(j.error || "No se pudo generar el encabezado.");
          return;
        }

        if (txtEncAI) txtEncAI.value = j.encabezado_ai || "";
        if (txtParAI) txtParAI.value = j.primer_parrafo_ai || "";

        // Forzar estado
        UI_HAS_ENC_OVERRIDE = true;

        showEncPanel(true);

        if (msgActa) {
          msgActa.style.display = "block";
          msgActa.textContent = "Encabezado generado y guardado correctamente.";
        }

        await evaluateAndApplyState();

      } finally {
        setEnabled(btnToggleEnc, true);
        setEnabled(btnRegenEnc, true);
        const hasNow = hasText(txtEncAI?.value) || hasText(txtParAI?.value);
        btnToggleEnc.textContent = hasNow ? "👁 Ver Encabezado" : oldTop;
      }
    }

    btnToggleEnc?.addEventListener('click', async () => {
      if (!actaId) return alert("Primero genera el acta.");

      const visible = panelEncAI && panelEncAI.style.display !== "none";
      if (visible) {
        showEncPanel(false);
        return;
      }

      const hasEncNow = hasText(txtEncAI?.value) || hasText(txtParAI?.value);
      if (hasEncNow) showEncPanel(true);
      else await generarEncabezadoAI();
    });

    btnHideEnc?.addEventListener('click', () => showEncPanel(false));
    btnRegenEnc?.addEventListener('click', generarEncabezadoAI);

    // =========================
    // SÍNTESIS (generar/regenerar)
    // =========================
    async function generarSintesis() {
      if (!actaId) return alert("Primero genere el acta.");

      const body = new URLSearchParams();
      body.append("acta_id", actaId);

      if (btnSintesisTop) btnSintesisTop.disabled = true;
      if (btnRegenSintesis) btnRegenSintesis.disabled = true;

      const original = btnSintesisTop ? btnSintesisTop.textContent : "";
      if (btnSintesisTop) btnSintesisTop.textContent = "Procesando síntesis…";

      try {
        const r = await fetch("index.php?ruta=actanueva/generarSintesis", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
          body: body.toString()
        });

        const data = await r.json();
        if (data.error) return alert("Error: " + data.error);

        if (txtSintesis) txtSintesis.value = data.texto_sintesis;
        if (charsSintesis) charsSintesis.textContent = data.chars_sintesis;

        updateWordLinks();

        if (msgActa) {
          msgActa.style.display = "block";
          msgActa.textContent = "Síntesis generada correctamente.";
        }

        await evaluateAndApplyState();

      } finally {
        if (btnSintesisTop) {
          btnSintesisTop.disabled = false;
          btnSintesisTop.textContent = original;
        }
        if (btnRegenSintesis) btnRegenSintesis.disabled = false;
      }
    }

    btnSintesisTop?.addEventListener("click", generarSintesis);
    btnRegenSintesis?.addEventListener("click", generarSintesis);

    // =========================
    // ABRIR MODAL METADATA
    // =========================
    function setVal(id, val) {
      const el = document.getElementById(id);
      if (el) el.value = val ?? '';
    }

    function fillMetaForm(d){
      setVal('m_clave_acta',  d?.clave_acta   ?? '');
      setVal('m_fecha',       d?.fecha         ?? '');
      setVal('m_hora_inicio', (d?.hora_inicio ?? '').substring(0, 5));
      setVal('m_ciudad',      d?.ciudad        ?? 'Mérida');
      setVal('m_recinto',     d?.recinto       ?? '');

      // Catálogos normalizados — valor = ID numérico
      setVal('m_legislatura', d?.id_legislatura ? String(d.id_legislatura) : (BOOT.metaDefaults?.id_legislatura ?? ''));
      setVal('m_periodo',     d?.id_periodo     ? String(d.id_periodo)     : (BOOT.metaDefaults?.id_periodo     ?? ''));
      setVal('m_ejercicio',   d?.id_ejercicio   ? String(d.id_ejercicio)   : (BOOT.metaDefaults?.id_ejercicio   ?? ''));

      // Tipo y sesión: sólo lectura (divs, no inputs)
      const roTipo   = document.getElementById('m_tipo_sesion_ro');
      const roSesion = document.getElementById('m_sesion_ro');
      if (roTipo)   roTipo.textContent   = d?.tipo_sesion_nombre || BOOT.metaDefaults?.tipo_sesion_ro || '—';
      if (roSesion) roSesion.textContent = d?.sesion_nombre_cat  || BOOT.metaDefaults?.sesion_ro      || '—';

      // Mesa directiva
      setVal('m_presidente',   d?.presidente   ?? BOOT.metaDefaults?.presidente   ?? '');
      setVal('m_secretaria_1', d?.secretaria_1 ?? BOOT.metaDefaults?.secretaria_1 ?? '');
      setVal('m_secretaria_2', d?.secretaria_2 ?? BOOT.metaDefaults?.secretaria_2 ?? '');

      enforceUniqueDiputados();
    }

    btnMeta?.addEventListener('click', async () => {
      if (!actaId) return alert("Primero genera el acta para poder guardar metadatos.");
      if (!modalMeta) return alert("Bootstrap Modal no está disponible. Verifica bootstrap.bundle.");

      if (metaMsg) metaMsg.style.display = 'none';

      // ✅ precarga defaults desde corrección (mesa directiva)
      fillMetaForm(META_DEFAULTS);

      const resp = await safeFetchJson(`index.php?ruta=actanueva/obtenerMetadata&acta_id=${actaId}`);

      if (!resp.ok) {
        console.error("obtenerMetadata NO devolvió JSON:", resp.raw);
        setMetaMsg("El servidor no devolvió JSON. Revisa consola (F12).", false);
        modalMeta.show();
        return;
      }

      const j = resp.json;
      if (j.error) setMetaMsg(j.error, false);
      else if (j.data) fillMetaForm(j.data); // si existe meta guardada, sobreescribe defaults

      modalMeta.show();
    });

    // =========================
    // GUARDAR METADATA
    // =========================
    btnGuardarMeta?.addEventListener('click', async () => {
      if (!actaId) return;

      const pres = document.getElementById('m_presidente').value;
      const s1   = document.getElementById('m_secretaria_1').value;
      const s2   = document.getElementById('m_secretaria_2').value;

      const ids = [pres, s1, s2].filter(v => v);
      const unique = new Set(ids);
      if (ids.length !== unique.size) {
        setMetaMsg("No se puede repetir la misma persona en Presidente/Secretarías.", false);
        return;
      }

      const body = new URLSearchParams();
      body.append('acta_id',        actaId);
      body.append('clave_acta',     document.getElementById('m_clave_acta').value);
      body.append('id_legislatura', document.getElementById('m_legislatura')?.value || '');
      body.append('id_periodo',     document.getElementById('m_periodo')?.value     || '');
      body.append('id_ejercicio',   document.getElementById('m_ejercicio')?.value   || '');
      body.append('fecha',          document.getElementById('m_fecha').value);
      body.append('hora_inicio',    document.getElementById('m_hora_inicio').value);
      body.append('ciudad',         document.getElementById('m_ciudad').value);
      body.append('recinto',        document.getElementById('m_recinto').value);
      body.append('presidente',     pres);
      body.append('secretaria_1',   s1);
      body.append('secretaria_2',   s2);

      btnGuardarMeta.disabled = true;

      const resp = await safeFetchJson("index.php?ruta=actanueva/guardarMetadata", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body: body.toString()
      });

      btnGuardarMeta.disabled = false;

      if (!resp.ok) {
        console.error("guardarMetadata NO devolvió JSON:", resp.raw);
        setMetaMsg("El servidor no devolvió JSON. Revisa consola (F12).", false);
        return;
      }

      const j = resp.json;
      if (j.ok) {
        setMetaMsg("Metadatos guardados correctamente.");

        // ✅ FORZAR el estado “metadata OK”
        UI_META_OK_OVERRIDE = true;

        await evaluateAndApplyState();

        // opcional: modalMeta?.hide();

      } else {
        setMetaMsg(j.error || "No se pudo guardar.", false);
      }
    });

  }); // DOMContentLoaded

})();
