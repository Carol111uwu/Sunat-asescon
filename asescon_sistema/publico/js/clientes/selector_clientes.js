                                                                               
                                                         
(() => {
  'use strict';
  const normalizar = value => String(value ?? '').normalize('NFD')
    .replace(/[\u0300-\u036f\u200b-\u200d\ufeff]/g, '').toLowerCase().trim();
  const coincide = (texto, query) => normalizar(query).split(/\s+/).filter(Boolean)
    .every(parte => normalizar(texto).includes(parte));

  function mejorar(select, indice) {
    if (select.multiple || select.disabled || select.dataset.sinAutocompletar !== undefined ||
        select.closest('#detalleLiquidacion') || select.closest('[data-no-selector-clientes]') ||
        select.options.length < 2) return;
    const contenedor = document.createElement('div');
    contenedor.className = 'asescon-cliente-combo';
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'asescon-cliente-boton';
    boton.setAttribute('role', 'combobox');
    boton.setAttribute('aria-haspopup', 'listbox');
    boton.setAttribute('aria-expanded', 'false');
    boton.setAttribute('aria-controls', 'asescon-clientes-lista-' + indice);
    const valor = document.createElement('span');
    const flecha = document.createElement('span');
    flecha.textContent = '⌄';
    flecha.setAttribute('aria-hidden','true');
    boton.append(valor, flecha);
    const popup = document.createElement('div');
    popup.className = 'asescon-cliente-popup';
    popup.hidden = true;
    const entrada = document.createElement('input');
    entrada.type = 'search';
    entrada.className = 'asescon-cliente-entrada';
    entrada.placeholder = 'Buscar por RUC, razón social o nombre…';
    entrada.autocomplete = 'off';
    entrada.setAttribute('aria-label','Buscar cliente');
    const lista = document.createElement('div');
    lista.className = 'asescon-cliente-lista';
    lista.id = 'asescon-clientes-lista-' + indice;
    lista.setAttribute('role','listbox');
    const nota = document.createElement('small');
    nota.className = 'asescon-cliente-nota';
    popup.append(entrada, lista, nota);
    contenedor.append(boton, popup);
    select.before(contenedor);
                                                                             
    select.classList.add('asescon-select-original');
    let filtradas = [], cursor = -1;
    function actualizar() {
      const elegida = [...select.options].find(o => o.selected);
      const sinSeleccion = !select.value && select.required;
      valor.textContent = sinSeleccion ? 'Seleccionar cliente' :
        (elegida?.textContent.trim() || 'Seleccionar cliente');
      boton.title = valor.textContent;
      boton.classList.toggle('sin-seleccion',sinSeleccion || !select.value);
    }
    function cerrar() {
      popup.hidden = true;
      boton.setAttribute('aria-expanded','false');
      entrada.value = '';
      cursor = -1;
    }
    function elegir(opcion) {
      if (!opcion || opcion.disabled) return;
      select.value = opcion.value;
      select.dispatchEvent(new Event('change',{bubbles:true}));
      actualizar();
      cerrar();
      boton.focus();
    }
    function mostrar() {
      const q = entrada.value;
      filtradas = [...select.options].filter(o => !o.disabled && coincide([
        o.textContent,o.dataset.ruc,o.dataset.nombre,o.dataset.telefono,
        o.dataset.celular,o.dataset.correo,o.dataset.busqueda
      ].join(' '),q));
      cursor = -1;
      lista.replaceChildren();
      const limite = 80;
      filtradas.slice(0,limite).forEach(o => {
        const item = document.createElement('button');
        item.type = 'button';
        item.setAttribute('role','option');
        item.setAttribute('aria-selected',String(o.value===select.value));
        item.textContent = o.textContent.trim();
        item.addEventListener('pointerdown',e=>e.preventDefault());
        item.addEventListener('click',()=>elegir(o));
        lista.append(item);
      });
      nota.textContent = filtradas.length===0 ? 'No se encontraron empresas.' :
        filtradas.length>limite ? `Mostrando ${limite} de ${filtradas.length}. Escribe para buscar.` :
        `${filtradas.length} opciones · selecciona un cliente.`;
    }
    function abrir() {
      if (select.disabled) return;
      document.querySelectorAll('.asescon-cliente-popup').forEach(p => {
        if(p!==popup){p.hidden=true;p.previousElementSibling?.setAttribute('aria-expanded','false');}
      });
      popup.hidden=false;
      boton.setAttribute('aria-expanded','true');
      mostrar();
      entrada.focus();
    }
    boton.addEventListener('click',()=>popup.hidden?abrir():cerrar());
    boton.addEventListener('keydown',e=>{
      if(['ArrowDown','Enter',' '].includes(e.key)){e.preventDefault();abrir();}
    });
    entrada.addEventListener('input',mostrar);
    entrada.addEventListener('keydown',e=>{
      if(e.key==='Escape'){e.preventDefault();cerrar();boton.focus();return;}
      if(e.key==='ArrowDown'||e.key==='ArrowUp'){
        e.preventDefault();
        cursor=Math.max(0,Math.min(lista.children.length-1,cursor+(e.key==='ArrowDown'?1:-1)));
        [...lista.children].forEach((b,i)=>b.classList.toggle('activo',i===cursor));
        lista.children[cursor]?.scrollIntoView({block:'nearest'});
      } else if(e.key==='Enter'&&cursor>=0){e.preventDefault();elegir(filtradas[cursor]);}
    });
    document.addEventListener('pointerdown',e=>{if(!contenedor.contains(e.target))cerrar();});
    select.addEventListener('change',actualizar);
    select.addEventListener('invalid',e=>{
      e.preventDefault();
      abrir();
      nota.textContent='Selecciona un cliente para continuar.';
    });
    select.form?.addEventListener('reset',()=>setTimeout(actualizar,0));
    select.form?.addEventListener('submit',e=>{
      if(select.required && !select.value){
        e.preventDefault();
        boton.focus();
        abrir();
        nota.textContent='Selecciona un cliente para continuar.';
      }
    });
    actualizar();
  }

  function iniciar() {
    const atributos = ['select[name="cliente"]','select[name="id_cliente"]',
      'select[name="cliente_desc"]','select[name="id_empresa"]','select[data-selector-cliente]'];
    document.querySelectorAll(atributos.join(',')).forEach((s,i)=>mejorar(s,i+1));
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',iniciar);
  else iniciar();
})();
