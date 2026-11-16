<?php
declare(strict_types=1);

require_once __DIR__ . '/DocumentoComprobanteRce.php';
require_once __DIR__ . '/DocumentoComprobanteRvie.php';

final class ExportadorZipPeriodoSire
{
    public const TAMANO_LOTE = 100;

    public function crear(string $libro, string $periodo, int $offset, iterable $filas, string $archivoTemporal): array
    {
        $generador = $libro === 'RCE' ? DocumentoComprobanteRce::class : DocumentoComprobanteRvie::class;
        $zip = new ZipArchive();
        if ($zip->open($archivoTemporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo preparar el ZIP de comprobantes.');
        }
        $zip->addFromString('LEER_PRIMERO.txt',
            "ASESCON — {$libro}\nLos PDF son reportes informativos generados a partir de registros SIRE consultados.\n"
            . "No son los XML, CDR ni PDF electrónicos originales emitidos por SUNAT o el proveedor.\n"
            . "Período: {$periodo}. Lote desde el registro " . ($offset + 1) . ".\n");
        $csv = fopen('php://temp', 'w+');
        if ($csv === false) {
            throw new RuntimeException('No se pudo crear el manifiesto de descarga.');
        }
        fputcsv($csv, ['empresa', 'ruc_empresa', 'periodo', 'serie', 'numero', 'tipo', 'estado', 'archivo'], ',', '"', '');
        $valorCsv = static function (mixed $valor): string {
            $texto = (string)$valor;
            return preg_match('/^\s*[=+@-]/u', $texto) ? "'" . $texto : $texto;
        };
        $generados = 0;
        $errores = 0;
        foreach ($filas as $registro) {
            $archivo = '';
            $estado = 'CORRECTO';
            try {
                $documento = new $generador($registro);
                $nombre = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $documento->nombreArchivo());
                $nombre = trim(substr((string)$nombre, 0, 90), '-_.') ?: 'Comprobante';
                $archivo = 'empresa-' . (int)$registro['id_cliente'] . '/'
                    . $registro['periodo'] . '/' . $nombre . '-'
                    . (int)$registro['id_detalle'] . '.pdf';
                if (!$zip->addFromString($archivo, $documento->pdf())) {
                    throw new RuntimeException('No se pudo incluir el PDF.');
                }
                $generados++;
            } catch (Throwable $error) {
                error_log('ASESCON descarga masiva ' . $libro . ': ' . get_class($error));
                $estado = 'ERROR_GENERACION';
                $archivo = '';
                $errores++;
            }
            fputcsv($csv, [
                $valorCsv($registro['empresa_asescon'] ?? ''),
                $valorCsv($registro['ruc_empresa'] ?? ''),
                $valorCsv($registro['periodo'] ?? ''),
                $valorCsv($registro['serie'] ?? ''),
                $valorCsv($registro['numero'] ?? ''),
                $valorCsv($registro['tipo_comprobante'] ?? ''),
                $estado,
                $archivo,
            ], ',', '"', '');
        }
        rewind($csv);
        if (!$zip->addFromString('MANIFIESTO.csv', (string)stream_get_contents($csv))) {
            throw new RuntimeException('No se pudo añadir el manifiesto.');
        }
        fclose($csv);
        if (!$zip->close()) {
            throw new RuntimeException('No se pudo cerrar el ZIP de reportes.');
        }
        if ($generados < 1) {
            throw new RuntimeException('No se pudo generar ningún reporte del lote. Consulta el registro de errores.');
        }

        return ['generados' => $generados, 'errores' => $errores];
    }
}
