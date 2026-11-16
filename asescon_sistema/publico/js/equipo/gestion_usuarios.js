(()=>{
 const assignments=JSON.parse(document.getElementById('assignmentsData').textContent);
 const menuPerms=JSON.parse(document.getElementById('menuPermsData').textContent);
 const dialogs=id=>document.getElementById(id);
 document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>dialogs(b.dataset.close)?.close()));
 document.getElementById('openNewUser')?.addEventListener('click',()=>dialogs('newUserDialog').showModal());
 const nuevoForm=document.getElementById('newUserForm');
 const tabsNuevo=[...document.querySelectorAll('[data-nuevo-tab]')];
 function abrirTabNuevo(tab){
   tabsNuevo.forEach(b=>b.setAttribute('aria-selected',String(b.dataset.nuevoTab===tab)));
   document.querySelectorAll('[data-nuevo-panel]').forEach(p=>{p.hidden=p.dataset.nuevoPanel!==tab;});
 }
 tabsNuevo.forEach(b=>b.addEventListener('click',()=>abrirTabNuevo(b.dataset.nuevoTab)));
 const verTodosNuevo=document.getElementById('nuevoUsuarioVerTodo');
 const listaModulosNuevo=document.getElementById('nuevoUsuarioModulos');
 verTodosNuevo?.addEventListener('change',()=>{listaModulosNuevo.hidden=verTodosNuevo.checked;});
 document.getElementById('nuevoUsuarioEmpresasToggle')?.addEventListener('click',e=>{
   const panel=document.getElementById('nuevoUsuarioEmpresas');
   panel.hidden=!panel.hidden;
   e.currentTarget.setAttribute('aria-expanded',String(!panel.hidden));
 });
 const globalNuevo = document.getElementById('nuevoUsuarioTodasEmpresas');
 globalNuevo?.addEventListener('change', () => {
   document.getElementById('nuevoUsuarioEmpresasManual').hidden = globalNuevo.checked;
 });
 const tipoId = document.getElementById('nuevoUsuarioTipo');
 const campoRuc = document.getElementById('nuevoUsuarioRuc');
 const fichaRuc = document.getElementById('nuevoUsuarioFichaRuc');
 let rucValidado = '';
 let consultaRuc = null;
 function cambiarTipoIdentificacion() {
   const esRuc = tipoId.value === 'RUC';
   const usuario = nuevoForm.elements.usuario;
   document.getElementById('nuevoUsuarioNombre').hidden = esRuc;
   document.getElementById('nuevoUsuarioRucPanel').hidden = !esRuc;
   usuario.required = !esRuc;
   usuario.disabled = esRuc;
   campoRuc.required = esRuc;
   campoRuc.disabled = !esRuc;
   if (esRuc && campoRuc.value.length === 11) consultarRuc();
   if (!esRuc) consultaRuc?.abort();
 }
 async function consultarRuc() {
   consultaRuc?.abort();
   rucValidado = '';
   const ruc = campoRuc.value;
   if (campoRuc.disabled || !/^\d{11}$/.test(ruc)) {
     fichaRuc.textContent = '';
     return;
   }
   const peticion = new AbortController();
   consultaRuc = peticion;
   fichaRuc.textContent = 'Consultando datos públicos del RUC…';
   try {
     const respuesta = await fetch(nuevoForm.dataset.fichaRucUrl, {
       method: 'POST',
       credentials: 'same-origin',
       signal: peticion.signal,
       body: new URLSearchParams({ruc, contexto: 'equipo', csrf_token: nuevoForm.dataset.fichaRucCsrf}),
     });
     const datos = await respuesta.json();
     if (consultaRuc !== peticion || peticion.signal.aborted) return;
     if (!respuesta.ok || !datos.ok || datos.data?.ruc !== ruc || !datos.data.razon_social) {
       throw new Error(datos.mensaje || 'No se encontró la razón social.');
     }
     rucValidado = ruc;
     fichaRuc.textContent = [datos.data.razon_social, datos.data.estado_ruc, datos.data.condicion_ruc].filter(Boolean).join(' · ');
   } catch (error) {
     if (!peticion.signal.aborted) fichaRuc.textContent = error.message || 'No se pudo consultar el RUC.';
   }
 }
 tipoId?.addEventListener('change', cambiarTipoIdentificacion);
 campoRuc?.addEventListener('input', () => {
   campoRuc.value = campoRuc.value.replace(/\D/g, '').slice(0, 11);
   consultarRuc();
 });
 document.getElementById('nuevoUsuarioConsultarRuc')?.addEventListener('click', consultarRuc);
 if (tipoId) cambiarTipoIdentificacion();
 nuevoForm?.addEventListener('invalid',e=>{
   const contenedor=e.target.closest('[data-nuevo-panel]');
   if(contenedor) abrirTabNuevo(contenedor.dataset.nuevoPanel);
 },true);
 nuevoForm?.addEventListener('submit',e=>{
   const clave=nuevoForm.elements.password.value;
   const confirmar=nuevoForm.elements.password_confirmacion.value;
   const mensaje=document.getElementById('nuevoUsuarioMensaje');
   if (tipoId?.value === 'RUC' && (!rucValidado || campoRuc.value !== rucValidado)) {
     e.preventDefault();
     abrirTabNuevo('acceso');
     mensaje.textContent = 'Consulta el RUC y espera a que aparezca su razón social.';
     campoRuc.focus();
     return;
   }
   if(clave!==confirmar){
     e.preventDefault();
     abrirTabNuevo('acceso');
     mensaje.textContent='Las contraseñas no coinciden.';
     nuevoForm.elements.password_confirmacion.focus();
   }else{
     mensaje.textContent='';
   }
 });
 function normalizeSearch(v) {
   return (v || '').toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
 }
 const selectores = new Map();
 document.querySelectorAll('[data-selector-empresas]').forEach(panel => {
   const buscar = panel.querySelector('[data-empresa-buscar]');
   const filas = [...panel.querySelectorAll('[data-empresa-fila]')];
   const conteo = panel.querySelector('[data-empresa-conteo]');
   const vacio = panel.querySelector('[data-empresa-vacio]');
   function actualizar() {
     const consulta = normalizeSearch(buscar.value);
     filas.forEach(fila => { fila.hidden = consulta !== '' && !normalizeSearch(fila.dataset.search).includes(consulta); });
     const seleccionadas = filas.filter(fila => fila.querySelector('input').checked).length;
     const visibles = filas.filter(fila => !fila.hidden).length;
     conteo.textContent = `${seleccionadas} de ${filas.length} seleccionadas · ${visibles} visibles`;
     vacio.hidden = visibles !== 0;
   }
   function marcar(todas) {
     filas.forEach(fila => { fila.querySelector('input').checked = todas; });
     actualizar();
   }
   panel.querySelector('[data-empresa-marcar]').addEventListener('click', () => marcar(true));
   panel.querySelector('[data-empresa-limpiar]').addEventListener('click', () => marcar(false));
   panel.querySelector('[data-empresa-borrar-busqueda]')?.addEventListener('click', () => {
     buscar.value = '';
     actualizar();
     buscar.focus();
   });
   buscar.addEventListener('input', actualizar);
   panel.addEventListener('change', actualizar);
   actualizar();
   selectores.set(panel.id, {buscar, actualizar});
 });
 const assignGlobal = document.getElementById('assignGlobal');
 const assignManual = document.getElementById('assignManual');
 assignGlobal.addEventListener('change', () => { assignManual.hidden = assignGlobal.checked; });
 document.querySelectorAll('.assign-client-btn').forEach(btn => btn.addEventListener('click', () => {
   const id = String(btn.dataset.userId);
   const dlg = dialogs('assignClientsDialog');
   const {buscar, actualizar} = selectores.get('assignManual');
   document.getElementById('assignUserId').value = id;
   document.getElementById('assignUserName').textContent = btn.dataset.userName || '';
   const empresas = assignments[id] || {};
   assignManual.querySelectorAll('input[name="clientes_asignados[]"]').forEach(check => {
     check.checked = Boolean(empresas[check.dataset.clientId]);
   });
   assignGlobal.checked = btn.dataset.userAll === '1';
   assignManual.hidden = assignGlobal.checked;
   buscar.value = '';
   actualizar();
   dlg.showModal();
   if (!assignGlobal.checked) buscar.focus();
 }));
 const menuDlg=dialogs('menuPermissionsDialog'),menuAll=document.getElementById('menuAll'),menuCount=document.getElementById('menuCount');
 function menuChecks(){return [...menuDlg.querySelectorAll('input[name="menu[]"]')];}
 function renderCurrentPermissions(){const checked=menuChecks().filter(x=>x.checked),box=document.getElementById('currentPermissionChips'),count=document.getElementById('currentPermissionCount');count.textContent=checked.length+' '+(checked.length===1?'módulo':'módulos');box.innerHTML='';if(!checked.length){const e=document.createElement('span');e.className='permission-empty';e.textContent='Sin módulos visibles';box.appendChild(e);return;}checked.forEach(ch=>{const chip=document.createElement('span');chip.className='permission-chip';chip.textContent=ch.dataset.name||ch.value;box.appendChild(chip);});}
 function updateMenuCount(){const c=menuChecks(),n=c.filter(x=>x.checked).length;menuCount.textContent=n+' de '+c.length+' seleccionados';menuAll.checked=n===c.length&&c.length>0;renderCurrentPermissions();}
 document.querySelectorAll('.menu-perm-btn').forEach(btn=>btn.addEventListener('click',()=>{const id=String(btn.dataset.userId);document.getElementById('menuUserId').value=id;document.getElementById('menuUserName').textContent=btn.dataset.userName||'';const current=menuPerms[id]||menuPerms[Number(id)]||{};menuChecks().forEach(ch=>ch.checked=Number(current[ch.dataset.code]||0)===1);updateMenuCount();menuDlg.showModal();}));
 menuChecks().forEach(ch=>ch.addEventListener('change',updateMenuCount));menuAll?.addEventListener('change',()=>{menuChecks().forEach(ch=>ch.checked=menuAll.checked);updateMenuCount();});document.getElementById('markAllMenu')?.addEventListener('click',()=>{menuChecks().forEach(ch=>ch.checked=true);updateMenuCount();});document.getElementById('clearAllMenu')?.addEventListener('click',()=>{menuChecks().forEach(ch=>ch.checked=false);updateMenuCount();});
 document.querySelectorAll('.password-user-btn').forEach(btn=>btn.addEventListener('click',()=>{document.getElementById('passwordUserId').value=btn.dataset.userId;document.getElementById('passwordUserName').textContent=btn.dataset.userName||'';dialogs('passwordUserDialog').showModal();}));
 document.querySelectorAll('.role-user-btn').forEach(btn=>btn.addEventListener('click',()=>{document.getElementById('roleUserId').value=btn.dataset.userId;document.getElementById('roleUserName').textContent=btn.dataset.userName||'';const s=document.getElementById('roleUserSelect');if([...s.options].some(o=>o.value===String(btn.dataset.roleId)))s.value=String(btn.dataset.roleId);else s.value='';dialogs('roleUserDialog').showModal();}));
 document.querySelectorAll('.delete-user-btn').forEach(btn=>btn.addEventListener('click',()=>{document.getElementById('deleteUserId').value=btn.dataset.userId;document.getElementById('deleteUserName').textContent=btn.dataset.userName||'';document.getElementById('deleteUserConfirm').value='';dialogs('deleteUserDialog').showModal();}));
})();
