<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
require_once dirname(__DIR__,2).'/servicios/sunat/ServicioColaBuzonSol.php';
require_once dirname(__DIR__,2).'/servicios/sunafil/ServicioColaBuzonSunafil.php';

                                                                                        
                                                                                          
$intervalo=(int)(getenv('ASESCON_AVISOS_INTERVALO_MIN')?:'30');
$intervalo=max(10,min(1440,$intervalo));
$limite=(int)(getenv('ASESCON_AVISOS_POR_CICLO')?:'3');
$limite=max(1,min(12,$limite));
$clientes=[];
$r=$conexion->query("SELECT DISTINCT c.id_cliente FROM clientes c INNER JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente WHERE c.estado='ACTIVO' AND cs.estado=1 AND TRIM(COALESCE(cs.usuario_sol,''))<>'' AND TRIM(COALESCE(cs.clave_sol_cifrada,''))<>'' ORDER BY c.id_cliente");
while($fila=$r->fetch_assoc())$clientes[]=(int)$fila['id_cliente'];

foreach(['SOL'=>new ServicioColaBuzonSol(),'SUNAFIL'=>new ServicioColaBuzonSunafil()] as $buzon=>$cola){
    $elegibles=[];
    foreach($clientes as $cliente){
        $estado=$cola->obtenerEstadoCliente($cliente);
        if(in_array($estado['estado']??'',['PENDIENTE','PROCESANDO'],true))continue;
        $fecha=(string)($estado['finalizado_en']??$estado['solicitado_en']??$estado['actualizado_en']??'');
        $marca=$fecha!==''?strtotime($fecha):false;
                                                                                      
        $minutos=$buzon==='SOL'&&($estado['estado']??'')==='ADJUNTOS_PENDIENTES'?min(10,$intervalo):$intervalo;
        if($marca!==false&&$marca>time()-$minutos*60)continue;
        $elegibles[]=['cliente'=>$cliente,'marca'=>$marca===false?0:$marca];
    }
    usort($elegibles,static fn(array $a,array $b):int=>($a['marca']<=>$b['marca'])?:($a['cliente']<=>$b['cliente']));
    $encolados=0;
    foreach(array_slice($elegibles,0,$limite) as $item){
        try{$cola->solicitar($item['cliente'],0);$encolados++;}
        catch(Throwable $e){error_log('Refresco '.$buzon.' empresa '.$item['cliente'].': '.get_class($e));}
    }
    if($encolados>0)echo 'Buzon '.$buzon.': '.$encolados.' clientes en cola, intervalo minimo '.$intervalo.' min.'.PHP_EOL;
}
