(() => {
 const modal=document.getElementById('detalleLiquidacion');if(!modal)return;
 const get=id=>document.getElementById(id),form=get('formularioDetalleLiquidacion');let datos=null,libro='ventas',version=0,origen=null,clienteActual=0;
 const dinero=v=>'S/ '+Number(v||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
 function tabla(destino,filas,campos){const cuerpo=get(destino);cuerpo.replaceChildren();for(const f of filas){const tr=document.createElement('tr');for(const campo of campos){const td=document.createElement('td');td.textContent=['base','igv','total','base_imponible','importe_total'].includes(campo)?dinero(f[campo]):String(f[campo]??'—');tr.append(td);}cuerpo.append(tr);}if(!filas.length){const tr=document.createElement('tr'),td=document.createElement('td');td.colSpan=campos.length;td.textContent='No hay registros de este libro.';tr.append(td);cuerpo.append(tr);}}
 function detalle(){const q=get('liquidacionFiltroDetalle').value.toLocaleLowerCase('es');const filas=(datos?.[libro]||[]).filter(f=>[f.documento,f.razon_social,f.serie,f.numero].join(' ').toLocaleLowerCase('es').includes(q));tabla('liquidacionComprobantes',filas,['fecha_emision','tipo_comprobante','serie','numero','documento','razon_social','base_imponible','igv','importe_total']);}
 function calcular(){const centimos=n=>Math.round(Number(form.elements[n]?.value||0)*100);const bruto=centimos('igv_ventas')-centimos('credito_compras');const igv=bruto-centimos('credito_fiscal_anterior')-centimos('percepciones')-centimos('retenciones');const renta=Math.max(0,centimos('renta')-centimos('saldo_anterior'));const total=Math.max(0,igv)+renta+['essalud','onp','renta_quinta','itan','otros'].reduce((s,n)=>s+centimos(n),0);get('liquidacionResultadoIgv').textContent=dinero(bruto/100);get('liquidacionSaldoIgv').textContent=dinero(igv/100);get('liquidacionTotalPagar').textContent=dinero(total/100);}
 async function abrir(id,boton,recargar=false){
  const v=++version;clienteActual=id;origen=boton||origen;get('liquidacionDetalleContenido').hidden=true;get('liquidacionDetalleEstado').hidden=false;get('liquidacionDetalleEstado').textContent='Cargando libros y liquidación…';if(!modal.open)modal.showModal();
  const periodo=document.getElementById('liqAnio').value+document.getElementById('liqMes').value;form.elements.periodo.value=periodo;
  try{const url=new URL(modal.dataset.url,location.href);url.searchParams.set('id_cliente',id);url.searchParams.set('periodo',periodo);if(recargar)url.searchParams.set('recargar','1');const r=await fetch(url,{credentials:'same-origin'});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.mensaje||'No se pudo cargar.');if(v!==version||!modal.open)return;datos=j.data;
   get('liquidacionDetalleTitulo').textContent=datos.cliente.razon_social+' · '+periodo;get('liquidacionClienteId').value=id;get('liquidacionClienteRuc').value=datos.cliente.ruc;
   get('liquidacionFuentes').textContent='RVIE: '+(datos.fuentes.ventas?'importado':'sin importar')+' · RCE: '+(datos.fuentes.compras?'importado':'sin importar')+(datos.guardada?' · Liquidación guardada':' · Borrador pendiente de revisión')+(datos.requiere_revision_legacy?' · Registro anterior: revisa el desglose antes de volver a guardar.':'');
   tabla('liquidacionResumenVentas',datos.resumen_ventas.grupos,['tipo','documentos','base','igv','total']);tabla('liquidacionResumenCompras',datos.resumen_compras.grupos,['tipo','documentos','base','igv','total']);
   for(const [n,valor] of Object.entries(datos.calculo))if(form.elements[n])form.elements[n].value=Number(valor).toFixed(2);
   form.elements.notas.value=datos.notas||'';form.elements.estado.value=['PENDIENTE','REVISADA','PAGADA'].includes(datos.estado)?datos.estado:'PENDIENTE';form.elements.confirmacion_revision.checked=false;form.elements.confirmacion_manual.checked=false;get('liquidacionConfirmacionManual').hidden=datos.fuentes.ventas&&datos.fuentes.compras;
   form.querySelectorAll('input:not([type=hidden]),textarea,select,button').forEach(x=>x.disabled=!datos.puede_editar);
   get('liquidacionRecargar').disabled=!datos.puede_editar;
   const exportar=new URL('../../api/sunat/exportar_liquidacion.php',location.href);exportar.searchParams.set('id_cliente',id);exportar.searchParams.set('periodo',periodo);
   for(const [elemento,formato] of [['liquidacionPdfIndividual','pdf'],['liquidacionXlsxIndividual','xlsx']]){const u=new URL(exportar);u.searchParams.set('formato',formato);get(elemento).href=u.href;get(elemento).setAttribute('aria-disabled',String(!datos.guardada));}
   detalle();calcular();get('liquidacionDetalleEstado').hidden=true;get('liquidacionDetalleContenido').hidden=false;
  }catch(e){if(v===version)get('liquidacionDetalleEstado').textContent=e.message;}
 }
 document.querySelectorAll('[data-liquidacion-detalle]').forEach(b=>b.addEventListener('click',()=>abrir(Number(b.dataset.idCliente),b)));
 modal.querySelector('[data-liquidacion-cerrar]').addEventListener('click',()=>modal.close());modal.addEventListener('close',()=>{version++;origen?.focus();});get('liquidacionRecargar').addEventListener('click',()=>abrir(clienteActual,null,true));
 form.querySelectorAll('[data-liquidacion-importe]').forEach(x=>x.addEventListener('input',calcular));get('liquidacionFiltroDetalle').addEventListener('input',detalle);modal.querySelectorAll('[data-liquidacion-libro]').forEach(b=>b.addEventListener('click',()=>{libro=b.dataset.liquidacionLibro;detalle();}));
 const inicial=Number(modal.dataset.clienteInicial);if(inicial>0)abrir(inicial,null);
})();
