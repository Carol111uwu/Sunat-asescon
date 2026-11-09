<?php
declare(strict_types=1);
require_once __DIR__.'/PeriodosSire.php';

                                                                                   
final class ServicioImportacionDetraccionesSpot
{
    private const ALIAS = [
        'numero_constancia'=>['numero constancia','nro constancia','nro de constancia','num constancia','num de constancia','numero de constancia','n constancia','constancia de deposito','constancia'],
        'fecha_deposito'=>['fecha pago','fecha de pago','fecha deposito','fecha de deposito','fec deposito','fec pago'],
        'monto'=>['monto deposito','monto de deposito','importe deposito','importe de deposito','importe depositado','monto depositado','importe de detraccion','monto de detraccion','monto detraccion','monto','importe'],
        'periodo'=>['periodo tributario','periodo de tributacion','periodo','periodo fiscal'],
        'ruc_titular'=>['ruc titular','ruc del titular','ruc cuenta detracciones','ruc de cuenta','ruc del contribuyente'],
        'ruc_proveedor'=>['ruc proveedor','ruc del proveedor','ruc vendedor','ruc del vendedor'],
        'ruc_adquirente'=>['ruc adquirente','ruc del adquirente','ruc comprador','ruc del comprador'],
        'tipo_cuenta'=>['tipo cuenta','tipo de cuenta'],
        'numero_cuenta'=>['numero cuenta','numero de cuenta','nro cuenta','nro de cuenta','cuenta detracciones'],
        'documento'=>['comprobante de pago','comprobante','numero comprobante','nro comprobante','numero cpe','documento'],
        'contraparte'=>['razon social proveedor','razon social adquirente','razon social comprador','razon social vendedor','razon social depositante','proveedor','adquirente'],
        'modalidad'=>['modalidad de pago','modalidad','tipo pago','tipo de pago','origen'],
    ];

    private static function normalizar(string $texto): string
    {
        if (function_exists('mb_convert_encoding')) $texto = mb_convert_encoding($texto,'UTF-8','UTF-8,Windows-1252,ISO-8859-1');
        $texto = strtolower(strtr($texto, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n']));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '');
    }

                                               
    private static function leerCsv(string $ruta): array
    {
        $muestra = (string)file_get_contents($ruta, false, null, 0, 8192);
        $lineas = preg_split('/\r\n|\n|\r/', $muestra) ?: [];
        $mejor = ';'; $max = -1;
        foreach (["\t", ';', '|', ','] as $sep) {
                                                                       
                                                                                
            $puntaje = 0;
            foreach (array_slice($lineas,0,15) as $linea) {
                $celdas = array_map([self::class,'normalizar'],str_getcsv($linea,$sep));
                $coincidencias=0;
                foreach (['numero_constancia','fecha_deposito','monto'] as $campo) {
                    if (count(array_intersect($celdas,self::ALIAS[$campo]))>0) $coincidencias++;
                }
                if ($coincidencias===3) $puntaje=max($puntaje,100+count($celdas));
                else $puntaje=max($puntaje,$coincidencias*20+count($celdas));
            }
            if ($puntaje > $max) { $max = $puntaje; $mejor = $sep; }
        }
        if ($max <= 1) throw new DomainException('El archivo no tiene columnas delimitadas. Descárgalo de SUNAT y expórtalo a CSV.');
        $f = fopen($ruta, 'rb'); if (!$f) throw new RuntimeException('No se pudo abrir el archivo.');
        $filas = [];
        try {
            while (($fila = fgetcsv($f, 0, $mejor, '"', '\\')) !== false) {
                $filas[] = array_map(static fn($x)=>trim((string)$x), $fila);
                if (count($filas) > 5100) throw new DomainException('El archivo supera el máximo de 5 000 filas. Divide el archivo y vuelve a importarlo.');
            }
        } finally { fclose($f); }
        return $filas;
    }

                                                                                          
    private static function leerXlsx(string $ruta): array
    {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
            throw new RuntimeException('Este servidor no tiene ZIP/XML para leer XLSX. Exporta el archivo como CSV.');
        }
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) throw new DomainException('El archivo XLSX está dañado.');
        try {
            $hoja = $zip->statName('xl/worksheets/sheet1.xml');
            if (!$hoja || $hoja['size'] > 25*1024*1024) throw new DomainException('XLSX sin hoja 1 o demasiado grande.');
            $cadenas = []; $info = $zip->statName('xl/sharedStrings.xml');
            if ($info && $info['size'] <= 12*1024*1024) {
                $xml = simplexml_load_string((string)$zip->getFromName('xl/sharedStrings.xml'), 'SimpleXMLElement', LIBXML_NONET);
                if ($xml === false) throw new DomainException('Las cadenas XLSX no se pueden interpretar.');
                foreach ($xml->si as $si) {
                    $valor = (string)$si->t;
                    foreach ($si->r as $r) $valor .= (string)$r->t;
                    $cadenas[] = $valor;
                }
            }
            $xml = simplexml_load_string((string)$zip->getFromName('xl/worksheets/sheet1.xml'), 'SimpleXMLElement', LIBXML_NONET);
            if ($xml === false) throw new DomainException('La hoja XLSX no se puede interpretar.');
            $salida = [];
            foreach ($xml->sheetData->row as $fila) {
                $columnas = [];
                foreach ($fila->c as $c) {
                    if (!preg_match('/^[A-Z]+/', (string)$c['r'], $m)) continue;
                    $indice=0;
                    foreach(str_split($m[0]) as $letra) $indice=$indice*26+ord($letra)-64;
                    if ($indice > 80) throw new DomainException('Demasiadas columnas en el archivo XLSX.');
                    $valor = (string)$c->v;
                    if ((string)$c['t'] === 's') $valor = $cadenas[(int)$valor] ?? '';
                    elseif ((string)$c['t'] === 'inlineStr') $valor = (string)$c->is->t;
                    $columnas[$indice-1] = trim($valor);
                }
                if ($columnas) {
                    $ultima = max(array_keys($columnas));
                    $salida[] = array_replace(array_fill(0,$ultima+1,''),$columnas);
                }
                if (count($salida) > 5100) throw new DomainException('Máximo 5 000 filas por archivo.');
            }
            return $salida;
        } finally { $zip->close(); }
    }

    private static function fecha(string $texto): string
    {
                                                                                            
        if (preg_match('/^\d{5}(?:\.\d+)?$/',$texto) && (float)$texto<90000) {
            $dias=(int)floor((float)$texto);
            $texto=(new DateTimeImmutable('1899-12-30',new DateTimeZone('America/Lima')))->modify("+{$dias} days")->format('Y-m-d');
        }
        foreach (['!d/m/Y','!Y-m-d','!d-m-Y','!d/m/Y H:i:s','!d/m/Y H:i'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat($formato, trim($texto), new DateTimeZone('America/Lima'));
            if ($fecha && $fecha->format($formato[0]==='!'?substr($formato,1):$formato) === trim($texto)
                && $fecha->format('Y-m-d') <= PeriodosSire::ahoraPeru()->format('Y-m-d')) return $fecha->format('Y-m-d');
        }
        throw new DomainException('Fecha de depósito inválida o futura: '.substr($texto,0,30));
    }

    private static function monto(string $texto): string
    {
        $n = preg_replace('/(?:S\/|PEN|\s)/iu','',trim($texto)) ?? '';
        if (str_contains($n,',') && str_contains($n,'.')) {
            $n = strrpos($n,',') > strrpos($n,'.') ? str_replace(',','.',str_replace('.','',$n)) : str_replace(',','',$n);
        } elseif (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/',$n)) $n=str_replace(',','',$n);
        elseif (str_contains($n,',')) $n=str_replace(',','.',$n);
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D',$n) || (float)$n<=0) throw new DomainException('Monto inválido: '.substr($texto,0,30));
        return number_format((float)$n,2,'.','');
    }

    public function analizar(string $ruta, string $nombre, string $ruc, string $periodoUsuario): array
    {
        if (!preg_match('/\.(csv|txt|xlsx)$/i',$nombre,$m)) throw new DomainException('Formatos admitidos: CSV, TXT delimitado y XLSX. No se ejecutan macros.');
        $filas = strtolower($m[1])==='xlsx' ? self::leerXlsx($ruta) : self::leerCsv($ruta);
        $posiciones = null; $inicio = 0;
        foreach (array_slice($filas,0,15) as $i=>$fila) {
            $cabeceras = array_map([self::class,'normalizar'],$fila);
            $halladas = [];
            foreach (self::ALIAS as $campo=>$opciones) foreach ($cabeceras as $j=>$cabecera) {
                if (in_array($cabecera,$opciones,true) && !isset($halladas[$campo])) $halladas[$campo]=$j;
            }
            if (isset($halladas['numero_constancia'],$halladas['fecha_deposito'],$halladas['monto'])) {
                $posiciones=$halladas; $inicio=$i+1; break;
            }
        }
        if ($posiciones===null) throw new DomainException('Faltan columnas identificables: constancia, fecha de depósito y monto. Comprueba las cabeceras de la exportación SUNAT.');
        $leer=static fn(array $f,string $k)=>trim((string)($f[$posiciones[$k] ?? -1] ?? ''));
        $resultado=[]; $vistos=[];
        foreach (array_slice($filas,$inicio) as $i=>$fila) {
            if (!array_filter($fila,static fn($v)=>trim((string)$v)!=='')) continue;
            $constancia=$leer($fila,'numero_constancia');
            if ($constancia==='' || strlen($constancia)>100 || preg_match('/^[=+@-]/',$constancia)) throw new DomainException('Fila '.($inicio+$i+1).': número de constancia no válido.');
            $fecha=self::fecha($leer($fila,'fecha_deposito'));
            $monto=self::monto($leer($fila,'monto'));
            $textoPeriodo=preg_replace('/\D/','',$leer($fila,'periodo')) ?? '';
            $periodo=$textoPeriodo!=='' ? $textoPeriodo : $periodoUsuario;
            if (!PeriodosSire::permitido($periodo,false)) throw new DomainException('Fila '.($inicio+$i+1).': período inválido. Si el archivo no incluye período, selecciona un mes concreto.');
            foreach (['ruc_titular','ruc_proveedor','ruc_adquirente'] as $campo) {
                if ($campo==='ruc_titular' && $leer($fila,$campo)!=='' && preg_replace('/\D/','',$leer($fila,$campo))!==$ruc) {
                    throw new DomainException('El RUC titular del archivo no coincide con la empresa seleccionada.');
                }
            }
            $clave=$constancia.'|'.$fecha.'|'.$monto;
            if (isset($vistos[$clave])) continue;
            $vistos[$clave]=true;
            $cabeceras=$filas[$inicio-1];
            $valores=array_slice(array_pad($fila,count($cabeceras),''),0,count($cabeceras));
            $original=[];
            foreach($cabeceras as $indice=>$cabecera) {
                $contenido=(string)($valores[$indice]??'');
                if (function_exists('mb_convert_encoding')) $contenido=mb_convert_encoding($contenido,'UTF-8','UTF-8,Windows-1252,ISO-8859-1');
                $original[(string)$cabecera]=$contenido;
            }
            $resultado[]=[
                'periodo'=>$periodo,'numero_constancia'=>$constancia,'fecha_deposito'=>$fecha,'monto'=>$monto,
                'tipo_cuenta'=>substr($leer($fila,'tipo_cuenta'),0,80),
                'numero_cuenta'=>substr($leer($fila,'numero_cuenta'),0,100),
                'documento'=>substr($leer($fila,'documento'),0,160),
                'contraparte'=>substr($leer($fila,'contraparte'),0,220),
                'modalidad'=>substr($leer($fila,'modalidad'),0,40),
                'datos_origen'=>$original,
            ];
        }
        if (!$resultado) throw new DomainException('El archivo no contiene depósitos para importar. No se registró un mes vacío como depósito confirmado.');
        return $resultado;
    }
}
