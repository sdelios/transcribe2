document.querySelectorAll('.btn-generar-modelo').forEach(btn => {
    btn.addEventListener('click', function () {
        console.log("Botón 'Generar' clickeado");

        const modelo = this.dataset.modelo;
        const ruta = this.dataset.ruta;
        const idAudio = this.dataset.idaudio;
        const idModelo = this.dataset.idmodelo;
        const cLink = this.dataset.clink;
        const contenedor = document.getElementById('contenedor-' + modelo);
        const todosBotones = document.querySelectorAll('button');

        // Deshabilitar todos los botones
        todosBotones.forEach(b => b.disabled = true);

        // Mostrar progreso
        contenedor.innerHTML = `
            <div id="procesando">
                <strong>Procesando</strong><span id="puntos">.</span> 
                <div id="cronometro">00:00</div>
                <div class="progress mt-2">
                    <div class="progress-bar" id="bar" style="width: 0%">0%</div>
                </div>
            </div>
        `;

        let puntosTxt = "";
        const puntos = document.getElementById("puntos");
        const cronometro = document.getElementById("cronometro");
        const bar = document.getElementById("bar");

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
            porcentaje += Math.random() * 1.5;
            if (porcentaje > 95) porcentaje = 95;
            bar.style.width = porcentaje + "%";
            bar.textContent = Math.floor(porcentaje) + "%";
        }, 400);

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "index.php?ruta=transcripcion/generarModelo", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onload = () => {
            console.log("Respuesta cruda del servidor:", xhr.responseText);
            clearInterval(puntoInterval);
            clearInterval(tiempoInterval);
            clearInterval(barInterval);
            bar.style.width = "100%";
            bar.textContent = "100%";

            const respuesta = JSON.parse(xhr.responseText);
            contenedor.innerHTML = `
                <div class="text-muted">Duración: ${cronometro.textContent}</div>
                <div class="card mt-2">
                    <div class="card-body text-justify">${respuesta.transcripcion}</div>
                </div>
            `;

            todosBotones.forEach(b => b.disabled = false);
        };

        xhr.send("modelo=" + encodeURIComponent(modelo) +
                 "&ruta=" + encodeURIComponent(ruta) +
                 "&idAudio=" + encodeURIComponent(idAudio) +
                 "&idModelo=" + encodeURIComponent(idModelo) +
                 "&cLink=" + encodeURIComponent(cLink));
    });
});
