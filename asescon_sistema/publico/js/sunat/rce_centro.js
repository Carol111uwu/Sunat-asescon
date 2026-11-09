(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const cliente = $('rceCliente');
  if (!cliente) return;
  if (!window.AsesconSireSync) {
    $('rceConsultaFormulario')?.addEventListener('submit', event => {
      event.preventDefault();
      const panel = $('rceProceso');
      if (panel) { panel.hidden = false; panel.scrollIntoView({block:'nearest'}); }
      const texto = $('syncStatusRCE');
      if (texto) texto.textContent = 'No se cargó el módulo de conexión SIRE. Recarga con Ctrl+F5 antes de consultar SUNAT. No se realizó ninguna consulta.';
    });
    return;
  }

  const mes = $('rceMes');
  const anio = $('rceAnio');
  const excluir = $('toggleExcluirAnteriores');
  const consultar = $('rceVerConsultar');
                                                                                            
  consultar.disabled = consultar.dataset.soloLectura === '1';
  const proceso = $('rceProceso');
  const estado = $('syncStatusRCE');
  const barra = $('rceBarraProgreso');
  const contadores = $('rceContadores');
  const lista = $('rceListaProcesos');
  const botonEstado = $('rceVerEstado');
  const avisoSeleccion = $('rceAvisoSeleccion');
  const pausar = $('rcePausar');
  const recuperarPendientes = $('rceRecuperarPendientes');
  const formulario = $('rceConsultaFormulario');
  let trabajando = false;
  let estadoOcultoPorUsuario = false;
  let recuperacionSolicitada = null;
  let pausaSolicitada = false;
  let reanudarPausa = null;
  const registroPendientes = window.AsesconPendientesAnualesSire?.crear({
    libro: 'RCE', formulario, cliente, anio, panel: $('rcePendientesAnuales'),
    texto: $('rcePendientesTexto'), boton: recuperarPendientes,
    ocupado: () => trabajando, soloLectura: () => consultar.dataset.soloLectura === '1'
  });

  const periodo = () => String(anio.value) + String(mes.value);
  const esAnual = () => String(mes.value) === '00';
  const elementosClientes = () => [...cliente.options].filter(o => Number(o.value) > 0);
  const nombre = opcion => opcion.dataset.nombre || opcion.textContent.trim();
  const urlVista = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('cliente', cliente.value);
    url.searchParams.set('mes', mes.value);
    url.searchParams.set('anio', anio.value);
    url.searchParams.set('excluir_anteriores', excluir?.checked ? '1' : '0');
    ['periodo', 'pagina', 'tipo', 'vista'].forEach(k => url.searchParams.delete(k));
    return url;
  };
  const verSeleccion = () => { window.location.assign(urlVista().toString()); };
  const fechaLima = () => {
    const partes = new Intl.DateTimeFormat('en-US', {timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
    const dato = tipo => partes.find(p => p.type === tipo)?.value || '';
    return `${dato('year')}-${dato('month')}-${dato('day')}`;
  };

  const actualizarExcel = () => {
    const filtro = periodo();
    document.querySelectorAll('[data-rce-excel]').forEach(link => {
      const params = new URLSearchParams({
        libro: 'RCE', id_cliente: cliente.value, periodo: filtro,
        excluir_anteriores: excluir?.checked ? '1' : '0',
        modo: link.dataset.rceExcel || 'resumen'
      });
      link.href = '../../api/sunat/exportar_sire_excel.php?' + params.toString();
    });
  };

  function mostrarProceso() {
    if (estadoOcultoPorUsuario) return;
    const estabaOculto = proceso.hidden;
    proceso.hidden = false;
    botonEstado?.setAttribute('aria-expanded', 'true');
    if (botonEstado) botonEstado.textContent = 'Ocultar estado de consultas';
    if (estabaOculto) proceso.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function mensaje(texto) {
    mostrarProceso();
    estado.textContent = texto;
  }
  function insertarEstado(etiqueta, resultado, clase, tarea = null) {
    const elemento = document.createElement('li');
    elemento.className = clase;
    const mesNumero = Number(tarea?.mesConsulta?.slice(4) || (etiqueta.match(/(0[1-9]|1[0-2])\/20\d{2}$/) || [])[1] || 0);
    if (mesNumero >= 1 && mesNumero <= 12) elemento.value = mesNumero;
    const titulo = document.createElement('strong');
    titulo.textContent = etiqueta + ' — ';
    elemento.append(titulo, document.createTextNode(resultado));
    if (tarea && consultar.dataset.soloLectura !== '1') {
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'reference-btn sire-reintentar-mes';
      boton.textContent = `Reintentar solo mes ${mesNumero}`;
      boton.disabled = trabajando;
      boton.addEventListener('click', () => {
        if (trabajando) return;
        cliente.value = String(tarea.opcion.value);
        mes.value = tarea.mesConsulta.slice(4);
        anio.value = tarea.mesConsulta.slice(0, 4);
        actualizarExcel();
        $('rceConsultaFormulario')?.requestSubmit();
      });
      elemento.append(document.createTextNode(' · '), boton);
    }
    lista.append(elemento);
  }
  function progreso(revisados, total, importados, errores, sinConfirmar = 0) {
    const porcentaje = total ? Math.round(revisados * 100 / total) : 0;
    barra.value = porcentaje;
    contadores.textContent = `${revisados}/${total} períodos revisados · ${importados} consultas completadas · ${errores} errores · ${sinConfirmar} sin confirmar`;
  }
  botonEstado?.addEventListener('click', () => {
    if (!proceso.hidden) {
      estadoOcultoPorUsuario = true;
      proceso.hidden = true;
      botonEstado.textContent = 'Ver estado de consultas';
      botonEstado.setAttribute('aria-expanded', 'false');
    } else {
      estadoOcultoPorUsuario = false;
      mostrarProceso();
      if (!trabajando && !lista.children.length) estado.textContent = 'No hay una consulta en curso en esta pantalla.';
    }
  });

  recuperarPendientes?.addEventListener('click', () => {
    if (trabajando || consultar.dataset.soloLectura === '1') return;
    const tareas = registroPendientes?.seleccion() || [];
    if (!tareas.length) return;
    recuperacionSolicitada = tareas;
    formulario?.requestSubmit();
  });
  pausar?.addEventListener('click', () => {
    if (!trabajando) return;
    if (reanudarPausa) {
      const seguir = reanudarPausa;
      reanudarPausa = null;
      pausaSolicitada = false;
      pausar.textContent = 'Pausar después del mes actual';
      seguir();
      mensaje('Continuando con el próximo mes pendiente.');
      return;
    }
    pausaSolicitada = !pausaSolicitada;
    pausar.textContent = pausaSolicitada ? 'Cancelar pausa' : 'Pausar después del mes actual';
    mensaje(pausaSolicitada
      ? 'Se pausará al terminar la consulta actual. Los meses restantes quedarán disponibles para recuperar.'
      : 'Pausa cancelada. El lote continuará normalmente.');
  });
  async function esperarPausa() {
    if (!pausaSolicitada) return;
    pausar.textContent = 'Reanudar consultas';
    mensaje('Consulta pausada entre meses. Pulsa «Reanudar consultas» para continuar.');
    await new Promise(resolve => { reanudarPausa = resolve; });
  }

  function cambioSeleccion() {
    actualizarExcel();
    if (trabajando && avisoSeleccion) {
      avisoSeleccion.hidden = false;
      avisoSeleccion.textContent = 'Cambiaste los filtros. El lote actual sigue con su selección original; cuando termine, pulsa «Consultar» para aplicar los nuevos filtros.';
    }
  }
  cliente.addEventListener('change', cambioSeleccion);
  mes.addEventListener('change', cambioSeleccion);
  anio.addEventListener('change', cambioSeleccion);
  excluir?.addEventListener('change', () => {
    actualizarExcel();
    if (trabajando) cambioSeleccion();
    else verSeleccion();
  });
  actualizarExcel();
  $('rceConsultaFormulario')?.addEventListener('submit', async event => {
    event.preventDefault();
    if (trabajando) return;
    estadoOcultoPorUsuario = false;
    trabajando = true;
    consultar.disabled = true;
    consultar.textContent = 'Consultando…';
    consultar.title = 'La consulta actual sigue en orden; no se iniciará otro lote simultáneo.';
    if (avisoSeleccion) avisoSeleccion.hidden = true;
    barra.hidden = false;
    lista.replaceChildren();
    progreso(0, 0, 0, 0);
    const loteRecuperacion = recuperacionSolicitada;
    recuperacionSolicitada = null;
    const periodoElegido = periodo();
    const clienteElegido = String(cliente.value);
    const anioElegido = String(anio.value);
    const anualElegido = esAnual();
    const opciones = (Number(cliente.value) > 0 ? [cliente.selectedOptions[0]] : elementosClientes())
      .map(opcion => ({value: opcion.value, nombre: nombre(opcion)}));
    try {
      if (!opciones.length) throw new Error('No hay empresas disponibles para consultar con tu cuenta.');
      if (!/^20\d{2}(00|0[1-9]|1[0-2])$/.test(periodoElegido)) throw new Error('Selecciona un año y mes válidos.');
      mensaje('Preparando consulta oficial a SUNAT SIRE...');
      const mesActual = fechaLima().slice(0, 7).replace('-', '');
      if (!anualElegido && periodoElegido > mesActual && !loteRecuperacion) {
        mensaje('El mes aún no comienza en Perú. Selecciona un período vigente para consultar SUNAT.');
        return;
      }
      const anioConsultado = Number(anioElegido);
      const anioVigente = Number(mesActual.slice(0, 4));
      if (anioConsultado < 2021 || anioConsultado > anioVigente) throw new Error('El año debe estar entre 2021 y el año actual de Perú.');
      const mesesElegidos = anualElegido
        ? Array.from({length: anioConsultado === anioVigente ? Number(mesActual.slice(4)) : 12}, (_, indice) => anioElegido + String(indice + 1).padStart(2, '0'))
        : [periodoElegido];
                                                                                    
      const tareas = loteRecuperacion || opciones.flatMap(opcion => mesesElegidos.map(mesConsulta => ({opcion, mesConsulta})));
                                                                                             
      const pendientes = tareas.filter(tarea => tarea.mesConsulta <= mesActual);
      if (!pendientes.length) {
        mensaje('Los períodos seleccionados aún no están disponibles para consultar a SUNAT.');
        return;
      }
      if (consultar.dataset.soloLectura === '1') {
        mensaje('Tu cuenta no tiene permiso para ejecutar consultas SIRE.');
        return;
      }
      mensaje(loteRecuperacion
        ? `Recuperando únicamente ${pendientes.length} mes(es) que quedaron sin completar del año ${anioElegido}. Los demás meses no se volverán a solicitar.`
        : `Consultando ${pendientes.length} período(s) directamente a SUNAT SIRE, uno por uno.`);
      if (pendientes.length > 1 && !window.confirm(loteRecuperacion
        ? `Compras RCE: recuperar solo ${pendientes.length} meses pendientes de ${anioElegido}, sin repetir los que ya terminaron. ¿Continuar?`
        : `Compras RCE: ${pendientes.length} consultas individuales (${mesesElegidos.length} mes(es) por empresa, ${opciones.length} empresa(s)). Todas se solicitarán a SUNAT. Pueden tardar varios minutos. ¿Iniciar?`)) {
        mensaje('La consulta a SUNAT fue cancelada.');
        return;
      }
      registroPendientes?.marcarTodos(pendientes);
      if (pausar) { pausar.hidden = pendientes.length <= 1; pausar.textContent = 'Pausar después del mes actual'; }
      let importados = 0, errores = 0, sinConfirmar = 0;
      progreso(0, pendientes.length, importados, errores);
      for (const tarea of pendientes) {
        await esperarPausa();
        const etiqueta = tarea.opcion.nombre + ' · ' + tarea.mesConsulta.slice(4) + '/' + tarea.mesConsulta.slice(0, 4);
        mensaje(`Consultando mes ${Number(tarea.mesConsulta.slice(4))} de ${tarea.mesConsulta.slice(0, 4)} · ${tarea.opcion.nombre} (${importados + errores + sinConfirmar + 1}/${pendientes.length})...`);
        try {
          const resultado = await AsesconSireSync.sincronizarLibro(Number(tarea.opcion.value), 'RCE', tarea.mesConsulta, paso => {
            estado.textContent = etiqueta + ' · ' + paso;
          });
          if (resultado?.descargado !== true || resultado?.procesado !== true
              || !Number.isInteger(Number(resultado.cantidad_registros)) || Number(resultado.cantidad_registros) < 0
              || (Number(resultado.cantidad_registros) === 0 && resultado.sin_comprobantes !== true)) {
            throw new Error('El servidor no confirmó una importación válida de RCE. No se marcará la empresa como procesada.');
          }
          importados++;
          registroPendientes?.resuelto(tarea);
          insertarEstado(etiqueta, resultado.sin_comprobantes
            ? 'Propuesta de SUNAT validada: 0 comprobantes (no significa presentación oficial).'
            : `${Number(resultado.cantidad_registros || 0)} comprobantes importados`, 'rce-ok');
        } catch (error) {
          if (error.pendiente === true) {
            sinConfirmar++;
            registroPendientes?.marcarTodos([tarea], 'sin_confirmar');
            insertarEstado(etiqueta, `${String(error.message || error)} · Mes ${Number(tarea.mesConsulta.slice(4))} pendiente de confirmar (no se considera vacío). Puedes reintentar únicamente este mes.`, 'rce-pendiente', tarea);
          } else {
            errores++;
            registroPendientes?.marcarTodos([tarea], 'error');
            insertarEstado(etiqueta, `${String(error.message || error)} · No se recibió una confirmación válida. Puedes reintentar únicamente este mes.`, 'rce-error', tarea);
          }
        }
        progreso(importados + errores + sinConfirmar, pendientes.length, importados, errores, sinConfirmar);
        if (pendientes.length > 1) await new Promise(r => setTimeout(r, 1200));
      }
      if (sinConfirmar || errores) {
        mensaje(`Se consultaron ${pendientes.length} períodos sin detener el lote: ${importados} completados, ${errores} con error y ${sinConfirmar} sin confirmar. Puedes usar «Reintentar solo mes» en los que falten.`);
      } else {
        mensaje('SUNAT confirmó todos los períodos solicitados. Se actualizará la vista para mostrar los resultados.');
      }
      if (importados > 0 && errores === 0 && sinConfirmar === 0) {
        setTimeout(() => {
          if (!trabajando && periodo() === periodoElegido && String(cliente.value) === clienteElegido) verSeleccion();
        }, 1500);
      }
    } catch (error) {
      mensaje('No se pudo preparar la consulta: ' + String(error.message || error));
    } finally {
      trabajando = false;
      pausaSolicitada = false;
      reanudarPausa = null;
      if (pausar) { pausar.hidden = true; pausar.textContent = 'Pausar después del mes actual'; }
      consultar.disabled = consultar.dataset.soloLectura === '1';
      consultar.textContent = 'Consultar';
      consultar.title = '';
      lista.querySelectorAll?.('.sire-reintentar-mes').forEach(boton => { boton.disabled = false; });
      registroPendientes?.actualizar();
      if (avisoSeleccion && !avisoSeleccion.hidden) avisoSeleccion.textContent = 'La consulta terminó. Pulsa «Consultar» para aplicar aquí los filtros que cambiaste.';
    }
  });

  const tabs = $('rceResultados');
  function abrirDetalle(tipo = '') {
    if (!tabs) return;
    const global = cliente.value === '0';
    if (global && tipo) {
      const url = urlVista();
      url.searchParams.set('vista', 'detalle');
      url.searchParams.set('tipo', tipo);
      window.location.assign(url.toString());
      return;
    }
    tabs.querySelectorAll('[data-tab-target]').forEach(b => b.classList.toggle('active', b.dataset.tabTarget === 'rce-detalle'));
    tabs.querySelectorAll('[data-tab-panel]').forEach(p => { p.hidden = p.dataset.tabPanel !== 'rce-detalle'; });
    tabs.querySelectorAll('.sire-detail-row').forEach(fila => { fila.hidden = !!tipo && fila.dataset.sireTipo !== tipo; });
    const limpiar = tabs.querySelector('.sire-clear-filter');
    if (limpiar) { limpiar.hidden = !tipo; limpiar.textContent = tipo ? 'Ver todos · ' + tipo : 'Ver todos'; }
    tabs.querySelector('[data-tab-panel="rce-detalle"]')?.scrollIntoView({behavior:'smooth',block:'start'});
  }
  tabs?.querySelectorAll('[data-tab-target]').forEach(btn => btn.addEventListener('click', () => {
    tabs.querySelectorAll('[data-tab-target]').forEach(b => b.classList.toggle('active', b === btn));
    tabs.querySelectorAll('[data-tab-panel]').forEach(p => { p.hidden = p.dataset.tabPanel !== btn.dataset.tabTarget; });
  }));
  tabs?.querySelectorAll('[data-open-sire-detail]').forEach(fila => {
    const abrir = () => abrirDetalle(fila.dataset.sireTipo || '');
    fila.addEventListener('click', abrir);
    fila.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(); }
    });
  });
  tabs?.querySelector('.sire-clear-filter')?.addEventListener('click', () => abrirDetalle());
})();
