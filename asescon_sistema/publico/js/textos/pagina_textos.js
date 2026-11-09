(()=>{
 'use strict';
 const f=document.getElementById('filtrosTextos'),lista=document.getElementById('listaTextos'),estado=document.getElementById('estadoTextos'),mas=document.getElementById('masTextos');
 const rutas={tarea:'../tareas/tareas.php',plan:'../clientes/servicios_cobros.php?seccion=planes',cotizacion:'../clientes/servicios_cobros.php?seccion=cotizaciones',honorario:'../clientes/servicios_cobros.php?seccion=honorarios',recordatorio:'../clientes/comunicacion_informes.php?seccion=recordatorios',plantilla:'../clientes/comunicacion_informes.php?seccion=recordatorios&tab=plantillas'};
 const notasEnteras=new Set(['nota_sol','nota_sunafil','avance_sol','observacion_cliente']);
 let desde=0,version=0;
 const inicial=new URLSearchParams(location.search);if(inicial.has('tipo'))f.elements.tipo.value=inicial.get('tipo');if(inicial.get('vista')==='archivados')f.elements.vista.value='archivados';
 async function cargar(anadir=false){
  const v=++version;if(!anadir){desde=0;lista.replaceChildren();}estado.textContent='Cargando…';mas.disabled=true;
  try{
   const d=await window.asesconTextos.pedir({tipo:f.elements.tipo.value,desde});if(v!==version)return;
   for(const t of d.textos){
    if(Boolean(t.archivado)!==(f.elements.vista.value==='archivados'))continue;
    const a=document.createElement('article'),h=document.createElement('h3'),p=document.createElement('p');a.className='reference-panel';h.textContent=t.titulo;p.textContent=t.texto;p.style.whiteSpace='pre-wrap';a.append(h,p);
    for(const [accion,titulo]of [...(t.puede_editar?[['editar','Editar texto']]:[]),[t.archivado?'restaurar':'archivar',t.archivado?'Restaurar / mostrar':'Archivar / ocultar'],...(t.puede_eliminar?[['eliminar',notasEnteras.has(t.tipo)?'Eliminar nota definitivamente':'Borrar solo el texto']]:[])]){
     const b=document.createElement('button');b.type='button';b.className='reference-btn';b.textContent=titulo;
     b.onclick=async()=>{b.disabled=true;try{const resultado=await window.asesconTextos.actuar(t.tipo,t.id,accion);if(resultado!==false){if(accion==='editar'){p.textContent=String(resultado);estado.textContent='Contenido corregido.';}else{a.remove();estado.textContent=accion==='eliminar'?'Contenido borrado.':'Vista actualizada.';}}}catch(e){estado.textContent=e.message;}finally{b.disabled=false;}};
     a.append(b);
    }
    if(rutas[t.tipo]){const link=document.createElement('a');link.className='reference-btn';link.href=new URL(rutas[t.tipo],location.href).href;link.textContent='Gestionar registro completo';a.append(link);}
    lista.append(a);
   }
   desde=d.siguiente;mas.hidden=desde===null;estado.textContent=lista.children.length?'':'No hay textos en esta vista.';
  }catch(e){estado.textContent=e.message;}finally{mas.disabled=false;}
 }
 f.onsubmit=e=>{e.preventDefault();cargar();};mas.onclick=()=>cargar(true);cargar();
})();
