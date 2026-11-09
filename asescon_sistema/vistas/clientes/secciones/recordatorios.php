<?php
if(!isset($seccionClientes,$conexion,$csrf,$tab)){http_response_code(404);exit;}
$waEsc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$waUrl=$waEsc(asesconUrl('controladores/clientes/ControladorWhatsApp.php'));
?>
<div class="page-header"><div><h2>Recordatorios WhatsApp</h2><p>Mensajes, plantillas y seguimiento. Puedes corregir, ocultar, restaurar o eliminar tus registros internos.</p></div></div>
<div class="integration-note">ASESCON prepara mensajes y abre WhatsApp. Marcar «Enviado» solo cambia el seguimiento interno; no envía ni retira mensajes automáticamente.</div>
<?php if(isset($_GET['guardado'])||isset($_GET['plantilla_guardada'])||isset($_GET['enviado'])||isset($_GET['actualizado'])):?><div class="notice ok">Cambios guardados correctamente.</div><?php endif;?>
<?php if(isset($_SESSION['error_whatsapp'])):?><div class="notice bad"><?=$waEsc($_SESSION['error_whatsapp'])?></div><?php unset($_SESSION['error_whatsapp']);endif;?>
<nav class="wa-tabs" aria-label="Vistas de WhatsApp">
<?php foreach(['recordatorios'=>'Recordatorios','plantillas'=>'Plantillas','historial'=>'Programados / historial','archivados'=>'Archivados / ocultos'] as $clave=>$etiqueta):?>
<a class="<?=$tab===$clave?'active':''?>" href="comunicacion_informes.php?seccion=recordatorios&amp;tab=<?=$clave?>"><?=$etiqueta?></a>
<?php endforeach;?></nav>

<?php if($tab==='recordatorios'):?>
<?php if(asesconTienePermiso($conexion,'WHATSAPP','crear')):?>
<section class="panel module-panel"><form class="module-form" method="post" action="<?=$waUrl?>">
<input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="crear_recordatorio">
<div class="grid"><label>Cliente<select name="id_cliente" id="waCliente" required><option value="">Seleccione</option><?php foreach($clientes as $c):?><option value="<?=(int)$c['id_cliente']?>" data-tel="<?=$waEsc($c['celular']?:'')?>"><?=$waEsc($c['razon_social'].' · '.$c['ruc'])?></option><?php endforeach;?></select></label>
<label>WhatsApp<input id="waTelefono" name="telefono" placeholder="Ej. 987654321"></label>
<label>Tipo<select name="tipo"><option value="VENCIMIENTO">Vencimiento</option><option value="PENDIENTE">Pendiente</option><option value="DOCUMENTO">Documento</option><option value="HONORARIO">Honorario</option><option value="GENERAL">General</option></select></label>
<label>Plantilla<select id="waPlantilla"><option value="">Sin plantilla</option><?php foreach($plantillas as $p):?><option value="<?=(int)$p['id_plantilla']?>" data-mensaje="<?=$waEsc($p['mensaje'])?>"><?=$waEsc($p['nombre'].' · '.($categorias[$p['categoria']]??$p['categoria']))?></option><?php endforeach;?></select></label>
<label>Programar para<input type="datetime-local" name="fecha_programada"></label></div>
<label class="gestion-textarea">Mensaje<textarea id="waMensaje" name="mensaje" required rows="4" placeholder="Escribe el mensaje o selecciona una plantilla"></textarea></label>
<button class="primary-btn">Guardar recordatorio</button></form></section>
<?php endif;?>
<section class="panel module-panel" style="margin-top:18px"><div class="reference-table-title"><div><h2>Recordatorios recientes</h2><p class="reference-subtle">Enviados y pendientes, exceptuando los archivados.</p></div></div>
<div class="table-container"><table><thead><tr><th>Cliente</th><th>Teléfono</th><th>Tipo</th><th>Mensaje</th><th>Programado</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if($filas===[]):?><tr><td colspan="7">No hay recordatorios visibles. Revisa «Archivados / ocultos».</td></tr><?php else:foreach($filas as $d):?>
<tr><td><strong><?=$waEsc($d['razon_social'])?></strong><small><?=$waEsc($d['ruc'])?></small></td><td><?=$waEsc($d['telefono']?:$d['telefono_cliente']?:'—')?></td><td><?=$waEsc($d['tipo'])?></td><td><?=nl2br($waEsc($d['mensaje']))?></td><td><?=$d['fecha_programada']?$waEsc(date('d/m/Y H:i',strtotime((string)$d['fecha_programada']))):'—'?></td><td><?=$waEsc($d['estado'])?></td>
<td><?php $enArchivados=false;require __DIR__.'/acciones_recordatorio.php';?></td></tr>
<?php endforeach;endif;?></tbody></table></div></section>

<?php elseif($tab==='plantillas'):?>
<?php if(asesconTienePermiso($conexion,'WHATSAPP','crear')):?>
<section class="panel module-panel"><div class="reference-table-title"><div><h2>Nueva plantilla</h2><p class="reference-subtle">Crea mensajes reutilizables.</p></div></div>
<form class="module-form" method="post" action="<?=$waUrl?>"><input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="crear_plantilla">
<div class="grid"><label>Nombre de la plantilla<input name="nombre" required placeholder="Ej. Recordatorio IGV mensual"></label>
<label>Categoría<select name="categoria" required><?php foreach($categorias as $k=>$v):?><option value="<?=$waEsc($k)?>"><?=$waEsc($v)?></option><?php endforeach;?></select></label></div>
<label class="gestion-textarea">Mensaje<textarea name="mensaje" rows="5" required placeholder="Escribe el mensaje que deseas guardar"></textarea></label><button class="primary-btn">Guardar plantilla</button></form></section>
<?php endif;?>
<section class="panel module-panel" style="margin-top:18px"><div class="reference-table-title"><div><h2>Plantillas guardadas</h2></div></div>
<div class="template-grid"><?php if($plantillas===[]):?><div class="reference-empty">Aún no hay plantillas visibles.</div><?php else:foreach($plantillas as $p):?>
<article class="template-card"><span><?=$waEsc($categorias[$p['categoria']]??$p['categoria'])?></span><h3><?=$waEsc($p['nombre'])?></h3><p><?=nl2br($waEsc($p['mensaje']))?></p><?php $enArchivados=false;require __DIR__.'/acciones_plantilla.php';?></article>
<?php endforeach;endif;?></div></section>

<?php elseif($tab==='historial'):?>
<section class="panel module-panel"><div class="reference-table-title"><div><h2>Envíos programados</h2><p class="reference-subtle">Recordatorios pendientes con fecha. Puedes editarlos aunque ya hayan pasado a historial.</p></div></div>
<div class="table-container"><table><thead><tr><th>Cliente</th><th>Tipo</th><th>Mensaje</th><th>Programado</th><th>Acciones</th></tr></thead><tbody>
<?php if($programados===[]):?><tr><td colspan="5">No hay envíos programados.</td></tr><?php else:foreach($programados as $d):?>
<tr><td><?=$waEsc($d['razon_social'])?></td><td><?=$waEsc($d['tipo'])?></td><td><?=nl2br($waEsc($d['mensaje']))?></td><td><?=$waEsc(date('d/m/Y H:i',strtotime((string)$d['fecha_programada'])))?></td><td><?php $enArchivados=false;require __DIR__.'/acciones_recordatorio.php';?></td></tr>
<?php endforeach;endif;?></tbody></table></div></section>
<section class="panel module-panel" style="margin-top:18px"><div class="reference-table-title"><div><h2>Historial de enviados</h2></div></div>
<div class="table-container"><table><thead><tr><th>Cliente</th><th>Tipo</th><th>Mensaje</th><th>Enviado</th><th>Acciones</th></tr></thead><tbody>
<?php if($historial===[]):?><tr><td colspan="5">No hay envíos registrados.</td></tr><?php else:foreach($historial as $d):?>
<tr><td><?=$waEsc($d['razon_social'])?></td><td><?=$waEsc($d['tipo'])?></td><td><?=nl2br($waEsc($d['mensaje']))?></td><td><?=$d['fecha_enviado']?$waEsc(date('d/m/Y H:i',strtotime((string)$d['fecha_enviado']))):'—'?></td><td><?php $enArchivados=false;require __DIR__.'/acciones_recordatorio.php';?></td></tr>
<?php endforeach;endif;?></tbody></table></div></section>

<?php else:?>
<section class="panel module-panel"><div class="reference-table-title"><div><h2>Recordatorios archivados</h2><p class="reference-subtle">Ocultos solo para tu cuenta. Puedes editarlos, mostrarlos de nuevo o eliminarlos definitivamente.</p></div></div>
<div class="table-container"><table><thead><tr><th>Cliente</th><th>Mensaje</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if(!$archivados['recordatorios']):?><tr><td colspan="4">No hay recordatorios archivados.</td></tr><?php else:foreach($archivados['recordatorios'] as $d):?>
<tr><td><?=$waEsc($d['razon_social'])?></td><td><?=nl2br($waEsc($d['mensaje']))?></td><td><?=$waEsc($d['estado'])?></td><td><?php $enArchivados=true;require __DIR__.'/acciones_recordatorio.php';?></td></tr>
<?php endforeach;endif;?></tbody></table></div></section>
<section class="panel module-panel" style="margin-top:18px"><h2>Plantillas archivadas</h2><div class="template-grid">
<?php if(!$archivados['plantillas']):?><div class="reference-empty">No hay plantillas archivadas.</div><?php else:foreach($archivados['plantillas'] as $p):?>
<article class="template-card"><span><?=$waEsc($categorias[$p['categoria']]??$p['categoria'])?></span><h3><?=$waEsc($p['nombre'])?></h3><p><?=nl2br($waEsc($p['mensaje']))?></p><?php $enArchivados=true;require __DIR__.'/acciones_plantilla.php';?></article>
<?php endforeach;endif;?></div></section>
<?php endif;?>
