<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/sunat/ControladorDetraccion.php';
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';
asesconExigirPermiso($conexion,'DETRACCIONES','ver');
$moduloActivo='DETRACCIONES';
$h=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
$ahora=PeriodosSire::ahoraPeru();
$anio=(string)($_GET['anio']??$ahora->format('Y'));
$mes=(string)($_GET['mes']??$ahora->format('m'));
$periodo=PeriodosSire::construir($anio,$mes);
$anio=substr($periodo,0,4);$mes=substr($periodo,4,2);
$nombresMeses=PeriodosSire::meses();
$anios=range((int)$ahora->format('Y'),2021);
$seccion=(string)($_GET['seccion']??'consultas');
if (!in_array($seccion,['consultas','pagos','otras','fondos'],true))$seccion='consultas';
$ctrl=new ControladorDetraccion($conexion);
$clientes=$ctrl->clientes();
$idCliente=(int)($_GET['cliente']??0);
$permitidos=array_values(array_filter($clientes,static fn($c)=>(int)$c['id_cliente']===$idCliente));
if($idCliente!==0 && !$permitidos) $idCliente=0;
$empresas=$idCliente>0?$permitidos:$clientes;
$pagina=max(1,(int)($_GET['pagina']??1));
$totalEmpresas=count($empresas);
$pagina=$totalEmpresas?min($pagina,$totalEmpresas):1;
$empresaActual=$empresas[$pagina-1]??null;
$idActual=(int)($empresaActual['id_cliente']??0);
$resultado=$ctrl->constancias($idActual>0?[$idActual]:[],$periodo);
$filas=$resultado['filas'];$migracionLista=$resultado['habilitado'];
$desde=(string)($_GET['desde']??'');$hasta=(string)($_GET['hasta']??'');
$fechasValidas=static fn($f)=>$f===''||((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D',$f)&&checkdate((int)substr($f,5,2),(int)substr($f,8,2),(int)substr($f,0,4)));
if(!$fechasValidas($desde))$desde='';
if(!$fechasValidas($hasta))$hasta='';
$filas=array_values(array_filter($filas,static fn($f)=>($desde===''||$f['fecha_deposito']>=$desde)&&($hasta===''||$f['fecha_deposito']<=$hasta)));
$totalMonto=array_sum(array_map(static fn($f)=>(float)$f['monto'],$filas));
$csrf=asesconCsrf('csrf_detracciones');
$alerta=$_SESSION['spot_aviso']??null;unset($_SESSION['spot_aviso']);
$urlSeccion=static function(string $nombre,int $p=1)use($idCliente,$anio,$mes,$desde,$hasta):string{
    return '?'.http_build_query(['cliente'=>$idCliente,'anio'=>$anio,'mes'=>$mes,'desde'=>$desde,'hasta'=>$hasta,'pagina'=>$p,'seccion'=>$nombre]);
};
?>
<!doctype html>
<html lang="es">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Detracciones SPOT — ASESCON</title>
 <link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?= (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css') ?>">
 <link rel="stylesheet" href="../../publico/css/modulos/sunat.css?v=<?= (int)@filemtime(__DIR__.'/../../publico/css/modulos/sunat.css') ?>">
 <link rel="stylesheet" href="../../publico/css/modulos/detracciones_spot.css?v=<?= (int)@filemtime(__DIR__.'/../../publico/css/modulos/detracciones_spot.css') ?>">
</head>
<body><div class="app">
 <?php require __DIR__.'/../componentes/menu_lateral.php'; ?>
 <main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php'; ?>
 <section class="content spot">
  <div class="page-header spot-head">
   <div><h1>Detracciones <span class="spot-head-subtitle">SPOT · SUNAT</span></h1>
   <p>Revisa los registros disponibles por empresa, contrasta comprobantes RCE y prepara operaciones dentro de ASESCON.</p></div>
  </div>
  <?php if($alerta):?><div class="notice <?=($alerta['tipo']??'')==='ok'?'ok':'bad'?>" role="status"><?=$h($alerta['texto']??'')?></div><?php endif;?>
  <div class="spot-source-strip"><span class="spot-source-dot"></span><p><strong>Estado de la conexión SPOT:</strong> ASESCON todavía no tiene una consulta automática autenticada a depósitos SPOT. Los archivos que importes se mostrarán como <strong>importados, sin verificación en vivo</strong>. Los comprobantes RCE solo sirven para preparar y contrastar operaciones; no prueban el pago.</p></div>
  <nav class="spot-tabs" aria-label="Secciones de Detracciones">
   <a class="<?=$seccion==='consultas'?'active':''?>" href="<?=$h($urlSeccion('consultas'))?>">Depósitos y constancias</a>
   <a class="<?=$seccion==='pagos'?'active':''?>" href="<?=$h($urlSeccion('pagos'))?>">Preparación de pagos</a>
   <a class="<?=$seccion==='otras'?'active':''?>" href="<?=$h($urlSeccion('otras'))?>">Otras consultas</a>
   <a class="<?=$seccion==='fondos'?'active':''?>" href="<?=$h($urlSeccion('fondos'))?>">Liberación de fondos</a>
  </nav>
  <section class="reference-panel spot-filters">
   <form method="get" class="reference-filter-grid three" id="spotFiltros">
    <input type="hidden" name="seccion" value="<?=$h($seccion)?>">
    <label class="reference-field spot-client-field"><span>Cliente</span>
     <select name="cliente" aria-label="Seleccionar empresa para detracciones">
      <option value="0" <?=$idCliente===0?'selected':''?>>Todos los clientes</option>
      <?php foreach($clientes as $c):?><option value="<?=(int)$c['id_cliente']?>" <?=$idCliente===(int)$c['id_cliente']?'selected':''?>><?=$h($c['ruc'].' — '.$c['razon_social'])?></option><?php endforeach;?>
     </select>
    </label>
    <label class="reference-field"><span>Mes</span><select name="mes">
      <?php foreach($nombresMeses as $num=>$nombre):?><option value="<?=$h($num)?>" <?=$mes===$num?'selected':''?>><?=$h($nombre)?></option><?php endforeach;?>
    </select></label>
    <label class="reference-field"><span>Año</span><select name="anio">
      <?php foreach($anios as $a):?><option value="<?=$a?>" <?=$anio===(string)$a?'selected':''?>><?=$a===(int)$ahora->format('Y')?'Año actual ('.$a.')':$a?></option><?php endforeach;?>
    </select></label>
    <div class="spot-filter-actions"><button class="reference-btn primary" type="submit">Aplicar filtros</button><span>Período: <strong><?=$h(PeriodosSire::etiqueta($periodo))?></strong></span></div>
   </form>
  </section>
  <?php if($seccion==='consultas'):?>
   <section class="spot-metrics" aria-label="Resumen de constancias importadas">
    <article class="spot-metric"><small>Constancias importadas</small><strong><?=count($filas)?></strong><span>Solo del cliente y período visibles</span></article>
    <article class="spot-metric"><small>Monto importado</small><strong>S/ <?=number_format($totalMonto,2)?></strong><span>No significa saldo bancario</span></article>
    <article class="spot-metric"><small>Verificación en vivo</small><strong class="spot-metric-unverified">No disponible</strong><span>Consultar SUNAT para confirmar</span></article>
   </section>
   <section class="reference-panel">
    <div class="spot-card-header"><div><h2>Depósitos y constancias</h2><p>Elige una empresa y un período. Mostramos sus constancias ya importadas dentro del sistema. La consulta automática de depósitos SPOT aún no está conectada y no fingiremos que una operación fue confirmada.</p></div></div>
    <?php if(asesconTienePermiso($conexion,'DETRACCIONES','crear')):?>
    <details class="spot-disclosure" data-toggle-label>
     <summary><span class="spot-summary-text">Mostrar importación de archivos SUNAT</span><span class="spot-summary-indicator">+</span></summary>
     <div class="spot-disclosure-body">
      <p>Importa un <strong>CSV, TXT delimitado o XLSX</strong> obtenido de SUNAT. Debe contener constancia, fecha y monto; si no incluye período, indícalo tú revisando el archivo original. No se aceptan archivos para confirmar meses vacíos.</p>
      <?php if(!$migracionLista):?><div class="notice bad">Falta ejecutar la migración <code>base_datos/migraciones/2026_11_03_constancias_detracciones_spot.sql</code> en la base principal antes de importar.</div><?php endif;?>
      <form method="post" enctype="multipart/form-data" action="../../controladores/sunat/ControladorDetraccion.php" class="spot-import-form">
       <input type="hidden" name="accion" value="importar_sunat">
       <input type="hidden" name="csrf_token" value="<?=$h($csrf)?>">
       <label>Empresa del archivo<select name="id_cliente" data-sin-autocompletar required><option value="">Selecciona una empresa</option>
        <?php foreach($clientes as $c):?><option value="<?=(int)$c['id_cliente']?>" <?=$idCliente>0&&$idCliente===(int)$c['id_cliente']?'selected':''?>><?=$h($c['ruc'].' — '.$c['razon_social'])?></option><?php endforeach;?>
       </select></label>
       <label>Período del archivo (si el archivo no lo incluye)<select name="periodo_archivo">
         <option value="">El archivo contiene período</option>
         <?php for($a=(int)$ahora->format('Y');$a>=2021;$a--):foreach(PeriodosSire::meses(false) as $m=>$nombre):if(PeriodosSire::permitido($a.$m,false)):?>
         <option value="<?=$a.$m?>"><?=$h($nombre.' '.$a.' · '.$a.$m)?></option>
         <?php endif;endforeach;endfor;?>
       </select></label>
       <label>Archivo SUNAT<input type="file" name="archivo_sunat" accept=".csv,.txt,.xlsx" required></label>
       <button type="submit" class="reference-btn primary" <?=$migracionLista?'':'disabled'?> >Importar constancias</button>
      </form>
      <p class="spot-footnote">El archivo se analiza sin ejecutar macros, se valida por RUC titular cuando consta y se guarda con su procedencia. ASESCON no puede certificar que el archivo no fue modificado externamente.</p>
     </div>
    </details>
    <?php endif;?>
   </section>
   <section class="reference-panel spot-list-card">
    <div class="spot-card-header"><div><h2>Constancias disponibles</h2><p>Información importada de archivos, sin confirmación de SUNAT en tiempo real.</p></div>
    <button type="button" class="reference-btn" data-export-table="#tablaDetracciones" data-filename="detracciones_spot_importadas" <?=!$filas?'disabled':''?>>Exportar CSV</button></div>
    <?php if($empresaActual):?><div class="spot-company-heading"><span>Empresa <?=$pagina?> de <?=$totalEmpresas?></span><strong><?=$h($empresaActual['razon_social'])?></strong><small>RUC <?=$h($empresaActual['ruc'])?></small></div><?php endif;?>
    <form method="get" class="spot-date-filters">
     <input type="hidden" name="seccion" value="consultas"><input type="hidden" name="cliente" value="<?=$idCliente?>">
     <input type="hidden" name="anio" value="<?=$h($anio)?>"><input type="hidden" name="mes" value="<?=$h($mes)?>">
     <input type="hidden" name="pagina" value="<?=$pagina?>">
     <label>Desde depósito<input type="date" name="desde" value="<?=$h($desde)?>" max="<?=$ahora->format('Y-m-d')?>"></label>
     <label>Hasta depósito<input type="date" name="hasta" value="<?=$h($hasta)?>" max="<?=$ahora->format('Y-m-d')?>"></label>
     <button class="reference-btn" type="submit">Filtrar fechas</button>
     <?php if($desde!==''||$hasta!==''):?><a class="reference-btn" href="<?=$h('?'.http_build_query(['cliente'=>$idCliente,'anio'=>$anio,'mes'=>$mes,'pagina'=>$pagina,'seccion'=>'consultas']))?>">Limpiar fechas</a><?php endif;?>
    </form>
    <div class="table-container sunat-card-table-container"><table class="sunat-responsive-cards spot-table" id="tablaDetracciones">
     <thead><tr><th>Cliente</th><th>Período</th><th>Constancia</th><th>Fecha depósito</th><th>Monto</th><th>Documento</th><th>Estado</th></tr></thead>
     <tbody>
      <?php if(!$filas):?><tr><td colspan="7"><div class="reference-empty"><strong>No hay constancias importadas para esta selección.</strong><p>Esto no acredita que SUNAT esté vacío. Aún falta integrar la consulta SPOT en vivo; puedes incorporar archivos oficiales que ya tengas.</p></div></td></tr>
      <?php else:foreach($filas as $fila):?>
       <tr><td data-label="Cliente"><strong><?=$h($fila['razon_social'])?></strong><small><?=$h($fila['ruc'])?></small></td>
        <td data-label="Período"><?=$h(substr((string)$fila['periodo'],4,2).'/'.substr((string)$fila['periodo'],0,4))?></td>
        <td data-label="Constancia"><?=$h($fila['numero_constancia'])?></td>
        <td data-label="Fecha depósito"><?=$h(date('d/m/Y',strtotime((string)$fila['fecha_deposito'])))?></td>
        <td data-label="Monto">S/ <?=number_format((float)$fila['monto'],2)?></td>
        <td data-label="Documento"><?=$h($fila['documento']?:'—')?></td>
        <td data-label="Estado"><span class="spot-badge">Importado · Sin verificación en vivo</span></td></tr>
      <?php endforeach;endif;?>
     </tbody>
    </table></div>
    <?php if($empresaActual && $totalEmpresas>1):?>
    <nav class="spot-pages" aria-label="Paginación por clientes">
     <?php if($pagina>1):?><a class="reference-btn" href="<?=$h($urlSeccion('consultas',$pagina-1))?>">← Cliente anterior</a><?php endif;?>
     <span>Cliente <?=$pagina?> de <?=$totalEmpresas?> · Una página completa por cliente</span>
     <?php if($pagina<$totalEmpresas):?><a class="reference-btn" href="<?=$h($urlSeccion('consultas',$pagina+1))?>">Cliente siguiente →</a><?php endif;?>
    </nav><?php endif;?>
   </section>
  <?php elseif($seccion==='pagos'):?>
   <section class="reference-panel">
    <h2>Preparación de pagos de detracciones</h2>
    <p class="spot-paragraph">Los comprobantes del RCE/SIRE pueden ayudarte a preparar una lista. <strong>La existencia de una factura no determina por sí sola que esté sujeta a detracción.</strong> La tasa, el código SPOT y la obligación deben ser revisados por tu contador antes de utilizar SUNAT.</p>
    <div class="spot-warning">ASESCON prepara un <strong>borrador CSV interno</strong>, no un archivo de pago masivo homologado por SUNAT. No realiza depósitos, no solicita datos bancarios ni envía pagos automáticamente.</div>
    <?php if(!$idCliente):?><p class="notice">Selecciona una sola empresa arriba para ver sus comprobantes RCE. En esta sección no se mezclan comprobantes de clientes diferentes.</p>
    <?php else:
      $candidatos=$ctrl->candidatosRce($idCliente,$periodo);
      $limitado=count($candidatos)>500;$candidatos=array_slice($candidatos,0,500);
    ?>
      <div class="spot-actions spot-draft-toolbar"><label>Código o concepto SPOT (según SUNAT)<input type="text" id="spotConcepto" maxlength="80" placeholder="Revisar en SUNAT"></label>
       <label>Porcentaje estimado (%)<input type="number" id="spotPorcentaje" step="0.01" min="0.01" max="100" placeholder="Ej. 12.00"></label>
       <button class="reference-btn primary" type="button" id="spotExportarBorrador" disabled>Exportar borrador CSV</button></div>
      <p class="spot-footnote" id="spotSeleccionResumen" role="status">Marca los CPE para preparar un borrador. Ninguno está marcado.</p>
      <?php if($limitado):?><p class="notice">Hay más de 500 comprobantes. Para preparar el resto, selecciona otro mes y exporta sus borradores por partes.</p><?php endif;?>
      <div class="table-container"><table class="spot-table spot-candidate-table" id="spotCandidatos">
       <thead><tr><th>Elegir</th><th>Período</th><th>Emisión</th><th>Comprobante</th><th>Proveedor</th><th>Total CPE</th></tr></thead><tbody>
        <?php if(!$candidatos):?><tr><td colspan="6"><div class="reference-empty">No hay comprobantes RCE procesados para este cliente y período. No se puede inferir nada sobre detracciones pendientes.</div></td></tr><?php endif;?>
        <?php foreach($candidatos as $i=>$c):?>
        <tr><td><input type="checkbox" class="spot-pick" aria-label="Seleccionar comprobante <?=$i+1?>" data-registro="<?=$h(json_encode($c,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP))?>"></td>
        <td><?=$h($c['periodo'])?></td><td><?=$h($c['fecha_emision']?:'—')?></td>
        <td><?=$h($c['tipo_comprobante'].' '.$c['serie'].'-'.$c['numero'])?></td>
        <td><strong><?=$h($c['razon_social_proveedor']?:'—')?></strong><small><?=$h($c['ruc_proveedor']?:'—')?></small></td>
        <td>S/ <?=number_format((float)$c['importe_total'],2)?></td></tr>
        <?php endforeach;?>
       </tbody></table></div>
    <?php endif;?>
    <p class="spot-footnote">Esta sección permanece dentro de ASESCON: no inicia depósitos ni envía dinero. La presentación y autorización del pago requieren una integración específica y aprobación del responsable.</p>
   </section>
  <?php elseif($seccion==='otras'):?>
   <section class="reference-panel"><h2>Otras consultas SUNAT SPOT</h2><p class="spot-paragraph">En los videos aparecen estas opciones oficiales. Se mantienen diferenciadas de constancias y pagos para no confundir resultados.</p>
   <div class="spot-info-grid">
    <article><h3>Consulta de NPD</h3><p>Revisa los números de pago de detracciones generados. No representa un depósito hasta verificar la operación.</p></article>
    <article><h3>Ingreso como recaudación</h3><p>Comprueba, en SUNAT SOL, las situaciones en las que los fondos ingresan como recaudación.</p></article>
    <article><h3>Consulta de imputaciones</h3><p>Revisa la aplicación de montos conforme a los registros oficiales de SUNAT.</p></article>
   </div>
   <p class="spot-footnote">Estas consultas todavía no cuentan con un conector SPOT probado. No se muestran resultados supuestos ni se envía al usuario fuera de ASESCON.</p></section>
  <?php else:?>
   <section class="reference-panel"><h2>Liberación de fondos · Formulario Virtual 1697</h2>
   <p class="spot-paragraph">El formulario 1697 aparece en el portal oficial. ASESCON puede ayudarte a identificar el trámite, pero no debe solicitar la liberación automáticamente: requiere cumplir condiciones legales y autorizar la presentación en SUNAT.</p>
   <div class="spot-warning">No mostramos saldos del Banco de la Nación. ASESCON no tiene acceso a esa entidad y no puede determinar fondos disponibles ni aprobaciones.</div>
   <p class="spot-footnote">ASESCON no solicita fondos ni presenta el formulario por sí solo. La solicitud se habilitará únicamente cuando se integre un flujo oficial y una aprobación explícita.</p>
   </section>
  <?php endif;?>
  <footer>© <?=$ahora->format('Y')?> ASESCON · Información sujeta a verificación oficial.</footer>
 </section></main></div>
 <script src="../../publico/js/navegacion/menu_lateral.js"></script>
 <script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script>
 <script src="../../publico/js/navegacion/menu_movil.js"></script>
 <script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script>
 <script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?= (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js') ?>"></script>
 <script src="../../publico/js/sunat/detracciones_spot.js?v=<?= (int)@filemtime(__DIR__.'/../../publico/js/sunat/detracciones_spot.js') ?>"></script>
</body></html>
