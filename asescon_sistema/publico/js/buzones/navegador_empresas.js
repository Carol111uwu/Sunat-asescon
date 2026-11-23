document.addEventListener('DOMContentLoaded',()=>{
  'use strict';
  document.querySelectorAll('[data-buzon-navegar]').forEach(area=>{
    const input=area.querySelector('input');
    const boton=area.querySelector('[data-ir-empresa-buzon]');
    const mensaje=area.querySelector('[data-mensaje-navegador]');
    let empresas=[];
    try{empresas=JSON.parse(area.dataset.clientes||'[]');}catch{}
    const normalizar=x=>String(x||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
    const buscar=()=>{
      const texto=normalizar(input.value);
      if(!texto){mensaje.textContent='Escribe un RUC o el nombre de la empresa.';return;}
      const exactas=empresas.filter(c=>normalizar(c.ruc)===texto||
           normalizar(c.ruc+' · '+c.nombre)===texto||
           normalizar(c.nombre)===texto);
      const parecidas=exactas.length?exactas:empresas.filter(c=>
           normalizar(c.nombre).includes(texto)||normalizar(c.ruc).includes(texto));
      if(parecidas.length===0){mensaje.textContent='No se encontró esa empresa entre las autorizadas.';return;}
      if(parecidas.length>1){mensaje.textContent=`Encontré ${parecidas.length} empresas. Elige una opción completa de la lista.`;return;}
      const url=new URL(window.location.href);
      url.searchParams.set('id_cliente','0');
      url.searchParams.set('empresa',String(parecidas[0].id));
      url.searchParams.delete('pagina');
      url.searchParams.delete('pagina_documentos');
      url.searchParams.delete('ver_documento');
      url.searchParams.delete('orden_detalle');
      // Al navegar conserva el filtro; no consulta los portales oficiales.
      window.location.assign(url.href);
    };
    boton?.addEventListener('click',buscar);
    input?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();buscar();}});
  });
});
