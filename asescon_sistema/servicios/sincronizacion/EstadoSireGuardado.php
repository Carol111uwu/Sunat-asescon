<?php
declare(strict_types=1);

                                                                                   
final class EstadoSireGuardado
{
    public function __construct(private mysqli $db){}

    public static function periodoActual(?DateTimeImmutable $hoy=null):string
    {
        return ($hoy??new DateTimeImmutable('now',new DateTimeZone('America/Lima')))
            ->setTimezone(new DateTimeZone('America/Lima'))->format('Ym');
    }

    public static function periodosActuales(?DateTimeImmutable $hoy=null):array
    {
        $ahora=($hoy??new DateTimeImmutable('now',new DateTimeZone('America/Lima')))
            ->setTimezone(new DateTimeZone('America/Lima'));
        return self::periodosAnio((int)$ahora->format('Y'),$ahora);
    }

                                                                                       
    public static function periodosAnio(int $anio,?DateTimeImmutable $hoy=null):array
    {
        $ahora=($hoy??new DateTimeImmutable('now',new DateTimeZone('America/Lima')))
            ->setTimezone(new DateTimeZone('America/Lima'));
        if($anio<2021||$anio>(int)$ahora->format('Y'))throw new InvalidArgumentException('El año debe estar entre 2021 y el año actual.');
        $limite=$anio===(int)$ahora->format('Y')?(int)$ahora->format('n'):12;
        $periodos=[];
        for($i=1;$i<=$limite;$i++){
            $periodos[]=$anio.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        }
        return $periodos;
    }

    private static function tabla(string $libro):string
    {
        return match($libro){'RVIE'=>'rvie','RCE'=>'rce',default=>throw new InvalidArgumentException('Libro SIRE inválido.')};
    }

                                                                                                 
    public function consultar(int $cliente,string $libro,string $periodo,?DateTimeImmutable $hoy=null):?array
    {
        $tabla=self::tabla($libro);
        if($cliente<=0||!preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D',$periodo))return null;
        $s=$this->db->prepare("SELECT cantidad_registros,estado,fecha_actualizacion FROM {$tabla} WHERE id_cliente=? AND periodo=? LIMIT 1");
        if(!$s)throw new RuntimeException('No se pudo consultar el historial SIRE guardado.');
        $s->bind_param('is',$cliente,$periodo);
        $s->execute();$fila=$s->get_result()->fetch_assoc();$s->close();
        if(!$fila||strtoupper((string)($fila['estado']??''))!=='PROCESADO')return null;
        $ahora=($hoy??new DateTimeImmutable('now',new DateTimeZone('America/Lima')))
            ->setTimezone(new DateTimeZone('America/Lima'));
        $actual=$periodo===$ahora->format('Ym');
        $fecha=substr((string)($fila['fecha_actualizacion']??''),0,10);
        return ['registros'=>max(0,(int)($fila['cantidad_registros']??0)),
                'fecha_actualizacion'=>(string)($fila['fecha_actualizacion']??''),
                'completo'=>!$actual||$fecha===$ahora->format('Y-m-d'),
                'actual'=>$actual];
    }

                                                                              
    public function inventario(?int $anio=null):array
    {
        $anio??=(int)(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y');
        $periodos=self::periodosAnio($anio);$inicio=$periodos[0];$fin=end($periodos);
        $datos=[];
        foreach(['RVIE','RCE'] as $libro){
            $tabla=self::tabla($libro);
            $s=$this->db->prepare("SELECT id_cliente,periodo,cantidad_registros,estado,fecha_actualizacion FROM {$tabla} WHERE periodo BETWEEN ? AND ?");
            if(!$s)continue;
            $s->bind_param('ss',$inicio,$fin);$s->execute();$resultado=$s->get_result();
            while($fila=$resultado->fetch_assoc()){
                $id=(int)$fila['id_cliente'];$periodo=(string)$fila['periodo'];
                if(!in_array($periodo,$periodos,true))continue;
                $fecha=(string)($fila['fecha_actualizacion']??'');
                $datos[$id][$libro][$periodo]=[
                    'guardado'=>strtoupper((string)($fila['estado']??''))==='PROCESADO',
                    'registros'=>max(0,(int)($fila['cantidad_registros']??0)),
                    'actualizado_en'=>$fecha,
                ];
            }
            $s->close();
        }
        return $datos;
    }
}
