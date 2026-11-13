(() => {
  'use strict';
  const boton = document.getElementById('rceLoteIndividual');
  const estado = document.getElementById('rceSeleccionEstado');
  if (!boton || !estado) return;
  const filas = [...document.querySelectorAll('tr.sire-detail-row')];
  let descargando = false;
  const visibles = () => filas.filter(f => !f.hidden && !f.classList.contains('rce-oculto-filtro') && f.style.display !== 'none');
  const marcadas = () => visibles().filter(f => f.querySelector('.rce-seleccionar-fila')?.checked);
  const actualizar = () => { boton.disabled = descargando || marcadas().length === 0; };
  const nombreArchivo = (respuesta, url) => {
    const cabecera = respuesta.headers.get('Content-Disposition') || '';
    const nombreUtf8 = cabecera.match(/filename\*=UTF-8''([^;]+)/i);
    const nombreNormal = cabecera.match(/filename="([^"]+)"/i);
    let nombre = nombreUtf8 ? decodeURIComponent(nombreUtf8[1]) : nombreNormal ? nombreNormal[1] : `SIRE-${new URL(url).searchParams.get('detalle')}.pdf`;
    nombre = nombre.replace(/[\\/\x00-\x1f]/g, '_');
    return nombre.toLowerCase().endsWith('.pdf') ? nombre : nombre + '.pdf';
  };
  filas.forEach(f => f.querySelector('.rce-seleccionar-fila')?.addEventListener('change', actualizar));
  document.getElementById('rceMarcarTodos')?.addEventListener('change', actualizar);
  document.getElementById('rceSeleccionCantidad')?.addEventListener('change', actualizar);
  document.getElementById('rceLimpiarSeleccion')?.addEventListener('click', actualizar);
  const observador = new MutationObserver(actualizar);
  filas.forEach(f => observador.observe(f, {attributes: true, attributeFilter: ['hidden', 'class', 'style']}));
  boton.addEventListener('click', async () => {
    if (descargando) return;
    const seleccion = marcadas();
    if (!seleccion.length) return;
    if (seleccion.length > 60) { estado.textContent = 'Descarga máximo 60 comprobantes por operación.'; return; }
    const urls = seleccion.map(f => f.querySelector('[data-sire-pdf-url]')?.dataset.sirePdfUrl).filter(Boolean);
    if (!urls.length) { estado.textContent = 'Los PDF seleccionados no tienen una dirección de descarga.'; return; }
    descargando = true;
    actualizar();
    let correctas = 0, errores = 0;
    for (let i = 0; i < urls.length; i++) {
      try {
        const respuesta = await fetch(urls[i], {credentials: 'same-origin', cache: 'no-store'});
        if (!respuesta.ok || !(respuesta.headers.get('Content-Type') || '').toLowerCase().includes('application/pdf')) {
          throw new Error(`HTTP ${respuesta.status}`);
        }
        const archivo = await respuesta.blob();
        if (!archivo.size) throw new Error('Archivo vacío');
        const url = URL.createObjectURL(archivo);
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = nombreArchivo(respuesta, urls[i]);
        enlace.hidden = true;
        document.body.append(enlace);
        enlace.click();
        enlace.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
        correctas++;
      } catch (error) {
        errores++;
        console.warn('No se pudo descargar el PDF individual:', error);
      }
      estado.textContent = `Procesados ${i + 1}/${urls.length}. Descargas iniciadas: ${correctas}. Errores: ${errores}.`;
    }
    descargando = false;
    actualizar();
    estado.textContent += ' Si tu navegador bloquea las descargas múltiples, permite descargas automáticas para este sitio.';
  });
  actualizar();
})();
