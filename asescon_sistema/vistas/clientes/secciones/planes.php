<?php if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; } ?>
<div class="page-header"><div><h2>Planes de servicios</h2><p>Catálogo de planes comerciales del estudio.</p></div></div>
<?php if(isset($_GET['guardado'])):?><div class="notice ok">Plan guardado.</div><?php endif;?>
<?php if(isset($_SESSION['error_planes'])):?><div class="notice bad"><?=htmlspecialchars((string)$_SESSION['error_planes'])?></div><?php unset($_SESSION['error_planes']);endif;?>
<?php $accionPlan=htmlspecialchars(asesconUrl('controladores/clientes/ControladorPlanes.php'),ENT_QUOTES,'UTF-8');$verArchivados=($_GET['vista']??'')==='archivados';$planesVisibles=array_values(array_filter($filas,static fn($p)=>($p['estado']!=='ACTIVO')===$verArchivados)); ?>
<?php if(asesconTienePermiso($conexion,'PLANES','crear')):?><section class="panel module-panel"><form class="module-form" method="post" action="<?=$accionPlan?>"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="crear"><div class="grid"><label>Nombre<input name="nombre" required></label><label>Precio<input type="number" step="0.01" min="0" name="precio"></label><label>Periodicidad<select name="periodicidad"><option>MENSUAL</option><option>TRIMESTRAL</option><option>ANUAL</option><option>UNICO</option></select></label></div><label class="reference-field">Descripción<textarea name="descripcion"></textarea></label><button class="primary-btn">Crear plan</button></form></section><?php endif;?>
<section class="panel module-panel" style="margin-top:18px">
<nav class="gestion-vistas" aria-label="Estado de los planes"><a class="mini-btn <?=$verArchivados?'':'active'?>" href="servicios_cobros.php?seccion=planes">Planes activos</a><a class="mini-btn <?=$verArchivados?'active':''?>" href="servicios_cobros.php?seccion=planes&amp;vista=archivados">Archivados / ocultos</a></nav>
<div class="table-container"><table><thead><tr><th>Plan</th><th>Descripción</th><th>Precio</th><th>Periodicidad</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if($planesVisibles===[]):?><tr><td colspan="6">No hay planes en esta vista.</td></tr>
<?php else:foreach($planesVisibles as $d):$idPlan=(int)$d['id_plan'];?><tr>
<td><strong><?=htmlspecialchars($d['nombre'])?></strong></td>
<td><div data-texto-tipo="plan" data-texto-id="<?=$idPlan?>"><?=htmlspecialchars((string)($d['descripcion']?:'—'))?></div></td>
<td>S/ <?=number_format((float)$d['precio'],2)?></td><td><?=htmlspecialchars($d['periodicidad'])?></td><td><?=htmlspecialchars($d['estado'])?></td>
<td class="gestion-actions">
<?php if(asesconTienePermiso($conexion,'PLANES','editar')):?>
<details class="gestion-editor"><summary class="mini-btn">Editar plan</summary>
<form method="post" action="<?=$accionPlan?>" class="gestion-inline-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="editar"><input type="hidden" name="id_plan" value="<?=$idPlan?>">
<label>Nombre<input name="nombre" maxlength="180" required value="<?=htmlspecialchars((string)$d['nombre'],ENT_QUOTES,'UTF-8')?>"></label><label>Precio<input type="number" name="precio" min="0" step="0.01" required value="<?=htmlspecialchars((string)$d['precio'])?>"></label>
<label>Periodicidad<select name="periodicidad"><?php foreach(['MENSUAL','TRIMESTRAL','ANUAL','UNICO'] as $periodo):?><option value="<?=$periodo?>" <?=$d['periodicidad']===$periodo?'selected':''?>><?=$periodo?></option><?php endforeach;?></select></label>
<label>Descripción<textarea name="descripcion" rows="3"><?=htmlspecialchars((string)$d['descripcion'])?></textarea></label><button class="mini-btn">Guardar cambios</button></form></details>
<form class="inline-form" method="post" action="<?=$accionPlan?>"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="<?=$verArchivados?'restaurar':'archivar'?>"><input type="hidden" name="id_plan" value="<?=$idPlan?>"><button class="mini-btn"><?=$verArchivados?'Desarchivar':'Archivar / ocultar'?></button></form>
<?php endif;?>
<?php if(asesconTienePermiso($conexion,'PLANES','eliminar')):?><form method="post" action="<?=$accionPlan?>" class="inline-form" onsubmit="return confirm('¿Eliminar este plan definitivamente? Esta acción no se puede deshacer.');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_plan" value="<?=$idPlan?>"><button class="mini-btn danger-btn">Eliminar plan</button></form><?php endif;?>
</td></tr><?php endforeach;endif;?></tbody></table></div></section>

