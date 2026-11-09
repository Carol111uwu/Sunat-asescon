(() => {
 const modal=document.getElementById('visorDocumentoBuzon');if(!modal)return;
 const get=id=>document.getElementById(id);let version=0,botonOrigen=null,resumen='',archivoUrl=null,temporizadorPdf=null,temporizadorVistaPdf=null;
 const texto=(id,valor)=>{get(id).textContent=String(valor||'—');};
 const carga=(id,activa)=>{const elemento=get(id);if(elemento)elemento.hidden=!activa;};
 async function fetchConLimite(url,opciones={},segundos=15){
  const control=new AbortController();
  const limite=setTimeout(()=>control.abort(),segundos*1000);
  try{return await fetch(url,{...opciones,signal:control.signal});}
  catch(error){if(error?.name==='AbortError')throw new Error('El servidor no respondió en '+segundos+' segundos. Vuelve a intentar; no es necesario esperar horas.');throw error;}
  finally{clearTimeout(limite);}
 }
 function mostrarDetalleSol(d){
  if(modal.dataset.buzon!=='SOL')return;
  const contenido=d.contenido_oficial;
  const solicitud=d.solicitud_detalle||{};
  const tieneContenido=!!(contenido&&contenido.contenido);
  const panel=get('visorSolDetalleTexto');panel.hidden=!tieneContenido;
  panel.textContent=tieneContenido?contenido.contenido:'';
  const lista=get('visorSolListaDocumentos');lista.replaceChildren();
  const nombres=Array.isArray(contenido?.archivos)?contenido.archivos:[];
  for(const nombre of nombres){const li=document.createElement('li');li.textContent=String(nombre);lista.append(li);}
  get('visorSolDocumentos').hidden=!nombres.length;
  const situacion=tieneContenido?'Contenido oficial recuperado el '+String(contenido.obtenido_en||'').replace(' ',' a las ')+'.':
      (solicitud.mensaje||'Todavía no se ha consultado el contenido oficial de esta notificación.');
  texto('visorSolDetalleEstado',situacion);
  const boton=get('visorSolConsultarContenido');
  if(boton)boton.disabled=['PENDIENTE','PROCESANDO'].includes(solicitud.estado);
 }
 function mostrarPdf(d){
  archivoUrl=d.archivo_url?new URL(d.archivo_url,location.href):null;
  if(archivoUrl&&archivoUrl.origin!==location.origin)throw new Error('La dirección del documento no es válida.');
  get('visorDocumentoAccionesPdf').hidden=!archivoUrl;
  get('visorDocumentoImprimir').disabled=!archivoUrl;
  const solicitud=d.solicitud_pdf||{};
  const mensaje=archivoUrl?'PDF disponible. Pulsa «Descargar PDF» para guardarlo o «Ver PDF aquí» para previsualizarlo.':
      (solicitud.mensaje || 'Aún no se ha descargado este documento desde SUNAT.');
  texto('visorDocumentoNota',mensaje);
  get('visorDocumentoNota').dataset.tipo=solicitud.estado==='ERROR'?'error':(['PENDIENTE','PROCESANDO'].includes(solicitud.estado)?'progreso':'info');
  if(archivoUrl){
    const descarga=new URL(archivoUrl);descarga.searchParams.set('descargar','1');get('visorDocumentoDescargar').href=descarga.href;
    get('visorDocumentoAbrir').textContent='Ver PDF aquí';
  }else{
    get('visorDocumentoPdf').hidden=true;get('visorDocumentoPdf').removeAttribute('src');
  }
 }
 async function abrir(id,boton,accionInicial=''){
  clearTimeout(temporizadorPdf);const actual=++version;botonOrigen=boton;get('visorDocumentoContenido').hidden=true;texto('visorDocumentoEstado','Cargando notificación…');get('visorDocumentoEstado').hidden=false;carga('visorDocumentoSkeleton',true);carga('visorDocumentoPdfSkeleton',false);
  archivoUrl=null;get('visorDocumentoImprimir').disabled=true;get('visorDocumentoPdf').removeAttribute('src');get('visorDocumentoPdf').hidden=true;
  if(!modal.open)modal.showModal();get('visorDocumentoSeccionCorreo').open=false;get('visorDocumentoSeccionCompartir').open=false;
  try{
   const url=new URL(modal.dataset.detalleUrl,location.href);url.searchParams.set('buzon',modal.dataset.buzon);url.searchParams.set('id_documento',id);
   const r=await fetchConLimite(url,{credentials:'same-origin',headers:{Accept:'application/json'}});if(!(r.headers.get('content-type')||'').includes('application/json'))throw new Error('El servidor no devolvió el detalle esperado.');const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.mensaje||'No se pudo cargar.');if(actual!==version||!modal.open)return;
   const d=j.data;texto('visorDocumentoTitulo',d.asunto);texto('visorDocumentoCliente',d.cliente);texto('visorDocumentoRuc',d.ruc);texto('visorDocumentoFecha',d.fecha);texto('visorDocumentoTipo',modal.dataset.buzon==='SOL'?(d.categoria||'Sin categoría'):(d.tipo+' · '+d.categoria));texto('visorDocumentoRevision',d.estado==='REVISADO'?'Revisado':'Pendiente de revisión');texto('visorDocumentoResumen',d.contenido);
   modal.querySelectorAll('[data-visor-id]').forEach(x=>x.value=d.id_documento);modal.querySelectorAll('[data-visor-editar]').forEach(x=>x.hidden=!d.puede_editar);modal.querySelectorAll('[data-visor-cliente]').forEach(x=>x.hidden=!d.puede_editar||!d.tiene_cliente);get('visorDocumentoDestinatario').value=d.correo||'';
   mostrarDetalleSol(d);mostrarPdf(d);if(!archivoUrl&&['PENDIENTE','PROCESANDO'].includes(d.solicitud_pdf?.estado))window.asesconDescargasSol?.vigilar(id);
   resumen=`Notificación ${modal.dataset.buzon}\nCliente: ${d.cliente}\nRUC: ${d.ruc}\nAsunto: ${d.asunto}\nFecha: ${d.fecha}`;
   get('visorDocumentoGmail').href='https://mail.google.com/mail/?view=cm&fs=1&to='+encodeURIComponent(d.correo||'')+'&su='+encodeURIComponent('Notificación '+modal.dataset.buzon)+'&body='+encodeURIComponent(resumen);
   let telefono=String(d.celular||'').replace(/\D/g,'');if(/^9\d{8}$/.test(telefono))telefono='51'+telefono;
   get('visorDocumentoWhatsapp').href='https://wa.me/'+telefono+'?text='+encodeURIComponent(resumen);
   get('visorDocumentoEstado').hidden=true;get('visorDocumentoContenido').hidden=false;carga('visorDocumentoSkeleton',false);
   modal.dispatchEvent(new CustomEvent('asescon:detalle-cargado',{detail:{notificacion:d,accionInicial}}));
   if(['correo','whatsapp','gmail'].includes(accionInicial)){
    const panel=accionInicial==='correo' && d.puede_editar?get('visorDocumentoSeccionCorreo'):get('visorDocumentoSeccionCompartir');
    if(panel){panel.open=true;(accionInicial==='correo'?get('visorDocumentoDestinatario'):(accionInicial==='gmail'?get('visorDocumentoGmail'):get('visorDocumentoWhatsapp')))?.focus();}
   }
  }catch(e){if(actual===version){carga('visorDocumentoSkeleton',false);texto('visorDocumentoEstado',e.message||'No se pudo cargar la notificación.');}}
 }
 document.querySelectorAll('[data-buzon-detalle]').forEach(b=>b.addEventListener('click',()=>abrir(b.dataset.idDocumento,b,b.dataset.accionInicial||'')));
 modal.querySelector('[data-visor-cerrar]').addEventListener('click',()=>modal.close());
 modal.addEventListener('close',()=>{clearTimeout(temporizadorPdf);clearTimeout(temporizadorVistaPdf);carga('visorDocumentoSkeleton',false);carga('visorDocumentoPdfSkeleton',false);version++;get('visorDocumentoPdf').removeAttribute('src');botonOrigen?.focus();});
 get('visorDocumentoPdf').addEventListener('load',()=>{clearTimeout(temporizadorVistaPdf);carga('visorDocumentoPdfSkeleton',false);get('visorDocumentoPdf').dataset.pdfCargado=archivoUrl?'1':'0';});
 get('visorDocumentoAbrir').addEventListener('click',async()=>{
  if(!archivoUrl)return;
  const iframe=get('visorDocumentoPdf');
  if(!iframe.hidden){iframe.hidden=true;iframe.removeAttribute('src');clearTimeout(temporizadorVistaPdf);carga('visorDocumentoPdfSkeleton',false);get('visorDocumentoAbrir').textContent='Ver PDF aquí';return;}
  const btn=get('visorDocumentoAbrir');btn.disabled=true;
  carga('visorDocumentoPdfSkeleton',true);
  texto('visorDocumentoNota','Comprobando el archivo para mostrar la vista previa…');
  try{
    const respuesta=await fetchConLimite(archivoUrl,{method:'HEAD',credentials:'same-origin',cache:'no-store'});
    if(!respuesta.ok || !(respuesta.headers.get('content-type')||'').includes('application/pdf'))throw new Error('El PDF no está disponible en el servidor. Vuelve a solicitarlo.');
    iframe.src=archivoUrl.href;iframe.hidden=false;btn.textContent='Ocultar vista previa';
    clearTimeout(temporizadorVistaPdf);temporizadorVistaPdf=setTimeout(()=>{carga('visorDocumentoPdfSkeleton',false);},15000);
    texto('visorDocumentoNota','Vista previa abierta dentro de ASESCON.');
  }catch(error){carga('visorDocumentoPdfSkeleton',false);texto('visorDocumentoNota',error.message);get('visorDocumentoNota').dataset.tipo='error';}
  finally{btn.disabled=false;}
 });
 get('visorDocumentoDescargar').addEventListener('click',async evento=>{
   if(modal.dataset.buzon!=='SOL'||!archivoUrl)return;
   evento.preventDefault();
   const id=Number(modal.querySelector('[data-visor-id]')?.value||0);
   try{await window.asesconDescargasSol.iniciarDescargaDisponible(id,get('visorDocumentoDescargar').href);}
   catch(e){texto('visorDocumentoNota',e.message);get('visorDocumentoNota').dataset.tipo='error';window.asesconDescargasSol?.mostrar(id,e.message,'error',true);}
 });
 window.addEventListener('asescon:pdf-disponible',async evento=>{
   const id=Number(modal.querySelector('[data-visor-id]')?.value||0);
   if(modal.open&&id===Number(evento.detail?.id_documento)){
     try{const url=new URL(modal.dataset.detalleUrl,location.href);url.searchParams.set('buzon',modal.dataset.buzon);url.searchParams.set('id_documento',id);
       const resp=await fetchConLimite(url,{credentials:'same-origin'});const json=await resp.json();if(resp.ok&&json.ok)mostrarPdf(json.data);
     }catch(e){texto('visorDocumentoNota','La descarga terminó. Cierra y vuelve a abrir el detalle para ver el PDF.');}
   }
 });
 get('visorDocumentoImprimir').addEventListener('click',()=>{if(archivoUrl)window.asesconImprimirDocumento(get('visorDocumentoPdf'),archivoUrl.href,get('visorDocumentoNota'));});
 get('visorDocumentoCopiar').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(resumen);texto('visorDocumentoEstado','Resumen copiado.');get('visorDocumentoEstado').hidden=false;}catch{texto('visorDocumentoEstado','No se pudo copiar. Selecciona el texto del resumen.');get('visorDocumentoEstado').hidden=false;}});
 get('visorDocumentoCompartirPdf').addEventListener('click',async()=>{
  if(!archivoUrl)return;
  try{const cabecera=await fetchConLimite(archivoUrl,{method:'HEAD',credentials:'same-origin'});if(!cabecera.ok)throw new Error('El PDF no está disponible.');const tamano=Number(cabecera.headers.get('Content-Length')||0);if(!tamano||tamano>20*1024*1024)throw new Error('El documento es demasiado grande para compartirlo directamente. Descárgalo y adjúntalo desde tu aplicación.');const r=await fetchConLimite(archivoUrl,{credentials:'same-origin'},30);if(!r.ok)throw new Error('No se pudo obtener el PDF.');const f=new File([await r.blob()],'notificacion.pdf',{type:'application/pdf'});
   if(!navigator.canShare?.({files:[f]}))throw new Error('Tu navegador no permite compartir archivos. Descarga el PDF y adjúntalo en WhatsApp o correo.');
   await navigator.share({files:[f],title:'Notificación '+modal.dataset.buzon});
  }catch(e){if(e.name!=='AbortError'){texto('visorDocumentoEstado',e.message);get('visorDocumentoEstado').hidden=false;}}
 });
 window.asesconAbrirNotificacionSol=(id,accion='ver')=>abrir(id,null,accion);
 const inicial=Number(modal.dataset.documentoInicial);if(inicial>0)abrir(inicial,null);
})();
