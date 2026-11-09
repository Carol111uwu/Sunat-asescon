(() => {
 const cliente=document.getElementById('correoClienteVista');if(!cliente)return;
 const asunto=document.querySelector('[name=plantilla_asunto]'),mensaje=document.querySelector('[name=plantilla_mensaje]');
 const enlace=document.getElementById('correoVistaEnviar');const base=enlace.href;
 function actualizar(){const opcion=cliente.selectedOptions[0];if(!cliente.value){document.getElementById('correoVistaDestinatario').textContent='Selecciona un cliente.';document.getElementById('correoVistaAsunto').textContent='Selecciona un cliente para ver el asunto final.';document.getElementById('correoVistaMensaje').textContent='';enlace.href=base;return;}
  const tipos=[...document.querySelectorAll('[data-entregable-vista]:checked')].map(x=>'• '+x.value).join('\n');const valores={'{cliente}':opcion.dataset.nombre,'{ruc}':opcion.dataset.ruc,'{entregables}':tipos||'(Selecciona entregables)'};
  const sustituir=v=>String(v).replace(/\{cliente\}|\{ruc\}|\{entregables\}/g,t=>valores[t]);
  document.getElementById('correoVistaDestinatario').textContent=opcion.dataset.correo||'El cliente no tiene correo registrado. Complétalo en su ficha antes de enviar.';
  document.getElementById('correoVistaAsunto').textContent=sustituir(asunto.value);document.getElementById('correoVistaMensaje').textContent=sustituir(mensaje.value);
  const url=new URL(base);url.searchParams.set('id_cliente',cliente.value);enlace.href=url.href;
 }
 cliente.addEventListener('change',actualizar);asunto.addEventListener('input',actualizar);mensaje.addEventListener('input',actualizar);document.querySelectorAll('[data-entregable-vista]').forEach(x=>x.addEventListener('change',actualizar));actualizar();
})();
