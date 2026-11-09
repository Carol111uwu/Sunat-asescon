(() => {
 if(location.origin!=='https://sistema.asescon.net')return;let ocupado=false;
 const informar=(data)=>window.postMessage({canal:'ASESCON_SOL_RESULTADO',...data},location.origin);
 window.addEventListener('message',async e=>{if(e.source!==window||e.origin!==location.origin||e.data?.canal!=='ASESCON_SOL')return;if(e.data.tipo==='COMPROBAR'){informar({tipo:'DISPONIBLE'});return;}
  if(e.data.tipo!=='INICIAR'||ocupado)return;const f=document.querySelector('[data-asescon-acceso]');if(!f)return;const peticion=String(e.data.peticion||'');if(!/^[a-f0-9-]{36}$/i.test(peticion))return;ocupado=true;
  try{const previo=await chrome.runtime.sendMessage({tipo:'COMPROBAR_VENTANA'});if(!previo.ok)throw new Error(previo.mensaje);const url=new URL(f.dataset.endpoint,location.href);if(url.origin!==location.origin||!url.pathname.endsWith('/api/sunat/preparar_acceso_portal.php'))throw new Error('Ruta de acceso no válida.');const r=await fetch(url,{method:'POST',credentials:'same-origin',body:new FormData(f),headers:{Accept:'application/json'}});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.mensaje||'No se pudo preparar el cliente.');const resultado=await chrome.runtime.sendMessage({tipo:'ABRIR_CLIENTE',datos:j.data});informar({peticion,mensaje:resultado.mensaje});}
  catch(error){informar({peticion,mensaje:error.message||'No se pudo abrir SUNAT.'});}finally{ocupado=false;}
 });informar({tipo:'DISPONIBLE'});
})();
