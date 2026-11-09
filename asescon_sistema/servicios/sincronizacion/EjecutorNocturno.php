<?php
declare(strict_types=1);
require_once __DIR__.'/ColaNocturna.php';
require_once __DIR__.'/EstadoSireGuardado.php';
final class EjecutorNocturno
{
    public function __construct(private mysqli $db){}
    public function ejecutar(array $t):array
    {
        $id=(int)$t['id_cliente'];$modulo=$t['modulo'];
        if($id>0){$s=$this->db->prepare("SELECT id_cliente FROM clientes WHERE id_cliente=? AND estado='ACTIVO'");$s->bind_param('i',$id);$s->execute();if(!$s->get_result()->fetch_assoc())return ['estado'=>'ERROR','codigo'=>'CLIENTE_INACTIVO'];}
        if(in_array($modulo,['SOL','SUNAFIL'],true)){
            if($modulo==='SOL'){require_once __DIR__.'/../sunat/ServicioColaBuzonSol.php';$cola=new ServicioColaBuzonSol();}
            else{require_once __DIR__.'/../sunafil/ServicioColaBuzonSunafil.php';$cola=new ServicioColaBuzonSunafil();}
            $estado=$cola->obtenerEstadoCliente($id);
                                                                               
                                                                              
                                                                           
            $inicioBuzon=(int)($t['buzon_iniciado']??0)?:time();
            if(time()-$inicioBuzon>=7200 && !in_array($estado['estado']??'',['COMPLETADO','ADJUNTOS_PENDIENTES'],true)){
                return ['estado'=>'ERROR','codigo'=>'BUZON_SIN_RESPUESTA',
                    'mensaje'=>'El trabajador del buzón no terminó dentro de dos horas. Revisa su cron y permisos; los documentos anteriores se conservaron.'];
            }
                                                                                         
            if(in_array($estado['estado']??'',['COMPLETADO','ADJUNTOS_PENDIENTES'],true)&&substr($estado['finalizado_en']??'',0,10)>=$t['fecha']&&empty($estado['id_documento']))return ['estado'=>'COMPLETADO','registros'=>max(0,(int)($estado['total']??0)),'pdf_pendientes'=>max(0,(int)($estado['pdf_pendientes']??0)), 'codigo'=>($estado['estado']??'')==='ADJUNTOS_PENDIENTES'?'PDF_PENDIENTES':'','mensaje'=>($estado['estado']??'')==='ADJUNTOS_PENDIENTES'?'Bandeja guardada; quedan PDF pendientes.':'Bandeja guardada.'];
            if(!in_array($estado['estado']??'',['PENDIENTE','PROCESANDO'],true)||empty($t['trabajo_buzon'])){
                $esReintento=($estado['estado']??'')==='ERROR';$estado=$cola->solicitar($id,0);return ['estado'=>'PENDIENTE','trabajo_buzon'=>$estado['id_trabajo']??'','buzon_iniciado'=>$inicioBuzon,'espera_buzon'=>!$esReintento,'codigo'=>$esReintento?($modulo==='SOL'?'BUZON_SOL_FALLIDO':'BUZON_SUNAFIL_FALLIDO'):''];
            }
            return ['estado'=>'PENDIENTE','trabajo_buzon'=>$estado['id_trabajo']??'','buzon_iniciado'=>$inicioBuzon,'espera_buzon'=>true];
        }
        if(in_array($modulo,['RVIE','RCE'],true)){
                                                                         
                                                                             
            $periodo=(string)$t['periodo'];
            $guardado=(new EstadoSireGuardado($this->db))->consultar($id,$modulo,$periodo);
            if($guardado!==null&&$guardado['completo']){
                return ['estado'=>'COMPLETADO','registros'=>$guardado['registros'],
                        'omitido'=>true,'mensaje'=>'Período ya guardado y vigente; no se volvió a consultar SUNAT.'];
            }
            require_once __DIR__.'/../sunat/ServicioOperacionesSire.php';
            $s=new ServicioOperacionesSire($this->db);
            $ticket=$t['ticket']??'';
            $inicioTicket=(int)($t['ticket_iniciado']??0)?:time();
            if($ticket===''){$sol=$s->solicitar($id,$modulo,$periodo);$ticket=$sol['num_ticket'];$inicioTicket=time();}
            $estado=$s->estado($id,$modulo,$periodo,$ticket);
            if(empty($estado['terminado'])){
                if(time()-$inicioTicket>=7200){
                    return ['estado'=>'ERROR','ticket'=>$ticket,'codigo'=>'TICKET_SUNAT_SIN_RESPUESTA',
                        'mensaje'=>'SUNAT no finalizó este ticket después de dos horas. El período no se considera vacío ni completo.'];
                }
                return ['estado'=>'PENDIENTE','ticket'=>$ticket,'ticket_iniciado'=>$inicioTicket,'codigo'=>'TICKET_EN_PROCESO','espera_ticket'=>true];
            }
            $r=$s->descargar($id,$modulo,$periodo,$ticket);
            if(empty($r['descargado'])){
                if(time()-$inicioTicket>=7200){
                    return ['estado'=>'ERROR','ticket'=>$ticket,'codigo'=>'TICKET_SUNAT_SIN_RESPUESTA',
                        'mensaje'=>'SUNAT no entregó el archivo dentro del plazo. No se registraron ventas ni compras ficticias.'];
                }
                return ['estado'=>'PENDIENTE','ticket'=>$ticket,'ticket_iniciado'=>$inicioTicket,'espera_ticket'=>true];
            }
            try{$inc=$s->inconsistencias($id,$modulo,$periodo);}catch(Throwable){$inc=['disponible'=>false];}
            return ['estado'=>'COMPLETADO','ticket'=>$ticket,'registros'=>(int)$r['cantidad_registros'],'inconsistencias'=>$inc];
        }
        if($modulo==='DECLARACIONES'){
            require_once __DIR__.'/../sunat/CargadorCredencialesSunat.php';
            require_once __DIR__.'/../sunat/declaraciones_pagos/SesionConsultaSol.php';
            require_once __DIR__.'/../sunat/declaraciones_pagos/ConsultaDeclaracionesSunat.php';
            require_once __DIR__.'/../../modelos/sunat/declaraciones_pagos/RepositorioDeclaracionesOficiales.php';
            $repo=new RepositorioDeclaracionesOficiales($this->db);
            if(!$repo->disponible())throw new RuntimeException('MIGRACION_DECLARACIONES_PENDIENTE');
            $c=(new CargadorCredencialesSunat($this->db))->obtenerSolPorCliente($id);$http=new TransporteConsultaSol();
            $entrada=$http->solicitar('https://www.sunat.gob.pe/p/js/apps/main.js');
            if($entrada['status']!==200)throw new RuntimeException('SOL_ENTRADA_NO_DISPONIBLE');
            $sesion=SesionConsultaSol::iniciar($c,$http,$entrada['body']);
            $s=new ConsultaDeclaracionesSunat($http,$sesion['token'],$c['ruc']);
            $hoy=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));
                                                                            
                                                                            
            $consulta=$s->consultar($hoy->format('Y-m').'-01',$hoy->format('Y-m-d'));
            $repo->guardar($id,$consulta);return ['estado'=>'COMPLETADO','registros'=>count($consulta['filas'])];
        }
        if($modulo==='PADRON_RUC'){
            require_once __DIR__.'/../sunat/ServicioPadronSunat.php';
            $padron=new ServicioPadronSunat();
            $padron->actualizarAhora(false);
            if(!$padron->cacheDisponible())throw new RuntimeException('PADRON_RUC_NO_DISPONIBLE');
            return ['estado'=>'COMPLETADO','mensaje'=>'Catálogo de RUC disponible para consultas del día.'];
        }
        if($modulo==='SSCO'){
            require_once __DIR__.'/../sunat/LectorPadronSsco.php';require_once __DIR__.'/../../modelos/sunat/PadronSsco.php';
            $registros=(new LectorPadronSsco())->descargar();
            if(count($registros)===0)throw new RuntimeException('PADRON_SSCO_VACIO_NO_CONFIRMADO');
            (new PadronSsco($this->db))->guardar($registros,'SUNAT');
            return ['estado'=>'COMPLETADO','registros'=>count($registros)];
        }
        throw new InvalidArgumentException('Trabajo no soportado.');
    }
}
