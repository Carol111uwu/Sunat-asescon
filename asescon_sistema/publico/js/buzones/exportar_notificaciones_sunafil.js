(() => {
  'use strict';
  const boton=document.getElementById('exportarBuzonSunafilCsv');
  const tabla=document.getElementById('tablaSunafil');
  if(!boton||!tabla)return;
  const columnas=['Estado','Origen','F. notificación','Cliente','Asunto','Registro','Plazo / prioridad','F. límite'];
  const escapar=valor=>{
    let texto=String(valor||'').replace(/\s+/g,' ').trim();
    if(/^[=+\-@\t\r]/.test(texto))texto="'"+texto;
    return '"'+texto.replace(/"/g,'""')+'"';
  };
  boton.addEventListener('click',()=>{
    const filas=[columnas.map(escapar).join(';')];
    for(const fila of tabla.querySelectorAll('tbody tr')){
      const celdas=columnas.map(c=>fila.querySelector('td[data-label="'+c+'"]'));
      if(celdas.some(c=>!c))continue;
      filas.push(celdas.map(c=>escapar(c.innerText)).join(';'));
    }
    const blob=new Blob(['\ufeffsep=;\r\n'+filas.join('\r\n')],{type:'text/csv;charset=utf-8'});
    const url=URL.createObjectURL(blob);const enlace=document.createElement('a');
    enlace.href=url;enlace.download='asescon_buzon_sunafil_requerimientos.csv';
    document.body.appendChild(enlace);enlace.click();enlace.remove();
    window.setTimeout(()=>URL.revokeObjectURL(url),1200);
  });
})();
