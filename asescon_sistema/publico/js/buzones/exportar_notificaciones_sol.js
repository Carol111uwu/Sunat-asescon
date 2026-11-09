(() => {
 const boton=document.getElementById('exportarBuzonSolCsv');
 const tabla=document.getElementById('tablaBuzonSol');
 if(!boton||!tabla)return;
 const celdas=['F. notificación','Cliente','Estado','F. consultado','Categoría','Asunto','Prioridad'];
 const limpiar=valor=>{
  let texto=String(valor||'').replace(/\s+/g,' ').trim();
  if(/^[=+\-@\t\r]/.test(texto))texto="'"+texto;
  return '"'+texto.replace(/"/g,'""')+'"';
 };
 boton.addEventListener('click',()=>{
  const filas=[celdas.map(limpiar).join(';')];
  for(const fila of tabla.querySelectorAll('tbody tr')){
   const datos=celdas.map(c=>fila.querySelector('td[data-label="'+c+'"]'));
   if(datos.some(d=>!d))continue;
   filas.push(datos.map(d=>limpiar(d.innerText)).join(';'));
  }
  const archivo=new Blob(['\ufeffsep=;\r\n'+filas.join('\r\n')],{type:'text/csv;charset=utf-8'});
  const url=URL.createObjectURL(archivo);const enlace=document.createElement('a');
  enlace.href=url;enlace.download='asescon_buzon_sol_notificaciones.csv';
  document.body.appendChild(enlace);enlace.click();enlace.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1200);
 });
})();
