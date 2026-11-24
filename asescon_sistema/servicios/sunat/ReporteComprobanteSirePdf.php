<?php
declare(strict_types=1);

final class ReporteComprobanteSirePdf
{
    private const IZQUIERDA = 36;
    private const DERECHA = 559;
    private const PIE = 51;

    public function crear(array $registro, string $libro, string $tipo, string $numero): string
    {
        $xml = $registro['datos_xml'] ?? [];
        $sire = json_decode((string)($registro['datos_impresion_sire'] ?? ''), true) ?? [];
        $esCompra = $libro === 'RCE';
        $valor = static fn(string $campo): string => trim((string)($registro[$campo] ?? ''));
        $preferir = static fn(string $campo, string $respaldo = ''): string => ($xml[$campo] ?? '') !== '' ? $xml[$campo] : $respaldo;
        $emisor = $preferir('emisor', $valor($esCompra ? 'razon_social_proveedor' : 'empresa_asescon'));
        $receptor = $preferir('receptor', $valor($esCompra ? 'empresa_asescon' : 'razon_social_cliente'));
        $idEmisor = $valor($esCompra ? 'ruc_proveedor' : 'ruc_empresa');
        $idReceptor = $valor($esCompra ? 'ruc_empresa' : 'documento_cliente');
        $nombreComercial = $preferir('nombre_comercial', $esCompra ? '' : $valor('nombre_comercial_empresa'));
        $direccion = $preferir('direccion_emisor', $esCompra ? '' : $valor('domicilio_empresa'));
        $direccionCliente = $preferir('direccion_receptor', $esCompra ? $valor('domicilio_empresa') : '');
        $moneda = strtoupper($preferir('moneda', (string)($sire['moneda'] ?? '')));
        $nombreMoneda = match ($moneda) {
            'PEN' => 'SOL', 'USD' => 'DÓLAR ESTADOUNIDENSE', 'EUR' => 'EURO', default => $moneda,
        };
        // Las propuestas SIRE expresan sus montos en soles. El XML usa la moneda del comprobante.
        $importeSire = $moneda === '' || $moneda === 'PEN' || $xml === [];
        $importes = array_replace(
            $importeSire ? ['gravado' => $valor('base_imponible'), 'igv' => $valor('igv'), 'total' => $valor('importe_total')] : [],
            $importeSire ? ($sire['importes'] ?? []) : [],
            $xml['importes'] ?? []
        );
        $simbolo = $xml === [] ? 'S/' : match ($moneda) {
            'PEN' => 'S/', 'USD' => 'US$', 'EUR' => 'EUR', default => $moneda,
        };
        $fecha = $preferir('fecha', $valor('fecha_emision'));
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

        $encabezado = function (bool $continuacion) use ($nombreComercial, $emisor, $direccion, $nombreTipo, $idEmisor, $numero): array {
            $p = "0 g 0.25 G 0.65 w\n";
            $y = 797;
            foreach ([[$nombreComercial ?: '—', true], [$emisor ?: '—', true], [$direccion, false]] as [$dato, $negrita]) {
                if ($dato === '') continue;
                foreach (array_slice($this->lineas($dato, 292, 10), 0, 4) as $linea) {
                    $p .= $this->texto(self::IZQUIERDA, $y, $linea, 10, $negrita);
                    $y -= 12;
                }
                $y -= 4;
            }
            $p .= "350 747 209 65 re S\n";
            $p .= $this->texto(455, 796, $nombreTipo, 9, true, 'C');
            $p .= $this->texto(455, 777, 'RUC: ' . ($idEmisor ?: '—'), 10, true, 'C');
            $p .= $this->texto(455, 758, $numero ?: '—', 11, true, 'C');
            $y = min(730, $y - 7);
            $p .= $this->regla($y);
            if ($continuacion) {
                $p .= $this->texto(36, $y - 16, 'Continuación', 9, true);
                $y -= 30;
            }
            return [$p, $y];
        };

        [$p, $y] = $encabezado(false);
        $paginas = [];
        $p .= $this->texto(380, $y - 17, 'Forma de Pago:', 9);
        $p .= $this->texto(460, $y - 17, match ($preferir('forma_pago')) {
            'Credito' => 'Crédito', '' => '—', default => $preferir('forma_pago'),
        }, 9);
        $y -= 17;
        foreach ([
            'Fecha de Emisión' => $fecha, 'Señor(es)' => $receptor, 'RUC' => $idReceptor,
            'Dirección del Cliente' => $direccionCliente, 'Tipo de Moneda' => $nombreMoneda,
            'Observaciones' => $preferir('observaciones'),
        ] as $etiqueta => $dato) {
            $p .= $this->texto(36, $y, $etiqueta, 9);
            $p .= $this->texto(140, $y, ':', 9);
            foreach ($this->lineas($dato ?: '—', 230, 9) as $linea) {
                if ($y < 75) {
                    $paginas[] = $p;
                    [$p, $y] = $encabezado(true);
                    $p .= $this->texto(36, $y, $etiqueta . ' (continuación)', 8);
                }
                $p .= $this->texto(150, $y, $linea, 9);
                $y -= 12;
            }
            $y -= 3;
        }

        $y -= 12;
        if ($y < 86) {
            $paginas[] = $p;
            [$p, $y] = $encabezado(true);
        }
        $p .= $this->cabeceraDetalle($y);
        $y -= 30;
        if (empty($xml['items'])) {
            $p .= $this->texto(42, $y, 'Detalle de productos no disponible para esta consulta.', 9);
            $y -= 18;
        }
        foreach ($xml['items'] ?? [] as [$cantidad, $unidad, $codigo, $descripcion, $unitario, $icbper]) {
            $columnas = [
                [91, 48, $cantidad, 'R'], [103, 62, $unidad, 'L'], [170, 61, $codigo, 'L'],
                [236, 176, $descripcion ?: '—', 'L'], [501, 82, $unitario, 'R'], [551, 45, $icbper, 'R'],
            ];
            $lineas = array_map(fn(array $c): array => $this->lineas($c[2], $c[1], 8), $columnas);
            $alto = max(array_map('count', $lineas));
            if ($y < 86) {
                $paginas[] = $p . $this->regla($y + 3);
                [$p, $y] = $encabezado(true);
                $p .= $this->cabeceraDetalle($y);
                $y -= 30;
            }
            for ($i = 0; $i < $alto; $i++) {
                if ($y < 65) {
                    $paginas[] = $p . $this->regla($y + 3);
                    [$p, $y] = $encabezado(true);
                    $p .= $this->cabeceraDetalle($y);
                    $p .= $this->texto(236, $y - 30, 'Continuación del ítem' . ($codigo ? ' ' . substr($codigo, 0, 24) : ''), 8, true);
                    $y -= 44;
                }
                foreach ($columnas as $k => [$x, , , $alineacion]) {
                    if (isset($lineas[$k][$i])) {
                        $p .= $this->texto($x, $y, $lineas[$k][$i], 8, false, $alineacion);
                    }
                }
                $y -= 12;
            }
            $y -= 5;
        }
        $p .= $this->regla($y);
        $y -= 18;
        if ($y < 382) {
            $paginas[] = $p;
            [$p, $y] = $encabezado(true);
        }

        $totalLetras = $preferir('total_letras', $this->totalEnLetras((string)($importes['total'] ?? ''), $xml === [] ? 'PEN' : $moneda));
        $totalLetras = preg_replace('/^SON\s*:\s*/iu', '', $totalLetras) ?? $totalLetras;
        $ly = $y - 20;
        foreach ($this->lineas('SON: ' . ($totalLetras ?: '—'), 225, 10) as $linea) {
            if ($ly < $y - 230) break;
            $p .= $this->texto(36, $ly, $linea, 10, true);
            $ly -= 13;
        }
        $p .= "280 " . ($y - 286) . " 279 286 re S\n";
        foreach ([
            'descuento_gravado' => 'Descuento Global', 'gravado' => 'Total valor venta gravado',
            'inafecto' => 'Total valor venta inafecto', 'exonerado' => 'Total valor venta exonerado',
            'gratuito' => 'Total valor venta gratuito', 'exportacion' => 'Total valor venta exportación',
            'descuento_no_gravado' => 'Descuento Global no afecto', 'descuentos' => 'Total descuentos',
            'tributos' => 'Sumatoria otros tributos', 'cargos' => 'Sumatoria otros cargos',
            'isc' => 'Sumatoria ISC', 'igv' => 'Sumatoria IGV', 'icbper' => 'Sumatoria ICBPER',
            'anticipo' => 'Monto total del anticipo', 'redondeo' => 'Monto de redondeo', 'total' => 'Importe total',
        ] as $campo => $etiqueta) {
            $total = $campo === 'total';
            $monto = $importes[$campo] ?? '';
            $dato = is_numeric($monto) ? trim($simbolo . ' ' . number_format((float)$monto, 2)) : '—';
            $y -= 17;
            $p .= "427 " . ($y - 6) . " 125 15 re S\n";
            $p .= $this->texto(421, $y - 1, $etiqueta . ':', 8, $total, 'R');
            $p .= $this->texto(548, $y - 1, $dato, 9, $total, 'R');
        }

        $p .= "36 43 523 30 re S\n";
        $p .= $this->texto(297, 61, $xml === []
            ? 'Reporte informativo de ASESCON basado en el registro SIRE.'
            : 'Representación del comprobante electrónico a partir del XML vinculado.', 8, false, 'C');
        $p .= $this->texto(297, self::PIE, 'Para verificar el documento, consulta el comprobante electrónico original con tu Clave SOL.', 8, false, 'C');
        $paginas[] = $p;
        return $this->pdf($paginas);
    }

    private function regla(int $y): string
    {
        return "36 $y m 559 $y l S\n";
    }

    private function cabeceraDetalle(int $y): string
    {
        $p = '36 ' . ($y - 20) . " 523 20 re S\n";
        foreach ([66 => 'Cantidad', 128 => 'Unidad Medida', 199 => 'Código', 323 => 'Descripción',
                  458 => 'Valor Unitario', 534 => 'ICBPER'] as $x => $nombre) {
            $p .= $this->texto($x, $y - 13, $nombre, 8, true, 'C');
        }
        return $p;
    }

    private function totalEnLetras(string $monto, string $moneda): string
    {
        if (!is_numeric($monto) || abs((float)$monto) >= 1_000_000_000_000) return '';
        [$entero, $centavos] = explode('.', number_format(abs((float)$monto), 2, '.', ''));
        $nombre = match ($moneda) {
            'PEN' => $entero === '1' ? 'SOL' : 'SOLES',
            'USD' => $entero === '1' ? 'DÓLAR' : 'DÓLARES',
            'EUR' => $entero === '1' ? 'EURO' : 'EUROS',
            default => $moneda,
        };
        $letras = trim($this->numeroEnLetras((int)$entero));
        if ($entero === '0') $letras = 'CERO';
        if ((float)$monto < 0) $letras = 'MENOS ' . $letras;
        return trim("$letras Y $centavos/100 $nombre");
    }

    private function numeroEnLetras(int $n): string
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
            'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO',
            'DIECINUEVE', 'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO',
            'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
        if ($n < 30) return $unidades[$n];
        if ($n < 100) {
            $decenas = [3 => 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
            return $decenas[intdiv($n, 10)] . ($n % 10 ? ' Y ' . $unidades[$n % 10] : '');
        }
        if ($n < 1000) {
            $cientos = [1 => 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
            return $n === 100 ? 'CIEN' : trim($cientos[intdiv($n, 100)] . ' ' . $this->numeroEnLetras($n % 100));
        }
        $escala = $n >= 1_000_000 ? 1_000_000 : 1000;
        $cantidad = intdiv($n, $escala);
        $grupo = $cantidad === 1 ? '' : preg_replace(['/VEINTIUNO$/', '/UNO$/'], ['VEINTIÚN', 'UN'], $this->numeroEnLetras($cantidad)) . ' ';
        $etiqueta = $escala === 1000 ? 'MIL' : ($cantidad === 1 ? 'UN MILLÓN' : 'MILLONES');
        return trim($grupo . $etiqueta . ' ' . $this->numeroEnLetras($n % $escala));
    }

    private function pdf(array $paginas): string
    {
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>', '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $hijos = [];
        foreach ($paginas as $i => $p) {
            $id = count($objetos) + 1;
            $hijos[] = "$id 0 R";
            $contenido = $id + 1;
            $p .= $this->texto(559, 29, ($i + 1) . ' / ' . count($paginas), 8, false, 'R');
            $objetos[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contenido 0 R >>";
            $objetos[] = '<< /Length ' . strlen($p) . " >>\nstream\n" . $p . "endstream";
        }
        $objetos[1] = '<< /Type /Pages /Kids [' . implode(' ', $hijos) . '] /Count ' . count($paginas) . ' >>';
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $xref = "0000000000 65535 f \n";
        foreach ($objetos as $i => $objeto) {
            $xref .= sprintf('%010d 00000 n ', strlen($pdf)) . "\n";
            $pdf .= ($i + 1) . " 0 obj\n$objeto\nendobj\n";
        }
        $inicioXref = strlen($pdf);
        $cantidad = count($objetos) + 1;
        return $pdf . "xref\n0 $cantidad\n$xref" . "trailer << /Size $cantidad /Root 1 0 R >>\nstartxref\n$inicioXref\n%%EOF\n";
    }

    private function lineas(string $texto, int $ancho, int $tamano): array
    {
        $lineas = [];
        $linea = '';
        foreach (explode(' ', $this->codificar($texto)) as $palabra) {
            $candidata = $linea === '' ? $palabra : "$linea $palabra";
            if ($this->ancho($candidata, $tamano) <= $ancho) {
                $linea = $candidata;
                continue;
            }
            if ($linea !== '') $lineas[] = $linea;
            $linea = '';
            foreach (str_split($palabra) as $letra) {
                if ($this->ancho($linea . $letra, $tamano) > $ancho) {
                    $lineas[] = $linea;
                    $linea = '';
                }
                $linea .= $letra;
            }
        }
        $lineas[] = $linea;
        return array_map(static fn(string $linea) => iconv('Windows-1252', 'UTF-8', $linea) ?: $linea, $lineas);
    }

    private function ancho(string $texto, int $tamano, bool $negrita = false): float
    {
        $anchos = 0;
        foreach (str_split($texto) as $c) {
            $anchos += match (true) {
                str_contains(" ilI.,:;'!|", $c) => 290,
                str_contains('MWmw@%', $c) => 900,
                ctype_upper($c), ord($c) >= 128 => 730,
                default => $negrita ? 610 : 560,
            };
        }
        return $tamano * $anchos / 1000;
    }

    private function codificar(string $texto): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $limpio);
        return $convertido === false ? '' : $convertido;
    }

    private function texto(int $x, int $y, string $texto, int $tamano, bool $negrita = false, string $alineacion = 'L'): string
    {
        $texto = $this->codificar($texto);
        if ($alineacion !== 'L') {
            $ancho = $this->ancho($texto, $tamano, $negrita);
            $x -= (int)round($alineacion === 'C' ? $ancho / 2 : $ancho);
        }
        $literal = strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $fuente = $negrita ? 'F2' : 'F1';
        return "0 g BT /$fuente $tamano Tf 1 0 0 1 $x $y Tm ($literal) Tj ET\n";
    }
}
