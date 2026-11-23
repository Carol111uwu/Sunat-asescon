<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/buzones/ControladorBuzonSunafil.php';
require_once __DIR__.'/componentes/iconos_acciones_sol.php';
asesconExigirPermiso($conexion,'BUZON_SUNAFIL','ver');
$moduloActivo='BUZON_SUNAFIL';
$estado=trim((string)($_GET['estado']??''));
$idCliente=max(0,(int)($_GET['id_cliente']??0));
$tipo=trim((string)($_GET['tipo']??''));
$desde=trim((string)($_GET['desde']??''));
$hasta=trim((string)($_GET['hasta']??''));
$pagina=max(1,(int)($_GET['pagina']??1));
$categorias=[''=>'Todos','FISCALIZACION'=>'Fiscalización','COBRANZA'=>'Cobranza','ACCIONES_PREVIAS'=>'Acciones previas','FORMALIZACION'=>'Formalización','SEGURIDAD_SALUD'=>'Seguridad y salud','ORIENTACION'=>'SUNAFIL te orienta'];
$categoria=(string)($_GET['categoria']??'');
if(!array_key_exists($categoria,$categorias))$categoria='';
if($categoria==='FISCALIZACION'){$estado=$tipo=$desde=$hasta='';}
$controladorSunafil = new ControladorBuzonSunafil($conexion);
$datos=$controladorSunafil->datos($estado,$idCliente,$tipo,$desde,$hasta,$pagina,$categoria,max(0,(int)($_GET['empresa']??0)),max(1,(int)($_GET['pagina_documentos']??1)));
$casosAsignados = $controladorSunafil->casosVisibles();
extract($datos);
$empresaGuardada = $idCliente === 0 ? (int)($cliente_pagina['id_cliente'] ?? 0) : 0;
$parametrosGuardados = ['id_cliente'=>$idCliente, 'empresa'=>$empresaGuardada, 'categoria'=>$categoria,
    'estado'=>$estado, 'desde'=>$desde, 'hasta'=>$hasta];
$parametrosGuardados['tipo'] = $tipo;
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
$columnasOficiales=[];
if($categoria==='ACCIONES_PREVIAS')$columnasOficiales=['Tipo de Requerimiento','Registro','Fecha de Notificación','Plazo','Fecha Límite de Presentación','Fecha de Depósito','Fecha Acuse de Recibo'];
if($categoria!==''&&$categoria!=='ORIENTACION'){
    foreach($filas as $fila){
        $campos=json_decode((string)($fila['campos_oficiales']??''),true);
        foreach(is_array($campos)?array_keys($campos):[] as $col){
            if(!in_array($col,['Envío Req.','Envío Req','Envio Req.','Envio Req'],true)&&!in_array($col,$columnasOficiales,true))$columnasOficiales[]=$col;
        }
    }
}
$esGeneral=$categoria===''||$categoria==='ORIENTACION';
$numColumnas=3+($categoria===''?1:0)+($esGeneral?5:count($columnasOficiales));
$bandejaVaciaConfirmada = $ultima_consulta_exitosa && ($estado==='' && $categoria==='' && $tipo==='' && $desde==='' && $hasta==='')
    && ($sincronizacion['estado'] ?? '') === 'COMPLETADO' && empty($sincronizacion['id_documento'])
    && empty($sincronizacion['consulta_parcial']) && ($sincronizacion['total'] ?? null) === 0;
$mensajeVacioBuzon = $bandejaVaciaConfirmada
    ? 'La última consulta completa confirmó que la bandeja estaba vacía.'
    : 'No hay documentos guardados para esta empresa y filtros.';
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Buzón SUNAFIL - ASESCON</title>
<link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2">
<link rel="stylesheet" href="../../publico/css/modulos/sunat.css">
<link rel="stylesheet" href="<?=htmlspecialchars(asesconRecurso('publico/css/modulos/buzones_ordenados.css'))?>">
<style>
.sunafil-pestanas{display:flex;gap:.4rem;flex-wrap:wrap;margin:0 0 1rem;padding:.75rem}
.sunafil-pestanas a{flex:1 0 auto;text-align:center;white-space:nowrap}
.sunafil-pestanas a[aria-current="page"]{background:#0c2948;color:#fff;border-color:#0c2948}
.sunafil-campo{white-space:normal;min-width:105px}
</style>
</head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?>
<main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?>
<section class="content buzon-renovado">
<div class="page-header"><div><h1>✉ Buzón SUNAFIL</h1><p>Consulta la casilla de empleadores y controla los requerimientos guardados.</p></div>
<div class="reference-head-actions"><a class="reference-btn" href="?<?=$escBuzon(http_build_query(array_replace($parametrosGuardados,['id_cliente'=>0,'empresa'=>$idCliente ?: $empresaGuardada])))?>">Ver todas</a><button type="button" class="reference-btn" data-refresh>Actualizar lista</button><button type="button" class="reference-btn" id="exportarBuzonSunafilCsv">Exportar esta página (CSV)</button><?php if ((int)($idCliente ?: ($cliente_pagina['id_cliente'] ?? 0)) > 0): ?><a class="reference-btn" href="../../api/buzones/exportar_excel.php?<?=htmlspecialchars(http_build_query(['buzon'=>'SUNAFIL','categoria'=>$categoria,'id_cliente'=>(int)($idCliente ?: ($cliente_pagina['id_cliente'] ?? 0)),'tipo'=>$tipo,'estado'=>$estado,'desde'=>$desde,'hasta'=>$hasta]), ENT_QUOTES)?>">Excel de esta empresa (hasta 100 filas)</a><?php endif; ?></div></div>

<?php $buzonCodigo='SUNAFIL'; require __DIR__.'/componentes/navegador_empresas.php'; ?>
<section class="reference-panel buzon-comando" aria-labelledby="tituloConsultarSunafil">
  <div class="buzon-comando-intro"><span class="buzon-kicker">CONSULTA OFICIAL · SUNAFIL</span><h2 id="tituloConsultarSunafil">Consultar casilla SUNAFIL</h2>
  <p>Selecciona una empresa. “Todos los clientes” programa solo SUNAFIL; no consulta Buzón SOL. Se utiliza Clave SOL registrada y el proceso se ejecuta en segundo plano.</p></div>
  <form class="buzon-comando-form" method="post" action="../../controladores/buzones/ControladorBuzonSunafil.php" data-consulta-permitida="<?=$puedeConsultar?'1':'0'?>" data-buzon-consulta="SUNAFIL" data-url-estado="<?=$escBuzon(asesconUrl('api/buzones/estado_consulta_sunafil.php'))?>" data-cliente-visible="<?=$idCliente ?: $empresaGuardada?>" data-todos-permitidos="<?=$puedeTodos?'1':'0'?>">
    <input type="hidden" name="csrf_token" value="<?=$escBuzon($csrf)?>"><input type="hidden" name="accion" value="sincronizar"><input type="hidden" name="alcance" value="todos">
    <label class="reference-field" for="buzonSunafilCliente">Empresa a consultar<select id="buzonSunafilCliente" name="id_cliente" data-buzon-cliente><option value="0">Todas las empresas<?php if(!$puedeTodos):?> (solo visualización)<?php endif;?></option><?php foreach($clientes as $id=>$c):?><option value="<?=$id?>" <?=$idCliente===$id?'selected':''?>><?=$escBuzon($c['razon_social'].' · RUC '.$c['ruc'])?></option><?php endforeach;?></select></label>
    <button type="submit" class="reference-btn primary buzon-boton-consultar" <?=!$puedeConsultar||($idCliente===0&&!$puedeTodos)||($idCliente>0&&$syncActivo)?'disabled':''?>><?=$idCliente>0&&$syncActivo?'Consultando…':($idCliente===0?'Consultar todos en SUNAFIL':'Consultar en el portal')?></button>
    <span class="buzon-consulta-aviso" role="status" aria-live="polite" hidden></span>
  </form>
  <div class="buzon-estado-servicio" data-cron-reciente="<?=!empty($estado_cron['reciente'])?'1':'0'?>"><span class="buzon-punto" aria-hidden="true"></span><span><?=$escBuzon($estado_cron['mensaje'])?></span></div>
  <?php if($idCliente===0&&$puedeTodos):?><?php $tipoResumenBuzon='SUNAFIL';require __DIR__.'/componentes/resumen_empresas_buzon.php';?><?php endif;?>
</section>

<?php if(is_array($mensajeGlobal)):?><div class="notice <?=$mensajeGlobal['tipo']==='ok'?'ok':($mensajeGlobal['tipo']==='error'?'bad':'')?>" role="status"><?=$escBuzon($mensajeGlobal['texto']??'')?></div><?php endif;?>
<?php if($idCliente>0||$empresaGuardada>0):?><?php $buzonCodigo='SUNAFIL';require __DIR__.'/componentes/estado_consulta.php';?><?php endif;?>
<?php if(isset($_GET['error'])):?><div class="notice bad" role="alert"><?=$escBuzon(['csrf'=>'La sesión venció. Recarga e inténtalo nuevamente.','cliente'=>'Selecciona una empresa activa autorizada.','documento'=>'El requerimiento ya no está disponible para revisión.','sunafil_sync'=>'No se pudo programar la consulta. Revisa el estado de Cron.'][(string)$_GET['error']]??'No se pudo completar la operación.')?></div><?php endif;?>
<?php if(($_GET['guardado']??'')==='1'):?><div class="notice ok">Revisión guardada en ASESCON. No modifica la lectura ni el acuse oficial SUNAFIL.</div><?php endif;?>

<div class="reference-metrics"><div class="reference-metric"><span>Requerimientos guardados</span><strong><?=(int)$resumen['total']?></strong><small>Historial en ASESCON</small></div><div class="reference-metric"><span>Pendientes de revisión</span><strong><?=(int)$resumen['sin_leer']?></strong><small>Seguimiento interno</small></div><div class="reference-metric"><span>Clientes registrados</span><strong><?=(int)$resumen['clientes']?></strong></div></div>
<nav class="reference-panel sunafil-pestanas" aria-label="Categorías SUNAFIL">
<?php foreach($categorias as $clave=>$nombre):?>
<a class="reference-btn" <?=$clave===$categoria?'aria-current="page"':''?> href="?<?=$escBuzon(http_build_query(array_replace($parametrosGuardados,['categoria'=>$clave])))?>"><?=$escBuzon($nombre)?></a>
<?php endforeach;?>
</nav>
<?php if($categoria!=='FISCALIZACION'):?>
<section class="reference-panel buzon-filtros"><h2 class="reference-section-title">Filtrar requerimientos guardados</h2>
<form method="get" class="reference-filter-grid"><input type="hidden" name="id_cliente" value="<?=$idCliente?>"><input type="hidden" name="empresa" value="<?=$empresaGuardada?>"><input type="hidden" name="categoria" value="<?=$escBuzon($categoria)?>">
<label class="reference-field">Tipo<select name="tipo"><option value="">Todos</option><?php foreach(($tipos_disponibles??[]) as $t):?><option value="<?=$escBuzon($t)?>" <?=$tipo===$t?'selected':''?>><?=$escBuzon($t)?></option><?php endforeach;?></select></label>
<label class="reference-field">Fecha desde<input type="date" name="desde" value="<?=$escBuzon($desde)?>"></label>
<label class="reference-field">Fecha hasta<input type="date" name="hasta" value="<?=$escBuzon($hasta)?>"></label>
<label class="reference-field">Estado<select name="estado"><option value="">Todos</option><option value="NO_REVISADO" <?=$estado==='NO_REVISADO'?'selected':''?>>Pendientes</option><option value="REVISADO" <?=$estado==='REVISADO'?'selected':''?>>Revisados</option></select></label>
<div class="reference-actions"><button class="reference-btn primary" type="submit">Aplicar filtros</button><a class="reference-btn" href="buzon_sunafil.php?<?=$escBuzon(http_build_query(['id_cliente'=>$idCliente,'empresa'=>$empresaGuardada,'categoria'=>$categoria]))?>">Limpiar filtros</a></div></form></section>
<?php endif;?>

<?php if($casosAsignados !== []):?>
<section class="reference-panel">
  <div class="reference-table-title"><h2><?=asesconEsPrincipal($conexion)?'Casos SUNAFIL registrados':'Mis casos SUNAFIL asignados'?></h2></div>
  <div class="table-container">
    <table class="buzon-tabla-simple">
      <thead><tr><th>Cliente</th><th>Asunto</th><th>Responsable</th><th>Estado</th><th>Acción</th></tr></thead>
      <tbody>
      <?php foreach($casosAsignados as $caso):?>
        <tr>
          <td><?=$escBuzon($caso['razon_social'])?></td>
          <td><?=$escBuzon($caso['asunto'])?></td>
          <td><?=$escBuzon($caso['responsable']??'Sin asignar')?></td>
          <td><?=$escBuzon($caso['estado_caso'])?></td>
          <td><a class="reference-btn" href="caso_sunafil.php?id_documento=<?=(int)$caso['id_documento']?>#seguimiento-sunafil">Abrir caso</a></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</section>
<?php endif;?>

<?php if($categoria==='FISCALIZACION'):require __DIR__.'/componentes/fiscalizacion.php';else:?>
<section class="reference-panel buzon-tabla-panel"><div class="reference-table-title"><div><h2>Requerimientos</h2><p><?=(int)$total_filtrado?> resultado(s) en total · <?php if($idCliente===0 && $cliente_pagina!==null):?>Empresa <?=(int)$pagina_actual?> de <?=(int)$total_paginas?>: <?=$escBuzon($cliente_pagina['razon_social'])?> · <?=count($filas)?> requerimientos completos de esta empresa<?php else:?>Cliente seleccionado · <?=(int)$pagina_actual?> de <?=(int)$total_paginas?><?php endif;?></p></div></div>
<h3 class="reference-section-title"><?=$escBuzon($categorias[$categoria])?></h3>
<div class="table-container"><table id="tablaSunafil" class="buzon-tabla-simple"><thead><tr><th>Estado</th><?php if($categoria===''):?><th>Origen</th><?php endif;?><th>Cliente</th><?php if($esGeneral):?><th>F. notificación</th><th>Asunto</th><th>Registro</th><th>Plazo / prioridad</th><th>F. límite</th><?php else:foreach($columnasOficiales as $col):?><th><?=$escBuzon($col)?></th><?php endforeach;endif;?><th>PDF / acción</th></tr></thead><tbody>
<?php if($filas===[]):?><tr><td colspan="<?=$numColumnas?>"><div class="reference-empty"><?=$escBuzon($mensajeVacioBuzon)?></div></td></tr>
<?php else:foreach($filas as $d):$f=$d['fecha_recepcion']?:$d['fecha_documento'];$idDocumento=(int)$d['id_documento'];?>
<tr>
  <td data-label="Estado"><span class="reference-badge <?=$d['estado']==='NO_REVISADO'?'warning':'success'?>"><?=$d['estado']==='NO_REVISADO'?'Pendiente':'Revisado'?></span></td>
  <?php if($categoria===''):?><td data-label="Origen"><?=$escBuzon($categorias[$d['categoria']]??$d['categoria']??'SUNAFIL')?></td><?php endif;?>
  <td data-label="Cliente"><strong><?=$escBuzon($d['razon_social']?:'Sin cliente')?></strong><small class="buzon-metadato">RUC <?=$escBuzon($d['ruc']?:'—')?></small></td>
  <?php if($esGeneral):?>
  <td data-label="F. notificación"><?=$f?$escBuzon(date('d/m/Y H:i',strtotime($f))):'—'?></td>
  <td data-label="Asunto"><strong class="buzon-asunto"><?=$escBuzon($d['asunto']??'')?></strong></td>
  <?php $metaGeneral=json_decode((string)($d['campos_oficiales']??''),true)?:[];?>
  <td data-label="Registro"><?=$escBuzon($metaGeneral['Registro']??$metaGeneral['Número de documento']??$d['identificador_externo']??'—')?></td>
  <td data-label="Plazo / prioridad"><?=$escBuzon($metaGeneral['Plazo']??$d['prioridad']??'NORMAL')?></td>
  <td data-label="F. límite"><?=$escBuzon($metaGeneral['Fecha Límite de Presentación']??$metaGeneral['Fecha límite de respuesta']??'—')?></td>
  <?php else:$campos=json_decode((string)($d['campos_oficiales']??''),true)?:[];foreach($columnasOficiales as $col):?>
  <td class="sunafil-campo" data-label="<?=$escBuzon($col)?>"><?=$escBuzon($campos[$col]??'—')?></td>
  <?php endforeach;endif;?>
  <td data-label="PDF / acción"><?php $metadataFila=json_decode((string)($d['campos_oficiales']??''),true)?:[];if(($d['categoria']??'')==='FISCALIZACION'&&isset($metadataFila['Orden de Inspección'])&&!isset($metadataFila['Número de documento'])):?>
  <a class="reference-btn" href="?<?=$escBuzon(http_build_query(['categoria'=>'FISCALIZACION','id_cliente'=>(int)$d['id_cliente'],'orden_detalle'=>(int)$d['id_cliente'].'|'.$metadataFila['Orden de Inspección']]))?>">Ver documentos</a>
  <?php else:?>
    <div class="buzon-barra-acciones" role="group" aria-label="Acciones del requerimiento">
      <button type="button" class="buzon-icono" data-buzon-detalle="SUNAFIL" data-id-documento="<?= $idDocumento ?>" <?= etiquetaAccionBuzon('Ver documento') ?>><?= iconoAccionBuzonSol('ver') ?></button>
      <?php if ($puedeConsultar || !empty($d['archivo'])): ?>
      <button class="buzon-icono" type="button" data-pdf-sunafil="<?= $idDocumento ?>" data-csrf="<?= $escBuzon($csrf) ?>" <?= etiquetaAccionBuzon('Descargar PDF') ?>><?= iconoAccionBuzonSol('descargar') ?></button>
      <small data-estado-pdf-sunafil="<?= $idDocumento ?>" role="status"></small>
      <?php endif; ?>

      <a class="buzon-icono" href="caso_sunafil.php?id_documento=<?= $idDocumento ?>#seguimiento-sunafil" <?= etiquetaAccionBuzon($puedeTodos ? 'Asignar y seguir' : 'Ver seguimiento') ?>><?= iconoAccionBuzonSol($puedeTodos ? 'asignar' : 'seguimiento') ?></a>
      <?php if ($d['estado'] === 'NO_REVISADO' && $puedeConsultar): ?>
        <form method="post" action="../../controladores/buzones/ControladorBuzonSunafil.php">
          <input type="hidden" name="csrf_token" value="<?= $escBuzon($csrf) ?>">
          <input type="hidden" name="accion" value="revisado">
          <input type="hidden" name="categoria" value="<?=$escBuzon($categoria)?>"><input type="hidden" name="id_cliente" value="<?= $idCliente ?>">
          <input type="hidden" name="id_documento" value="<?= $idDocumento ?>">
          <button class="buzon-icono" type="submit" <?= etiquetaAccionBuzon('Marcar revisado en ASESCON') ?>><?= iconoAccionBuzonSol('revisar') ?></button>
        </form>
      <?php endif; ?>
      <?php $buzonCodigo = 'SUNAFIL'; require __DIR__.'/componentes/enviar_notificacion.php'; ?>
    </div>
  <?php endif;?></td>
</tr>
<?php endforeach;endif;?></tbody></table></div>
<?php endif;?>
<?php require __DIR__.'/componentes/paginacion_documentos.php'; ?><?php if($categoria!=='FISCALIZACION'):?></section><?php endif;?>
<?php $buzonCodigo='SUNAFIL';require __DIR__.'/componentes/detalle_documento.php';?>
<footer>© <?= (int)(new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y') ?> ASESCON - Todos los derechos reservados.</footer></section></main></div>
<script src="../../publico/js/navegacion/menu_lateral.js"></script>
<script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script>
<script src="../../publico/js/navegacion/menu_movil.js"></script>
<script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script>
<link rel="stylesheet" href="<?=htmlspecialchars(asesconUrl('publico/css/modulos/buzon_navegador.css'))?>?v=<?=(int)@filemtime(__DIR__.'/../../publico/css/modulos/buzon_navegador.css')?>">
<script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/navegador_empresas.js'))?>?v=<?=(int)@filemtime(__DIR__.'/../../publico/js/buzones/navegador_empresas.js')?>"></script>
<script defer src="<?=htmlspecialchars(asesconRecurso('publico/js/buzones/consulta_unificada.js'))?>"></script>
<script defer src="../../publico/js/buzones/exportar_notificaciones_sunafil.js?v=20261102_1"></script>
<script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?=(int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js')?>"></script>
<script defer src="<?=htmlspecialchars(asesconRecurso('publico/js/buzones/pdf_sunafil.js'))?>"></script>
</body></html>
