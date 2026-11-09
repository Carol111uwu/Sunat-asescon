<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/tareas/ControladorTarea.php';
require_once __DIR__.'/../../servicios/textos/ArchivoTextos.php';
asesconExigirPermiso($conexion,'TAREAS','ver');
$moduloActivo='TAREAS';
$datos=(new ControladorTarea($conexion))->datosVista();
extract($datos);
$csrf=asesconCsrf('csrf_tareas');
$vistaArchivadas=($_GET['vista']??'')==='archivadas';
$marcadasArchivo=(new ArchivoTextos((int)asesconUsuarioActual($conexion)['id_usuario']))->listar();

$q=trim((string)($_GET['q']??''));
$idClienteFiltro=(int)($_GET['cliente']??0);
$idResponsableFiltro=(int)($_GET['responsable']??0);
$tipoFiltro=trim((string)($_GET['tipo']??''));
$tiposPrioritarios=['Coactiva','Notificación','SUNAFIL','Esquela','Embargo','Urgente','Inductiva','Fiscalización'];
$tareas=array_values(array_filter($tareas,static function(array $t) use($q,$idClienteFiltro,$idResponsableFiltro,$tipoFiltro,$vistaArchivadas,$marcadasArchivo):bool{
    if(isset($marcadasArchivo['tarea:'.$t['id_tarea']])!==$vistaArchivadas)return false;
    if($idClienteFiltro>0 && (int)($t['id_cliente']??0)!==$idClienteFiltro)return false;
    if($idResponsableFiltro>0 && (int)($t['id_usuario']??0)!==$idResponsableFiltro)return false;
    $texto=strtolower((string)($t['titulo']??'').' '.(string)($t['descripcion']??'').' '.(string)($t['razon_social']??''));
    if($q!=='' && !str_contains($texto,strtolower($q)))return false;
    if($tipoFiltro!=='' && !str_contains($texto,strtolower($tipoFiltro)))return false;
    return true;
}));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tareas - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css'); ?>"><link rel="stylesheet" href="../../publico/css/modulos/tareas.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/modulos/tareas.css'); ?>"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content">
<div class="reference-head-actions"><div class="page-header"><div><h1>Tareas</h1><p>Gestiona el seguimiento de casos y pendientes del equipo.</p></div></div><?php if(asesconTienePermiso($conexion,'TAREAS','crear')):?><a class="reference-btn primary" href="#nuevaTarea">＋ Nueva tarea</a><?php endif;?></div>

<div class="reference-chip-row"><span class="reference-chip-label">Filtros prioritarios</span><?php foreach($tiposPrioritarios as $tipo):?><a class="reference-chip<?php echo strcasecmp($tipoFiltro,$tipo)===0?' active':'';?>" href="?tipo=<?php echo urlencode($tipo);?>"><?php echo htmlspecialchars($tipo);?></a><?php endforeach;?></div>

<section class="reference-panel compact">
<form method="get" class="reference-filter-grid three">
<label class="reference-field"><span>Buscar por tarea</span><div class="reference-search-line"><span>⌕</span><input name="q" value="<?php echo htmlspecialchars($q);?>" placeholder="Ej. coactiva, notificación..."></div></label>
<label class="reference-field"><span>Filtrar por cliente</span><select name="cliente"><option value="0">Todos los clientes</option><?php foreach($clientes as $c):?><option value="<?php echo (int)$c['id_cliente'];?>" <?php echo $idClienteFiltro===(int)$c['id_cliente']?'selected':'';?>><?php echo htmlspecialchars($c['razon_social']);?></option><?php endforeach;?></select></label>
<label class="reference-field"><span>Filtrar por responsable</span><select name="responsable"><option value="0">Todos los responsables</option><?php foreach($responsables as $u):?><option value="<?php echo (int)$u['id_usuario'];?>" <?php echo $idResponsableFiltro===(int)$u['id_usuario']?'selected':'';?>><?php echo htmlspecialchars($u['usuario']);?></option><?php endforeach;?></select></label>
<input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipoFiltro);?>">
<div class="reference-actions" style="grid-column:1/-1"><a class="reference-btn" href="tareas.php">Limpiar</a><button class="reference-btn primary">Aplicar filtros</button></div>
</form>
</section>

<?php if(isset($_GET['guardado'])):?><div class="notice ok">Tarea guardada.</div><?php endif;?>
<?php if(isset($_GET['completada'])):?><div class="notice ok">Estado de tarea actualizado.</div><?php endif;?>
<?php if(isset($_GET['editada'])):?><div class="notice ok">Tarea corregida correctamente.</div><?php endif;?>
<?php if(isset($_GET['eliminada'])):?><div class="notice ok">Tarea eliminada definitivamente.</div><?php endif;?>
<?php if(isset($_GET['archivo'])):?><div class="notice ok">Archivo de tareas actualizado.</div><?php endif;?>
<?php if(isset($_GET['error'])):$errorTarea=(string)($_SESSION['tareas_error']??'No se pudo completar la operación.');unset($_SESSION['tareas_error']);?><div class="notice bad" role="alert"><?php echo htmlspecialchars($errorTarea,ENT_QUOTES,'UTF-8');?></div><?php endif;?>

<?php if(asesconTienePermiso($conexion,'TAREAS','crear')):?><section class="panel task-form" id="nuevaTarea"><div class="panel-header"><div class="panel-title"><span class="panel-icon">＋</span><h2>Nueva tarea</h2></div></div><form method="post" action="../../controladores/tareas/ControladorTarea.php" class="form-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="crear"><div class="form-grid three"><label>Título *<input required name="titulo"></label><label>Empresa<select name="id_cliente"><option value="">Sin empresa</option><?php foreach($clientes as $c):?><option value="<?php echo (int)$c['id_cliente'];?>"><?php echo htmlspecialchars($c['razon_social'].' · '.$c['ruc']);?></option><?php endforeach;?></select></label><label>Responsable<select name="id_usuario"><?php foreach($responsables as $u):?><option value="<?php echo (int)$u['id_usuario'];?>"><?php echo htmlspecialchars($u['usuario']);?></option><?php endforeach;?></select></label><label>Prioridad<select name="prioridad"><option>BAJA</option><option selected>MEDIA</option><option>ALTA</option></select></label><label>Inicio<input type="date" name="fecha_inicio"></label><label class="span-3 descripcion-voz-field">
    <span class="field-label">Descripción</span>
    <textarea name="descripcion" id="descripcion" rows="5" placeholder="Escribe la descripción de la tarea..."></textarea>
    <div class="acciones-voz">
        <div class="acciones-voz-botones">
            <button type="button" id="btnVoz" class="btn-voz">🎤 <span>Dictar por voz</span></button>
            <button type="button" id="btnDetener" class="btn-detener" style="display:none;">■ <span>Detener</span></button>
        </div>
        <span class="voz-ayuda" id="vozEstado">Puedes dictar la descripción usando el micrófono.</span>
    </div>
</label><label>Vencimiento<input type="date" name="fecha_vencimiento"></label></div><div class="form-actions"><button class="primary-btn">Guardar tarea</button></div></form></section><?php endif;?>

<?php if(!empty($seguimientosSol)):?>
<section class="panel module-panel" style="margin-top:18px">
  <div class="reference-table-title" style="padding:0 18px"><div><h2>Notificaciones SOL asignadas <span class="reference-badge"><?php echo count($seguimientosSol);?></span></h2><p>Casos de SUNAT seguidos dentro de ASESCON. Los informes se escriben en el detalle de la notificación.</p></div></div>
  <div class="table-container"><table id="tablaSeguimientosSol"><thead><tr><th>Cliente</th><th>Notificación</th><th>Responsable</th><th>Estado del caso</th><th>Acceso</th></tr></thead><tbody>
  <?php foreach($seguimientosSol as $caso):?><tr data-id-cliente="<?php echo (int)($caso['id_cliente']??0);?>">
    <td><?php echo htmlspecialchars((string)$caso['razon_social']);?></td>
    <td><strong><?php echo htmlspecialchars((string)$caso['asunto']);?></strong></td>
    <td><?php echo htmlspecialchars((string)($caso['responsable']??'Sin asignar'));?></td>
    <td><?php echo $caso['estado_caso']==='CERRADO'?'Cerrado':'Abierto';?></td>
    <td><a class="reference-btn" href="<?php echo htmlspecialchars(asesconUrl('vistas/buzones/buzon_sol_sunat.php').'?'.http_build_query(['id_cliente'=>(int)$caso['id_cliente'],'ver_documento'=>(int)$caso['id_documento']]));?>">Ver caso y enviar reporte</a></td>
  </tr><?php endforeach;?></tbody></table></div>
</section>
<?php endif;?>
<section class="panel module-panel" style="margin-top:18px"><div class="reference-table-title" style="padding:0 18px"><div><h2>Seguimiento de tareas <span class="reference-badge"><?php echo count($tareas);?></span></h2><p>Casos visibles según tus empresas y permisos.</p></div><nav class="gestion-vistas"><a class="mini-btn <?=$vistaArchivadas?'':'active'?>" href="tareas.php">Tareas visibles</a><a class="mini-btn <?=$vistaArchivadas?'active':''?>" href="tareas.php?vista=archivadas">Archivadas / ocultas</a></nav></div><div class="table-container"><table id="tablaTareas">
<thead><tr><th>Tarea</th><th>Cliente</th><th>Responsable</th><th>Prioridad</th><th>Vence</th><th>Estado</th><th>Acción</th></tr></thead>
<tbody><?php if($tareas===[]):?><tr><td colspan="7"><div class="reference-empty">No tienes tareas para los filtros seleccionados.</div></td></tr>
<?php else:foreach($tareas as $t):?><tr data-id-cliente="<?php echo (int)($t['id_cliente']??0);?>">
<td><strong><?php echo htmlspecialchars($t['titulo']);?></strong><small data-texto-tipo="tarea" data-texto-id="<?=(int)$t['id_tarea']?>"><?php echo htmlspecialchars((string)($t['descripcion']??''));?></small></td>
<td><?php echo htmlspecialchars((string)($t['razon_social']??'—'));?></td>
<td><?php echo htmlspecialchars((string)($t['responsable']??'—'));?></td>
<td><?php echo htmlspecialchars($t['prioridad']);?></td>
<td><?php echo $t['fecha_vencimiento']?htmlspecialchars(date('d/m/Y',strtotime($t['fecha_vencimiento']))):'—';?></td>
<td><span class="status <?php echo $t['estado']==='COMPLETADA'?'done':'pending';?>"><?php echo htmlspecialchars($t['estado']);?></span></td>
<td class="task-action-cell" data-label="Acciones">
<?php if(asesconTienePermiso($conexion,'TAREAS','editar')||asesconTienePermiso($conexion,'TAREAS','eliminar')):?>
<div class="task-action-group">
<?php if(asesconTienePermiso($conexion,'TAREAS','editar')):?>
<details class="task-edit-details"><summary class="mini-btn">✎ Editar</summary>
<form method="post" action="../../controladores/tareas/ControladorTarea.php" class="task-edit-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf,ENT_QUOTES,'UTF-8');?>">
<input type="hidden" name="accion" value="editar">
<input type="hidden" name="id_tarea" value="<?php echo (int)$t['id_tarea'];?>">
<label>Título<input name="titulo" required maxlength="180" value="<?php echo htmlspecialchars((string)$t['titulo'],ENT_QUOTES,'UTF-8');?>"></label>
<label>Descripción<textarea name="descripcion" rows="3" maxlength="10000"><?php echo htmlspecialchars((string)($t['descripcion']??''),ENT_QUOTES,'UTF-8');?></textarea></label>
<label>Prioridad<select name="prioridad"><?php foreach(['BAJA','MEDIA','ALTA'] as $nivel):?><option value="<?php echo $nivel;?>" <?php echo $t['prioridad']===$nivel?'selected':'';?>><?php echo ucfirst(strtolower($nivel));?></option><?php endforeach;?></select></label>
<label>Inicio<input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars((string)($t['fecha_inicio']??''),ENT_QUOTES,'UTF-8');?>"></label>
<label>Vencimiento<input type="date" name="fecha_vencimiento" value="<?php echo htmlspecialchars((string)($t['fecha_vencimiento']??''),ENT_QUOTES,'UTF-8');?>"></label>
<button type="submit" class="mini-btn task-save-btn">Guardar cambios</button>
</form></details>
<form method="post" action="../../controladores/tareas/ControladorTarea.php" onsubmit="return confirm('¿Confirmas el cambio de estado de esta tarea?');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf,ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="accion" value="<?php echo $t['estado']==='COMPLETADA'?'reabrir':'completar';?>">
<input type="hidden" name="id_tarea" value="<?php echo (int)$t['id_tarea'];?>"><button type="submit" class="mini-btn"><?php echo $t['estado']==='COMPLETADA'?'Reabrir':'Completar';?></button>
</form>
<form method="post" action="../../controladores/tareas/ControladorTarea.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="accion" value="<?=$vistaArchivadas?'restaurar':'archivar'?>"><input type="hidden" name="id_tarea" value="<?=(int)$t['id_tarea']?>"><button type="submit" class="mini-btn"><?=$vistaArchivadas?'Desarchivar tarea':'Archivar / ocultar'?></button></form>
<?php endif;?>
<?php if(asesconTienePermiso($conexion,'TAREAS','eliminar')):?>
<form method="post" action="../../controladores/tareas/ControladorTarea.php" onsubmit="return confirm('¿Eliminar definitivamente esta tarea? No se podrá recuperar desde ASESCON.');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf,ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="accion" value="eliminar">
<input type="hidden" name="id_tarea" value="<?php echo (int)$t['id_tarea'];?>"><button type="submit" class="mini-btn task-delete-btn">🗑 Eliminar</button>
</form>
<?php endif;?>
</div>
<?php else:?><small>Sin permiso para modificar</small><?php endif;?></td>
</tr><?php endforeach;endif;?></tbody></table></div></section>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script><script src="../../publico/js/tareas/entrada_voz_tareas.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/tareas/entrada_voz_tareas.js'); ?>"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
