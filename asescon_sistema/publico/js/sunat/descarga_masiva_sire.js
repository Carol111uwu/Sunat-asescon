/* Seleccion y descarga independiente de reportes PDF SIRE (RCE/RVIE). */
(() => {
  'use strict';
  document.querySelectorAll('[data-sire-masiva]').forEach(panel => {
    const seccion = panel.closest('[data-tab-panel]');
    const filas = [...seccion.querySelectorAll('tr.sire-detail-row')];
    const todos = seccion.querySelector('[data-sire-todos]');
    const elegir = panel.querySelector('[data-sire-cantidad]');
    const contador = panel.querySelector('[data-sire-contador]');
    const descargar = panel.querySelector('[data-sire-descargar]');
    const limpiar = panel.querySelector('[data-sire-limpiar]');
    const progreso = panel.querySelector('[data-sire-progreso]');
    let trabajando = false;

    const visibles = () => filas.filter(f => !f.hidden && f.style.display !== 'none');
    const marcadas = () => visibles().filter(f => f.querySelector('.sire-seleccion-fila')?.checked);
    const actualizar = () => {
      const n = marcadas().length;
      const cantidad = visibles().length;
      contador.textContent = `${n} seleccionado${n === 1 ? '' : 's'} de ${cantidad} visibles`;
      descargar.textContent = `Descargar seleccionados (${n})`;
      descargar.disabled = trabajando || !n;
      limpiar.disabled = trabajando || !n;
      elegir.disabled = trabajando;
      todos.disabled = trabajando || !cantidad;
      todos.checked = cantidad > 0 && n === cantidad;
      todos.indeterminate = n > 0 && n < cantidad;
      filas.forEach(f => { const c = f.querySelector('.sire-seleccion-fila'); if(c) c.disabled = trabajando; });
    };
    filas.forEach(f => f.querySelector('.sire-seleccion-fila')?.addEventListener('change', actualizar));
    todos.addEventListener('change', () => {
      visibles().forEach(f => { f.querySelector('.sire-seleccion-fila').checked = todos.checked; });
      actualizar();
    });
    elegir.addEventListener('change', () => {
      if (!elegir.value) return;
      const lista = visibles();
      const n = elegir.value === 'todos' ? lista.length : Number(elegir.value);
      lista.forEach((f, i) => { f.querySelector('.sire-seleccion-fila').checked = i < n; });
      actualizar();
    });
    limpiar.addEventListener('click', () => {
      filas.forEach(f => { f.querySelector('.sire-seleccion-fila').checked = false; });
      elegir.value = '';
      progreso.textContent = '';
      actualizar();
    });

    const filename = (res, url) => {
      const h = res.headers.get('Content-Disposition') || '';
      const utf = h.match(/filename\*=UTF-8''([^;]+)/i);
      const quoted = h.match(/filename="([^"]+)"/i);
      let nombre = utf ? decodeURIComponent(utf[1]) : quoted ? quoted[1] : `SIRE-${new URL(url).searchParams.get('detalle')}.pdf`;
      nombre = nombre.replace(/[\\/\x00-\x1f]/g, '_');
      return nombre.toLowerCase().endsWith('.pdf') ? nombre : nombre + '.pdf';
    };
    descargar.addEventListener('click', async () => {
      if (trabajando) return;
      const tareas = marcadas().map(f => f.querySelector('[data-sire-pdf-url]')?.dataset.sirePdfUrl).filter(Boolean);
      if (!tareas.length) { progreso.textContent = 'No hay archivos disponibles para descargar.'; return; }
      trabajando = true;
      actualizar();
      let correctas = 0, errores = 0;
      progreso.textContent = `Preparando ${tareas.length} descargas independientes…`;
      for (let i = 0; i < tareas.length; i++) {
        try {
          const respuesta = await fetch(tareas[i], { credentials: 'same-origin', cache: 'no-store' });
          if (!respuesta.ok || !(respuesta.headers.get('Content-Type') || '').toLowerCase().includes('application/pdf')) {
            throw new Error(`Respuesta no disponible (${respuesta.status})`);
          }
          const archivo = await respuesta.blob();
          if (!archivo.size) throw new Error('Archivo vacío');
          const url = URL.createObjectURL(archivo);
          const enlace = document.createElement('a');
          enlace.href = url;
          enlace.download = filename(respuesta, tareas[i]);
          enlace.hidden = true;
          document.body.appendChild(enlace);
          enlace.click();
          enlace.remove();
          setTimeout(() => URL.revokeObjectURL(url), 60000);
          correctas++;
        } catch (err) {
          errores++;
          console.warn('No se pudo descargar un reporte SIRE:', err);
        }
        progreso.textContent = `Procesados ${i + 1}/${tareas.length}. Descargas iniciadas: ${correctas}. Errores: ${errores}.`;
      }
      trabajando = false;
      actualizar();
      progreso.textContent += ' Si Chrome bloquea varias descargas, permite descargas automáticas para localhost:8080.';
    });
    // Actualizar contador si los filtros existentes ocultan filas.
    const observador = new MutationObserver(actualizar);
    filas.forEach(f => observador.observe(f, { attributes: true, attributeFilter: ['style', 'hidden'] }));
    actualizar();
  });
})();
