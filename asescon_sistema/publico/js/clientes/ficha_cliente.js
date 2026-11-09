(() => {
  const form = document.getElementById('clienteForm');
  if (!form) return;
  const creando = form.dataset.creando === '1';
  const reactivando = form.dataset.reactivando === '1';
  const idCliente = Number(form.dataset.idCliente || 0);
  const puedeEditar = form.dataset.puedeEditar === '1';
  let fichaOk = form.dataset.fichaRucOk === '1';
  const byId = id => document.getElementById(id);
  const set = (id, valor) => { const elemento=byId(id); if(elemento) elemento.value=valor ?? ''; };
  const estadoSire=byId('sireEstado'), estadoRuc=byId('rucEstado'), fuenteRuc=byId('rucFuente'), btnSire=byId('conectarSire'), btnRuc=byId('consultarRuc');
  const tokenSire=()=>document.querySelector('meta[name="asescon-csrf-sire"]')?.content?.trim() || '';
  const tokenFichaRuc=()=>document.querySelector('meta[name="asescon-csrf-ficha-ruc"]')?.content?.trim() || '';
  const base=()=>({ruc:byId('ruc').value.replace(/\D/g,''),usuario_sol:byId('usuario_sol').value.trim(),clave_sol:byId('clave_sol').value});
  const credencialesSire=()=>{const c=base();return {...c,id_cliente:idCliente,client_id_sunat:byId('client_id_sire').value.trim(),client_secret_sunat:byId('client_secret_sire').value};};
  async function fetchConTiempo(url,opciones,ms){const ctrl=new AbortController();const timer=setTimeout(()=>ctrl.abort(),ms);try{return await fetch(url,{...opciones,credentials:'same-origin',signal:ctrl.signal});}finally{clearTimeout(timer);}}

  btnRuc?.addEventListener('click', async () => {
    const c=base();
    if(!/^\d{11}$/.test(c.ruc)) return alert('El RUC debe contener exactamente 11 dígitos.');
    const csrfRuc=tokenFichaRuc(); if(!csrfRuc) return alert('No se encontró el token de seguridad para la consulta RUC.');
    btnRuc.disabled=true; if(btnSire)btnSire.disabled=true; btnRuc.textContent='Consultando...'; estadoRuc.className='ruc-state muted'; estadoRuc.textContent='Consultando datos del RUC...';
    try{
      const res=await fetchConTiempo('../../api/sunat/consulta_ficha_ruc.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json','X-CSRF-Token':csrfRuc},body:new URLSearchParams({ruc:c.ruc,id_cliente:String(idCliente)})},18000);
      const j=await asesconLeerRespuestaApi(res,'Consulta RUC'); const d=j.data||{};
      [['razon_social',d.razon_social],['nombre_comercial',d.nombre_comercial],['tipo_contribuyente',d.tipo_contribuyente],['estado_ruc',d.estado_ruc],['condicion_ruc',d.condicion_ruc],['ubigeo',d.ubigeo],['domicilio_fiscal',d.domicilio_fiscal],['actividad_economica',d.actividad_economica],['fecha_inscripcion',d.fecha_inscripcion],['fecha_inicio_actividades',d.fecha_inicio_actividades],['fuente_datos_ruc',d.fuente]].forEach(([k,v])=>set(k,v));
      set('fecha_consulta_ruc',new Date().toISOString().slice(0,19).replace('T',' ')); fichaOk=true; estadoRuc.className='ruc-state ok'; estadoRuc.textContent='Datos del RUC obtenidos correctamente.'; if(fuenteRuc){fuenteRuc.className='ruc-state muted';fuenteRuc.textContent='Fuente de datos: '+String(d.fuente||'No informada')+'.';}
    }catch(e){fichaOk=false;estadoRuc.className='ruc-state bad';estadoRuc.textContent=e.name==='AbortError'?'La consulta del RUC superó 18 segundos. Intenta nuevamente.':e.message;}
    finally{btnRuc.disabled=false;btnRuc.textContent='Consultar RUC';if(btnSire)btnSire.disabled=false;}
  });

  btnSire?.addEventListener('click', async () => {
    const c=credencialesSire();
    if(!/^\d{11}$/.test(c.ruc)) return alert('El RUC debe contener exactamente 11 dígitos.');
    if(!c.usuario_sol||(!c.clave_sol&&!idCliente)||!c.client_id_sunat||(!c.client_secret_sunat&&!idCliente)) return alert('Para validar SIRE completa Usuario SOL, Clave SOL, Client ID y Client Secret.');
    const csrf=tokenSire(); if(!csrf) return alert('No se encontró el token de seguridad SIRE.');
    btnSire.disabled=true;if(btnRuc)btnRuc.disabled=true;btnSire.textContent='Validando...';estadoSire.className='connection-state muted';estadoSire.textContent='Validando SIRE (máximo 40 s)...';
    try{
      const res=await fetchConTiempo('../../api/sunat/autenticacion.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json','X-CSRF-Token':csrf},body:new URLSearchParams(c)},40000);
      await asesconLeerRespuestaApi(res,'Validación SIRE');estadoSire.className='connection-state ok';estadoSire.textContent='SIRE validado correctamente. Al guardar no se repetirá esta validación.';
    }catch(e){estadoSire.className='connection-state bad';estadoSire.textContent=e.name==='AbortError'?'SUNAT SIRE no respondió dentro de 40 segundos. La validación se canceló; puedes volver a intentar.':e.message;}
    finally{btnSire.disabled=false;if(btnRuc)btnRuc.disabled=false;btnSire.textContent='Validar SIRE';}
  });

  if(puedeEditar)form.addEventListener('submit',e=>{if((creando||reactivando)&&!fichaOk){e.preventDefault();alert('Antes de guardar debes consultar los datos del RUC.');return;}if(creando&&!byId('razon_social').value.trim()){e.preventDefault();alert('Consulta primero los datos del RUC.');}});
})();
