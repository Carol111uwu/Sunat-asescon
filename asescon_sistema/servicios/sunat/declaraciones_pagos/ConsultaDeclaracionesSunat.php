<?php
declare(strict_types=1);
require_once __DIR__.'/TransporteConsultaSol.php';

                                                                                             
final class ConsultaDeclaracionesSunat
{
    private const BASE='https://e-plataformaunica.sunat.gob.pe/v1/recaudacion/tributaria/';
    public function __construct(private ContratoConsultaSol $http, private string $token, private string $ruc) {}

    public static function validarFechas(string $inicio, string $fin): void
    {
        $a=DateTimeImmutable::createFromFormat('!Y-m-d', $inicio);
        $b=DateTimeImmutable::createFromFormat('!Y-m-d', $fin);
        $hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->setTime(23,59,59);
        if (!$a || !$b || $a->format('Y-m-d')!==$inicio || $b->format('Y-m-d')!==$fin ||
            $inicio<'2021-01-01' || $b>$hoy || $b<$a || $b>$a->modify('+6 months')) {
            throw new DomainException('Consulta fechas entre 2021 y hoy (Perú), en orden y en un rango máximo de seis meses.');
        }
    }

    private function pedir(string $ruta, array $parametros=[]): array
    {
        if ($parametros) {
            $pares=[]; foreach ($parametros as $k=>$v) foreach (is_array($v)?$v:[$v] as $valor) $pares[]=rawurlencode($k).'='.rawurlencode((string)$valor);
            $ruta.='?'.implode('&',$pares);
        }
        $r=$this->http->solicitar(self::BASE.$ruta, null, ['Accept: application/json,application/pdf,application/zip,*/*',
            'IdCache: '.$this->token, 'IdFormulario: *MENU*', 'Lang: es-PE']);
        if ($r['status']!==200) {
            throw new DomainException(in_array($r['status'], [401,403], true)
                ? 'La sesión de consulta SUNAT venció o no tiene acceso. Vuelve a consultar; los datos anteriores se conservan.'
                : 'SUNAT no completó la consulta (HTTP '.(int)$r['status'].'). Se conserva el historial anterior.');
        }
        return $r;
    }

    private function json(string $ruta): array
    {
        $r=$this->pedir('consultadeclaracion/t/internet/declaracion/'.$ruta);
        try { $d=json_decode($r['body'], true, 64, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new DomainException('SUNAT devolvió un formato desconocido. No se reemplazó el historial.'); }
        if (!is_array($d) || isset($d['codigo']) || isset($d['error'])) throw new DomainException('SUNAT no devolvió datos de consulta reconocibles.');
        return $d;
    }

    public function consultar(string $inicio, string $fin): array
    {
        self::validarFechas($inicio,$fin);
        $catalogo=$this->json('factoriaFormulario/'); $codigos=[];
        foreach ($catalogo as $f) if (is_array($f) && preg_match('/^\d{4}$/D', (string)($f['codFor']??''))) $codigos[]=$f['codFor'];
        $codigos=array_values(array_unique($codigos));
        if (!$codigos) throw new DomainException('SUNAT no publicó formularios consultables para esta empresa.');
        $partes=[implode(',',$codigos),str_replace('-','',$inicio),str_replace('-','',$fin),'00','AÑO','00','AÑO','true','false'];
        $filas=$this->json('factoriaConsulta/'.implode('/',array_map('rawurlencode',$partes)));
        $filas=self::validarFilas($filas,$this->ruc,$inicio,$fin);
        return ['filas'=>$filas,'formularios'=>$codigos,'inicio'=>$inicio,'fin'=>$fin,'consultado_en'=>date(DATE_ATOM)];
    }

    public static function validarFilas(array $filas,string $ruc,string $inicio,string $fin): array
    {
        if (!array_is_list($filas) || count($filas)>2000) throw new DomainException('La consulta necesita un rango de fechas menor.');
        $vistas=[]; $resultado=[];
        foreach ($filas as $r) {
            if (!is_array($r) || !empty($r['cantidad'])) throw new DomainException('SUNAT indica resultados incompletos. Reduce el rango de fechas.');
            if ((string)($r['numRuc']??'')!==$ruc || !preg_match('/^\d{4}$/D',(string)($r['codFor']??''))
                || !preg_match('/^\d{1,30}$/D',(string)($r['numOrd']??''))
                || !preg_match('/^\d{4}(?:00|0[1-9]|1[0-2])$/D',(string)($r['perTri']??''))
                || !is_numeric($r['mtoPag']??null)) throw new DomainException('La respuesta no identifica inequívocamente las declaraciones del RUC seleccionado.');
            $fecha=DateTimeImmutable::createFromFormat('!d/m/Y',(string)($r['strFecPres']??''));
            if (!$fecha || $fecha->format('d/m/Y')!==$r['strFecPres'] || $fecha->format('Y-m-d')<$inicio || $fecha->format('Y-m-d')>$fin) {
                throw new DomainException('SUNAT devolvió una fecha fuera del rango consultado.');
            }
            $clave=$r['codFor'].':'.$r['numOrd'];
            if (isset($vistas[$clave])) {
                if ($vistas[$clave]!==$r) throw new DomainException('SUNAT devolvió versiones contradictorias de una misma orden.');
                continue;
            }
            $vistas[$clave]=$r; $resultado[]=$r;
        }
        return $resultado;
    }

    public function detalle(string $orden): array
    {
        if (!preg_match('/^\d{1,30}$/D',$orden)) throw new DomainException('Número de orden inválido.');
        $constancia=$this->json('factoriaConstanciaFormulario/'.$orden);
        if (!isset($constancia['tributos']) || !is_array($constancia['tributos'])) throw new DomainException('SUNAT no devolvió un detalle de tributos reconocible para este formulario.');
        foreach ($constancia['tributos'] as $t) {
            if (!is_array($t) || (isset($t['numOrd']) && (string)$t['numOrd']!==$orden)
                || !is_numeric($t['mtoTotDeu']??null) || !is_numeric($t['mtoResPag']??null)) throw new DomainException('El detalle de SUNAT no corresponde a la orden consultada.');
        }
        $casillas=$this->json('factoriaDetalleFormulario/'.$orden);
        foreach ($casillas as $k=>$v) if (!preg_match('/^C\d+$/D',(string)$k) || (!is_scalar($v) && $v!==null)) throw new DomainException('El formato de casillas cambió. Se conserva el detalle anterior.');
        return ['constancia'=>$constancia,'casillas'=>$casillas,'consultado_en'=>date(DATE_ATOM)];
    }

    public function documento(array $fila,string $tipo): array
    {
        $orden=(string)($fila['numOrd']??'');
        if ((string)($fila['numRuc']??'')!==$this->ruc || !preg_match('/^\d{1,30}$/D',$orden)) throw new DomainException('El documento no pertenece al RUC seleccionado.');
        $opciones=['constancia'=>['factoriaGenerarReporteConstancia/','ecmFormulario','pdf'],
            'casillas'=>['factoriaGenerarReporteDetallepdf/','ecmCasillas','pdf'],
            'anexos'=>['factoriaDescargarAnexasFormulario/',null,'zip']];
        [$ruta,$campo,$extension]=$opciones[$tipo]??throw new DomainException('Documento no válido.');
        $p=['numeroOrden'=>$orden];
        if ($campo!==null) {
            $ecm=(string)($fila[$campo]??'');
            if (!preg_match('/^\{[A-Fa-f0-9-]{36}\}$/D',$ecm)) throw new DomainException('SUNAT no informó un archivo de este tipo para la orden.');
            $p['codigoEcm']=$ecm;
        }
        return self::validarArchivo($this->pedir('consulta/t/visorconstancia/'.$ruta,$p),$extension,$tipo.'_'.$fila['codFor'].'_'.$orden.'.'.$extension);
    }

    public function reporte(array $consulta,string $tipo): array
    {
        self::validarFechas($consulta['inicio'],$consulta['fin']);
        if (!in_array($tipo,['pdf','xls'],true)) throw new DomainException('Formato de reporte inválido.');
        $p=['numeroFormularios'=>$consulta['formularios'],
            'rangoDeFechaPresentacionInicio'=>str_replace('-','',$consulta['inicio']), 'rangoDeFechaPresentacionFin'=>str_replace('-','',$consulta['fin']),
            'rangoPeriodoTributarioInicioMes'=>'00','rangoPeriodoTributarioInicioAnio'=>'AÑO','rangoPeriodoTributarioFinMes'=>'00','rangoPeriodoTributarioFinAnio'=>'AÑO',
            'consideraFechaPresentacion'=>'true','consideraPeriodoTributario'=>'false','idioma'=>'es-PE'];
        $ruta=$tipo==='pdf'?'factoriaGenerarReporteGeneral/':'factoriaGenerarReporteGeneralExcel/';
        return self::validarArchivo($this->pedir('consulta/t/visorconstancia/'.$ruta,$p),$tipo,'declaraciones_pagos.'.$tipo);
    }

    public static function validarArchivo(array $respuesta,string $extension,string $nombre): array
    {
        $b=(string)$respuesta['body'];
        $valido=match($extension){'pdf'=>str_starts_with($b,'%PDF-'),'zip'=>str_starts_with($b,"PK\x03\x04"),
            'xls'=>str_starts_with($b,"\xD0\xCF\x11\xE0") || preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<Workbook\b/i',$b),default=>false};
        if (!$valido) throw new DomainException('SUNAT no devolvió el archivo solicitado o su formato no es reconocible.');
        return ['body'=>$b,'nombre'=>$nombre,'tipo'=>match($extension){'pdf'=>'application/pdf','zip'=>'application/zip','xls'=>'application/vnd.ms-excel'}];
    }
}
