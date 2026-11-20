(() => {
 const form=document.getElementById('fiscalFiltros');
 if(!form)return;
 const filas=Array.from(document.querySelectorAll('[data-fiscal-fila]'));
 const norm=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
 const fecha=s=>{const m=String(s).match(/(\d{2})\/(\d{2})\/(\d{4})/);return m?m[3]+'-'+m[2]+'-'+m[1]:String(s).match(/^\d{4}-\d{2}-\d{2}/)?.[0]||'';};
 function filtrar(){
  let total=0;
  const desde=form.querySelector('[data-fiscal-desde]')?.value||'',hasta=form.querySelector('[data-fiscal-hasta]')?.value||'',anio=form.querySelector('[data-fiscal-anio]')?.value||'';
  for(const fila of filas){
   const detalle=!fila.hasAttribute('data-fiscal-fechas'),dias=detalle?[fecha(fila.cells[2]?.textContent)]:JSON.parse(fila.dataset.fiscalFechas).map(fecha);
   const ok=Array.from(form.querySelectorAll('[data-fiscal-filtro]')).every(i=>norm(fila.cells[Number(i.dataset.fiscalFiltro)]?.textContent).includes(norm(i.value)))&&((!desde&&!hasta)||dias.some(d=>d&&(!desde||d>=desde)&&(!hasta||d<=hasta)))&&(!anio||(detalle?dias.some(d=>d.startsWith(anio)):norm(fila.cells[1]?.textContent).includes(anio)||dias.some(d=>d.startsWith(anio))));
   fila.hidden=!ok;if(ok)total++;
  }
  document.getElementById('fiscalResultados').textContent=total+' resultado(s) en esta vista.';
 }
 form.addEventListener('submit',e=>{e.preventDefault();filtrar();});
 form.addEventListener('reset',()=>setTimeout(filtrar,0));
})();
