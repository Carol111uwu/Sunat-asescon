(()=>{'use strict';
window.asesconVentanaSol=(id,tipo)=>{
 const dialogo=document.getElementById(id);if(!dialogo)return null;
 const aviso=dialogo.querySelector('[data-aviso]'),contenido=dialogo.querySelector('[data-contenido]');let secuencia=0,documento=0,origen=null;
 const mensaje=t=>{aviso.textContent=t||'';};
 const cerrar=()=>{dialogo.close();};dialogo.querySelector('[data-cerrar]').onclick=cerrar;
 dialogo.addEventListener('close',()=>{secuencia++;origen?.focus();});
 const api=async(url,opciones={})=>{const control=new AbortController();const limite=setTimeout(()=>control.abort(),15000);let r;try{r=await fetch(url,{signal:control.signal,credentials:'same-origin',headers:{Accept:'application/json'},...opciones});}catch(error){if(error.name==='AbortError')throw Error('El servidor tardó demasiado. Cierra y vuelve a abrir esta ventana.');throw error;}finally{clearTimeout(limite);}if(!(r.headers.get('content-type')||'').includes('application/json'))throw Error('La sesión terminó o el servidor devolvió una respuesta no válida.');const j=await r.json();if(!r.ok||!j.ok)throw Error(j.mensaje||'No se pudo completar la operación.');return j;};
 return {dialogo,contenido,mensaje,api,get id(){return documento;},get version(){return secuencia;},async abrir(detalle){origen=detalle.origen;documento=Number(detalle.id);const v=++secuencia;contenido.hidden=true;mensaje('Cargando…');if(!dialogo.open)dialogo.showModal();const url=new URL(dialogo.dataset.url,location.href);url.searchParams.set('id_documento',documento);if(tipo)url.searchParams.set('tipo',tipo);let j;try{j=await api(url);}catch(error){if(v!==secuencia||!dialogo.open)return null;throw error;}if(v!==secuencia||!dialogo.open)return null;const d=j.data||j;dialogo.querySelector('[data-titulo-documento]').textContent=d.asunto||d.documento?.asunto||'';mensaje('');contenido.hidden=false;return d;}};
};
document.addEventListener('click',e=>{const b=e.target.closest('[data-accion-buzon]');if(!b)return;const tipo=b.dataset.accionBuzon;if(!/^(pdf|correo|whatsapp|notas|asignacion)$/.test(tipo))return;document.dispatchEvent(new CustomEvent('buzon-sol:'+tipo,{detail:{id:b.dataset.idDocumento,origen:b}}));});
})();
