(() => {
  'use strict';
  const root=document.getElementById('dashboardSire');
  if(!root)return;
  const $=id=>document.getElementById(id);
  const normalizar=valor=>String(valor||'').normalize('NFD')
    .replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').trim();

                                                                            
                                                                        
  const selector=$('sireCliente');

                                                                   
  const globalFilas=[...document.querySelectorAll('[data-sire-global-empresa]')];
  const globalBuscar=$('sireFiltroGlobal');
  if(globalBuscar){
    const porPagina=25;let pagina=1;
    const anterior=$('sireGlobalAnterior'),siguiente=$('sireGlobalSiguiente');
    const mostrar=()=> {
      const filtro=normalizar(globalBuscar.value);
      const filtradas=globalFilas.filter(f=>normalizar(f.dataset.busqueda).includes(filtro));
      const paginas=Math.max(1,Math.ceil(filtradas.length/porPagina));
      pagina=Math.max(1,Math.min(pagina,paginas));
      const visibles=new Set(filtradas.slice((pagina-1)*porPagina,pagina*porPagina));
      globalFilas.forEach(f=>{f.hidden=!visibles.has(f);});
      if($('sireGlobalVacio'))$('sireGlobalVacio').hidden=filtradas.length!==0;
      if($('sireGlobalConteo'))$('sireGlobalConteo').textContent=filtradas.length+' de '+globalFilas.length+' empresas coinciden con la búsqueda.';
      if($('sireGlobalPagina'))$('sireGlobalPagina').textContent='Página '+pagina+' de '+paginas;
      if(anterior)anterior.disabled=pagina<=1;
      if(siguiente)siguiente.disabled=pagina>=paginas;
    };
    globalBuscar.addEventListener('input',()=>{pagina=1;mostrar();});
    anterior?.addEventListener('click',()=>{pagina--;mostrar();});
    siguiente?.addEventListener('click',()=>{pagina++;mostrar();});
    mostrar();
  }

                                                                             
  const ticket=$('sireTicket'),libro=$('sireLibro'),mes=$('sireMes'),anio=$('sireAnio');
  const resultado=$('sireResult');
  const botones={
    periodos:$('btnPeriodos'),solicitar:$('btnSolicitar'),
    estado:$('btnEstado'),descargar:$('btnDescargar')
  };
  const ejecutar=async accion=>{
    if(!resultado||!selector||!libro)return;
    if(!Number(selector.value))return alert('Selecciona una empresa antes de usar herramientas técnicas.');
    const periodo=String(anio?.value||'')+String(mes?.value||'');
    if(['solicitar','descargar'].includes(accion)&&/^20\d{2}00$/.test(periodo))
      return alert('Para descargar o solicitar propuestas debes seleccionar un mes.');
    if(['estado','descargar'].includes(accion)&&!String(ticket?.value||'').trim())
      return alert('Introduce el ticket que SUNAT devolvió para esta solicitud.');
    const boton=botones[accion];if(boton)boton.disabled=true;
    resultado.textContent='Consultando SUNAT para '+libro.value+'...';
    try{
      const respuesta=await AsesconSireSync.post(accion,{
        id_cliente:Number(selector.value),libro:libro.value,
        periodo,ticket:String(ticket?.value||'').trim()
      });
      resultado.textContent=JSON.stringify(respuesta,null,2);
      const nuevoTicket=respuesta?.num_ticket||respuesta?.ticket;
      if(nuevoTicket&&ticket)ticket.value=String(nuevoTicket);
    }catch(error){
      resultado.textContent='No se completó la operación técnica: '+String(error?.message||error);
    }finally{if(boton)boton.disabled=false;}
  };
  for(const [accion,boton] of Object.entries(botones))
    boton?.addEventListener('click',()=>ejecutar(accion));
})();
