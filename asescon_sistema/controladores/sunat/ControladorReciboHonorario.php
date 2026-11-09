<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/ReciboHonorario.php';
final class ControladorReciboHonorario
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'RHE', 'ver');
        $filas = (new ReciboHonorario($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'));
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
return compact('filas', 'clientes');
    }

    public function crear(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_rhe')) {
            asesconRedirigir('vistas/sunat/recibos_honorarios.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'RHE', 'crear');
        $id = (int)($p['id_cliente'] ?? 0);
        if (!asesconPuedeGestionarCliente($this->conexion, $id)) {
            exit('Cliente no autorizado.');
        }
$bruto = (float)($p['monto_bruto'] ?? 0);
        $ret = (float)($p['retencion'] ?? 0);
        (new ReciboHonorario($this->conexion))->crear(['id_cliente' => $id,'numero_recibo' => trim((string)($p['numero_recibo'] ?? '')) ?: null,'fecha_emision' => trim((string)($p['fecha_emision'] ?? '')) ?: null,'fecha_pago' => trim((string)($p['fecha_pago'] ?? '')) ?: null,'monto_bruto' => $bruto,'retencion' => $ret,'monto_neto' => max(0, $bruto - $ret),'estado' => strtoupper(trim((string)($p['estado'] ?? 'EMITIDO'))),'descripcion' => trim((string)($p['descripcion'] ?? '')) ?: null]);
        asesconRedirigir('vistas/sunat/recibos_honorarios.php', ['guardado' => 1]);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorReciboHonorario($conexion))->crear($_POST);
}
