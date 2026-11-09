<?php

declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/clientes/ControladorInformes.php';
asesconExigirPermiso($conexion,'INFORMES','ver');
$moduloActivo='INFORMES';
$filtros=[
 'id_cliente'=>(int)($_GET['id_cliente']??0),
 'fecha_inicio'=>trim((string)($_GET['fecha_inicio']??'')),
 'fecha_fin'=>trim((string)($_GET['fecha_fin']??'')),
 'responsable'=>trim((string)($_GET['responsable']??'')),
 'firma'=>trim((string)($_GET['firma']??'ESTUDIO_CONTABLE')),
 'q'=>trim((string)($_GET['q']??'')),
];
$datos=(new ControladorInformes($conexion))->datos($filtros);extract($datos);
$actorNombre=(string)($actor['usuario']??'Usuario actual');foreach($responsables as $rr){if((int)$rr['id_usuario']===(int)$actor['id_usuario']){$actorNombre=(string)$rr['nombre_mostrar'];break;}}
$buscando=$filtros['id_cliente']>0||$filtros['fecha_inicio']!==''||$filtros['fecha_fin']!==''||$filtros['responsable']!==''||isset($_GET['buscar']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informe para Cliente - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css');?>"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/modulos/sunat.css');?>"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content"><?php $avisoModulo = ['etiqueta'=> 'Seguimiento de entregables', 'descripcion'=> 'El módulo conserva listado y metadatos de informes. La generación PDF integral desde tareas y comentarios todavía no está disponible.']; require __DIR__ . '/../componentes/estado_integracion.php'; ?>
<div class="page-header"><div><h1>Informe para Cliente</h1><p>Genera informes PDF con las tareas y comentarios por cliente.</p></div></div>
<section class="reference-panel report-filter-card"><h2 class="reference-section-title">▤ Filtros del Informe</h2><form method="get" class="report-filter-grid">
<label class="reference-field"><span>Cliente *</span><select name="id_cliente" required><option value="">Seleccionar cliente</option><?php foreach($clientes as $c):?><option value="<?php echo (int)$c['id_cliente'];?>" <?php echo $filtros['id_cliente']===(int)$c['id_cliente']?'selected':'';?>><?php echo htmlspecialchars($c['razon_social'].' · '.$c['ruc']);?></option><?php endforeach;?></select></label>
<label class="reference-field"><span>Fecha Inicio</span><input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($filtros['fecha_inicio']);?>"></label>
<label class="reference-field"><span>Fecha Fin</span><input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($filtros['fecha_fin']);?>"></label>
<label class="reference-field"><span>Responsable</span><select name="responsable"><option value="">Todos los responsables</option><option value="AUXILIARES" <?php echo $filtros['responsable']==='AUXILIARES'?'selected':'';?>>Auxiliares</option><option value="U:<?php echo (int)$actor['id_usuario'];?>" <?php echo $filtros['responsable']==='U:'.(int)$actor['id_usuario']?'selected':'';?>><?php echo htmlspecialchars($actorNombre);?></option><?php foreach($responsables as $r):if((int)$r['id_usuario']===(int)$actor['id_usuario'])continue;?><option value="U:<?php echo (int)$r['id_usuario'];?>" <?php echo $filtros['responsable']==='U:'.(int)$r['id_usuario']?'selected':'';?>><?php echo htmlspecialchars((string)$r['nombre_mostrar']);?></option><?php endforeach;?></select></label>
<label class="reference-field"><span>Firma del Informe</span><select name="firma"><option value="ESTUDIO_CONTABLE" <?php echo $filtros['firma']==='ESTUDIO_CONTABLE'?'selected':'';?>>Estudio Contable</option><option value="RESPONSABLE" <?php echo $filtros['firma']==='RESPONSABLE'?'selected':'';?>>Responsable</option><option value="SIN_FIRMA" <?php echo $filtros['firma']==='SIN_FIRMA'?'selected':'';?>>Sin firma</option></select></label>
<button class="reference-btn primary report-search-btn" name="buscar" value="1">⌕ Buscar</button>
</form></section>

<?php if($buscando):?><section class="panel module-panel report-results"><div class="reference-table-title"><div><h2>Informes encontrados</h2><p class="reference-subtle">Resultados según los filtros seleccionados.</p></div><span class="reference-badge"><?php echo count($filas);?> resultados</span></div><div class="table-container"><table><thead><tr><th>Cliente</th><th>Informe</th><th>Responsable</th><th>Firma</th><th>Fecha</th><th>Estado</th></tr></thead><tbody><?php if($filas===[]):?><tr><td colspan="6"><div class="reference-empty">No hay informes para los filtros seleccionados.</div></td></tr><?php else:foreach($filas as $d):?><tr><td><strong><?php echo htmlspecialchars($d['razon_social']);?></strong><small><?php echo htmlspecialchars($d['ruc']);?></small></td><td><?php echo htmlspecialchars($d['titulo']);?></td><td><?php echo htmlspecialchars((string)($d['responsable_nombre']?:'—'));?></td><td><?php echo htmlspecialchars(match((string)($d['firma_informe']??'')){'ESTUDIO_CONTABLE'=>'Estudio Contable','RESPONSABLE'=>'Responsable','SIN_FIRMA'=>'Sin firma',default=>(string)($d['firma_informe']?:'—')});?></td><td><?php echo $d['fecha_generacion']?htmlspecialchars(date('d/m/Y',strtotime($d['fecha_generacion']))):'—';?></td><td><?php echo htmlspecialchars($d['estado']);?></td></tr><?php endforeach;endif;?></tbody></table></div></section><?php endif;?>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
