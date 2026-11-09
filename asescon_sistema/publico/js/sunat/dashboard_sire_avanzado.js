(() => {
  'use strict';
  const raiz = document.getElementById('dashboardSire');
  if (!raiz) return;
  const $ = id => document.getElementById(id);
  const csrf = document.querySelector('meta[name="asescon-csrf-sire"]')?.content || '';
  const periodo = () => String($('sireAnio')?.value || '') + String($('sireMes')?.value || '');
  const cliente = () => Number($('sireCliente')?.value || 0);

                                                                                                
  $('sireFiltroCobertura')?.addEventListener('input', event => {
    const palabra = String(event.target.value || '').trim().toLocaleLowerCase('es');
    document.querySelectorAll('.sire-coverage-row').forEach(fila => {
      fila.hidden = !String(fila.dataset.search || '').toLocaleLowerCase('es').includes(palabra);
    });
  });

  const aviso = $('sireAccionIndividual');
  const notificar = mensaje => {
    if (!aviso) return;
    aviso.hidden = false;
    aviso.textContent = mensaje;
  };
  document.querySelectorAll('[data-sire-consultar-libro]').forEach(boton => {
    boton.addEventListener('click', async () => {
      const tipo = boton.dataset.sireConsultarLibro;
      if (!['RVIE', 'RCE'].includes(tipo) || !cliente() || !/^20\d{2}(0[1-9]|1[0-2])$/.test(periodo())) return;
      if (boton.dataset.guardado === '1' && !window.confirm(
        `Ya hay datos ${tipo} guardados para este período. ¿Deseas consultarlos nuevamente en SUNAT?`
      )) return;
      boton.disabled = true;
      const original = boton.textContent;
      boton.textContent = 'Consultando...';
      try {
        if (!window.AsesconSireSync) throw new Error('El módulo SIRE aún no se ha cargado.');
        const res = await AsesconSireSync.sincronizarLibro(cliente(), tipo, periodo(), notificar);
        notificar(`${tipo}: ${Number(res.cantidad_registros || 0)} comprobantes guardados. Actualizando Dashboard SIRE...`);
        window.setTimeout(() => location.reload(), 1300);
      } catch (error) {
        notificar(`No se pudo consultar ${tipo}: ${String(error?.message || error)}. Los datos anteriores no se eliminan.`);
      } finally {
        boton.disabled = false;
        boton.textContent = original;
      }
    });
  });

  const formulario = $('sireRevisionIgv');
  formulario?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!formulario.reportValidity()) return;
    const boton = formulario.querySelector('button[type="submit"]');
    const salida = $('sireRevisionMensaje');
    const datos = Object.fromEntries(new FormData(formulario).entries());
    datos.id_cliente = Number(formulario.dataset.cliente);
    datos.periodo = String(formulario.dataset.periodo || '');
    const aviso = (texto, error) => {
      if (!salida) return;
      salida.textContent = texto;
      salida.className = error ? 'sire-error' : 'sire-success';
    };
    boton.disabled = true;
    aviso('Guardando revisión contable...', false);
    try {
      const respuesta = await fetch('../../api/sunat/validacion_igv_sire.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(datos)
      });
      const cuerpo = await respuesta.json();
      if (!respuesta.ok || !cuerpo.ok) throw new Error(cuerpo.mensaje || `Error HTTP ${respuesta.status}`);
      aviso('Revisión guardada. Actualizando el análisis sin salir del Dashboard...', false);
      window.setTimeout(() => location.reload(), 1100);
    } catch (error) {
      aviso(String(error?.message || error || 'No se pudo guardar la revisión.'), true);
    } finally { boton.disabled = false; }
  });
})();
