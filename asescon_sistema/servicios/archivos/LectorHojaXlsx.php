<?php
declare(strict_types=1);
require_once __DIR__.'/LectorContenedorZip.php';

final class LectorHojaXlsx
{
    public function leer(string $bytes):array
    {
        $zip=new LectorContenedorZip($bytes);$nombres=$zip->nombres();$textos=[];$estilosFecha=[];
        if(in_array('xl/sharedStrings.xml',$nombres,true)){
            $xml=$this->seguro($zip->leer('xl/sharedStrings.xml'));
            preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s',$xml,$cadenas);
            foreach($cadenas[1] as $cadena)$textos[]=$this->texto($cadena);
        }
        if(in_array('xl/styles.xml',$nombres,true)){
            $xml=$this->seguro($zip->leer('xl/styles.xml'));$formatos=[];
            preg_match_all('/<numFmt\b[^>]*\/?\s*>/s',$xml,$fmt);
            foreach($fmt[0] as $f){$id=(int)$this->atributo($f,'numFmtId');$codigo=$this->atributo($f,'formatCode');$formatos[$id]=(bool)preg_match('/[dy]/i',preg_replace('/"[^"]*"|\[[^\]]*\]/','',$codigo));}
            if(preg_match('/<cellXfs\b[^>]*>(.*?)<\/cellXfs>/s',$xml,$xfs)){
                preg_match_all('/<xf\b[^>]*>/s',$xfs[1],$xf);
                foreach($xf[0] as $i=>$f){$id=(int)$this->atributo($f,'numFmtId');$estilosFecha[$i]=($id>=14&&$id<=22)||in_array($id,[45,46,47],true)||($formatos[$id]??false);}
            }
        }
        $libro=$this->seguro($zip->leer('xl/workbook.xml'));$base=str_contains($libro,'date1904="1"')?'1904-01-01':'1899-12-30';
        $hojas=array_values(array_filter($nombres,static fn(string $n):bool=>(bool)preg_match('~^xl/worksheets/sheet\d+\.xml$~',$n)));sort($hojas,SORT_NATURAL);
        if(!$hojas)throw new DomainException('El XLSX no contiene una hoja de datos.');
        $xml=$this->seguro($zip->leer($hojas[0]));preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s',$xml,$rows);
        if(count($rows[1])>20001)throw new DomainException('La hoja supera los 20 000 registros.');
        $salida=[];
        foreach($rows[1] as $row){
            preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/s',$row,$cells,PREG_SET_ORDER);$fila=[];
            foreach($cells as $cell){
                $ref=$this->atributo('<c '.$cell[1].'>','r');if(!preg_match('/^([A-Z]+)\d+$/',$ref,$r))continue;
                $indice=0;foreach(str_split($r[1]) as $letra)$indice=$indice*26+ord($letra)-64;$indice--;
                if($indice>255)throw new DomainException('La hoja supera las 256 columnas admitidas.');
                $tipo=$this->atributo('<c '.$cell[1].'>','t');$estilo=(int)$this->atributo('<c '.$cell[1].'>','s');$cuerpo=$cell[2]??'';
                preg_match('/<v\b[^>]*>(.*?)<\/v>/s',$cuerpo,$v);$valor=html_entity_decode($v[1]??'',ENT_QUOTES|ENT_XML1,'UTF-8');
                if($tipo==='s')$valor=$textos[(int)$valor]??throw new DomainException('Referencia de texto XLSX inválida.');
                elseif($tipo==='inlineStr')$valor=$this->texto($cuerpo);
                elseif($tipo==='e')throw new DomainException('La hoja contiene una celda con error.');
                elseif($valor!==''&&($estilosFecha[$estilo]??false)&&is_numeric($valor)){
                    $dias=(int)floor((float)$valor);if($dias<0||$dias>100000)throw new DomainException('Fecha XLSX fuera de rango.');
                    $valor=(new DateTimeImmutable($base))->modify('+'.$dias.' days')->format('Y-m-d');
                }elseif(is_numeric($valor)&&preg_match('/e[+-]?\d+$/i',$valor))$valor=number_format((float)$valor,0,'.','');
                $fila[$indice]=$valor;
            }
            if($fila){$fila=array_replace(array_fill(0,max(array_keys($fila))+1,''),$fila);ksort($fila);$salida[]=array_values($fila);}
        }
        return $salida;
    }
    private function seguro(string $xml):string
    {
        if(preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)||!preg_match('//u',$xml))throw new DomainException('El XML del XLSX no es seguro o no está en UTF-8.');
        return $xml;
    }
    private function atributo(string $tag,string $nombre):string
    {
        preg_match('/\b'.preg_quote($nombre,'/').'\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27)/s',$tag,$m);
        return html_entity_decode($m[1]??$m[2]??'',ENT_QUOTES|ENT_XML1,'UTF-8');
    }
    private function texto(string $xml):string
    {
        preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s',$xml,$m);
        return html_entity_decode(implode('',$m[1]),ENT_QUOTES|ENT_XML1,'UTF-8');
    }
}
