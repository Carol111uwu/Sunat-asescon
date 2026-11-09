<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/ComprobanteNoDomiciliado.php';

final class ControladorComprobanteNoDomiciliado
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(): array
    {
        asesconExigirPermiso($this->conexion, 'NO_DOMICILIADOS', 'ver');

        $filas = (new ComprobanteNoDomiciliado($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'));
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
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_nodomic')) {
            asesconRedirigir('vistas/sunat/comprobantes_no_domiciliados.php', ['error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'NO_DOMICILIADOS', 'crear');
        $id = (int)($p['id_cliente'] ?? 0);
        if (!asesconPuedeGestionarCliente($this->conexion, $id)) {
            exit('Cliente no autorizado.');
        }
        (new ComprobanteNoDomiciliado($this->conexion))->crear([
            'id_cliente' => $id,'tipo_comprobante' => trim((string)$p['tipo_comprobante']),'serie' => trim((string)($p['serie'] ?? '')) ?: null,
            'numero' => trim((string)($p['numero'] ?? '')) ?: null,'fecha_emision' => trim((string)($p['fecha_emision'] ?? '')) ?: null,
            'proveedor' => trim((string)($p['proveedor'] ?? '')) ?: null,'pais' => trim((string)($p['pais'] ?? '')) ?: null,
            'moneda' => strtoupper(trim((string)($p['moneda'] ?? 'PEN'))),'importe' => (float)($p['importe'] ?? 0),'impuesto' => (float)($p['impuesto'] ?? 0),
            'estado' => strtoupper(trim((string)($p['estado'] ?? 'REGISTRADO')))
        ]);
        asesconRedirigir('vistas/sunat/comprobantes_no_domiciliados.php', ['guardado' => 1]);
    }

    public function generarZip(array $p): never
    {
        if (!asesconValidarCsrf((string)($p['csrf_token'] ?? ''), 'csrf_nodomic')) {
            asesconRedirigir('vistas/sunat/comprobantes_no_domiciliados.php', ['error' => 'csrf']);
        }
        asesconExigirPermiso($this->conexion, 'NO_DOMICILIADOS', 'ejecutar');
        if (!class_exists('ZipArchive')) {
            asesconRedirigir('vistas/sunat/comprobantes_no_domiciliados.php', ['error' => 'zip']);
        }
        $filas = (new ComprobanteNoDomiciliado($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'));
        $tmp = tempnam(sys_get_temp_dir(), 'asescon_nd_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo preparar el ZIP.');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo crear el ZIP.');
        }
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['RUC CLIENTE','CLIENTE','TIPO','SERIE','NUMERO','FECHA','PROVEEDOR','PAIS','MONEDA','IMPORTE','IMPUESTO','ESTADO'], ';');
        foreach ($filas as $d) {
            fputcsv($fh, [$d['ruc'],$d['razon_social'],$d['tipo_comprobante'],$d['serie'],$d['numero'],$d['fecha_emision'],$d['proveedor'],$d['pais'],$d['moneda'],$d['importe'],$d['impuesto'],$d['estado']], ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        $zip->addFromString('comprobantes_no_domiciliados.csv', (string)$csv);
        $zip->addFromString('LEEME.txt', "Archivo generado por ASESCON para revisión y preparación de datos. Debe validarse contra la estructura oficial SUNAT vigente antes de una carga o presentación.\r\n");
        $zip->close();
        $nombre = 'respaldo_no_domiciliados_' . date('Ymd_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorComprobanteNoDomiciliado($conexion);
    $accion = (string)($_POST['accion'] ?? 'crear');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'generar_zip') {
        $c->generarZip($_POST);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->crear($_POST);
    }
    asesconRedirigir('vistas/sunat/comprobantes_no_domiciliados.php');
}
