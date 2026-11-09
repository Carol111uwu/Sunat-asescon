<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';
                                                                                               
final class ColaNocturna
{
                                                                         
                                                                   
    private const ORDEN_MODULOS=['PADRON_RUC'=>0,'SSCO'=>1,'SOL'=>2,'SUNAFIL'=>3,'RVIE'=>4,'RCE'=>5,'DECLARACIONES'=>6];
    public function __construct(private ?string $base=null)
    {
        $this->base??=asesconRutaAlmacenamiento().'/sincronizacion';
        if(!is_dir($this->base)&&!mkdir($this->base,0700,true)&&!is_dir($this->base))throw new RuntimeException('No se pudo preparar la cola nocturna.');
    }
    public static function enHorario(?DateTimeImmutable $ahora=null):bool
    {
        $ahora=($ahora??new DateTimeImmutable())->setTimezone(new DateTimeZone('America/Lima'));
                                                                        
                                                                        
        return $ahora->format('H')==='02';
    }

                                                                                  
    public function estaPausada(): bool
    {
        return is_file($this->base.'/pausa.flag');
    }

    public function establecerPausa(bool $pausar): void
    {
        $this->bloquear(function()use($pausar):void{
            $ruta=$this->base.'/pausa.flag';
            if($pausar){
                if(file_put_contents($ruta,date(DATE_ATOM),LOCK_EX)===false)throw new RuntimeException('No se pudo pausar la sincronización.');
                chmod($ruta,0600);
            }elseif(is_file($ruta)&&!unlink($ruta)){
                throw new RuntimeException('No se pudo reanudar la sincronización.');
            }
        });
    }
    public function programar(int $cliente,string $modulo,string $periodo='',?string $fecha=null,bool $forzar=false):bool
    {
        if($cliente<0||!in_array($modulo,['SOL','SUNAFIL','RVIE','RCE','DECLARACIONES','PADRON_RUC','SSCO'],true))throw new InvalidArgumentException('Trabajo no permitido.');
        $fecha??=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
        $id=hash('sha256',$fecha.'|'.$cliente.'|'.$modulo.'|'.$periodo);
        return $this->bloquear(function()use($id,$cliente,$modulo,$periodo,$fecha,$forzar):bool{
            $ruta=$this->ruta($id);
            if(is_file($ruta)){
                $anterior=$this->leer($ruta);
                if(!$forzar||in_array(($anterior['estado']??''),['PENDIENTE','PROCESANDO'],true))return false;
            }
            $this->guardar($ruta,['id'=>$id,'id_cliente'=>$cliente,'modulo'=>$modulo,'periodo'=>$periodo,'fecha'=>$fecha,'estado'=>'PENDIENTE','intentos'=>0,'reintentar'=>0,'actualizado_en'=>date(DATE_ATOM)]);
            return true;
        });
    }
    public function tomar():?array
    {
        return $this->bloquear(function(){
            if($this->estaPausada())return null;
            $candidatos=[];
            foreach(glob($this->base.'/trabajo_*.json')?:[] as $ruta){
                $t=$this->leer($ruta);
                if(!in_array($t['estado']??'',['PENDIENTE','PROCESANDO'],true))continue;
                $hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
                if(($t['fecha']??'')!==$hoy)continue;
                $candidatos[]=['ruta'=>$ruta,'trabajo'=>$t];
            }
                                                                             
                                                                     
            usort($candidatos,static function($a,$b):int{
                $x=$a['trabajo'];$y=$b['trabajo'];
                $xGlobal=(int)($x['id_cliente']??0)===0?0:1;
                $yGlobal=(int)($y['id_cliente']??0)===0?0:1;
                return ($xGlobal<=>$yGlobal)
                    ?: (int)($x['intentos']??0)<=>(int)($y['intentos']??0)
                    ?: (int)($x['id_cliente']??0)<=>(int)($y['id_cliente']??0)
                    ?: (self::ORDEN_MODULOS[$x['modulo']??'']??99)<=>(self::ORDEN_MODULOS[$y['modulo']??'']??99)
                    ?: strcmp((string)($x['periodo']??''),(string)($y['periodo']??''))
                    ?: strcmp((string)($x['id']??''),(string)($y['id']??''));
            });
                                                                              
                                                                            
                                                                                
            foreach($candidatos as $candidato){
                $ruta=$candidato['ruta'];$t=$candidato['trabajo'];
                if(($t['estado']??'')==='PROCESANDO'&&(int)($t['iniciado']??0)<time()-1200){$t['estado']='PENDIENTE';}
                if(($t['estado']??'')==='PROCESANDO')continue;
                if(($t['estado']??'')!=='PENDIENTE'||(int)($t['reintentar']??0)>time())continue;
                if((int)$t['intentos']>=4){$t['estado']='ERROR';$this->guardar($ruta,$t);continue;}
                $t['estado']='PROCESANDO';$t['intentos']++;$t['iniciado']=time();$t['actualizado_en']=date(DATE_ATOM);$this->guardar($ruta,$t);return $t;
            }
            return null;
        });
    }
    public function terminar(array $trabajo,string $estado,array $datos=[]):void
    {
        if(!in_array($estado,['COMPLETADO','PENDIENTE','ERROR'],true))throw new InvalidArgumentException('Estado no permitido.');
        $this->bloquear(function()use($trabajo,$estado,$datos){
            $ruta=$this->ruta($trabajo['id']);$actual=$this->leer($ruta);
            if(($actual['iniciado']??0)!==($trabajo['iniciado']??0)||($actual['intentos']??0)!==($trabajo['intentos']??0))return;
            if($estado==='PENDIENTE'&&(int)($datos['intentos']??$actual['intentos'])>=4)$estado='ERROR';
            if($estado==='COMPLETADO'){$actual['codigo']='';$actual['mensaje']='';}
            $this->guardar($ruta,array_replace($actual,$datos,['estado'=>$estado,'reintentar'=>time()+min(1800,120*(2**max(0,(int)$actual['intentos']-1))),'actualizado_en'=>date(DATE_ATOM)]));
        });
    }
    public function resumen():array
    {
        $r=[];$limite=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
        foreach(glob($this->base.'/trabajo_*.json')?:[] as $ruta){$d=$this->leer($ruta);if(($d['fecha']??'')>=$limite)$r[]=$d;}
        usort($r,static fn($a,$b)=>strcmp($b['actualizado_en'],$a['actualizado_en']));return $r;
    }
    private function ruta(string $id):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$id))throw new InvalidArgumentException('Trabajo inválido.');return $this->base.'/trabajo_'.$id.'.json';
    }
    private function leer(string $ruta):array{return is_file($ruta)?(json_decode((string)file_get_contents($ruta),true)?:[]):[];}
    private function guardar(string $ruta,array $datos):void
    {
        $tmp=tempnam($this->base,'cola_');if($tmp===false)throw new RuntimeException('No se pudo guardar el estado.');
        try{$json=json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$json)!==strlen($json))throw new RuntimeException('No se pudo guardar el estado.');chmod($tmp,0600);if(!rename($tmp,$ruta))throw new RuntimeException('No se pudo confirmar el estado.');}finally{if(is_file($tmp))unlink($tmp);}
    }
    private function bloquear(callable $accion):mixed
    {
        $f=fopen($this->base.'/cola.lock','c+');if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Cola ocupada.');
        try{return $accion();}finally{flock($f,LOCK_UN);fclose($f);}
    }
}
