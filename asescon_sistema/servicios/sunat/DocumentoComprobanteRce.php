<?php
declare(strict_types=1);

require_once __DIR__ . '/ExportadorExcelSire.php';

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
        $logo = __DIR__ . '/../../publico/img/asescon-logo-rce.jpg';
        [$ancho, $alto] = getimagesize($logo);
        $imagen = file_get_contents($logo);
        $altoLogo = round(170 * $alto / $ancho, 2);
        $p = "q 170 0 0 $altoLogo 42 728 cm /Logo Do Q\n";
        $p .= "0.98 g 0.66 G 328 711 225 83 re B\n";
        $p .= $this->texto(344, 773, 'REGISTRO DE COMPRAS · RCE', 9, true);
        $numero = $this->numero() ?: 'Sin numeración';
        $tamanoNumero = min(16, (int)floor(195 / (max(1, strlen($numero)) * 0.6)));
        $p .= $this->texto(344, 747, $numero, $tamanoNumero, true);
        $p .= $this->texto(344, 728, $this->tipo(), 10);

        $p .= $this->texto(42, 679, 'PROVEEDOR', 9, true);
        $p .= $this->texto(310, 679, 'EMPRESA COMPRADORA', 9, true);
        $proveedor = $this->lineasNombre($this->campo('razon_social_proveedor'));
        $empresa = $this->lineasNombre($this->campo('empresa_asescon'));
        $p .= $this->nombre(42, 657, $proveedor);
        $p .= $this->nombre(310, 657, $empresa);
        $ajuste = 12 * (4 - max(count($proveedor), count($empresa)));
        $p .= "q 1 0 0 1 0 $ajuste cm\n";
        $p .= $this->texto(42, 602, 'RUC / documento  ' . ($this->campo('ruc_proveedor') ?: '—'), 10);
        $p .= $this->texto(310, 602, 'RUC  ' . ($this->campo('ruc_empresa') ?: '—'), 10);

        $p .= "0.76 G 42 581 m 553 581 l S\n";
        $p .= $this->texto(42, 555, 'Fecha de emisión', 9, true);
        $p .= $this->texto(218, 555, 'Período SIRE', 9, true);
        $p .= $this->texto(388, 555, 'Estado del registro', 9, true);
        $p .= $this->texto(42, 536, $this->campo('fecha_emision') ?: '—', 10);
        $p .= $this->texto(218, 536, $this->campo('periodo') ?: '—', 10);
        $p .= $this->texto(388, 536, $this->campo('estado_registro') ?: '—', 10);
        $p .= $this->texto(42, 504, 'CAR SUNAT   ' . ($this->campo('car_sunat') ?: '—'), 9);

        $p .= "0.90 g 42 436 511 30 re f\n";
        $p .= $this->texto(54, 447, 'CONCEPTO', 9, true);
        $p .= $this->texto(541, 447, 'IMPORTE (S/)', 9, true, true);
        foreach ([['Base imponible', 'base_imponible'], ['IGV', 'igv'], ['Total registrado', 'importe_total']] as $i => [$etiqueta, $clave]) {
            $y = 411 - 33 * $i;
            $total = $i === 2;
            if ($total) {
                $p .= '0.94 g 42 ' . ($y - 12) . " 511 33 re f\n";
            }
            $valor = $this->campo($clave);
            $p .= $this->texto(54, $y, $etiqueta, $total ? 12 : 10, $total);
            $p .= $this->texto(541, $y, $valor === '' ? '—' : number_format((float)$valor, 2), $total ? 12 : 10, $total, true);
            $p .= '0.76 G 42 ' . ($y - 12) . ' m 553 ' . ($y - 12) . " l S\n";
        }

        $p .= $this->texto(42, 291, 'Fuente: SUNAT SIRE · Registro de Compras Electrónico.', 9);
        $p .= $this->texto(42, 274, 'El RCE contiene importes consolidados por comprobante.', 9);
        $p .= $this->texto(42, 257, 'Reporte informativo. Para fines tributarios, consulta el comprobante original.', 9);
        $p .= "Q\n";
        $p .= "0.76 G 42 66 m 553 66 l S\n";
        $p .= $this->texto(42, 47, 'ASESCON · Contadores y Abogados', 9, true);
        $p .= $this->texto(553, 47, '1 / 1', 9, false, true);

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Logo 6 0 R >> >> /Contents 7 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
            "<< /Type /XObject /Subtype /Image /Width $ancho /Height $alto /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($imagen) . " >>\nstream\n$imagen\nendstream",
            '<< /Length ' . strlen($p) . " >>\nstream\n$p\nendstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $xref = "0000000000 65535 f \n";
        foreach ($objetos as $i => $objeto) {
            $xref .= sprintf('%010d 00000 n ', strlen($pdf)) . "\n";
            $pdf .= ($i + 1) . " 0 obj\n$objeto\nendobj\n";
        }
        $inicioXref = strlen($pdf);
        return $pdf . "xref\n0 8\n$xref" . "trailer << /Size 8 /Root 1 0 R >>\nstartxref\n$inicioXref\n%%EOF\n";
    }

    private function lineasNombre(string $nombre): array
    {
        return explode("\n", wordwrap($this->codificar($nombre ?: '—'), 40, "\n", true));
    }

    private function nombre(int $x, int $y, array $lineas): string
    {
        $p = '';
        foreach ($lineas as $i => $linea) {
            $p .= $this->textoCodificado($x, $y - 12 * $i, $linea, 10, true, false);
        }
        return $p;
    }

    private function codificar(string $texto): string
    {
        $limpio = preg_replace('/\s+/u', ' ', $texto) ?? '';
        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $limpio) ?: '';
    }

    private function texto(int $x, int $y, string $texto, int $tamano, bool $negrita = false, bool $derecha = false): string
    {
        return $this->textoCodificado($x, $y, $this->codificar($texto), $tamano, $negrita, $derecha);
    }

    private function textoCodificado(int $x, int $y, string $texto, int $tamano, bool $negrita, bool $derecha): string
    {
        $origen = $derecha ? $x - strlen($texto) * $tamano * 0.6 : $x;
        $literal = strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $fuente = $negrita ? 'F2' : 'F1';
        return "0 g BT /$fuente $tamano Tf 1 0 0 1 $origen $y Tm ($literal) Tj ET\n";
    }
}
