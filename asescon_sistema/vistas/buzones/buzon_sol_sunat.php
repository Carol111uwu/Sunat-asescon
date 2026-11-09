<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/buzones/ControladorBuzonSol.php';
asesconExigirPermiso($conexion,'BUZON_SOL','ver');
$moduloActivo='BUZON_SOL';
$estado=trim((string)($_GET['estado']??''));
$categoria=trim((string)($_GET['categoria']??''));
$idCliente=(int)($_GET['id_cliente']??0);
$desde=trim((string)($_GET['desde']??''));
$hasta=trim((string)($_GET['hasta']??''));
$pagina=max(1,(int)($_GET['pagina']??1));
$datos=(new ControladorBuzonSol($conexion))->datos($estado,$categoria,$idCliente,$desde,$hasta,$pagina);
extract($datos);
$csrf=asesconCsrf('csrf_buzon_sol');
$clientes=[];
foreach(($clientes_disponibles??[]) as $c){$clientes[(int)$c['id_cliente']]=['razon_social'=>(string)$c['razon_social'],'ruc'=>(string)$c['ruc']];}
foreach($filas as $f){if(!empty($f['id_cliente'])&&!isset($clientes[(int)$f['id_cliente']])){$clientes[(int)$f['id_cliente']]=['razon_social'=>(string)$f['razon_social'],'ruc'=>(string)$f['ruc']];}}
$iconosAcciones=__DIR__.'/componentes/iconos_acciones_sol.php';
require_once $iconosAcciones;
$permisoEditarBuzon=asesconTienePermiso($conexion,'BUZON_SOL','editar');
$puedeCerrarCaso=asesconEsPrincipal($conexion)&&$permisoEditarBuzon;
$puedeAsignarCaso=asesconEsPrincipal($conexion)&&$permisoEditarBuzon;
$csrfDocumento=asesconCsrf('csrf_buzon_sol');
$chips=['COACTIVA','ORDEN DE PAGO','ESQUELA','FISCALIZACION','FRACCIONAMIENTO','EMBARGO','INDUCTIVA','MULTA'];
$mensajeGlobal=$_SESSION['mensaje_buzones_global']??null;
unset($_SESSION['mensaje_buzones_global']);
$puedeConsultarBuzon=asesconTienePermiso($conexion,'BUZON_SOL','editar');
$puedeConsultarTodos=asesconEsPrincipal($conexion)&&$puedeConsultarBuzon;
$urlConsulta=asesconUrl('api/buzones/solicitar_consulta_sol.php');
$urlEstado=asesconUrl('api/buzones/estado_consulta_sol.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Buzón SOL SUNAT - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui3"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"><link rel="stylesheet" href="../../publico/css/modulos/buzon_sol_responsive.css?v=20261022_3"><link rel="stylesheet" href="../../publico/css/modulos/buzones_ordenados.css?v=20261102_1"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content buzon-renovado">
<?php $estadoSync=strtoupper((string)($sincronizacion['estado']??''));$syncActivo=in_array($estadoSync,['PENDIENTE','PROCESANDO'],true);?>
<div class="page-header buzon-sol-page-header"><div><h1>✉ Buzón SOL SUNAT</h1><p>Revisa las notificaciones oficiales recuperadas y organiza el trabajo del equipo.</p></div><div class="reference-head-actions buzon-sol-head-actions"><button type="button" class="reference-btn" data-refresh title="Actualizar las notificaciones almacenadas">Actualizar lista</button><button type="button" class="reference-btn" id="exportarBuzonSolCsv" title="Descargar las filas visibles como CSV">Exportar CSV</button></div></div>
<section class="reference-panel buzon-comando" aria-labelledby="tituloConsultarSol">
  <div class="buzon-comando-intro"><span class="buzon-kicker">CONSULTA OFICIAL · SUNAT</span><h2 id="tituloConsultarSol">Consultar Buzón SOL</h2><p>Elige una empresa y consulta su bandeja. El modo “Todos los clientes” programa únicamente SUNAT SOL; no inicia SUNAFIL.</p></div>
  <form class="buzon-comando-form buzon-sol-sync-form" method="post" action="../../controladores/buzones/ControladorBuzonSol.php" data-buzon-consulta="SOL" data-url-solicitar="<?=htmlspecialchars($urlConsulta)?>" data-url-estado="<?=htmlspecialchars($urlEstado)?>" data-todos-permitidos="<?=$puedeConsultarTodos?'1':'0'?>">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="accion" value="sincronizar"><input type="hidden" name="alcance" value="todos">
    <label class="reference-field" for="buzonSolCliente">Empresa a consultar<select id="buzonSolCliente" name="id_cliente" data-buzon-cliente><option value="0">Todos los clientes<?php if(!$puedeConsultarTodos):?> (solo visualización)<?php endif;?></option><?php foreach($clientes as $id=>$c):?><option value="<?=$id?>" <?=$idCliente===$id?'selected':''?>><?=htmlspecialchars($c['razon_social'].' · RUC '.$c['ruc'])?></option><?php endforeach;?></select></label>
    <button type="submit" class="reference-btn primary buzon-boton-consultar" <?=!$puedeConsultarBuzon||($idCliente===0&&!$puedeConsultarTodos)||$syncActivo?'disabled':''?>><?=$syncActivo?'Consultando…':($idCliente===0?'Consultar todos en SOL':'Consultar empresa')?></button>
    <span class="buzon-consulta-aviso" id="buzonSolMensajeConsulta" role="status" aria-live="polite" hidden></span>
  </form>
  <div class="buzon-estado-servicio" data-cron-reciente="<?=!empty($estado_cron['reciente'])?'1':'0'?>"><span class="buzon-punto" aria-hidden="true"></span><span><?=htmlspecialchars((string)$estado_cron['mensaje'])?></span></div>
  <?php if($idCliente===0&&$puedeConsultarTodos):?><?php $tipoResumenBuzon='SOL';require __DIR__.'/componentes/resumen_empresas_buzon.php';?><?php endif;?>
</section>
<?php if(($_GET['correo']??'')==='ok'):?><div class="notice ok">Correo enviado desde ASESCON.</div><?php elseif(($_GET['error']??'')==='correo'):?><div class="notice bad">No se pudo enviar el correo. Revisa el destinatario y la configuración SMTP del servidor.</div><?php endif;?>
<?php if(($_GET['error']??'')==='documento'):?><div class="notice bad">No se pudo actualizar la revisión de la notificación.</div><?php endif;?>
<?php if(($_GET['error']??'')==='cliente'):?><div class="notice bad">Selecciona una empresa activa que tengas autorizada para consultar.</div><?php elseif(($_GET['error']??'')==='csrf'):?><div class="notice bad">Tu sesión venció. Actualiza la página e intenta nuevamente.</div><?php endif;?>
<?php if(($_GET['guardado']??'')==='1'):?><div class="notice ok">Revisión guardada en ASESCON. No modifica el estado de lectura oficial de SUNAT.</div><?php endif;?>
<?php if(isset($_GET['solicitud'])):?><div class="notice ok">Consulta solicitada. Los resultados se actualizarán cuando termine.</div><?php elseif(($_GET['error']??'')==='sol_sync'):?><div class="notice bad">No se pudo iniciar la consulta: <?php echo htmlspecialchars((string)($_GET['detalle']??'Error de acceso.'));?></div><?php endif;?>
<?php if(is_array($mensajeGlobal)):?><div class="notice <?php echo $mensajeGlobal['tipo']==='ok'?'ok':($mensajeGlobal['tipo']==='error'?'bad':'');?>" role="status"><?php echo htmlspecialchars((string)$mensajeGlobal['texto']);?></div><?php endif;?>
<?php if($idCliente>0):?><?php $buzonCodigo='SOL';require __DIR__.'/componentes/estado_consulta.php';?><?php endif;?>
<div class="reference-metrics"><div class="reference-metric"><span>Notificaciones registradas</span><strong><?php echo (int)$resumen['total'];?></strong><small>En el sistema</small></div><div class="reference-metric"><span>Pendientes de revisión</span><strong><?php echo (int)$resumen['sin_leer'];?></strong><small>Seguimiento interno</small></div><div class="reference-metric"><span>Clientes registrados</span><strong><?php echo (int)$resumen['clientes'];?></strong></div></div>
<section class="reference-panel buzon-filtros"><h2 class="reference-section-title">Filtrar notificaciones guardadas</h2><form method="get" class="reference-filter-grid"><input type="hidden" name="id_cliente" value="<?=$idCliente?>"><label class="reference-field">Fecha desde<input type="date" name="desde" value="<?php echo htmlspecialchars($desde);?>"></label><label class="reference-field">Fecha hasta<input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta);?>"></label><label class="reference-field">Estado<select name="estado"><option value="">Todos</option><option value="NO_REVISADO" <?php echo $estado==='NO_REVISADO'?'selected':'';?>>Pendientes</option><option value="REVISADO" <?php echo $estado==='REVISADO'?'selected':'';?>>Revisadas</option></select></label><label class="reference-field">Categoría<input name="categoria" value="<?php echo htmlspecialchars($categoria);?>" placeholder="Coactiva, esquela..."></label><div class="reference-actions"><button class="reference-btn primary">Aplicar filtros</button><a class="reference-btn" href="buzon_sol_sunat.php?<?=htmlspecialchars(http_build_query(['id_cliente'=>$idCliente]))?>">Limpiar filtros</a></div></form>
<div class="reference-chip-row" style="margin-top:14px"><?php foreach($chips as $ch):?><a class="reference-chip <?php echo strtoupper($categoria)===$ch?'active':'';?>" href="?<?php echo htmlspecialchars(http_build_query(['categoria'=>$ch,'id_cliente'=>$idCliente,'desde'=>$desde,'hasta'=>$hasta,'estado'=>$estado]));?>"><?php echo htmlspecialchars(ucwords(strtolower($ch)));?></a><?php endforeach;?></div></section>
<div class="buzon-sol-avisos-globales" id="buzonSolAvisoGlobal" hidden role="status" aria-live="polite"></div>
<div class="buzon-sol-avisos-flotantes" data-avisos-operaciones-sol hidden role="status" aria-live="polite"><span class="buzon-sol-avisos-animacion" aria-hidden="true"></span><span data-avisos-texto></span><button type="button" data-avisos-cerrar aria-label="Ocultar aviso" title="Ocultar aviso">×</button></div>
<section class="reference-panel buzon-sol-notificaciones-panel">
    <div class="reference-table-title">
        <div>
            <h2>Notificaciones</h2>
            <p><?php echo (int)$total_filtrado;?> resultado(s) en total.
              <?php if($idCliente===0 && $cliente_pagina!==null): ?>Empresa <?= (int)$pagina_actual ?> de <?= (int)$total_paginas ?>: <?= htmlspecialchars((string)$cliente_pagina['razon_social'],ENT_QUOTES,'UTF-8') ?> · <?= count($filas) ?> notificaciones de esta empresa, sin dividirlas en varias páginas.<?php else: ?>Página <?= (int)$pagina_actual ?> de <?= (int)$total_paginas ?>.<?php endif; ?>
            </p>
        </div>
    </div>
    <div class="table-container buzon-sol-table-container">
        <table id="tablaBuzonSol" class="buzon-sol-responsive-table">
            <thead>
                <tr>
                    <th>F. notificación</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>F. consultado</th>
                    <th>Categoría</th>
                    <th>Asunto</th>
                    <th>Prioridad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if($filas===[]):?>
                <tr class="buzon-sol-empty-row">
                    <td colspan="8"><div class="reference-empty">No hay notificaciones almacenadas para estos filtros. Si es una empresa nueva, consulta su buzón desde el panel superior.</div></td>
                </tr>
            <?php else:foreach($filas as $d):$f=$d['fecha_recepcion']?:$d['fecha_documento'];?>
                <?php $idNota=(int)$d['id_documento'];$tienePdf=trim((string)($d['archivo']??''))!==''; ?>
                <tr data-id-notificacion="<?php echo $idNota;?>">
                    <td data-label="F. notificación"><?php echo $f?htmlspecialchars(date('d/m/Y H:i',strtotime($f))):'—';?></td>
                    <td data-label="Cliente"><strong class="buzon-sol-cliente-nombre"><?php echo htmlspecialchars((string)($d['razon_social']?:'Sin cliente'));?></strong><span class="buzon-sol-cliente-ruc">RUC <?php echo htmlspecialchars((string)($d['ruc']?:'—'));?></span></td>
                    <td data-label="Estado"><span data-estado-caso class="reference-badge <?php echo ($d['estado_caso']??'')==='CERRADO'?'success':($d['estado']==='NO_REVISADO'?'warning':'success');?>"><?php echo ($d['estado_caso']??'')==='CERRADO'?'Caso cerrado':(!empty($d['id_responsable_caso'])?'En seguimiento':($d['estado']==='NO_REVISADO'?'Por revisar':'Revisada'));?></span></td>
                    <td data-label="F. consultado"><?php echo !empty($d['fecha_sincronizacion'])?htmlspecialchars(date('d/m/Y H:i',strtotime($d['fecha_sincronizacion']))):'—';?></td>
                    <td data-label="Categoría"><?php echo htmlspecialchars((string)($d['categoria']?:'—'));?></td>
                    <td data-label="Asunto"><strong class="buzon-asunto"><?php echo htmlspecialchars((string)$d['asunto']);?></strong></td>
                    <td data-label="Prioridad"><?php echo htmlspecialchars((string)($d['prioridad']?:'NORMAL'));?></td>
                    <td data-label="Acciones"><div class="buzon-sol-row-actions" role="group" aria-label="Acciones de la notificación">
                        <button type="button" class="reference-btn buzon-accion-principal" data-buzon-detalle="SOL" data-accion-inicial="ver" data-id-documento="<?php echo $idNota;?>"><?php echo iconoAccionBuzonSol('ver');?> <span>Ver detalle</span></button>
                        <button type="button" class="reference-btn buzon-accion-pdf" <?php echo (!$tienePdf&&!$permisoEditarBuzon)?'disabled':'';?> data-buzon-pdf="<?php echo $idNota;?>" title="<?php echo $tienePdf?'Descargar PDF guardado':'Solicitar PDF oficial a SUNAT; puede requerir abrir la notificación';?>"><?php echo iconoAccionBuzonSol('descargar');?> <span><?php echo $tienePdf?'PDF':'Solicitar PDF';?></span></button>
                        <details class="buzon-mas-acciones"><summary>Más acciones</summary><div class="buzon-mas-menu">
                          <button type="button" data-buzon-detalle="SOL" data-accion-inicial="correo" data-id-documento="<?php echo $idNota;?>"><?php echo iconoAccionBuzonSol('correo');?> Enviar por correo</button>
                          <button type="button" data-buzon-detalle="SOL" data-accion-inicial="whatsapp" data-id-documento="<?php echo $idNota;?>"><?php echo iconoAccionBuzonSol('whatsapp');?> Preparar WhatsApp</button>
                          <button type="button" data-accion-buzon="notas" data-id-documento="<?php echo $idNota;?>"><?php echo iconoAccionBuzonSol('notas');?> Añadir nota</button>
                          <?php if($permisoEditarBuzon):?><form method="post" action="../../controladores/buzones/ControladorBuzonSol.php"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="id_cliente" value="<?=$idCliente?>"><input type="hidden" name="id_documento" value="<?=$idNota?>"><input type="hidden" name="accion" value="<?=$d['estado']==='NO_REVISADO'?'revisado':'reabrir'?>"><button type="submit" title="Solo cambia la revisión interna en ASESCON"><?=$d['estado']==='NO_REVISADO'?'Marcar revisada':'Marcar pendiente'?></button></form><?php endif;?>
                          <?php if($puedeAsignarCaso):?><button type="button" data-accion-buzon="asignacion" data-id-documento="<?php echo $idNota;?>"><?php echo iconoAccionBuzonSol('asignar');?> Asignar responsable</button><?php endif;?>
                          <button type="button" class="buzon-sol-cerrar-caso" data-buzon-detalle="SOL" data-accion-inicial="caso" data-id-documento="<?php echo $idNota;?>"><?php echo iconoAccionBuzonSol('cerrar');?> <span><?php echo $puedeCerrarCaso?((($d['estado_caso']??'')==='CERRADO')?'Reabrir caso':'Cerrar caso'):'Seguimiento';?></span></button>
                        </div></details>
                    </div><span class="buzon-sol-aviso-fila" data-aviso-fila hidden role="status" aria-live="polite"></span></td>
                </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
    <?php if($total_paginas>1):?>
    <nav class="buzon-sol-paginacion" aria-label="Páginas de notificaciones">
      <?php if($pagina_actual>1):?><a class="reference-btn" href="?<?php echo htmlspecialchars(http_build_query(['id_cliente'=>$idCliente,'desde'=>$desde,'hasta'=>$hasta,'estado'=>$estado,'categoria'=>$categoria,'pagina'=>$pagina_actual-1]));?>"><?= $idCliente===0?'Anterior empresa':'Anterior' ?></a><?php endif;?>
      <span><?= $idCliente===0?'Empresa':'Página' ?> <?php echo (int)$pagina_actual;?> de <?php echo (int)$total_paginas;?></span>
      <?php if($pagina_actual<$total_paginas):?><a class="reference-btn" href="?<?php echo htmlspecialchars(http_build_query(['id_cliente'=>$idCliente,'desde'=>$desde,'hasta'=>$hasta,'estado'=>$estado,'categoria'=>$categoria,'pagina'=>$pagina_actual+1]));?>"><?= $idCliente===0?'Siguiente empresa':'Siguiente' ?></a><?php endif;?>
    </nav>
    <?php endif;?>
</section>
<?php $buzonCodigo='SOL';require __DIR__.'/componentes/detalle_documento.php';?>
<?php require __DIR__.'/ventanas/notas.php'; if($puedeAsignarCaso)require __DIR__.'/ventanas/asignacion.php'; ?>
<link rel="stylesheet" href="<?=htmlspecialchars(asesconUrl('publico/css/modulos/ventanas_buzon_sol.css'))?>?v=20261023_1">
<script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/ventanas/base_ventana.js'))?>?v=20261023_1"></script>
<script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/ventanas/notas.js'))?>?v=20261023_1"></script>
<?php if($puedeAsignarCaso):?><script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/ventanas/asignacion.js'))?>?v=20261023_1"></script><?php endif;?>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script><script defer src="../../publico/js/buzones/consulta_unificada.js?v=20261102_1"></script><script defer src="../../publico/js/buzones/exportar_notificaciones_sol.js?v=20261022_3"></script><script defer src="../../publico/js/buzones/estado_operaciones_sol.js?v=20261022_4"></script><script defer src="../../publico/js/buzones/seguimiento_notificaciones_sol.js?v=20261022_4"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
