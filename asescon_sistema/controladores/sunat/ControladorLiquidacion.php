<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/Liquidacion.php';
require_once __DIR__ . '/../../servicios/sunat/ServicioPreparacionLiquidacion.php';
final class ControladorLiquidacion
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $periodo = ''): array
    {
        asesconExigirPermiso($this->conexion, 'LIQUIDACIONES', 'ver');
        $filas = (new Liquidacion($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $periodo);
        $clientes = [];
        $r = asesconClientesVisibles($this->conexion, true);
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $clientes[] = $x;
            }
        }
return compact('filas', 'clientes');
    }

    public function guardar(array $p): void
    {
        asesconExigirPermiso($this->conexion,'LIQUIDACIONES','editar');
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_liquidacion')){
            http_response_code(403);exit('Solicitud no válida.');
        }
        $id=(int)($p['id_cliente']??0);$periodo=(string)($p['periodo']??'');
        try{
            if(!asesconPuedeGestionarCliente($this->conexion,$id))throw new DomainException('Cliente no autorizado.');
            (new ServicioPreparacionLiquidacion($this->conexion))->guardar($id,$periodo,$p);
            $_SESSION['mensaje_liquidacion']='Liquidación guardada. Los cambios se reflejan en sus exportaciones.';
        }catch(Throwable $e){error_log('Guardar liquidación: '.get_class($e));$_SESSION['error_liquidacion']=$e instanceof DomainException?$e->getMessage():'No se pudo guardar la liquidación.';}
        asesconRedirigir('vistas/sunat/liquidaciones_impuestos.php',['periodo'=>$periodo,'q'=>(string)($p['ruc']??''),'ver_cliente'=>$id]);
    }

}
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    (new ControladorLiquidacion($conexion))->guardar($_POST);
}
