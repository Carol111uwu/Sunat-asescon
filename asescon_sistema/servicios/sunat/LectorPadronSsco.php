<?php
declare(strict_types=1);
require_once __DIR__.'/../archivos/LectorHojaXlsx.php';

final class LectorPadronSsco
{
    public const FUENTE = 'https://www.sunat.gob.pe/padronesnotificaciones/sujeSinCapacidadOperativa.html';
    public const ARCHIVO_OFICIAL = 'https://www.sunat.gob.pe/padronesnotificaciones/ssco/sujesincapacidadOperativa.xlsx';
    public const CAMPOS = ['ruc','razon_social','resolucion','fecha_firme','fecha_publicacion','domicilio_fiscal','representante_legal'];

    public function descargar(): array
    {
        if (!function_exists('curl_init')) { throw new DomainException('El servidor necesita cURL para actualizar desde SUNAT. Puedes importar un CSV.'); }
        $c = curl_init(self::ARCHIVO_OFICIAL);
        $contenido = '';
        curl_setopt_array($c, [CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'ASESCON/1.0 Consulta de padron publico',
            CURLOPT_WRITEFUNCTION=>static function ($recurso, string $parte) use (&$contenido): int {
                if (strlen($contenido) + strlen($parte) > 8 * 1024 * 1024) { return 0; }
                $contenido .= $parte; return strlen($parte);
            }]);
        $ok = curl_exec($c); $codigo = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        if (!$ok || $codigo !== 200) { throw new DomainException('SUNAT no respondió con un padrón válido. Se conserva la lista anterior; puedes usar la importación CSV.'); }
        if(!str_starts_with($contenido,'PK'))throw new DomainException('SUNAT no devolvió su archivo XLSX. Se conserva la lista anterior.');
        return $this->desdeBytesXlsx($contenido);
    }

    public function desdeXlsx(string $ruta):array
    {
        if(!is_file($ruta)||filesize($ruta)>8*1024*1024)throw new DomainException('El XLSX debe pesar como máximo 8 MB.');
        return $this->desdeBytesXlsx((string)file_get_contents($ruta));
    }
    public function desdeBytesXlsx(string $contenido):array
    {
        $matriz=(new LectorHojaXlsx())->leer($contenido);
        foreach($matriz as $i=>$fila){
            $mapa=$this->cabeceras($fila);
            if(isset($mapa['ruc'],$mapa['razon_social'],$mapa['resolucion']))return $this->registros(array_slice($matriz,$i+1),$mapa);
        }
        throw new DomainException('El XLSX no contiene las columnas del padrón SSCO. Se conserva la lista anterior.');
    }

    public function desdeHtml(string $html): array
    {
        preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $html, $tablas);
        foreach ($tablas[1] as $tabla) {
            preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tabla, $filas);
            $matriz = [];
            foreach ($filas[1] as $fila) {
                preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $fila, $celdas);
                $matriz[] = array_map(static fn(string $v): string => trim(preg_replace('/\s+/u',' ',
                    html_entity_decode(strip_tags(preg_replace('/<br\s*\/?\s*>/i',' ', $v)), ENT_QUOTES | ENT_HTML5,'UTF-8')) ?? ''), $celdas[1]);
            }
            foreach ($matriz as $i=>$fila) {
                $mapa = $this->cabeceras($fila);
                if (isset($mapa['ruc'],$mapa['razon_social'],$mapa['resolucion'])) {
                    return $this->registros(array_slice($matriz,$i+1),$mapa);
                }
            }
        }
        throw new DomainException('La página de SUNAT cambió de formato o no contiene la tabla SSCO. Se conserva la lista anterior.');
    }

    public function desdeCsv(string $ruta): array
    {
        if (!is_file($ruta) || filesize($ruta) > 8 * 1024 * 1024) { throw new DomainException('El CSV debe pesar como máximo 8 MB.'); }
        $f = fopen($ruta,'rb');
        if (!$f) { throw new DomainException('No se pudo leer el CSV.'); }
        try {
            $primera = fgets($f);
            if ($primera === false) { throw new DomainException('El archivo está vacío.'); }
            $primera = preg_replace('/^\xEF\xBB\xBF/', '', $primera);
            $delimitador = substr_count($primera,';') > substr_count($primera,',') ? ';' : ',';
            $mapa = $this->cabeceras(str_getcsv($primera,$delimitador,'"',''));
            if (!isset($mapa['ruc'],$mapa['razon_social'],$mapa['resolucion'])) { throw new DomainException('El CSV necesita las columnas ruc, razon_social y resolucion. Descarga la plantilla.'); }
            $filas = [];
            while (($fila=fgetcsv($f,0,$delimitador,'"','')) !== false) {
                if (count($filas) >= 20000) { throw new DomainException('La carga supera los 20 000 registros.'); }
                if ($fila === [null]) { continue; }
                $filas[] = $fila;
            }
            return $this->registros($filas,$mapa);
        } finally { fclose($f); }
    }

    private function cabeceras(array $fila): array
    {
        $mapa = [];
        foreach ($fila as $i=>$nombre) {
            $n = strtolower(strtr(trim((string)$nombre),['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));
            $n = preg_replace('/[_\s]+/u',' ', $n);
            $campo = match (true) {
                str_contains($n,'representante legal') && !str_contains($n,'ruc') && !str_contains($n,'documento') => 'representante_legal',
                $n==='ruc' || (str_starts_with($n,'ruc o documento')&&!str_contains($n,'representante')) => 'ruc',
                $n==='razon social' || (str_contains($n,'apellidos y nombres')&&!str_contains($n,'representante')) => 'razon_social',
                $n==='resolucion' || str_starts_with($n,'resolucion de') => 'resolucion',
                $n==='fecha firme' || str_contains($n,'quedo firme') => 'fecha_firme',
                $n==='fecha publicacion' || str_contains($n,'fecha de publicacion') => 'fecha_publicacion',
                str_contains($n,'domicilio fiscal') => 'domicilio_fiscal',
                str_contains($n,'representante legal') => 'representante_legal',
                default => null
            };
            if ($campo !== null) { $mapa[$campo]=$i; }
        }
        return $mapa;
    }

    private function registros(array $filas, array $mapa): array
    {
        $salida = [];
        foreach ($filas as $i=>$fila) {
            if (!$fila || (count($fila)===1 && trim((string)$fila[0])==='')) { continue; }
            $d = [];
            foreach (self::CAMPOS as $campo) { $d[$campo]=isset($mapa[$campo]) ? trim((string)($fila[$mapa[$campo]]??'')) : ''; }
            if (!preg_match('/^\d{11}$/D',$d['ruc']) || $d['razon_social']==='' || $d['resolucion']==='') {
                throw new DomainException('Fila ' . ($i+2) . ': revisa el RUC, razón social y resolución. La carga no se guardó.');
            }
            foreach (['razon_social'=>250,'resolucion'=>250,'domicilio_fiscal'=>500,'representante_legal'=>250] as $campo=>$limite) {
                if (!preg_match('//u',$d[$campo]) || preg_match_all('/./us',$d[$campo]) > $limite) {
                    throw new DomainException('Fila ' . ($i+2) . ': usa texto UTF-8 dentro del tamaño permitido.');
                }
            }
            foreach (['fecha_firme','fecha_publicacion'] as $campo) { $d[$campo]=$this->fecha($d[$campo]); }
            $clave=$d['ruc'].'|'.$d['resolucion'];
            if (isset($salida[$clave]) && $salida[$clave]!==$d) { throw new DomainException('Hay registros contradictorios para el mismo RUC y resolución.'); }
            $salida[$clave]=$d;
        }
        if (!$salida) { throw new DomainException('No se encontraron registros SSCO. Se conserva el padrón anterior.'); }
        return array_values($salida);
    }

    private function fecha(string $valor): ?string
    {
        if ($valor==='') { return null; }
        foreach (['Y-m-d','d/m/Y','d-m-Y'] as $formato) {
            $fecha=DateTimeImmutable::createFromFormat('!'.$formato,$valor);
            if ($fecha && $fecha->format($formato)===$valor) { return $fecha->format('Y-m-d'); }
        }
        throw new DomainException('Hay una fecha inválida. Usa AAAA-MM-DD o DD/MM/AAAA.');
    }
}
