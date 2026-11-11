(() => {
  'use strict';
  const raiz = document.getElementById('rceSeleccionControles');
  if (!raiz) return;

  const endpoint = '../../api/sunat/comprobantes_rce_lote.php';
  const token = raiz.dataset.csrf;
  const casillas = Array.from(document.querySelectorAll('.rce-seleccionar-fila'));
  const marcarTodo = document.getElementById('rceMarcarTodos');
  const limpiar = document.getElementById('rceLimpiarSeleccion');
  const cantidad = document.getElementById('rceCantidadSeleccionados');
  const pdf = document.getElementById('rceLotePdf');
  const excel = document.getElementById('rceLoteExcel');
  const enviar = document.getElementById('rceLoteEnviar');
  const opciones = document.getElementById('rceEnviarOpciones');
  const estado = document.getElementById('rceSeleccionEstado');
  const correo = document.getElementById('rceCorreoLote');
  const formCorreo = document.getElementById('rceCorreoLoteFormulario');
  const whatsapp = document.getElementById('rceWhatsAppLote');
  const compartir = document.getElementById('rceCompartirLote');
  const descargar = document.getElementById('rceDescargarLoteWhatsapp');
  let archivoParaCompartir = null;
  let preparando = false;

  const visibles = () => casillas.filter(c => !c.closest('tr').hidden && !c.closest('tr').classList.contains('rce-oculto-filtro'));
  const seleccionadas = () => visibles().filter(c => c.checked);
  const datosSeleccionados = () => seleccionadas().map(c => ({
    cliente: Number(c.dataset.cliente),
    detalle: Number(c.dataset.detalle)
  }));

  function actualizar() {
    const actuales = visibles();
    const marcadas = seleccionadas().length;
    cantidad.textContent = marcadas === 1 ? '1 comprobante seleccionado' : `${marcadas} comprobantes seleccionados`;
    marcarTodo.checked = actuales.length > 0 && marcadas === actuales.length;
    marcarTodo.indeterminate = marcadas > 0 && marcadas < actuales.length;
    for (const boton of [limpiar, pdf, excel, enviar]) boton.disabled = marcadas === 0 || preparando;
    if (!marcadas) opciones.hidden = true;
  }

  casillas.forEach(c => c.addEventListener('change', actualizar));
  marcarTodo.addEventListener('change', () => {
    visibles().forEach(c => { c.checked = marcarTodo.checked; });
    actualizar();
  });
  limpiar.addEventListener('click', () => {
    casillas.forEach(c => { c.checked = false; });
    actualizar();
  });
  const formularioFiltros = document.getElementById('rceFiltrosDetalle');
  function aplicarFiltros() {
    if (!formularioFiltros) return;
    const filtros = new FormData(formularioFiltros);
    const buscar = String(filtros.get('proveedor') || '').trim().toLocaleLowerCase('es');
    const tipo = String(filtros.get('tipo') || '');
    const serie = String(filtros.get('serie') || '').trim().toLocaleLowerCase('es');
    const numero = String(filtros.get('numero') || '').trim().toLocaleLowerCase('es');
    const desde = String(filtros.get('desde') || '');
    const hasta = String(filtros.get('hasta') || '');
    const minimo = filtros.get('minimo') === '' ? null : Number(filtros.get('minimo'));
    const maximo = filtros.get('maximo') === '' ? null : Number(filtros.get('maximo'));
    if (minimo !== null && maximo !== null && minimo > maximo) {
      document.getElementById('rceFiltrosEstado').textContent = 'El importe mínimo no puede superar el máximo.';
      return;
    }
    for (const casilla of casillas) {
      const fila = casilla.closest('tr');
      const coincide = (!buscar || (fila.dataset.rceProveedor || '').toLocaleLowerCase('es').includes(buscar))
        && (!tipo || fila.dataset.sireTipo === tipo)
        && (!serie || (fila.dataset.rceSerie || '').toLocaleLowerCase('es').includes(serie))
        && (!numero || (fila.dataset.rceNumero || '').toLocaleLowerCase('es').includes(numero))
        && (!desde || (fila.dataset.rceFecha || '') >= desde)
        && (!hasta || (fila.dataset.rceFecha || '') <= hasta)
        && (minimo === null || Number(fila.dataset.rceTotal) >= minimo)
        && (maximo === null || Number(fila.dataset.rceTotal) <= maximo);
      fila.classList.toggle('rce-oculto-filtro', !coincide);
      if (!coincide) casilla.checked = false;
    }
    const visiblesAhora = visibles().length;
    document.getElementById('rceFiltrosEstado').textContent =
      `${visiblesAhora} comprobantes visibles con estos filtros, de ${casillas.length} cargados en la página.`;
    actualizar();
  }
  formularioFiltros?.addEventListener('submit', evento => {
    evento.preventDefault();
    aplicarFiltros();
  });
  formularioFiltros?.addEventListener('reset', () => {
    setTimeout(aplicarFiltros, 0);
  });
  enviar.addEventListener('click', () => {
    opciones.hidden = !opciones.hidden;
    enviar.setAttribute('aria-expanded', String(!opciones.hidden));
  });
  document.addEventListener('click', evento => {
    if (!evento.target.closest('#rceEnviarGrupo')) {
      opciones.hidden = true;
      enviar.setAttribute('aria-expanded', 'false');
    }
  });

  function datosFormulario(accion) {
    const seleccion = datosSeleccionados();
    if (!seleccion.length) throw new Error('Selecciona por lo menos un comprobante visible.');
    if (seleccion.length > 60) throw new Error('El máximo es 60 comprobantes por descarga o envío.');
    const datos = new FormData();
    datos.set('csrf_token', token);
    datos.set('accion', accion);
    datos.set('seleccion', JSON.stringify(seleccion));
    return datos;
  }

  async function solicitar(datos) {
    const respuesta = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', body: datos});
    if (!respuesta.ok) {
      const json = await respuesta.json().catch(() => ({}));
      throw new Error(json.mensaje || `La operación no se completó (HTTP ${respuesta.status}).`);
    }
    return respuesta;
  }

  async function archivoLote(accion) {
    const respuesta = await solicitar(datosFormulario(accion));
    const blob = await respuesta.blob();
    const filename = respuesta.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1]
      || (accion === 'excel' ? 'RCE-seleccion.xlsx' : 'RCE-PDF-seleccion.zip');
    return new File([blob], filename, {type: blob.type || (accion === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/zip')});
  }

  function guardar(archivo) {
    const url = URL.createObjectURL(archivo);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = archivo.name;
    document.body.append(enlace);
    enlace.click();
    enlace.remove();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
  }

  async function ejecutarDescarga(accion) {
    if (preparando) return;
    preparando = true;
    actualizar();
    estado.textContent = accion === 'excel' ? 'Preparando el Excel…' : 'Preparando el ZIP con los PDF…';
    try {
      guardar(await archivoLote(accion));
      estado.textContent = 'Archivo preparado. Revisa las descargas de tu navegador.';
    } catch (error) {
      estado.textContent = error.message || 'No se pudo completar la descarga.';
    } finally {
      preparando = false;
      actualizar();
    }
  }
  pdf.addEventListener('click', () => ejecutarDescarga('pdf'));
  excel.addEventListener('click', () => ejecutarDescarga('excel'));

  raiz.querySelector('[data-rce-enviar-correo]')?.addEventListener('click', () => {
    opciones.hidden = true;
    enviar.setAttribute('aria-expanded', 'false');
    formCorreo.reset();
    correo.querySelector('[data-rce-estado]').textContent = '';
    correo.querySelector('[data-rce-cantidad]').textContent = cantidad.textContent;
    correo.showModal();
  });
  formCorreo?.addEventListener('submit', async evento => {
    evento.preventDefault();
    const boton = formCorreo.querySelector('[type=submit]');
    boton.disabled = true;
    const respuesta = correo.querySelector('[data-rce-estado]');
    respuesta.textContent = 'Enviando los PDF seleccionados…';
    try {
      const datos = datosFormulario('correo');
      datos.set('destinatario', formCorreo.elements.destinatario.value.trim());
      const json = await (await solicitar(datos)).json();
      if (!json.ok) throw new Error(json.mensaje || 'El envío no se confirmó.');
      respuesta.textContent = json.mensaje;
    } catch (error) {
      respuesta.textContent = error.message || 'No se pudo confirmar el envío.';
    } finally {
      boton.disabled = false;
    }
  });

  raiz.querySelector('[data-rce-enviar-whatsapp]').addEventListener('click', async () => {
    opciones.hidden = true;
    enviar.setAttribute('aria-expanded', 'false');
    whatsapp.querySelector('[data-rce-cantidad]').textContent = cantidad.textContent;
    const mensaje = whatsapp.querySelector('[data-rce-estado]');
    archivoParaCompartir = null;
    compartir.hidden = true;
    descargar.disabled = true;
    whatsapp.showModal();
    mensaje.textContent = 'Preparando un ZIP con los PDF seleccionados…';
    try {
      archivoParaCompartir = await archivoLote('pdf');
      descargar.disabled = false;
      mensaje.textContent = 'El ZIP está listo. Puedes descargarlo y adjuntarlo en WhatsApp.';
      compartir.hidden = typeof navigator.share !== 'function' || typeof navigator.canShare !== 'function'
        || !navigator.canShare({files: [archivoParaCompartir]});
    } catch (error) {
      mensaje.textContent = error.message || 'No se pudo preparar el ZIP.';
    }
  });
  descargar.addEventListener('click', () => {
    if (archivoParaCompartir) guardar(archivoParaCompartir);
  });
  compartir.addEventListener('click', async () => {
    if (!archivoParaCompartir) return;
    try {
      await navigator.share({files: [archivoParaCompartir]});
    } catch (error) {
      if (error.name !== 'AbortError') {
        whatsapp.querySelector('[data-rce-estado]').textContent = 'Descarga el ZIP y adjúntalo manualmente en WhatsApp.';
      }
    }
  });
  for (const dialogo of [correo, whatsapp].filter(Boolean)) {
    dialogo.querySelector('[data-rce-cerrar]').addEventListener('click', () => dialogo.close());
  }
  const observer = new MutationObserver(() => {
    casillas.forEach(c => { if (c.closest('tr').hidden || c.closest('tr').classList.contains('rce-oculto-filtro')) c.checked = false; });
    actualizar();
  });
  document.querySelectorAll('.sire-detail-row').forEach(fila => observer.observe(fila, {attributes: true, attributeFilter: ['hidden']}));
  actualizar();
})();
