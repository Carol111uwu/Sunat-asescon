(() => {
  'use strict';
  const contenedor=document.querySelector('[data-avisos-operaciones-sol]');
  if(!contenedor)return;
  const avisoGlobal=document.getElementById('buzonSolAvisoGlobal');
  const inicial=new Map();
  const avisos=new Map();
  const estatus=new Map();
  const enlacesDisponibles=new Map();
  let visible=null;
  function mensajeCorto(texto){return String(texto||'').trim().slice(0,600);}
  function mostrar(id,texto,tipo='info',fijo=false){
    const mensaje=mensajeCorto(texto);const clave=Number(id)||0;
    const fila=document.querySelector(`[data-id-notificacion="${clave}"]`);
    let destino=fila?.querySelector('[data-aviso-fila]');
    if(destino){destino.replaceChildren(document.createTextNode(mensaje));destino.dataset.tipo=tipo;destino.hidden=false;
      const urlDescarga=enlacesDisponibles.get(clave);if(urlDescarga){const enlace=document.createElement('a');enlace.textContent='Descargar PDF ahora';enlace.href=urlDescarga;enlace.download='buzon_sol_'+clave+'.pdf';enlace.className='buzon-sol-enlace-listo';destino.append(enlace);}
    }
    avisoGlobal.textContent=mensaje;avisoGlobal.dataset.tipo=tipo;avisoGlobal.hidden=false;
    contenedor.dataset.tipo=tipo;
    contenedor.querySelector('[data-avisos-texto]').textContent=mensaje;
    contenedor.hidden=false;
    visible=clave;
    if(!fijo){const token=(avisos.get(clave)||0)+1;avisos.set(clave,token);
      setTimeout(()=>{if(avisos.get(clave)===token&&destino&&destino.dataset.tipo!=='progreso')destino.hidden=true;},15000);
    }
  }
  contenedor.querySelector('[data-avisos-cerrar]').addEventListener('click',()=>{contenedor.hidden=true;avisoGlobal.hidden=true;});
  function tamaño(bytes){return (bytes/1024/1024).toLocaleString('es-PE',{maximumFractionDigits:1})+' MB';}
  async function consultar(id){
    const origen=document.getElementById('visorDocumentoBuzon');
    const url=new URL(origen.dataset.estadoPdfUrl,location.href);
    url.searchParams.set('id_documento',String(id));
    const control=new AbortController();const limite=setTimeout(()=>control.abort(),12000);
    let r;try{r=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:control.signal});}
    catch(e){if(e.name==='AbortError')throw new Error('La consulta del PDF superó 12 segundos. Revisa el procesador.');throw e;}
    finally{clearTimeout(limite);}
    const tipo=r.headers.get('content-type')||'';
    if(!tipo.includes('application/json'))throw new Error('La sesión o el servidor no respondió correctamente. Actualiza e inicia sesión si es necesario.');
    const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.mensaje||'No se pudo consultar la descarga.');
    return j;
  }
  function enlaceDescarga(url,id){
    const u=new URL(url,location.href);
    if(u.origin!==location.origin)throw new Error('El archivo tiene una ruta no autorizada.');
    const a=document.createElement('a');a.href=u.href;a.download='buzon_sol_'+id+'.pdf';
    document.body.append(a);a.click();a.remove();
  }
  async function iniciarDescargaDisponible(id,url){
    mostrar(id,'Comprobando el archivo antes de descargar…','progreso',true);
    const destino=new URL(url,location.href);if(destino.origin!==location.origin)throw new Error('Ruta del archivo no válida.');
    const control=new AbortController();const limite=setTimeout(()=>control.abort(),15000);
    let r;try{r=await fetch(destino,{method:'HEAD',credentials:'same-origin',cache:'no-store',signal:control.signal});}
    catch(e){if(e.name==='AbortError')throw new Error('El PDF tardó demasiado en responder. Intenta nuevamente.');throw e;}
    finally{clearTimeout(limite);}
    if(!r.ok || !(r.headers.get('content-type')||'').includes('application/pdf'))throw new Error('El servidor no pudo abrir el PDF guardado. Se debe revisar el archivo o sus permisos.');
    enlacesDisponibles.set(Number(id),destino.href);
    enlaceDescarga(url,id);
    mostrar(id,'Se solicitó la descarga al navegador. Si no comienza, pulsa «Descargar PDF ahora».','exito',true);
  }
  async function vigilar(id){
    const clave=Number(id);if(estatus.has(clave))return;
    const evento={activo:true,intentos:0};estatus.set(clave,evento);
    const consulta=async()=>{
      if(!evento.activo)return;
      try{
        const s=await consultar(clave);
        if(s.disponible){evento.activo=false;estatus.delete(clave);
          mostrar(clave,'PDF recibido de SUNAT y guardado en ASESCON. Iniciando descarga…','exito',true);
          await iniciarDescargaDisponible(clave,s.url);window.dispatchEvent(new CustomEvent('asescon:pdf-disponible',{detail:{id_documento:clave}}));return;
        }
        if(s.estado==='ERROR'){
          evento.activo=false;estatus.delete(clave);mostrar(clave,'No se pudo descargar el PDF: '+s.mensaje,'error',true);return;
        }
        if(s.estado==='COMPLETADO'){
          evento.activo=false;estatus.delete(clave);mostrar(clave,'La consulta terminó sin un PDF guardado. Revisa el aviso del servidor y vuelve a intentarlo.','error',true);return;
        }
        const activo=['PENDIENTE','PROCESANDO'].includes(s.estado);
        if(!activo){evento.activo=false;estatus.delete(clave);mostrar(clave,s.mensaje||'No hay ninguna descarga activa.','error',true);return;}
        let desc=s.mensaje||'Descarga en curso…';
        if(s.bytes_descargados>0){desc+=' '+tamaño(s.bytes_descargados);
          if(s.bytes_totales>0)desc+=' de '+tamaño(s.bytes_totales)+' ('+Math.min(100,Math.floor(100*s.bytes_descargados/s.bytes_totales))+' %).';}
        mostrar(clave,desc,'progreso',true);
      }catch(e){mostrar(clave,'No se pudo comprobar la descarga: '+e.message+' Se intentará nuevamente.','error',true);}
      evento.intentos++;
      if(evento.intentos<18&&evento.activo){setTimeout(consulta,8000);}else{estatus.delete(clave);evento.activo=false;mostrar(clave,'La descarga sigue sin confirmarse. Se detuvo la espera automática; revisa el estado de Cron y solicita el PDF nuevamente si es necesario.','error',true);}
    };
    await consulta();
  }
  window.asesconDescargasSol={mostrar,consultar,vigilar,iniciarDescargaDisponible};
  const existente=document.querySelector('[data-buzon-estado][data-buzon-documento]');
  const idInicial=Number(existente?.dataset.buzonDocumento||0);
  if(idInicial>0 && ['PENDIENTE','PROCESANDO'].includes(existente.dataset.buzonEstado))vigilar(idInicial);
})();
