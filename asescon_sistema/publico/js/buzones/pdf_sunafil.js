document.addEventListener('click', async function(e) {
    const boton = e.target.closest('[data-pdf-sunafil]');
    if (!boton) return;

    const id = boton.dataset.pdfSunafil;
    const estado = document.querySelector(
        '[data-estado-pdf-sunafil="' + id + '"]'
    );

    const endpoint = '../../api/buzones/pdf_sunafil.php';

    boton.disabled = true;
    estado.textContent = 'Solicitando PDF...';

    try {
        const datos = new URLSearchParams();

        datos.set('id_documento', id);
        datos.set('csrf_token', boton.dataset.csrf);

        let respuesta = await fetch(endpoint, {
            method: 'POST',
            body: datos
        });

        let resultado = await respuesta.json();

        for (let i = 0; i < 60; i++) {
            if (!resultado.ok) {
                throw new Error(resultado.mensaje);
            }

            if (resultado.estado === 'DISPONIBLE') {
                const url = new URL(resultado.url, location.href);

                if (url.origin !== location.origin) {
                    throw new Error('Enlace no válido.');
                }

                const enlace = document.createElement('a');
                enlace.href = url.href;
                enlace.download = '';
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();

                estado.textContent = 'PDF disponible.';
                return;
            }

            if (!['PENDIENTE', 'PROCESANDO'].includes(resultado.estado)) {
                throw new Error(resultado.mensaje || 'PDF no disponible.');
            }

            estado.textContent = resultado.mensaje || 'En proceso...';

            await new Promise(resolve => setTimeout(resolve, 4000));

            respuesta = await fetch(
                endpoint + '?id_documento=' + encodeURIComponent(id)
            );

            resultado = await respuesta.json();
        }

        estado.textContent = 'Solicitud en cola. Vuelve a comprobar.';
    } catch (error) {
        estado.textContent = error.message;
    } finally {
        boton.disabled = false;
    }
});
