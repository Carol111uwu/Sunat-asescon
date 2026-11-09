<?php

declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/clientes/ControladorCliente.php';
asesconExigirPermiso($conexion,'CLIENTES','ver');
$moduloActivo='CLIENTES';
$buscar=trim((string)($_GET['q']??''));
$cc=new ControladorCliente($conexion);
$clientes=$cc->listar($buscar);
$clientesResumen=$buscar===''?$clientes:$cc->listar('');
$csrf=asesconCsrf('csrf_clientes');
$totalClientes=count($clientesResumen);
$totalActivos=0;
$totalConSol=0;
foreach($clientesResumen as $fila){
    if(strtoupper((string)($fila['estado']??''))==='ACTIVO') $totalActivos++;
    if((int)($fila['tiene_sol_configurado']??0)===1) $totalConSol++;
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clientes - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css'); ?>"><link rel="stylesheet" href="../../publico/css/modulos/clientes.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/modulos/clientes.css'); ?>"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php'; ?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php'; ?><section class="content">
<div class="page-header"><div><h1>Gestión de clientes</h1><p>Empresas, contacto, estado y consulta SIRE.</p></div><?php if(asesconTienePermiso($conexion,'CLIENTES','crear')): ?><a class="primary-btn" href="ficha_cliente.php">+ Crear cliente</a><?php endif; ?></div>
<?php if(isset($_GET['guardado'])):?><div class="notice ok">Cliente guardado correctamente.</div><?php endif; ?>
<?php if(isset($_GET['eliminado'])):?><div class="notice ok">Cliente y sus datos relacionados fueron eliminados definitivamente.</div><?php endif; ?>
<?php if(($_GET['error']??'')==='confirmacion'):?><div class="notice bad">La confirmación de eliminación no coincidió.</div><?php endif; ?>

<div class="client-summary-grid">
  <article class="client-summary-card"><span>Total de clientes</span><strong><?php echo $totalClientes;?></strong><small>en tu cartera visible</small></article>
  <article class="client-summary-card active"><span>Activos</span><strong><?php echo $totalActivos;?></strong><small>gestión al día</small></article>
  <article class="client-summary-card sol"><span>Con SOL</span><strong><?php echo $totalConSol;?></strong><small>credenciales cargadas</small></article>
</div>

<form class="search-row" method="get"><input data-autocompletar-cliente autocomplete="off" name="q" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar por RUC, cliente, contacto, correo o teléfono"><button class="mini-btn">Buscar</button><?php if($buscar!==''):?><a class="mini-btn" href="gestion_clientes.php">Limpiar</a><?php endif;?></form>
<section class="panel module-panel"><div class="table-container"><table class="clientes-responsive-table"><thead><tr><th>Cliente</th><th>RUC</th><th>Contacto</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if($clientes===[]):?><tr><td colspan="5">No hay clientes disponibles para esta cuenta.</td></tr><?php else: foreach($clientes as $c):$id=(int)$c['id_cliente'];$gest=asesconPuedeGestionarCliente($conexion,$id)&&asesconTienePermiso($conexion,'CLIENTES','editar');$nombreContacto=trim((string)($c['nombre_contacto']??''));$telefono=trim((string)($c['celular']??''));$correo=trim((string)($c['correo']??''));?>
<tr>
<td data-label="Cliente"><strong><?php echo htmlspecialchars((string)$c['razon_social']);?></strong><?php if((int)($c['tiene_sol_configurado']??0)===1):?><small class="sol-note"> SOL configurado<?php echo ($c['estado_sire']??'')==='VALIDA'?' · SIRE validado':'';?></small><?php endif;?></td>
<td data-label="RUC"><strong><?php echo htmlspecialchars((string)$c['ruc']);?></strong></td>
<td data-label="Contacto"><?php if($nombreContacto!==''):?><strong><?php echo htmlspecialchars($nombreContacto);?></strong><?php endif;?><?php if($telefono!==''||$correo!==''):?><small><?php echo htmlspecialchars(trim($telefono.($telefono!==''&&$correo!==''?' · ':'').$correo));?></small><?php elseif($nombreContacto===''):?>—<?php endif;?></td>
<td data-label="Estado"><span class="status <?php echo $c['estado']==='ACTIVO'?'done':'expired';?>"><?php echo htmlspecialchars((string)$c['estado']);?></span></td>
<td class="actions-cell" data-label="Acciones"><?php $idClienteSol=$id;require __DIR__.'/../sunat/componentes/acceso_directo_sol.php';?><a class="mini-btn sunat-shortcut" href="../sunat/panel_sire.php?cliente=<?php echo $id;?>">SIRE</a><a class="mini-btn" href="ficha_cliente.php?id=<?php echo $id;?>"><?php echo $gest?'Editar':'Ver';?></a><?php if($gest):?><?php if($c['estado']==='ACTIVO'):?><form class="inline-form js-inhabilitar" method="post" action="../../controladores/clientes/ControladorCliente.php" data-ruc="<?php echo htmlspecialchars((string)$c['ruc']);?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_cliente" value="<?php echo $id;?>"><input type="hidden" name="estado" value="INACTIVO"><button class="mini-btn" type="submit">Inhabilitar</button></form><?php else:?><a class="mini-btn" href="ficha_cliente.php?id=<?php echo $id;?>&reactivar=1">Reactivar</a><?php endif;?><?php if(asesconTienePermiso($conexion,'CLIENTES','eliminar')):?><form class="inline-form js-eliminar" method="post" action="../../controladores/clientes/ControladorCliente.php" data-ruc="<?php echo htmlspecialchars((string)$c['ruc']);?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_cliente" value="<?php echo $id;?>"><input type="hidden" name="confirmacion" value=""><button class="mini-btn danger-btn" type="submit">Eliminar</button></form><?php endif;?><?php endif;?></td>
</tr>
<?php endforeach; endif;?></tbody></table></div></section>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/clientes/gestion_clientes.js"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
