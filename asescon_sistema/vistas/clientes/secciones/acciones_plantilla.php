<?php
if(!isset($p,$conexion,$csrf,$categorias)){http_response_code(404);exit;}
$tplArchivada=(bool)($enArchivados??false);$tplId=(int)$p['id_plantilla'];
$tplUrl=htmlspecialchars(asesconUrl('controladores/clientes/ControladorWhatsApp.php'),ENT_QUOTES,'UTF-8');
$tplEsc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="gestion-actions">
<?php if(asesconEsPrincipal($conexion)&&asesconTienePermiso($conexion,'WHATSAPP','editar')):?>
<details class="gestion-editor"><summary class="mini-btn">Editar plantilla</summary>
<form method="post" action="<?=$tplUrl?>" class="gestion-inline-form">
<input type="hidden" name="csrf_token" value="<?=$tplEsc($csrf)?>"><input type="hidden" name="accion" value="editar_plantilla"><input type="hidden" name="id_plantilla" value="<?=$tplId?>">
<label>Nombre<input name="nombre" required value="<?=$tplEsc($p['nombre'])?>"></label>
<label>Categoría<select name="categoria"><?php foreach($categorias as $cat=>$label):?><option value="<?=$tplEsc($cat)?>" <?=$p['categoria']===$cat?'selected':''?>><?=$tplEsc($label)?></option><?php endforeach;?></select></label>
<label>Mensaje<textarea name="mensaje" rows="4" required><?=$tplEsc($p['mensaje'])?></textarea></label>
<button class="mini-btn">Guardar cambios</button></form></details>
<form method="post" action="<?=$tplUrl?>"><input type="hidden" name="csrf_token" value="<?=$tplEsc($csrf)?>"><input type="hidden" name="accion" value="<?=$tplArchivada?'restaurar_plantilla':'archivar_plantilla'?>"><input type="hidden" name="id_plantilla" value="<?=$tplId?>"><button class="mini-btn"><?=$tplArchivada?'Desarchivar':'Archivar / ocultar'?></button></form>
<?php endif;?>
<?php if(asesconEsPrincipal($conexion)&&asesconTienePermiso($conexion,'WHATSAPP','eliminar')):?>
<form method="post" action="<?=$tplUrl?>" onsubmit="return confirm('¿Eliminar definitivamente esta plantilla?');"><input type="hidden" name="csrf_token" value="<?=$tplEsc($csrf)?>"><input type="hidden" name="accion" value="eliminar_plantilla"><input type="hidden" name="id_plantilla" value="<?=$tplId?>"><button class="mini-btn danger-btn">Eliminar plantilla</button></form>
<?php endif;?>
</div>
