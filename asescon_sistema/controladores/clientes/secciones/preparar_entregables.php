<?php
declare(strict_types=1);
if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; }

require_once __DIR__.'/../../../configuracion/inicializacion.php';
require_once __DIR__.'/../../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../../modelos/comunicaciones/EnvioEntregables.php';
asesconExigirPermiso($conexion,'INFORMES','ver');
$configuracionEntrega=new ConfiguracionComunicaciones($conexion);$entregaDisponible=$configuracionEntrega->disponible();
$escEntrega=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$csrfEntrega=asesconCsrf('csrf_entregables');$claveEntrega=bin2hex(random_bytes(32));
$_SESSION['claves_entregables']=array_slice($_SESSION['claves_entregables']??[],-9,null,true);$_SESSION['claves_entregables'][$claveEntrega]=true;
$clientesEntrega=[];$tiposEntrega=[];$historialEntrega=[];
if($entregaDisponible){
    $filtroEntrega=asesconFiltroClientesSql($conexion,'c');
    $clientesEntrega=$conexion->query("SELECT c.id_cliente,c.ruc,c.razon_social,c.correo FROM clientes c WHERE {$filtroEntrega} AND c.estado='ACTIVO' ORDER BY c.razon_social")->fetch_all(MYSQLI_ASSOC);
    $tiposEntrega=$configuracionEntrega->entregables();$historialEntrega=(new EnvioEntregables($conexion))->historial($filtroEntrega);
}
$mensajeEntrega=$_SESSION['mensaje_entregables']??'';$errorEntrega=$_SESSION['error_entregables']??'';unset($_SESSION['mensaje_entregables'],$_SESSION['error_entregables']);

