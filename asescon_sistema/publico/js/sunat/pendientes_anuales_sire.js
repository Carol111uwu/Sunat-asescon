(() => {
  'use strict';

                                                                            
                                                                 
  window.AsesconPendientesAnualesSire = {
    crear({libro, formulario, cliente, anio, panel, texto, boton, ocupado, soloLectura}) {
      const usuario = String(formulario?.dataset?.sireUsuario || '0');
      const partesLima = new Intl.DateTimeFormat('en-US',{timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
      const obtenerParte = tipo => partesLima.find(parte => parte.type === tipo)?.value || '';
      const hoy = `${obtenerParte('year')}-${obtenerParte('month')}-${obtenerParte('day')}`;
      const clave = `asescon:sire:pendientes:v2:${usuario}:${libro}:${hoy}`;
      let respaldo = {};
      const patron = /^[1-9]\d*\|20\d{2}(0[1-9]|1[0-2])$/;

      function leer() {
        try {
          const raw = window.localStorage?.getItem(clave);
          const datos = raw ? JSON.parse(raw) : respaldo;
          if (datos && typeof datos === 'object' && !Array.isArray(datos)) {
            return Object.fromEntries(Object.entries(datos).filter(([llave, valor]) =>
              patron.test(llave) && typeof valor === 'string'));
          }
        } catch (_) {                                                                }
        return {...respaldo};
      }
      function guardar(datos) {
        respaldo = {...datos};
        try { window.localStorage?.setItem(clave, JSON.stringify(datos)); }
        catch (_) {                                                   }
        actualizar();
      }
      function opcionesAutorizadas() {
        return new Map([...cliente.options].filter(o => Number(o.value) > 0)
          .map(o => [String(o.value), o]));
      }
      function seleccion() {
        const opciones = opcionesAutorizadas();
        const elegido = String(cliente.value);
        const anyo = String(anio.value);
        return Object.entries(leer()).flatMap(([llave, motivo]) => {
          const [id, periodo] = llave.split('|');
          const opcion = opciones.get(id);
          if (!opcion || periodo.slice(0, 4) !== anyo || (elegido !== '0' && elegido !== id)) return [];
          return [{opcion: {value:id, nombre:opcion.dataset?.nombre || opcion.textContent.trim()}, mesConsulta:periodo, motivo}];
        }).sort((a, b) => Number(a.opcion.value) - Number(b.opcion.value)
          || a.mesConsulta.localeCompare(b.mesConsulta));
      }
      function actualizar() {
        if (!panel || !texto || !boton) return;
        const tareas = seleccion();
        panel.hidden = tareas.length === 0;
        if (!tareas.length) return;
        const meses = [...new Set(tareas.map(t => Number(t.mesConsulta.slice(4))))].sort((a, b) => a - b);
        const empresas = new Set(tareas.map(t => t.opcion.value)).size;
        const sinConfirmar = tareas.filter(t => t.motivo !== 'error').length;
        texto.textContent = `${tareas.length} ${tareas.length === 1 ? 'mes pendiente' : 'meses pendientes'} de ${anio.value}` +
          (empresas > 1 ? ` en ${empresas} empresas` : '') +
          ` · Meses ${meses.join(', ')}${sinConfirmar ? ` · ${sinConfirmar} sin confirmar` : ''}.` +
          ' Únicamente corresponden a consultas solicitadas hoy que quedaron pendientes.';
        texto.title = tareas.map(t => `${t.opcion.nombre}: ${Number(t.mesConsulta.slice(4))}/${t.mesConsulta.slice(0, 4)}`).join(' · ');
        boton.textContent = `Recuperar ${tareas.length} ${tareas.length === 1 ? 'mes pendiente' : 'meses pendientes'}`;
        boton.disabled = ocupado() || soloLectura();
      }
      function marcarTodos(tareas, motivo = 'por_consultar') {
        const datos = leer();
        for (const tarea of tareas) {
          const id = String(tarea.opcion.value);
          const periodo = String(tarea.mesConsulta);
          if (Number(id) > 0 && /^20\d{2}(0[1-9]|1[0-2])$/.test(periodo)) {
            datos[`${id}|${periodo}`] = motivo;
          }
        }
        guardar(datos);
      }
      function resuelto(tarea) {
        const datos = leer();
        const llave = `${tarea.opcion.value}|${tarea.mesConsulta}`;
        delete datos[llave];
        guardar(datos);
      }
      cliente.addEventListener('change', actualizar);
      anio.addEventListener('change', actualizar);
      window.addEventListener?.('storage', event => {
        if (event.key === clave) actualizar();
      });
      actualizar();
      return {seleccion, actualizar, marcarTodos, resuelto};
    }
  };
})();
