<?php
declare(strict_types=1);
require_once __DIR__.'/ColaNocturna.php';
require_once __DIR__.'/EstadoSireGuardado.php';

                                                                                
final class PlanificadorSincronizacion
{
    private ColaNocturna $cola;

    public function __construct(private mysqli $db, ?ColaNocturna $cola=null)
    {
        $this->cola=$cola??new ColaNocturna();
    }

    public static function periodosSire():array
    {
                                                                         
                                                                              
        return [EstadoSireGuardado::periodoActual()];
    }

    public function programar(bool $forzar=false):array
    {
                                                                             
                                                                                 
        $resultado=$this->db->query("SELECT c.id_cliente,cs.usuario_sol,cs.clave_sol_cifrada,cs.client_id_sire,cs.client_secret_sire_cifrado FROM clientes c LEFT JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente AND cs.estado=1 WHERE c.estado='ACTIVO' ORDER BY c.id_cliente");
        $clientes=[];$trabajos=0;$periodos=self::periodosSire();
        $inventario=(new EstadoSireGuardado($this->db))->inventario();
        $hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
        $activas=[];
        foreach($this->cola->resumen() as $pendiente){
            if(in_array((string)($pendiente['estado']??''),['PENDIENTE','PROCESANDO'],true)){
                $clave=(int)($pendiente['id_cliente']??0).'|'.($pendiente['modulo']??'').'|'.($pendiente['periodo']??'');
                $activas[$clave]=true;
            }
        }
        $programar= function(int $id,string $modulo,string $periodo='')use(&$trabajos,&$activas,$forzar):void{
            $clave=$id.'|'.$modulo.'|'.$periodo;
            if(isset($activas[$clave]))return;
            if($this->cola->programar($id,$modulo,$periodo,null,$forzar)){
                $trabajos++;$activas[$clave]=true;
            }
        };
        while($cliente=$resultado->fetch_assoc()){
            $id=(int)$cliente['id_cliente'];if(isset($clientes[$id]))continue;$clientes[$id]=true;
            if(trim((string)$cliente['client_id_sire'])!==''&&trim((string)$cliente['client_secret_sire_cifrado'])!==''){
                foreach(['RVIE','RCE'] as $libro){
                    foreach($periodos as $periodo){
                        $previo=$inventario[$id][$libro][$periodo]??null;
                        $completo=is_array($previo)&&!empty($previo['guardado'])
                            &&substr((string)$previo['actualizado_en'],0,10)===$hoy;
                        if($completo)continue;
                        $programar($id,$libro,$periodo);
                    }
                }
            }
            if(trim((string)$cliente['usuario_sol'])!==''&&trim((string)$cliente['clave_sol_cifrada'])!==''){
                foreach(['DECLARACIONES','SOL','SUNAFIL'] as $modulo){
                    $programar($id,$modulo);
                }
            }
        }
        foreach(['PADRON_RUC','SSCO'] as $modulo)$programar(0,$modulo);
        return ['clientes'=>count($clientes),'trabajos'=>$trabajos,'periodos_sire'=>$periodos];
    }
}
