<?php if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; } ?>
<?php
require_once __DIR__.'/../../../servicios/textos/ArchivoTextos.php';
$honorarioArchivados=(new ArchivoTextos((int)asesconUsuarioActual($conexion)['id_usuario']))->listar();
$verArchivados=($_GET['ver']??'')==='archivados';
$totalArchivados=0;
foreach($filas as $honorarioFila){if(isset($honorarioArchivados['honorario:'.$honorarioFila['id_honorario']]))$totalArchivados++;}
$filas=array_values(array_filter($filas,static fn($h)=>isset($honorarioArchivados['honorario:'.$h['id_honorario']])===$verArchivados));
?>
<div class="page-header"><div><h2>Honorarios pendientes</h2><p>Control de honorarios del estudio por cada empresa.</p></div></div>
<?php if(isset($_GET['guardado'])||isset($_GET['pagado'])):?><div class="notice ok">Información actualizada.</div><?php endif;?>
<?php if(isset($_SESSION['error_honorarios'])):?><div class="notice bad"><?=htmlspecialchars((string)$_SESSION['error_honorarios'])?></div><?php unset($_SESSION['error_honorarios']);endif;?>
<?php if(asesconTienePermiso($conexion,'HONORARIOS','crear')):?><section class="panel module-panel"><form class="module-form" method="post" action="<?=htmlspecialchars(asesconUrl('controladores/clientes/ControladorHonorarios.php'),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="crear"><div class="grid"><label>Cliente<select name="id_cliente" required><option value="">Seleccione</option><?php foreach($clientes as $c):?><option value="<?php echo (int)$c['id_cliente'];?>"><?php echo htmlspecialchars($c['razon_social'].' · '.$c['ruc']);?></option><?php endforeach;?></select></label><label>Concepto<input name="concepto" required></label><label>Monto<input type="number" step="0.01" min="0" name="monto" required></label><label>Fecha emisión<input type="date" name="fecha_emision"></label><label>Fecha vencimiento<input type="date" name="fecha_vencimiento"></label></div><label style="display:flex;flex-direction:column;margin-top:12px">Observaciones<textarea name="observaciones"></textarea></label><button class="primary-btn" style="margin-top:12px">Guardar honorario</button></form></section><?php endif;?>
<nav class="gestion-vistas" aria-label="Estado de honorarios"><a class="<?=$verArchivados?'':'active'?>" href="servicios_cobros.php?seccion=honorarios&amp;ver=visibles">Honorarios visibles</a><a class="<?=$verArchivados?'active':''?>" href="servicios_cobros.php?seccion=honorarios&amp;ver=archivados">Archivados / ocultos (<?=$totalArchivados?>)</a></nav>
<section class="panel module-panel" style="margin-top:18px"><div class="table-container"><table>
<thead><tr><th>Cliente</th><th>Concepto / observaciones</th><th>Monto</th><th>Emisión</th><th>Vencimiento</th><th>Pago</th><th>Estado</th><th>Acción</th></tr></thead>
<tbody><?php if($filas===[]):?><tr><td colspan="8">No hay honorarios registrados.</td></tr>
<?php else:foreach($filas as $d):?><tr>
<td><strong><?php echo htmlspecialchars($d['razon_social']);?></strong><small><?php echo htmlspecialchars($d['ruc']);?></small></td>
<td><?php echo htmlspecialchars($d['concepto']);?><small data-texto-tipo="honorario" data-texto-id="<?php echo (int)$d['id_honorario'];?>"><?php echo htmlspecialchars((string)($d['observaciones']?:'Sin observaciones'));?></small></td>
<td>S/ <?php echo number_format((float)$d['monto'],2);?></td>
<td><?php echo $d['fecha_emision']?htmlspecialchars(date('d/m/Y',strtotime($d['fecha_emision']))):'—';?></td>
<td><?php echo $d['fecha_vencimiento']?htmlspecialchars(date('d/m/Y',strtotime($d['fecha_vencimiento']))):'—';?></td>
<td><?php echo $d['fecha_pago']?htmlspecialchars(date('d/m/Y',strtotime($d['fecha_pago']))):'—';?></td>
<td><?php echo htmlspecialchars($d['estado']);?></td>
<td>
<?php if(asesconTienePermiso($conexion,'HONORARIOS','editar')):?>
<details class="gestion-editor"><summary class="mini-btn">Editar honorario</summary>
<form method="post" action="<?=htmlspecialchars(asesconUrl('controladores/clientes/ControladorHonorarios.php'),ENT_QUOTES,'UTF-8')?>" class="gestion-inline-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="editar"><input type="hidden" name="id_honorario" value="<?=(int)$d['id_honorario']?>">
<label>Concepto<input required maxlength="180" name="concepto" value="<?=htmlspecialchars((string)$d['concepto'],ENT_QUOTES,'UTF-8')?>"></label>
<label>Monto<input required type="number" min="0" step="0.01" name="monto" value="<?=htmlspecialchars((string)$d['monto'])?>"></label>
<label>Fecha emisión<input type="date" name="fecha_emision" value="<?=htmlspecialchars((string)$d['fecha_emision'])?>"></label>
<label>Fecha vencimiento<input type="date" name="fecha_vencimiento" value="<?=htmlspecialchars((string)$d['fecha_vencimiento'])?>"></label>
<label>Observaciones<textarea name="observaciones" rows="3"><?=htmlspecialchars((string)$d['observaciones'])?></textarea></label>
<button class="mini-btn">Guardar cambios</button></form></details>
<form method="post" action="<?=htmlspecialchars(asesconUrl('controladores/clientes/ControladorHonorarios.php'),ENT_QUOTES,'UTF-8')?>" onsubmit="return confirm('¿Confirmas este cambio de estado del honorario?');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>">
<input type="hidden" name="accion" value="<?php echo $d['estado']==='PAGADO'?'reabrir':'pagar';?>">
<input type="hidden" name="id_honorario" value="<?php echo (int)$d['id_honorario'];?>">
<button class="mini-btn"><?php echo $d['estado']==='PAGADO'?'Revertir a pendiente':'Marcar pagado';?></button>
</form>
<?php endif;?>
<?php if(asesconTienePermiso($conexion,'HONORARIOS','editar')):?><form method="post" action="<?=htmlspecialchars(asesconUrl('controladores/clientes/ControladorHonorarios.php'),ENT_QUOTES,'UTF-8')?>" onsubmit="return confirm('<?=$verArchivados?'¿Volver a mostrar este honorario?':'¿Ocultar este honorario en Archivados?'?>');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="<?=$verArchivados?'restaurar':'archivar'?>"><input type="hidden" name="id_honorario" value="<?=(int)$d['id_honorario']?>"><button class="mini-btn"><?=$verArchivados?'Mostrar de nuevo':'Archivar / ocultar'?></button></form><?php endif;?>
<?php if(asesconTienePermiso($conexion,'HONORARIOS','eliminar')):?><form method="post" action="<?=htmlspecialchars(asesconUrl('controladores/clientes/ControladorHonorarios.php'),ENT_QUOTES,'UTF-8')?>" onsubmit="return confirm('¿Eliminar definitivamente este honorario? No podrá recuperarse.');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_honorario" value="<?=(int)$d['id_honorario']?>"><button class="mini-btn danger-btn">Eliminar honorario</button></form><?php endif;?></td>
</tr><?php endforeach;endif;?></tbody></table></div></section>

