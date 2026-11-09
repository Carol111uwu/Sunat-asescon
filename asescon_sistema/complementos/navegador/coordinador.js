const origenAsescon='https://sistema.asescon.net';let abriendo=false,entregando=false;
const oficial=url=>{try{const u=new URL(url);return u.protocol==='https:'&&(u.hostname==='sunat.gob.pe'||u.hostname.endsWith('.sunat.gob.pe'));}catch{return false;}};
async function comprobar(){if(!await chrome.extension.isAllowedIncognitoAccess())return {ok:false,mensaje:'Activa Permitir en incógnito en los detalles del asistente.'};const ventanas=await chrome.windows.getAll();if(ventanas.some(v=>v.incognito))return {ok:false,mensaje:'Cierra las ventanas privadas abiertas antes de iniciar otro cliente. Así se evita reutilizar la sesión de otra empresa.'};return {ok:true};}
chrome.runtime.onMessage.addListener((m,sender,responder)=>{
 (async()=>{
  const desdeAsescon=sender.tab&&!sender.tab.incognito&&new URL(sender.url).origin===origenAsescon;
  if(m.tipo==='COMPROBAR_VENTANA'&&desdeAsescon)return await comprobar();
  if(m.tipo==='ABRIR_CLIENTE'&&desdeAsescon){if(abriendo)return {ok:false,mensaje:'Ya se está preparando una ventana.'};abriendo=true;
   try{const estado=await comprobar();if(!estado.ok)return estado;const d=m.datos;if(!d||!/^\d{11}$/.test(d.ruc)||typeof d.usuario_sol!=='string'||!d.usuario_sol||typeof d.clave_sol!=='string'||!d.clave_sol||!oficial(d.url)||!Number.isFinite(Number(d.vence_en))||Number(d.vence_en)*1000<Date.now())throw new Error('Solicitud de cliente vencida o no válida.');
    const ventana=await chrome.windows.create({url:'about:blank',incognito:true,type:'normal',focused:true});const tab=ventana.tabs?.[0];if(!tab?.id){await chrome.windows.remove(ventana.id);throw new Error('No se pudo crear la ventana privada.');}
    await chrome.storage.session.set({sol:{ruc:d.ruc,usuario_sol:d.usuario_sol,clave_sol:d.clave_sol,tabId:tab.id,windowId:ventana.id,vence:Date.now()+90000,entregado:false}});await chrome.alarms.create('borrar_clave_sol',{when:Date.now()+90000});await chrome.tabs.update(tab.id,{url:d.url});return {ok:true,mensaje:'Se abrió la ventana privada del cliente. Comprueba el RUC en SUNAT antes de declarar o pagar.'};
   }finally{abriendo=false;}
  }
  if(m.tipo==='CREDENCIAL_FORMULARIO'&&sender.tab?.incognito&&oficial(sender.url)){
   if(entregando)return {ok:false};entregando=true;try{
   const {sol}=await chrome.storage.session.get('sol');if(!sol||sol.tabId!==sender.tab.id||sol.windowId!==sender.tab.windowId||sol.vence<Date.now()||sol.entregado)return {ok:false};
   sol.entregado=true;await chrome.storage.session.set({sol});const respuesta={ok:true,ruc:sol.ruc,usuario_sol:sol.usuario_sol,clave_sol:sol.clave_sol};delete sol.clave_sol;delete sol.usuario_sol;await chrome.storage.session.set({sol});return respuesta;
   }finally{entregando=false;}
  }
  return {ok:false};
 })().then(responder).catch(()=>responder({ok:false,mensaje:'El asistente no pudo completar el acceso. Revisa la ventana oficial.'}));return true;
});
chrome.alarms.onAlarm.addListener(a=>{if(a.name==='borrar_clave_sol')chrome.storage.session.remove('sol');});
chrome.windows.onRemoved.addListener(async id=>{const {sol}=await chrome.storage.session.get('sol');if(sol?.windowId===id)await chrome.storage.session.remove('sol');});
