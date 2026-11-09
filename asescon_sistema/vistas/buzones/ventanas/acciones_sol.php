<?php
if(!isset($csrf,$conexion)){http_response_code(404);exit;}
foreach(['pdf','correo','whatsapp','notas','asignacion'] as $ventanaSol)require __DIR__.'/'.$ventanaSol.'.php';
?>
<link rel="stylesheet" href="<?=htmlspecialchars(asesconUrl('publico/css/modulos/ventanas_buzon_sol.css'))?>?v=20261022_5">
<?php foreach(['base_ventana','pdf','correo','whatsapp','notas','asignacion'] as $scriptSol): ?>
<script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/ventanas/'.$scriptSol.'.js'))?>?v=20261022_5"></script>
<?php endforeach; ?>
