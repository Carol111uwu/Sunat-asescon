                                                                          
                                                                           
(() => {
  'use strict';
  const hoy = new Date();
  const fechaPeru = new Intl.DateTimeFormat('en-CA',{
    timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'
  }).formatToParts(hoy);
  const valor = nombre => Number(fechaPeru.find(p=>p.type===nombre)?.value);
  const anio = valor('year'), mes = valor('month');
  if(!Number.isInteger(anio) || anio<2021)return;
  const ids = ['anioPeriodo','pdtAnio','plameAnio','liqAnio','sireAnio','rvieAnio','rceAnio'];

  function iniciarAnio(select){
    const originales = [...select.options];
    const años = originales.filter(o=>/^20\d{2}$/.test(o.value));
    if(!años.length)return;
    const previo = select.value;
    const todos = originales.filter(o=>!/^20\d{2}$/.test(o.value)&&!o.disabled);
    const grupoActual = document.createElement('optgroup');
    grupoActual.label = 'Año actual';
    grupoActual.append(new Option('Año actual ('+anio+')',String(anio)));
    const otros = document.createElement('optgroup');
    otros.label = 'Otros años';
    for(let año=anio-1;año>=2021;año--)otros.append(new Option(String(año),String(año)));
    select.replaceChildren(...todos.map(o=>o.cloneNode(true)),grupoActual,otros);
    select.value = [...select.options].some(o=>o.value===previo) ? previo : String(anio);

    const form = select.form;
    const nombre = select.getAttribute('name') || '';
    const id = select.id || '';
    let selectorMes = null;
    if (id === 'anioPeriodo') selectorMes = document.getElementById('mesPeriodo');
    else if (id.endsWith('Anio')) selectorMes = document.getElementById(id.slice(0,-4)+'Mes');
    if (!selectorMes && form) {
      selectorMes = form.querySelector(nombre==='periodo_anio' ? 'select[name="periodo_mes"]' : 'select[name="mes"]');
    }
    const actualizarMes = () => {
      if (!selectorMes) return;
      for(const opcion of selectorMes.options){
        if (!/^\d{1,2}$/.test(opcion.value)) continue;
        const numero = Number(opcion.value);
        opcion.disabled = numero>0 && Number(select.value)===anio && numero>mes;
      }
      if (selectorMes.selectedOptions[0]?.disabled) {
        const actual = [...selectorMes.options].find(o=>Number(o.value)===mes && !o.disabled);
        if (actual) {selectorMes.value=actual.value;selectorMes.dispatchEvent(new Event('change',{bubbles:true}));}
      }
    };
    select.addEventListener('change',actualizarMes);
    actualizarMes();
    if (previo!==select.value)select.dispatchEvent(new Event('change',{bubbles:true}));
  }

  function iniciar(){
    document.querySelectorAll('select').forEach(select=>{
      const nombre = select.name || '';
      if (['anio','periodo_anio'].includes(nombre)||ids.includes(select.id))iniciarAnio(select);
    });
    const fechaHasta = String(anio)+'-'+String(mes).padStart(2,'0')+'-'+String(valor('day')).padStart(2,'0');
    document.querySelectorAll('input[type="date"]').forEach(input=>{
      if (!['desde','hasta','inicio','fin'].includes(input.name))return;
      input.min='2021-01-01';input.max=fechaHasta;
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',iniciar);
  else iniciar();
})();
