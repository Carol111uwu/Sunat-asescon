<?php
declare(strict_types=1);
require_once __DIR__.'/ServicioColaBuzonSol.php';
require_once __DIR__.'/ServicioEstadoAdjuntosSol.php';
require_once __DIR__.'/../archivos/ServicioArchivosPrivados.php';

final class ServicioPrecargaAdjuntosSol
{
    public function __construct(private mysqli $conexion){}
    public function pendientes(int $cliente,bool $soloDisponibles=true,int $limite=20):array
    {
        $s=$this->conexion->prepare('SELECT id_documento,identificador_externo,tipo_documento,observaciones,asunto,COALESCE(fecha_recepcion,fecha_documento) fecha,archivo FROM buzon_sol_sunat WHERE id_cliente=? ORDER BY COALESCE(fecha_recepcion,fecha_documento) DESC,id_documento DESC');
        $s->bind_param('i',$cliente);$s->execute();$r=$s->get_result();$estados=new ServicioEstadoAdjuntosSol();$archivos=new ServicioArchivosPrivados();$pendientes=[];
        while($d=$r->fetch_assoc()){
            $id=(int)$d['id_documento'];
            if($archivos->resolver((string)($d['archivo']??''),'SOL'))continue;
            if($soloDisponibles&&!$estados->puedeIntentar($cliente,$id))continue;
            unset($d['archivo']);$pendientes[]=$d;
            if($limite>0&&count($pendientes)>=$limite)break;
        }
        $s->close();return $pendientes;
    }
    public function programarExistentes():void
    {
        $r=$this->conexion->query("SELECT DISTINCT b.id_cliente FROM buzon_sol_sunat b INNER JOIN clientes c ON c.id_cliente=b.id_cliente INNER JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente WHERE c.estado='ACTIVO' AND cs.estado=1 AND TRIM(COALESCE(cs.usuario_sol,''))<>'' ORDER BY b.id_cliente");
        $cola=new ServicioColaBuzonSol();
        while($c=$r->fetch_assoc()){
            $id=(int)$c['id_cliente'];
            if(in_array($cola->obtenerEstadoCliente($id)['estado']??'',['PENDIENTE','PROCESANDO'],true))continue;
            if($this->pendientes($id,true,1))$cola->solicitar($id,0,null,true);
        }
    }
}
