<?php
declare(strict_types=1);
require_once __DIR__.'/ColaNocturna.php';
require_once __DIR__.'/EstadoSireGuardado.php';

                                                                                
final class PlanificadorSincronizacion
{
    private const MAX_HISTORICOS_ACTIVOS = 8;

    private ColaNocturna $cola;

    public function __construct(private mysqli $db, ?ColaNocturna $cola=null)
    {
        $this->cola=$cola??new ColaNocturna();
    }

    public function programar(bool $forzar=false, bool $soloHistorico=false):array
    {
        $ahora = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $periodoActual = EstadoSireGuardado::periodoActual($ahora);
        $periodos = EstadoSireGuardado::periodosActuales($ahora);
        $hoy = $ahora->format('Y-m-d');
        $resultado=$this->db->query("SELECT c.id_cliente,cs.usuario_sol,cs.clave_sol_cifrada,cs.client_id_sire,cs.client_secret_sire_cifrado FROM clientes c LEFT JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente AND cs.estado=1 WHERE c.estado='ACTIVO' ORDER BY c.id_cliente");
        $clientes=[];$clientesSire=[];$trabajos=0;
        $inventario=(new EstadoSireGuardado($this->db))->inventario((int)$ahora->format('Y'));
        $activas=[];
        $historicosRegistrados=[];
        $cuposHistoricos=self::MAX_HISTORICOS_ACTIVOS;
        foreach($this->cola->resumen() as $pendiente){
            $clave=(int)($pendiente['id_cliente']??0).'|'.($pendiente['modulo']??'').'|'.($pendiente['periodo']??'');
            $activa=in_array((string)($pendiente['estado']??''),['PENDIENTE','PROCESANDO'],true);
            if($activa)$activas[$clave]=true;
            if(ColaNocturna::esHistoricoSire($pendiente)){
                $historicosRegistrados[$clave]=true;
                if($activa)$cuposHistoricos--;
            }
        }
        $programar= function(int $id,string $modulo,string $periodo='')use(&$trabajos,&$activas,$forzar,$hoy):bool{
            $clave=$id.'|'.$modulo.'|'.$periodo;
            if(isset($activas[$clave])||!$this->cola->programar($id,$modulo,$periodo,$hoy,$forzar))return false;
            $trabajos++;
            $activas[$clave]=true;
            return true;
        };
        while($cliente=$resultado->fetch_assoc()){
            $id=(int)$cliente['id_cliente'];if(isset($clientes[$id]))continue;$clientes[$id]=true;
            if(trim((string)$cliente['client_id_sire'])!==''&&trim((string)$cliente['client_secret_sire_cifrado'])!==''){
                $clientesSire[]=$id;
                if(!$soloHistorico){
                    foreach(['RVIE','RCE'] as $libro){
                        $previo=$inventario[$id][$libro][$periodoActual]??null;
                        $completo=is_array($previo)&&!empty($previo['guardado'])
                            &&substr((string)$previo['actualizado_en'],0,10)===$hoy;
                        if(!$completo)$programar($id,$libro,$periodoActual);
                    }
                }
            }
            if($soloHistorico)continue;
            if(trim((string)$cliente['usuario_sol'])!==''&&trim((string)$cliente['clave_sol_cifrada'])!==''){
                foreach(['DECLARACIONES','SOL','SUNAFIL'] as $modulo){
                    $programar($id,$modulo);
                }
            }
        }
        if(!$soloHistorico){
            foreach(['PADRON_RUC','SSCO'] as $modulo)$programar(0,$modulo);
        }
        $historicos=0;
        if(!$forzar && $cuposHistoricos>0){
            // Empieza por el último mes cerrado y pasa por todas las empresas antes de retroceder.
            foreach(array_reverse($periodos) as $periodo){
                if($periodo===$periodoActual)continue;
                foreach($clientesSire as $id){
                    foreach(['RVIE','RCE'] as $libro){
                        $clave=$id.'|'.$libro.'|'.$periodo;
                        if(isset($historicosRegistrados[$clave])||!empty($inventario[$id][$libro][$periodo]['guardado']))continue;
                        if($programar($id,$libro,$periodo)){
                            $historicos++;
                            if(--$cuposHistoricos===0)break 3;
                        }
                    }
                }
            }
        }
        return ['clientes'=>count($clientes),'trabajos'=>$trabajos,'historicos'=>$historicos];
    }
}
