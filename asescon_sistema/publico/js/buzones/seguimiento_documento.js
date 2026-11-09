(() => {
 'use strict';
 window.asesconSeguimientoBuzon = {
  iniciar(modal) {
   const lista=document.getElementById('visorDocumentoNotas');
   const form=document.getElementById('visorDocumentoFormNota');
   const estado=document.getElementById('visorDocumentoNotaEstado');
   const clave=()=>Array.from(crypto.getRandomValues(new Uint8Array(20)),x=>x.toString(16).padStart(2,'0')).join('');
   const mostrar=notas=>{
    lista.replaceChildren();
    if(!notas.length){const p=document.createElement('p');p.textContent='Sin notas registradas.';lista.append(p);return;}
    notas.forEach(n=>{
     const articulo=document.createElement('article');const texto=document.createElement('p');texto.textContent=n.texto;
     const datos=document.createElement('small');const fecha=new Date(n.fecha);datos.textContent=`${n.autor} · ${Number.isNaN(fecha.getTime())?n.fecha:fecha.toLocaleString('es-PE')}`;
     articulo.append(texto,datos);articulo.dataset.textoTipo=form.elements.buzon.value==='SOL'?'nota_sol':'nota_sunafil';articulo.dataset.textoId=n.id_nota;lista.append(articulo);
    });
   };
   form.addEventListener('submit',async e=>{
    e.preventDefault();const id=form.elements.id_documento.value;const boton=form.querySelector('button');const datos=new FormData(form);boton.disabled=true;estado.textContent='Guardando nota…';
    try{
     const r=await fetch(form.action,{method:'POST',body:datos,credentials:'same-origin',headers:{Accept:'application/json'}});const j=await r.json();
     if(!r.ok||!j.ok)throw new Error(j.mensaje||'No se pudo guardar la nota.');
     if(form.elements.id_documento.value!==id)return;
     mostrar(j.notas||[]);form.elements.nota.value='';form.elements.clave_nota.value=clave();estado.textContent='Nota guardada.';
    }catch(e){if(form.elements.id_documento.value===id)estado.textContent=e.message||'No se pudo guardar la nota.';}
    finally{boton.disabled=false;}
   });
   modal.querySelectorAll('[data-documento-panel]').forEach(b=>b.addEventListener('click',()=>this.activar(b.dataset.documentoPanel)));
   return {cargar(d){mostrar(d.notas||[]);form.elements.nota.value='';form.elements.clave_nota.value=clave();estado.textContent=d.error_notas||'';}};
  },
  activar(panel){
   document.getElementById('visorDocumentoPanelPdf').hidden=panel!=='pdf';
   document.getElementById('visorDocumentoSeguimiento').hidden=panel!=='notas';
   document.querySelectorAll('[data-documento-panel]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.documentoPanel===panel)));
   if(panel==='notas')document.querySelector('#visorDocumentoFormNota textarea')?.focus();
  }
 };
})();
