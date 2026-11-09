<?php
declare(strict_types=1);
function asesconGruposClientes(): array
{
    return [
        'COMUNICACION_CLIENTES' => ['titulo'=>'Comunicación e informes', 'ruta'=>'vistas/clientes/comunicacion_informes.php', 'secciones'=>[
            'recordatorios'=>['titulo'=>'Recordatorios WhatsApp','permiso'=>'WHATSAPP'],
            'informes'=>['titulo'=>'Informes','permiso'=>'INFORMES'],
            'entregables'=>['titulo'=>'Envío de entregables','permiso'=>'INFORMES'],
        ]],
        'SERVICIOS_CLIENTES' => ['titulo'=>'Servicios y cobros', 'ruta'=>'vistas/clientes/servicios_cobros.php', 'secciones'=>[
            'planes'=>['titulo'=>'Planes','permiso'=>'PLANES'],
            'cotizaciones'=>['titulo'=>'Cotizaciones','permiso'=>'COTIZACIONES'],
            'honorarios'=>['titulo'=>'Honorarios','permiso'=>'HONORARIOS'],
        ]],
    ];
}
function asesconSeccionesClientesVisibles(mysqli $conexion, string $grupo): array
{
    $grupos=asesconGruposClientes();
    return array_filter($grupos[$grupo]['secciones']??[], static fn(array $seccion): bool => asesconTienePermiso($conexion,$seccion['permiso'],'ver'));
}
