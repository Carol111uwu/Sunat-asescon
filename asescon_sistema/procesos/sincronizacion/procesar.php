<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
require_once dirname(__DIR__,2).'/servicios/sincronizacion/ColaNocturna.php';
                                                                             
                                                                              
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
require_once dirname(__DIR__,2).'/servicios/sincronizacion/EjecutorNocturno.php';
$cola=new ColaNocturna();
                                                                                 
$segundos=max(30,min(240,(int)(getenv('ASESCON_COLA_SEGUNDOS')?:'180')));
$limite=time()+$segundos;
do{
    $t=$cola->tomar();if(!$t)break;
    try{
        $r=(new EjecutorNocturno($conexion))->ejecutar($t);$estado=$r['estado'];unset($r['estado']);
                                                                                                  
        if(!empty($r['espera_buzon'])||!empty($r['espera_ticket'])){
            $t['intentos']=max(0,(int)$t['intentos']-1);
                                                                                                                
            $r['intentos']=$t['intentos'];$t['intentos']++;
        }
        unset($r['espera_buzon'],$r['espera_ticket']);
        $cola->terminar($t,$estado,$r);
    }catch(Throwable $e){
                                                                                
                                                                              
        $texto=strtoupper((string)$e->getMessage());
        $codigo='CONSULTA_FALLIDA';
        $detalle='La consulta no terminó. Los datos anteriores se conservaron; revisa el registro del módulo.';
        $terminal=false;
        if(in_array((string)$t['modulo'],['RVIE','RCE'],true)&&str_contains($texto,'YA TIENE COMPROBANTES GUARDADOS')){
            $codigo='SIRE_CERO_CON_HISTORIAL';
            $detalle='La propuesta descargada contiene cero comprobantes, pero hay movimientos anteriores guardados. No se borraron; revisa la discrepancia manualmente.';
            $terminal=true;
        }elseif(in_array((string)$t['modulo'],['RVIE','RCE'],true)
            &&(str_contains($texto,'FALTAN EVIDENCIAS DEL TICKET')||str_contains($texto,'LA ESTRUCTURA DE LA PROPUESTA'))){
            $codigo='SIRE_CERO_NO_CONFIRMADO';
            $detalle='No hay comprobantes verificables, pero no se pudo confirmar un archivo oficial vacío. Se conservaron los datos y el archivo para revisión.';
            $terminal=true;
        }elseif(str_contains($texto,'MIGRACION_DECLARACIONES_PENDIENTE')){
            $codigo='FALTAN_TABLAS_DECLARACIONES';
            $detalle='El módulo de declaraciones necesita una migración de tablas pendiente. Revisa su configuración antes de reintentar.';
        }elseif(str_contains($texto,'CREDENCIALES_NO_DESCIFRABLES')){
            $codigo='CREDENCIALES_NO_DESCIFRABLES';
            $detalle='La clave local no puede descifrar las credenciales actuales. No se modificaron los datos guardados.';
        }elseif(str_contains($texto,'SOL_ENTRADA_NO_DISPONIBLE')){
            $codigo='SUNAT_SOL_NO_DISPONIBLE';
            $detalle='La página inicial de SUNAT SOL no respondió correctamente. Es posible que requiera otro método de acceso.';
        }elseif($e instanceof mysqli_sql_exception){
            $codigo='ERROR_MYSQL';
            $detalle='Error de acceso a la base de datos durante la consulta; revisa el registro del servidor.';
        }elseif($e instanceof ExcepcionSunat){
            $codigo='CONSULTA_SUNAT_FALLIDA';
            $detalle='SUNAT rechazó o no completó la consulta; los datos anteriores se conservaron.';
        }
        error_log('Cron nocturno '.$t['modulo'].' empresa '.(int)$t['id_cliente'].' codigo '.$codigo.' tipo '.get_class($e));
        $cola->terminar($t,$terminal?'ERROR':'PENDIENTE',['codigo'=>$codigo,'mensaje'=>$detalle]);
    }
}while(time()<$limite);
