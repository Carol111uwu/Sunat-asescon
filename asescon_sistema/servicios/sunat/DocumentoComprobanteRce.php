<?php
declare(strict_types=1);

require_once __DIR__ . '/ExportadorExcelSire.php';
require_once __DIR__ . '/ReporteComprobanteSirePdf.php';

final class DocumentoComprobanteRce
{
    public function __construct(private array $registro)
    {
    }

    public function numero(): string
    {
        return implode('-', array_filter([$this->campo('serie'), $this->campo('numero')], 'strlen'));
    }

    public function nombreArchivo(): string
    {
        return 'RCE-' . (preg_replace('/[^A-Za-z0-9-]+/', '-', $this->numero()) ?: 'comprobante');
    }

    private function campo(string $clave): string
    {
        return trim((string)($this->registro[$clave] ?? ''));
    }

    private function tipo(): string
    {
        $codigo = $this->campo('tipo_comprobante');
        return match (str_pad($codigo, 2, '0', STR_PAD_LEFT)) {
            '01' => 'Factura (01)',
            '03' => 'Boleta de venta (03)',
            '07' => 'Nota de crédito (07)',
            '08' => 'Nota de débito (08)',
            default => $codigo,
        };
    }

    public function excel(): string
    {
        return (new ExportadorExcelSire())->crear(
            'Registro de compras | ASESCON',
            'SUNAT SIRE · ' . $this->numero(),
            ['Empresa', 'RUC empresa', 'Proveedor', 'RUC / documento proveedor', 'Tipo', 'Serie', 'Número',
             'Fecha de emisión', 'Período SIRE', 'CAR SUNAT', 'Estado', 'Base imponible (S/)', 'IGV (S/)', 'Total (S/)'],
            [[$this->campo('empresa_asescon'), $this->campo('ruc_empresa'), $this->campo('razon_social_proveedor'),
              $this->campo('ruc_proveedor'), $this->tipo(), $this->campo('serie'), $this->campo('numero'),
              $this->campo('fecha_emision'), $this->campo('periodo'), $this->campo('car_sunat'), $this->campo('estado_registro'),
              $this->campo('base_imponible'), $this->campo('igv'), $this->campo('importe_total')]],
            ['text', 'text', 'text', 'text', 'text', 'text', 'text', 'date', 'text', 'text', 'text', 'money', 'money', 'money'],
            'Comprobante RCE'
        );
    }

    public function pdf(): string
    {
        return (new ReporteComprobanteSirePdf())->crear($this->registro, 'RCE', $this->tipo(), $this->numero());
    }
}
