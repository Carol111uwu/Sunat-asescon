(() => {
 'use strict';
 const panel=document.getElementById('syncPanel');if(!panel)return;
 const $=id=>document.getElementById(id);
 const botonEmpresa=$('syncEmpresaBoton'),panelEmpresa=$('syncEmpresaPanel'),busqueda=$('syncBuscar');
 const opciones=$('syncOpcionesEmpresa'),periodo=$('syncPeriodo'),modulo=$('syncModulo');
 const lista=$('syncEmpresas'),estado=$('syncGlobalEstado'),barra=$('syncGlobalBarra');
 const cifras=$('syncGlobalCifras'),aviso=$('syncGlobalAviso');
 const enlaces=JSON.parse(panel.dataset.enlaces||'{}');
 const etiquetas={RVIE:'Ventas RVIE',RCE:'Compras RCE',DECLARACIONES:'Declaraciones',PADRON_RUC:'Padrón RUC',SSCO:'SSCO',SOL:'Buzón SOL',SUNAFIL:'Buzón SUNAFIL'};
 const orden=Object.keys(etiquetas),meses=['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
 const fechaLima=()=>{
  const partes=new Intl.DateTimeFormat('en-US',{timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
  const valor=k=>partes.find(p=>p.type===k)?.value||'';
  return {anio:Number(valor('year')),mes:Number(valor('month')),dia:`${valor('year')}-${valor('month')}-${valor('day')}`};
 };
 const anioSeleccionado=()=>{
  const valor=String(periodo.value||'');
  const match=valor.match(/^(?:anio_)?(20\d{2})(?:0[1-9]|1[0-2])?$/);
  return match?Number(match[1]):fechaLima().anio;
 };
 const periodosVisibles=()=>periodo.value==='todos'||/^anio_20\d{2}$/.test(periodo.value)
  ?(datos?.periodos_sire||[]):[periodo.value];
 const estados={COMPLETADO:'Completado',PROCESANDO:'Consultando',PENDIENTE:'Pendiente',ERROR:'Error'};
 const abiertos=new Set();let datos=null,empresaSeleccionada='todos',cargando=false,control=null,revision=0;
 const numero=n=>Math.max(0,Number(n)||0);
 const nodo=(tag,valor,clase)=>{const e=document.createElement(tag);if(valor!==undefined)e.textContent=String(valor);if(clase)e.className=clase;return e;};
 const etiqueta=(valor,tipo)=>nodo('span',valor,'sync-etiqueta '+tipo);
 const mesTexto=p=>/^20\d{2}(0[1-9]|1[0-2])$/.test(p)?meses[Number(p.slice(4))-1]+' '+p.slice(0,4):'Consulta general';
 const nombreEmpresa=c=>(c.razon_social||c.nombre||'Empresa')+' · '+(c.ruc||'');
 const normalizar=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es');
 function modulosSeleccionados(){
  const sel=modulo.value;
  if(sel==='TODOS')return orden;
  if(sel==='BUZONES')return ['SOL','SUNAFIL'];
  if(sel==='PADRONES')return ['PADRON_RUC','SSCO'];
  return [sel];
 }
 function catalogo(){return Array.isArray(datos?.catalogo_clientes)?datos.catalogo_clientes:[];}
 function abrirSelector(abrir){panelEmpresa.hidden=!abrir;botonEmpresa.setAttribute('aria-expanded',String(abrir));if(abrir){pintarOpciones();busqueda.focus();}}
 function pintarOpciones(){
  const valor=normalizar(busqueda.value).trim();const frag=document.createDocumentFragment();let visibles=0;
  for(const c of catalogo()){
   if(valor&&!normalizar([c.ruc,c.razon_social,c.nombre_comercial,c.nombre,c.celular].join(' ')).includes(valor))continue;
   visibles++;
   const b=nodo('button',nombreEmpresa(c),'sync-empresa-opcion');b.type='button';b.setAttribute('role','option');
   b.setAttribute('aria-selected',String(empresaSeleccionada===String(c.id_cliente)));
   b.addEventListener('click',()=>{empresaSeleccionada=String(c.id_cliente);botonEmpresa.textContent='Empresa · '+(c.razon_social||c.nombre||c.ruc)+' ▾';abrirSelector(false);pintar();});
   frag.append(b);
  }
  if(!visibles)frag.append(nodo('p','No hay empresas con esa búsqueda.','reference-subtle'));
  opciones.replaceChildren(frag);
 }
 botonEmpresa.addEventListener('click',()=>abrirSelector(panelEmpresa.hidden));
 $('syncTodas').addEventListener('click',()=>{empresaSeleccionada='todos';botonEmpresa.textContent='Empresas · Todas ▾';busqueda.value='';abrirSelector(false);pintar();});
 busqueda.addEventListener('input',pintarOpciones);
 document.addEventListener('click',e=>{if(!e.target.closest('.sync-empresa-selector'))abrirSelector(false);});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')abrirSelector(false);});
 periodo.addEventListener('change',()=>{
  if(datos&&anioSeleccionado()!==Number(datos.anio_consulta)){
   estado.textContent='Cargando el historial del año '+anioSeleccionado()+'…';
   cargar(true);
  }else pintar();
 });
 modulo.addEventListener('change',pintar);
 function actualizarPeriodos(){
  const actual=periodo.value;const frag=document.createDocumentFragment();
  const hoy=fechaLima();
  const todo=nodo('option',`Todos los meses de ${hoy.anio} (año actual)`);todo.value='todos';frag.append(todo);
  for(let y=hoy.anio;y>=2021;y--){
   const grupo=nodo('optgroup');grupo.label=y===hoy.anio?`Año actual (${y})`:`Año ${y}`;
   const todos=nodo('option',`Todos los meses de ${y}`);todos.value='anio_'+y;grupo.append(todos);
   const maximo=y===hoy.anio?hoy.mes:12;
   for(let m=1;m<=maximo;m++){
    const codigo=y+String(m).padStart(2,'0');const o=nodo('option',mesTexto(codigo));o.value=codigo;grupo.append(o);
   }
   frag.append(grupo);
  }
  periodo.replaceChildren(frag);
  periodo.value=[...periodo.options].some(o=>o.value===actual)?actual:'todos';
 }
 function contadores(total){
  const e=nodo('div',undefined,'sync-contadores');
  e.append(etiqueta('Correctas: '+numero(total.completados),'ok'));
  if(numero(total.procesando))e.append(etiqueta('En proceso: '+total.procesando,'proceso'));
  if(numero(total.pendientes))e.append(etiqueta('Pendientes: '+total.pendientes,'pendiente'));
  if(numero(total.errores))e.append(etiqueta('Errores: '+total.errores,'error'));
  if(numero(total.advertencias))e.append(etiqueta('Consultas con PDF pendiente: '+total.advertencias,'aviso'));
  return e;
 }
 function desplegable(id,contenido,clase){
  const d=nodo('details',undefined,clase);d.open=abiertos.has(id);d.dataset.syncId=id;
  const cab=nodo('summary');cab.append(contenido);d.append(cab);
  d.addEventListener('toggle',()=>{if(!d.isConnected)return;d.open?abiertos.add(id):abiertos.delete(id);});
  return d;
 }
 function tabla(filas){
  const caja=nodo('div',undefined,'sync-tabla-scroll'),t=nodo('table'),head=nodo('thead'),tr=nodo('tr'),body=nodo('tbody');
  for(const titulo of ['Mes / Año','Estado','Comprobantes / registros','Actualizado · Perú','Detalle']){const th=nodo('th',titulo);th.scope='col';tr.append(th);}head.append(tr);
  for(const f of filas){
   const row=nodo('tr');row.append(nodo('td',f.periodo_texto||mesTexto(f.periodo)));
   const e=nodo('td');e.append(etiqueta(f.etiqueta,f.clase));row.append(e);
   row.append(nodo('td',f.registros===null?'—':numero(f.registros)),nodo('td',f.actualizado_en||'—'),nodo('td',f.detalle||'','sync-detalle'));
   body.append(row);
  }
  if(!filas.length){const row=nodo('tr'),cell=nodo('td','No hay resultados ni trabajos programados para este filtro.','reference-subtle');cell.colSpan=5;row.append(cell);body.append(row);}
  t.append(head,body);caja.append(t);return caja;
 }
 function filaTrabajo(t){
  const clase=t.advertencia?'aviso':t.estado==='COMPLETADO'?'ok':t.estado==='PROCESANDO'?'proceso':t.estado==='ERROR'?'error':'pendiente';
  return {periodo:t.periodo||'',periodo_texto:t.periodo_texto||'Consulta general',etiqueta:t.advertencia?'Notificaciones consultadas · faltan PDF':estados[t.estado]||'Pendiente',
   clase,registros:t.estado==='COMPLETADO'?numero(t.registros):null,actualizado_en:t.actualizado_en,detalle:t.detalle};
 }
 function filasSire(id,libro,trabajos){
  const inventario=datos.cobertura_sire?.[id]?.[libro]||{};const lima=fechaLima();
  const actual=String(lima.anio)+String(lima.mes).padStart(2,'0');const hoy=lima.dia;
  const periodos=periodosVisibles();
  return periodos.map(p=>{
   const guardado=inventario[p],trabajo=trabajos.find(t=>t.periodo===p);
   const tiene=Boolean(guardado?.guardado);
   const vigente=p===actual,actualizado=String(guardado?.actualizado_en||'');
   const completo=tiene&&(!vigente||actualizado.startsWith(hoy));
   if(trabajo&&['PENDIENTE','PROCESANDO','ERROR'].includes(trabajo.estado)&&!completo){
    const f=filaTrabajo(trabajo);f.periodo=p;f.periodo_texto=mesTexto(p);
    f.registros=tiene?numero(guardado.registros):null;
    f.detalle=(tiene?'Hay un resultado temporal disponible. ':'')+f.detalle;
    return f;
   }
   if(tiene)return {periodo:p,periodo_texto:mesTexto(p),etiqueta:completo?'Consultado':'Consultar nuevamente mes actual',
    clase:completo?'ok':'aviso',registros:guardado.registros,actualizado_en:actualizado.replace(' ',' · '),
    detalle:numero(guardado.registros)===0?'Consulta realizada: SUNAT devolvió 0 comprobantes.':(completo?'Consulta del período completada.':'Falta una consulta confirmada de hoy.')};
   return {periodo:p,periodo_texto:mesTexto(p),etiqueta:'Sin resultado',clase:'pendiente',registros:null,actualizado_en:'—',
    detalle:'Todavía no hay una consulta completada para este mes. No significa que SUNAT tenga 0 comprobantes.'};
  });
 }
 function filasBuzon(id,codigo,trabajos){
  const guardados=datos.cobertura_buzones?.[id]?.[codigo]||{};
  const filtrados=periodosVisibles();
  const trabajo=trabajos.find(t=>t.estado==='PROCESANDO')||trabajos.find(t=>t.estado==='PENDIENTE')||trabajos.at(-1);
  const filas=filtrados.map(p=>{
   const cantidad=numero(guardados[p]);
   return {periodo:p,periodo_texto:mesTexto(p),etiqueta:cantidad?'Notificaciones obtenidas':'Sin notificaciones registradas',
    clase:cantidad?'ok':'pendiente',registros:cantidad||null,actualizado_en:'—',
    detalle:cantidad?'Notificaciones recibidas durante las consultas.':'No hay resultados de este mes. Esto NO confirma que la bandeja oficial esté vacía.'};
  });
  if((periodo.value==='todos'||/^anio_/.test(periodo.value))&&trabajos.length)filas.unshift(...trabajos.map(filaTrabajo));
  if(trabajo&&periodo.value!=='todos'&&!/^anio_/.test(periodo.value)&&['PENDIENTE','PROCESANDO'].includes(trabajo.estado)){
   for(const f of filas)f.detalle+=' · Estado actual del buzón: '+(estados[trabajo.estado]||'Pendiente')+'.';
  }
  return filas;
 }
 function listaEmpresas(){
  const porId=new Map((datos.empresas||[]).map(e=>[String(e.id_cliente),e]));
  const activas=catalogo().map(c=>porId.get(String(c.id_cliente))||{
   id_cliente:Number(c.id_cliente),nombre:c.razon_social,ruc:c.ruc,modulos:[],total:0,finalizados:0,completados:0,pendientes:0,procesando:0,errores:0,advertencias:0
  });
  const global=porId.get('0')||{id_cliente:0,nombre:'Padrones generales',ruc:'',modulos:[],total:0,finalizados:0,completados:0,pendientes:0,procesando:0,errores:0,advertencias:0};
  activas.push(global);
  const mostrarPadrones=modulosSeleccionados().some(c=>c==='PADRON_RUC'||c==='SSCO');
  return activas.filter(e=>empresaSeleccionada==='todos'||String(e.id_cliente)===empresaSeleccionada||(e.id_cliente===0&&mostrarPadrones));
 }
 function tareasModulo(empresa,codigo){return (empresa.modulos||[]).find(m=>m.modulo===codigo)?.trabajos||[];}
 function pintar(){
  if(!datos)return;
  const foco=document.activeElement?.dataset.syncFocus;
  const seleccionados=modulosSeleccionados();const fragmento=document.createDocumentFragment();let visibles=0;
  for(const empresa of listaEmpresas()){
   const modulosPermitidos=empresa.id_cliente===0?['PADRON_RUC','SSCO']:['RVIE','RCE','DECLARACIONES','SOL','SUNAFIL'];
   const codigos=orden.filter(c=>seleccionados.includes(c)&&modulosPermitidos.includes(c));if(!codigos.length)continue;
   visibles++;
   const title=nodo('div',undefined,'sync-empresa-titulo'),ident=nodo('div',undefined,'sync-identidad');
   ident.append(nodo('strong',empresa.nombre),nodo('small',empresa.id_cliente===0?'Padrones comunes':'RUC '+(empresa.ruc||'no disponible')));
   const avance=nodo('div',undefined,'sync-empresa-avance');avance.append(nodo('strong',numero(empresa.finalizados)+' / '+numero(empresa.total)),nodo('small','trabajos finalizados'));
   title.append(ident,contadores(empresa),avance);
   const card=desplegable('empresa-'+empresa.id_cliente,title,'sync-empresa');card.querySelector('summary').dataset.syncFocus='empresa-'+empresa.id_cliente;
   const contenido=nodo('div',undefined,'sync-empresa-contenido');
   for(const codigo of codigos){
    const anioFiltro=anioSeleccionado();
    const trabajos=tareasModulo(empresa,codigo).filter(t=>{
     if(!t.periodo)return anioFiltro===fechaLima().anio&&(periodo.value==='todos'||/^anio_/.test(periodo.value));
     if(periodo.value==='todos'||/^anio_/.test(periodo.value))return String(t.periodo).startsWith(String(anioFiltro));
     return t.periodo===periodo.value;
    });
    const filas=['RVIE','RCE'].includes(codigo)?filasSire(String(empresa.id_cliente),codigo,trabajos):
       ['SOL','SUNAFIL'].includes(codigo)?filasBuzon(String(empresa.id_cliente),codigo,trabajos):trabajos.map(filaTrabajo);
    const titleModulo=nodo('div',undefined,'sync-modulo-titulo');titleModulo.append(nodo('strong',etiquetas[codigo]),etiqueta(
     ['RVIE','RCE'].includes(codigo)?filas.filter(f=>f.clase==='ok').length+' de '+filas.length+' meses consultados':
     ['SOL','SUNAFIL'].includes(codigo)?filas.filter(f=>f.clase==='ok').length+' meses con avisos':trabajos.length+' trabajos','proceso'));
    if(codigo==='SOL'&&trabajos.some(t=>t.advertencia))titleModulo.append(etiqueta('PDF pendientes de descargar','aviso'));
    const bloque=desplegable('modulo-'+empresa.id_cliente+'-'+codigo,titleModulo,'sync-modulo');
    const dentro=nodo('div',undefined,'sync-modulo-contenido');
    if(enlaces[codigo]){const url=new URL(enlaces[codigo],location.href);if(empresa.id_cliente>0)url.searchParams.set('id_cliente',String(empresa.id_cliente));const link=nodo('a','Abrir módulo','reference-btn sync-abrir');link.href=url.href;dentro.append(link);}
    dentro.append(tabla(filas));bloque.append(dentro);contenido.append(bloque);
   }
   card.append(contenido);fragmento.append(card);
  }
  lista.replaceChildren(fragmento);$('syncSinResultados').hidden=visibles>0;
  if(foco)lista.querySelector(`[data-sync-focus="${foco}"]`)?.focus({preventScroll:true});
 }
 function resumen(d){
  datos=d;actualizarPeriodos();pintarOpciones();
  const total=numero(d.total),terminados=numero(d.finalizados);
  barra.hidden=total===0;barra.value=numero(d.porcentaje);
  estado.textContent=total===0?'Sin trabajos nuevos en el período seleccionado. Puedes realizar una consulta desde el módulo correspondiente.':
   d.pausado?'Sincronización pausada · '+terminados+' de '+total+' trabajos finalizados.':
   terminados===total?(numero(d.errores)?'Lote finalizado con incidencias.':'Lote finalizado: datos disponibles.'):
   'Trabajos finalizados: '+terminados+' de '+total+' ('+numero(d.porcentaje)+'%).';
  cifras.replaceChildren(contadores(d));
  $('syncCronEstado').textContent=(d.cron_activo?'Cron de sincronización total activo':'Cron de sincronización total sin señal reciente')+(d.ultima_senal?' · '+d.ultima_senal:'');
  aviso.textContent=d.pausado?'Se detuvo la asignación de nuevas tareas. Los datos existentes se conservan.':
   !d.cron_activo&&d.runtime_preparado===false?'El cron total no puede arrancar: falta confirmar el entorno privado de Python/Playwright (RUNTIME_OK). Revisa la instalación antes de volver a ejecutar.':
   !d.cron_activo&&numero(d.pendientes)+numero(d.procesando)>0?'Hay pendientes, pero el cron total no está activo. La tarea exclusiva del Buzón SOL no procesa ventas/compras.':
   d.sin_avance_reciente?'Hay trabajos sin avance reciente. Abre la empresa para ver el estado.':'';
  aviso.hidden=!aviso.textContent;
  const solicitar=document.querySelector('[data-sync-solicitar]');if(solicitar)solicitar.disabled=Boolean(d.pausado);
  const pausa=document.querySelector('[data-sync-pausa]');if(pausa){pausa.elements.accion.value=d.pausado?'reanudar':'pausar';pausa.querySelector('button').textContent=d.pausado?'Reanudar sincronización':'Pausar sincronización';pausa.onsubmit=()=>confirm(d.pausado?'¿Reanudar los trabajos pendientes?':'¿Pausar la asignación de nuevas tareas?');}
  pintar();
 }
 async function cargar(forzar=false){
  if(document.hidden||(cargando&&!forzar))return;
  if(cargando&&control)control.abort();
  cargando=true;const paso=++revision,ctl=new AbortController();control=ctl;const limite=setTimeout(()=>ctl.abort(),12000);
  try{
   const url=new URL(panel.dataset.endpoint,window.location.href);
   url.searchParams.set('anio',String(anioSeleccionado()));
   const r=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store',signal:ctl.signal});
   if(!r.ok)throw new Error('HTTP '+r.status);const d=await r.json();if(d.error)throw new Error(d.error);
   if(paso===revision)resumen(d);
  }catch(e){if(paso===revision)estado.textContent='No se pudo actualizar el avance. Comprueba la conexión y vuelve a intentarlo.';}
  finally{clearTimeout(limite);if(paso===revision){control=null;cargando=false;}}
 }
 cargar();setInterval(cargar,15000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)cargar();});
})();
