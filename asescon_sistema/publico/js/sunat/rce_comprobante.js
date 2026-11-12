(() => {
  const correo = document.getElementById('rceCorreo');
  const formulario = document.getElementById('rceCorreoFormulario');
  const whatsapp = document.getElementById('rceWhatsApp');
  const compartir = whatsapp.querySelector('[data-rce-compartir]');
  const descargar = whatsapp.querySelector('[data-rce-descargar]');
  let archivoCompartir = null;
  let consultaCompartir;

  for (const dialogo of [correo, whatsapp].filter(Boolean)) {
    dialogo.querySelector('[data-rce-cerrar]').addEventListener('click', () => dialogo.close());
  }

  document.querySelectorAll('[data-rce-correo]').forEach(boton => {
    boton.addEventListener('click', () => {
      formulario.reset();
      const parametros = new URL(boton.dataset.rceUrl, location.href).searchParams;
      formulario.elements.cliente.value = parametros.get('cliente');
      formulario.elements.detalle.value = parametros.get('detalle');
      correo.querySelector('[data-rce-documento]').textContent = boton.dataset.rceNumero;
      correo.querySelector('[data-rce-estado]').textContent = '';
      formulario.querySelector('[type=submit]').disabled = false;
      correo.showModal();
    });
  });

  formulario?.addEventListener('submit', async evento => {
    evento.preventDefault();
    const enviar = formulario.querySelector('[type=submit]');
    const cerrar = formulario.querySelector('[data-rce-cerrar]');
    if (enviar.disabled) return;
    const estado = correo.querySelector('[data-rce-estado]');
    enviar.disabled = cerrar.disabled = true;
    estado.textContent = 'Enviando PDF…';
    let enviado = false;
    try {
      const respuesta = await fetch(formulario.action, {method: 'POST', body: new FormData(formulario)});
      const datos = await respuesta.json();
      if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'El envío no se confirmó.');
      enviado = true;
      estado.textContent = datos.mensaje;
    } catch (error) {
      estado.textContent = error instanceof SyntaxError
        ? 'No se pudo verificar el envío. Revisa tu sesión antes de reintentar.'
        : error.message;
    } finally {
      enviar.disabled = enviado;
      cerrar.disabled = false;
    }
  });

  correo?.addEventListener('cancel', evento => {
    if (formulario.querySelector('[data-rce-cerrar]').disabled) evento.preventDefault();
  });

  document.querySelectorAll('[data-rce-whatsapp]').forEach(boton => {
    boton.addEventListener('click', async () => {
      const estado = whatsapp.querySelector('[data-rce-estado]');
      const url = boton.dataset.rceUrl;
      descargar.href = url;
      compartir.hidden = true;
      archivoCompartir = null;
      estado.textContent = '';
      whatsapp.querySelector('[data-rce-documento]').textContent = boton.dataset.rceNumero;
      whatsapp.showModal();
      if (typeof navigator.share !== 'function' || typeof navigator.canShare !== 'function') return;

      const consulta = new AbortController();
      consultaCompartir = consulta;
      try {
        const respuesta = await fetch(url, {signal: consulta.signal});
        if (!respuesta.ok || !respuesta.headers.get('Content-Type')?.startsWith('application/pdf')) return;
        const blob = await respuesta.blob();
        const nombre = respuesta.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1] || 'comprobante-SIRE.pdf';
        const archivo = new File([blob], nombre, {type: 'application/pdf'});
        if (consulta.signal.aborted || !navigator.canShare({files: [archivo]})) return;
        archivoCompartir = archivo;
        compartir.hidden = false;
      } catch {
        if (!consulta.signal.aborted) estado.textContent = 'Puedes descargar el PDF y adjuntarlo en WhatsApp.';
      }
    });
  });

  whatsapp.addEventListener('close', () => {
    consultaCompartir?.abort();
    archivoCompartir = null;
  });

  compartir.addEventListener('click', async () => {
    if (!archivoCompartir) return;
    try {
      await navigator.share({files: [archivoCompartir]});
    } catch (error) {
      if (error.name !== 'AbortError') {
        whatsapp.querySelector('[data-rce-estado]').textContent = 'Puedes descargar el PDF y adjuntarlo en WhatsApp.';
      }
    }
  });
})();
