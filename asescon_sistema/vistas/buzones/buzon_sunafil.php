<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/buzones/ControladorBuzonSunafil.php';
asesconExigirPermiso($conexion,'BUZON_SUNAFIL','ver');
$moduloActivo='BUZON_SUNAFIL';
$estado=trim((string)($_GET['estado']??''));
$idCliente=max(0,(int)($_GET['id_cliente']??0));
$tipo=trim((string)($_GET['tipo']??''));
$desde=trim((string)($_GET['desde']??''));
$hasta=trim((string)($_GET['hasta']??''));
$pagina=max(1,(int)($_GET['pagina']??1));
$datos=(new ControladorBuzonSunafil($conexion))->datos($estado,$idCliente,$tipo,$desde,$hasta,$pagina);
extract($datos);
$csrf=asesconCsrf('csrf_buzon_sunafil');
$clientes=[];
foreach(($clientes_disponibles??[]) as $c){
    $clientes[(int)$c['id_cliente']]=['razon_social'=>(string)$c['razon_social'],'ruc'=>(string)$c['ruc']];
}
$estadoSync=strtoupper((string)($sincronizacion['estado']??''));
$syncActivo=in_array($estadoSync,['PENDIENTE','PROCESANDO'],true);
$puedeConsultar=asesconTienePermiso($conexion,'BUZON_SUNAFIL','editar');
$puedeTodos=$puedeConsultar&&asesconEsPrincipal($conexion);
$mensajeGlobal=$_SESSION['mensaje_buzones_global']??null;
unset($_SESSION['mensaje_buzones_global']);
$escBuzon=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Buzón SUNAFIL - ASESCON</title>
<link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2">
<link rel="stylesheet" href="../../publico/css/modulos/sunat.css">
<link rel="stylesheet" href="../../publico/css/modulos/buzones_ordenados.css?v=20261102_1">
</head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?>
<main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?>
<section class="content buzon-renovado">
<div class="page-header"><div><h1>✉ Buzón SUNAFIL</h1><p>Consulta la casilla de empleadores y controla los requerimientos guardados.</p></div>
<div class="reference-head-actions"><button type="button" class="reference-btn" data-refresh>Actualizar lista</button><button type="button" class="reference-btn" id="exportarBuzonSunafilCsv">Exportar esta página (CSV)</button></div></div>

<section class="reference-panel buzon-comando" aria-labelledby="tituloConsultarSunafil">
  <div class="buzon-comando-intro"><span class="buzon-kicker">CONSULTA OFICIAL · SUNAFIL</span><h2 id="tituloConsultarSunafil">Consultar casilla SUNAFIL</h2>
  <p>Selecciona una empresa. “Todos los clientes” programa solo SUNAFIL; no consulta Buzón SOL. Se utiliza Clave SOL registrada y el proceso se ejecuta en segundo plano.</p></div>
  <form class="buzon-comando-form" method="post" action="../../controladores/buzones/ControladorBuzonSunafil.php" data-buzon-consulta="SUNAFIL" data-url-estado="<?=$escBuzon(asesconUrl('api/buzones/estado_consulta_sunafil.php'))?>" data-todos-permitidos="<?=$puedeTodos?'1':'0'?>">
    <input type="hidden" name="csrf_token" value="<?=$escBuzon($csrf)?>"><input type="hidden" name="accion" value="sincronizar"><input type="hidden" name="alcance" value="todos">
    <label class="reference-field" for="buzonSunafilCliente">Empresa a consultar<select id="buzonSunafilCliente" name="id_cliente" data-buzon-cliente><option value="0">Todos los clientes<?php if(!$puedeTodos):?> (solo visualización)<?php endif;?></option><?php foreach($clientes as $id=>$c):?><option value="<?=$id?>" <?=$idCliente===$id?'selected':''?>><?=$escBuzon($c['razon_social'].' · RUC '.$c['ruc'])?></option><?php endforeach;?></select></label>
    <button type="submit" class="reference-btn primary buzon-boton-consultar" <?=!$puedeConsultar||($idCliente===0&&!$puedeTodos)||$syncActivo?'disabled':''?>><?=$syncActivo?'Consultando…':($idCliente===0?'Consultar todos en SUNAFIL':'Consultar empresa')?></button>
    <span class="buzon-consulta-aviso" role="status" aria-live="polite" hidden></span>
  </form>
  <div class="buzon-estado-servicio" data-cron-reciente="<?=!empty($estado_cron['reciente'])?'1':'0'?>"><span class="buzon-punto" aria-hidden="true"></span><span><?=$escBuzon($estado_cron['mensaje'])?></span></div>
  <?php if($idCliente===0&&$puedeTodos):?><?php $tipoResumenBuzon='SUNAFIL';require __DIR__.'/componentes/resumen_empresas_buzon.php';?><?php endif;?>
</section>

<?php if(is_array($mensajeGlobal)):?><div class="notice <?=$mensajeGlobal['tipo']==='ok'?'ok':($mensajeGlobal['tipo']==='error'?'bad':'')?>" role="status"><?=$escBuzon($mensajeGlobal['texto']??'')?></div><?php endif;?>
<?php if($idCliente>0):?><?php $buzonCodigo='SUNAFIL';require __DIR__.'/componentes/estado_consulta.php';?><?php endif;?>
<?php if(isset($_GET['error'])):?><div class="notice bad" role="alert"><?=$escBuzon(['csrf'=>'La sesión venció. Recarga e inténtalo nuevamente.','cliente'=>'Selecciona una empresa activa autorizada.','documento'=>'El requerimiento ya no está disponible para revisión.','sunafil_sync'=>'No se pudo programar la consulta. Revisa el estado de Cron.'][(string)$_GET['error']]??'No se pudo completar la operación.')?></div><?php endif;?>
<?php if(($_GET['guardado']??'')==='1'):?><div class="notice ok">Revisión guardada en ASESCON. No modifica la lectura ni el acuse oficial SUNAFIL.</div><?php endif;?>

<div class="reference-metrics"><div class="reference-metric"><span>Requerimientos guardados</span><strong><?=(int)$resumen['total']?></strong><small>Historial en ASESCON</small></div><div class="reference-metric"><span>Pendientes de revisión</span><strong><?=(int)$resumen['sin_leer']?></strong><small>Seguimiento interno</small></div><div class="reference-metric"><span>Clientes registrados</span><strong><?=(int)$resumen['clientes']?></strong></div></div>
<section class="reference-panel buzon-filtros"><h2 class="reference-section-title">Filtrar requerimientos guardados</h2>
<form method="get" class="reference-filter-grid"><input type="hidden" name="id_cliente" value="<?=$idCliente?>">
<label class="reference-field">Tipo<select name="tipo"><option value="">Todos</option><?php foreach(($tipos_disponibles??[]) as $t):?><option value="<?=$escBuzon($t)?>" <?=$tipo===$t?'selected':''?>><?=$escBuzon($t)?></option><?php endforeach;?></select></label>
<label class="reference-field">Fecha desde<input type="date" name="desde" value="<?=$escBuzon($desde)?>"></label>
<label class="reference-field">Fecha hasta<input type="date" name="hasta" value="<?=$escBuzon($hasta)?>"></label>
<label class="reference-field">Estado<select name="estado"><option value="">Todos</option><option value="NO_REVISADO" <?=$estado==='NO_REVISADO'?'selected':''?>>Pendientes</option><option value="REVISADO" <?=$estado==='REVISADO'?'selected':''?>>Revisados</option></select></label>
<div class="reference-actions"><button class="reference-btn primary" type="submit">Aplicar filtros</button><a class="reference-btn" href="buzon_sunafil.php?<?=$escBuzon(http_build_query(['id_cliente'=>$idCliente]))?>">Limpiar filtros</a></div></form></section>

<section class="reference-panel buzon-tabla-panel"><div class="reference-table-title"><div><h2>Requerimientos</h2><p><?=(int)$total_filtrado?> resultado(s) en total · <?php if($idCliente===0 && $cliente_pagina!==null):?>Empresa <?=(int)$pagina_actual?> de <?=(int)$total_paginas?>: <?=$escBuzon($cliente_pagina['razon_social'])?> · <?=count($filas)?> requerimientos completos de esta empresa<?php else:?>Cliente seleccionado · <?=(int)$pagina_actual?> de <?=(int)$total_paginas?><?php endif;?></p></div></div>
<div class="table-container"><table id="tablaSunafil" class="buzon-tabla-simple"><thead><tr><th>Estado</th><th>Origen</th><th>F. notificación</th><th>Cliente</th><th>Asunto</th><th>Registro</th><th>Plazo / prioridad</th><th>F. límite</th><th>PDF / acción</th></tr></thead><tbody>
<?php if($filas===[]):?><tr><td colspan="9"><div class="reference-empty">No hay requerimientos guardados para estos filtros. Si es una empresa nueva, consulta su casilla desde el panel superior.</div></td></tr>
<?php else:foreach($filas as $d):$f=$d['fecha_recepcion']?:$d['fecha_documento'];$idDocumento=(int)$d['id_documento'];?>
<tr>
  <td data-label="Estado"><span class="reference-badge <?=$d['estado']==='NO_REVISADO'?'warning':'success'?>"><?=$d['estado']==='NO_REVISADO'?'Pendiente':'Revisado'?></span></td>
  <td data-label="Origen"><?=$escBuzon($d['categoria']?:'SUNAFIL')?></td>
  <td data-label="F. notificación"><?=$f?$escBuzon(date('d/m/Y H:i',strtotime($f))):'—'?></td>
  <td data-label="Cliente"><strong><?=$escBuzon($d['razon_social']?:'Sin cliente')?></strong><small class="buzon-metadato">RUC <?=$escBuzon($d['ruc']?:'—')?></small></td>
  <td data-label="Asunto"><strong class="buzon-asunto"><?=$escBuzon($d['asunto']??'')?></strong></td>
  <td data-label="Registro"><?=$escBuzon($d['identificador_externo']?:$d['tipo_documento']?:'—')?></td>
  <td data-label="Plazo / prioridad"><?=$escBuzon($d['prioridad']?:'NORMAL')?></td>
  <td data-label="F. límite">—</td>
  <td data-label="PDF / acción"><div class="buzon-acciones-sunafil"><button type="button" class="reference-btn buzon-accion-principal" data-buzon-detalle="SUNAFIL" data-id-documento="<?=$idDocumento?>">Ver documento</button>
    <?php if(!empty($d['archivo'])):?><span class="reference-badge">Archivo</span><?php endif;?>
    <?php if($d['estado']==='NO_REVISADO'&&$puedeConsultar):?><form method="post" action="../../controladores/buzones/ControladorBuzonSunafil.php"><input type="hidden" name="csrf_token" value="<?=$escBuzon($csrf)?>"><input type="hidden" name="accion" value="revisado"><input type="hidden" name="id_cliente" value="<?=$idCliente?>"><input type="hidden" name="id_documento" value="<?=$idDocumento?>"><button class="reference-btn" type="submit">Marcar revisado</button></form><?php endif;?></div></td>
</tr>
<?php endforeach;endif;?></tbody></table></div>
<?php if($total_paginas>1):?><nav class="buzon-sol-paginacion" aria-label="Páginas de requerimientos">
<?php if($pagina_actual>1):?><a class="reference-btn" href="?<?=$escBuzon(http_build_query(['id_cliente'=>$idCliente,'tipo'=>$tipo,'estado'=>$estado,'desde'=>$desde,'hasta'=>$hasta,'pagina'=>$pagina_actual-1]))?>">Anterior empresa</a><?php endif;?>
<span>Empresa <?=(int)$pagina_actual?> de <?=(int)$total_paginas?></span>
<?php if($pagina_actual<$total_paginas):?><a class="reference-btn" href="?<?=$escBuzon(http_build_query(['id_cliente'=>$idCliente,'tipo'=>$tipo,'estado'=>$estado,'desde'=>$desde,'hasta'=>$hasta,'pagina'=>$pagina_actual+1]))?>">Siguiente empresa</a><?php endif;?>
</nav><?php endif;?></section>
<?php $buzonCodigo='SUNAFIL';require __DIR__.'/componentes/detalle_documento.php';?>
<footer>© <?= (int)(new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y') ?> ASESCON - Todos los derechos reservados.</footer></section></main></div>
<script src="../../publico/js/navegacion/menu_lateral.js"></script>
<script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script>
<script src="../../publico/js/navegacion/menu_movil.js"></script>
<script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script>
<script defer src="../../publico/js/buzones/consulta_unificada.js?v=20261102_1"></script>
<script defer src="../../publico/js/buzones/exportar_notificaciones_sunafil.js?v=20261102_1"></script>
<script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?=(int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js')?>"></script>
</body></html>
