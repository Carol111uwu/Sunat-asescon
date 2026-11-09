(()=>{'use strict';const d=document.getElementById('avisosCasosSol'),b=document.getElementById('abrirAvisosCasosSol');if(!d||!b)return;const lista=d.querySelector('[data-lista-avisos]'),mensaje=d.querySelector('[data-mensaje-avisos]'),badge=b.querySelector('span'),pendientesSol=Number(b.dataset.pendientesSol)||0;d.querySelector('[data-cerrar-avisos]').onclick=()=>d.close();
async function cargarNotificaciones(){const r=await fetch(d.dataset.url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(!r.ok)throw Error('No se pudieron consultar los avisos de casos.');const j=await r.json();if(!j.ok)throw Error(j.mensaje||'No se pudieron consultar los avisos.');const n=(j.notificaciones||[]).filter(x=>!Number(x.leida)).length,total=pendientesSol+n;if(badge)badge.textContent=String(total);b.setAttribute('aria-label',total+' notificaciones pendientes');b.title=total?'Tienes '+total+' notificaciones pendientes':'Notificaciones SUNAT y casos';return j.notificaciones||[];}
b.onclick=async()=>{
 if(!d.open)d.showModal();
 mensaje.textContent='Cargando avisos de casos…';
 lista.innerHTML='<div class="asescon-skeleton-list" role="status" aria-label="Cargando notificaciones" aria-busy="true">'+
   Array.from({length:3},()=>'<div class="asescon-skeleton-item"><span class="asescon-skeleton avatar"></span><div class="asescon-skeleton-lines"><span class="asescon-skeleton wide"></span><span class="asescon-skeleton short"></span></div></div>').join('')+'</div>';
 try{
  const notificaciones=await cargarNotificaciones();
  lista.replaceChildren();
  mensaje.textContent=notificaciones.length?'':'No tienes avisos de casos. Puedes consultar el Buzón SOL desde el botón superior.';
  notificaciones.forEach(n=>{
   const a=document.createElement('article'),titulo=document.createElement('h3'),p=document.createElement('p'),enlace=document.createElement('a');
   titulo.textContent=n.titulo+(Number(n.leida)?'':' · Nueva');
   p.textContent=`Cliente: ${n.cliente} · Prioridad: ${n.prioridad} · Fecha límite: ${n.fecha_limite||'Sin fecha'}`;
   enlace.href=d.dataset.casos+'?caso='+Number(n.id_documento);
   enlace.textContent='Ingresar al caso';
   enlace.addEventListener('click',async e=>{e.preventDefault();try{await fetch(d.dataset.url,{method:'POST',credentials:'same-origin',keepalive:true,headers:{Accept:'application/json'},body:new URLSearchParams({csrf_token:d.dataset.csrf,id_notificacion:n.id_notificacion})});}catch{}location.href=enlace.href;});
   a.append(titulo,p,enlace);lista.append(a);
  });
 }catch(e){lista.replaceChildren();mensaje.textContent=(e.message||'No se pudieron cargar los avisos.')+' El acceso al Buzón SOL permanece disponible.';}
};
                                                                                               
const temporizador=window.setInterval(()=>{if(!document.hidden)cargarNotificaciones().catch(()=>{});},30000);window.addEventListener('pagehide',()=>window.clearInterval(temporizador));
})();
