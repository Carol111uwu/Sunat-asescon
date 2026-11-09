(() => {
 'use strict';
 const script=document.currentScript;if(!script)return;
 const api=script.dataset.api,csrf=script.dataset.csrf,archivo=script.dataset.archivo;
 let ocultos=new Set();
 async function pedir(datos,post=false){
  const u=new URL(api,location.href),op={credentials:'same-origin',headers:{Accept:'application/json'}};
  if(post){const f=new FormData();for(const [k,v]of Object.entries({...datos,csrf_token:csrf}))f.set(k,String(v));op.method='POST';op.body=f;}
  else for(const [k,v]of Object.entries(datos))u.searchParams.set(k,String(v));
  const r=await fetch(u,op),j=await r.json();if(!r.ok||!j.ok)throw Error(j.mensaje||'No se pudo completar la acción.');return j.data;
 }
 const estado=document.createElement('p');estado.setAttribute('role','status');estado.className='texto-estado';
 function aviso(t){estado.textContent=t;if(!estado.isConnected)document.querySelector('.content')?.prepend(estado);}
 const dialog=document.createElement('dialog');dialog.className='texto-dialog';
 dialog.innerHTML='<form method="dialog" class="texto-editor-form"><h2>Editar contenido guardado</h2><p class="texto-editor-titulo"></p><label>Texto<textarea name="texto" rows="9" maxlength="20000" required></textarea></label><p class="texto-editor-error" role="alert"></p><div class="texto-editor-acciones"><button type="button" class="reference-btn" data-cancelar>Cancelar</button><button type="submit" class="reference-btn primary">Guardar cambios</button></div></form>';
 document.body.append(dialog);
 function editar(tipo,id){
  return pedir({accion:'detalle',tipo,id}).then(({texto:d})=>{
   if(!d.puede_editar)throw Error('No puedes editar este contenido; posiblemente ya fue enviado o es un registro oficial.');
   return new Promise(resolve=>{
    const form=dialog.querySelector('form'),campo=form.elements.texto,error=dialog.querySelector('.texto-editor-error'),guardar=form.querySelector('[type=submit]');
    dialog.querySelector('.texto-editor-titulo').textContent=d.titulo;campo.value=d.texto;error.textContent='';guardar.disabled=false;
    let terminado=false;
    const finalizar=valor=>{if(terminado)return;terminado=true;form.removeEventListener('submit',enviar);dialog.removeEventListener('close',cerrar);resolve(valor);};
    const cerrar=()=>finalizar(false);
    const enviar=async e=>{
     e.preventDefault();const nuevo=campo.value.trim();if(!nuevo){error.textContent='Escribe un texto o usa Eliminar si quieres quitarlo.';return;}
     guardar.disabled=true;error.textContent='Guardando cambios…';
     try{await pedir({accion:'editar',tipo,id,revision:d.revision,texto:nuevo},true);dialog.close();document.dispatchEvent(new CustomEvent('asescon:texto-cambiado',{detail:{tipo,id,accion:'editar'}}));finalizar(nuevo);}
     catch(ex){error.textContent=ex.message;guardar.disabled=false;}
    };
    form.addEventListener('submit',enviar);dialog.addEventListener('close',cerrar,{once:true});
    dialog.querySelector('[data-cancelar]').onclick=()=>dialog.close();dialog.showModal();campo.focus();
   });
  });
 }
 async function actuar(tipo,id,accion){
  if(accion==='editar')return editar(tipo,id);
  if(accion==='eliminar'){
   const d=await pedir({accion:'detalle',tipo,id});
   if(!d.texto.puede_eliminar)throw Error('No tienes permiso para eliminar este texto.');
   if(!confirm('¿Eliminar este contenido? No podrás recuperar el texto desde ASESCON. No se eliminan documentos oficiales.'))return false;
   await pedir({accion,tipo,id,revision:d.texto.revision},true);
  }else await pedir({accion,tipo,id},true);
  if(accion==='archivar')ocultos.add(tipo+':'+id);else ocultos.delete(tipo+':'+id);
  document.dispatchEvent(new CustomEvent('asescon:texto-cambiado',{detail:{tipo,id,accion}}));return true;
 }
 function decorar(el){
  if(el.dataset.textoPreparado)return;el.dataset.textoPreparado='1';
  const tipo=el.dataset.textoTipo,id=el.dataset.textoId;if(!tipo||!Number(id))return;
  const contenedor=el.closest('.template-card,tr')||el;
                                                                                 
                                                                                  
  if(contenedor.querySelector('.gestion-actions')||(tipo==='tarea'&&contenedor.querySelector('[name="accion"][value="eliminar"]')))return;
  if(ocultos.has(tipo+':'+id)){
                                                                             
   if(tipo==='tarea')el.hidden=true;
   else contenedor.hidden=true;
   return;
  }
  const acciones=document.createElement('span');acciones.className='texto-acciones';
  for(const [accion,nombre]of [['editar','Editar'],['archivar','Ocultar / archivar'],['eliminar','Eliminar texto']]){
   const b=document.createElement('button');b.type='button';b.className='mini-btn';b.textContent=tipo==='tarea'&&accion==='eliminar'?'Borrar descripción':nombre;
   b.onclick=async()=>{
    b.disabled=true;
    try{
     const resultado=await actuar(tipo,id,accion);
     if(resultado===false)return;
     if(accion==='editar'){
      const destino=el.matches('article')?el.querySelector('p')||el:el;
      destino.textContent=String(resultado);aviso('Contenido corregido.');
     }else{
      if(tipo==='tarea'){
       el.textContent='';el.hidden=true;
       aviso(accion==='archivar'?'Descripción archivada; la tarea sigue visible.':'Descripción borrada. Para eliminar la tarea completa usa el botón rojo «Eliminar».');
      }else{
       contenedor.hidden=true;
       aviso(accion==='archivar'?'Archivado para tu cuenta. Puedes restaurarlo desde Utilitarios → Textos guardados.':'Contenido eliminado.');
      }
      acciones.hidden=true;
     }
    }catch(e){aviso(e.message);}finally{b.disabled=false;}
   };acciones.append(b);
  }
  el.after(acciones);
 }
 function explorar(){
  document.querySelectorAll('[data-texto-tipo]').forEach(decorar);
  document.querySelectorAll('textarea:not([data-limpiar-borrador])').forEach(t=>{
   if(dialog.contains(t))return;
   t.dataset.limpiarBorrador='1';const b=document.createElement('button');b.type='button';b.className='mini-btn';b.textContent='Vaciar borrador';
   b.onclick=()=>{if(t.disabled||t.readOnly)return;if(t.value&&!confirm('¿Vaciar este campo? El cambio se aplicará cuando guardes.'))return;t.value='';t.dispatchEvent(new Event('input',{bubbles:true}));t.focus();};t.after(b);
  });
 }
 window.asesconTextos={pedir,actuar,editar,decorar,archivo};
 pedir({accion:'estados'}).then(d=>{ocultos=new Set(d.archivados);explorar();new MutationObserver(explorar).observe(document.body,{childList:true,subtree:true});}).catch(e=>{explorar();aviso('No se pudo cargar el estado de los textos archivados: '+e.message);});
})();
