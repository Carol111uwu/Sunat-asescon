(() => {
  const libro = document.getElementById('rvieCliente') ? 'RVIE' : (document.getElementById('rceCliente') ? 'RCE' : '');
  if (!libro) return;

  const prefijo = libro.toLowerCase();
  const cliente = document.getElementById(prefijo + 'Cliente');
  const mes = document.getElementById(prefijo + 'Mes');
  const anio = document.getElementById(prefijo + 'Anio');
  const btnSincronizar = document.getElementById('btnSync' + libro);
  const btnReparar = document.getElementById('btnRepair' + libro);
  const estado = document.getElementById('syncStatus' + libro);
  const excluir = document.getElementById('toggleExcluirAnteriores');
  const detalleId = prefijo + '-detalle';

  const periodoActual = () => String(anio?.value || '') + String(mes?.value || '');
  const abrirSeleccion = () => {
    const url = new URL(location.href);
    url.searchParams.set('cliente', cliente?.value || '');
    url.searchParams.set('anio', anio?.value || '');
    url.searchParams.set('mes', mes?.value || '');
    url.searchParams.delete('periodo');
    url.searchParams.set('excluir_anteriores', excluir?.checked ? '1' : '0');
    location.href = url.toString();
  };

  excluir?.addEventListener('change', abrirSeleccion);

  btnReparar?.addEventListener('click', async () => {
    const idCliente = Number(cliente?.value || 0);
    const periodo = periodoActual();
    if (!idCliente) return alert('Selecciona un cliente.');
    btnReparar.disabled = true;
    if (estado) {
      estado.style.display = 'block';
      estado.textContent = 'Reconstruyendo el detalle desde el archivo SIRE ya descargado...';
    }
    try {
      const respuesta = await AsesconSireSync.post('reprocesar', { id_cliente: idCliente, libro, periodo });
      if (estado) estado.textContent = '✓ ' + (respuesta.mensaje || 'Detalle reprocesado.');
      setTimeout(abrirSeleccion, 700);
    } catch (error) {
      if (estado) estado.textContent = 'ERROR: ' + error.message;
    } finally {
      btnReparar.disabled = false;
    }
  });

  btnSincronizar?.addEventListener('click', async () => {
    const idCliente = Number(cliente?.value || 0);
    const periodo = periodoActual();
    if (!idCliente) return alert('Selecciona un cliente.');
    if (/^20\d{2}00$/.test(periodo)) return alert('La vista anual es solo de consulta. Selecciona un mes para sincronizar con SUNAT.');
    if (!/^20\d{4}$/.test(periodo)) return alert('Selecciona un período válido.');
    btnSincronizar.disabled = true;
    if (estado) {
      estado.style.display = 'block';
      estado.textContent = 'Iniciando sincronización ' + libro + '...';
    }
    try {
      const respuesta = await AsesconSireSync.sincronizarLibro(idCliente, libro, periodo, mensaje => {
        if (estado) estado.textContent = mensaje;
      });
      if (estado) estado.textContent = '✓ ' + libro + ' sincronizado desde SUNAT: ' + Number(respuesta.cantidad_registros || 0) + ' comprobantes.';
      setTimeout(abrirSeleccion, 900);
    } catch (error) {
      if (estado) estado.textContent = 'ERROR: ' + (error.name === 'AbortError' ? 'SUNAT tardó demasiado en responder.' : error.message);
    } finally {
      btnSincronizar.disabled = false;
    }
  });

  const root = document.querySelector('[data-tabs]');
  if (!root) return;
  const panelDetalle = root.querySelector('[data-tab-panel="' + detalleId + '"]');
  const tabDetalle = root.querySelector('[data-tab-target="' + detalleId + '"]');
  const limpiar = root.querySelector('.sire-clear-filter');
  const abrirDetalle = (tipo = '') => {
    root.querySelectorAll('[data-tab-target]').forEach(boton => boton.classList.toggle('active', boton === tabDetalle));
    root.querySelectorAll('[data-tab-panel]').forEach(panel => { panel.hidden = panel !== panelDetalle; });
    panelDetalle?.querySelectorAll('.sire-detail-row').forEach(fila => { fila.hidden = !!tipo && fila.dataset.sireTipo !== tipo; });
    if (limpiar) {
      limpiar.hidden = !tipo;
      limpiar.textContent = tipo ? 'Ver todos · filtro ' + tipo : 'Ver todos';
    }
    panelDetalle?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  document.querySelectorAll('[data-open-sire-detail]').forEach(elemento => {
    const ejecutar = () => abrirDetalle(elemento.dataset.sireTipo || '');
    elemento.addEventListener('click', ejecutar);
    elemento.addEventListener('keydown', evento => {
      if (evento.key === 'Enter' || evento.key === ' ') {
        evento.preventDefault();
        ejecutar();
      }
    });
  });
  limpiar?.addEventListener('click', () => abrirDetalle(''));
})();
