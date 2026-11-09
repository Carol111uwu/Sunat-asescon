document.addEventListener('DOMContentLoaded', () => {
  'use strict';
  const formulario=document.querySelector('[data-buzon-consulta]');
  if(!formulario)return;
  const buzon=formulario.dataset.buzonConsulta;
  const cliente=formulario.querySelector('[data-buzon-cliente]');
  const boton=formulario.querySelector('button[type="submit"]');
  const mensaje=formulario.querySelector('.buzon-consulta-aviso');
  const panel=document.querySelector('.buzon-proceso-detalle[data-buzon-documento="0"]');
  let vigilando=false;
  let ultimoEstado=panel?.dataset.buzonEstado||'SIN_SOLICITUD';
  const mostrar=(texto,tipo='info')=>{
    if(!mensaje)return;
    mensaje.textContent=texto;
    mensaje.dataset.tipo=tipo;
    mensaje.hidden=false;
  };
  const etiquetas={PENDIENTE:'En cola',PROCESANDO:'Procesando',COMPLETADO:'Completado',ADJUNTOS_PENDIENTES:'Bandeja guardada; PDF pendientes',ERROR:'Error',SIN_SOLICITUD:'Sin consulta anterior'};
  const fecha=valor=>{
    if(!valor)return '—';
    const d=new Date(valor);
    return Number.isNaN(d.getTime())?'—':new Intl.DateTimeFormat('es-PE',{dateStyle:'short',timeStyle:'medium'}).format(d);
  };
  const texto=(selector,valor)=>{
    const elemento=panel?.querySelector(selector);
    if(elemento)elemento.textContent=String(valor??'—');
  };
  function actualizarPanel(s){
    ultimoEstado=String(s.estado||'SIN_SOLICITUD').toUpperCase();
    if(!panel)return;
    panel.dataset.buzonEstado=ultimoEstado;
    texto('[data-resumen-estado]',etiquetas[ultimoEstado]||'Estado de consulta');
    const resumen=ultimoEstado==='PENDIENTE'?'Esperando turno del procesador':ultimoEstado==='PROCESANDO'?'Conectando con el portal oficial':ultimoEstado==='ERROR'?'La última consulta necesita atención':ultimoEstado==='COMPLETADO'?'Datos recibidos; puedes actualizar la lista':'Estado de la última consulta';
    texto('[data-resumen-proceso]',resumen);
    texto('[data-detalle-mensaje]',s.mensaje||'Sin información adicional.');
    texto('[data-detalle-solicitado]',fecha(s.solicitado_en));
    texto('[data-detalle-actualizado]',fecha(s.actualizado_en));
    const cola=s.cola||{};
    texto('[data-detalle-cola]',cola.posicion!==null&&cola.posicion!==undefined?'Turno '+cola.posicion+' de '+(cola.pendientes||1):(cola.pendientes||0)+' solicitud(es) pendientes');
    texto('[data-detalle-espera]',cola.espera_segundos===null||cola.espera_segundos===undefined?'—':Math.floor(cola.espera_segundos/60)+' min');
    texto('[data-detalle-cron]',s.mensaje_cron||'No hay información reciente del procesador.');
    texto('[data-detalle-cron-ultima]',s.cron_ultima?fecha(s.cron_ultima*1000):'No registrada');
    texto('[data-detalle-resultados]',(s.total||0)+' / '+(s.nuevos||0));
  }
  panel?.addEventListener('toggle',()=>texto('[data-detalles-etiqueta]',panel.open?'Ocultar detalles':'Ver detalles'));

  const urlActual=new URL(window.location.href);
  cliente?.addEventListener('change',()=>{
    const id=Number(cliente.value)||0;
    if(id===(Number(urlActual.searchParams.get('id_cliente'))||0))return;
    urlActual.searchParams.set('id_cliente',String(id));
    urlActual.searchParams.delete('pagina');
    window.location.assign(urlActual.href);
  });
  document.querySelectorAll('[data-refresh]').forEach(b=>b.addEventListener('click',()=>window.location.reload()));
  document.addEventListener('click',event=>{
    const actual=event.target.closest('.buzon-mas-acciones');
    document.querySelectorAll('.buzon-mas-acciones[open]').forEach(menu=>{
      if(menu!==actual)menu.open=false;
    });
  });

  async function solicitudConLimite(url,opciones={},segundos=12){
    const control=new AbortController();
    const limite=window.setTimeout(()=>control.abort(),segundos*1000);
    try{
      const respuesta=await fetch(url,{...opciones,signal:control.signal});
      if(!(respuesta.headers.get('content-type')||'').includes('application/json'))throw new Error('La sesión o el servidor no respondió correctamente.');
      const json=await respuesta.json();
      if(!respuesta.ok||!json.ok)throw new Error(json.mensaje||'El servidor no pudo completar la operación.');
      return json;
    }catch(error){
      if(error?.name==='AbortError')throw new Error('El servidor tardó más de '+segundos+' segundos. Comprueba el estado de Cron.');
      throw error;
    }finally{window.clearTimeout(limite);}
  }
  async function consultarEstado(id){
    const url=new URL(formulario.dataset.urlEstado,window.location.href);
    url.searchParams.set('id_cliente',String(id));
    return solicitudConLimite(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}},12);
  }
  function detener(){
    vigilando=false;
    boton.disabled=false;
    boton.textContent=Number(cliente?.value||0)>0?'Consultar empresa':('Consultar todos en '+buzon);
  }
  async function vigilar(id){
    if(vigilando||id<1)return;
    vigilando=true;
    boton.disabled=true;
    boton.textContent='Consulta en curso';
    let erroresConsecutivos=0;
                                                                              
    for(let intento=0;intento<18;intento++){
      try{
        const s=await consultarEstado(id);
        erroresConsecutivos=0;
        actualizarPanel(s);
        if(['COMPLETADO','ADJUNTOS_PENDIENTES'].includes(s.estado)){
          mostrar('Consulta finalizada: '+s.total+' registros leídos y '+s.nuevos+' nuevos. Actualizando la lista…','exito');
          window.setTimeout(()=>window.location.reload(),1500);
          return;
        }
        if(s.estado==='ERROR'){
          mostrar('La consulta necesita atención. Pulsa «+» para ver el motivo; los datos anteriores se conservan.','error');
          detener();return;
        }
        if(!['PENDIENTE','PROCESANDO'].includes(s.estado)){
          mostrar('No hay una consulta activa. Puedes solicitar una nueva.','info');detener();return;
        }
        mostrar((s.estado==='PENDIENTE'?'En cola.':'Procesando.')+(s.cron_reciente===false?' El procesador Cron no muestra actividad reciente.':' Abre «+» para consultar el avance.'),'progreso');
      }catch(error){
        erroresConsecutivos++;
        mostrar('No se pudo comprobar el avance: '+error.message+' Abre «+» para consultar los últimos detalles.','error');
        if(erroresConsecutivos>=3){detener();return;}
      }
      if(intento<17)await new Promise(resolve=>window.setTimeout(resolve,8000));
    }
    mostrar('Se detuvo la actualización automática para evitar una espera infinita. La solicitud puede seguir en el servidor. Pulsa «+» y «Comprobar estado ahora» o actualiza la página.','info');
    detener();
  }
  panel?.querySelector('[data-actualizar-consulta]')?.addEventListener('click',async event=>{
    const b=event.currentTarget;
    const id=Number(cliente?.value||0);
    if(id<1)return;
    b.disabled=true;b.textContent='Comprobando…';
    try{
      const estado=await consultarEstado(id);
      actualizarPanel(estado);
      mostrar(estado.estado==='ERROR'?'La consulta necesita atención; revisa los detalles abiertos.':'Estado actualizado. Puedes ver el resultado en «+».',estado.estado==='ERROR'?'error':'info');
      if(estado.estado==='COMPLETADO'||estado.estado==='ADJUNTOS_PENDIENTES')window.setTimeout(()=>window.location.reload(),1200);
      if(estado.estado==='ERROR')detener();
    }catch(error){mostrar('No se pudo actualizar el estado: '+error.message,'error');}
    finally{b.disabled=false;b.textContent='Comprobar estado ahora';}
  });
  formulario.addEventListener('submit',async event=>{
    if(vigilando){event.preventDefault();return;}
    const id=Number(cliente?.value||0);
    const todos=formulario.dataset.todosPermitidos==='1';
    if(!id&&!todos){event.preventDefault();mostrar('Elige una empresa antes de consultar.','error');return;}
                                                              
    if(buzon!=='SOL'||id<1){boton.disabled=true;boton.textContent='Programando…';return;}
    event.preventDefault();
    boton.disabled=true;boton.textContent='Registrando…';
    mostrar('Enviando la solicitud al servidor…','progreso');
    try{
      await solicitudConLimite(formulario.dataset.urlSolicitar,{
        method:'POST',body:new FormData(formulario),credentials:'same-origin',headers:{Accept:'application/json'}
      },18);
      actualizarPanel({estado:'PENDIENTE',mensaje:'Solicitud registrada. A la espera del procesador Cron.',cola:{pendientes:1}});
      mostrar('Solicitud registrada. Abre «+» para revisar los detalles.','progreso');
      boton.disabled=false;
      vigilar(id);
    }catch(error){
      mostrar('No se pudo confirmar la solicitud: '+error.message+' Comprueba el estado antes de volver a enviar.','error');
      detener();
    }
  });
  if(cliente&&Number(cliente.value)>0&&panel&&['PENDIENTE','PROCESANDO'].includes(ultimoEstado))vigilar(Number(cliente.value));
});
