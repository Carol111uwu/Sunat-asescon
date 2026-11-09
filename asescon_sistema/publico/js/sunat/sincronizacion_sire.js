(function (global) {
  'use strict';

  const endpoint = '../../api/sunat/operaciones_sire.php';
  const endpointAutenticacion = '../../api/sunat/autenticacion.php';
  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

  function obtenerCsrfSire() {
    const meta = document.querySelector('meta[name="asescon-csrf-sire"]');
    const token = meta?.content?.trim() || '';
    if (!token) throw new Error('No se encontró el token de seguridad SIRE.');
    return token;
  }

  async function leerRespuesta(res, contexto = 'SIRE') {
    const texto = await res.text();
    let json = null;
    if (texto.trim() !== '') {
      try { json = JSON.parse(texto); } catch (_) { json = null; }
    }
    if (!json || typeof json !== 'object') {
      if (res.status === 401) throw new Error('Tu sesión terminó. Vuelve a iniciar sesión.');
      if (res.status === 403) throw new Error('El servidor denegó la solicitud de ' + contexto + ' (HTTP 403).');
      if (res.status === 404) throw new Error('No se encontró la ruta interna de ' + contexto + ' (HTTP 404).');
      if (res.status === 504) throw new Error('La respuesta tardó demasiado (HTTP 504). La consulta podría seguir procesándose en el servidor.');
      if (res.status >= 500) throw new Error('El servidor falló al procesar ' + contexto + ' (HTTP ' + res.status + ').');
      throw new Error('ASESCON recibió una respuesta no válida de ' + contexto + ' (HTTP ' + res.status + ').');
    }
    if (!res.ok || !json.ok) {
      const codigo = json.codigo_sunat ? ' [' + json.codigo_sunat + ']' : '';
      const temporal = [429, 502, 503, 504].includes(res.status) ? ' (HTTP ' + res.status + ')' : '';
      throw new Error((json.mensaje || 'SUNAT SIRE rechazó la solicitud.') + codigo + temporal);
    }
    return json;
  }

  async function postJson(url, body, contexto, timeoutMs) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': obtenerCsrfSire()
        },
        signal: ctrl.signal,
        body: JSON.stringify(body)
      });
      return await leerRespuesta(res, contexto);
    } finally {
      clearTimeout(timer);
    }
  }

  async function post(accion, payload, timeoutMs = 45000) {
    const json = await postJson(endpoint, {accion, ...payload}, 'SIRE', timeoutMs);
    return json.data || {};
  }

  async function postAutenticacion(payload, timeoutMs = 45000) {
    const json = await postJson(endpointAutenticacion, payload, 'Validación SIRE', timeoutMs);
    return json.data || json;
  }

  function esRateLimit(error) {
    return /(?:HTTP\s*429|429|limit[oó].*consult|demasiadas.*consult)/i.test(String(error && error.message || error || ''));
  }
  function esTemporal(error) {
    return error?.name === 'AbortError' || error?.name === 'TypeError' || esRateLimit(error)
      || /HTTP\s*(?:502|503|504)\b/i.test(String(error?.message || error || ''));
  }

  function pendiente(mensaje) {
    const error = new Error(mensaje);
    error.pendiente = true;
    return error;
  }

  async function ticketRegistrado(idCliente, libro, periodo) {
    return post('ticket_reciente', {id_cliente:Number(idCliente), libro, periodo}, 18000);
  }

  function resultadoLocal(ticket, datos) {
    if (String(datos?.num_ticket || '') !== String(ticket) || datos?.procesado !== true) return null;
    const cantidad = Number(datos.cantidad_registros);
    if (!Number.isInteger(cantidad) || cantidad < 0) return null;
    return {
      descargado:true, procesado:true, ticket, cantidad_registros:cantidad,
      sin_comprobantes:cantidad === 0 && datos.sin_comprobantes === true,
      mensaje:'El servidor confirmó que el ticket ya se procesó y guardó.'
    };
  }

  async function sincronizarLibro(idCliente, libro, periodo, onStatus) {
    if (!idCliente) throw new Error('Selecciona una empresa.');
    if (!/^20\d{4}$/.test(String(periodo || ''))) throw new Error('El período debe tener formato AAAAMM.');
    let anterior = null;
    let anteriorVerificado = false;
    try {
      anterior = await ticketRegistrado(idCliente, libro, periodo);
      anteriorVerificado = true;
    } catch (error) {
      if (!esTemporal(error)) throw error;
    }
                                                                                
    let solicitud = anterior?.reutilizable === true
      ? {...anterior, reutilizado:true}
      : null;
    if (!solicitud) try {
      onStatus && onStatus(`Solicitando propuesta ${libro} a SUNAT...`);
      solicitud = await post('solicitar', {id_cliente: Number(idCliente), libro, periodo}, 45000);
    } catch (error) {
      if (!esTemporal(error)) throw error;
                                                                         
                                                                             
      for (let intento = 1; intento <= 10; intento++) {
        onStatus && onStatus(`${libro}: sin confirmación de la solicitud. Esperando el MISMO mes (${intento}/10); no se generarán otros tickets...`);
        await sleep(6000);
        try {
          const reciente = await ticketRegistrado(idCliente, libro, periodo);
          if (reciente?.num_ticket) {
                                                                                   
            if (anteriorVerificado && anterior?.procesado === true
                && String(anterior.num_ticket) === String(reciente.num_ticket)) continue;
            if (!anteriorVerificado && reciente.procesado === true) continue;
            solicitud = {...reciente, reutilizado:true};
            break;
          }
        } catch (consultaError) {
          if (!esTemporal(consultaError)) throw consultaError;
        }
      }
      if (!solicitud) throw pendiente(`${libro}: SUNAT o el servidor no confirmaron el ticket de ${periodo}. Este mes queda pendiente; no se volverá a solicitar ese mismo mes automáticamente. El lote puede continuar con los demás meses.`);
    }
    const ticket = solicitud.num_ticket;
    if (solicitud.reutilizado) onStatus && onStatus(`${libro}: reutilizando ticket ${ticket} de este mes; no se solicitó otro...`);
    if (!ticket) throw new Error('SUNAT no devolvió ticket para ' + libro + '.');

                                                                                
    const yaProcesado = resultadoLocal(ticket, solicitud);
    if (yaProcesado) return yaProcesado;

    let estado = null;
    for (let i = 1; i <= 12; i++) {
      onStatus && onStatus(`${libro}: ticket ${ticket} · esperando archivo (${i}/12)...`);
      await sleep(i === 1 ? 2500 : 4000);
      try {
        estado = await post('estado', {id_cliente: Number(idCliente), libro, periodo, ticket}, 35000);
      } catch (e) {
        if (esTemporal(e) && i < 12) {
          onStatus && onStatus(`${libro}: respuesta temporalmente no disponible. Se reintentará el MISMO ticket sin solicitar otro...`);
          await sleep(esRateLimit(e) ? 6500 : 8000);
          continue;
        }
        if (esTemporal(e)) throw pendiente(`${libro}: la respuesta del ticket ${ticket} sigue pendiente (${String(e.message || e)}). Se conservará este mes como no confirmado y se continuará con los demás.`);
        throw e;
      }
      if (estado.terminado) break;
    }
    if (!estado || !estado.terminado) {
      throw pendiente(`${libro}: SUNAT todavía procesa el ticket ${ticket}. Este mes queda sin confirmar y se puede retomar después con el mismo ticket; se continuará con los otros meses.`);
    }
    onStatus && onStatus(`${libro}: descargando e importando comprobantes reales...`);
    let descarga;
                                                                           
                                                                             
    for (let intento = 0; intento < 3; intento++) {
      try {
        descarga = await post('descargar', {id_cliente: Number(idCliente), libro, periodo, ticket}, 90000);
        break;
      } catch (error) {
        if (!esTemporal(error)) throw error;
        try {
          const local = resultadoLocal(ticket, await ticketRegistrado(idCliente, libro, periodo));
          if (local) return local;
        } catch (_) {                                                       }
        if (intento === 2) throw pendiente(`${libro}: la descarga del ticket ${ticket} no se confirmó. Se conserva lo anterior; este mes queda pendiente y se continuará con los demás.`);
        onStatus && onStatus(`${libro}: descarga sin confirmación. Reintentando el ticket existente (${intento + 1}/2)...`);
        await sleep((intento + 1) * 9000);
      }
    }
    if (!descarga?.descargado) throw pendiente(`${libro}: el ticket ${ticket} todavía no está listo para descargar. El mes queda pendiente sin perder datos; se continuará con los otros meses.`);
    onStatus && onStatus(`${libro}: consultando inconsistencias oficiales del período...`);
    try {
      descarga.inconsistencias = {ok:true, data:await post('inconsistencias', {id_cliente:Number(idCliente), libro, periodo, tipo_resumen:1}, 45000)};
    } catch (error) {
      descarga.inconsistencias = {ok:false, mensaje:String(error?.message || error || 'No disponible')};
    }
    return descarga;
  }

  async function sincronizarAmbos(idCliente, periodo, onStatus) {
    const rvie = await sincronizarLibro(idCliente, 'RVIE', periodo, onStatus);
    await sleep(1800);
    const rce = await sincronizarLibro(idCliente, 'RCE', periodo, onStatus);
    return {rvie, rce};
  }

  global.AsesconSireSync = {post, postAutenticacion, leerRespuesta, sincronizarLibro, sincronizarAmbos};
})(window);
