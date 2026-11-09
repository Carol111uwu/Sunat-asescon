(() => {
 'use strict';
 const mini=document.getElementById('syncDashboardMini');if(!mini)return;
 const $=id=>document.getElementById(id);
 let consultando=false;
 const numero=v=>Math.max(0,Number(v)||0);
 async function cargar(){
  if(consultando||document.hidden)return;consultando=true;
  const ctl=new AbortController(),limite=setTimeout(()=>ctl.abort(),10000);
  try{
   const r=await fetch(mini.dataset.endpoint,{cache:'no-store',credentials:'same-origin',signal:ctl.signal});
   if(!r.ok)throw new Error('No disponible');const d=await r.json();if(d.error)throw new Error(d.error);
   const total=numero(d.total),terminados=numero(d.finalizados),porcentaje=numero(d.porcentaje);
   $('syncMiniBarra').value=porcentaje;
   $('syncMiniPorcentaje').textContent=total?porcentaje+'% completado':'Sin trabajos nuevos';
   const fase=(d.por_modulo||[]).find(m=>numero(m.pendientes)+numero(m.procesando)>0);
   const etapa=fase?' · Ahora: '+fase.nombre:'';
   $('syncMiniEstado').textContent=d.pausado?'Sincronización pausada. No se asignan nuevos trabajos.':
    total===0?'No hay nuevas tareas pendientes. Puedes solicitar la sincronización cuando quieras.':
    terminados>=total?'Lote finalizado'+(numero(d.errores)?' con incidencias.':'.'):
    terminados+' de '+total+' trabajos finalizados'+etapa+'.';
   const boton=$('syncMiniSolicitar');if(boton)boton.disabled=Boolean(d.pausado);
  }catch(e){$('syncMiniEstado').textContent='No se pudo leer el avance. Los datos anteriores no se modifican.';}
  finally{clearTimeout(limite);consultando=false;}
 }
 cargar();setInterval(cargar,15000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)cargar();});
})();
