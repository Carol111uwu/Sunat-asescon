<?php

declare(strict_types=1);
require_once __DIR__.'/../../controladores/afp/ControladorAFP.php';
asesconExigirPermiso($conexion,'AFP','ver');
$moduloActivo='AFP';
$periodo = preg_replace('/\D+/', '', (string)($_GET['periodo'] ?? ''));
if ($periodo !== '' && !preg_match('/^20\d{2}(0[1-9]|1[0-2])$/', $periodo)) $periodo = '';
if ($periodo !== '' && !PeriodosSire::permitido($periodo,false)) $periodo = '';
if (isset($_GET['mes']) || isset($_GET['anio'])) {
    $mesFiltro=(string)($_GET['mes']??'');
    $anioFiltro=(string)($_GET['anio']??date('Y'));
    $periodo=preg_match('/^20\d{2}$/D',$anioFiltro) && preg_match('/^(0[1-9]|1[0-2])$/D',$mesFiltro)
        ? $anioFiltro.$mesFiltro : '';
}
if ($periodo !== '' && !PeriodosSire::permitido($periodo,false)) $periodo = '';
$mesUi=$periodo!==''?substr($periodo,4,2):'';
$anioUi=$periodo!==''?substr($periodo,0,4):date('Y');
$mesesUi=['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$aniosUi=range((int)(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y'),2021);
$buscar = trim((string)($_GET['buscar'] ?? ''));
$declaraciones = (new ControladorAFP($conexion))->listar($periodo, $buscar);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AFP - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php';?>
<main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content"><?php $avisoModulo = ['etiqueta'=> 'Registro AFP local', 'descripcion'=> 'Este módulo no se conecta automáticamente a AFPNet. Los registros no deben considerarse una validación oficial.']; require __DIR__ . '/../componentes/estado_integracion.php'; ?>
<div class="page-header"><div><h1>AFP</h1><p>Declaraciones AFP guardadas por cliente y período.</p></div></div>

<section class="reference-panel"><form method="get" class="reference-filter-grid three"><label class="reference-field"><span>Cliente o RUC</span><input name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Todos los clientes"></label><label class="reference-field"><span>Mes</span><select name="mes"><option value="">Todos los períodos</option><?php foreach($mesesUi as $clave=>$nombre):?><option value="<?=$clave?>" <?=$clave===$mesUi?'selected':''?>><?=htmlspecialchars($nombre)?></option><?php endforeach;?></select><small class="field-hint">Todos los períodos muestra todos los meses y años guardados.</small></label><label class="reference-field"><span>Año</span><select name="anio"><?php foreach($aniosUi as $anioOpcion):?><option value="<?=$anioOpcion?>" <?=(string)$anioOpcion===$anioUi?'selected':''?>><?=$anioOpcion?></option><?php endforeach;?></select></label><div class="reference-actions" style="grid-column:1/-1"><button class="reference-btn primary">Buscar</button><a class="reference-btn" href="https://www.afpnet.com.pe/" target="_blank" rel="noopener noreferrer">Abrir AFPnet</a><button type="button" class="reference-btn" data-export-table="#tablaAfp" data-filename="declaraciones_afp">Exportar CSV</button></div></form></section>
<section class="panel module-panel">
  <div class="reference-table-title"><div><h2>Declaraciones AFP</h2><p class="reference-subtle">Información registrada en ASESCON.</p></div><span class="reference-badge"><?php echo count(is_array($declaraciones)?$declaraciones:[]);?> registros</span></div>
  <div class="table-container"><table id="tablaAfp"><thead><tr><th>Periodo</th><th>Cliente</th><th>Ticket</th><th>AFP / monto</th><th>Total</th><th>Presentación</th><th>Pago</th><th>Vencimiento</th></tr></thead><tbody>
  <?php if(!is_array($declaraciones)||$declaraciones===[]):?><tr><td colspan="8">No hay declaraciones AFP para estos filtros.</td></tr><?php else:foreach($declaraciones as $d):
    $afps=[];
    foreach(['Habitat'=>'monto_habitat','Integra'=>'monto_integra','Prima'=>'monto_prima','Profuturo'=>'monto_profuturo'] as $nombre=>$campo){$m=(float)($d[$campo]??0);if($m!=0.0)$afps[]=$nombre.' S/ '.number_format($m,2,'.',',');}
  ?>
  <tr><td><?php echo htmlspecialchars(substr((string)$d['periodo'],4,2).'/'.substr((string)$d['periodo'],0,4));?></td><td><strong><?php echo htmlspecialchars((string)$d['razon_social']);?></strong><small><?php echo htmlspecialchars((string)$d['ruc']);?></small></td><td><?php echo htmlspecialchars((string)($d['numero_ticket']??'—'));?></td><td><?php echo htmlspecialchars($afps?implode(' · ',$afps):'—');?></td><td>S/ <?php echo number_format((float)($d['total_afp']??0),2,'.',',');?></td><td><?php echo htmlspecialchars((string)($d['estado_presentacion']??'—'));?></td><td><?php echo htmlspecialchars((string)($d['estado_pago']??'—'));?></td><td><?php echo !empty($d['fecha_vencimiento'])?htmlspecialchars(date('d/m/Y',strtotime((string)$d['fecha_vencimiento']))):'—';?></td></tr>
  <?php endforeach;endif;?></tbody></table></div>
</section>

<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
</section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
