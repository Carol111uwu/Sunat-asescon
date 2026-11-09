(() => {
 const script=document.currentScript;const endpoint=script?.dataset.url;
 const normalizar=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f\u200b-\u200d\ufeff]/g,'').toLowerCase().trim();
 const coincide=(texto,q)=>{q=normalizar(q);if(/^[\d\s.-]+$/.test(q))q=q.replace(/\D/g,'');return q.split(/\s+/).filter(Boolean).every(t=>normalizar(texto).includes(t));};
 function iniciar(){
  let contador=0;let catalogo=null;const json=document.getElementById('catalogoClientesBusqueda');if(json){try{catalogo=JSON.parse(json.textContent);}catch{}}
  function crear(input,buscar,seleccionar){
   const caja=document.createElement('div');caja.className='autocompletar-cliente';input.parentNode.insertBefore(caja,input);caja.append(input);const lista=document.createElement('div');lista.className='autocompletar-cliente-lista';lista.hidden=true;lista.id='sugerenciasCliente'+(++contador);lista.setAttribute('role','listbox');caja.append(lista);const desplegar=document.createElement('button');desplegar.type='button';desplegar.className='autocompletar-cliente-desplegar';desplegar.title='Ver clientes disponibles';desplegar.textContent='⌄';caja.append(desplegar);
   input.autocomplete='off';input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-controls',lista.id);input.setAttribute('aria-expanded','false');
   let opciones=[],activo=-1,version=0,temporizador,cambiando=false;
   const cerrar=()=>{lista.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');activo=-1;};
   function elegir(i){const opcion=opciones[i];if(!opcion)return;cambiando=true;seleccionar(opcion);input.setCustomValidity('');input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));cambiando=false;version++;cerrar();input.focus();}
   function mostrar(filas){opciones=filas.slice(0,80);activo=-1;lista.replaceChildren();for(const [i,opcion] of opciones.entries()){const b=document.createElement('button');b.type='button';b.id=lista.id+'_'+i;b.setAttribute('role','option');b.textContent=opcion.etiqueta;b.addEventListener('pointerdown',e=>e.preventDefault());b.addEventListener('click',()=>elegir(i));lista.append(b);}if(filas.length>80){const aviso=document.createElement('p');aviso.className='autocompletar-cliente-nota';aviso.textContent='Escribe para buscar entre los demás clientes.';lista.append(aviso);}lista.hidden=!opciones.length;input.setAttribute('aria-expanded',String(opciones.length>0));}
   async function consultar(){const v=++version;try{const datos=await buscar(input.value);if(v===version&&document.activeElement===input)mostrar(datos);}catch{if(v===version)cerrar();}}
   input.addEventListener('input',()=>{if(cambiando)return;clearTimeout(temporizador);version++;temporizador=setTimeout(consultar,180);});
   input.addEventListener('focus',consultar);
   desplegar.addEventListener('click',()=>{if(!lista.hidden)cerrar();else{input.focus();consultar();}});
   input.addEventListener('keydown',e=>{if(e.key==='Escape'){cerrar();return;}if(lista.hidden)return;if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();activo=Math.max(0,Math.min(opciones.length-1,activo+(e.key==='ArrowDown'?1:-1)));[...lista.children].forEach((b,i)=>b.setAttribute('aria-selected',String(i===activo)));input.setAttribute('aria-activedescendant',lista.children[activo].id);lista.children[activo].scrollIntoView({block:'nearest'});}else if(e.key==='Enter'&&activo>=0){e.preventDefault();elegir(activo);}});
   document.addEventListener('pointerdown',e=>{if(!caja.contains(e.target)){version++;cerrar();}});input.addEventListener('blur',()=>setTimeout(cerrar,150));
  }
  document.querySelectorAll('[data-autocompletar-cliente]').forEach(input=>{
   crear(input,async q=>{
    if(normalizar(q).length===1)return [];
    if(catalogo)return catalogo.filter(c=>coincide([c.ruc,c.razon_social,c.nombre_comercial].join(' '),q)).map(c=>({ruc:c.ruc,etiqueta:c.razon_social+' · '+c.ruc}));
    if(!endpoint)return [];const url=new URL(endpoint,location.href);if(!normalizar(q))url.searchParams.set('listar','1');else url.searchParams.set('q',q);const r=await fetch(url,{credentials:'same-origin'});const j=await r.json();return r.ok&&j.ok?j.data.map(c=>({ruc:c.ruc,etiqueta:c.razon_social+' · '+c.ruc})):[];
   },c=>input.value=c.ruc);
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',iniciar);else iniciar();
})();
