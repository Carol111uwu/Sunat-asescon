(() => {
  'use strict';
  const dialog=document.getElementById('avisosCasosSol');
  const area=dialog?.querySelector('[data-buzon-novedades]');
  const lista=area?.querySelector('[data-buzon-novedades-lista]');
  const insignia=document.querySelector('[data-buzon-novedades-badge]');
  if(!area||!lista||!insignia) return;
  let ultimaClave='',actualizando=false;
  const base=area.dataset.url;
  const construir=(tipo,id)=>{
    const ruta=tipo==='SUNAFIL'?'vistas/buzones/buzon_sunafil.php':'vistas/buzones/buzon_sol_sunat.php';
    // Derivar desde /api/buzones/... evitando construir una URL externa.
    const u=new URL('../../'+ruta,new URL(base,window.location.href));
    u.searchParams.set('id_cliente',String(id));
    return u.href;
  };
  function pintar(datos){
    const nuevos=Number(datos.nuevas)||0;
    insignia.textContent=nuevos>99?'99+':String(nuevos);
    insignia.hidden=!nuevos;
    if(!dialog.open)return;
    const clave=JSON.stringify(datos.avisos||[]);
    if(clave===ultimaClave)return;
    ultimaClave=clave;
    lista.replaceChildren();
    if(!datos.disponible){
      lista.textContent='Pendiente instalar la migración de avisos en la base principal.';
      return;
    }
    if(!(datos.avisos||[]).length){
      lista.textContent='No tienes avisos nuevos. El historial continúa disponible en los buzones.';
      return;
    }
    datos.avisos.forEach(a=>{
      const articulo=document.createElement('article');
      articulo.className='buzon-novedad-fila';
      const cuerpo=document.createElement('div');
      const titulo=document.createElement('strong');
      titulo.textContent=`${a.buzon==='SUNAFIL'?'SUNAFIL':'Buzón SOL'} · ${a.cliente}`;
      const descripcion=document.createElement('p');
      descripcion.textContent=`RUC ${a.ruc} · Detectada ${a.fecha}`;
      const enlace=document.createElement('a');
      enlace.href=construir(a.buzon,a.id_cliente);
      enlace.textContent='Ver buzón';
      cuerpo.append(titulo,descripcion,enlace);
      const visto=document.createElement('button');
      visto.type='button';visto.className='reference-btn';
      visto.textContent='Marcar visto';
      visto.addEventListener('click',async()=>{
        visto.disabled=true;
        try {
          const respuesta=await fetch(base,{
            method:'POST',credentials:'same-origin',
            headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({csrf_token:area.dataset.csrf,id_novedad:String(a.id_novedad)})
          });
          const json=await respuesta.json();
          if(!respuesta.ok||!json.ok)throw Error(json.mensaje||'No fue posible guardar la lectura');
          ultimaClave='';await refrescar();
        }catch(error){visto.disabled=false;visto.title=error.message;}
      });
      articulo.append(cuerpo,visto);
      lista.append(articulo);
    });
  }
  async function refrescar(){
    if(actualizando)return;
    actualizando=true;
    try {
      const respuesta=await fetch(base,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      if(!respuesta.ok)throw Error('Error al comprobar novedades');
      const datos=await respuesta.json();
      if(datos.ok)pintar(datos);
    }catch(error){
      if(dialog.open)lista.textContent='No se pudieron comprobar los avisos nuevos. Intenta de nuevo.';
    }finally{actualizando=false;}
  }
  const observer=new MutationObserver(()=>{if(dialog.open){ultimaClave='';refrescar();}});
  observer.observe(dialog,{attributes:true,attributeFilter:['open']});
  refrescar();
  const timer=window.setInterval(()=>{if(!document.hidden)refrescar();},45000);
  window.addEventListener('pagehide',()=>{window.clearInterval(timer);observer.disconnect();});
})();
