<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/Cpe.php';
final class ControladorCpe
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'CPE', 'ver');

        $filas = (new Cpe($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'));
        $clientes = [];
        $disponibles = asesconClientesVisibles($this->conexion, true);
        if ($disponibles) {
            while ($cliente = $disponibles->fetch_assoc()) { $clientes[] = $cliente; }
        }
        return compact('filas','clientes');
    }
    public function registrar(array $p): void
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_cpe')) {
            asesconRedirigir('vistas/sunat/consulta_cpe.php', ['error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'CPE', 'ejecutar');
        $ruc = preg_replace('/\D+/', '', (string)($p['ruc_emisor'] ?? '')) ?? '';
        if (!preg_match('/^\d{11}$/', $ruc)) {
            asesconRedirigir('vistas/sunat/consulta_cpe.php', ['error' => 'ruc']);
        }
        $tipo = trim((string)($p['tipo_comprobante'] ?? ''));
        $permitidos = ['01','03','07','08'];
        if (!in_array($tipo, $permitidos, true)) {
            asesconRedirigir('vistas/sunat/consulta_cpe.php', ['error' => 'tipo']);
        }
        $idCliente = (int)($p['id_cliente'] ?? 0);
        if ($idCliente <= 0 || !asesconPuedeGestionarCliente($this->conexion, $idCliente)) {
            http_response_code(403);
            exit('Cliente no autorizado para registrar un comprobante.');
        }
        (new Cpe($this->conexion))->registrar([
            'id_cliente' => $idCliente,'ruc_emisor' => $ruc,'tipo_comprobante' => $tipo,'serie' => strtoupper(trim((string)($p['serie'] ?? ''))),
            'numero' => trim((string)($p['numero'] ?? '')),'fecha_emision' => trim((string)($p['fecha_emision'] ?? '')) ?: null,
            'monto_total' => (float)($p['monto_total'] ?? 0),'moneda' => 'PEN'
        ]);
        asesconRedirigir('vistas/sunat/consulta_cpe.php', ['guardado' => 1]);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        (new ControladorCpe($conexion))->registrar($_POST);
    }
asesconRedirigir('vistas/sunat/consulta_cpe.php');
}
