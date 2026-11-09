(() => {
 if(location.protocol!=='https:'||!location.hostname.endsWith('.sunat.gob.pe'))return;let intentado=false;
 const visible=e=>e&&!e.disabled&&e.getClientRects().length>0;
 function campo(selectores){for(const s of selectores){const e=[...document.querySelectorAll(s)].find(visible);if(e)return e;}return null;}
 function poner(e,v){const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set;setter.call(e,v);e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));}
 async function revisar(){if(intentado)return;const ruc=campo(['#txtRuc','input[name*="ruc" i]','input[id*="ruc" i]']);const usuario=campo(['#txtUsuario','#txtUser','input[name*="usuario" i]','input[id*="usuario" i]']);const clave=campo(['input[type=password]']);if(!ruc||!usuario||!clave)return;
  const texto=document.body.innerText;if(/no soy un robot|resuelve.{0,30}captcha/i.test(texto))return;intentado=true;observador.disconnect();
  const d=await chrome.runtime.sendMessage({tipo:'CREDENCIAL_FORMULARIO'});if(!d?.ok)return;poner(ruc,d.ruc);poner(usuario,d.usuario_sol);poner(clave,d.clave_sol);d.clave_sol='';
  const raiz=clave.form||document;const boton=[...raiz.querySelectorAll('button,input[type=submit],a')].find(e=>visible(e)&&/^(ingresar|entrar|iniciar sesi[oó]n)$/i.test((e.innerText||e.value||'').trim()));if(boton)boton.click();
 }
 const observador=new MutationObserver(()=>revisar());observador.observe(document.documentElement,{childList:true,subtree:true});revisar();setTimeout(()=>observador.disconnect(),90000);
})();
