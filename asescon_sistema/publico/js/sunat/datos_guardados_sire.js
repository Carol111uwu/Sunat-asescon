(() => {
  'use strict';

  const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const llave = (cliente, periodo) => `${cliente}|${periodo}`;

  async function leer(libro, filtros = {}) {
    const datos = await window.AsesconSireSync.post('inventario', {libro, ...filtros});
    if (!Array.isArray(datos.periodos)) throw new Error('ASESCON no devolvió los períodos guardados.');
    return datos.periodos;
  }

  function vincularInventario(libro, boton, panel) {
    if (!boton || !panel) return;
    const estado = panel.querySelector('[role="status"]');
    const cuerpo = panel.querySelector('tbody');
    const tabla = panel.querySelector('.table-container');
    let solicitud = 0;
    boton.hidden = false;
    boton.addEventListener('click', async () => {
      const actual = ++solicitud;
      panel.hidden = !panel.hidden;
      boton.setAttribute('aria-expanded', String(!panel.hidden));
      boton.textContent = panel.hidden ? 'Ver datos guardados' : 'Ocultar datos guardados';
      if (panel.hidden) return;
      estado.textContent = 'Leyendo datos guardados…';
      tabla.hidden = true;
      cuerpo.replaceChildren();
      try {
        const filas = await leer(libro);
        if (actual !== solicitud) return;
        const grupos = new Map();
        for (const fila of filas) {
          const anio = fila.periodo.slice(0, 4);
          const mes = Number(fila.periodo.slice(4));
          if (mes < 1 || mes > 12) continue;
          const clave = llave(fila.id_cliente, anio);
          if (!grupos.has(clave)) grupos.set(clave, {fila, anio, meses: new Set()});
          grupos.get(clave).meses.add(mes);
        }
        for (const grupo of grupos.values()) {
          const tr = cuerpo.insertRow();
          const nombres = [...grupo.meses].sort((a, b) => a - b).map(mes => meses[mes - 1]);
          for (const valor of [grupo.fila.ruc, grupo.fila.razon_social, grupo.anio, nombres.join(', ')]) {
            tr.insertCell().textContent = valor;
          }
        }
        tabla.hidden = !grupos.size;
        estado.textContent = grupos.size ? 'Períodos guardados disponibles en ASESCON.'
          : 'Todavía no hay períodos guardados para las empresas autorizadas.';
      } catch (error) {
        if (actual === solicitud) estado.textContent = error.message;
      }
    });
  }

  async function actualizarVista(id, url, guardarFiltros = true) {
    const consulta = new URL(url);
    consulta.searchParams.set('fragmento', 'resultados');
    const respuesta = await fetch(consulta, {
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      signal: AbortSignal.timeout(30000)
    });
    if (!respuesta.ok) throw new Error('No se pudieron actualizar los resultados guardados.');
    const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
    const nuevo = documento.getElementById(id);
    const anterior = document.getElementById(id);
    if (!nuevo || !anterior) throw new Error('No se recibieron los resultados guardados. Verifica tu sesión.');
    nuevo.querySelectorAll('script').forEach(script => script.remove());
    anterior.replaceWith(nuevo);
    if (guardarFiltros) window.history.replaceState(null, '', url);
    document.dispatchEvent(new CustomEvent('asescon:sire-resultados', {detail: nuevo}));
  }

  window.AsesconDatosGuardadosSire = {leer, llave, vincularInventario, actualizarVista};
})();
