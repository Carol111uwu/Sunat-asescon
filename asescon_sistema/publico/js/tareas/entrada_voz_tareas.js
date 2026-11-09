document.addEventListener("DOMContentLoaded", function () {

    const botonVoz = document.getElementById("btnVoz");
    const botonDetener = document.getElementById("btnDetener");
    const campoTexto = document.getElementById("descripcion");
    const estadoVoz = document.getElementById("vozEstado");

    const ponerTextoBoton = (texto, icono = "🎤") => {
        botonVoz.innerHTML = `${icono} <span>${texto}</span>`;
    };

    if (!botonVoz || !campoTexto) {
        return;
    }

    const SpeechRecognition =
        window.SpeechRecognition ||
        window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
        botonVoz.disabled = true;
        ponerTextoBoton("Voz no disponible");
        if (estadoVoz) estadoVoz.textContent = "Tu navegador no ofrece reconocimiento de voz.";
        return;
    }

    const reconocimiento = new SpeechRecognition();

    reconocimiento.lang = "es-PE";
    reconocimiento.continuous = true;
    reconocimiento.interimResults = true;

    let textoGuardado = "";

    reconocimiento.onstart = function () {
        ponerTextoBoton("Escuchando...", "🎙️");
        if (estadoVoz) estadoVoz.textContent = "Escuchando. Habla con normalidad; el texto aparecerá arriba.";
        botonVoz.classList.add("escuchando");

        if (botonDetener) {
            botonDetener.style.display = "inline-flex";
        }
    };

    reconocimiento.onresult = function (evento) {

        let textoTemporal = "";

        for (
            let i = evento.resultIndex;
            i < evento.results.length;
            i++
        ) {

            const texto =
                evento.results[i][0].transcript;

            if (evento.results[i].isFinal) {
                textoGuardado += texto + " ";
            } else {
                textoTemporal += texto;
            }
        }

        campoTexto.value =
            textoGuardado + textoTemporal;
    };

    reconocimiento.onerror = function (evento) {

        console.error(
            "Error de reconocimiento de voz:",
            evento.error
        );

        ponerTextoBoton("Dictar por voz");
        botonVoz.classList.remove("escuchando");
        if (estadoVoz) estadoVoz.textContent = "No se pudo continuar con el dictado. Puedes intentarlo nuevamente.";
    };

    reconocimiento.onend = function () {

        ponerTextoBoton("Dictar por voz");
        botonVoz.classList.remove("escuchando");
        if (estadoVoz) estadoVoz.textContent = "Dictado finalizado. Puedes volver a activarlo cuando quieras.";

        if (botonDetener) {
            botonDetener.style.display = "none";
        }
    };

    botonVoz.addEventListener("click", function () {

        textoGuardado = campoTexto.value.trim();

        if (textoGuardado !== "") {
            textoGuardado += " ";
        }

        reconocimiento.start();
    });

    if (botonDetener) {

        botonDetener.addEventListener("click", function () {
            reconocimiento.stop();
        });

    }

});
