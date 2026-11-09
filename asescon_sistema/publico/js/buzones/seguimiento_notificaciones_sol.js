(() => {
  const dialogo = document.getElementById('visorDocumentoBuzon');
  const panel = document.getElementById('seguimientoNotificacionSol');
  if (!dialogo || !panel) return;
  const elemento = id => document.getElementById(id);
  const url = panel.dataset.url;
  const token = panel.dataset.csrf;
  let actual = null;
  let notificacionId = 0;
  let trabajando = false;
  let confirmarCierre = false;
  const avisos = () => window.asesconDescargasSol;
  const mensaje = (texto, error=false) => {
    const caja = elemento('buzonSolMensajeSeguimiento');
    caja.textContent = texto || '';
    caja.style.backgroundColor = error ? '#fff1f1' : '';
    caja.dataset.tipo = error ? 'error' : 'exito';
    if(texto && notificacionId) avisos()?.mostrar(notificacionId,texto,error?'error':'exito',error);
  };
  async function enviar(accion, datos={}) {
    if (trabajando) return null;
    trabajando = true;
    const objetivo=notificacionId;
    if(objetivo) avisos()?.mostrar(objetivo,accion==='pdf'?'Comprobando y solicitando el PDF oficial…':'Procesando la solicitud…','progreso',true);
    const entrada = new URLSearchParams({accion,csrf_token:token,id_documento:String(notificacionId),...datos});
    try {
      const respuesta = await fetch(url,{method:'POST',body:entrada,credentials:'same-origin',headers:{Accept:'application/json'}});
      if(!(respuesta.headers.get('content-type')||'').includes('application/json')) throw new Error('El servidor no respondió correctamente. Comprueba tu sesión e inténtalo otra vez.');
      const contenido = await respuesta.json();
      if (!respuesta.ok || !contenido.ok) throw new Error(contenido.mensaje || 'La operación no pudo completarse.');
      mensaje(contenido.mensaje);
      return contenido;
    } catch (error) {
      mensaje(error.message || 'No se pudo completar la operación.',true);
      return null;
    } finally { trabajando = false; }
  }
  async function detalle(id,accionInicial='ver') {
    const destino = new URL(dialogo.dataset.detalleUrl,location.href);
    destino.searchParams.set('buzon','SOL');destino.searchParams.set('id_documento',String(id));
    const r = await fetch(destino,{credentials:'same-origin',headers:{Accept:'application/json'}});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.mensaje || 'No se pudo recuperar la notificación.');
    return j.data;
  }
  function pintar(d) {
    actual = d;notificacionId = Number(d.id_documento);
    const seg = d.seguimiento || {};
    const habilitado = Boolean(seg.habilitado);
    elemento('buzonSolInstalarSeguimiento').hidden = habilitado;
    elemento('buzonSolSeguimientoActivo').hidden = !habilitado;
    const principal = Boolean(seg.puede_asignar);
    elemento('buzonSolHabilitarSeguimiento').hidden = !seg.puede_habilitar;
    if (!habilitado) return;
    const caso = seg.caso || {};
    const cerrado = caso.estado === 'CERRADO';
    elemento('buzonSolEstadoCaso').textContent = cerrado ? 'Caso cerrado' : 'Caso abierto';
    elemento('buzonSolResponsableActual').textContent = 'Responsable: ' + (caso.responsable || 'Sin asignar');
    elemento('buzonSolAsignacion').hidden = !principal;
    elemento('buzonSolControlesCierre').hidden = !principal;
    elemento('buzonSolCambiarEstado').textContent = cerrado ? 'Reabrir caso' : 'Cerrar caso';
    elemento('buzonSolCambiarEstado').dataset.accion = cerrado ? 'reabrir' : 'cerrar';
    confirmarCierre=false;
    const sel = elemento('buzonSolResponsable');sel.replaceChildren();
    const opcion = (value,texto) => {const o=document.createElement('option');o.value=String(value);o.textContent=texto;sel.appendChild(o);};
    opcion('','Sin asignar');
    for (const u of seg.responsables || []) opcion(u.id_usuario,u.usuario);
    sel.value = caso.id_responsable ? String(caso.id_responsable) : '';
    const historial = elemento('buzonSolAvances');historial.replaceChildren();
    for (const avance of seg.avances || []) {
      const entrada = document.createElement('article');entrada.className='buzon-caso-entrada';
      const nombre = document.createElement('b');nombre.textContent=avance.usuario || 'Usuario';
      const fecha = document.createElement('time');fecha.textContent=avance.creado_en || '';
      const texto = document.createElement('p');texto.textContent=avance.contenido || '';
      entrada.append(nombre,fecha,texto);historial.appendChild(entrada);
    }
    if (!historial.childElementCount) {
      const vacio = document.createElement('p');vacio.textContent='Todavía no hay notas de seguimiento.';historial.appendChild(vacio);
    }
    elemento('buzonSolFormularioNota').hidden = !seg.puede_reportar;
    const fila = document.querySelector(`[data-id-notificacion="${notificacionId}"]`);
    const etiqueta=fila?.querySelector('[data-estado-caso]');
    const botonCaso=fila?.querySelector('.buzon-sol-cerrar-caso');
    if (botonCaso && principal){const span=botonCaso.querySelector('span');if(span)span.textContent=cerrado?'Reabrir caso':'Cerrar caso';}
    if (etiqueta && seg.caso) etiqueta.textContent = cerrado ? 'Caso cerrado' : (caso.id_responsable ? 'En seguimiento' : 'Por revisar');
  }
  async function actualizar() {
    const id = notificacionId;
    if (!id) return;
    try {const d=await detalle(id);if(id===notificacionId)pintar(d);}catch(e){mensaje(e.message,true);}
  }
  dialogo.addEventListener('asescon:detalle-cargado',event => {
    if(dialogo.dataset.buzon!=='SOL')return;
    mensaje('');pintar(event.detail.notificacion);
    const accion = event.detail.accionInicial;
    if (accion==='caso')panel.scrollIntoView({behavior:'smooth',block:'nearest'});
  });
  elemento('buzonSolHabilitarSeguimiento').addEventListener('click', async()=>{
    if(await enviar('habilitar'))await actualizar();
  });
  elemento('buzonSolGuardarResponsable').addEventListener('click',async()=>{
    if(await enviar('asignar',{id_responsable:elemento('buzonSolResponsable').value}))await actualizar();
  });
  elemento('buzonSolFormularioNota').addEventListener('submit',async(e)=>{
    e.preventDefault();const texto=elemento('buzonSolNota').value;
    if(!texto.trim()){mensaje('Escribe una nota antes de guardarla.',true);return;}
    if(await enviar('nota',{contenido:texto})){
      elemento('buzonSolNota').value='';await actualizar();
    }
  });
  elemento('buzonSolCambiarEstado').addEventListener('click',async()=>{
    const accion=elemento('buzonSolCambiarEstado').dataset.accion;
    if(accion==='cerrar'&&!confirmarCierre){
      confirmarCierre=true;
      elemento('buzonSolCambiarEstado').textContent='Confirmar cierre del caso';
      mensaje('Pulsa «Confirmar cierre del caso» para cerrarlo solo dentro de ASESCON.');
      return;
    }
    confirmarCierre=false;
    if(await enviar(accion))await actualizar();
  });
  const formCorreo = elemento('visorDocumentoSeccionCorreo')?.querySelector('form');
  formCorreo?.addEventListener('submit',async evento => {
    evento.preventDefault();
    if(!notificacionId)return;
    const destinatario=elemento('visorDocumentoDestinatario').value;
    const mensajeTexto=formCorreo.querySelector('textarea[name="mensaje"]').value;
    const boton=formCorreo.querySelector('button[type="submit"]');
    boton.disabled=true;boton.textContent='Enviando correo…';
    const resultado=await enviar('correo',{destinatario,mensaje:mensajeTexto});
    const zona=elemento('visorDocumentoMensajeCorreo');
    zona.hidden=false;zona.textContent=resultado?.mensaje||'No se pudo confirmar el envío del correo.';
    zona.dataset.tipo=resultado?'exito':'error';
    boton.disabled=false;boton.textContent='Confirmar envío';
  });
  const formPdf=dialogo.querySelector('form input[value="obtener_pdf"]')?.closest('form');
  async function solicitarDocumento(id,boton=null){
    if(trabajando || !id)return;
    notificacionId=Number(id);
    if(boton){boton.disabled=true;boton.setAttribute('aria-busy','true');}
    const resultado=await enviar('pdf');
    if(boton){boton.disabled=false;boton.removeAttribute('aria-busy');}
    if(!resultado)return;
    if(resultado.data?.disponible && resultado.data.url){
      try{await avisos()?.iniciarDescargaDisponible(id,resultado.data.url);}
      catch(e){avisos()?.mostrar(id,'Error al descargar: '+e.message,'error',true);}
    }else{
      avisos()?.mostrar(id,resultado.mensaje||'La descarga quedó en cola.','progreso',true);
      avisos()?.vigilar(id);
    }
  }
  formPdf?.addEventListener('submit',async evento => {
    evento.preventDefault();if(!notificacionId)return;
    await solicitarDocumento(notificacionId,formPdf.querySelector('button[type="submit"]'));
  });
  document.querySelectorAll('[data-buzon-pdf]').forEach(b=>b.addEventListener('click',()=>solicitarDocumento(Number(b.dataset.buzonPdf),b)));
})();
