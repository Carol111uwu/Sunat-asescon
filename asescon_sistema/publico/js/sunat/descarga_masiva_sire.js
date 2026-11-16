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

/* Descarga completa del periodo: ZIP por lotes, no restringida a la tabla visible. */
(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="asescon-csrf-sire-masivo"]')?.content;
  document.querySelectorAll('[data-sire-periodo-zip]').forEach(panel => {
    const boton = panel.querySelector('[data-sire-descargar-periodo]');
    const detener = panel.querySelector('[data-sire-detener-periodo]');
    const estado = panel.querySelector('[data-sire-progreso]');
    if (!boton || !detener || !estado || !csrf) return;
    let ocupado = false;
    let offset = 0;
    let peticion = null;
    let totalConocido = 0;

    const nombreArchivo = (respuesta, lote) => {
      const encabezado = respuesta.headers.get('Content-Disposition') || '';
      const nombre = encabezado.match(/filename="([^"\\/]+)"/)?.[1];
      return nombre || `ASESCON-${panel.dataset.sirePeriodoZip.toUpperCase()}-lote-${lote}.zip`;
    };
    detener.addEventListener('click', () => {
      peticion?.abort();
      estado.textContent = `Descarga detenida. Puedes continuar a partir del registro ${offset + 1}.`;
    });
    boton.addEventListener('click', async () => {
      if (ocupado) return;
      ocupado = true;
      boton.disabled = true;
      detener.hidden = false;
      let lotes = 0;
      let errores = 0;
      try {
        while (true) {
          peticion = new AbortController();
          const cuerpo = new URLSearchParams({
            csrf_token: csrf,
            libro: panel.dataset.sirePeriodoZip.toUpperCase(),
            cliente: panel.dataset.sireCliente,
            periodo: panel.dataset.sirePeriodo,
            excluir_anteriores: panel.dataset.sireExcluir,
            tipo: panel.dataset.sireTipo || '',
            offset: String(offset),
          });
          estado.textContent = `Preparando ZIP ${lotes + 1}… (${offset} de ${totalConocido || '?'} comprobantes procesados)`;
          const respuesta = await fetch(panel.dataset.sireZipUrl, {
            method: 'POST',
            body: cuerpo,
            credentials: 'same-origin',
            cache: 'no-store',
            signal: peticion.signal,
          });
          if (!respuesta.ok) {
            const aviso = await respuesta.json().catch(() => ({}));
            throw new Error(aviso.mensaje || `El servidor devolvió HTTP ${respuesta.status}`);
          }
          if (!(respuesta.headers.get('Content-Type') || '').includes('application/zip')) {
            throw new Error('La respuesta no contiene un archivo ZIP.');
          }
          const total = Number(respuesta.headers.get('X-ASESCON-Total'));
          const siguiente = Number(respuesta.headers.get('X-ASESCON-Siguiente'));
          if (!Number.isFinite(total) || !Number.isFinite(siguiente) || siguiente <= offset) {
            throw new Error('La respuesta de la descarga no contiene un rango válido.');
          }
          const erroresLote = Number(respuesta.headers.get('X-ASESCON-Errores')) || 0;
          const blob = await respuesta.blob();
          if (!blob.size) throw new Error('El ZIP recibido está vacío.');
          const url = URL.createObjectURL(blob);
          const enlace = document.createElement('a');
          enlace.href = url;
          enlace.download = nombreArchivo(respuesta, lotes + 1);
          enlace.hidden = true;
          document.body.appendChild(enlace);
          enlace.click();
          enlace.remove();
          setTimeout(() => URL.revokeObjectURL(url), 60000);
          lotes++;
          errores += erroresLote;
          offset = siguiente;
          totalConocido = total;
          estado.textContent = `ZIP ${lotes} solicitado. ${offset} de ${total} registros procesados. Reportes fallidos: ${errores}.`;
          if (offset >= total) {
            estado.textContent += ' Descarga completa; revisa los manifiestos de cada ZIP y los permisos de descargas múltiples del navegador.';
            offset = 0;
            totalConocido = 0;
            break;
          }
        }
      } catch (error) {
        if (error.name === 'AbortError') {
          estado.textContent = `Detenido tras ${lotes} ZIP. Puedes continuar desde el registro ${offset + 1}.`;
        } else {
          console.warn('No se completó la descarga masiva SIRE:', error);
          estado.textContent = `Descarga interrumpida tras ${lotes} ZIP: ${error.message}. Reintenta para continuar.`;
        }
      } finally {
        ocupado = false;
        peticion = null;
        detener.hidden = true;
        boton.disabled = false;
        boton.textContent = offset > 0 ? `Continuar descarga completa desde ${offset + 1}` : 'Descargar todo el período (ZIP)';
      }
    });
  });
})();
