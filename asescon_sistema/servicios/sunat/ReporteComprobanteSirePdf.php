<?php
declare(strict_types=1);

final class ReporteComprobanteSirePdf
{
    public function crear(array $registro, string $libro, string $tipo, string $numero): string
    {
        $esCompra = $libro === 'RCE';
        $emisor = $esCompra ? 'razon_social_proveedor' : 'empresa_asescon';
        $documentoEmisor = $esCompra ? 'ruc_proveedor' : 'ruc_empresa';
        $receptor = $esCompra ? 'empresa_asescon' : 'razon_social_cliente';
        $documentoReceptor = $esCompra ? 'ruc_empresa' : 'documento_cliente';
        $valor = static fn(string $clave): string => trim((string)($registro[$clave] ?? ''));
        $fecha = $valor('fecha_emision');
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$| )/', $fecha, $partes)) {
            $fecha = "$partes[3]/$partes[2]/$partes[1]";
        }
        $nombreTipo = match ($tipo) {
            'Factura (01)' => 'FACTURA ELECTRÓNICA',
            'Boleta de venta (03)' => 'BOLETA DE VENTA ELECTRÓNICA',
            'Nota de crédito (07)' => 'NOTA DE CRÉDITO',
            'Nota de débito (08)' => 'NOTA DE DÉBITO',
            default => 'COMPROBANTE · TIPO ' . ($tipo ?: 'SIN DATO'),
        };

        $p = "0 g 0.25 G 0.65 w\n";
        $y = 770;
        $p .= $this->bloque(40, $y, $valor($emisor), 39, 11, true);
        $p .= $this->texto(40, $y - 2, 'RUC / documento: ' . ($valor($documentoEmisor) ?: '—'), 10);

        $p .= "352 735 203 53 re S\n";
        $p .= $this->texto(364, 773, $nombreTipo, 10, true);
        $p .= $this->texto(364, 758, 'RUC / documento: ' . ($valor($documentoEmisor) ?: '—'), 9);
        $p .= $this->texto(364, 742, $numero ?: 'Sin numeración', 13, true);

        $y = min(726, $y - 20);
        $p .= "34 $y m 561 $y l S\n";
        $y -= 21;
        foreach ([
            ['Fecha de emisión', $fecha],
            [$esCompra ? 'Empresa compradora' : 'Cliente / receptor', $valor($receptor)],
            ['RUC / documento', $valor($documentoReceptor)],
            ['Período SIRE', $valor('periodo')],
            ['Estado del registro', $valor('estado_registro')],
            ['CAR SUNAT', $valor('car_sunat')],
        ] as [$etiqueta, $dato]) {
            $p .= $this->texto(40, $y, $etiqueta, 10, true);
            $p .= $this->texto(169, $y, ':', 10);
            $p .= $this->bloque(183, $y, $dato, 57, 10);
            $y -= 5;
        }

        $y -= 11;
        $fila = $y - 25;
        $p .= "40 $fila 515 25 re S\n";
        $p .= $this->texto(51, $y - 16, 'DESCRIPCIÓN DEL REGISTRO', 9, true);
        $p .= $this->texto(427, $y - 16, 'IMPORTE REGISTRADO', 9, true);
        $p .= "416 $fila m 416 $y l S\n";
        $y = $fila - 27;
        $p .= "40 $y 515 27 re S 416 $y m 416 $fila l S\n";
        $p .= $this->texto(51, $y + 10, 'Importe consolidado del comprobante', 10);
        $p .= $this->importe(543, $y + 10, $valor('importe_total'));

        $tope = $y - 16;
        $p .= $this->texto(42, $tope - 30, "REGISTRO ELECTRÓNICO $libro", 9, true);
        $p .= $this->texto(42, $tope - 45, 'Fuente: SUNAT SIRE', 9);
        foreach ([
            ['Base imponible', $valor('base_imponible')],
            ['IGV', $valor('igv')],
            ['Importe total', $valor('importe_total')],
        ] as $i => [$etiqueta, $dato]) {
            $y = $tope - 22 * ($i + 1);
            $total = $i === 2;
            $p .= "315 $y 240 22 re S\n";
            $p .= '438 ' . $y . ' m 438 ' . ($y + 22) . " l S\n";
            $p .= $this->texto(326, $y + 7, $etiqueta, 10, $total);
            $p .= $this->importe(543, $y + 7, $dato, $total);
        }

        $y -= 55;
        $p .= "40 $y 515 36 re S\n";
        $p .= $this->texto(51, $y + 22, 'Reporte informativo de ASESCON basado en el registro SIRE.', 9);
        $p .= $this->texto(51, $y + 10, 'Para verificar el documento, consulta el comprobante electrónico original.', 9);
        $marco = $y - 5;
        $altoMarco = 800 - $marco;
        $p .= "34 $marco 527 $altoMarco re S\n";

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . strlen($p) . " >>\nstream\n$p\nendstream",
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $xref = "0000000000 65535 f \n";
        foreach ($objetos as $i => $objeto) {
            $xref .= sprintf('%010d 00000 n ', strlen($pdf)) . "\n";
            $pdf .= ($i + 1) . " 0 obj\n$objeto\nendobj\n";
        }
        $inicioXref = strlen($pdf);
        return $pdf . "xref\n0 7\n$xref" . "trailer << /Size 7 /Root 1 0 R >>\nstartxref\n$inicioXref\n%%EOF\n";
    }

    private function importe(int $x, int $y, string $valor, bool $negrita = false): string
    {
        $texto = is_numeric($valor) ? 'S/ ' . number_format((float)$valor, 2) : '—';
        $ancho = $texto === '—' ? 10 : (int)round(10 * (strlen($texto) * 0.556 + 0.111 - 0.278 * substr_count($texto, ',') - 0.278));
        return $this->texto($x - $ancho, $y, $texto, 10, $negrita);
    }

    private function bloque(int $x, int &$y, string $texto, int $limite, int $tamano, bool $negrita = false): string
    {
        $p = '';
        foreach (explode("\n", wordwrap($this->codificar($texto ?: '—'), $limite, "\n", true)) as $linea) {
            $p .= $this->textoCodificado($x, $y, $linea, $tamano, $negrita);
            $y -= 13;
        }
        return $p;
    }

    private function texto(int $x, int $y, string $texto, int $tamano, bool $negrita = false): string
    {
        return $this->textoCodificado($x, $y, $this->codificar($texto), $tamano, $negrita);
    }

    private function codificar(string $texto): string
    {
        $limpio = preg_replace('/\s+/u', ' ', $texto) ?? '';
        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $limpio) ?: '';
    }

    private function textoCodificado(int $x, int $y, string $texto, int $tamano, bool $negrita): string
    {
        $literal = strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $fuente = $negrita ? 'F2' : 'F1';
        return "0 g BT /$fuente $tamano Tf 1 0 0 1 $x $y Tm ($literal) Tj ET\n";
    }
}
