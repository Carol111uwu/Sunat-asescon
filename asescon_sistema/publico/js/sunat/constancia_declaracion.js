(() => {
 const modal=document.getElementById('constanciaDeclaracionModal');if(!modal)return;const form=document.getElementById('formularioConstanciaDeclaracion');let origen=null;
 document.querySelectorAll('[data-constancia-declaracion]').forEach(b=>b.addEventListener('click',()=>{const d=JSON.parse(b.dataset.constanciaDeclaracion);origen=b;form.reset();for(const n of ['id','numero_orden','monto_declarado','estado_presentacion'])form.elements[n].value=d[n]??'';form.elements.fecha_presentacion.value=String(d.fecha_presentacion||'').replace(' ','T').slice(0,16);document.getElementById('constanciaDeclaracionTitulo').textContent=d.cliente+' · '+d.formulario+' · '+d.periodo;modal.showModal();}));
 modal.querySelector('[data-constancia-cerrar]').addEventListener('click',()=>modal.close());modal.addEventListener('close',()=>origen?.focus());
})();
