<?php
                                                                                                    
declare(strict_types=1);
if (empty($esGlobal)) return;
$codigoLibro = strtoupper((string)($moduloActivo ?? 'SIRE'));
$sireAmbos = $codigoLibro === 'SIRE';
$filasResumen = [];
foreach (($clientes ?? []) as $empresa) {
    $id=(int)$empresa['id_cliente'];
    $filasResumen[$id]=['id'=>$id,'ruc'=>(string)$empresa['ruc'],'nombre'=>(string)$empresa['razon_social'],'rvie'=>null,'rce'=>null];
}
if ($sireAmbos) {
    foreach (($rvGlobal ?? []) as $fila) {
        $id=(int)$fila['id_cliente'];
        $filasResumen[$id]=['id'=>$id,'ruc'=>(string)$fila['ruc'],'nombre'=>(string)$fila['razon_social'],'rvie'=>$fila,'rce'=>null];
    }
    foreach (($rcGlobal ?? []) as $fila) {
        $id=(int)$fila['id_cliente'];
        $filasResumen[$id]??=['id'=>$id,'ruc'=>(string)$fila['ruc'],'nombre'=>(string)$fila['razon_social'],'rvie'=>null,'rce'=>null];
        $filasResumen[$id]['rce']=$fila;
    }
} else {
    foreach (($resumenClientes ?? []) as $fila) {
        $id=(int)$fila['id_cliente'];
        $filasResumen[$id]=['id'=>$id,'ruc'=>(string)$fila['ruc'],'nombre'=>(string)$fila['razon_social'],'rvie'=>$codigoLibro==='RVIE'?$fila:null,'rce'=>$codigoLibro==='RCE'?$fila:null];
    }
}
usort($filasResumen,static fn($a,$b)=>strcmp($a['nombre'],$b['nombre']));
$escSire=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
$mesGlobal=(string)($mes ?? date('m'));
$anioGlobal=(string)($anio ?? date('Y'));
?>
<section class="reference-panel compact" aria-label="Consolidado de empresas" style="margin:14px 0">
 <div class="reference-panel-head"><div><h2 class="reference-section-title">Resumen de todas las empresas</h2>
 <p>Resultados disponibles para <strong><?= $escSire($periodoEtiqueta ?? '') ?></strong>.</p></div>
 <?php if(!$sireAmbos): ?><a class="reference-btn" href="../utilitarios/sincronizacion.php">↻ Sincronización total</a><?php endif; ?></div>
 <?php if($sireAmbos): ?><details class="sire-global-detalles"><summary>Resultados por empresa (<?= count($filasResumen) ?>)</summary><?php endif; ?>
 <div class="table-container"><table class="sunat-responsive-cards"><thead><tr><th>Empresa</th>
 <?php if($sireAmbos || $codigoLibro==='RCE'):?><th>Compras RCE</th><?php endif;?>
 <?php if($sireAmbos || $codigoLibro==='RVIE'):?><th>Ventas RVIE</th><?php endif;?>
 <th>Detalle por empresa</th></tr></thead><tbody>
 <?php if(!$filasResumen):?><tr><td colspan="4">Todavía no hay resúmenes importados para el período. Los trabajos pendientes no cuentan como datos descargados.</td></tr><?php endif;?>
 <?php foreach($filasResumen as $fila): ?>
 <tr><td><strong><?= $escSire($fila['nombre']) ?></strong><small>RUC <?= $escSire($fila['ruc']) ?></small></td>
 <?php if($sireAmbos || $codigoLibro==='RCE'):?><td><?= $fila['rce']!==null?((int)$fila['rce']['cantidad_registros'].' comprobantes · S/ '.PresentacionDatosSire::formatearMonto($fila['rce']['total'])):'Sin resultado disponible' ?></td><?php endif;?>
 <?php if($sireAmbos || $codigoLibro==='RVIE'):?><td><?= $fila['rvie']!==null?((int)$fila['rvie']['cantidad_registros'].' comprobantes · S/ '.PresentacionDatosSire::formatearMonto($fila['rvie']['total'])):'Sin resultado disponible' ?></td><?php endif;?>
 <td><a class="reference-btn" href="panel_sire.php?<?= $escSire(http_build_query(['cliente'=>$fila['id'],'anio'=>$anioGlobal,'mes'=>$mesGlobal])) ?>">Ver detalle</a></td></tr>
 <?php endforeach;?></tbody></table></div>
 <?php if($sireAmbos): ?></details><?php else: ?><small>Los detalles se abren al seleccionar una empresa.</small><?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 for(const id of ['btnSyncBoth','btnValidarConexionSire','btnSyncRCE','btnSyncRVIE','btnRepairRCE','btnRepairRVIE']){
  const boton=document.getElementById(id);if(boton){boton.disabled=true;boton.title='Selecciona una empresa para esta operación, o usa sincronización general';}
 }
});
</script>
