<?php
declare(strict_types=1);

final class GeneradorPdfTabla
{
    public function crear(string $titulo,string $subtitulo,array $cabeceras,array $filas,array $anchos=[]):string
    {
        if(!$cabeceras)throw new InvalidArgumentException('Faltan columnas para el PDF.');
        $anchos=$anchos?:array_fill(0,count($cabeceras),762/count($cabeceras));
        if(count($anchos)!==count($cabeceras))throw new InvalidArgumentException('Anchos de columnas inválidos.');
        $paginas=[];$contenido='';$y=0;
        $nueva=function()use(&$contenido,&$y,&$paginas,$titulo,$subtitulo,$cabeceras,$anchos):void{
            if($contenido!=='')$paginas[]=$contenido;
            $contenido=$this->texto(40,561,$titulo,14,true).$this->texto(40,541,$subtitulo,8);
            $y=517;$this->fila($contenido,$y,$cabeceras,$anchos,true);
        };
        $nueva();
        foreach($filas as $fila){
            $alto=$this->alto($fila,$anchos);if($y-$alto<35)$nueva();
            $this->fila($contenido,$y,$fila,$anchos,false);
        }
        if(!$filas)$contenido.=$this->texto(40,$y-16,'No hay liquidaciones guardadas para los filtros seleccionados.',9);
        $paginas[]=$contenido;$objetos=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($paginas as $i=>$p){$c=5+$i*2;$pagina=$c+1;$p.=$this->texto(40,18,'ASESCON · '.($i+1).' / '.count($paginas),8);$objetos[$c]='<< /Length '.strlen($p).' >>'."\nstream\n".$p."\nendstream";$objetos[$pagina]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$c.' 0 R >>';$kids[]=$pagina.' 0 R';}
        $objetos[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objetos);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objetos as $n=>$obj){$offsets[$n]=strlen($pdf);$pdf.=$n." 0 obj\n".$obj."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        for($i=1;$i<count($offsets);$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
        return $pdf.'trailer << /Size '.count($offsets).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";
    }
    private function lineas(string $v,float $ancho):array
    {
        $limite=max(6,(int)floor(($ancho-8)/4.8));$caracteres=preg_split('//u',preg_replace('/\s+/u',' ',trim($v))??'',-1,PREG_SPLIT_NO_EMPTY)?:[];
        return array_map(static fn(array $p):string=>implode('',$p),array_chunk($caracteres,$limite))?:[''];
    }
    private function alto(array $fila,array $anchos):int
    {
        $max=1;foreach($fila as $i=>$valor)$max=max($max,min(10,count($this->lineas((string)$valor,$anchos[$i]))));return $max*11+9;
    }
    private function fila(string &$contenido,float|int &$y,array $fila,array $anchos,bool $cabecera):void
    {
        $alto=$this->alto($fila,$anchos);$x=40;
        if($cabecera)$contenido.="0.92 0.95 0.98 rg 40 ".($y-$alto)." 762 $alto re f 0 0 0 rg\n";
        foreach($fila as $i=>$valor){foreach(array_slice($this->lineas((string)$valor,$anchos[$i]),0,10) as $j=>$linea)$contenido.=$this->texto($x+4,$y-12-$j*11,$linea,8,$cabecera);$x+=$anchos[$i];}
        $contenido.='0.80 0.84 0.88 RG 40 '.($y-$alto).' m 802 '.($y-$alto)." l S 0 0 0 RG\n";$y-=$alto;
    }
    private function texto(float|int $x,float|int $y,string $texto,int $tamano,bool $negrita=false):string
    {
        if(function_exists('iconv'))$texto=iconv('UTF-8','Windows-1252//TRANSLIT',$texto)?:'';
        else $texto=strtr($texto,['á'=>"\xE1",'é'=>"\xE9",'í'=>"\xED",'ó'=>"\xF3",'ú'=>"\xFA",'ñ'=>"\xF1",'Á'=>"\xC1",'É'=>"\xC9",'Í'=>"\xCD",'Ó'=>"\xD3",'Ú'=>"\xDA",'Ñ'=>"\xD1",'·'=>"\xB7",'—'=>'-','–'=>'-']);
        $texto=strtr($texto,['\\'=>'\\\\','('=>'\\(',')'=>'\\)',"\r"=>' ',"\n"=>' ']);
        return 'BT /'.($negrita?'F2':'F1').' '.$tamano.' Tf 1 0 0 1 '.$x.' '.$y.' Tm ('.$texto.") Tj ET\n";
    }
}
