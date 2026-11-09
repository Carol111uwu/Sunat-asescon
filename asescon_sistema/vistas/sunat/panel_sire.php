<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/sunat/ControladorDashboardSire.php';
$datosVista=(new ControladorDashboardSire($conexion))->preparar($_GET);
extract($datosVista,EXTR_SKIP);
$escSire=static fn($dato): string=>htmlspecialchars((string)$dato,ENT_QUOTES,'UTF-8');
$sireImporte=static fn($dato): string=>'S/ '.PresentacionDatosSire::formatearMonto($dato);
$historialUrl=static function(array $cambios=[]) use ($idCliente,$anio,$mes,$historialSoloSeleccion,$paginaHistorial): string {
    $params=array_merge([
        'cliente'=>$idCliente,'anio'=>$anio,'mes'=>$mes,
        'historial_modo'=>$historialSoloSeleccion?'seleccion':'completo',
        'historial_pagina'=>$paginaHistorial
    ],$cambios);
    return 'panel_sire.php?'.http_build_query($params).'#historialSire';
};
?>
<!doctype html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="asescon-csrf-sire" content="<?= $escSire($csrfSire) ?>">
 <title>Dashboard SIRE - ASESCON</title>
 <link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?= (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css') ?>">
 <link rel="stylesheet" href="../../publico/css/modulos/sunat.css?v=<?= (int)@filemtime(__DIR__.'/../../publico/css/modulos/sunat.css') ?>">
</head>
<body>
<div class="app">
 <?php require __DIR__.'/../componentes/menu_lateral.php'; ?>
 <main class="main-content">
  <?php require __DIR__.'/../componentes/barra_superior.php'; ?>
  <section class="content" id="dashboardSire"
    data-periodo="<?= $escSire($periodo) ?>" data-anio="<?= $escSire($anio) ?>"
    data-anual="<?= $esAnual?'1':'0' ?>">
   <div class="page-header">
    <div><h1>Dashboard SIRE</h1>
     <p>Resumen de RVIE Ventas y RCE Compras consultadas hoy, por empresa y período. Los datos temporales se renuevan diariamente.</p>
    </div>
   </div>
   <?php if($seleccionInvalida): ?>
    <div class="notice bad" role="alert">No se pudo abrir la empresa solicitada. Solo se muestran las empresas a las que tu cuenta tiene acceso.</div>
   <?php endif; ?>
   <section class="reference-panel compact" aria-label="Seleccionar empresa y período SIRE">
    <h2 class="reference-section-title">Empresa y período</h2>
    <form method="get" class="sire-filtros">
     <label class="reference-field"><span>Seleccionar o buscar empresa</span>
      <select name="cliente" id="sireCliente">
       <option value="0">Todas las empresas (por separado)</option>
       <?php foreach($clientes as $cliente): ?>
        <option value="<?= (int)$cliente['id_cliente'] ?>" <?= $idCliente===(int)$cliente['id_cliente']?'selected':'' ?>><?= $escSire($cliente['ruc'].' — '.$cliente['razon_social']) ?></option>
       <?php endforeach; ?>
      </select>
     </label>
     <label class="reference-field"><span>Mes</span>
      <select name="mes" id="sireMes">
       <?php foreach($mesesUi as $codigoMes=>$nombreMes): $codigoMes=(string)$codigoMes; ?>
        <option value="<?= $escSire($codigoMes) ?>" <?= $codigoMes===$mes?'selected':'' ?>><?= $escSire($nombreMes) ?></option>
       <?php endforeach; ?>
      </select>
     </label>
     <label class="reference-field"><span>Año</span>
      <select name="anio" id="sireAnio">
       <?php foreach($aniosUi as $a): $a=(string)$a; ?>
        <option value="<?= $escSire($a) ?>" <?= $a===$anio?'selected':'' ?>><?= $escSire($a===(string)(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y')?'Año actual ('.$a.')':$a) ?></option>
       <?php endforeach; ?>
      </select>
     </label>
     <div class="sire-filtro-acciones"><button type="submit" class="reference-btn primary">Ver RVIE + RCE</button></div>
    </form>
   </section>

   <?php if($esGlobal): ?>
    <?php require __DIR__.'/componentes/dashboard_sire_global.php'; ?>
   <?php else: ?>
    <section class="reference-panel compact sire-empresa-resumen" aria-label="Identidad de la empresa consultada">
     <h2><?= $escSire($clienteSel['razon_social']) ?></h2>
     <p>RUC <strong><?= $escSire($clienteSel['ruc']) ?></strong> · Consulta <?= $escSire($periodoEtiqueta) ?>.
      Los importes son de este contribuyente; no indican presentación oficial ante SUNAT.</p>
    </section>
    <div class="reference-metrics sire-summary-metrics">
     <?php foreach(['RVIE'=>['Ventas',$rv],'RCE'=>['Compras',$rc]] as $libro=>$info):
       [$titulo,$datos]=$info;
       $estado=$estadosSire[$libro];
       $importado=$estado['codigo']!=='SIN_IMPORTAR';
       $enlace=($libro==='RVIE'?'rvie.php':'rce.php').'?'.http_build_query(['cliente'=>$idCliente,'mes'=>$mes,'anio'=>$anio]);
     ?>
      <section class="reference-metric sire-summary-book">
       <div class="sire-card-title">
        <h3><?= $escSire($titulo.' · '.$libro) ?></h3>
        <span class="sire-chip <?= $importado?'sire-chip-success':'sire-chip-warning' ?>"><?= $escSire($estado['etiqueta']) ?></span>
       </div>
       <strong><?= $importado?$sireImporte($datos['total']):'—' ?></strong>
       <p><?= $importado?(int)$datos['cantidad_registros'].' comprobantes'.($esAnual?' · '.(int)($datos['meses_importados']??0).' meses importados':''):'Sin consulta importada para esta selección' ?></p>
       <div class="sire-libro-igv">IGV identificado: <strong><?= $importado?$sireImporte($datos['igv']):'—' ?></strong></div>
       <small><?= $importado?(!empty($datos['fecha_actualizacion'])?'Última actualización registrada: '.$escSire(date('d/m/Y H:i',strtotime((string)$datos['fecha_actualizacion']))):'Importación sin fecha registrada.'): 'La ausencia de importación no equivale a cero operaciones.' ?></small>
       <a class="reference-btn" href="<?= $escSire($enlace) ?>">Abrir <?= $escSire($titulo) ?></a>
      </section>
     <?php endforeach; ?>
    </div>
    <?php require __DIR__.'/componentes/dashboard_sire_avanzado.php'; ?>

    <?php if(!$esAnual): ?>
     <?php foreach([['RVIE',$ultimaDescargaRvie],['RCE',$ultimaDescargaRce]] as $control):
       $descarga=$control[1];
       if(is_array($descarga)&&str_contains((string)($descarga['mensaje']??''),'Filas omitidas')): ?>
        <div class="notice bad" role="status"><strong>Revisar <?= $escSire($control[0]) ?>:</strong>
         <?= $escSire($descarga['mensaje']) ?>. Los archivos originales deben verificarse antes de usarlos como definitivos.</div>
       <?php endif; endforeach; ?>
    <?php endif; ?>

    <details class="reference-panel sire-herramientas" aria-label="Herramientas técnicas SUNAT">
     <summary>Herramientas técnicas SIRE · tickets y archivos (opcional)</summary>
     <p class="reference-subtle">Un <strong>ticket SUNAT</strong> identifica una solicitud de propuesta o descarga que SUNAT puede preparar de forma diferida. «Consultar ticket» verifica su avance y «Descargar archivo» recupera el resultado cuando esté disponible. Esta herramienta es manual y <strong>no presenta declaraciones</strong>; para operaciones habituales usa los módulos Ventas y Compras.</p>
     <div class="module-form">
      <div class="grid">
       <label>Libro<select id="sireLibro"><option value="RVIE">RVIE · Ventas</option><option value="RCE">RCE · Compras</option></select></label>
       <label>Ticket SUNAT<input id="sireTicket" placeholder="Número de ticket recibido"></label>
      </div>
      <div class="sire-actions" style="margin-top:14px">
       <button type="button" class="mini-btn" id="btnPeriodos">Consultar períodos disponibles</button>
       <button type="button" class="primary-btn" id="btnSolicitar" <?= $esAnual?'disabled title="Selecciona un mes antes de solicitar propuestas"':'' ?>>Solicitar propuesta</button>
       <button type="button" class="mini-btn" id="btnEstado">Consultar ticket</button>
       <button type="button" class="mini-btn" id="btnDescargar" <?= $esAnual?'disabled title="Selecciona un mes antes de descargar"':'' ?>>Descargar archivo</button>
      </div>
      <pre id="sireResult" class="sire-result" role="status" aria-live="polite">Selecciona una acción técnica.</pre>
     </div>
    </details>
   <?php endif; ?>

   <section class="panel module-panel sire-history-panel" id="historialSire">
    <div class="reference-table-title">
     <div>
      <h2>Consultas SIRE de hoy · <?= $esGlobal?'todas las empresas':$escSire($clienteSel['razon_social']) ?></h2>
      <p><?= $esGlobal?count($clientes).' empresas autorizadas':('RUC '.$escSire($clienteSel['ruc'])) ?>
       · <?= $historialSoloSeleccion?'Período seleccionado: '.$escSire($periodoEtiqueta):'Períodos consultados hoy' ?>
       · <?= $totalHistorial ?> registros en total.</p>
     </div>
     <div class="reference-table-actions">
      <a class="reference-btn <?= $historialSoloSeleccion?'primary':'' ?>" id="toggleHistorialPeriodo"
         href="<?= $escSire($historialUrl(['historial_modo'=>$historialSoloSeleccion?'completo':'seleccion','historial_pagina'=>1])) ?>">
       <?= $historialSoloSeleccion?'Ver otros períodos de hoy':'Ver solo período seleccionado' ?>
      </a>
     </div>
    </div>
    <div class="table-container sunat-card-table-container">
     <table class="sunat-responsive-cards sire-history-table">
      <thead><tr><th>Empresa / RUC</th><th>Libro</th><th>Período</th><th>Comprobantes</th><th>Estado local</th><th>Última actualización</th></tr></thead>
      <tbody>
       <?php if($historial===[]): ?>
        <tr><td colspan="6"><div class="reference-empty">No hay importaciones registradas hoy para este filtro. Puedes consultar SUNAT en Ventas o Compras; esto no demuestra ausencia de operaciones.</div></td></tr>
       <?php else:foreach($historial as $fila):
         $estadoReal=(string)$fila['estado'];
         $estadoTexto=$estadoReal==='PROCESADO'?'Importado en ASESCON':$estadoReal;
       ?>
        <tr class="historial-sire-row">
         <td data-label="Empresa / RUC"><strong><?= $escSire($fila['razon_social']) ?></strong><small>RUC <?= $escSire($fila['ruc']) ?></small></td>
         <td data-label="Libro"><?= $escSire($fila['tipo_libro']) ?></td>
         <td data-label="Período"><?= $escSire(PeriodosSire::etiqueta((string)$fila['periodo'])) ?> · <?= $escSire($fila['periodo']) ?></td>
         <td data-label="Comprobantes"><?= (int)$fila['cantidad_registros'] ?></td>
         <td data-label="Estado local"><?= $escSire($estadoTexto) ?><?= $estadoReal==='PROCESADO'&&(int)$fila['cantidad_registros']===0?' · 0 comprobantes':'' ?></td>
         <td data-label="Última actualización"><?= $fila['fecha_actualizacion']?$escSire(date('d/m/Y H:i',strtotime((string)$fila['fecha_actualizacion']))):'—' ?></td>
        </tr>
       <?php endforeach;endif; ?>
      </tbody>
     </table>
    </div>
    <div class="sire-historial-paginacion">
     <?php if($esGlobal): ?>
       <span><?= $clientePaginaHistorial!==null
         ? 'Empresa '.$paginaHistorial.' de '.$totalPaginasHistorial.' · '.$escSire($clientePaginaHistorial['razon_social']).' (RUC '.$escSire($clientePaginaHistorial['ruc']).') · '.count($historial).' registro(s) en esta página'
         : 'No hay empresas con historial para este filtro' ?></span>
     <?php else: ?>
       <span>Mostrando <?= $totalHistorial?($paginaHistorial-1)*$porPaginaHistorial+1:0 ?>–<?= min($totalHistorial,$paginaHistorial*$porPaginaHistorial) ?> de <?= $totalHistorial ?> · página <?= $paginaHistorial ?> de <?= $totalPaginasHistorial ?></span>
     <?php endif; ?>
     <div>
      <?php if($paginaHistorial>1): ?><a class="reference-btn" href="<?= $escSire($historialUrl(['historial_pagina'=>$paginaHistorial-1])) ?>">← <?= $esGlobal?'Empresa anterior':'Anterior' ?></a><?php endif; ?>
      <?php if($paginaHistorial<$totalPaginasHistorial): ?><a class="reference-btn" href="<?= $escSire($historialUrl(['historial_pagina'=>$paginaHistorial+1])) ?>"><?= $esGlobal?'Siguiente empresa':'Siguiente' ?> →</a><?php endif; ?>
     </div>
    </div>
    <p class="reference-subtle sire-historial-nota">El estado «Importado en ASESCON» confirma almacenamiento local del registro, no generación de constancia, presentación o pago oficial ante SUNAT.</p>
   </section>
   <footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
  </section>
 </main>
</div>
<script src="../../publico/js/navegacion/menu_lateral.js"></script>
<script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script>
<script src="../../publico/js/navegacion/menu_movil.js"></script>
<script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260916_1"></script>
<script src="../../publico/js/sunat/sincronizacion_sire.js"></script>
<script src="../../publico/js/sunat/panel_sire.js?v=<?= (int)@filemtime(__DIR__.'/../../publico/js/sunat/panel_sire.js') ?>"></script>
<script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?= (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js') ?>"></script>
</body>
</html>
