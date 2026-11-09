<?php
                                                                                    
declare(strict_types=1);
if (!$esGlobal) return;
$globalH=static fn($v): string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$globalMontos=static fn($v): string=>'S/ '.PresentacionDatosSire::formatearMonto($v);
$rvPorCliente=[]; $rcPorCliente=[];
foreach ($rvGlobal as $dato) $rvPorCliente[(int)$dato['id_cliente']]=$dato;
foreach ($rcGlobal as $dato) $rcPorCliente[(int)$dato['id_cliente']]=$dato;
$ambasImportadas=0; $soloUna=0; $sinImportacion=0;
foreach ($clientes as $cliente) {
    $id=(int)$cliente['id_cliente'];
    $v=isset($rvPorCliente[$id]);$c=isset($rcPorCliente[$id]);
    if($v&&$c)$ambasImportadas++;
    elseif($v||$c)$soloUna++;
    else $sinImportacion++;
}
?>
<section class="reference-panel sire-global-panel" aria-label="SIRE de todas las empresas">
 <div class="reference-panel-head">
  <div>
   <h2 class="reference-section-title">RVIE y RCE de todas las empresas</h2>
   <p class="reference-subtle">Período: <strong><?= $globalH($periodoEtiqueta) ?></strong> · <?= count($clientes) ?> empresas autorizadas. Cada importe pertenece exclusivamente al RUC de su fila.</p>
  </div>
 </div>
 <div class="sire-global-resumen">
  <span class="sire-chip sire-chip-success"><?= $ambasImportadas ?> empresas con RVIE y RCE importados</span>
  <span class="sire-chip sire-chip-warning"><?= $soloUna ?> con un solo libro importado</span>
  <span class="sire-chip sire-chip-neutral"><?= $sinImportacion ?> sin importación local en la selección</span>
 </div>
 <?php if($esAnual): ?><p class="reference-subtle">En vista anual, «ambos importados» significa que hay al menos un mes de cada libro; no implica que coincidan todos los meses. Revisa las cifras y los meses importados por cada RUC.</p><?php endif; ?>
 <label class="reference-field sire-global-buscador"><span>Buscar entre todas las empresas</span>
  <input type="search" id="sireFiltroGlobal" placeholder="Escribe RUC o razón social" autocomplete="off">
 </label>
 <p id="sireGlobalConteo" class="reference-subtle" aria-live="polite">Mostrando todas las empresas.</p>
 <div class="table-container"><table class="sunat-responsive-cards sire-global-table">
  <thead><tr><th>Empresa / RUC</th><th>Ventas RVIE</th><th>Compras RCE</th><th>Estado de la selección</th><th>Consulta</th></tr></thead>
  <tbody>
   <?php foreach($clientes as $cliente):
     $id=(int)$cliente['id_cliente'];
     $rvFila=$rvPorCliente[$id]??null;$rcFila=$rcPorCliente[$id]??null;
     $buscar=$cliente['ruc'].' '.$cliente['razon_social'];
     $descripcion=$rvFila!==null&&$rcFila!==null?'Ambos importados':($rvFila!==null?'Solo RVIE importado':($rcFila!==null?'Solo RCE importado':'Sin importación local'));
   ?>
   <tr class="sire-global-fila" data-sire-global-empresa data-busqueda="<?= $globalH($buscar) ?>">
    <td data-label="Empresa / RUC"><strong><?= $globalH($cliente['razon_social']) ?></strong><small>RUC <?= $globalH($cliente['ruc']) ?></small></td>
    <?php foreach(['RVIE'=>$rvFila,'RCE'=>$rcFila] as $codigoLibro=>$libro): ?>
     <td data-label="<?= $codigoLibro==='RVIE'?'Ventas RVIE':'Compras RCE' ?>">
      <?php if($libro===null): ?><span class="sire-chip sire-chip-warning">Sin importación local</span>
      <?php else: ?>
       <strong><?= (int)$libro['cantidad_registros'] ?> comprobantes</strong><small><?= $globalMontos($libro['total']) ?></small>
       <?php if($esAnual): ?><small><?= (int)$libro['periodos_importados'] ?> meses importados de <?= $globalH($anio) ?></small><?php endif; ?>
       <?php if(!empty($libro['fecha_actualizacion'])): ?><small>Actualizado: <?= $globalH(date('d/m/Y H:i',strtotime((string)$libro['fecha_actualizacion']))) ?></small><?php endif; ?>
      <?php endif; ?>
     </td>
    <?php endforeach; ?>
    <td data-label="Estado"><span class="sire-chip <?= $rvFila!==null&&$rcFila!==null?'sire-chip-success':'sire-chip-warning' ?>"><?= $globalH($descripcion) ?></span></td>
    <td data-label="Consulta"><a class="reference-btn" href="panel_sire.php?<?= $globalH(http_build_query(['cliente'=>$id,'mes'=>$mes,'anio'=>$anio])) ?>">Ver RVIE + RCE</a></td>
   </tr>
   <?php endforeach; ?>
   <tr id="sireGlobalVacio" hidden><td colspan="5">No hay empresas que coincidan con la búsqueda.</td></tr>
  </tbody>
 </table></div>
 <div class="sire-global-paginacion">
  <button type="button" class="reference-btn" id="sireGlobalAnterior">Anterior</button>
  <span id="sireGlobalPagina" aria-live="polite">Página 1</span>
  <button type="button" class="reference-btn" id="sireGlobalSiguiente">Siguiente</button>
 </div>
 <p class="reference-subtle">Una importación procesada con <strong>0 comprobantes</strong> se presenta como cero. Sin importación local significa que no contamos con un archivo procesado, no que SUNAT confirme ausencia de operaciones. Para trabajar con un RUC, pulsa «Ver RVIE + RCE».</p>
</section>
