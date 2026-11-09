<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/buzones/BuzonSunafil.php';
require_once __DIR__ . '/../../servicios/sunafil/ServicioColaBuzonSunafil.php';
require_once __DIR__ . '/../../servicios/buzones/EstadoCronBuzon.php';

final class ControladorBuzonSunafil
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $estado = '', int $idCliente = 0,string $tipo='',string $desde='',string $hasta='',int $pagina=1): array
    {
        asesconExigirPermiso($this->conexion, 'BUZON_SUNAFIL', 'ver');

        $m = new BuzonSunafil($this->conexion);
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
        $cron=['reciente'=>false,'mensaje'=>'El almacenamiento del Buzón SUNAFIL no está listo. Revisa la configuración del servidor.'];
        try {
            $cola = new ServicioColaBuzonSunafil();
            $cron=EstadoCronBuzon::consultar($cola->directorioBase());
            if ($idCliente > 0 && asesconPuedeVerCliente($this->conexion, $idCliente)) {
                $sincronizacion = $cola->obtenerEstadoCliente($idCliente);
                $detalleCola=EstadoCronBuzon::detalleCola($cola->directorioBase(),$sincronizacion);
            }
        } catch (Throwable $e) {
            error_log('Buzón SUNAFIL estado de servidor: '.get_class($e));
        }

        $total=$m->contarFiltrados($f,$estado,$idCliente,$tipo,$desde,$hasta);
        $clientesPaginados=$idCliente===0?$m->clientesConNotificaciones($f,$estado,$tipo,$desde,$hasta):[];
        $paginas=$idCliente===0?max(1,count($clientesPaginados)):1;
        $pagina=max(1,min($pagina,$paginas));
        $clientePagina=$idCliente===0?($clientesPaginados[$pagina-1]??null):null;
        $clienteListado=$idCliente>0?$idCliente:(int)($clientePagina['id_cliente']??0);
        return [
            'filas' => $clienteListado>0?$m->listar($f,$estado,$clienteListado,$tipo,$desde,$hasta):[],
            'total_filtrado'=>$total,'pagina_actual'=>$pagina,'total_paginas'=>$paginas,
            'cliente_pagina'=>$clientePagina,
            'tipos_disponibles'=>$m->tiposDisponibles($f),
            'resumen' => $m->resumen($f),
            'clientes_disponibles' => $clientes,
            'sincronizacion' => $sincronizacion,
            'estado_cron' => $cron,
            'detalle_cola' => $detalleCola,
        ];
    }

    public function procesar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_buzon_sunafil')) {
            asesconRedirigir('vistas/buzones/buzon_sunafil.php', ['error' => 'csrf']);
        }

        $accion = (string)($p['accion'] ?? '');
        if ($accion === 'revisado') {
            asesconExigirPermiso($this->conexion, 'BUZON_SUNAFIL', 'editar');
            try {
                (new BuzonSunafil($this->conexion))->marcarRevisado(
                    (int)($p['id_documento'] ?? 0),
                    asesconIdsClientesPermitidos($this->conexion)
                );
                $idFiltro=(int)($p['id_cliente']??0);
                if($idFiltro>0&&!asesconPuedeVerCliente($this->conexion,$idFiltro))$idFiltro=0;
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', ['guardado' => 1,'id_cliente'=>$idFiltro]);
            } catch (DomainException $e) {
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', ['error' => 'documento']);
            }
        }

        if ($accion === 'sincronizar') {
            asesconExigirPermiso($this->conexion, 'BUZON_SUNAFIL', 'editar');
            $idCliente = (int)($p['id_cliente'] ?? 0);
            if ($idCliente === 0 && ($p['alcance']??'') === 'todos' && asesconEsPrincipal($this->conexion)) {
                try {
                    require_once __DIR__.'/../../servicios/buzones/ProgramadorBuzones.php';
                    $resumen = (new ProgramadorBuzones($this->conexion))->todos('SUNAFIL');
                    $_SESSION['mensaje_buzones_global'] = [
                        'tipo' => $resumen['empresas'] > 0 && $resumen['errores'] === 0 ? 'ok' : 'aviso',
                        'texto' => ProgramadorBuzones::mensaje('SUNAFIL', $resumen),
                    ];
                } catch (Throwable $e) {
                    error_log('Buzón SUNAFIL masivo: ' . get_class($e));
                    $_SESSION['mensaje_buzones_global'] = ['tipo'=>'error','texto'=>'No se pudo programar SUNAFIL. Revisa Cron y los accesos.'];
                }
                asesconRedirigir('vistas/buzones/buzon_sunafil.php');
            }
            if ($idCliente <= 0 || !asesconPuedeVerCliente($this->conexion, $idCliente)) {
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', ['error' => 'cliente']);
            }

            $stmt = $this->conexion->prepare("SELECT id_cliente FROM clientes WHERE id_cliente=? AND estado='ACTIVO' LIMIT 1");
            if (!$stmt) {
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', ['error' => 'cliente']);
            }
            $stmt->bind_param('i', $idCliente);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', ['error' => 'cliente']);
            }

            try {
                $estado = (new ServicioColaBuzonSunafil())->solicitar(
                    $idCliente,
                    (int)($_SESSION['id_usuario'] ?? 0)
                );
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', [
                    'id_cliente' => $idCliente,
                    'solicitud' => 1,
                    'estado_sync' => strtolower((string)($estado['estado'] ?? 'pendiente')),
                ]);
            } catch (Throwable $e) {
                error_log('Buzón SUNAFIL cola: ' . get_class($e) . ': ' . $e->getMessage());
                asesconRedirigir('vistas/buzones/buzon_sunafil.php', [
                    'id_cliente' => $idCliente,
                    'error' => 'sunafil_sync',
                    'detalle' => 'No se pudo registrar la solicitud de sincronización.',
                ]);
            }
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorBuzonSunafil($conexion))->procesar($_POST);
}
