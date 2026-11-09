<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/buzones/BuzonSol.php';
require_once __DIR__ . '/../../servicios/sunat/ServicioColaBuzonSol.php';
require_once __DIR__ . '/../../servicios/buzones/EstadoCronBuzon.php';

final class ControladorBuzonSol
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $estado = '', string $categoria = '', int $idCliente = 0, string $desde='',string $hasta='',int $pagina=1): array
    {
        asesconExigirPermiso($this->conexion, 'BUZON_SOL', 'ver');

        $m = new BuzonSol($this->conexion);
        $f = asesconFiltroClientesSql($this->conexion, 'c');
        $clientes = [];
        $r = $this->conexion->query("SELECT c.id_cliente,c.ruc,c.razon_social FROM clientes c WHERE {$f} AND c.estado='ACTIVO' ORDER BY c.razon_social");
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }

        $sincronizacion = [];
        $detalleCola=['posicion'=>null,'pendientes'=>0,'espera_segundos'=>null];
        $cron=['reciente'=>false,'mensaje'=>'El almacenamiento del Buzón SOL no está listo. Revisa la configuración del servidor.'];
        try {
            $cola = new ServicioColaBuzonSol();
            $cron=EstadoCronBuzon::consultar($cola->directorioBase());
            if ($idCliente > 0 && asesconPuedeVerCliente($this->conexion, $idCliente)) {
                $sincronizacion = $cola->obtenerEstadoCliente($idCliente);
                $detalleCola=EstadoCronBuzon::detalleCola($cola->directorioBase(),$sincronizacion);
            }
        } catch (Throwable $e) {
            error_log('Buzón SOL estado de servidor: '.get_class($e));
        }

        $totalFiltrado=$m->contarFiltrados($f,$estado,$categoria,$idCliente,$desde,$hasta);
        $porPagina=50;
        $clientesPaginados=$idCliente===0?$m->clientesConNotificaciones($f,$estado,$categoria,$desde,$hasta):[];
        $totalPaginas=$idCliente===0?max(1,count($clientesPaginados)):max(1,(int)ceil($totalFiltrado/$porPagina));
        $pagina=max(1,min($pagina,$totalPaginas));
        $clientePagina=$idCliente===0?($clientesPaginados[$pagina-1]??null):null;
        $clienteListado=$idCliente>0?$idCliente:(int)($clientePagina['id_cliente']??0);
        return [
            'filas' => $clienteListado>0
                ? $m->listar($f,$estado,$categoria,$clienteListado,$desde,$hasta,$porPagina,
                    $idCliente===0?0:($pagina-1)*$porPagina,$idCliente===0)
                : [],
            'total_filtrado'=>$totalFiltrado,
            'pagina_actual'=>$pagina,
            'total_paginas'=>$totalPaginas,
            'cliente_pagina'=>$clientePagina,
            'resumen' => $m->resumen($f),
            'clientes_disponibles' => $clientes,
            'sincronizacion' => $sincronizacion,
            'estado_cron' => $cron,
            'detalle_cola' => $detalleCola,
        ];
    }

    public function procesar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_buzon_sol')) {
            asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', ['error' => 'csrf']);
        }

        $accion = (string)($p['accion'] ?? '');
        if ($accion === 'revisado' || $accion === 'reabrir') {
            asesconExigirPermiso($this->conexion, 'BUZON_SOL', 'editar');
            try {
                (new BuzonSol($this->conexion))->actualizarRevision(
                    (int)($p['id_documento'] ?? 0),
                    asesconIdsClientesPermitidos($this->conexion),
                    $accion === 'revisado' ? 'REVISADO' : 'NO_REVISADO'
                );
                $idFiltro=(int)($p['id_cliente']??0);
                if($idFiltro>0&&!asesconPuedeVerCliente($this->conexion,$idFiltro))$idFiltro=0;
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', ['guardado'=>1,'id_cliente'=>$idFiltro]);
            } catch (DomainException $e) {
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', ['error' => 'documento']);
            }
        }

        if ($accion === 'enviar_correo') {
            require_once __DIR__.'/ControladorDocumentoBuzon.php';
            $p['buzon']='SOL';
            (new ControladorDocumentoBuzon($this->conexion))->procesar($p,$_FILES);
            return;
        }

        if ($accion === 'sincronizar') {
            asesconExigirPermiso($this->conexion, 'BUZON_SOL', 'editar');
            $idCliente = (int)($p['id_cliente'] ?? 0);
            if ($idCliente === 0 && ($p['alcance']??'') === 'todos' && asesconEsPrincipal($this->conexion)) {
                try {
                    require_once __DIR__.'/../../servicios/buzones/ProgramadorBuzones.php';
                    $resumen = (new ProgramadorBuzones($this->conexion))->todos('SOL');
                    $_SESSION['mensaje_buzones_global'] = [
                        'tipo' => $resumen['empresas'] > 0 && $resumen['errores'] === 0 ? 'ok' : 'aviso',
                        'texto' => ProgramadorBuzones::mensaje('SUNAT SOL', $resumen),
                    ];
                } catch (Throwable $e) {
                    error_log('Buzón SOL masivo: ' . get_class($e));
                    $_SESSION['mensaje_buzones_global'] = ['tipo'=>'error','texto'=>'No fue posible programar el Buzón SOL. Revisa Cron y los accesos.'];
                }
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php');
            }
            if ($idCliente <= 0 || !asesconPuedeVerCliente($this->conexion, $idCliente)) {
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', ['error' => 'cliente']);
            }

            $stmt = $this->conexion->prepare("SELECT id_cliente FROM clientes WHERE id_cliente=? AND estado='ACTIVO' LIMIT 1");
            if (!$stmt) {
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', ['error' => 'cliente']);
            }
            $stmt->bind_param('i', $idCliente);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', ['error' => 'cliente']);
            }

            try {
                $estado = (new ServicioColaBuzonSol())->solicitar(
                    $idCliente,
                    (int)($_SESSION['id_usuario'] ?? 0)
                );
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', [
                    'id_cliente' => $idCliente,
                    'solicitud' => 1,
                    'estado_sync' => strtolower((string)($estado['estado'] ?? 'pendiente')),
                ]);
            } catch (Throwable $e) {
                error_log('Buzón SOL cola: ' . get_class($e) . ': ' . $e->getMessage());
                asesconRedirigir('vistas/buzones/buzon_sol_sunat.php', [
                    'id_cliente' => $idCliente,
                    'error' => 'sol_sync',
                    'detalle' => 'No se pudo registrar la solicitud de sincronización.',
                ]);
            }
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorBuzonSol($conexion))->procesar($_POST);
}
