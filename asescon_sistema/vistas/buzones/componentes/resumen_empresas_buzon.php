<?php
declare(strict_types=1);
                                                                                       
require_once __DIR__.'/../../../servicios/sunat/ServicioColaBuzonSol.php';
require_once __DIR__.'/../../../servicios/sunafil/ServicioColaBuzonSunafil.php';
$tipoResumenBuzon=($tipoResumenBuzon??'SOL')==='SUNAFIL'?'SUNAFIL':'SOL';
$colaResumen=null;
try {$colaResumen=$tipoResumenBuzon==='SOL'?new ServicioColaBuzonSol():new ServicioColaBuzonSunafil();}
catch(Throwable $e){error_log('Resumen buzón: '.get_class($e));}
$cifras=[];
if($clientes){
    $ids=array_map('intval',array_keys($clientes));
    $tabla=$tipoResumenBuzon==='SOL'?'buzon_sol_sunat':'buzon_sunafil';
    $res=$conexion->query('SELECT id_cliente,COUNT(*) cantidad FROM '.$tabla.' WHERE id_cliente IN ('.implode(',',$ids).') GROUP BY id_cliente');
    if($res)while($r=$res->fetch_assoc())$cifras[(int)$r['id_cliente']]=(int)$r['cantidad'];
}
$eResumen=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?>
<details class="buzon-estados-empresas"><summary><span class="buzon-expandir-mas" aria-hidden="true"></span> Estado por empresa (<?= count($clientes) ?>)</summary>
<div class="table-container"><table><thead><tr><th>Empresa</th><th>Notificaciones guardadas</th><th>Estado <?= $tipoResumenBuzon==='SOL'?'SUNAT SOL':'SUNAFIL' ?></th><th>Ver</th></tr></thead><tbody>
<?php foreach($clientes as $id=>$c):
 $estadoEmpresa=$colaResumen?$colaResumen->obtenerEstadoCliente((int)$id):['estado'=>'SIN PROCESADOR','mensaje'=>'Revisar configuración del servidor.'];
?>
<tr><td><strong><?= $eResumen($c['razon_social']) ?></strong><small><?= $eResumen($c['ruc']) ?></small></td>
<td><?= (int)($cifras[(int)$id]??0) ?></td><td><?= $eResumen($estadoEmpresa['estado']??'SIN CONSULTA') ?><?php if(!empty($estadoEmpresa['mensaje'])):?><small><?= $eResumen($estadoEmpresa['mensaje']) ?></small><?php endif;?></td>
<td><a class="reference-btn" href="?<?= $eResumen(http_build_query(['id_cliente'=>(int)$id])) ?>">Ver empresa</a></td></tr>
<?php endforeach;?></tbody></table></div>
<small>La tabla refleja trabajos y datos locales. Un estado completado no garantiza que existan nuevas notificaciones ni PDF descargados.</small>
</details>
