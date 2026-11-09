<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/sunat/ControladorVencimientoSunat.php';
require_once __DIR__.'/../../servicios/clientes/SelectorClientes.php';
asesconExigirPermiso($conexion,'VENCIMIENTOS_SUNAT','ver');
$moduloActivo='VENCIMIENTOS_SUNAT';
$estado=trim((string)($_GET['estado']??''));
$buscar=trim((string)($_GET['buscar']??''));
$hoyPeru=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
$mes=max(0,min(12,(int)($_GET['mes']??$hoyPeru->format('n'))));
$anioActual=(int)$hoyPeru->format('Y');
$anio=(int)($_GET['anio']??$anioActual);
if($anio!==0&&($anio<2021||$anio>$anioActual))$anio=0;
$clientesDisponibles=SelectorClientes::visibles($conexion);
$clienteFiltro=SelectorClientes::idAutorizado($clientesDisponibles,$_GET['cliente']??0);
$clienteInvalido=filter_var($_GET['cliente']??0,FILTER_VALIDATE_INT)>0 && $clienteFiltro===0;
$datos=(new ControladorVencimientoSunat($conexion))->datos($estado);
extract($datos);
if($clienteInvalido)$filas=[];
$cronograma = $clienteInvalido ? [] : (new VencimientoSunat($conexion))->cronograma(
    asesconFiltroClientesSql($conexion,'c'), $anio===0?$anioActual:$anio, $mes
);
$cronograma = array_values(array_filter($cronograma,static function(array $d)use($buscar,$clienteFiltro):bool{
    if($clienteFiltro>0 && (int)$d['id_cliente']!==$clienteFiltro)return false;
    if($buscar!=='' && !str_contains(mb_strtolower((string)($d['razon_social'].' '.$d['ruc'].' '.$d['formulario'])),mb_strtolower($buscar)))return false;
    return true;
}));
$csrf=asesconCsrf('csrf_vencimientos');
$filas=array_values(array_filter($filas,static function(array $d)use($buscar,$mes,$anio,$clienteFiltro,$anioActual):bool{
    $periodoRegistro=(string)($d['periodo']??'');
    if(preg_match('/^(20\d{2})/', $periodoRegistro,$coincidencia)){
        $anioRegistro=(int)$coincidencia[1];
        if($anioRegistro<2021||$anioRegistro>$anioActual)return false;
    }
    if($clienteFiltro>0&&(int)$d['id_cliente']!==$clienteFiltro)return false;
    if($buscar!==''&&!str_contains(mb_strtolower((string)($d['razon_social'].' '.$d['ruc'].' '.$d['tipo_obligacion'])),mb_strtolower($buscar)))return false;
    $ts=strtotime((string)$d['fecha_vencimiento']);
    if($mes>0&&(int)date('n',$ts)!==$mes)return false;
    if($anio>0&&(int)date('Y',$ts)!==$anio)return false;
    return true;
}));
$hoy=$hoyPeru->format('Y-m-d');
$hoyMedianoche=$hoyPeru->setTime(0,0);
foreach($filas as &$d){
    $fv=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$d['fecha_vencimiento'],new DateTimeZone('America/Lima'));
    $d['dias_restantes']=$fv?(int)$hoyMedianoche->diff($fv)->format('%r%a'):0;
}
unset($d);
$mVenc=0;$mHoy=0;$m7=0;$mDespues=0;
foreach($filas as $d){
    if(in_array((string)$d['estado'],['CUMPLIDO','COMPLETADO','ATENDIDO','PAGADO'],true))continue;
    $fv=(string)$d['fecha_vencimiento'];$dias=(int)$d['dias_restantes'];
    if($fv<$hoy)$mVenc++;elseif($fv===$hoy)$mHoy++;elseif($dias<=7)$m7++;else$mDespues++;
}
$mesesNombre=[1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$periodoTitulo=isset($mesesNombre[$mes])&&$anio>0?$mesesNombre[$mes].' '.$anio:($anio>0?'Todo el año '.$anio:($mes>0?($mesesNombre[$mes]??'Mes').' · todos los años':'Todos los períodos'));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vencimientos SUNAT - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content">
<div class="page-header"><div><h1>▣ Vencimientos SUNAT</h1><p>Obligaciones y fechas límite de los clientes autorizados.</p></div><div class="reference-head-actions"><button type="button" class="reference-btn" data-export-table="#tablaVencimientos">Exportar CSV</button></div></div>
<?php if(isset($_GET['guardado'])): ?><div class="reference-panel compact" role="status">Estado del vencimiento actualizado en el control interno.</div>
<?php elseif(isset($_GET['error'])): ?><div class="reference-panel compact" role="alert">No se pudo realizar la acción solicitada. Recarga la página y revisa tus permisos o el registro de errores del servidor.</div><?php endif; ?>
<div class="reference-metrics"><div class="reference-metric"><span>Vencidos</span><strong><?php echo $mVenc;?></strong><small>requieren atención</small></div><div class="reference-metric"><span>Vencen hoy</span><strong><?php echo $mHoy;?></strong><small>prioridad del día</small></div><div class="reference-metric"><span>Próximos 7 días</span><strong><?php echo $m7;?></strong><small>por vencer</small></div><div class="reference-metric"><span>Más adelante</span><strong><?php echo $mDespues;?></strong><small>programados</small></div></div>
<section class="reference-panel">
 <form method="get" class="reference-filter-grid">
  <label class="reference-field"><span>Cliente</span>
   <select name="cliente">
    <option value="0">Todos los clientes</option>
    <?php foreach($clientesDisponibles as $c): ?>
     <option value="<?= (int)$c['id_cliente'] ?>" <?= $clienteFiltro===(int)$c['id_cliente']?'selected':'' ?>
       data-telefono="<?= htmlspecialchars((string)($c['celular']??''),ENT_QUOTES,'UTF-8') ?>"
       data-nombre="<?= htmlspecialchars((string)($c['nombre_comercial']??''),ENT_QUOTES,'UTF-8') ?>">
      <?= htmlspecialchars($c['ruc'].' — '.$c['razon_social']) ?>
     </option>
    <?php endforeach; ?>
   </select>
  </label>
  <label class="reference-field"><span>Buscar obligación</span><input name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Nombre o tipo de obligación"></label>
  <label class="reference-field"><span>Mes</span><select name="mes"><option value="0">Todos los meses / Anual</option>
   <?php foreach($mesesNombre as $i=>$nombre): ?><option value="<?= $i ?>" <?= $mes===$i?'selected':'' ?>><?= htmlspecialchars($nombre) ?></option><?php endforeach; ?></select></label>
  <label class="reference-field"><span>Año</span><select name="anio"><option value="0" <?= $anio===0?'selected':'' ?>>Todos los años (2021–<?= $anioActual ?>)</option>
   <?php for($y=$anioActual;$y>=2021;$y--): ?><option value="<?= $y ?>" <?= $anio===$y?'selected':'' ?>><?= $y===$anioActual?'Año actual ('.$y.')':$y ?></option><?php endfor; ?></select></label>
  <label class="reference-field"><span>Estado</span><select name="estado"><option value="">Todos</option><option value="PENDIENTE" <?= $estado==='PENDIENTE'?'selected':'' ?>>Pendiente</option><option value="CUMPLIDO" <?= $estado==='CUMPLIDO'?'selected':'' ?>>Cumplido</option></select></label>
  <div class="reference-actions"><button class="reference-btn primary" type="submit">Buscar</button><a class="reference-btn" href="vencimientos_sunat.php">Limpiar búsqueda</a></div>
 </form>
</section>
<section class="reference-panel compact" id="calendarioVencimientos">
 <h2>Fechas del cronograma</h2>
 <p class="reference-subtle">Calendario configurado en ASESCON o, cuando falta, cronograma general SUNAT 2026 según el dígito del RUC. No confirma la obligación ni la presentación. Comprueba regímenes especiales y prórrogas en SUNAT.</p>
 <?php if($cronograma===[]): ?>
 <p role="status">No se encontraron fechas en el cronograma para esta búsqueda. Es posible que el calendario aún no esté cargado después de la limpieza. Consulta SUNAT antes de asignar vencimientos.</p>
 <?php else: ?>
 <div class="table-container"><table id="tablaCalendarioVencimientos"><thead><tr><th>Empresa</th><th>RUC</th><th>Período tributario</th><th>Formulario</th><th>Vence</th></tr></thead><tbody>
 <?php foreach(array_slice($cronograma,0,80) as $d): ?><tr><td><?= htmlspecialchars((string)$d['razon_social']) ?></td><td><?= htmlspecialchars((string)$d['ruc']) ?></td><td><?= htmlspecialchars((string)$d['periodo']) ?></td><td><?= htmlspecialchars((string)$d['formulario']) ?></td><td><?= htmlspecialchars(date('d/m/Y',strtotime((string)$d['fecha_vencimiento']))) ?></td></tr><?php endforeach; ?>
 </tbody></table></div>
 <?php if(count($cronograma)>80): ?><p class="reference-subtle">Se muestran las primeras 80 de <?= count($cronograma) ?> fechas. Selecciona un cliente para reducir el resultado.</p><?php endif; ?>
 <?php endif; ?>
 <?php if(($anio?:$anioActual)===2026): ?><p><a href="https://www.sunat.gob.pe/orientacion/cronogramas/2026/cObligacionMensual2026.html" target="_blank" rel="noopener noreferrer">Consultar cronograma oficial SUNAT 2026</a></p><?php endif; ?>
</section>
<section class="reference-panel"><div class="reference-table-title"><div><h2><?php echo htmlspecialchars($periodoTitulo);?> · <?php echo count($filas);?> registro(s)</h2></div></div><div class="table-container"><table id="tablaVencimientos"><thead><tr><th>Cliente</th><th>RUC</th><th>Dígito</th><th>Obligación</th><th>Periodo</th><th>Vence</th><th>Días</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php if($filas===[]):?><tr><td colspan="9"><div class="reference-empty">No hay vencimientos para estos filtros.</div></td></tr><?php else:foreach($filas as $d):$dias=(int)$d['dias_restantes'];$ruc=(string)$d['ruc'];?><tr><td><strong><?php echo htmlspecialchars((string)$d['razon_social']);?></strong></td><td><?php echo htmlspecialchars($ruc);?></td><td><?php echo htmlspecialchars($ruc!==''?substr($ruc,-1):'—');?></td><td><?php echo htmlspecialchars((string)$d['tipo_obligacion']);?></td><td><?php echo htmlspecialchars((string)$d['periodo']);?></td><td><?php echo htmlspecialchars(date('d/m/Y',strtotime((string)$d['fecha_vencimiento'])));?></td><td><span class="status <?php echo $dias<0?'expired':($dias<=7?'pending':'done');?>"><?php echo $dias<0?'Venció hace '.abs($dias).' día(s)':($dias===0?'Hoy':$dias.' día(s)');?></span></td><td><?php echo htmlspecialchars((string)$d['estado']);?></td><td><?php if($d['estado']!=='CUMPLIDO'&&asesconTienePermiso($conexion,'VENCIMIENTOS_SUNAT','editar')):?><form method="post" action="../../controladores/sunat/ControladorVencimientoSunat.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="id_vencimiento" value="<?php echo (int)$d['id_vencimiento'];?>"><input type="hidden" name="estado" value="CUMPLIDO"><button class="mini-btn">Cumplido</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
<footer>© <?= $anioActual ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
