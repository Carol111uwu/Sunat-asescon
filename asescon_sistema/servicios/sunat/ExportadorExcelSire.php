<?php

declare(strict_types=1);

final class ExportadorExcelSire
{
    public function crear(string $titulo, string $subtitulo, array $cabeceras, array $filas, array $tipos = [], string $nombreHoja = 'SIRE'): string
    {
        if ($cabeceras === []) {
            throw new InvalidArgumentException('El archivo Excel necesita al menos una columna.');
        }
        $partsDir = __DIR__ . '/xlsx/partes_plantilla';
        $fijos = [
            '[Content_Types].xml',
            '_rels/.rels',
            'docProps/app.xml',
            'docProps/core.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/theme/theme1.xml',
        ];
        $entradas = [];
        foreach ($fijos as $nombre) {
            $ruta = $partsDir . '/' . $nombre;
            if (!is_file($ruta) || !is_readable($ruta)) {
                throw new RuntimeException('Falta una parte base del XLSX: ' . $nombre);
            }
$contenido = file_get_contents($ruta);
            if (!is_string($contenido)) {
                throw new RuntimeException('No se pudo leer una parte base del XLSX: ' . $nombre);
            }
$entradas[$nombre] = $contenido;
        }

        $nombreHoja = $this->limpiarNombreHoja($nombreHoja);
        $columnas = count($cabeceras);
        $ultimaCol = $this->columnaExcel($columnas);
        $ultimaFila = max(5, 4 + count($filas));
        $conFiltro = $filas !== [];
        $entradas['xl/workbook.xml'] = $this->workbookXml($nombreHoja, $conFiltro, $ultimaCol, $ultimaFila);
        $entradas['xl/worksheets/sheet1.xml'] = $this->sheetXml($titulo, $subtitulo, $cabeceras, $filas, $tipos, $ultimaCol, $ultimaFila);
        $this->validarXml('xl/workbook.xml', $entradas['xl/workbook.xml']);
        $this->validarXml('xl/worksheets/sheet1.xml', $entradas['xl/worksheets/sheet1.xml']);
        $bytes = $this->crearZipStore($entradas);
        if (strlen($bytes) < 4 || substr($bytes, 0, 2) !== 'PK') {
            throw new RuntimeException('No se pudo construir el contenedor XLSX.');
        }
        $this->validarBytes($bytes, array_keys($entradas));
        return $bytes;
    }

    private function crearZipStore(array $entradas): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $cantidad = 0;
        foreach ($entradas as $nombre => $contenido) {
            $nombre = str_replace('\\', '/', $nombre);
            $crc = crc32($contenido);
            if ($crc < 0) {
                $crc += 4294967296;
            }
$tam = strlen($contenido);
            $nl = strlen($nombre);
            $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $tam, $tam, $nl, 0) . $nombre;
            $local .= $header . $contenido;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $tam, $tam, $nl, 0, 0, 0, 0, 0, $offset) . $nombre;
            $offset += strlen($header) + $tam;
            $cantidad++;
        }
        $cdOffset = strlen($local);
        $cdSize = strlen($central);
        $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $cantidad, $cantidad, $cdSize, $cdOffset, 0);
        return $local . $central . $eocd;
    }

    private function validarBytes(string $bytes, array $obligatorios): void
    {
        foreach ($obligatorios as $nombre) {
            if (strpos($bytes, $nombre) === false) {
                throw new RuntimeException('Falta una parte obligatoria del XLSX: ' . $nombre);
            }
        }
    }

    private function workbookXml(string $nombreHoja, bool $conFiltro, string $ultimaCol, int $ultimaFila): string
    {
        $nombreXml = $this->x($nombreHoja);
        $definedNames = '';
        if ($conFiltro) {
            $nombreFormula = str_replace("'", "''", $nombreHoja);
            $formula = "'{$nombreFormula}'!\$A\$4:\${$ultimaCol}\${$ultimaFila}";
            $definedNames = '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">' . $this->x($formula) . '</definedName></definedNames>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView visibility="visible" minimized="0" showHorizontalScroll="1" showVerticalScroll="1" showSheetTabs="1" tabRatio="600" firstSheet="0" activeTab="0"/></bookViews>'
            . '<sheets><sheet name="' . $nombreXml . '" sheetId="1" r:id="rId1"/></sheets>' . $definedNames . '<calcPr calcId="124519" fullCalcOnLoad="1"/></workbook>';
    }

    private function sheetXml(string $titulo, string $subtitulo, array $cabeceras, array $filas, array $tipos, string $ultimaCol, int $ultimaFila): string
    {
        $n = count($cabeceras);$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><outlinePr summaryBelow="1" summaryRight="1"/></sheetPr><dimension ref="A1:' . $ultimaCol . $ultimaFila . '"/><sheetViews><sheetView showGridLines="1" workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A5" sqref="A5"/></sheetView></sheetViews><sheetFormatPr baseColWidth="8" defaultRowHeight="15"/><cols>';
        for ($i = 1; $i <= $n; $i++) {
            $tipo = $tipos[$i - 1] ?? 'text';
            $ancho = match ($tipo) {
                'money'=>16,'int'=>14,'date'=>14,default=>$i === 1 ? 20 : 24
            };
            $xml .= '<col width="' . $ancho . '" customWidth="1" min="' . $i . '" max="' . $i . '"/>';
        }
        $xml .= '</cols><sheetData><row r="1" ht="28" customHeight="1">' . $this->celdaTexto('A1', $titulo, 1) . '</row><row r="2" ht="22" customHeight="1">' . $this->celdaTexto('A2', $subtitulo, 2) . '</row><row r="3" ht="8" customHeight="1"></row><row r="4" ht="26" customHeight="1">';
        foreach ($cabeceras as $i => $cabecera) {
            $xml .= $this->celdaTexto($this->columnaExcel($i + 1) . '4', (string)$cabecera, 3);
        }
$xml .= '</row>';
        $r = 5;
        foreach ($filas as $fila) {
            $xml .= '<row r="' . $r . '">';for ($i = 0; $i < $n; $i++) {
                $coord = $this->columnaExcel($i + 1) . $r;
                $tipo = $tipos[$i] ?? 'text';
                $valor = $fila[$i] ?? null;
                if ($tipo === 'money') {
                    $xml .= $this->celdaNumero($coord, (float)$valor, 6);
                } elseif ($tipo === 'int') {
                    $xml .= $this->celdaNumero($coord, (int)$valor, 5);
                } elseif ($tipo === 'date') {
                    $xml .= $this->celdaTexto($coord, $this->fechaVisible((string)$valor), 7);
                } else {
                    $xml .= $this->celdaTexto($coord, (string)$valor, 4);
                }
            } $xml .= '</row>';
            $r++;
        }
        if ($filas === []) {
            $xml .= '<row r="5">' . $this->celdaTexto('A5', 'Sin registros para la selección actual.', 4) . '</row>';
        }
$xml .= '</sheetData>';
        if ($filas !== []) {
            $xml .= '<autoFilter ref="A4:' . $ultimaCol . $ultimaFila . '"/>';
        }
$xml .= '<mergeCells count="2"><mergeCell ref="A2:' . $ultimaCol . '2"/><mergeCell ref="A1:' . $ultimaCol . '1"/></mergeCells><pageMargins left="0.75" right="0.75" top="1" bottom="1" header="0.5" footer="0.5"/></worksheet>';
        return $xml;
    }

    private function validarXml(string $nombre, string $contenido): void
    {
        if (!class_exists('DOMDocument')) {
            return;
        }
$prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument('1.0', 'UTF-8');
        $ok = $dom->loadXML($contenido, LIBXML_NONET | LIBXML_NOBLANKS);
        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            $detalle = $errores !== [] ? trim((string)$errores[0]->message) : 'XML no válido';
            throw new RuntimeException('No se pudo construir ' . $nombre . ': ' . $detalle);
        }
    }
    private function celdaTexto(string $coord, string $valor, int $estilo): string
    {
        $preserve = preg_match('/^\s|\s$/u', $valor) ? ' xml:space="preserve"' : '';
        return '<c r="' . $coord . '" s="' . $estilo . '" t="inlineStr"><is><t' . $preserve . '>' . $this->x($valor) . '</t></is></c>';
    }
    private function celdaNumero(string $coord, int|float $valor, int $estilo): string
    {
        if (is_float($valor) && !is_finite($valor)) {
            $valor = 0.0;
        }
$n = is_float($valor) ? rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.') : (string)$valor;
        return '<c r="' . $coord . '" s="' . $estilo . '" t="n"><v>' . $n . '</v></c>';
    }
    private function x(string $valor): string
    {
        if (!preg_match('//u', $valor)) {
            if (function_exists('iconv')) {
                $c = @iconv('UTF-8', 'UTF-8//IGNORE', $valor);
                if (is_string($c)) {
                    $valor = $c;
                }
            } elseif (function_exists('mb_convert_encoding')) {
                $valor = mb_convert_encoding($valor, 'UTF-8', 'UTF-8');
            }
        }
$valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u', '', $valor) ?? $valor;
        return htmlspecialchars($valor, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
    private function columnaExcel(int $numero): string
    {
        $out = '';
        while ($numero > 0) {
            $numero--;
            $out = chr(65 + ($numero % 26)) . $out;
            $numero = intdiv($numero, 26);
        }
return $out;
    }
    private function limpiarNombreHoja(string $nombre): string
    {
        $nombre = str_replace(['\\','/','?','*','[',']',':'], ' ', trim($nombre));
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre);
        if ($nombre === '') {
            $nombre = 'SIRE';
        }
return function_exists('mb_substr') ? mb_substr($nombre, 0, 31, 'UTF-8') : substr($nombre, 0, 31);
    }
    private function fechaVisible(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }
$d = DateTimeImmutable::createFromFormat('Y-m-d', substr($fecha, 0, 10));
        return $d instanceof DateTimeImmutable ? $d->format('d/m/Y') : $fecha;
    }
}
