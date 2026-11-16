<?php

if (!isset($conexion)) {
    http_response_code(404);
    return;
}

require_once __DIR__ . '/../../configuracion/permisos.php';
require_once __DIR__ . '/../../configuracion/menu_lateral.php';
require_once __DIR__ . '/../../configuracion/secciones_clientes.php';
require_once __DIR__ . '/ControladorIndicadoresMenu.php';

$moduloMenuActual = strtoupper((string) ($moduloActivo ?? ''));
$permisoMenuLateral = static fn(string $codigo): bool => $codigo === 'CONFIGURACION_CORREO'
    ? asesconEsPrincipal($conexion)
    : asesconTienePermiso($conexion, $codigo, 'ver');
$gruposMenuClientes = [];
foreach (asesconGruposClientes() as $codigoGrupo => $grupoMenu) {
    if (asesconSeccionesClientesVisibles($conexion, $codigoGrupo)) {
        $gruposMenuClientes[] = ['texto' => $grupoMenu['titulo'], 'icono' => $codigoGrupo === 'COMUNICACION_CLIENTES' ? '✉' : '▤',
            'ruta' => $grupoMenu['ruta'], 'activo' => $moduloMenuActual === $codigoGrupo];
    }
}

$indicadoresMenuLateral = (new ControladorIndicadoresMenu($conexion))->obtener();
$menuLateral = asesconMenuLateral($permisoMenuLateral, $moduloMenuActual, $gruposMenuClientes, [
    'BUZON_SOL' => $indicadoresMenuLateral['sol'],
    'BUZON_SUNAFIL' => $indicadoresMenuLateral['sunafil'],
]);
