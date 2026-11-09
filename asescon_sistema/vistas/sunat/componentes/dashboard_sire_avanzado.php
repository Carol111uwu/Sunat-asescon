<?php
                                                                            
declare(strict_types=1);
if ($esGlobal || !$clienteSel) return;
$sireH = static fn($dato): string => htmlspecialchars((string)$dato,ENT_QUOTES,'UTF-8');
$sireDinero = static fn($dato): string => 'S/ '.PresentacionDatosSire::formatearMonto($dato);
$sireHoy = new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
$sirePeriodoActual = $sireHoy->format('Ym');
$sireMeses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
               '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$sireTotalMeses = 0;
foreach ($sireMeses as $numero=>$nombre) {
    $codigo=$anio.$numero;
    if ($codigo>$sirePeriodoActual) continue;
    $fila=$comparacionMensual[$codigo]??[];
    if (isset($fila['RVIE']) || isset($fila['RCE'])) $sireTotalMeses++;
}
?>
<section class="sire-advanced sire-advanced-compact" aria-label="Comparación anual por meses de la empresa seleccionada">
 <section class="sire-analysis-card" aria-label="Comparación anual por meses">
  <div class="sire-card-title">
   <div>
    <h3>Comparación anual por meses · <?= $sireH($anio) ?></h3>
    <p><strong><?= $sireH($clienteSel['razon_social']) ?></strong> · RUC <?= $sireH($clienteSel['ruc']) ?></p>
   </div>
   <span class="sire-chip sire-chip-neutral"><?= $sireTotalMeses ?> meses con al menos un libro importado</span>
  </div>
  <p>Compara importes de <strong>esta empresa</strong>. «—» indica que no hay importación procesada; <strong>S/ 0.00</strong> es un importe neto guardado en cero (puede incluir comprobantes que se compensen). Si el conteo dice «0 comprobantes», hay una importación local procesada sin filas. Ninguno de estos estados acredita por sí solo lo que figura oficialmente en SUNAT.</p>
  <div class="table-container"><table class="sire-compare-table sire-anual-comparacion">
   <thead><tr><th>Mes</th><th>Ventas RVIE</th><th>IGV ventas</th><th>Compras RCE</th><th>IGV compras</th><th>Consulta guardada / actualización</th></tr></thead>
   <tbody>
   <?php foreach ($sireMeses as $numero=>$nombre):
       $codigo=$anio.$numero;
       if ($codigo>$sirePeriodoActual) continue;
       $fila=$comparacionMensual[$codigo]??[];
       $hayV=isset($fila['RVIE']); $hayC=isset($fila['RCE']);
       $datosV=$fila['RVIE']??null; $datosC=$fila['RCE']??null;
       $fechaV=(string)($datosV['fecha_actualizacion']??'');
       $fechaC=(string)($datosC['fecha_actualizacion']??'');
       $detalleV=$hayV?'RVIE: '.(int)$datosV['registros'].' comprobantes':'RVIE: sin importar';
       $detalleC=$hayC?'RCE: '.(int)$datosC['registros'].' comprobantes':'RCE: sin importar';
       $fechaMasReciente=max($fechaV,$fechaC);
   ?>
    <tr>
     <td><strong><?= $sireH($nombre) ?></strong></td>
     <td><?= $hayV?$sireDinero($datosV['total']):'—' ?></td>
     <td><?= $hayV?$sireDinero($datosV['igv']):'—' ?></td>
     <td><?= $hayC?$sireDinero($datosC['total']):'—' ?></td>
     <td><?= $hayC?$sireDinero($datosC['igv']):'—' ?></td>
     <td>
      <span class="sire-chip <?= $hayV&&$hayC?'sire-chip-success':'sire-chip-warning' ?>"><?= $hayV&&$hayC?'Dos libros importados':($hayV||$hayC?'Importación parcial':'Sin importación local') ?></span>
      <small class="sire-estado-detalle"><?= $sireH($detalleV) ?> · <?= $sireH($detalleC) ?></small>
      <?php if($fechaMasReciente!==''): ?><small class="sire-estado-fecha">Última actualización registrada: <?= $sireH(date('d/m/Y H:i',strtotime($fechaMasReciente))) ?></small><?php endif; ?>
     </td>
    </tr>
   <?php endforeach; ?>
   </tbody>
  </table></div>
  <p class="reference-subtle">«Importado» significa que ASESCON almacenó una propuesta procesada. No equivale a constancia de presentación, aceptación ni pago ante SUNAT. Para consultar o actualizar propuestas utiliza los módulos <a href="rvie.php?<?= $sireH(http_build_query(['cliente'=>$idCliente,'mes'=>$mes,'anio'=>$anio])) ?>">RVIE Ventas</a> y <a href="rce.php?<?= $sireH(http_build_query(['cliente'=>$idCliente,'mes'=>$mes,'anio'=>$anio])) ?>">RCE Compras</a>.</p>
 </section>
</section>
