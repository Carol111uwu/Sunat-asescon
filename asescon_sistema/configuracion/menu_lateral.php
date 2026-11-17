<?php
declare(strict_types=1);

function asesconMenuLateral(callable $permiso, string $activo, array $gruposClientes, array $indicadores): array
{
    $enlace = static function (string $codigo, string $texto, string $icono, string $ruta, ?string $permisoRequerido = null) use ($permiso, $activo, $indicadores): ?array {
        if (!$permiso($permisoRequerido ?? $codigo)) {
            return null;
        }
        return ['texto' => $texto, 'icono' => $icono, 'ruta' => $ruta, 'activo' => $activo === $codigo,
            'contador' => (int) ($indicadores[$codigo] ?? 0)];
    };
    $grupo = static function (string $id, string $texto, string $icono, array $elementos): ?array {
        $elementos = array_values(array_filter($elementos));
        if ($elementos === []) {
            return null;
        }
        return ['id' => $id, 'texto' => $texto, 'icono' => $icono, 'elementos' => $elementos,
            'activo' => in_array(true, array_column($elementos, 'activo'), true)];
    };

    return array_values(array_filter([
        $enlace('DASHBOARD', 'Dashboard', '⌂', 'vistas/panel_principal.php'),
        $grupo('generalMenu', 'General', '▥', [
            $enlace('CLIENTES', 'Mis clientes', '♙', 'vistas/clientes/gestion_clientes.php'),
            $enlace('TAREAS', 'Tareas y flujo de trabajo', '☷', 'vistas/tareas/tareas.php'),
            $enlace('EQUIPO', 'Mi equipo', '♙', 'vistas/equipo/equipo.php'),
        ]),
        $grupo('buzonMenu', 'Buzón electrónico', '✉', [
            $enlace('BUZON_SOL', 'SUNAT - Buzón SOL', '✉', 'vistas/buzones/buzon_sol_sunat.php'),
            $enlace('BUZON_SUNAFIL', 'SUNAFIL - Casilla electrónica', '✉', 'vistas/buzones/buzon_sunafil.php'),
        ]),
        $grupo('tributarioMenu', 'Tributario', '◉', [
            $grupo('sireMenu', 'SIRE y Comprobantes', '◫', [
                $enlace('RCE', 'RCE (Compras)', '▤', 'vistas/sunat/rce.php'),
                $enlace('RVIE', 'RVIE (Ventas)', '▤', 'vistas/sunat/rvie.php'),
                $enlace('NO_DOMICILIADOS', 'Comprobantes No Domiciliados', '▤', 'vistas/sunat/comprobantes_no_domiciliados.php'),
                $enlace('CPE', 'Consulta de Validez CPE', '▤', 'vistas/sunat/consulta_cpe.php'),
            ]),
            $grupo('declaracionesMenu', 'Declaraciones y Liquidaciones', '▣', [
                $enlace('LIQUIDACIONES', 'Liquidación de Impuestos', '▥', 'vistas/sunat/liquidaciones_impuestos.php'),
                $enlace('PDT_0621', 'PDT / Formulario 0621', '▤', 'vistas/sunat/pdt_0621.php'),
                $enlace('PLAME_0601', 'PDT / Formulario 0601', '▤', 'vistas/sunat/plame_0601.php'),
                $enlace('DECLARACIONES', 'Control de Declaraciones', '▣', 'vistas/sunat/control_declaraciones.php'),
                $enlace('DECLARACIONES_PAGOS_SUNAT', 'Historial de Declaraciones y Pagos', '▤', 'vistas/sunat/declaraciones_pagos/principal.php'),
            ]),
            $grupo('operacionesMenu', 'Operaciones y Alertas', '◈', [
                $enlace('DETRACCIONES', 'Detracciones', '◈', 'vistas/sunat/detracciones.php'),
                $enlace('RHE', 'Recibos por Honorarios', '▱', 'vistas/sunat/recibos_honorarios.php'),
                $grupo('vencimientosAlertasMenu', 'Vencimientos y Alertas Tributarias', '△', [
                    $enlace('VENCIMIENTOS_SUNAT', 'Vencimientos SUNAT', '▣', 'vistas/vencimientos/vencimientos_sunat.php'),
                    $enlace('ALERTAS_SUNAT', 'Alertas SUNAT', '△', 'vistas/alertas/alertas_sunat.php'),
                ]),
                $enlace('RELACION_SSCO', 'Relación SSCO', '△', 'vistas/alertas/relacion_ssco.php', 'ALERTAS_SUNAT'),
            ]),
        ]),
        $grupo('afpMenu', 'AFPs', '◫', [
            $enlace('AFP', 'Gestión AFP', '▤', 'vistas/afp/afp.php'),
        ]),
        $grupo('extrasMenu', 'Extras', '⊞', [
            ...$gruposClientes,
            $enlace('SIRE', 'Dashboard SIRE', '◫', 'vistas/sunat/panel_sire.php'),
            $enlace('COMPROBANTES_SUNAT', 'Comprobantes SUNAT', '▤', 'vistas/sunat/comprobantes_sunat.php'),
            $grupo('utilMenu', 'Utilitarios', '⚙', [
                $enlace('TIPO_CAMBIO', 'Tipo de Cambio', '◌', 'vistas/sunat/tipo_cambio.php'),
                $grupo('herramientasMenu', 'Herramientas / Configuración', '⚙', [
                    $enlace('UTILITARIOS', 'Panel de utilitarios', '⚙', 'vistas/utilitarios/utilitarios.php'),
                    $enlace('UTIL_RUTA', 'Ruta de declaraciones', '⌁', 'vistas/utilitarios/ruta_declaraciones.php', 'UTILITARIOS'),
                    $enlace('UTIL_WHATSAPP', 'Notificaciones WhatsApp', '◉', 'vistas/utilitarios/notificaciones_whatsapp.php', 'UTILITARIOS'),
                    $enlace('CONFIGURACION_CORREO', 'Configuración de correo', '✉', 'vistas/comunicaciones/configuracion_correo.php'),
                ]),
            ]),
        ]),
    ]));
}
