<?php

declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/sunat/ControladorDeclaracion.php';
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';
require_once __DIR__.'/../../servicios/clientes/SelectorClientes.php';
asesconExigirPermiso($conexion,'DECLARACIONES','ver');
$moduloActivo='DECLARACIONES';
$periodo=preg_replace('/\D+/','',(string)($_GET['periodo']??date('Ym')))??date('Ym');
                                                                                    
if (isset($_GET['mes'], $_GET['anio']) && ctype_digit((string)$_GET['mes']) && ctype_digit((string)$_GET['anio'])) {
    $periodo = sprintf('%04d%02d', (int)$_GET['anio'], (int)$_GET['mes']);
}
if(!PeriodosSire::permitido($periodo))$periodo=PeriodosSire::ahoraPeru()->format('Ym');
$estado=trim((string)($_GET['estado']??''));
$buscar=trim((string)($_GET['q']??''));
$tipoFiltro=trim((string)($_GET['tipo_declaracion']??''));
$digitoFiltro=trim((string)($_GET['digito']??''));
$regimenFiltro=trim((string)($_GET['regimen']??''));
$orden=trim((string)($_GET['orden']??'nombre'));
$datos=(new ControladorDeclaracion($conexion))->datosVista($periodo,$estado,$buscar);extract($datos);
$clientesDisponibles=SelectorClientes::visibles($conexion);
$clienteFiltro=SelectorClientes::idAutorizado($clientesDisponibles,$_GET['cliente']??0);
$clienteInvalido=filter_var($_GET['cliente']??0,FILTER_VALIDATE_INT)>0 && $clienteFiltro===0;
$modeloDeclaraciones = new DeclaracionSunat($conexion);
$cronograma = (!$clienteInvalido && !str_ends_with($periodo, '00'))
    ? $modeloDeclaraciones->cronogramaPeriodo(asesconFiltroClientesSql($conexion, 'c'), $periodo, $clienteFiltro) : [];
if($clienteInvalido){
    $filas=[];
    $resumen=['esperadas'=>0,'presentadas'=>0,'pendientes'=>0,'porcentaje'=>0];
}
if($clienteFiltro>0){
                                                                             
    $todas=(new DeclaracionSunat($conexion))->listar(asesconFiltroClientesSql($conexion,'c'),$periodo);
    $todas=array_values(array_filter($todas,static fn(array $d):bool=>(int)$d['id_cliente']===$clienteFiltro));
    $esperadas=count($todas);
    $presentadas=count(array_filter($todas,static fn(array $d):bool=>(string)$d['estado_presentacion']==='PRESENTADA'));
    $resumen=['esperadas'=>$esperadas,'presentadas'=>$presentadas,'pendientes'=>$esperadas-$presentadas,
        'porcentaje'=>$esperadas?(int)round(100*$presentadas/$esperadas):0];
    $filas=array_values(array_filter($filas,static fn(array $d):bool=>(int)$d['id_cliente']===$clienteFiltro));
}
$csrf=asesconCsrf('csrf_declaraciones');
$tipos=[];$regimenes=[];foreach($filas as $f){$t=(string)$f['tipo_obligacion'];if($t!==''&&!in_array($t,$tipos,true))$tipos[]=$t;$rg=trim((string)($f['regimen_tributario']??''));if($rg!==''&&!in_array($rg,$regimenes,true))$regimenes[]=$rg;}sort($tipos);sort($regimenes);
$filas=array_values(array_filter($filas,static function(array $d)use($tipoFiltro,$digitoFiltro,$regimenFiltro):bool{
    if($tipoFiltro!==''&&(string)$d['tipo_obligacion']!==$tipoFiltro)return false;
    if($digitoFiltro!==''&&(string)$d['ultimo_digito_ruc']!==$digitoFiltro)return false;
    if($regimenFiltro!==''&&(string)($d['regimen_tributario']??'')!==$regimenFiltro)return false;
    return true;
}));
usort($filas,static function(array $a,array $b)use($orden):int{return match($orden){'ruc'=>strcmp((string)$a['ruc'],(string)$b['ruc']),'vencimiento'=>strcmp((string)($a['fecha_vencimiento']??'9999-12-31'),(string)($b['fecha_vencimiento']??'9999-12-31')),default=>strcmp((string)$a['razon_social'],(string)$b['razon_social'])};});
$anio=(int)substr($periodo,0,4);$mes=(int)substr($periodo,4,2);$esAnual=$mes===0;$meses=[1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
function montoDecl(array $d): float { $m=(float)($d['monto_declarado']??0); if($m!=0.0)return $m; return (float)($d['monto_igv']??0)+(float)($d['monto_renta']??0)+(float)($d['monto_essalud']??0)+(float)($d['monto_onp']??0)+(float)($d['monto_otro']??0); }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Control de declaraciones - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content"><?php $avisoModulo = ['etiqueta'=> 'Control interno de obligaciones', 'descripcion'=> 'Las obligaciones generadas desde el catálogo y los estados registrados manualmente no son confirmaciones de SUNAT. Los documentos oficiales requieren consulta y verificación por separado.']; require __DIR__ . '/../componentes/estado_integracion.php'; ?>
<div class="reference-head-actions"><div class="page-header"><div><h1>Control de Declaraciones</h1><p>Consulta las presentaciones con Clave SOL y revisa obligaciones y vencimientos por empresa.</p></div></div><div class="reference-actions"><a class="reference-btn" href="../vencimientos/vencimientos_sunat.php">Ver vencimientos</a><a class="reference-btn" href="declaraciones_pagos/principal.php">Consultar declaraciones y pagos</a></div></div>
<?php if (isset($_GET['consultado'])): ?>
<div class="reference-panel compact" role="status" style="border-left:4px solid #23875f;margin-bottom:12px">
 <strong>La consulta a SUNAT terminó.</strong>
 <p>Se encontraron <?= (int)($_GET['oficiales']??0) ?> formulario(s) del período; <?= (int)($_GET['coincidencias']??0) ?> obligación(es) del control se actualizaron y <?= (int)($_GET['sin_vincular']??0) ?> no tenían una obligación local equivalente.</p>
 <?php if ((int)($_GET['oficiales']??0)===0): ?><p>SUNAT no devolvió presentaciones para ese período. Esto no demuestra que el cliente esté omiso.</p><?php endif; ?>
</div>
<?php elseif (($_GET['error']??'')==='consulta_sol'): ?>
<div class="reference-panel compact" role="alert" style="border-left:4px solid #b45309;margin-bottom:12px">No se pudo completar la consulta SOL. No se ha confirmado ningún estado nuevo. Comprueba las credenciales del cliente y el acceso a SUNAT; si continúa, revisa el registro de errores PHP del servidor.</div>
<?php elseif (($_GET['error']??'')==='generar'): ?>
<div class="reference-panel compact" role="alert" style="border-left:4px solid #b45309;margin-bottom:12px">No fue posible preparar las obligaciones de este período. Revisa el catálogo de obligaciones, el cronograma y los registros de errores.</div>
<?php elseif (($_GET['error']??'')==='csrf'): ?>
<div class="reference-panel compact" role="alert">La sesión venció; vuelve a cargar la página y repite la acción.</div>
<?php elseif (isset($_GET['generado'])): ?>
<div class="reference-panel compact" role="status"><?= (int)$_GET['generado'] ?> obligación(es) creadas. <?php if((int)$_GET['generado']===0): ?>No había nuevas obligaciones definidas en el catálogo para el cliente y período elegidos, o ya estaban creadas.<?php endif; ?></div>
<?php endif; ?>
<section class="reference-panel compact">
 <form method="get" class="reference-toolbar-row">
  <input type="hidden" name="cliente" value="<?= $clienteFiltro ?>">
  <div class="reference-filter-grid two" style="flex:1;max-width:430px">
   <label class="reference-field"><span>Mes</span><select id="mesPeriodo" name="mes"><option value="00" <?= $esAnual?'selected':'' ?>>Todos los meses / Anual</option>
    <?php foreach($meses as $n=>$nombre): ?><option value="<?= str_pad((string)$n,2,'0',STR_PAD_LEFT) ?>" <?= $mes===$n?'selected':'' ?>><?= htmlspecialchars($nombre) ?></option><?php endforeach; ?></select></label>
   <label class="reference-field"><span>Año</span><select id="anioPeriodo" name="anio">
    <?php for($y=(int)PeriodosSire::ahoraPeru()->format('Y');$y>=2021;$y--): ?><option value="<?= $y ?>" <?= $anio===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></label>
  </div>
  <input type="hidden" name="periodo" id="periodoControl" value="<?= htmlspecialchars($periodo) ?>">
  <div class="reference-actions"><span class="reference-subtle">Período tributario: <strong><?= htmlspecialchars($esAnual?(string)$anio.' · Anual':$periodo) ?></strong></span><button class="reference-btn" type="submit">Buscar período</button></div>
 </form>
 <?php if(asesconTienePermiso($conexion,'DECLARACIONES','ejecutar')&&!$esAnual): ?>
  <form method="post" action="../../controladores/sunat/ControladorDeclaracion.php" style="margin-top:10px"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="accion" value="generar"><input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>"><input type="hidden" name="id_cliente" value="<?= (int)$clienteFiltro ?>"><button class="reference-btn" type="submit">Preparar control del período</button><span class="reference-subtle">Usa el catálogo de obligaciones existente; no realiza declaraciones en SUNAT.</span></form>
  <?php if ($clienteFiltro>0): ?>
  <form id="consultaControlSol" method="post" action="../../controladores/sunat/ControladorDeclaracion.php" data-api="<?= htmlspecialchars(asesconUrl('api/sunat/consultar_control_declaraciones.php'),ENT_QUOTES,'UTF-8') ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
   <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="accion" value="sincronizar"><input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>"><input type="hidden" name="id_cliente" value="<?= (int)$clienteFiltro ?>">
   <button class="reference-btn primary" type="submit">Consultar SUNAT (Clave SOL)</button>
   <span class="reference-subtle">Consulta real del cliente seleccionado, sin generar PDF ni guardar archivos.</span>
  </form>
  <div id="resultadoControlSol" role="status" aria-live="polite" style="margin-top:12px"></div>
  <?php else: ?><p class="reference-subtle">Selecciona una empresa en la búsqueda inferior y pulsa Buscar para habilitar su consulta SOL.</p><?php endif; ?>
 <?php elseif($esAnual): ?><div class="reference-subtle" style="margin-top:10px">La vista anual reúne los meses ya registrados. Para generar obligaciones nuevas, selecciona un mes.</div><?php endif; ?>
</section>
<div class="reference-metrics"><div class="reference-metric"><span class="label">Declaraciones esperadas</span><strong><?php echo $resumen['esperadas'];?></strong></div><div class="reference-metric success"><span class="label">Presentadas</span><strong><?php echo $resumen['presentadas'];?></strong></div><div class="reference-metric danger"><span class="label">Pendientes</span><strong><?php echo $resumen['pendientes'];?></strong></div><div class="reference-metric info"><span class="label">% avance</span><strong><?php echo $resumen['porcentaje'];?>%</strong></div></div>
<section class="reference-panel compact">
 <form method="get" class="reference-filter-grid five">
  <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
  <label class="reference-field"><span>Cliente</span><select name="cliente">
    <option value="0">Todos los clientes</option>
    <?php foreach($clientesDisponibles as $c): ?>
     <option value="<?= (int)$c['id_cliente'] ?>" <?= $clienteFiltro===(int)$c['id_cliente']?'selected':'' ?>
       data-telefono="<?= htmlspecialchars((string)($c['celular']??''),ENT_QUOTES,'UTF-8') ?>"
       data-nombre="<?= htmlspecialchars((string)($c['nombre_comercial']??''),ENT_QUOTES,'UTF-8') ?>">
      <?= htmlspecialchars($c['ruc'].' — '.$c['razon_social']) ?>
     </option>
    <?php endforeach; ?>
   </select></label>
  <label class="reference-field"><span>Buscar</span><input name="q" value="<?= htmlspecialchars($buscar) ?>" placeholder="RUC o nombre"></label>
  <label class="reference-field"><span>Régimen tributario</span><select name="regimen"><option value="">Todos</option>
    <?php foreach($regimenes as $rg): ?><option value="<?= htmlspecialchars($rg) ?>" <?= $regimenFiltro===$rg?'selected':'' ?>><?= htmlspecialchars($rg) ?></option><?php endforeach; ?>
   </select></label>
  <label class="reference-field"><span>Tipo de declaración</span><select name="tipo_declaracion"><option value="">Todas (<?= count($filas) ?>)</option>
    <?php foreach($tipos as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $tipoFiltro===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
   </select></label>
  <label class="reference-field"><span>Último dígito RUC</span><select name="digito"><option value="">Todos</option>
    <?php for($i=0;$i<=9;$i++): ?><option value="<?= $i ?>" <?= $digitoFiltro===(string)$i?'selected':'' ?>><?= $i ?></option><?php endfor; ?>
   </select></label>
  <label class="reference-field"><span>Ordenar por</span><select name="orden">
    <option value="nombre" <?= $orden==='nombre'?'selected':'' ?>>Nombre</option><option value="ruc" <?= $orden==='ruc'?'selected':'' ?>>RUC</option><option value="vencimiento" <?= $orden==='vencimiento'?'selected':'' ?>>Vencimiento</option>
   </select></label>
  <div class="reference-actions" style="grid-column:1/-1"><a class="reference-btn" href="?periodo=<?= urlencode($periodo) ?>">Limpiar búsqueda</a><button class="reference-btn primary" type="submit">Buscar</button></div>
 </form>
</section>
<?php if (!$esAnual): ?>
<section class="reference-panel compact" id="cronogramaDeclaraciones">
 <div class="reference-head-actions"><div><h2>Vencimientos del período <?= htmlspecialchars($periodo) ?></h2><p class="reference-subtle">Fechas del calendario configurado o, si falta, del cronograma general SUNAT 2026 según el último dígito del RUC. No prueban que exista una obligación ni que esté presentada. No incluye prórrogas especiales.</p></div>
 <a class="reference-btn" href="../vencimientos/vencimientos_sunat.php?anio=<?= (int)$anio ?>">Consultar vencimientos</a></div>
 <?php if ($cronograma===[]): ?>
 <p role="status">No hay fechas del período en el cronograma de ASESCON. No se inventarán vencimientos: revisa o incorpora el calendario oficial aplicable antes de generar obligaciones.</p>
 <?php else: ?>
 <div class="table-container"><table><thead><tr><th>Empresa</th><th>RUC</th><th>Formulario</th><th>Fecha de vencimiento</th></tr></thead><tbody>
 <?php foreach(array_slice($cronograma,0,40) as $fechaC): ?><tr><td><?= htmlspecialchars((string)$fechaC['razon_social']) ?></td><td><?= htmlspecialchars((string)$fechaC['ruc']) ?></td><td><?= htmlspecialchars((string)$fechaC['formulario']) ?></td><td><?= htmlspecialchars(date('d/m/Y',strtotime((string)$fechaC['fecha_vencimiento']))) ?></td></tr><?php endforeach; ?>
 </tbody></table></div>
 <?php if(count($cronograma)>40): ?><p class="reference-subtle">Se muestran las primeras 40 fechas. Selecciona un cliente para ver las suyas.</p><?php endif; ?>
 <?php endif; ?>
 <?php if($anio===2026): ?><p class="reference-subtle"><a href="https://www.sunat.gob.pe/orientacion/cronogramas/2026/cObligacionMensual2026.html" target="_blank" rel="noopener noreferrer">Ver el cronograma oficial SUNAT 2026</a>. Algunas empresas pueden tener prórrogas o condiciones especiales: confirma la fecha en SUNAT.</p><?php endif; ?>
</section>
<?php endif; ?>
<div class="reference-tabs">
 <?php $urlEstados='?'.http_build_query(['periodo'=>$periodo,'cliente'=>$clienteFiltro,'q'=>$buscar,'tipo_declaracion'=>$tipoFiltro,'digito'=>$digitoFiltro,'regimen'=>$regimenFiltro,'orden'=>$orden]); ?>
 <a class="reference-tab<?= $estado===''?' active':'' ?>" href="<?= htmlspecialchars($urlEstados) ?>">Todas <span class="count"><?= $resumen['esperadas'] ?></span></a>
 <a class="reference-tab<?= $estado==='PENDIENTE'?' active':'' ?>" href="<?= htmlspecialchars($urlEstados.'&estado=PENDIENTE') ?>">Pendientes <span class="count"><?= $resumen['pendientes'] ?></span></a>
 <a class="reference-tab<?= $estado==='PRESENTADA'?' active':'' ?>" href="<?= htmlspecialchars($urlEstados.'&estado=PRESENTADA') ?>">Presentadas <span class="count"><?= $resumen['presentadas'] ?></span></a>
</div>
<section class="panel module-panel"><div class="table-container"><table id="tablaDeclaraciones"><thead><tr><th>RUC (último dígito)</th><th>Cliente</th><th>Régimen</th><th>Formulario</th><th>Estado del control</th><th>N.º Orden</th><th>Vencimiento</th><th>Presentación</th><th>WhatsApp</th><th>SUNAT</th></tr></thead><tbody><?php if($filas===[]):?><tr><td colspan="10"><div class="reference-empty">No se encontraron obligaciones para esta búsqueda. Puedes preparar el control del período desde el catálogo y consultar una empresa con Clave SOL.</div></td></tr><?php else:foreach($filas as $d):$vencimiento=$d['fecha_vencimiento']??null;$presentacion=$d['fecha_presentacion']??null;$tel=preg_replace('/\D+/','',(string)($d['celular']??''))??'';if(strlen($tel)===9)$tel='51'.$tel;$msg='Hola, te escribimos de ASESCON sobre la declaración '.($d['formulario']??'').' del periodo '.($d['periodo']??'').'.';$wa=$tel!==''?'https://wa.me/'.$tel.'?text='.rawurlencode($msg):'';?><tr><td><strong><?= htmlspecialchars((string)$d['ruc']) ?></strong><small>Dígito: <?= htmlspecialchars((string)$d['ultimo_digito_ruc']) ?></small></td><td><strong><?= htmlspecialchars((string)$d['razon_social']) ?></strong></td><td><?= htmlspecialchars((string)($d['regimen_tributario']?:'Sin definir')) ?></td><td><?= htmlspecialchars((string)$d['formulario']) ?></td><td><span class="status <?= $d['estado_presentacion']==='PRESENTADA'?'done':'pending' ?>"><?= htmlspecialchars((string)$d['estado_presentacion']) ?></span></td><td><?= htmlspecialchars((string)($d['numero_orden']?:'—')) ?></td><td><?= $vencimiento?htmlspecialchars(date('d/m/Y',strtotime((string)$vencimiento))):'—' ?></td><td><?= $presentacion?htmlspecialchars(date('d/m/Y',strtotime((string)$presentacion))):'—' ?></td><td><?php if($wa!==''):?><a class="mini-btn" href="<?= htmlspecialchars($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php else:?>—<?php endif;?></td><td><?php $idClienteSol=(int)$d['id_cliente'];require __DIR__.'/componentes/acceso_directo_sol.php';?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script><script src="../../publico/js/sunat/control_declaraciones.js"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
