let ID_AUDIO_GLOBAL = null;

const form = document.getElementById("formulario");
const progress = document.getElementById("progress");
const bar = document.getElementById("bar");
const output = document.getElementById("output");
const puntos = document.getElementById("puntos");
const cronometro = document.getElementById("cronometro");
const procesando = document.getElementById("procesando");

form.addEventListener("submit", function (e) {
    e.preventDefault();

    const url = document.getElementById("url").value.trim();
    const nombre = document.getElementById("nombre").value.trim();

    if (!url || !nombre) {
        alert("Por favor, completa ambos campos: URL del video y nombre del archivo.");
        return;
    }

    output.innerHTML = "";
    progress.style.display = "block";
    procesando.style.display = "block";
    bar.style.width = "0%";
    bar.textContent = "0%";

    let puntosTxt = "";
    let puntoInterval = setInterval(() => {
        puntosTxt = puntosTxt.length < 3 ? puntosTxt + "." : "";
        puntos.textContent = puntosTxt;
    }, 500);

    let segundos = 0;
    let tiempoInterval = setInterval(() => {
        segundos++;
        let min = String(Math.floor(segundos / 60)).padStart(2, "0");
        let seg = String(segundos % 60).padStart(2, "0");
        cronometro.textContent = `${min}:${seg}`;
    }, 1000);

    let porcentaje = 0;
    const barInterval = setInterval(() => {
        porcentaje += Math.random() * 1;
        if (porcentaje > 95) porcentaje = 95;
        bar.style.width = porcentaje + "%";
        bar.textContent = Math.floor(porcentaje) + "%";
    }, 500);

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "index.php?ruta=proceso/procesar", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function () {
        clearInterval(barInterval);
        clearInterval(puntoInterval);
        clearInterval(tiempoInterval);

        try {
            const respuesta = JSON.parse(xhr.responseText);

            if (respuesta.error) {
                output.innerHTML = `<div class="text-danger"><strong>Error:</strong> ${respuesta.error}</div>`;
                console.error("Comando de depuración:", respuesta.debug_comando || 'No disponible');
                return;
            }

            bar.style.width = "100%";
            bar.textContent = "100%";
            progress.style.display = "none";
            procesando.style.display = "none";

            // Decodificar caracteres unicode (acentos, ñ, etc.)
            const textoPlano = new DOMParser().parseFromString(respuesta.transcripcion.trim(), "text/html").documentElement.textContent;

            // Mostrar transcripción con encabezado
            output.innerHTML = `
            div class="table-container card-style mb-4">
                <div class="card mt-3">
                    <div class="card-header bg-dark text-white fw-bold">
                    Tiempo total de transcripción: ${cronometro.textContent}
                    </div>
                    <div class="card-body text-justify">
                    <p>${textoPlano.replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
            </div>
            `;

            // Cargar en el modal
            document.getElementById("btnModalGuardar").style.display = "inline-block";
            document.getElementById("cTituloTrans").value = nombre;
            document.getElementById("dFechaTrans").value = new Date().toISOString().split('T')[0];
            document.getElementById("cLinkTrans").value = url;
            document.getElementById("tTrans").value = textoPlano;
            document.getElementById("iIdAudio").value = respuesta.id_audio;
            ID_AUDIO_GLOBAL = respuesta.id_audio;

        } catch (e) {
            output.innerHTML = `<div class="text-danger">Error inesperado. Revisa la consola (F12) para más información.</div>`;
            console.error("Error al procesar respuesta:", xhr.responseText);
        }
    };

    xhr.send("url=" + encodeURIComponent(url) + "&nombre=" + encodeURIComponent(nombre));
});
