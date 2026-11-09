document.addEventListener('DOMContentLoaded', () => {
 'use strict';
 const botonRecargar=document.querySelector('[data-refresh]');
 if(botonRecargar)botonRecargar.addEventListener('click',()=>location.reload());
 const formulario=document.querySelector('.buzon-sol-sync-form[data-url-solicitar]');
 if(!formulario)return;
 const boton=formulario.querySelector('button[type="submit"]');
 const mensaje=formulario.querySelector('#buzonSolMensajeConsulta');
 const cliente=Number(formulario.querySelector('[name="id_cliente"]')?.value||0);
 let ocupado=false;let observaciones=0;
 const mostrar=(texto,tipo='progreso')=>{
   mensaje.textContent=texto;mensaje.dataset.tipo=tipo;mensaje.hidden=false;
   window.asesconDescargasSol?.mostrar(0,texto,tipo,tipo==='error');
 };
 async function consultarEstado(){
  const url=new URL(formulario.dataset.urlEstado,location.href);url.searchParams.set('id_cliente',String(cliente));
  const respuesta=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(!(respuesta.headers.get('content-type')||'').includes('application/json'))throw new Error('La sesión o el servidor no respondió correctamente.');
  const estado=await respuesta.json();if(!respuesta.ok||!estado.ok)throw new Error(estado.mensaje||'No se pudo comprobar la consulta.');
  return estado;
 }
 async function vigilar(){
  if(ocupado)return;ocupado=true;boton.disabled=true;boton.textContent='Consultando…';
  let activo=true;
  while(activo&&observaciones<225){
   observaciones++;
   try{
    const s=await consultarEstado();
    if(['COMPLETADO','ADJUNTOS_PENDIENTES'].includes(s.estado)){
      mostrar('Consulta terminada: '+s.total+' registros leídos; '+s.nuevos+' nuevos.'+(s.estado==='ADJUNTOS_PENDIENTES'?' Los PDF pendientes continuarán en segundo plano.':'')+' Pulsa «Recargar lista» para ver los resultados.','exito');activo=false;
    }else if(s.estado==='ERROR'){
      mostrar('No se pudo consultar SUNAT: '+s.mensaje,'error');activo=false;
    }else if(!['PENDIENTE','PROCESANDO'].includes(s.estado)){
      mostrar(s.mensaje||'No hay una consulta activa.','error');activo=false;
    }else{
      mensaje.textContent=s.mensaje||'La consulta se está procesando…';mensaje.hidden=false;mensaje.dataset.tipo='progreso';
    }
   }catch(e){mostrar('No se pudo comprobar el avance: '+e.message+' Se intentará de nuevo.','error');}
   if(activo)await new Promise(resolver=>setTimeout(resolver,8000));
  }
  if(activo)mostrar('No se confirmó el fin de la consulta. Comprueba Cron antes de reintentar.','error');
  boton.disabled=false;boton.textContent='Consultar Buzón';ocupado=false;observaciones=0;
 }
 formulario.addEventListener('submit',async evento=>{
  evento.preventDefault();if(ocupado)return;
  boton.disabled=true;boton.textContent='Solicitando…';
  mostrar('Registrando la consulta del Buzón en el servidor…');
  try{
   const resp=await fetch(formulario.dataset.urlSolicitar,{method:'POST',body:new FormData(formulario),credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!(resp.headers.get('content-type')||'').includes('application/json'))throw new Error('La sesión venció o el servidor no respondió en JSON.');
   const j=await resp.json();if(!resp.ok||!j.ok)throw new Error(j.mensaje||'No se pudo solicitar la consulta.');
   mostrar('Consulta registrada. Puedes seguir usando el sistema mientras SUNAT responde.');vigilar();
  }catch(e){mostrar(e.message||'Error al consultar SUNAT.','error');boton.disabled=false;boton.textContent='Consultar Buzón';}
 });
 const inicial=document.querySelector('[data-buzon-estado][data-buzon-documento]');
 if(cliente&&inicial&&Number(inicial.dataset.buzonDocumento)===0&&['PENDIENTE','PROCESANDO'].includes(inicial.dataset.buzonEstado))vigilar();
});
