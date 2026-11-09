<?php
                                                                           
if(!isset($d,$conexion,$csrf)){http_response_code(404);exit;}
$waArchivado=(bool)($enArchivados??false);
$waUrl=htmlspecialchars(asesconUrl('controladores/clientes/ControladorWhatsApp.php'),ENT_QUOTES,'UTF-8');
$waId=(int)$d['id_recordatorio'];
$waEsc=static fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
?>
<div class="gestion-actions">
<?php if(asesconTienePermiso($conexion,'WHATSAPP','editar')):?>
<details class="gestion-editor"><summary class="mini-btn">Editar recordatorio</summary>
<form method="post" action="<?=$waUrl?>" class="gestion-inline-form">
<input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="editar_recordatorio"><input type="hidden" name="id_recordatorio" value="<?=$waId?>">
<label>Teléfono<input name="telefono" value="<?=$waEsc($d['telefono'])?>"></label>
<label>Tipo<select name="tipo"><?php foreach(['VENCIMIENTO','PENDIENTE','DOCUMENTO','HONORARIO','GENERAL'] as $tipo):?><option value="<?=$tipo?>" <?=$tipo===$d['tipo']?'selected':''?>><?=$tipo?></option><?php endforeach;?></select></label>
<label>Programado para<input type="datetime-local" name="fecha_programada" value="<?=$waEsc($d['fecha_programada']?date('Y-m-d\TH:i',strtotime((string)$d['fecha_programada'])):'')?>"></label>
<label>Mensaje<textarea name="mensaje" rows="4" required><?=$waEsc($d['mensaje'])?></textarea></label>
<button class="mini-btn">Guardar cambios</button></form></details>
<?php if(($d['estado']??'')==='ENVIADO'):?>
<form method="post" action="<?=$waUrl?>" onsubmit="return confirm('¿Revertir este recordatorio a pendiente? No deshace mensajes ya enviados por WhatsApp.');"><input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="reabrir_recordatorio"><input type="hidden" name="id_recordatorio" value="<?=$waId?>"><button class="mini-btn">Volver a pendiente</button></form>
<?php else:?>
<form method="post" action="<?=$waUrl?>" onsubmit="return confirm('¿Confirmas que enviaste este recordatorio?');"><input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="marcar_enviado"><input type="hidden" name="id_recordatorio" value="<?=$waId?>"><button class="mini-btn">Marcar enviado</button></form>
<?php endif;?>
<form method="post" action="<?=$waUrl?>"><input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="<?=$waArchivado?'restaurar_recordatorio':'archivar_recordatorio'?>"><input type="hidden" name="id_recordatorio" value="<?=$waId?>"><button class="mini-btn"><?=$waArchivado?'Desarchivar / mostrar':'Archivar / ocultar'?></button></form>
<?php endif;?>
<?php if(asesconTienePermiso($conexion,'WHATSAPP','eliminar')):?>
<form method="post" action="<?=$waUrl?>" onsubmit="return confirm('¿Eliminar este recordatorio DEFINITIVAMENTE, incluido su mensaje?');"><input type="hidden" name="csrf_token" value="<?=$waEsc($csrf)?>"><input type="hidden" name="accion" value="eliminar_recordatorio"><input type="hidden" name="id_recordatorio" value="<?=$waId?>"><button class="mini-btn danger-btn">Eliminar recordatorio</button></form>
<?php endif;?>
</div>
