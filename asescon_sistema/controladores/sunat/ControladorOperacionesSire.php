<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/ServicioOperacionesSire.php';
final class ControladorOperacionesSire extends ServicioOperacionesSire
{
    protected function exigirEjecucion(string $libro):void
    {
        asesconExigirPermiso($this->conexion,'SIRE','ejecutar');
        asesconExigirPermiso($this->conexion,$libro,'ejecutar');
    }
    protected function auditar(string $accion,int $id,string $tabla,?string $registro,string $detalle):void
    {
        asesconRegistrarAuditoria($this->conexion,$accion,'SIRE',$id,$tabla,$registro,$detalle);
    }
                                                                                           
    protected function autorizarCliente(int $id):void
    {
        if(!asesconPuedeVerCliente($this->conexion,$id))throw new RuntimeException('Empresa no autorizada.');
    }
}
